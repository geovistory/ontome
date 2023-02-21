<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/06/2017
 * Time: 17:15
 */

namespace AppBundle\Repository;

use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoClassVersion;
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\Property;
use AppBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;

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
     * @param array $namespacesId
     * @return \Doctrine\ORM\QueryBuilder
     * Remplace les fonctions obsolètes findFilteredClassByActiveProjectOrderedById et findFilteredByActiveProjectOrderedById
     */
    public function findClassesByNamespacesIdQueryBuilder(array $namespacesId){
        $qb = $this->createQueryBuilder('class')
            ->join('class.classVersions','cv')
            ->join('cv.namespaceForVersion','nfv')
            ->where('nfv.id IN (:namespacesId)')
            ->setParameter('namespacesId', $namespacesId);

        return $qb;
    }

    /**
     * @param array $namespacesId
     * @return OntoClass[]
     * Remplace les fonctions obsolètes findFilteredClassByActiveProjectOrderedById et findFilteredByActiveProjectOrderedById
     */
    public function findClassesByNamespacesId(array $namespacesId){
        $qb = $this->findClassesByNamespacesIdQueryBuilder($namespacesId);

        $classes = $qb->getQuery()->execute();

        return $classes;
    }

    /**
     * @param OntoClassVersion $classVersion
     * @param array $namespacesId
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     * Remplace les fonctions obsolètes findAncestorsById et findFilteredAncestorsById
     */
    public function findAncestorsByClassVersionAndNamespacesId(OntoClassVersion $classVersion, array $namespacesId){
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';
        $sql = "WITH t_ascendants_classes AS(
	                SELECT pk_parent,
                        parent_identifier,
                        DEPTH,
                        ARRAY_TO_STRING(ancestors,'|') ancestors,
                        ARRAY_TO_STRING(pk_ancestors,'|') pk_ancestors,
                        ARRAY_TO_STRING(pk_version_ancestors,'|') pk_version_ancestors,
                        pk_is_subclass_of,
                        fk_parent_namespace_for_version
                        FROM che.ascendant_class_hierarchy(?, ARRAY[".$in."]::integer[])
                )
                SELECT t_ascendants_classes.pk_parent AS id,
                    t_ascendants_classes.parent_identifier AS identifier,
                    t_ascendants_classes.DEPTH,
                    t_ascendants_classes.ancestors,
                    t_ascendants_classes.pk_ancestors,
                    t_ascendants_classes.pk_version_ancestors,
                    che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                    (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\",
                    nsp_ascendant.pk_namespace AS \"classNamespaceId\",
                    nsp_ascendant.standard_label AS \"classNamespaceLabel\",
                    t_ascendants_classes.fk_parent_namespace_for_version
                FROM t_ascendants_classes,
                che.namespace nsp,
                che.namespace nsp_ascendant,
                che.is_subclass_of subcl
                WHERE depth > 1
                AND t_ascendants_classes.fk_parent_namespace_for_version = nsp.pk_namespace 
                AND t_ascendants_classes.pk_is_subclass_of = subcl.pk_is_subclass_of 
                AND subcl.fk_parent_class_namespace = nsp_ascendant.pk_namespace 
                AND subcl.fk_parent_class_namespace IN (".$in.")
                GROUP BY t_ascendants_classes.pk_parent,
                t_ascendants_classes.parent_identifier,
                t_ascendants_classes.depth,
                nsp.pk_namespace,
                nsp_ascendant.pk_namespace,
		        t_ascendants_classes.fk_parent_namespace_for_version,
		        t_ascendants_classes.pk_is_subclass_of,
		        t_ascendants_classes.ancestors,
		        t_ascendants_classes.pk_ancestors,
		        t_ascendants_classes.pk_version_ancestors
                ORDER BY depth DESC;";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId, $namespacesId));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClassVersion $classVersion
     * @param array $namespacesId
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     * Remplace les fonctions obsolètes findDescendantsById et findFilteredDescendantsById
     */
    public function findDescendantsByClassVersionAndNamespacesId(OntoClassVersion $classVersion, array $namespacesId){
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT pk_child AS id,
                  child_identifier AS identifier,
                  depth,
                  che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                  ( SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\",
                  nsp.pk_namespace AS \"classNamespaceId\",
                  nsp.standard_label AS \"classNamespaceLabel\",
                  fk_namespace_for_version,
                  ARRAY_TO_STRING(descendants,'|') AS \"descendants\",
                  ARRAY_TO_STRING(pk_descendants,'|') AS \"pk_descendants\",
                  ARRAY_TO_STRING(pk_version_descendants,'|') AS \"pk_version_descendants\"
                  FROM che.descendant_class_hierarchy(?, ARRAY[".$in."]::integer[]) cls,
                  che.namespace nsp
                WHERE nsp.pk_namespace = cls.fk_namespace_for_version
                AND nsp.pk_namespace IN (".$in.")
                AND cls.fk_namespace_for_version IN (".$in.")
                GROUP BY pk_child, child_identifier, depth, nsp.pk_namespace, che.get_root_namespace(nsp.pk_namespace), fk_namespace_for_version, descendants, pk_descendants, pk_version_descendants
                ORDER BY depth ASC;";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId, $namespacesId, $namespacesId));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClassVersion $classVersion
     * @param array $namespacesId
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     * Remplace les fonctions obsolètes findRelationsById et findFilteredRelationsById
     */
    public function findRelationsByClassVersionAndNamespacesId(OntoClassVersion $classVersion, array $namespacesId){
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT 
                ea.pk_entity_association, 
                ea.fk_target_class AS fk_related_class,
                c.identifier_in_namespace,
                cv.standard_label,
                st.standard_label AS relation,
                txtp.pk_text_property,
                ns.pk_namespace AS \"rootNamespaceId\",
                ns.standard_label AS \"standardLabelNamespace\"
                FROM che.entity_association ea 
                JOIN che.system_type st ON ea.fk_system_type = st.pk_system_type 
                JOIN che.class c ON ea.fk_target_class = c.pk_class
                JOIN che.namespace ns ON ns.pk_namespace = che.get_root_namespace(ea.fk_namespace_for_version)
                JOIN che.class_version cv ON c.pk_class = cv.fk_class
                LEFT JOIN (SELECT * FROM che.text_property WHERE fk_text_property_type = 15) AS txtp ON txtp.fk_entity_association = ea.pk_entity_association
                WHERE st.pk_system_type IN (4,19)
                AND c.pk_class = ?
                AND ea.fk_namespace_for_version IN (".$in.")
                UNION
                SELECT 
                ea.pk_entity_association,
                ea.fk_source_class AS fk_related_class,
                c.identifier_in_namespace,
                cv.standard_label,
                st.standard_label AS relation,
                txtp.pk_text_property,
                ns.pk_namespace AS \"rootNamespaceId\",
                ns.standard_label AS \"standardLabelNamespace\" 
                FROM che.entity_association ea 
                JOIN che.system_type st ON ea.fk_system_type = st.pk_system_type 
                JOIN che.class c ON ea.fk_source_class = c.pk_class
                JOIN che.namespace ns ON ns.pk_namespace = che.get_root_namespace(ea.fk_namespace_for_version)
                JOIN che.class_version cv ON c.pk_class = cv.fk_class
                LEFT JOIN (SELECT * FROM che.text_property WHERE fk_text_property_type = 15) AS txtp ON txtp.fk_entity_association = ea.pk_entity_association
                WHERE st.pk_system_type IN (4,19)
                AND c.pk_class = ?
                AND ea.fk_namespace_for_version IN (".$in.");";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId, array($classVersion->getClass()->getId()), $namespacesId));

        return $stmt->fetchAll();
    }

    /**
     * @return array
     * @throws \Doctrine\DBAL\DBALException
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
     * @param User $user
     * @return array
     * @throws \Doctrine\DBAL\DBALException
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
     * @param OntoNamespace $namespace
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findClassesGraphById(OntoClass $class, OntoNamespace $namespace){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(result)) AS json 
                FROM (SELECT pk_target AS id, 
                             pk_target AS real_id, 
                             replace(target_identifier, '_',' ') AS name, 
                             pk_source AS pk_parent, 
                             pk_source AS parent_id, 
                             source_identifier AS parent_name, 
                             depth, link_type 
                FROM che.get_classes_rows_for_graph(:class, :namespace)) result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId(), 'namespace' => $namespace->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Project $project
     * @return array
     * @throws \Doctrine\DBAL\DBALException
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
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findClassesByProfileId(Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT pk_class AS id,
                        class_standard_label AS \"standardLabel\",
                        identifier_in_namespace AS \"identifierInNamespace\" ,
                        namespace AS \"namespace\" ,
                        profile_association_type AS \"associationType\",
                        fk_class_namespace_for_version AS \"namespaceForVersion\",
                        aspro.pk_associates_profile AS \"profileAssociationId\",
                        COUNT(txtp.*) AS \"nbTxtpCustom\"
                FROM che.get_all_classes_for_profile(:profile)
                LEFT JOIN che.associates_profile aspro ON aspro.fk_profile = :profile AND aspro.fk_class = pk_class 
                LEFT JOIN che.text_property txtp ON txtp.fk_associates_profile = aspro.pk_associates_profile
                GROUP BY pk_class, class_standard_label, identifier_in_namespace, namespace, profile_association_type, fk_class_namespace_for_version, pk_associates_profile;";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Profile $profile
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findClassesForAssociationWithProfileByProfileId(Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT cls.pk_class AS \"classId\",
                        cls.identifier_in_namespace AS \"identifierInNamespace\",
                        cv.standard_label AS \"standardLabel\",
                        cv.fk_namespace_for_version AS \"namespaceId\",
                        nsp.standard_label AS \"namespace\"
                FROM che.class cls JOIN che.class_version cv ON cls.pk_class = cv.fk_class AND cv.fk_namespace_for_version IN (SELECT * FROM che.get_all_references_namespaces_for_profile(:profile))
                JOIN che.namespace nsp ON cv.fk_namespace_for_version = nsp.pk_namespace
                EXCEPT
                SELECT pk_class, identifier_in_namespace, class_standard_label, fk_class_namespace_for_version, namespace
                FROM che.get_all_classes_for_profile(:profile) WHERE profile_association_type = 'selected';";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Profile $profile
     * @param OntoClass $class
     * @return bool|string
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findInferredClassesByProfileAndClassId(Profile $profile, OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT cls.pk_class
                FROM che.associates_profile aspro
                JOIN che.property prop ON aspro.fk_property = prop.pk_property
                JOIN che.property_version pv ON prop.pk_property = pv.fk_property
                JOIN che.class cls ON pv.has_domain = cls.pk_class
                WHERE aspro.fk_profile = :profile AND aspro.fk_property IS NOT NULL AND aspro.fk_system_type = 5 AND cls.pk_class = :class
                UNION
                SELECT DISTINCT cls.pk_class
                FROM che.associates_profile aspro
                JOIN che.property prop ON aspro.fk_property = prop.pk_property
                JOIN che.property_version pv ON prop.pk_property = pv.fk_property
                JOIN che.class cls ON pv.has_range = cls.pk_class
                WHERE aspro.fk_profile = :profile AND aspro.fk_property IS NOT NULL AND aspro.fk_system_type = 5 AND cls.pk_class = :class
                UNION
                SELECT DISTINCT cls.pk_class
                FROM che.associates_profile aspro
                JOIN che.class cls ON aspro.fk_inheriting_domain_class = cls.pk_class
                WHERE aspro.fk_profile = :profile AND aspro.fk_property IS NOT NULL AND aspro.fk_system_type = 5 AND cls.pk_class = :class
                UNION
                SELECT DISTINCT cls.pk_class
                FROM che.associates_profile aspro
                JOIN che.class cls ON aspro.fk_inheriting_range_class = cls.pk_class
                WHERE aspro.fk_profile = :profile AND aspro.fk_property IS NOT NULL AND aspro.fk_system_type = 5 AND cls.pk_class = :class;";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('profile' => $profile->getId(), 'class' => $class->getId()));

        return $stmt->fetchColumn();
    }

    /**
     * @param Profile $profile
     * @param OntoClass $domain
     * @param OntoClass $range
     * @param Property $property
     * @param String $searchTerm
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findDescendantsRangeByProfileAndDomainAndRangeId(Profile $profile, OntoClass $domain, OntoClass $range, Property $property, $searchTerm = null){
        $conn = $this->getEntityManager()
            ->getConnection();

        $namespacesId = new ArrayCollection();
        foreach($profile->getNamespaces() as $namespace){
            if(!$namespacesId->contains($namespace->getId())){
                $namespacesId->add($namespace->getId());
            }
            foreach ($namespace->getAllReferencedNamespaces() as $nsRef){
                if(!$namespacesId->contains($nsRef->getId())){
                    $namespacesId->add($nsRef->getId());
                }
            }
        }

        $iLike = '';
        $queryParams = array(
            'profile' => $profile->getId(),
            'domain' => $domain->getId(),
            'range' => $range->getId(),
            'property' => $property->getId(),
        );
        if(!empty($searchTerm)) {
            $iLike = ' AND text ILIKE :searchTerm';
            $queryParams['searchTerm'] = '%'.$searchTerm.'%';
        }
        $sql = "SELECT *
                FROM (
                    SELECT DISTINCT pk_child AS id,
                                    che.get_root_namespace_prefix(che.get_root_namespace(cv.fk_namespace_for_version)) || ':' || child_identifier AS \"text\"
                    FROM che.descendant_class_hierarchy (:range, ARRAY[".implode(',',$namespacesId->toArray())."]) cls
                    JOIN che.class_version cv ON cv.fk_class = cls.pk_child AND cv.fk_namespace_for_version IN (SELECT pk_namespace FROM che.get_all_references_namespaces_for_profile(:profile)) 
                    
                    EXCEPT 
                    
                    SELECT  aspro.fk_inheriting_range_class AS id,
                            che.get_root_namespace_prefix(che.get_root_namespace(cv.fk_namespace_for_version)) || ':' || cls.identifier_in_namespace || ' ' || cv.standard_label AS \"text\"
                    FROM che.associates_profile aspro
                    JOIN che.class cls ON aspro.fk_inheriting_range_class = cls.pk_class
                    JOIN che.class_version cv ON cls.pk_class = cv.fk_class
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
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findDescendantsDomainByProfileAndDomainAndRangeId(Profile $profile, OntoClass $domain, OntoClass $range, Property $property, $searchTerm = null){
        $conn = $this->getEntityManager()
            ->getConnection();

        $namespacesId = new ArrayCollection();
        foreach($profile->getNamespaces() as $namespace){
            if(!$namespacesId->contains($namespace->getId())){
                $namespacesId->add($namespace->getId());
            }
            foreach ($namespace->getAllReferencedNamespaces() as $nsRef){
                if(!$namespacesId->contains($nsRef->getId())){
                    $namespacesId->add($nsRef->getId());
                }
            }
        }

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
                                    che.get_root_namespace_prefix(che.get_root_namespace(cv.fk_namespace_for_version)) || ':' || child_identifier AS \"text\"
                    FROM che.descendant_class_hierarchy (:domain, ARRAY[".implode(',',$namespacesId->toArray())."]) cls
                    JOIN che.class_version cv ON cv.fk_class = cls.pk_child AND cv.fk_namespace_for_version IN (SELECT pk_namespace FROM che.get_all_references_namespaces_for_profile(:profile)) 
                    
                    EXCEPT 
                    
                    SELECT  aspro.fk_inheriting_range_class AS id,
                            che.get_root_namespace_prefix(che.get_root_namespace(cv.fk_namespace_for_version)) || ':' || cls.identifier_in_namespace || ' ' || cv.standard_label AS \"text\"
                    FROM che.associates_profile aspro
                    JOIN che.class cls 
                        ON aspro.fk_inheriting_domain_class = cls.pk_class
                            AND aspro.fk_profile = :profile
                            AND   aspro.fk_property = :property
                            AND   aspro.fk_system_type = 5
                            AND   aspro.fk_inheriting_range_class = :range
                    JOIN che.class_version cv
                    ON cls.pk_class = cv.fk_class
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
     * @throws \Doctrine\DBAL\DBALException
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

    /**
     * @param $label string the label of the class to find
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findE55ChildClassesFromLabel($label)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(result)) AS json FROM ( 
                    SELECT DISTINCT     pk_child AS id,
                                        child_identifier AS \"standardLabel\",
                                        'https://ontome.net/ontology/c'||pk_child AS \"ontomeURI\",
                                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                                        ( SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\"
                    FROM che.descendant_class_hierarchy(53) cls, che.namespace nsp
                    WHERE nsp.pk_namespace = cls.fk_namespace_for_version AND child_identifier ILIKE :label
                    GROUP BY pk_child, child_identifier, nsp.pk_namespace, che.get_root_namespace(nsp.pk_namespace)
                    ORDER BY \"rootNamespaceLabel\" ASC
                ) result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(
            'label' => '%'.$label.'%'
        ));

        return $stmt->fetchAll();
    }

    /**
     * @param $class integer the id of the class to find in the CRM E55 Type class descendants
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findE55ChildClasses($class)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT pk_child AS id
                FROM che.descendant_class_hierarchy(53) cls, che.namespace nsp
                WHERE nsp.pk_namespace = cls.fk_namespace_for_version AND pk_child = :class
                GROUP BY pk_child;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(
            'class' => $class
        ));

        return $stmt->fetchAll();
    }
}