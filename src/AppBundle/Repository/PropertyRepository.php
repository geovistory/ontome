<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/07/2017
 * Time: 12:01
 */

namespace AppBundle\Repository;

use AppBundle\Entity\OntoClass;
use Doctrine\ORM\EntityRepository;

class PropertyRepository extends EntityRepository
{

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
}