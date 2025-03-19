<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/07/2017
 * Time: 12:01
 */

namespace AppBundle\Repository;

use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoClassVersion;
use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\Property;
use AppBundle\Entity\PropertyVersion;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class PropertyRepository extends EntityRepository
{

    /**
     * @return Property[]
     */
    public function findAllOrderedById()
    {
        return $this->createQueryBuilder('property')
            ->join('property.namespaces', 'nspc')
            ->addSelect('nspc')
            ->leftJoin('nspc.referencedVersion', 'referencedVersion')
            ->addSelect('referencedVersion')
            ->orderBy('property.id', 'DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * @param array $namespacesId
     * @return Property[]
     */
    public function findPropertiesByNamespacesIdQueryBuilder(array $namespacesId){
        $qb = $this->createQueryBuilder('property')
            ->join('property.propertyVersions','pv')
            ->join('pv.namespaceForVersion','nfv')
            ->where('nfv.id IN (:namespacesId)')
            ->setParameter('namespacesId', $namespacesId);

        return $qb;
    }

    /**
     * @param array $namespacesId
     * @return Property[]
     */
    public function findPropertiesByNamespacesId(array $namespacesId){
        $qb = $this->findPropertiesByNamespacesIdQueryBuilder($namespacesId);

        $properties = $qb->getQuery()->execute();

        return $properties;
    }

    /**
     * @param PropertyVersion $propertyVersion
     * @param array $namespacesId
     * @return void
     * Remplace les fonctions obsolètes findAncestorsById et findFilteredAncestorsById
     */
    public function findAncestorsByPropertyVersionAndNamespacesId(PropertyVersion $propertyVersion, array $namespacesId)
    {
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "WITH t_namespaces AS(
                    SELECT pk_namespace FROM che.namespace WHERE pk_namespace IN (".$in.")),
                t_ascendants_properties AS
                (
                SELECT pk_parent,
                     parent_identifier,
                     DEPTH,
                     ARRAY_TO_STRING(_path,'|') ancestors,
                        pk_is_subproperty_of,
                        fk_namespace_for_version
                  FROM che.ascendant_property_hierarchy(?, (SELECT array_agg(pk_namespace) FROM t_namespaces))
                )
                SELECT t_ascendants_properties.pk_parent  AS id,
                       t_ascendants_properties.parent_identifier AS identifier,
                       pv.has_domain,
                       domain.identifier_in_namespace AS \"domainIdentifier\",
                       domain_version.standard_label AS \"domainStandardLabel\",
                       domain_version.fk_namespace_for_version AS \"domainNamespaceId\",
                       che.get_root_namespace_prefix(che.get_root_namespace(domain_version.fk_namespace_for_version)) AS \"domainRootNamespacePrefix\",
                       (SELECT label FROM che.get_namespace_labels(domain_version.fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS \"domainNamespaceLabel\",
                       pv.domain_instances_min_quantifier,
                       pv.domain_instances_max_quantifier,
                       pv.has_range,
                       range.identifier_in_namespace AS \"rangeIdentifier\",
                       range_version.standard_label AS \"rangeStandardLabel\",
                       range_version.fk_namespace_for_version AS \"rangeNamespaceId\",
                       che.get_root_namespace_prefix(che.get_root_namespace(range_version.fk_namespace_for_version)) AS \"rangeRootNamespacePrefix\",
                       (SELECT label FROM che.get_namespace_labels(range_version.fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS \"rangeNamespaceLabel\",
                       pv.range_instances_min_quantifier,
                       pv.range_instances_max_quantifier,
                       t_ascendants_properties.DEPTH,
                       replace(t_ascendants_properties.ancestors, '|', '→') AS ancestors,
                       che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                       (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS \"rootNamespaceLabel\",
                       nsp.pk_namespace AS \"propertyNamespaceId\",
                       nsp.standard_label AS \"propertyNamespaceLabel\",
                       t_ascendants_properties.fk_namespace_for_version
                FROM t_ascendants_properties,
                     che.property p JOIN che.property_version pv ON p.pk_property = pv.fk_property,
                     che.namespace nsp,
                     che.class domain JOIN che.class_version domain_version ON domain.pk_class = domain_version.fk_class,
                     che.class range JOIN che.class_version range_version ON range.pk_class = range_version.fk_class
                WHERE p.pk_property = t_ascendants_properties.pk_parent
    AND   nsp.pk_namespace = pv.fk_namespace_for_version
    AND pv.fk_namespace_for_version IN (SELECT pk_namespace FROM t_namespaces)
    AND domain_version.fk_namespace_for_version IN (SELECT pk_namespace FROM t_namespaces)
    AND range_version.fk_namespace_for_version IN (SELECT pk_namespace FROM t_namespaces)
    AND depth > 1
    AND pv.has_domain = domain.pk_class
    AND pv.has_range = range.pk_class
    AND t_ascendants_properties.fk_namespace_for_version IN (SELECT pk_namespace FROM t_namespaces)
                GROUP BY t_ascendants_properties.pk_parent,
                     t_ascendants_properties.parent_identifier,
                     pv.has_domain,
                       domain.identifier_in_namespace,
                       domain_version.standard_label,
                       domain_version.fk_namespace_for_version,
                       pv.domain_instances_min_quantifier,
                       pv.domain_instances_max_quantifier,
                       pv.has_range,
                       range.identifier_in_namespace,
                       range_version.standard_label,
                       range_version.fk_namespace_for_version,
                       pv.range_instances_min_quantifier,
                       pv.range_instances_max_quantifier,
                     t_ascendants_properties.depth,
                     t_ascendants_properties.ancestors,
                     nsp.pk_namespace,
                     t_ascendants_properties.fk_namespace_for_version;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge($namespacesId, array($propertyVersion->getProperty()->getId())));

        return $stmt->fetchAll();
    }



    /**
     * @param PropertyVersion $propertyVersion
     * @param array $namespacesId
     * @return array Remplace les fonctions obsolètes findDescendantsById et findFilteredDescendantsById
     * Remplace les fonctions obsolètes findDescendantsById et findFilteredDescendantsById
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findDescendantsByPropertyVersionAndNamespacesId(PropertyVersion $propertyVersion, array $namespacesId)
    {
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT  t_descendants_properties.pk_child AS id,
                       t_descendants_properties.child_identifier as identifier,
                       pv.has_domain,
                       domain_class.identifier_in_namespace AS \"domainIdentifier\",
                       domain_cv.standard_label AS \"domainStandardLabel\",
                       domain_cv.fk_namespace_for_version AS \"domainNamespaceId\",
                       che.get_root_namespace_prefix(che.get_root_namespace(domain_cv.fk_namespace_for_version)) AS \"domainRootNamespacePrefix\",
                       (SELECT label FROM che.get_namespace_labels(domain_cv.fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS \"domainNamespaceLabel\",
                       pv.domain_instances_min_quantifier,
                       pv.domain_instances_max_quantifier,
                       pv.has_range,
                       range_class.identifier_in_namespace AS \"rangeIdentifier\",
                       range_cv.standard_label AS \"rangeStandardLabel\",
                       range_cv.fk_namespace_for_version AS \"rangeNamespaceId\",
                       che.get_root_namespace_prefix(che.get_root_namespace(range_cv.fk_namespace_for_version)) AS \"rangeRootNamespacePrefix\",
                       (SELECT label FROM che.get_namespace_labels(range_cv.fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS \"rangeNamespaceLabel\",
                       pv.range_instances_min_quantifier,
                       pv.range_instances_max_quantifier,
                        t_descendants_properties.depth,
                        replace(t_descendants_properties.descendants, '|', '→') AS descendants,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                       (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS \"rootNamespaceLabel\",
                       nsp.pk_namespace AS \"propertyNamespaceId\",
                       nsp.standard_label AS \"propertyNamespaceLabel\"
                    FROM che.descendant_property_hierarchy(?, ARRAY[".$in."]::integer[]) t_descendants_properties,
                         che.property p JOIN che.property_version pv ON p.pk_property = pv.fk_property,
                         che.namespace nsp,
                         che.class domain_class JOIN che.class_version domain_cv ON domain_class.pk_class = domain_cv.fk_class,
                         che.class range_class JOIN che.class_version range_cv ON range_class.pk_class = range_cv.fk_class
                    WHERE pv.fk_property = pk_child
                    AND   nsp.pk_namespace = pv.fk_namespace_for_version
                    AND pv.has_domain = domain_class.pk_class
                    AND pv.has_range = range_class.pk_class
                    AND pv.fk_namespace_for_version IN (".$in.")
                    AND domain_cv.fk_namespace_for_version IN (".$in.")
                    AND range_cv.fk_namespace_for_version IN (".$in.");";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($propertyVersion->getProperty()->getId()), $namespacesId, $namespacesId, $namespacesId, $namespacesId));

        return $stmt->fetchAll();
    }


    /**
     * @param OntoClassVersion $classVersion
     * @param array $namespacesId
     * @return Property[]
     * @throws \Doctrine\DBAL\DBALException
     * Remplace les fonctions obsolètes findFilteredOutgoingPropertiesById et findOutgoingPropertiesById
     */
    public function findOutgoingPropertiesByClassVersionAndNamespacesId(OntoClassVersion $classVersion, array $namespacesId){
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT  identifier_property AS property,
                        identifier_range AS range,
                        v.fk_range_namespace AS \"rangeNamespaceId\",
                        che.get_root_namespace_prefix(che.get_root_namespace(v.fk_range_namespace)) AS \"rangeRootNamespacePrefix\",
                        pk_property AS \"propertyId\",
                        pk_range AS \"rangeId\",
                        v.fk_namespace_for_version AS \"propertyNamespaceId\",
                        array_append(array_agg(asrefns.fk_referenced_namespace), v.fk_namespace_for_version) AS \"selectedNamespacesId\",
                         (
                            SELECT array_agg(pk_namespace) 
                            FROM che.get_all_references_namespaces_for_namespace(v.fk_namespace_for_version)
                          ) AS \"allSelectedNamespacesId\",
                        identifier_domain AS domain,
                        v.fk_domain_namespace AS \"domainNamespaceId\",
                        che.get_root_namespace(fk_namespace_for_version) AS \"rootNamespaceId\",
                        che.get_root_namespace_prefix(che.get_root_namespace(fk_namespace_for_version)) AS \"propertyRootNamespacePrefix\",
                        (SELECT label FROM che.get_namespace_labels(fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS namespace,
                        (SELECT label FROM che.get_namespace_labels(v.fk_range_namespace) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS \"rangeNamespace\",
                        (SELECT CASE WHEN pv.domain_instances_min_quantifier IS NOT NULL THEN REPLACE(CONCAT('(',pv.domain_instances_min_quantifier,',',pv.domain_instances_max_quantifier,':',pv.range_instances_min_quantifier,',',pv.range_instances_max_quantifier,')'), '-1', 'n') ELSE '' END FROM che.property_version pv WHERE v.pk_property = pv.fk_property AND pv.fk_namespace_for_version = v.fk_namespace_for_version) AS \"quantifiersString\"
                FROM    che.v_properties_with_domain_range v
                LEFT JOIN   che.associates_referenced_namespace asrefns ON v.fk_namespace_for_version = asrefns.fk_namespace
                WHERE   v.pk_domain = ?
                AND     fk_domain_namespace IN (".$in.")
                AND     fk_range_namespace IN (".$in.")
                AND     fk_namespace_for_version IN (".$in.")
                GROUP BY    v.identifier_property, 
                            v.identifier_range, 
                            v.fk_range_namespace, 
                            v.pk_property, 
                            v.pk_range, 
                            v.fk_namespace_for_version, 
                            v.identifier_domain, 
                            v.fk_domain_namespace,
							v.identifier_in_namespace
                ORDER BY 
                    CASE
                        WHEN v.fk_namespace_for_version = ".$classVersion->getNamespaceForVersion()->getId()." THEN 1
                        ELSE 2 END ASC,
                        che.get_root_namespace_prefix(che.get_root_namespace(fk_namespace_for_version)) ASC,
						LENGTH(v.identifier_in_namespace) ASC, v.identifier_in_namespace ASC;
                ;
                ";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId, $namespacesId, $namespacesId));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClassVersion $classVersion
     * @param array $namespacesId
     * @return Property[]
     * @throws \Doctrine\DBAL\DBALException
     * Remplace les fonctions obsolètes findFilteredOutgoingInheritedPropertiesById et findOutgoingInheritedPropertiesById
     */
    public function findOutgoingInheritedPropertiesByClassVersionAndNamespacesId(OntoClassVersion $classVersion, array $namespacesId){
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT concat(present_class_identifier, ' ', present_class_label) AS domain,
                  pk_domain_class AS \"parentClassId\", 
                  concat(domain_identifier_in_namespace, ' ', domain_standard_label) AS \"parentClass\",
                  pk_property AS \"propertyId\",
                  concat(identifier_in_namespace, ' ', standard_label) AS property,
                  fk_namespace_for_version AS \"propertyNamespaceId\",
                  array_append(array_agg(asrefns.fk_referenced_namespace), v.fk_namespace_for_version) AS \"selectedNamespacesId\",
                 (
                    SELECT array_agg(pk_namespace) 
                    FROM che.get_all_references_namespaces_for_namespace(v.fk_namespace_for_version)
                  ) AS \"allSelectedNamespacesId\",
                  pk_range_class AS \"rangeId\",
                  concat(range_identifier_in_namespace, ' ', range_standard_label) AS range,
                  fk_range_namespace_for_version AS \"rangeNamespaceId\",
                  fk_domain_namespace_for_version AS \"domainNamespaceId\",
                  che.get_root_namespace_prefix(che.get_root_namespace(fk_namespace_for_version)) AS \"propertyRootNamespacePrefix\",
                  replace(ancestors, '|', '→') AS ancestors,
                  (SELECT label FROM che.get_namespace_labels(fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS namespace
                FROM che.class_outgoing_inherited_properties(?, ARRAY[".$in."]::integer[]) v
                LEFT JOIN che.associates_referenced_namespace asrefns ON v.fk_namespace_for_version = asrefns.fk_namespace
                WHERE fk_namespace_for_version IN (".$in.")
                AND v.fk_range_namespace_for_version IN (".$in.")
                AND v.fk_domain_namespace_for_version IN (".$in.")
                GROUP BY v.present_class_identifier, v.present_class_label, v.pk_domain_class, v.domain_identifier_in_namespace, v.domain_standard_label, v.standard_label, v.range_standard_label, v.ancestors, v.identifier_in_namespace, v.pk_property, v.fk_namespace_for_version, v.pk_range_class, v.range_identifier_in_namespace, v.fk_domain_namespace_for_version, v.fk_range_namespace_for_version;";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId, $namespacesId, $namespacesId, $namespacesId));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClassVersion $classVersion
     * @param array $namespacesId
     * @return Property[]
     * @throws \Doctrine\DBAL\DBALException
     * Remplace les fonctions obsolètes findFilteredIngoingPropertiesById et findIngoingPropertiesById
     */
    public function findIngoingPropertiesByClassVersionAndNamespacesId(OntoClassVersion $classVersion, array $namespacesId){
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT pk_domain AS \"domainId\",
                  identifier_domain AS domain,
                  identifier_property AS property,
                  pk_property AS \"propertyId\",
                  v.fk_namespace_for_version AS \"propertyNamespaceId\",
                  array_append(array_agg(asrefns.fk_referenced_namespace), v.fk_namespace_for_version) AS \"selectedNamespacesId\",
                 (
                    SELECT array_agg(pk_namespace) 
                    FROM che.get_all_references_namespaces_for_namespace(v.fk_namespace_for_version)
                  ) AS \"allSelectedNamespacesId\",
                  pk_range AS \"rangeId\",
                  identifier_range AS range,
                    che.get_root_namespace_prefix(che.get_root_namespace(v.fk_range_namespace)) AS \"rangeRootNamespacePrefix\",
                  v.fk_range_namespace AS \"rangeNamespaceId\",
                    che.get_root_namespace_prefix(che.get_root_namespace(v.fk_domain_namespace)) AS \"domainRootNamespacePrefix\",
                  v.fk_domain_namespace AS \"domainNamespaceId\",
                  che.get_root_namespace(fk_namespace_for_version) AS \"rootNamespaceId\",
                  che.get_root_namespace_prefix(che.get_root_namespace(fk_namespace_for_version)) AS \"propertyRootNamespacePrefix\",
                  (SELECT label FROM che.get_namespace_labels(fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS namespace,
                  (SELECT label FROM che.get_namespace_labels(v.fk_range_namespace) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS \"domainNamespace\",
                  (SELECT CASE WHEN pv.domain_instances_min_quantifier IS NOT NULL THEN REPLACE(CONCAT('(',pv.domain_instances_min_quantifier,',',pv.domain_instances_max_quantifier,':',pv.range_instances_min_quantifier,',',pv.range_instances_max_quantifier,')'), '-1', 'n') ELSE '' END FROM che.property_version pv WHERE v.pk_property = pv.fk_property AND pv.fk_namespace_for_version = v.fk_namespace_for_version) AS \"quantifiersString\"
                FROM che.v_properties_with_domain_range v
                LEFT JOIN che.associates_referenced_namespace asrefns ON v.fk_namespace_for_version = asrefns.fk_namespace
                WHERE pk_range = ?
                AND fk_range_namespace IN (".$in.")
                AND fk_domain_namespace IN (".$in.")
                AND fk_namespace_for_version IN (".$in.")
                GROUP BY v.pk_domain, v.identifier_property, v.identifier_range, v.fk_range_namespace, v.pk_property, v.pk_range, v.fk_namespace_for_version, v.identifier_domain, v.fk_domain_namespace, v.identifier_in_namespace
                ORDER BY 
                    CASE
                        WHEN v.fk_namespace_for_version = ".$classVersion->getNamespaceForVersion()->getId()." THEN 1
                        ELSE 2 END ASC,
                        che.get_root_namespace_prefix(che.get_root_namespace(fk_namespace_for_version)) ASC,
						LENGTH(v.identifier_in_namespace) ASC, v.identifier_in_namespace ASC;";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId, $namespacesId, $namespacesId));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClassVersion $classVersion
     * @param array $namespacesId
     * @return Property[]
     * @throws \Doctrine\DBAL\DBALException
     * Remplace les fonctions obsolètes findFilteredIngoingInheritedPropertiesById et findIngoingInheritedPropertiesById
     */
    public function findIngoingInheritedPropertiesByClassVersionAndNamespacesId(OntoClassVersion $classVersion, array $namespacesId){
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT pk_domain_class AS \"domainId\",
                  concat(domain_identifier_in_namespace, ' ', domain_standard_label) AS domain,
                  concat(identifier_in_namespace, ' ', pv.standard_label) AS property,
                  pk_property AS \"propertyId\",
                  pv.fk_namespace_for_version AS \"propertyNamespaceId\",
                  pk_range_class AS \"rangeId\",
                  range_identifier_in_namespace AS range,
                  v.fk_range_namespace_for_version AS \"rangeNamespaceId\",
                  v.fk_domain_namespace_for_version AS \"domainNamespaceId\",
                  che.get_root_namespace_prefix(che.get_root_namespace(pv.fk_namespace_for_version)) AS \"propertyRootNamespacePrefix\",
                  replace(ancestors, '|', '→') AS ancestors,
                 (
                    SELECT array_agg(pk_namespace) 
                    FROM che.get_all_references_namespaces_for_namespace(v.fk_namespace_for_version)
                  ) AS \"allSelectedNamespacesId\",
                  (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS namespace
                FROM che.class_ingoing_inherited_properties(?, ARRAY[".$in."]::integer[]) v,
                  che.property_version pv,
                  che.namespace nsp 
                WHERE pv.fk_property = pk_property
                AND nsp.pk_namespace = pv.fk_namespace_for_version
                AND pv.fk_namespace_for_version IN (".$in.")
                AND v.fk_range_namespace_for_version IN (".$in.")
                AND v.fk_domain_namespace_for_version IN (".$in.");";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId, $namespacesId, $namespacesId, $namespacesId));

        return $stmt->fetchAll();
    }

    /**
     * @param PropertyVersion $propertyVersion
     * @param array $namespacesId
     * Remplace la fonction obsolète findDomainRangeById
     */
    public function findDomainAndRangeByPropertyVersionAndNamespacesId(PropertyVersion $propertyVersion, array $namespacesId){
        $conn = $this->getEntityManager()->getConnection();
        // Construit la chaine ?,? pour les namespacesId dans la requête SQL
        $in  = str_repeat('?,', count($namespacesId) - 1) . '?';

        $sql = "SELECT pv.has_domain AS \"domainId\",
                c_domain.identifier_in_namespace || ' ' || cv_domain.standard_label AS \"domainIdentifier\",
                cv_domain.fk_namespace_for_version AS \"domainNamespaceId\",
                pr.identifier_in_namespace || ' ' || pv.standard_label AS \"propertyIdentifier\",
                pv.has_range AS \"rangeId\",
                c_range.identifier_in_namespace || ' ' || cv_range.standard_label AS \"rangeIdentifier\",
                cv_range.fk_namespace_for_version AS \"rangeNamespaceId\"
                FROM che.property_version pv 
                LEFT JOIN che.property pr ON pr.pk_property = pv.fk_property
                LEFT JOIN che.class c_domain ON pv.has_domain = c_domain.pk_class
                LEFT JOIN che.class_version cv_domain ON pv.has_domain = cv_domain.fk_class AND cv_domain.fk_namespace_for_version = pv.fk_domain_namespace
                LEFT JOIN che.class c_range ON pv.has_range = c_range.pk_class 
                LEFT JOIN che.class_version cv_range ON pv.has_range = cv_range.fk_class AND cv_range.fk_namespace_for_version = pv.fk_range_namespace
                WHERE pr.pk_property = ?
                AND pv.fk_namespace_for_version IN (".$in.");";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($propertyVersion->getProperty()->getId()), $namespacesId));

        return $stmt->fetch();
    }

    /**
     * @return array
     */
    public function findPropertiesTree()
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(tree)) AS json FROM che.tree_properties_with_css_color(155) tree";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @return array
     */
    public function findPropertiesTreeLegend()
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(legend)) AS json FROM (SELECT pk_css_color, css_color, label FROM che.css_color WHERE context = 'ontologies_properties_tree' ORDER BY pk_css_color) legend;";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param Project $project
     * @return array
     */
    public function findPropertiesByProjectId(Project $project)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(result)) AS json FROM (SELECT * FROM api.v_property_profile_project WHERE pk_project = :project ) result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('project' => $project->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Profile $profile
     * @return array
     */
    public function findPropertiesByProfileId(Profile $profile){
        return $this->createQueryBuilder('property')
            ->join('property.propertyVersions','pv')
            ->join('pv.namespaceForVersion','nspc')
            ->join('property.profiles', 'profile')
            ->where('profile.id = :profile')
            ->addSelect('profile')
            ->addSelect('pv')
            ->addSelect('nspc')
            ->leftJoin('nspc.referencedVersion', 'referencedVersion')
            ->addSelect('referencedVersion')
            ->orderBy('property.id','DESC')
            ->setParameter('profile', $profile->getId())
            ->getQuery()
            ->execute();
    }

    /**
     * @param OntoClass $class
     * @param Profile $profile
     * @return array
     */
    public function findOutgoingPropertiesByClassAndProfileId(OntoClass $class, Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT
                    che.get_root_namespace_prefix(che.get_root_namespace(dv.fk_namespace_for_version)) AS domain_root_namespace_prefix,
                    d.pk_class AS domain_id,
                    dv.fk_namespace_for_version AS domain_namespace_id,
                    d.identifier_in_namespace AS domain_identifier,
                    dv.standard_label AS domain_label,
                    pv.domain_instances_min_quantifier AS domain_min_quantifier,
                    pv.domain_instances_max_quantifier AS domain_max_quantifier,
                    p.pk_property AS property_id,
                    p.identifier_in_namespace AS property_identifier,
                    pv.standard_label AS property_label,
                    pv.fk_namespace_for_version AS property_namespace_id,
                    (SELECT label FROM che.get_namespace_labels(pv.fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS namespace,
                    pv.range_instances_min_quantifier AS range_min_quantifier,
                    pv.range_instances_max_quantifier AS range_max_quantifier,
                    che.get_root_namespace_prefix(che.get_root_namespace(rv.fk_namespace_for_version)) AS range_root_namespace_prefix,
                    r.pk_class AS range_id,
                    rv.fk_namespace_for_version AS range_namespace_id,
                    r.identifier_in_namespace AS range_identifier,
                    rv.standard_label AS range_label,
                    CASE
                        WHEN aspro.fk_system_type IS NULL THEN 999
                        ELSE aspro.fk_system_type
                    END AS fk_system_type
                FROM che.property p
                JOIN che.property_version pv ON pv.fk_property = p.pk_property
                JOIN che.class d ON d.pk_class = pv.has_domain 
                JOIN che.class_version dv ON dv.fk_class = pv.has_domain 
                                          AND dv.fk_namespace_for_version = pv.fk_domain_namespace
                JOIN che.class r ON r.pk_class = pv.has_range
                JOIN che.class_version rv ON rv.fk_class = pv.has_range 
                                          AND rv.fk_namespace_for_version = pv.fk_range_namespace
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = p.pk_property 
                                                       AND aspro.fk_profile = :profile 
                                                       AND aspro.fk_inheriting_range_class IS NULL 
                                                       AND aspro.fk_inheriting_domain_class IS NULL
                WHERE pv.fk_namespace_for_version IN (SELECT pk_namespace FROM che.get_all_references_namespaces_for_profile(:profile))
                AND d.pk_class = :class
                ";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId(), 'profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @param Profile $profile
     * @return array
     */
    public function findIncomingPropertiesByClassAndProfileId(OntoClass $class, Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT
                    che.get_root_namespace_prefix(che.get_root_namespace(dv.fk_namespace_for_version)) AS domain_root_namespace_prefix,
                    d.pk_class AS domain_id,
                    dv.fk_namespace_for_version AS domain_namespace_id,
                    d.identifier_in_namespace AS domain_identifier,
                    dv.standard_label AS domain_label,
                    pv.domain_instances_min_quantifier AS domain_min_quantifier,
                    pv.domain_instances_max_quantifier AS domain_max_quantifier,
                    p.pk_property AS property_id,
                    p.identifier_in_namespace AS property_identifier,
                    pv.standard_label AS property_label,
                    pv.fk_namespace_for_version AS property_namespace_id,
                    (SELECT label FROM che.get_namespace_labels(pv.fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS namespace,
                    pv.range_instances_min_quantifier AS range_min_quantifier,
                    pv.range_instances_max_quantifier AS range_max_quantifier,
                    che.get_root_namespace_prefix(che.get_root_namespace(rv.fk_namespace_for_version)) AS range_root_namespace_prefix,
                    r.pk_class AS range_id,
                    rv.fk_namespace_for_version AS range_namespace_id,
                    r.identifier_in_namespace AS range_identifier,
                    rv.standard_label AS range_label,
                    CASE
                        WHEN aspro.fk_system_type IS NULL THEN 999
                        ELSE aspro.fk_system_type
                    END AS fk_system_type
                FROM che.property p
                JOIN che.property_version pv ON pv.fk_property = p.pk_property
                JOIN che.class d ON d.pk_class = pv.has_domain 
                JOIN che.class_version dv ON dv.fk_class = pv.has_domain 
                                          AND dv.fk_namespace_for_version = pv.fk_domain_namespace
                JOIN che.class r ON r.pk_class = pv.has_range
                JOIN che.class_version rv ON rv.fk_class = pv.has_range 
                                          AND rv.fk_namespace_for_version = pv.fk_range_namespace
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = p.pk_property 
                                                       AND aspro.fk_profile = :profile 
                                                       AND aspro.fk_inheriting_range_class IS NULL 
                                                       AND aspro.fk_inheriting_domain_class IS NULL
                WHERE pv.fk_namespace_for_version IN (SELECT pk_namespace FROM che.get_all_references_namespaces_for_profile(:profile))
                AND r.pk_class = :class
                ";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId(), 'profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @param Profile $profile
     * @return array
     */
    public function findOutgoingInheritedPropertiesByClassAndProfileId(OntoClass $class, Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $in = '4';

        $namespacesId = array();
        foreach($profile->getNamespaces() as $namespace){
            $namespacesId[] = $namespace->getId();
            foreach($namespace->getAllReferencedNamespaces() as $referencedNamespace){
                if(!in_array($referencedNamespace->getId(), $namespacesId)){
                    $namespacesId[] = $referencedNamespace->getId();
                }
            }
        }
        foreach($namespacesId as $prfNs){
            $in.=','.$prfNs;
        }

        $sql = "SELECT DISTINCT
                coip.present_class_identifier,
                coip.present_class_label,
                    coip.domain_instances_min_quantifier AS domain_min_quantifier,
                    coip.domain_instances_max_quantifier AS domain_max_quantifier,
                    coip.pk_property AS property_id,
                    coip.identifier_in_namespace AS property_identifier,
                    coip.standard_label AS property_label,
                    coip.fk_namespace_for_version AS property_namespace_id,
                    (SELECT label FROM che.get_namespace_labels(coip.fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS namespace,
                    coip.range_instances_min_quantifier AS range_min_quantifier,
                    coip.range_instances_max_quantifier AS range_max_quantifier,
                    che.get_root_namespace_prefix(che.get_root_namespace(coip.fk_range_namespace_for_version)) AS range_root_namespace_prefix,
                    coip.pk_range_class AS range_id,
                    coip.fk_range_namespace_for_version AS range_namespace_id,
                    coip.range_identifier_in_namespace AS range_identifier,
                    coip.range_standard_label AS range_label,
                    CASE
                        WHEN aspro.fk_system_type IS NULL THEN 999
                        ELSE aspro.fk_system_type
                    END AS fk_system_type
                FROM che.class_outgoing_inherited_properties(:class, ARRAY[".$in."]::integer[]) coip
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = coip.pk_property AND aspro.fk_inheriting_domain_class = :class AND aspro.fk_inheriting_range_class = coip.pk_range_class AND aspro.fk_profile = :profile
                
                UNION DISTINCT
                
                SELECT DISTINCT
                d.identifier_in_namespace AS present_class_identifier,
                dv.standard_label AS present_class_label,
                    pv.domain_instances_min_quantifier AS domain_min_quantifier,
                    pv.domain_instances_max_quantifier AS domain_max_quantifier,
                    p.pk_property AS property_id,
                    p.identifier_in_namespace AS property_identifier,
                    pv.standard_label AS property_label,
                    pv.fk_namespace_for_version AS property_namespace_id,
                    (SELECT label FROM che.get_namespace_labels(pv.fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS namespace,
                    pv.range_instances_min_quantifier AS range_min_quantifier,
                    pv.range_instances_max_quantifier AS range_max_quantifier,
                    che.get_root_namespace_prefix(che.get_root_namespace(rv.fk_namespace_for_version)) AS range_root_namespace_prefix,
                    r.pk_class AS range_id,
                    rv.fk_namespace_for_version AS range_namespace_id,
                    r.identifier_in_namespace AS range_identifier,
                    rv.standard_label AS range_label,
                    CASE
                        WHEN aspro.fk_system_type IS NULL THEN 999
                        ELSE aspro.fk_system_type
                    END AS fk_system_type
                FROM che.associates_profile aspro
                JOIN che.property p ON aspro.fk_property = p.pk_property
                JOIN che.property_version pv ON p.pk_property = pv.fk_property AND pv.fk_namespace_for_version IN (SELECT pk_namespace FROM che.get_all_references_namespaces_for_profile(:profile))
                --JOIN che.associates_referenced_namespace asrefns ON asrefns.fk_referenced_namespace = pv.fk_namespace_for_version AND asrefns.fk_profile = :profile
                JOIN che.class d ON d.pk_class = aspro.fk_inheriting_domain_class
                JOIN che.class_version dv ON dv.fk_class = aspro.fk_inheriting_domain_class
                                          AND dv.fk_namespace_for_version = aspro.fk_domain_namespace
                JOIN che.class r ON r.pk_class = aspro.fk_inheriting_range_class
                JOIN che.class_version rv ON rv.fk_class = aspro.fk_inheriting_range_class
                                          AND rv.fk_namespace_for_version = aspro.fk_range_namespace
                WHERE aspro.fk_profile = :profile AND aspro.fk_system_type = 5 AND aspro.fk_inheriting_domain_class = :class;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId(), 'profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findIncomingInheritedPropertiesByClassAndProfileId(OntoClass $class, Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $in = '4';
        $namespacesId = array();
        foreach($profile->getNamespaces() as $namespace){
            $namespacesId[] = $namespace->getId();
            foreach($namespace->getAllReferencedNamespaces() as $referencedNamespace){
                if(!in_array($referencedNamespace->getId(), $namespacesId)){
                    $namespacesId[] = $referencedNamespace->getId();
                }
            }
        }
        $in.=','.implode(",", $namespacesId);

        $sql = "SELECT DISTINCT
                ciip.present_class_identifier,
                ciip.present_class_label,
                che.get_root_namespace_prefix(che.get_root_namespace(ciip.fk_domain_namespace_for_version)) AS domain_root_namespace_prefix,
                ciip.pk_domain_class AS domain_id,
                ciip.domain_identifier_in_namespace AS domain_identifier,
                ciip.domain_standard_label AS domain_label,
                    ciip.domain_instances_min_quantifier AS domain_min_quantifier,
                    ciip.domain_instances_max_quantifier AS domain_max_quantifier,
                    ciip.pk_property AS property_id,
                    ciip.identifier_in_namespace AS property_identifier,
                    ciip.standard_label AS property_label,
                    ciip.fk_namespace_for_version AS property_namespace_id,
                    (SELECT label FROM che.get_namespace_labels(ciip.fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS namespace,
                    ciip.range_instances_min_quantifier AS range_min_quantifier,
                    ciip.range_instances_max_quantifier AS range_max_quantifier,
                    aspro.fk_inheriting_range_class AS range_id,
                    aspro.fk_range_namespace AS range_namespace_id,
                    ciip.present_class_identifier AS range_identifier,
                    ciip.present_class_label AS range_label,
                    CASE
                        WHEN aspro.fk_system_type IS NULL THEN 999
                        ELSE aspro.fk_system_type
                    END AS fk_system_type
                FROM che.class_ingoing_inherited_properties(:class, ARRAY[".$in."]::integer[]) ciip
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = ciip.pk_property AND aspro.fk_inheriting_range_class = :class AND aspro.fk_inheriting_domain_class = ciip.pk_domain_class AND aspro.fk_profile = :profile 
                
                UNION
                
                SELECT DISTINCT
                r.identifier_in_namespace AS present_class_identifier,
                rv.standard_label AS present_class_label,
                che.get_root_namespace_prefix(che.get_root_namespace(dv.fk_namespace_for_version)) AS domain_root_namespace_prefix,
                d.pk_class AS domain_id,
                d.identifier_in_namespace AS domain_identifier,
                dv.standard_label AS domain_label,
                    pv.domain_instances_min_quantifier AS domain_min_quantifier,
                    pv.domain_instances_max_quantifier AS domain_max_quantifier,
                    p.pk_property AS property_id,
                    p.identifier_in_namespace AS property_identifier,
                    pv.standard_label AS property_label,
                    pv.fk_namespace_for_version AS property_namespace_id,
                    (SELECT label FROM che.get_namespace_labels(pv.fk_namespace_for_version) WHERE language_iso_code = 'en' ORDER BY is_standard_label_for_language DESC LIMIT 1 OFFSET 0) AS namespace,
                    pv.range_instances_min_quantifier AS range_min_quantifier,
                    pv.range_instances_max_quantifier AS range_max_quantifier,
                    r.pk_class AS range_id,
                    rv.fk_namespace_for_version AS range_namespace_id,
                    r.identifier_in_namespace AS range_identifier,
                    rv.standard_label AS range_label,
                    CASE
                        WHEN aspro.fk_system_type IS NULL THEN 999
                        ELSE aspro.fk_system_type
                    END AS fk_system_type
                FROM che.associates_profile aspro
                JOIN che.property p ON aspro.fk_property = p.pk_property
                JOIN che.property_version pv ON p.pk_property = pv.fk_property AND pv.fk_namespace_for_version IN (SELECT pk_namespace FROM che.get_all_references_namespaces_for_profile(:profile))
                --JOIN che.associates_referenced_namespace asrefns ON asrefns.fk_referenced_namespace = pv.fk_namespace_for_version AND asrefns.fk_profile = :profile
                JOIN che.class d ON d.pk_class = aspro.fk_inheriting_domain_class
                JOIN che.class_version dv ON dv.fk_class = aspro.fk_inheriting_domain_class
                                        AND dv.fk_namespace_for_version = aspro.fk_domain_namespace
                JOIN che.class r ON r.pk_class = aspro.fk_inheriting_range_class
                JOIN che.class_version rv ON rv.fk_class = aspro.fk_inheriting_range_class
                                        AND rv.fk_namespace_for_version = aspro.fk_range_namespace
                WHERE aspro.fk_profile = :profile AND aspro.fk_system_type = 5 AND aspro.fk_inheriting_range_class = :class;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId(), 'profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param PropertyVersion $propertyVersion
     * @param array $namespacesId
     * @return array
     * Remplace les fonctions obsolètes findRelationsById et findFilteredRelationsById
     */
    public function findRelationsByPropertyVersionAndNamespacesId(PropertyVersion $propertyVersion, array $namespacesId){
        return array();
    }

    /**
     * @param $lang string the language iso code
     * @param $availableInProfile
     * @param $selectedByProject
     * @return array
     */
    public function findPropertiesWithProfileApi($lang, $availableInProfile, $selectedByProject)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(result)) AS json FROM (SELECT * FROM api.get_properties_list(:lang, :availableInProfile, :selectedByProject) ) result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(
            'lang' => $lang,
            'availableInProfile' => $availableInProfile,
            'selectedByProject' => $selectedByProject
        ));

        return $stmt->fetchAll();
    }

}