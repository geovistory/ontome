<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 23/06/2017
 * Time: 14:57
 */

namespace AppBundle\Repository;


use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\User;
use AppBundle\Entity\UserProjectAssociation;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\QueryBuilder;

class NamespaceRepository extends EntityRepository
{

    /**
     * @return array
     */
    public function findAllOrderedById(){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "WITH tw1 AS
                (
                  SELECT nsp.pk_namespace,
                     nsp.namespace_uri,
                     lbl.label,
                     nsp.modification_time
                  FROM che.namespace nsp
                LEFT JOIN che.label lbl ON nsp.pk_namespace = lbl.fk_namespace
                )
                SELECT pk_namespace AS id,
                   namespace_uri AS \"namespaceUri\",
                   STRING_AGG(label,'; ') labels,
                   modification_time AS \"modificationTime\"
                FROM tw1
                GROUP BY pk_namespace,
                     namespace_uri,
                     modification_time";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param Profile $profile the profile to be checked for association
     * @return array
     */
    public function findAllNonAssociatedToProfileByProfileId(Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT pk_namespace AS id, 
                       standard_label AS \"standardLabel\" 
                FROM che.namespace
                WHERE pk_namespace NOT IN (
                    SELECT nsp.fk_top_level_namespace FROM che.associates_referenced_namespace ansp
                    JOIN che.namespace nsp ON ansp.fk_referenced_namespace = nsp.pk_namespace
                    WHERE ansp.fk_profile = :profile AND ansp.fk_referenced_namespace IS NOT NULL
                    )
                AND is_top_level_namespace;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoNamespace $namespace the namespace to be checked for referenced namespace association
     * @return array
     */
    public function findAllNonAssociatedToNamespaceByNamespaceId(OntoNamespace $namespace){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT pk_namespace AS id, 
                       standard_label AS \"standardLabel\" 
                FROM che.namespace
                WHERE pk_namespace NOT IN (
                    SELECT nsp.fk_top_level_namespace FROM che.associates_referenced_namespace ansp
                    JOIN che.namespace nsp ON ansp.fk_referenced_namespace = nsp.pk_namespace
                    WHERE ansp.fk_namespace = :namespace AND ansp.fk_referenced_namespace IS NOT NULL
                    )
                AND is_top_level_namespace;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('namespace' => $namespace->getId()));

        return $stmt->fetchAll();
    }

    public function findAllowedOngoingNamespaceByUser($user)
    {
        return $this->createQueryBuilder('nsp')
            ->andWhere('nsp.isOngoing = :isOngoing')
            ->setParameter('isOngoing', true)
            ->join('nsp.projectForTopLevelNamespace','proj')
            ->addSelect('proj')
            ->join('proj.userProjectAssociations', 'upa')
            ->addSelect('upa')
            ->join('upa.user', 'user')
            ->addSelect('user')
            ->andWhere('user.id = :user')
            ->setParameter('user', $user)
            ->orderBy('nsp.id','DESC');
            //->getQuery()
            //->execute();
    }

    // Pour le choix par défaut dans My current namepaces
    // // 1/ En 1er choix : le namespace ongoing du projet
    // // 2/ S'il n'existe pas, en 2nd choix : le namespace publié le plus récent
    // // 3/ S'il ne peut être déterminé : le premier namespace trouvé
    /**
     * @return OntoNamespace
     */
    public function findDefaultNamespaceForProject(Project $project)
    {
         $defaultNamespace = $this->createQueryBuilder('nsp')
            ->join('nsp.projectForTopLevelNamespace','proj')
            ->andWhere('proj.id = :pk_project')
            ->andWhere('nsp.isTopLevelNamespace = false')
            ->orderBy('nsp.isOngoing', 'DESC')
            ->addOrderBy('nsp.creationTime', 'DESC')
            ->setParameter('pk_project', $project->getId())
            ->getQuery()
            ->execute();

         if(isset($defaultNamespace[0]))
            return $defaultNamespace[0];
         else
             return null;
    }

    /**
     * @param User $user
     * @return OntoNamespace[]
     */
    public function findAdditionalNamespacesForUser(User $user)
    {
        $additionalNamespaces = $this->createQueryBuilder('nsp')
            ->join('nsp.namespaceUserProjectAssociation', 'nupa')
            ->join('nupa.userProjectAssociation', 'upa')
            ->join('nupa.systemType', 'st')
            ->andWhere('upa.user = :user')
            ->andWhere('upa.project = :project')
            ->andWhere('st.id = 25')
            ->setParameter('user', $user)
            ->setParameter('project', $user->getCurrentActiveProject())
            ->getQuery()
            ->execute();

        return $additionalNamespaces;
    }

    /**
     * @param User $user
     * @return OntoNamespace[]
     */
    public function findAllActiveNamespacesForUser(User $user)
    {
        // Cas Projet public
        if($user->getCurrentActiveProject()->getId() == 21){
            $publicProjectNamespaces = $this->createQueryBuilder('nsp')
                ->join('nsp.projectAssociations', 'npa')
                ->andWhere('npa.project = :project')
                ->andWhere('npa.systemType = 17')
                ->setParameter('project', $user->getCurrentActiveProject())
                ->getQuery()
                ->execute();

            $publicProjectReferendedNamespaces = $this->createQueryBuilder('nsp')
                ->join('nsp.referencedVersion', 'nrv')
                ->andWhere('nrv in (:publicProjectNamespaces)')
                ->setParameter('publicProjectNamespaces', $publicProjectNamespaces)
                ->getQuery()
                ->execute();

            return array_merge($publicProjectNamespaces, $publicProjectReferendedNamespaces);
        }
        else{ // Autre cas
            $rsm = new ResultSetMappingBuilder($this->getEntityManager());
            $rsm->addRootEntityFromClassMetadata('AppBundle\Entity\OntoNamespace', 'ns');

            $sql = "  SELECT ns.* FROM che.namespace ns
                      LEFT JOIN che.associates_entity_to_user_project aseup ON aseup.fk_namespace = ns.pk_namespace 
                      WHERE aseup.fk_system_type = 25
                      AND aseup.fk_associate_user_to_project = (SELECT pk_associate_user_to_project 
                                                                FROM che.associate_user_to_project 
                                                                WHERE fk_user = :id_user AND fk_project = :id_project)
                      UNION
                      SELECT ns2.* FROM che.namespace ns2
                      WHERE ns2.pk_namespace IN (SELECT fk_referenced_namespace FROM che.associates_referenced_namespace WHERE fk_namespace IN(
                        SELECT ns3.pk_namespace FROM che.namespace ns3
                        LEFT JOIN che.associates_entity_to_user_project aseup2 ON aseup2.fk_namespace = ns3.pk_namespace 
                        WHERE aseup2.fk_system_type = 25
                        AND aseup2.fk_associate_user_to_project = (SELECT pk_associate_user_to_project 
                                                                  FROM che.associate_user_to_project 
                                                                  WHERE fk_user = :id_user AND fk_project = :id_project)
                      ))
                      ";

            $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
            $query->setParameter('id_user', $user->getId());
            $query->setParameter('id_project', $user->getCurrentActiveProject()->getId());
            return $query->getResult();
        }
    }

    /**
     * @return array
     */
    public function findNamespacesGraph(OntoNamespace $namespace){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT get_webvowl_json_for_namespace AS json FROM che.get_webvowl_json_for_namespace(:namespace)";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('namespace' => $namespace->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @return array
     */
    public function findNamespacesInPublicProject(){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT  nsp.pk_namespace AS id,
                        nsp.standard_label AS \"standardLabel\",
                        aspro.pk_associates_project AS \"associationId\"
                FROM che.namespace nsp
                JOIN che.associates_project aspro ON nsp.pk_namespace = aspro.fk_namespace
                WHERE aspro.fk_project = 21 AND aspro.fk_system_type = 17;";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @return array
     */
    public function findActiveNamespacesInPublicProject(){
        $publicProjectNamespaces = $this->createQueryBuilder('nsp')
            ->join('nsp.projectAssociations', 'npa')
            ->join('npa.project', 'p')
            ->andWhere('p.id = 21')
            ->andWhere('npa.systemType = 17')
            ->getQuery()
            ->execute();

        return $publicProjectNamespaces;
    }

    /**
     * @return QueryBuilder to create the query for the list of namespaces available for association with public project
     */
    public function findAvailableNamespacesForPublicProjectAssociation()
    {
        $em = $this->getEntityManager();
        //$subQueryBuilder = $em->getRepository('AppBundle:ProjectAssociation')->createQueryBuilder('exnsp');
        $subQueryBuilder = $this->getEntityManager()->createQueryBuilder();
        $subQuery = $subQueryBuilder
            ->select(['nspt.id'])
            ->from('AppBundle:ProjectAssociation', 'exnsp')
            ->join('exnsp.namespace','nspt')
            ->where('exnsp.systemType = 17')
            ->andWhere('exnsp.project = 21')
            ->getQuery()
            ->getArrayResult()
        ;

        $queryBuilder = $em->getRepository('AppBundle:OntoNamespace')->createQueryBuilder('nsp');

        return $queryBuilder->select('nsp')
            ->where(
                $queryBuilder->expr()->notIn(
                    'nsp.id',
                    ':subQuery'
                )
            )
            ->setParameter('subQuery', $subQuery);

    }

    /**
     * @param $lang string the language iso code
     * @param $namespace int the ID of the namespace
     * @return array
     */
    public function findClassesAndPropertiesByNamespaceIdApi($lang, $namespace)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT result::text FROM api.get_classes_and_properties_for_namespace(:lang, :namespace) as result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(
            'lang' => $lang,
            'namespace' => $namespace
        ));

        return $stmt->fetchAll();
    }

}