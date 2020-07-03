<?php
/**
 * Created by PhpStorm.
 * User: Alexandre
 * Date: 02/07/2020
 * Time: 15:53
 */

namespace AppBundle\Repository;

use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoNamespace;
use Doctrine\ORM\EntityRepository;

class ClassVersionRepository extends EntityRepository
{
    /**
     * @param OntoClass $class
     * @param array $namespaces
     */
    public function findClassVersionByClassAndNamespacesId(OntoClass $class, array $namespacesId)
    {
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT pk_class_version 
                FROM che.class_version 
                WHERE fk_class = ? AND fk_namespace_for_version IN (".$in.")";

        $em = $this->getEntityManager();
        $conn = $em->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($class->getId()), $namespacesId));

        return $em->getRepository('AppBundle:OntoClassVersion')->find($stmt->fetch()['pk_class_version']);
    }
}