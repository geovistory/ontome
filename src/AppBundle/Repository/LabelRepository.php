<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 26/04/2017
 * Time: 11:38
 */

namespace AppBundle\Repository;


use AppBundle\Entity\Project;
use Doctrine\ORM\EntityRepository;

class LabelRepository extends EntityRepository
{
    /**
     * @param string $query
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findByFullTextSearch($query)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        // Rechercher tous les txtp correspondant à la recherche
        // comparateur: to_tsvector('english', CONCAT(lbl.label, ' ', lbl.inverse_label)) @@ plainto_tsquery('english', :query)
        // On concatene label avec inverse_label pour chercher les 2 en même temps
        // Il y a plusieurs unions car il faut parfois récupérer l'identifier, le standard label de l'entité ou le standard label du NS...

        $sql = "SELECT lbl.*, c.identifier_in_namespace, cv.standard_label, ns.standard_label as ns_standard_label FROM che.label lbl
                JOIN che.class c ON lbl.fk_class = c.pk_class 
                JOIN che.class_version cv ON lbl.fk_class = cv.fk_class AND lbl.fk_namespace_for_version = cv.fk_namespace_for_version
                JOIN che.namespace ns ON lbl.fk_namespace_for_version = ns.pk_namespace
                WHERE lbl.fk_class IS NOT NULL AND to_tsvector('english', CONCAT(lbl.label, ' ', lbl.inverse_label)) @@ plainto_tsquery('english', :query)
                UNION 
                SELECT lbl.*, p.identifier_in_namespace, pv.standard_label, ns.standard_label as ns_standard_label FROM che.label lbl
                JOIN che.property p ON lbl.fk_property = p.pk_property
                JOIN che.property_version pv ON lbl.fk_property = pv.fk_property AND lbl.fk_namespace_for_version = pv.fk_namespace_for_version
                JOIN che.namespace ns ON lbl.fk_namespace_for_version = ns.pk_namespace
                WHERE lbl.fk_property IS NOT NULL AND to_tsvector('english', CONCAT(lbl.label, ' ', lbl.inverse_label)) @@ plainto_tsquery('english', :query)
                UNION 
                SELECT lbl.*, '', ns.standard_label, '' as ns_standard_label FROM che.label lbl
                JOIN che.namespace ns ON lbl.fk_namespace = ns.pk_namespace
                WHERE lbl.fk_namespace IS NOT NULL AND to_tsvector('english', CONCAT(lbl.label, ' ', lbl.inverse_label)) @@ plainto_tsquery('english', :query)
                UNION 
                SELECT lbl.*, '', prf.standard_label, '' as ns_standard_label FROM che.label lbl
                JOIN che.profile prf ON lbl.fk_profile = prf.pk_profile
                WHERE lbl.fk_profile IS NOT NULL AND to_tsvector('english', CONCAT(lbl.label, ' ', lbl.inverse_label)) @@ plainto_tsquery('english', :query)
                UNION 
                SELECT lbl.*, '', prj.standard_label, '' as ns_standard_label FROM che.label lbl
                JOIN che.project prj ON lbl.fk_project = prj.pk_project
                WHERE lbl.fk_project IS NOT NULL AND to_tsvector('english', CONCAT(lbl.label, ' ', lbl.inverse_label)) @@ plainto_tsquery('english', :query)
                UNION 
                SELECT lbl.*, '' as identifier_in_namespace, '' as standard_label, '' as ns_standard_label FROM che.label lbl
                WHERE lbl.fk_property IS NULL  
                  AND lbl.fk_class IS NULL 
                  AND lbl.fk_namespace IS NULL 
                  AND lbl.fk_profile IS NULL 
                  AND lbl.fk_project IS NULL 
                  AND to_tsvector('english', CONCAT(lbl.label, ' ', lbl.inverse_label)) @@ plainto_tsquery('english', :query);";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('query' => $query));

        return $stmt->fetchAll();
    }

}