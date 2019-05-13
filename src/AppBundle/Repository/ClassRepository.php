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
                       (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\"
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
    public function findDescendantsById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT 	pk_child AS id,
                        child_identifier AS identifier,
                        depth,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                        (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\"
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
                ON ans.fk_namespace = ns.pk_namespace
                WHERE
                ea.fk_system_type IN (4, 18, 19, 20)
                AND ea.fk_source_class = :class
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
                ON ans.fk_namespace = ns.pk_namespace
                WHERE
                ea.fk_system_type IN (4, 18, 19, 20)
                AND ea.fk_target_class = :class";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

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
    public function findClassesTreeLegend(){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(legend)) AS json FROM (SELECT pk_css_color, css_color, label FROM che.css_color WHERE context = 'ontologies_classes_tree' ORDER BY pk_css_color) legend;";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

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
     * @param OntoClass $class
     * @param Property $property
     * @param String $searchTerm
     * @return array
     */
    public function findDescendantsByProfileAndClassId(Profile $profile, OntoClass $class, Property $property, $searchTerm = null){
        $conn = $this->getEntityManager()
            ->getConnection();

        $iLike = '';
        $queryParams = array(
            'profile' => $profile->getId(),
            'class' => $class->getId(),
            'property' => $property->getId()
        );
        if(!empty($searchTerm)) {
            $iLike = ' AND text ILIKE :searchTerm';
            $queryParams['searchTerm'] = '%'.$searchTerm.'%';
        }

        $sql = "SELECT * 
                FROM (
                    SELECT DISTINCT	pk_child AS id,
                                       child_identifier AS \"text\"
                    FROM che.descendant_class_hierarchy(:class) cls
                             JOIN che.associates_namespace asnsp ON asnsp.fk_class = cls.pk_child
                             JOIN che.associates_referenced_namespace asrefnsp ON asrefnsp.fk_referenced_namespace = asnsp.fk_namespace AND asrefnsp.fk_profile = :profile
                    
                        EXCEPT
                    
                    SELECT  aspro.fk_inheriting_range_class AS id,
                            cls.identifier_in_namespace || ' ' || cls.standard_label AS \"text\"
                    FROM che.associates_profile aspro
                             JOIN che.class cls ON aspro.fk_inheriting_range_class = cls.pk_class
                    WHERE aspro.fk_profile = :profile AND aspro.fk_property = :property AND aspro.fk_system_type = 5
                    
                        EXCEPT
                    
                    SELECT  aspro.fk_inheriting_domain_class AS id,
                            cls.identifier_in_namespace || ' ' || cls.standard_label AS \"text\"
                    FROM che.associates_profile aspro
                             JOIN che.class cls ON aspro.fk_inheriting_domain_class = cls.pk_class
                    WHERE aspro.fk_profile = :profile AND aspro.fk_property = :property  AND aspro.fk_system_type = 5
                    
                    ORDER BY id ASC
                ) AS result
                WHERE TRUE".$iLike.";";

        //echo $sql;die;

        $stmt = $conn->prepare($sql);
        $stmt->execute($queryParams);

        return $stmt->fetchAll();
    }


}