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

        $sql = "WITH t_ascendants_properties AS
                (
                SELECT pk_parent,
                     parent_identifier,
                     DEPTH,
                     ARRAY_TO_STRING(_path,'|') ancestors,
                        pk_is_subproperty_of,
                        fk_namespace_for_version
                  FROM che.ascendant_property_hierarchy(?, ARRAY[".$in."]::integer[])
                )
                SELECT t_ascendants_properties.pk_parent  AS id,
                       t_ascendants_properties.parent_identifier AS identifier,
                       pv.has_domain,
                       domain.identifier_in_namespace AS \"domainIdentifier\",
                       domain_version.standard_label AS \"domainStandardLabel\",
                       domain_version.fk_namespace_for_version AS \"domainNamespaceId\",
                       pv.domain_instances_min_quantifier,
                       pv.domain_instances_max_quantifier,
                       pv.has_range,
                       range.identifier_in_namespace AS \"rangeIdentifier\",
                       range_version.standard_label AS \"rangeStandardLabel\",
                       range_version.fk_namespace_for_version AS \"rangeNamespaceId\",
                       pv.range_instances_min_quantifier,
                       pv.range_instances_max_quantifier,
                       t_ascendants_properties.DEPTH,
                       replace(t_ascendants_properties.ancestors, '|', '→') AS ancestors,
                       che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                       (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\",
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
    AND pv.fk_namespace_for_version IN (".$in.")
    AND depth > 1
    AND pv.has_domain = domain.pk_class
    AND pv.has_range = range.pk_class
    AND t_ascendants_properties.fk_namespace_for_version = pv.fk_namespace_for_version
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
        $stmt->execute(array_merge(array($propertyVersion->getProperty()->getId()), $namespacesId, $namespacesId));

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
                       pv.domain_instances_min_quantifier,
                       pv.domain_instances_max_quantifier,
                       pv.has_range,
                       range_class.identifier_in_namespace AS \"rangeIdentifier\",
                       range_cv.standard_label AS \"rangeStandardLabel\",
                       range_cv.fk_namespace_for_version AS \"rangeNamespaceId\",
                       pv.range_instances_min_quantifier,
                       pv.range_instances_max_quantifier,
                        t_descendants_properties.depth,
                        replace(t_descendants_properties.descendants, '|', '→') AS descendants,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                       (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\",
                       nsp.pk_namespace AS \"propertyNamespaceId\",
                       nsp.standard_label AS \"propertyNamespaceLabel\"
                    FROM che.descendant_property_hierarchy(?) t_descendants_properties,
                         che.property p JOIN che.property_version pv ON p.pk_property = pv.fk_property,
                         che.namespace nsp,
                         che.class domain_class JOIN che.class_version domain_cv ON domain_class.pk_class = domain_cv.fk_class,
                         che.class range_class JOIN che.class_version range_cv ON range_class.pk_class = range_cv.fk_class
                    WHERE pv.fk_property = pk_child
                    AND   nsp.pk_namespace = pv.fk_namespace_for_version
                    AND pv.has_domain = domain_class.pk_class
                    AND pv.has_range = range_class.pk_class
                    AND pv.fk_namespace_for_version IN (".$in.");";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($propertyVersion->getProperty()->getId()), $namespacesId));

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

        $sql = "SELECT identifier_property AS property,
                  identifier_range AS range,
                  v.fk_range_namespace AS \"rangeNamespaceId\",
                    che.get_root_namespace_prefix(che.get_root_namespace(v.fk_range_namespace)) AS \"rangeRootNamespacePrefix\",
                  pk_property AS \"propertyId\",
                  pk_range AS \"rangeId\",
                  v.fk_namespace_for_version AS \"propertyNamespaceId\",
                  array_append(array_agg(asrefns.fk_referenced_namespace), v.fk_namespace_for_version) AS \"selectedNamespacesId\",
                  identifier_domain AS domain,
                  v.fk_domain_namespace AS \"domainNamespaceId\",
                  che.get_root_namespace(fk_namespace_for_version) AS \"rootNamespaceId\",
                  che.get_root_namespace_prefix(che.get_root_namespace(fk_namespace_for_version)) AS \"propertyRootNamespacePrefix\",
                  (SELECT label FROM che.get_namespace_labels(fk_namespace_for_version) WHERE language_iso_code = 'en') AS namespace,
                  (SELECT label FROM che.get_namespace_labels(v.fk_range_namespace) WHERE language_iso_code = 'en') AS \"rangeNamespace\"
                FROM che.v_properties_with_domain_range v
                LEFT JOIN che.associates_referenced_namespace asrefns ON v.fk_namespace_for_version = asrefns.fk_namespace
                WHERE v.pk_domain = ?
                AND fk_namespace_for_version IN (".$in.")
                GROUP BY v.identifier_property, v.identifier_range, v.fk_range_namespace, v.pk_property, v.pk_range, v.fk_namespace_for_version, v.identifier_domain, v.fk_domain_namespace;";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId));

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

        $sql = "SELECT identifier_in_namespace AS domain,
                  pk_parent AS \"parentClassId\", 
                  parent_identifier AS \"parentClass\",
                  pk_property AS \"propertyId\",
                  identifier_property AS property,
                  v.fk_namespace_for_version AS \"propertyNamespaceId\",
                  array_append(array_agg(asrefns.fk_referenced_namespace), v.fk_namespace_for_version) AS \"selectedNamespacesId\",
                  pk_range AS \"rangeId\",
                  identifier_range AS range,
                  v.fk_range_namespace AS \"rangeNamespaceId\",
                  v.fk_domain_namespace AS \"domainNamespaceId\",
                  replace(ancestors, '|', '→') AS ancestors,
                  (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace
                FROM che.class_outgoing_inherited_properties(?, ARRAY[".$in."]::integer[]) v
                INNER JOIN che.property_version pv ON pv.fk_property = v.pk_property
                INNER JOIN che.namespace nsp ON nsp.pk_namespace = pv.fk_namespace_for_version
                LEFT JOIN che.associates_referenced_namespace asrefns ON v.fk_namespace_for_version = asrefns.fk_namespace
                GROUP BY nsp.pk_namespace, v.ancestors, v.identifier_in_namespace, v.pk_parent, v.parent_identifier, v.pk_property, v.identifier_property, v.fk_namespace_for_version, v.pk_range, v.identifier_range, v.fk_domain_namespace, v.fk_range_namespace;";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId));

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
                  pk_range AS \"rangeId\",
                  identifier_range AS range,
                  v.fk_range_namespace AS \"rangeNamespaceId\",
                  v.fk_domain_namespace AS \"domainNamespaceId\",
                  che.get_root_namespace(fk_namespace_for_version) AS \"rootNamespaceId\",
                  (SELECT label FROM che.get_namespace_labels(fk_namespace_for_version) WHERE language_iso_code = 'en') AS namespace
                FROM che.v_properties_with_domain_range v
                LEFT JOIN che.associates_referenced_namespace asrefns ON v.fk_namespace_for_version = asrefns.fk_namespace
                WHERE pk_range = ?
                AND fk_namespace_for_version IN (".$in.")
                GROUP BY v.pk_domain, v.identifier_property, v.identifier_range, v.fk_range_namespace, v.pk_property, v.pk_range, v.fk_namespace_for_version, v.identifier_domain, v.fk_domain_namespace;";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId));

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

        $sql = "SELECT pk_domain AS \"domainId\",
                  identifier_domain AS domain,
                  identifier_property AS property,
                  pk_property AS \"propertyId\",
                  v.fk_namespace_for_version AS \"propertyNamespaceId\",
                  pk_parent AS \"rangeId\",
                  parent_identifier AS range,
                  v.fk_range_namespace AS \"rangeNamespaceId\",
                  v.fk_domain_namespace AS \"domainNamespaceId\",
                  replace(ancestors, '|', '→') AS ancestors,
                  (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace
                FROM che.class_ingoing_inherited_properties(?, ARRAY[".$in."]::integer[]) v,
                  che.property_version pv,
                  che.namespace nsp 
                WHERE pv.fk_property = pk_property
                AND nsp.pk_namespace = pv.fk_namespace_for_version;";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId));

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

        $sql = "SELECT DISTINCT identifier_property AS property,
                                identifier_range AS range,
                                pk_property AS \"propertyId\",
                                pk_range AS \"rangeId\",
                                identifier_domain AS domain,
                                che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                                (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace,
                                CASE
                                    WHEN aspro.fk_system_type IS NULL THEN 999
                                    ELSE aspro.fk_system_type
                                END AS fk_system_type
                FROM che.v_properties_with_domain_range
                JOIN che.property_version pv ON pv.fk_property = pk_property
                JOIN che.namespace nsp ON nsp.pk_namespace = pv.fk_namespace_for_version
                JOIN che.associates_referenced_namespace asrefns ON asrefns.fk_referenced_namespace = pv.fk_namespace_for_version AND asrefns.fk_profile = :profile                    
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = pk_property AND aspro.fk_profile = :profile AND aspro.fk_inheriting_range_class IS NULL AND aspro.fk_inheriting_domain_class IS NULL
                
                WHERE pk_domain = :class;";

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

        $sql = "SELECT  pk_domain AS \"domainId\",
                        identifier_domain AS domain,
                        identifier_property AS property,
                        pk_property AS \"propertyId\",
                        pk_range AS \"rangeId\",
                        identifier_range AS range,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                        (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace,
                        CASE
                            WHEN aspro.fk_system_type IS NULL THEN 999
                            ELSE aspro.fk_system_type
                        END AS fk_system_type
                FROM  che.v_properties_with_domain_range
                JOIN che.property_version pv ON pv.fk_property = pk_property
                JOIN che.namespace nsp ON nsp.pk_namespace = pv.fk_namespace_for_version 
                JOIN che.associates_referenced_namespace asrefns ON asrefns.fk_referenced_namespace = pv.fk_namespace_for_version AND asrefns.fk_profile = :profile
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = pk_property AND aspro.fk_profile = :profile AND aspro.fk_inheriting_range_class IS NULL AND aspro.fk_inheriting_domain_class IS NULL
                WHERE pk_range = :class;";

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

        $sql = "SELECT DISTINCT identifier_in_namespace AS domain,
                pk_property AS \"propertyId\",
                identifier_property AS property,
                pk_range AS \"rangeId\",
                identifier_range AS range,
                CASE
                    WHEN aspro.fk_system_type IS NULL THEN 999
                    ELSE aspro.fk_system_type
                    END AS fk_system_type
                FROM che.class_outgoing_inherited_properties(:class) coip
                JOIN che.property_version pv ON pv.fk_property = coip.pk_property
                JOIN che.associates_referenced_namespace asrefns ON asrefns.fk_referenced_namespace = pv.fk_namespace_for_version AND asrefns.fk_profile = :profile
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = pk_property AND aspro.fk_inheriting_domain_class = :class AND aspro.fk_inheriting_range_class = pk_range AND aspro.fk_profile = :profile
                
                UNION DISTINCT
                
                SELECT clsdmn.identifier_in_namespace || ' ' || domain_cv.standard_label AS domain,
                       aspro.fk_property AS \"propertyId\",
                       prop.identifier_in_namespace  || ' ' ||  pv.standard_label,
                       aspro.fk_inheriting_range_class AS \"rangeId\",
                       clsrng.identifier_in_namespace || ' ' || range_cv.standard_label AS range,
                       CASE
                           WHEN aspro.fk_system_type IS NULL THEN 999
                           ELSE aspro.fk_system_type
                           END AS fk_system_type
                FROM che.associates_profile aspro
                JOIN che.property prop ON aspro.fk_property = prop.pk_property
                JOIN che.property_version pv ON prop.pk_property = pv.fk_property
                JOIN che.associates_referenced_namespace asrefns ON asrefns.fk_referenced_namespace = pv.fk_namespace_for_version AND asrefns.fk_profile = :profile
                JOIN che.class clsdmn ON aspro.fk_inheriting_domain_class = clsdmn.pk_class
                JOIN che.class_version domain_cv ON clsdmn.pk_class = domain_cv.fk_class 
                JOIN che.class clsrng ON aspro.fk_inheriting_range_class = clsrng.pk_class
                JOIN che.class_version range_cv ON clsrng.pk_class = range_cv.fk_class
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

        $sql = "SELECT  pk_domain AS \"domainId\",
                        identifier_domain AS domain,
                        identifier_property AS property,
                        pk_property AS \"propertyId\",
                        :class AS \"rangeId\",
                        cls.identifier_in_namespace || ' ' || cv.standard_label AS range,
                        CASE
                            WHEN aspro.fk_system_type IS NULL THEN 999
                            ELSE aspro.fk_system_type
                            END AS fk_system_type
                FROM che.class_ingoing_inherited_properties(:class) ciip
                JOIN che.property_version pv ON pv.fk_property = ciip.pk_property
                JOIN che.associates_referenced_namespace asrefns ON asrefns.fk_referenced_namespace = pv.fk_namespace_for_version AND asrefns.fk_profile = :profile
                JOIN che.class cls ON cls.pk_class = :class
                JOIN che.class_version cv ON cls.pk_class = cv.fk_class
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = pk_property AND aspro.fk_inheriting_range_class = :class AND aspro.fk_inheriting_domain_class = pk_domain AND aspro.fk_profile = :profile
                
                UNION DISTINCT
                
                SELECT clsdmn.pk_class AS \"domainId\",
                       clsdmn.identifier_in_namespace || ' ' || domain_cv.standard_label AS domain,
                       prop.identifier_in_namespace  || ' ' ||  pv.standard_label AS property,
                       aspro.fk_property AS \"propertyId\",
                       aspro.fk_inheriting_range_class AS \"rangeId\",
                       clsrng.identifier_in_namespace || ' ' || range_cv.standard_label AS range,
                       CASE
                           WHEN aspro.fk_system_type IS NULL THEN 999
                           ELSE aspro.fk_system_type
                           END AS fk_system_type
                FROM che.associates_profile aspro
                JOIN che.property prop ON aspro.fk_property = prop.pk_property
                JOIN che.property_version pv ON prop.pk_property = pv.fk_property   
                JOIN che.associates_referenced_namespace asrefns ON asrefns.fk_referenced_namespace = pv.fk_namespace_for_version AND asrefns.fk_profile = :profile
                JOIN che.class clsdmn ON aspro.fk_inheriting_domain_class = clsdmn.pk_class
                JOIN che.class_version domain_cv ON clsdmn.pk_class = domain_cv.fk_class
                JOIN che.class clsrng ON aspro.fk_inheriting_range_class = clsrng.pk_class
                JOIN che.class_version range_cv ON clsrng.pk_class = range_cv.fk_class
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