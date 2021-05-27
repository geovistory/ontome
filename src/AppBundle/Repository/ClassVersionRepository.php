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
     * @param array $namespacesId
     * @return object|null
     * @throws \Doctrine\DBAL\DBALException
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

    public function findIdAndStandardLabelOfClassesVersionByNamespacesId(array $namespacesId)
    {
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT cv.fk_class AS id, cv.standard_label AS \"standardLabel\", cls.identifier_in_namespace AS \"identifierInNamespace\", topns.root_namespace_prefix AS \"rootNamespacePrefix\"
                FROM che.class_version cv
                LEFT JOIN che.class cls ON cls.pk_class = cv.fk_class
                LEFT JOIN che.namespace ns ON cv.fk_namespace_for_version = ns.pk_namespace
                LEFT JOIN che.namespace topns ON ns.fk_top_level_namespace = topns.pk_namespace
                WHERE fk_namespace_for_version IN (".$in.")";

        $em = $this->getEntityManager();
        $conn = $em->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($namespacesId);

        return $stmt->fetchAll();
    }

}