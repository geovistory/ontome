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
        $sql = "SELECT 
                       txtp.*, 
                       null as link_entity_route,
                       0 as link_entity_id, 
                       0 as link_entity_version_id,
                       c.identifier_in_namespace, 
                       cv.standard_label, 
                       ns.standard_label as ns_standard_label, 
                       ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.class c ON txtp.fk_class = c.pk_class 
                JOIN che.class_version cv ON txtp.fk_class = cv.fk_class AND txtp.fk_namespace_for_version = cv.fk_namespace_for_version
                JOIN che.namespace ns ON txtp.fk_namespace_for_version = ns.pk_namespace
                WHERE txtp.fk_class IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION --2 Txtp des propriétés
                SELECT 
                       txtp.*, 
                       null as link_entity_route,
                       0 as link_entity_id, 
                       0 as link_entity_version_id,
                       p.identifier_in_namespace, 
                       pv.standard_label, 
                       ns.standard_label as ns_standard_label, 
                       ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.property p ON txtp.fk_property = p.pk_property
                JOIN che.property_version pv ON txtp.fk_property = pv.fk_property AND txtp.fk_namespace_for_version = pv.fk_namespace_for_version
                JOIN che.namespace ns ON txtp.fk_namespace_for_version = ns.pk_namespace
                WHERE txtp.fk_property IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION --3 Txtp des namespaces
                SELECT 
                       txtp.*,
                       null as link_entity_route,
                       0 as link_entity_id, 
                       0 as link_entity_version_id,
                       null, 
                       ns.standard_label, 
                       '' as ns_standard_label, 
                       ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.namespace ns ON txtp.fk_namespace = ns.pk_namespace
                WHERE txtp.fk_namespace IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION --4 Txtp des profiles
                SELECT 
                       txtp.*,
                       null as link_entity_route,
                       0 as link_entity_id, 
                       0 as link_entity_version_id,
                       null, 
                       prf.standard_label, 
                       '' as ns_standard_label, 
                       ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.profile prf ON txtp.fk_profile = prf.pk_profile
                WHERE txtp.fk_profile IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION --5 Txtp des projets
                SELECT 
                       txtp.*, 
                       null as link_entity_route,
                       0 as link_entity_id, 
                       0 as link_entity_version_id,
                       null, 
                       prj.standard_label, 
                       '' as ns_standard_label, 
                       ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.project prj ON txtp.fk_project = prj.pk_project
                WHERE txtp.fk_project IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION --6 Txtp des relations hierarchiques classes
                SELECT 
                       txtp.*, 
                       'class_show_with_version' as link_entity_route, 
                       cv.fk_class as link_entity_id, 
                       cv.fk_namespace_for_version as link_entity_version_id, 
                       cl.identifier_in_namespace, 
                       cv.standard_label, 
                       ns.standard_label as ns_standard_label, 
                       ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.is_subclass_of iso ON txtp.fk_is_subclass_of = iso.pk_is_subclass_of
                JOIN che.namespace ns ON ns.pk_namespace = iso.fk_namespace_for_version                    
                JOIN che.class cl ON iso.is_child_class = cl.pk_class
                JOIN che.class_version cv ON iso.is_child_class = cv.fk_class AND iso.fk_child_class_namespace = cv.fk_namespace_for_version
                WHERE txtp.fk_is_subclass_of IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION --7 Txtp des relations hierarchiques propriétés
                SELECT 
                       txtp.*, 
                       'property_show_with_version' as link_entity_route, 
                       pv.fk_property as link_entity_id, 
                       pv.fk_namespace_for_version as link_entity_version_id, 
                       pr.identifier_in_namespace,
                       pv.standard_label, 
                       ns.standard_label as ns_standard_label, 
                       ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.is_subproperty_of iso ON txtp.fk_is_subproperty_of = iso.pk_is_subproperty_of
                JOIN che.namespace ns ON ns.pk_namespace = iso.fk_namespace_for_version
                JOIN che.property pr ON iso.is_child_property = pr.pk_property
                JOIN che.property_version pv ON iso.is_child_property = pv.fk_property AND iso.fk_child_property_namespace = pv.fk_namespace_for_version
                WHERE txtp.fk_is_subproperty_of IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION --8 Txtp des relations (autres) classes
                SELECT 
                       txtp.*,
                       'class_show_with_version' as link_entity_route,
                       cv.fk_class as link_entity_id, 
                       cv.fk_namespace_for_version as link_entity_version_id, 
                       cl.identifier_in_namespace, 
                       cv.standard_label, 
                       ns.standard_label as ns_standard_label, 
                       ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.entity_association ea ON txtp.fk_entity_association = ea.pk_entity_association
                JOIN che.namespace ns ON ns.pk_namespace = ea.fk_namespace_for_version
                JOIN che.class cl ON ea.fk_source_class = cl.pk_class
                JOIN che.class_version cv ON ea.fk_source_class = cv.fk_class AND ea.fk_source_namespace_for_version = cv.fk_namespace_for_version
                WHERE txtp.fk_entity_association IS NOT NULL AND ea.fk_source_class IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION --9 Txtp des relations (autres) propriétés
                SELECT 
                       txtp.*, 
                       'property_show_with_version' as link_entity_route, 
                       pv.fk_property as link_entity_id, 
                       pv.fk_namespace_for_version as link_entity_version_id, 
                       pr.identifier_in_namespace, 
                       pv.standard_label, 
                       ns.standard_label as ns_standard_label, 
                       ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                JOIN che.entity_association ea ON txtp.fk_entity_association = ea.pk_entity_association
                JOIN che.namespace ns ON ns.pk_namespace = ea.fk_namespace_for_version
                JOIN che.property pr ON ea.fk_source_property = pr.pk_property
                JOIN che.property_version pv ON ea.fk_source_property = pv.fk_property AND ea.fk_source_namespace_for_version = pv.fk_namespace_for_version
                WHERE txtp.fk_is_subproperty_of IS NOT NULL AND ea.fk_source_property IS NOT NULL AND to_tsvector('english', txtp.text_property) @@ plainto_tsquery('english', :query)
                    UNION -- Tous les autres
                SELECT 
                       txtp.*, 
                       null as link_entity_route,
                       0 as link_entity_id, 
                       0 as link_entity_version_id, 
                       null as identifier_in_namespace, 
                       '' as standard_label, 
                       '' as ns_standard_label, 
                       ts_rank(to_tsvector('english', txtp.text_property), plainto_tsquery('english', :query)) as score 
                FROM che.text_property txtp
                WHERE txtp.fk_property IS NULL 
                  AND txtp.fk_class IS NULL 
                  AND txtp.fk_namespace IS NULL 
                  AND txtp.fk_profile IS NULL 
                  AND txtp.fk_project IS NULL 
                  AND txtp.fk_is_subclass_of IS NULL 
                  AND txtp.fk_is_subproperty_of IS NULL 
                  AND txtp.fk_entity_association IS NULL 
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
        $sql = "SELECT CAST(plainto_tsquery('english', :query) AS text);";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('query' => $query));

        return $stmt->fetchAll();
    }
}