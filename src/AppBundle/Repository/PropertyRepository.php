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

    public function findOutgoingPropertiesById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT identifier_property AS property,
                       identifier_range AS range,
                       pk_property AS \"propertyId\",
                       pk_range AS \"rangeId\",
                       identifier_domain AS domain,
                       che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                      (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\"
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
}