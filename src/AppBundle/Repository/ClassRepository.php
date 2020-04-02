<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/06/2017
 * Time: 17:15
 */

namespace AppBundle\Repository;


use AppBundle\Entity\OntoClass;
use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\Property;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\QueryBuilder;

class ClassRepository extends EntityRepository
{
    /**
     * @return OntoClass[]
     */
    public function findAllOrderedById()
    {
        return $this->createQueryBuilder('class')
            ->join('class.namespaces','nspc')
            ->addSelect('nspc')
            ->leftJoin('nspc.referencedVersion', 'referencedVersion')
            ->addSelect('referencedVersion')
            ->orderBy('class.id','DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * @return OntoClass[]
     */
    public function findFilteredByPublicProjectOrderedById()
    {
        return $this->createQueryBuilder('class')
            ->join('class.namespaces','nspc')
            ->join('nspc.projects', 'prj')
            ->addSelect('nspc')
            ->leftJoin('nspc.referencedVersion', 'referencedVersion')
            ->addSelect('referencedVersion')
            ->orderBy('class.id','DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * @return QueryBuilder
     * Identique en dessous mais retourne le QueryBuilder (pour les form)
     */
    public function findFilteredClassByActiveProjectOrderedById(User $user)
    {
        //D'abord trouver les fk_namespace sélectionnés
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT fk_namespace 
                FROM che.associates_entity_to_user_project 
                WHERE fk_system_type = 25 
                AND fk_associate_user_to_project = (  SELECT pk_associate_user_to_project 
                                                      FROM che.associate_user_to_project 
                                                      WHERE fk_user = :fk_user AND fk_project = :fk_project)
                AND fk_namespace IS NOT NULL
                UNION
                SELECT fk_referenced_namespace AS fk_namespace
                FROM che.associates_referenced_namespace
                WHERE fk_namespace IN (
                  SELECT fk_namespace 
                  FROM che.associates_entity_to_user_project 
                  WHERE fk_system_type = 25 
                  AND fk_associate_user_to_project = (  SELECT pk_associate_user_to_project 
                                                        FROM che.associate_user_to_project 
                                                        WHERE fk_user = :fk_user AND fk_project = :fk_project)
                  AND fk_namespace IS NOT NULL
                );";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(
            'fk_user' => $user->getId(),
            'fk_project' => $user->getCurrentActiveProject()->getId()
        ));

        $arrayActivesNamespaces = $stmt->fetchAll();

        $qb = $this->createQueryBuilder('class')
            ->join('class.namespaces','nspc')
            ->where('nspc.id IN (:id_namespaces)')
            ->setParameter('id_namespaces', $arrayActivesNamespaces);

        return $qb;
    }

    /**
     * @return OntoClass[]
     */
    public function findFilteredByActiveProjectOrderedById(User $user)
    {
        return $this->findFilteredClassByActiveProjectOrderedById($user)->getQuery()->execute();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findAncestorsById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "WITH tw1 AS
                (
                  SELECT pk_parent,
                     parent_identifier,
                     DEPTH,
                     ARRAY_TO_STRING(_path,'|') ancestors,
                     pk_is_subclass_of
                  FROM che.ascendant_class_hierarchy(:class)
                )
                SELECT tw1.pk_parent AS id,
                       tw1.parent_identifier AS identifier,
                       tw1.DEPTH,
                       che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                       (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\",
                       nsp.pk_namespace AS \"classNamespaceId\",
                       nsp.standard_label AS \"classNamespaceLabel\"
                FROM tw1
                JOIN che.associates_namespace asnsp ON (asnsp.fk_class = tw1.pk_parent)
                JOIN che.namespace nsp ON (nsp.pk_namespace = asnsp.fk_namespace)
                WHERE depth > 1 
                GROUP BY tw1.pk_parent,
                     tw1.parent_identifier,
                     tw1.depth,
                     nsp.pk_namespace
                ORDER BY depth DESC;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findFilteredAncestorsById(OntoClass $class, User $user){
        $conn = $this->getEntityManager()
            ->getConnection();

        // Trouver d'abord les espaces de noms filtrés - juste les clés pour le IN de la requête ci-dessous
        $em = $this->getEntityManager();
        $filteredNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findAllActiveNamespacesForUser($user);

        $classNamespaces = $class->getNamespaces();
        $isClassNamespace = null;

        foreach ($classNamespaces as $classNamespace){
            if(!$classNamespace->getIsTopLevelNamespace()){
                if($classNamespace->getIsOngoing()){
                    $isClassNamespace = $classNamespace;
                    break;
                }
                else{
                    $isClassNamespace = $classNamespace;
                }
            }
        }

        if(!in_array($isClassNamespace, $filteredNamespaces)){
            $filteredNamespaces[] = $isClassNamespace;
        }

        $idsFilteredNamespaces = array();
        $qFilteredNamespaces = array();
        foreach ($filteredNamespaces as $namespace)
        {
            $idsFilteredNamespaces[] = $namespace->getId();
        }

        // Construire la variable qui permet d'avoir ?,?,?... pour la requête ci-dessous
        for($i=0;$i<count($idsFilteredNamespaces);$i++){
            $qFilteredNamespaces[] = "?";
        }
        $strQFilteredNamespaces = join(',',$qFilteredNamespaces);

        $sql = "WITH tw1 AS
                (
                  SELECT pk_parent,
                     parent_identifier,
                     DEPTH,
                     ARRAY_TO_STRING(_path,'|') ancestors,
                     pk_is_subclass_of
                  FROM che.ascendant_class_hierarchy(?)
                )
                SELECT tw1.pk_parent AS id,
                       tw1.parent_identifier AS identifier,
                       tw1.DEPTH,
                       che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                       (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\",
                       nsp.pk_namespace AS \"classNamespaceId\",
                       nsp.standard_label AS \"classNamespaceLabel\"
                FROM tw1
                JOIN che.associates_namespace asnsp ON (asnsp.fk_class = tw1.pk_parent)
                JOIN che.namespace nsp ON (nsp.pk_namespace = asnsp.fk_namespace)
                
                WHERE depth > 1 
                AND nsp.pk_namespace IN (".$strQFilteredNamespaces.")
                GROUP BY tw1.pk_parent,
                     tw1.parent_identifier,
                     tw1.depth,
                     nsp.pk_namespace
                ORDER BY depth DESC;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($class->getId()), $idsFilteredNamespaces));
        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findDescendantsById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT 	pk_child AS id,
                        child_identifier AS identifier,
                        depth,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                        (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\",
                        nsp.pk_namespace AS \"classNamespaceId\",
                        nsp.standard_label AS \"classNamespaceLabel\"
                FROM 	che.descendant_class_hierarchy(:class) cls, 
                        che.associates_namespace asnsp,
                        che.namespace nsp
                
                WHERE 	asnsp.fk_class = cls.pk_child
                AND   	nsp.pk_namespace = asnsp.fk_namespace
                
                GROUP BY pk_child, child_identifier, depth, nsp.pk_namespace, che.get_root_namespace(nsp.pk_namespace)
                ORDER BY depth ASC;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findFilteredDescendantsById(OntoClass $class, User $user){
        $conn = $this->getEntityManager()
            ->getConnection();

        // Trouver d'abord les espaces de noms filtrés - juste les clés pour le IN de la requête ci-dessous
        $em = $this->getEntityManager();
        $filteredNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findAllActiveNamespacesForUser($user);

        $classNamespaces = $class->getNamespaces();
        $isClassNamespace = null;

        foreach ($classNamespaces as $classNamespace){
            if(!$classNamespace->getIsTopLevelNamespace()){
                if($classNamespace->getIsOngoing()){
                    $isClassNamespace = $classNamespace;
                    break;
                }
                else{
                    $isClassNamespace = $classNamespace;
                }
            }
        }

        if(!in_array($isClassNamespace, $filteredNamespaces)){
            $filteredNamespaces[] = $classNamespace;
        }

        $idsFilteredNamespaces = array();
        $qFilteredNamespaces = array();
        foreach ($filteredNamespaces as $namespace)
        {
            $idsFilteredNamespaces[] = $namespace->getId();
        }

        // Construire la variable qui permet d'avoir ?,?,?... pour la requête ci-dessous
        for($i=0;$i<count($idsFilteredNamespaces);$i++){
            $qFilteredNamespaces[] = "?";
        }
        $strQFilteredNamespaces = join(',',$qFilteredNamespaces);

        $sql = "SELECT 	pk_child AS id,
                    child_identifier AS identifier,
                    depth,
                    che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                    (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\",
                    nsp.pk_namespace AS \"classNamespaceId\",
                    nsp.standard_label AS \"classNamespaceLabel\"
            FROM 	che.descendant_class_hierarchy(?) cls, 
                    che.associates_namespace asnsp,
                    che.namespace nsp
            
            WHERE 	asnsp.fk_class = cls.pk_child
            AND   	nsp.pk_namespace = asnsp.fk_namespace
            
            AND nsp.pk_namespace IN (" . $strQFilteredNamespaces . ")
            GROUP BY pk_child, child_identifier, depth, nsp.pk_namespace, che.get_root_namespace(nsp.pk_namespace)
            ORDER BY depth ASC;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($class->getId()), $idsFilteredNamespaces));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findEquivalencesById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT 	fk_associated_class AS id,
                        identifier_in_namespace AS identifier,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                        (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\"
                FROM  che.get_equivalent_classes(:class) cls,
                      che.associates_namespace asnsp,
                      che.namespace nsp
                WHERE 	asnsp.fk_class = cls.fk_associated_class
                 AND   	nsp.pk_namespace = asnsp.fk_namespace;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findFilteredEquivalencesById(OntoClass $class, User $user){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT 	fk_associated_class AS id,
                        identifier_in_namespace AS identifier,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                        (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\"
                FROM  che.get_equivalent_classes(:class) cls,
                      che.associates_namespace asnsp,
                      che.namespace nsp
                WHERE 	asnsp.fk_class = cls.fk_associated_class
                 AND   	nsp.pk_namespace = asnsp.fk_namespace
                 AND nsp.pk_namespace IN (
                    SELECT fk_namespace 
                    FROM che.associates_entity_to_user_project 
                    WHERE fk_associate_user_to_project = (
                        SELECT pk_associate_user_to_project 
                        FROM che.associate_user_to_project
                        WHERE fk_user = :user AND fk_project = :project)
                    AND fk_system_type = 25);";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId(), 'user' => $user->getId(), 'project' => $user->getCurrentActiveProject()->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findRelationsById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT
                ea.pk_entity_association,
                ea.fk_target_class AS fk_related_class,
                c.identifier_in_namespace,
                c.standard_label,
                st.standard_label AS relation,
                txtp.pk_text_property,
                ns.pk_namespace AS \"rootNamespaceId\",
                ns.standard_label AS \"standardLabelNamespace\"
                FROM
                che.entity_association AS ea
                LEFT JOIN che.system_type AS st
                ON st.pk_system_type = ea.fk_system_type
                LEFT JOIN che.class AS c
                ON ea.fk_target_class = c.pk_class
                LEFT JOIN (SELECT * FROM che.text_property WHERE fk_text_property_type = 15) AS txtp
                ON txtp.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.associates_namespace AS ans
                ON ans.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.namespace AS ns
                ON ns.pk_namespace= che.get_root_namespace(ans.fk_namespace)
                WHERE
                ea.fk_system_type IN (4, 19)
                AND ea.fk_source_class = :class
                AND c.pk_class IS NOT NULL
                UNION
                SELECT
                ea.pk_entity_association,
                ea.fk_source_class AS fk_related_class,
                c.identifier_in_namespace,
                c.standard_label,
                st.standard_label AS relation,
                txtp.pk_text_property,
                ns.pk_namespace AS \"rootNamespaceId\",
                ns.standard_label AS \"standardLabelNamespace\"
                FROM
                che.entity_association AS ea
                LEFT JOIN che.system_type AS st
                ON st.pk_system_type = ea.fk_system_type
                LEFT JOIN che.class AS c
                ON ea.fk_source_class = c.pk_class
                LEFT JOIN (SELECT * FROM che.text_property WHERE fk_text_property_type = 15) AS txtp
                ON txtp.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.associates_namespace AS ans
                ON ans.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.namespace AS ns
                ON ns.pk_namespace= che.get_root_namespace(ans.fk_namespace)
                WHERE
                ea.fk_system_type IN (4, 19)
                AND ea.fk_target_class = :class
                AND c.pk_class IS NOT NULL;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findFilteredRelationsById(OntoClass $class, User $user){
        $conn = $this->getEntityManager()
            ->getConnection();

        // Trouver d'abord les espaces de noms filtrés - juste les clés pour le IN de la requête ci-dessous
        $em = $this->getEntityManager();
        $filteredNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findAllActiveNamespacesForUser($user);

        $classNamespaces = $class->getNamespaces();
        $isClassNamespace = null;

        foreach ($classNamespaces as $classNamespace){
            if(!$classNamespace->getIsTopLevelNamespace()){
                if($classNamespace->getIsOngoing()){
                    $isClassNamespace = $classNamespace;
                    break;
                }
                else{
                    $isClassNamespace = $classNamespace;
                }
            }
        }

        if(!in_array($isClassNamespace, $filteredNamespaces)){
            $filteredNamespaces[] = $classNamespace;
        }

        $idsFilteredNamespaces = array();
        $qFilteredNamespaces = array();
        foreach ($filteredNamespaces as $namespace)
        {
            $idsFilteredNamespaces[] = $namespace->getId();
        }

        // Construire la variable qui permet d'avoir ?,?,?... pour la requête ci-dessous
        for($i=0;$i<count($idsFilteredNamespaces);$i++){
            $qFilteredNamespaces[] = "?";
        }
        $strQFilteredNamespaces = join(',',$qFilteredNamespaces);

        $sql = "SELECT
                ea.pk_entity_association,
                ea.fk_target_class AS fk_related_class,
                c.identifier_in_namespace,
                c.standard_label,
                st.standard_label AS relation,
                txtp.pk_text_property,
                ns.pk_namespace AS \"rootNamespaceId\",
                ns.standard_label AS \"standardLabelNamespace\"
                FROM
                che.entity_association AS ea
                LEFT JOIN che.system_type AS st
                ON st.pk_system_type = ea.fk_system_type
                LEFT JOIN che.class AS c
                ON ea.fk_target_class = c.pk_class
                LEFT JOIN (SELECT * FROM che.text_property WHERE fk_text_property_type = 15) AS txtp
                ON txtp.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.associates_namespace AS ans
                ON ans.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.namespace AS ns
                ON ns.pk_namespace= che.get_root_namespace(ans.fk_namespace)
                WHERE
                ea.fk_system_type IN (4, 19)
                AND ea.fk_source_class = ?
                AND c.pk_class IS NOT NULL
                AND ns.pk_namespace IN (".$strQFilteredNamespaces.")
                UNION
                SELECT
                ea.pk_entity_association,
                ea.fk_source_class AS fk_related_class,
                c.identifier_in_namespace,
                c.standard_label,
                st.standard_label AS relation,
                txtp.pk_text_property,
                ns.pk_namespace AS \"rootNamespaceId\",
                ns.standard_label AS \"standardLabelNamespace\"
                FROM
                che.entity_association AS ea
                LEFT JOIN che.system_type AS st
                ON st.pk_system_type = ea.fk_system_type
                LEFT JOIN che.class AS c
                ON ea.fk_source_class = c.pk_class
                LEFT JOIN (SELECT * FROM che.text_property WHERE fk_text_property_type = 15) AS txtp
                ON txtp.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.associates_namespace AS ans
                ON ans.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.namespace AS ns
                ON ns.pk_namespace= che.get_root_namespace(ans.fk_namespace)
                WHERE
                ea.fk_system_type IN (4, 19)
                AND ea.fk_target_class = ?
                AND c.pk_class IS NOT NULL
                AND ns.pk_namespace IN (".$strQFilteredNamespaces.");";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($class->getId()), $idsFilteredNamespaces, array($class->getId()), $idsFilteredNamespaces));

        return $stmt->fetchAll();
    }

    /**
     * @return array
     */
    public function findClassesTree(){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(tree)) AS json FROM che.tree_classes_with_css_color(214) tree";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @return array
     */
    public function findFilteredClassesTree(User $user){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(tree)) AS json FROM che.tree_filtered_classes_with_css_color(214,:fk_user,:fk_project) tree";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('fk_user'=>$user->getId(),'fk_project'=>$user->getCurrentActiveProject()->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param $context
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findClassesTreeLegend($context){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(legend)) AS json FROM (SELECT pk_css_color, css_color, label FROM che.css_color WHERE context = :context ORDER BY notes) legend;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('context' => $context));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findClassesGraphById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(result)) AS json FROM (SELECT pk_target AS id, pk_target AS real_id, replace(target_identifier, '_',' ') AS name, pk_source AS pk_parent, pk_source AS parent_id, source_identifier AS parent_name, depth, link_type FROM che.get_classes_rows_for_graph (:class)) result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Project $project
     * @return array
     */
    public function findClassesByProjectId(Project $project){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(result)) AS json FROM (SELECT * FROM api.v_classes_all_profile_project WHERE pk_project = :project ) result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('project' => $project->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Profile $profile
     * @return array
     */
    public function findClassesByProfileId(Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT pk_class AS id,
                        class_standard_label AS \"standardLabel\",
                        identifier_in_namespace AS \"identifierInNamespace\" ,
                        namespace AS \"namespace\" ,
                        profile_association_type AS \"associationType\"
                FROM che.v_all_classes_profile WHERE fk_profile = :profile;";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Profile $profile
     * @return array
     */
    public function findClassesForAssociationWithProfileByProfileId(Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT cls.pk_class AS \"classId\",
                        cls.identifier_in_namespace AS \"identifierInNamespace\",
                        cls.standard_label AS \"standardLabel\",
                        nsp.standard_label AS \"namespace\"
                FROM che.class cls
                JOIN che.associates_namespace asnsp ON cls.pk_class = asnsp.fk_class
                JOIN che.associates_referenced_namespace arfnsp ON asnsp.fk_namespace = arfnsp.fk_referenced_namespace
                JOIN che.namespace nsp ON asnsp.fk_namespace = nsp.pk_namespace
                WHERE arfnsp.fk_profile = :profile
                EXCEPT
                SELECT pk_class, identifier_in_namespace, class_standard_label, namespace
                FROM che.v_all_classes_profile WHERE fk_profile = :profile AND profile_association_type = 'selected';";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Profile $profile
     * @param OntoClass $class
     * @return array
     */
    public function findInferredClassesByProfileAndClassId(Profile $profile, OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT cls.pk_class
                FROM che.associates_profile aspro
                JOIN che.property prop ON aspro.fk_property = prop.pk_property
                JOIN che.class cls ON prop.has_domain = cls.pk_class
                WHERE aspro.fk_profile = :profile AND fk_property IS NOT NULL AND aspro.fk_system_type = 5 AND cls.pk_class = :class
                UNION
                SELECT DISTINCT cls.pk_class
                FROM che.associates_profile aspro
                JOIN che.property prop ON aspro.fk_property = prop.pk_property
                JOIN che.class cls ON prop.has_range = cls.pk_class
                WHERE aspro.fk_profile = :profile AND fk_property IS NOT NULL AND aspro.fk_system_type = 5 AND cls.pk_class = :class;";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('profile' => $profile->getId(), 'class' => $class->getId()));

        return $stmt->fetchColumn();
    }

    /**
     * @param Profile $profile
     * @param OntoClass $range
     * @param OntoClass $domain
     * @param Property $property
     * @param String $searchTerm
     * @return array
     */
    public function findDescendantsRangeByProfileAndDomainAndRangeId(Profile $profile, OntoClass $domain, OntoClass $range, Property $property, $searchTerm = null){
        $conn = $this->getEntityManager()
            ->getConnection();

        $iLike = '';
        $queryParams = array(
            'profile' => $profile->getId(),
            'domain' => $domain->getId(),
            'range' => $range->getId(),
            'property' => $property->getId()
        );
        if(!empty($searchTerm)) {
            $iLike = ' AND text ILIKE :searchTerm';
            $queryParams['searchTerm'] = '%'.$searchTerm.'%';
        }

        $sql = "SELECT *
                FROM (
                    SELECT DISTINCT pk_child AS id,
                                    child_identifier AS \"text\"
                    FROM che.descendant_class_hierarchy (:range) cls
                    JOIN che.associates_namespace asnsp ON asnsp.fk_class = cls.pk_child
                    JOIN che.associates_referenced_namespace asrefnsp 
                        ON asrefnsp.fk_referenced_namespace = asnsp.fk_namespace AND asrefnsp.fk_profile = :profile 
                    
                    EXCEPT 
                    
                    SELECT  aspro.fk_inheriting_range_class AS id,
                            cls.identifier_in_namespace || ' ' || cls.standard_label AS \"text\"
                    FROM che.associates_profile aspro
                    JOIN che.class cls 
                        ON aspro.fk_inheriting_range_class = cls.pk_class
                            AND aspro.fk_profile = :profile
                            AND   aspro.fk_property = :property
                            AND   aspro.fk_system_type = 5
                            AND   aspro.fk_inheriting_domain_class = :domain
                    ORDER BY id ASC) 
                AS result
                WHERE TRUE".$iLike.";";

        //echo $sql;die;

        $stmt = $conn->prepare($sql);
        $stmt->execute($queryParams);

        return $stmt->fetchAll();
    }

    /**
     * @param Profile $profile
     * @param OntoClass $domain
     * @param OntoClass $range
     * @param Property $property
     * @param String $searchTerm
     * @return array
     */
    public function findDescendantsDomainByProfileAndDomainAndRangeId(Profile $profile, OntoClass $domain, OntoClass $range, Property $property, $searchTerm = null){
        $conn = $this->getEntityManager()
            ->getConnection();

        $iLike = '';
        $queryParams = array(
            'profile' => $profile->getId(),
            'domain' => $domain->getId(),
            'range' => $range->getId(),
            'property' => $property->getId()
        );
        if(!empty($searchTerm)) {
            $iLike = ' AND text ILIKE :searchTerm';
            $queryParams['searchTerm'] = '%'.$searchTerm.'%';
        }

        $sql = "SELECT *
                FROM (
                    SELECT DISTINCT pk_child AS id,
                                    child_identifier AS \"text\"
                    FROM che.descendant_class_hierarchy (:domain) cls
                    JOIN che.associates_namespace asnsp ON asnsp.fk_class = cls.pk_child
                    JOIN che.associates_referenced_namespace asrefnsp 
                        ON asrefnsp.fk_referenced_namespace = asnsp.fk_namespace AND asrefnsp.fk_profile = :profile 
                    
                    EXCEPT 
                    
                    SELECT  aspro.fk_inheriting_range_class AS id,
                            cls.identifier_in_namespace || ' ' || cls.standard_label AS \"text\"
                    FROM che.associates_profile aspro
                    JOIN che.class cls 
                        ON aspro.fk_inheriting_domain_class = cls.pk_class
                            AND aspro.fk_profile = :profile
                            AND   aspro.fk_property = :property
                            AND   aspro.fk_system_type = 5
                            AND   aspro.fk_inheriting_range_class = :range
                    ORDER BY id ASC) 
                AS result
                WHERE TRUE".$iLike.";";

        $stmt = $conn->prepare($sql);
        $stmt->execute($queryParams);

        return $stmt->fetchAll();
    }

    /**
     * @param $lang string the language iso code
     * @param $availableInProfile
     * @param $selectedByProject
     * @return array
     */
    public function findClassesWithProfileApi($lang, $availableInProfile, $selectedByProject)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(result)) AS json FROM (SELECT * FROM api.get_classes_list(:lang, :availableInProfile, :selectedByProject) ) result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(
            'lang' => $lang,
            'availableInProfile' => $availableInProfile,
            'selectedByProject' => $selectedByProject
        ));

        return $stmt->fetchAll();
    }


}