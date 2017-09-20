<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/07/2017
 * Time: 12:01
 */

namespace AppBundle\Repository;

use AppBundle\Entity\OntoClass;
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

        $sql = "SELECT  identifier_domain AS domain,
                        identifier_property AS property,
                        pk_property AS \"propertyId\",
                        pk_range AS \"rangeId\",
                        identifier_range AS range,
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

        $sql = "SELECT  identifier_domain AS domain,
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
                       tw1.parent_identifier AS \"parentIdentifier\",
                       tw1.DEPTH,
                       replace(tw1.ancestors, '|', '→') AS ancestors,
                       STRING_AGG(nsp.namespace_uri,'; ') namespaces
                FROM tw1,
                     che.associates_namespace asnsp,
                     che.namespace nsp
                WHERE asnsp.fk_property = tw1.pk_parent
                AND   nsp.pk_namespace = asnsp.fk_namespace
                GROUP BY tw1.pk_parent,
                     tw1.parent_identifier,
                     tw1.depth,
                     tw1.ancestors";

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
                        namespaces
                    FROM che.descendant_property_hierarchy((:property))";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('property' => $property->getId()));

        return $stmt->fetchAll();
    }
}