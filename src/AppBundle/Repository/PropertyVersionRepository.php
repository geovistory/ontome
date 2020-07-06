<?php
/**
 * Created by PhpStorm.
 * User: Alexandre
 * Date: 02/07/2020
 * Time: 15:53
 */

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

class PropertyVersionRepository extends EntityRepository
{

    public function findIdAndStandardLabelOfPropertiesVersionByNamespacesId(array $namespacesId)
    {
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT pv.fk_property AS id, pv.standard_label AS \"standardLabel\", p.identifier_in_namespace AS \"identifierInNamespace\"
                FROM che.property_version pv JOIN che.property p ON p.pk_property = pv.fk_property
                WHERE fk_namespace_for_version IN (".$in.")";

        $em = $this->getEntityManager();
        $conn = $em->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($namespacesId);

        return $stmt->fetchAll();
    }

}