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
    public function findPropertiesByNamespacesId(array $namespacesId){
        $qb = $this->createQueryBuilder('property')
            ->join('property.propertyVersions','pv')
            ->join('pv.namespaceForVersion','nfv')
            ->where('nfv.id IN (:namespacesId)')
            ->setParameter('namespacesId', $namespacesId);

        $properties = $qb->getQuery()->execute();

        return $properties;
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
                  pk_property AS \"propertyId\",
                  pk_range AS \"rangeId\",
                  identifier_domain AS domain,
                  che.get_root_namespace(fk_property_namespace_for_version) AS \"rootNamespaceId\",
                  (SELECT label FROM che.get_namespace_labels(fk_property_namespace_for_version) WHERE language_iso_code = 'en') AS namespace
                FROM che.v_properties_version_with_domain_version_range_version
                WHERE pk_domain_version = ?
                AND fk_property_namespace_for_version IN (".$in.");";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getId()), $namespacesId));

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
                  pk_range AS \"rangeId\",
                  identifier_range AS range,
                  replace(ancestors, '|', '→') AS ancestors,
                  (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace
                FROM che.class_outgoing_inherited_properties(?),
                  che.associates_namespace asnsp,
                  che.namespace nsp
                WHERE asnsp.fk_property = pk_property
                AND nsp.pk_namespace = asnsp.fk_namespace
                AND nsp.pk_namespace IN (".$in.");";

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
                  pk_range AS \"rangeId\",
                  identifier_range AS range,
                  che.get_root_namespace(fk_property_namespace_for_version) AS \"rootNamespaceId\",
                  (SELECT label FROM che.get_namespace_labels(fk_property_namespace_for_version) WHERE language_iso_code = 'en') AS namespace
                FROM che.v_properties_version_with_domain_version_range_version
                WHERE pk_range_version = ?
                AND fk_property_namespace_for_version IN (".$in.");";

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
                  pk_parent AS \"rangeId\",
                  parent_identifier AS range,
                  replace(ancestors, '|', '→') AS ancestors,
                  (SELECT label FROM che.get_namespace_labels(nsp.pk_namespace) WHERE language_iso_code = 'en') AS namespace
                FROM che.class_ingoing_inherited_properties(?),
                  che.associates_namespace asnsp,
                  che.namespace nsp 
                WHERE asnsp.fk_property = pk_property
                AND nsp.pk_namespace = asnsp.fk_namespace
                AND nsp.pk_namespace IN (".$in.");";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($classVersion->getClass()->getId()), $namespacesId));

        return $stmt->fetchAll();
    }

    /**
     * @param Property $property
     * @return array
     */
    public function findDomainRangeById(Property $property)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT  pk_domain AS \"domainId\",
                        identifier_domain AS \"domainIdentifier\",
                        identifier_property AS \"propertyIdentifier\",
                        pk_range AS \"rangeId\",
                        identifier_range AS \"rangeIdentifier\"
                FROM che.v_properties_with_domain_range
                WHERE pk_property = :property";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('property' => $property->getId()));

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
            ->join('property.namespaces','nspc')
            ->join('property.profiles', 'profile')
            ->where('profile.id = :profile')
            ->addSelect('profile')
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
                JOIN che.associates_namespace asnsp ON asnsp.fk_property = pk_property
                JOIN che.namespace nsp ON nsp.pk_namespace = asnsp.fk_namespace 
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
                JOIN che.associates_namespace asnsp ON asnsp.fk_property = pk_property
                JOIN che.namespace nsp ON nsp.pk_namespace = asnsp.fk_namespace 
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
                FROM che.class_outgoing_inherited_properties(:class)
                JOIN che.associates_namespace asnsp ON asnsp.fk_property = pk_property
                JOIN che.namespace nsp ON nsp.pk_namespace = asnsp.fk_namespace
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = pk_property AND aspro.fk_inheriting_domain_class = :class AND aspro.fk_inheriting_range_class = pk_range AND aspro.fk_profile = :profile
                
                UNION DISTINCT
                
                SELECT clsdmn.identifier_in_namespace || ' ' || clsdmn.standard_label AS domain,
                       aspro.fk_property AS \"propertyId\",
                       prop.identifier_in_namespace  || ' ' ||  prop.standard_label,
                       aspro.fk_inheriting_range_class AS \"rangeId\",
                       clsrng.identifier_in_namespace || ' ' || clsrng.standard_label AS range,
                       CASE
                           WHEN aspro.fk_system_type IS NULL THEN 999
                           ELSE aspro.fk_system_type
                           END AS fk_system_type
                FROM che.associates_profile aspro
                JOIN che.property prop ON aspro.fk_property = prop.pk_property
                JOIN che.class clsdmn ON aspro.fk_inheriting_domain_class = clsdmn.pk_class
                JOIN che.class clsrng ON aspro.fk_inheriting_range_class = clsrng.pk_class
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
                        cls.identifier_in_namespace || ' ' || cls.standard_label AS range,
                        CASE
                            WHEN aspro.fk_system_type IS NULL THEN 999
                            ELSE aspro.fk_system_type
                            END AS fk_system_type
                FROM che.class_ingoing_inherited_properties(:class)
                JOIN che.associates_namespace asnsp ON asnsp.fk_property = pk_property
                JOIN che.namespace nsp ON nsp.pk_namespace = asnsp.fk_namespace
                JOIN che.class cls ON cls.pk_class = :class
                LEFT JOIN che.associates_profile aspro ON aspro.fk_property = pk_property AND aspro.fk_inheriting_range_class = :class AND aspro.fk_inheriting_domain_class = pk_domain AND aspro.fk_profile = :profile
                
                UNION DISTINCT
                
                SELECT clsdmn.pk_class AS \"domainId\",
                       clsdmn.identifier_in_namespace || ' ' || clsdmn.standard_label AS domain,
                       prop.identifier_in_namespace  || ' ' ||  prop.standard_label AS property,
                       aspro.fk_property AS \"propertyId\",
                       aspro.fk_inheriting_range_class AS \"rangeId\",
                       clsrng.identifier_in_namespace || ' ' || clsrng.standard_label AS range,
                       CASE
                           WHEN aspro.fk_system_type IS NULL THEN 999
                           ELSE aspro.fk_system_type
                           END AS fk_system_type
                FROM che.associates_profile aspro
                         JOIN che.property prop ON aspro.fk_property = prop.pk_property
                         JOIN che.class clsdmn ON aspro.fk_inheriting_domain_class = clsdmn.pk_class
                         JOIN che.class clsrng ON aspro.fk_inheriting_range_class = clsrng.pk_class
                WHERE aspro.fk_profile = :profile AND aspro.fk_system_type = 5 AND aspro.fk_inheriting_range_class = :class;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId(), 'profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Property $property
     * @return array
     */
    public function findRelationsById(Property $property)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT
                ea.pk_entity_association,
                ea.fk_target_property AS fk_related_property,
                p.identifier_in_namespace,
                p.standard_label,
                st.standard_label AS relation,
                txtp.pk_text_property,
                ns.pk_namespace AS \"rootNamespaceId\",
                ns.standard_label AS \"standardLabelNamespace\"
                FROM
                che.entity_association AS ea
                LEFT JOIN che.system_type AS st
                ON st.pk_system_type = ea.fk_system_type
                LEFT JOIN che.property AS p
                ON ea.fk_target_property = p.pk_property
                LEFT JOIN (SELECT * FROM che.text_property WHERE fk_text_property_type = 15) AS txtp
                ON txtp.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.associates_namespace AS ans
                ON ans.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.namespace AS ns
                ON ns.pk_namespace= che.get_root_namespace(ans.fk_namespace)
                WHERE
                ea.fk_system_type IN (18, 20)
                AND p.pk_property IS NOT NULL
                AND ea.fk_source_property = :property
                UNION
                SELECT
                ea.pk_entity_association,
                ea.fk_source_property AS fk_related_property,
                p.identifier_in_namespace,
                p.standard_label,
                st.standard_label AS relation,
                txtp.pk_text_property,
                ns.pk_namespace AS \"rootNamespaceId\",
                ns.standard_label AS \"standardLabelNamespace\"
                FROM
                che.entity_association AS ea
                LEFT JOIN che.system_type AS st
                ON st.pk_system_type = ea.fk_system_type
                LEFT JOIN che.property AS p
                ON ea.fk_source_property = p.pk_property
                LEFT JOIN (SELECT * FROM che.text_property WHERE fk_text_property_type = 15) AS txtp
                ON txtp.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.associates_namespace AS ans
                ON ans.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.namespace AS ns
                ON ns.pk_namespace= che.get_root_namespace(ans.fk_namespace)
                WHERE
                ea.fk_system_type IN (18, 20)
                AND p.pk_property IS NOT NULL
                AND ea.fk_target_property = :property";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('property' => $property->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Property $property
     * @return array
     */
    public function findFilteredRelationsById(Property $property, User $user)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        // Trouver d'abord les espaces de noms filtrés - juste les clés pour le IN de la requête ci-dessous
        $em = $this->getEntityManager();
        $filteredNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findAllActiveNamespacesForUser($user);

        $propertyNamespace = $property->getOngoingNamespace();
        if(!in_array($propertyNamespace, $filteredNamespaces)){
            $filteredNamespaces[] = $propertyNamespace;
        }

        $idsFilteredNamespaces = array();
        $qFilteredNamespaces = array();
        foreach ($filteredNamespaces as $namespace)
        {
            $idsFilteredNamespaces[] = $namespace->getId();
        }

        // Construire la variable qui permet d'avoir ?,?,?... pour la requête ci-dessous
        for($i=0;$i<count($idsFilteredNamespaces);$i++){
            $qFilteredNamespaces[] = "?";
        }
        $strQFilteredNamespaces = join(',',$qFilteredNamespaces);

        $sql = "SELECT
                ea.pk_entity_association,
                ea.fk_target_property AS fk_related_property,
                p.identifier_in_namespace,
                p.standard_label,
                st.standard_label AS relation,
                txtp.pk_text_property,
                ns.pk_namespace AS \"rootNamespaceId\",
                ns.standard_label AS \"standardLabelNamespace\"
                FROM
                che.entity_association AS ea
                LEFT JOIN che.system_type AS st
                ON st.pk_system_type = ea.fk_system_type
                LEFT JOIN che.property AS p
                ON ea.fk_target_property = p.pk_property
                LEFT JOIN (SELECT * FROM che.text_property WHERE fk_text_property_type = 15) AS txtp
                ON txtp.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.associates_namespace AS ans
                ON ans.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.namespace AS ns
                ON ns.pk_namespace= che.get_root_namespace(ans.fk_namespace)
                WHERE
                ea.fk_system_type IN (18, 20)
                AND p.pk_property IS NOT NULL
                AND ea.fk_source_property = ?
                AND ns.pk_namespace IN (".$strQFilteredNamespaces.")
                UNION
                SELECT
                ea.pk_entity_association,
                ea.fk_source_property AS fk_related_property,
                p.identifier_in_namespace,
                p.standard_label,
                st.standard_label AS relation,
                txtp.pk_text_property,
                ns.pk_namespace AS \"rootNamespaceId\",
                ns.standard_label AS \"standardLabelNamespace\"
                FROM
                che.entity_association AS ea
                LEFT JOIN che.system_type AS st
                ON st.pk_system_type = ea.fk_system_type
                LEFT JOIN che.property AS p
                ON ea.fk_source_property = p.pk_property
                LEFT JOIN (SELECT * FROM che.text_property WHERE fk_text_property_type = 15) AS txtp
                ON txtp.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.associates_namespace AS ans
                ON ans.fk_entity_association = ea.pk_entity_association
                LEFT JOIN che.namespace AS ns
                ON ns.pk_namespace= che.get_root_namespace(ans.fk_namespace)
                WHERE
                ea.fk_system_type IN (18, 20)
                AND p.pk_property IS NOT NULL
                AND ea.fk_target_property = ?
                AND ns.pk_namespace IN (".$strQFilteredNamespaces.");";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge(array($property->getId()) , $idsFilteredNamespaces, array($property->getId()), $idsFilteredNamespaces));

        return $stmt->fetchAll();
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