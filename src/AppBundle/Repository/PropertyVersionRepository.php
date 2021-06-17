<?php
/**
 * Created by PhpStorm.
 * User: Alexandre
 * Date: 02/07/2020
 * Time: 15:53
 */

namespace AppBundle\Repository;

use AppBundle\Entity\Property;
use Doctrine\ORM\EntityRepository;

class PropertyVersionRepository extends EntityRepository
{
    /**
     * @param OntoClass $class
     * @param array $namespacesId
     * @return object|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findPropertyVersionByPropertyAndNamespacesId(Property $property, array $namespacesId)
    {
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT pk_property_version 
                FROM che.property_version 
                WHERE fk_property = ? AND fk_namespace_for_version IN (".$in.")";

        $em = $this->getEntityManager();
        $conn = $em->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($property->getId()), $namespacesId));

        return $em->getRepository('AppBundle:PropertyVersion')->find($stmt->fetch()['pk_property_version']);
    }

    public function findIdAndStandardLabelOfPropertiesVersionByNamespacesId(array $namespacesId)
    {
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT pv.fk_property AS id, pv.standard_label AS \"standardLabel\", p.identifier_in_namespace AS \"identifierInNamespace\", topns.root_namespace_prefix AS \"rootNamespacePrefix\"
                FROM che.property_version pv JOIN che.property p ON p.pk_property = pv.fk_property
                LEFT JOIN che.namespace ns ON pv.fk_namespace_for_version = ns.pk_namespace
                LEFT JOIN che.namespace topns ON ns.fk_top_level_namespace = topns.pk_namespace
                WHERE fk_namespace_for_version IN (".$in.")";

        $em = $this->getEntityManager();
        $conn = $em->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($namespacesId);

        return $stmt->fetchAll();
    }

}