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

class TextPropertyRepository extends EntityRepository
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
        // comparateur: to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
        // Il y a plusieurs unions car il faut parfois récupérer l'identifier, le standard label de l'entité ou le standard label du NS...
        $sql = "SELECT txtp.*, c.identifier_in_namespace, cv.standard_label, ns.standard_label as ns_standard_label, ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.class c ON txtp.fk_class = c.pk_class 
                JOIN che.class_version cv ON txtp.fk_class = cv.fk_class AND txtp.fk_namespace_for_version = cv.fk_namespace_for_version
                JOIN che.namespace ns ON txtp.fk_namespace_for_version = ns.pk_namespace
                WHERE txtp.fk_class IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION 
                SELECT txtp.*, p.identifier_in_namespace, pv.standard_label, ns.standard_label as ns_standard_label, ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.property p ON txtp.fk_property = p.pk_property
                JOIN che.property_version pv ON txtp.fk_property = pv.fk_property AND txtp.fk_namespace_for_version = pv.fk_namespace_for_version
                JOIN che.namespace ns ON txtp.fk_namespace_for_version = ns.pk_namespace
                WHERE txtp.fk_property IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION 
                SELECT txtp.*, '', ns.standard_label, '' as ns_standard_label, ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.namespace ns ON txtp.fk_namespace = ns.pk_namespace
                WHERE txtp.fk_namespace IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION 
                SELECT txtp.*, '', prf.standard_label, '' as ns_standard_label, ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.profile prf ON txtp.fk_profile = prf.pk_profile
                WHERE txtp.fk_profile IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION 
                SELECT txtp.*, '', prj.standard_label, '' as ns_standard_label, ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score FROM che.text_property txtp
                JOIN che.project prj ON txtp.fk_project = prj.pk_project
                WHERE txtp.fk_project IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION 
                SELECT txtp.*, '' as identifier_in_namespace, '' as standard_label, '' as ns_standard_label, ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score FROM che.text_property txtp
                WHERE txtp.fk_property IS NULL 
                  AND txtp.fk_class IS NULL 
                  AND txtp.fk_namespace IS NULL 
                  AND txtp.fk_profile IS NULL 
                  AND txtp.fk_project IS NULL 
                  AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query);";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('query' => $query));

        return $stmt->fetchAll();
    }

    /**
     * @param string $query
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findWhatSearch($query){
        // Fonction retournant le résultat de plainto_tsquery pour informer l'utilisateur les termes recherchées
        $conn = $this->getEntityManager()
            ->getConnection();
        $sql = "SELECT plainto_tsquery('english', :query);";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('query' => $query));

        return $stmt->fetchAll();
    }
}