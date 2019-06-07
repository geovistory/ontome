<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/07/2017
 * Time: 12:01
 */

namespace AppBundle\Repository;

use AppBundle\Entity\OntoClass;
use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\Property;
use Doctrine\ORM\EntityRepository;

class PropertyRepository extends EntityRepository
{

    /**
     * @return Property[]
     */
    public function findAllOrderedById()
    {
        return $this->createQueryBuilder('property')
            ->join('property.namespaces','nspc')
            ->addSelect('nspc')
            ->leftJoin('nspc.referencedVersion', 'referencedVersion')
            ->addSelect('referencedVersion')
            ->orderBy('property.id','DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * @return Property[]
     */
    public function findFilteredByPublicProjectOrderedById()
    {
        return $this->createQueryBuilder('property')
            ->join('property.namespaces','nspc')
            ->join('nspc.projects', 'prj')
            ->addSelect('nspc')
            ->leftJoin('nspc.referencedVersion', 'referencedVersion')
            ->addSelect('referencedVersion')
            ->orderBy('property.id','DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findOutgoingPropertiesById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT identifier_property AS property,
                       identifier_range AS range,
                       pk_property AS \"propertyId\",
                       pk_range AS \"rangeId\",
                       identifier_domain AS domain,
                       che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                      (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace
                FROM  che.v_properties_with_domain_range,
                      che.associates_namespace asnsp,
                      che.namespace nsp
                WHERE asnsp.fk_property = pk_property
                  AND nsp.pk_namespace = asnsp.fk_namespace 
                  AND pk_domain = :class;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findOutgoingInheritedPropertiesById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT 	identifier_in_namespace AS domain,
                        pk_parent AS \"parentClassId\",
                        parent_identifier AS \"parentClass\",
                        pk_property AS \"propertyId\",
                        identifier_property AS property,
                        pk_range AS \"rangeId\",
                        identifier_range AS range,
                        replace(ancestors, '|', '→') AS ancestors,
                        (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace
                FROM 	che.class_outgoing_inherited_properties(:class),
                        che.associates_namespace asnsp,
                        che.namespace nsp
                WHERE 	asnsp.fk_property = pk_property
                  AND 	nsp.pk_namespace = asnsp.fk_namespace;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findIngoingPropertiesById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT  pk_domain AS \"domainId\",
                        identifier_domain AS domain,
                        identifier_property AS property,
                        pk_property AS \"propertyId\",
                        pk_range AS \"rangeId\",
                        identifier_range AS range,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                        (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace
                FROM  che.v_properties_with_domain_range,
                      che.associates_namespace asnsp,
                      che.namespace nsp 
                WHERE pk_range = :class
                  AND asnsp.fk_property = pk_property
                  AND nsp.pk_namespace = asnsp.fk_namespace;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findIngoingInheritedPropertiesById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT  pk_domain AS \"domainId\",
                        identifier_domain AS domain,
                        identifier_property AS property,
                        pk_property AS \"propertyId\",
                        pk_parent AS \"rangeId\",
                        parent_identifier AS range,
                        replace(ancestors, '|', '→') AS ancestors,
                        (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace
                FROM  che.class_ingoing_inherited_properties(:class),
                      che.associates_namespace asnsp,
                      che.namespace nsp 
                WHERE asnsp.fk_property = pk_property
                  AND nsp.pk_namespace = asnsp.fk_namespace;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Property $property
     * @return array
     */
    public function findAncestorsById(Property $property){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "WITH tw1 AS
                (
                  SELECT pk_parent,
                     parent_identifier,
                     DEPTH,
                     ARRAY_TO_STRING(_path,'|') ancestors
                  FROM che.ascendant_property_hierarchy(:property)
                )
                SELECT tw1.pk_parent  AS id,
                       tw1.parent_identifier AS identifier,
                       tw1.DEPTH,
                       replace(tw1.ancestors, '|', '→') AS ancestors,
                       che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                       (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\"
                FROM tw1,
                     che.associates_namespace asnsp,
                     che.namespace nsp
                WHERE asnsp.fk_property = tw1.pk_parent
                AND   nsp.pk_namespace = asnsp.fk_namespace
                AND depth > 1 
                GROUP BY tw1.pk_parent,
                     tw1.parent_identifier,
                     tw1.depth,
                     tw1.ancestors,
                     nsp.pk_namespace";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('property' => $property->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Property $property
     * @return array
     */
    public function findDescendantsById(Property $property){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT  pk_child AS id,
                        child_identifier as identifier,
                        depth,
                        replace(descendants, '|', '→') AS descendants,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                       (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\"                        
                    FROM che.descendant_property_hierarchy((:property)),
                         che.associates_namespace asnsp,
                         che.namespace nsp
                    WHERE asnsp.fk_property = pk_child
                    AND   nsp.pk_namespace = asnsp.fk_namespace    
                         ";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('property' => $property->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Property $property
     * @return array
     */
    public function findDomainRangeById(Property $property){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT  pk_domain AS \"domainId\",
                        identifier_domain AS \"domainIdentifier\",
                        identifier_property AS \"propertyIdentifier\",
                        pk_range AS \"rangeId\",
                        identifier_range AS \"rangeIdentifier\"
                FROM che.v_properties_with_domain_range
                WHERE pk_property = :property";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('property' => $property->getId()));

        return $stmt->fetch();
    }

    /**
     * @return array
     */
    public function findPropertiesTree(){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(tree)) AS json FROM che.tree_properties_with_css_color(155) tree";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @return array
     */
    public function findPropertiesTreeLegend(){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(legend)) AS json FROM (SELECT pk_css_color, css_color, label FROM che.css_color WHERE context = 'ontologies_properties_tree' ORDER BY pk_css_color) legend;";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param Project $project
     * @return array
     */
    public function findPropertiesByProjectId(Project $project){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(result)) AS json FROM (SELECT * FROM api.v_property_profile_project WHERE pk_project = :project ) result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('project' => $project->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Profile $profile
     * @return array
     */
    public function findPropertiesByProfileId(Profile $profile){
        return $this->createQueryBuilder('property')
            ->join('property.namespaces','nspc')
            ->join('property.profiles', 'profile')
            ->where('profile.id = :profile')
            ->addSelect('profile')
            ->addSelect('nspc')
            ->leftJoin('nspc.referencedVersion', 'referencedVersion')
            ->addSelect('referencedVersion')
            ->orderBy('property.id','DESC')
            ->setParameter('profile', $profile->getId())
            ->getQuery()
            ->execute();
    }

    /**
     * @param OntoClass $class
     * @param Profile $profile
     * @return array
     */
    public function findOutgoingPropertiesByClassAndProfileId(OntoClass $class, Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT identifier_property AS property,
                                identifier_range AS range,
                                pk_property AS \"propertyId\",
                                pk_range AS \"rangeId\",
                                identifier_domain AS domain,
                                che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                                (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace,
                                CASE
                                    WHEN aspro.fk_system_type IS NULL THEN 999
                                    ELSE aspro.fk_system_type
                                END AS fk_system_type
                FROM  che.v_properties_with_domain_range
                JOIN che.associates_namespace asnsp ON asnsp.fk_property = pk_property
                JOIN che.namespace nsp ON nsp.pk_namespace = asnsp.fk_namespace 
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = pk_property AND aspro.fk_profile = :profile
                
                WHERE pk_domain = :class;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId(), 'profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @param Profile $profile
     * @return array
     */
    public function findIncomingPropertiesByClassAndProfileId(OntoClass $class, Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT  pk_domain AS \"domainId\",
                        identifier_domain AS domain,
                        identifier_property AS property,
                        pk_property AS \"propertyId\",
                        pk_range AS \"rangeId\",
                        identifier_range AS range,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                        (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace,
                        CASE
                            WHEN aspro.fk_system_type IS NULL THEN 999
                            ELSE aspro.fk_system_type
                        END AS fk_system_type
                FROM  che.v_properties_with_domain_range
                JOIN che.associates_namespace asnsp ON asnsp.fk_property = pk_property
                JOIN che.namespace nsp ON nsp.pk_namespace = asnsp.fk_namespace 
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = pk_property AND aspro.fk_profile = :profile
                WHERE pk_range = :class;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId(), 'profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @param Profile $profile
     * @return array
     */
    public function findOutgoingInheritedPropertiesByClassAndProfileId(OntoClass $class, Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT identifier_in_namespace AS domain,
                                pk_parent AS \"parentClassId\",
                                parent_identifier AS \"parentClass\",
                                pk_property AS \"propertyId\",
                                identifier_property AS property,
                                pk_range AS \"rangeId\",
                                identifier_range AS range,
                                replace(ancestors, '|', '→') AS ancestors,
                                (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace,
                                CASE
                                    WHEN aspro.fk_system_type IS NULL THEN 999
                                    ELSE aspro.fk_system_type
                                    END AS fk_system_type
                FROM che.class_outgoing_inherited_properties(:class)
                JOIN che.associates_namespace asnsp ON asnsp.fk_property = pk_property
                JOIN che.namespace nsp ON nsp.pk_namespace = asnsp.fk_namespace
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = pk_property AND aspro.fk_inheriting_domain_class = :class AND aspro.fk_inheriting_range_class = pk_range AND aspro.fk_profile = :profile;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId(), 'profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findincomingInheritedPropertiesByClassAndProfileId(OntoClass $class, Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT  pk_domain AS \"domainId\",
                        identifier_domain AS domain,
                        identifier_property AS property,
                        pk_property AS \"propertyId\",
                        pk_parent AS \"rangeId\",
                        parent_identifier AS range,
                        replace(ancestors, '|', '→') AS ancestors,
                        (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace,
                        CASE
                            WHEN aspro.fk_system_type IS NULL THEN 999
                            ELSE aspro.fk_system_type
                        END AS fk_system_type
                FROM che.class_ingoing_inherited_properties(:class)
                JOIN che.associates_namespace asnsp ON asnsp.fk_property = pk_property
                JOIN che.namespace nsp ON nsp.pk_namespace = asnsp.fk_namespace
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = pk_property AND aspro.fk_inheriting_domain_class = :class AND aspro.fk_inheriting_range_class = pk_parent AND aspro.fk_profile = :profile;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId(), 'profile' => $profile->getId()));

        return $stmt->fetchAll();
    }
}