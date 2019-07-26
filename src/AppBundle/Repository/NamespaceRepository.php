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
         $namespaceOngoing = $this->createQueryBuilder('nsp')
            ->andWhere('nsp.isOngoing = true')
            ->andWhere('nsp.isTopLevelNamespace = false')
            ->join('nsp.projectForTopLevelNamespace','proj')
            ->andWhere('proj.id = :pk_project')
            ->setParameter('pk_project', $project->getId())
            ->getQuery()
            ->execute();

        if(!empty($namespaceOngoing))
            return $namespaceOngoing[0];

        $latestNamespace = $this->createQueryBuilder('nsp')
            ->andWhere('nsp.isTopLevelNamespace = false')
            ->join('nsp.projectForTopLevelNamespace','proj')
            ->andWhere('proj.id = :pk_project')
            ->setParameter('pk_project', $project->getId())
            ->orderBy('nsp.creationTime', 'DESC')
            ->getQuery()
            ->execute()[0];

        return $latestNamespace;
    }

    /**
     * @return OntoNamespace[]
     */
    public function findAdditionalNamespacesForUserProject(UserProjectAssociation $userProjectAssociation)
    {
        $additionalNamespaces = $this->createQueryBuilder('nsp')
            ->join('nsp.namespaceUserProjectAssociation', 'nupa')
            ->join('nupa.userProjectAssociation', 'upa')
            ->join('nupa.systemType', 'st')
            ->andWhere('upa.id = :pk_user_project_association')
            ->andWhere('st.id = 25')
            ->setParameter('pk_user_project_association', $userProjectAssociation->getId())
            ->getQuery()
            ->execute();

        return $additionalNamespaces;
    }

    /**
     * @return OntoNamespace[]
     */
    public function findAllActiveNamespacesForUserProject(UserProjectAssociation $userProjectAssociation)
    {
        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata('AppBundle\Entity\OntoNamespace', 'ns');

        $sql = "
          SELECT ns.* FROM che.namespace ns
          LEFT JOIN che.associates_entity_to_user_project aseup ON aseup.fk_namespace = ns.pk_namespace 
          WHERE ns.pk_namespace IN(
	        -- Retourne l'espace de nom géré par le projet
	        (SELECT pk_namespace FROM che.namespace
	        WHERE fk_project_for_top_level_namespace = 6
	        ORDER BY is_ongoing DESC, creation_time DESC
	        LIMIT 1 OFFSET 0)
	        UNION
	        -- Retourne les espaces de nom utilisés par les profils actifs du projet
	        (SELECT DISTINCT fk_referenced_namespace FROM che.associates_referenced_namespace asrefns
	        LEFT JOIN che.associates_entity_to_user_project aseup2 ON aseup2.fk_profile = asrefns.fk_profile 
	        WHERE asrefns.fk_profile IN(
	          SELECT fk_profile FROM che.associates_project
		      WHERE fk_project = 6)
	          AND ((aseup2.fk_system_type = 25 AND aseup2.fk_associate_user_to_project IN(
	            SELECT pk_associate_user_to_project FROM che.associate_user_to_project
		        WHERE fk_user = 7 AND fk_project = 6)) 
	          OR aseup2.fk_system_type IS NULL)
	        )
          )
          AND ((aseup.fk_system_type = 25 AND aseup.fk_associate_user_to_project IN(
            SELECT pk_associate_user_to_project FROM che.associate_user_to_project
	        WHERE fk_user = 7 AND fk_project = 6)) 
	      OR aseup.fk_system_type IS NULL)
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        return $query->getResult();
    }

    // Retrouve tous les espaces de noms inactifs (système type 26)
    /**
     * @return OntoNamespace[]
     */
    public function findAllInactiveNamespacesForUserProject(UserProjectAssociation $userProjectAssociation)
    {
        $inactiveNamespaces = $this->createQueryBuilder('nsp')
            ->join('nsp.namespaceUserProjectAssociation', 'nupa')
            ->join('nupa.userProjectAssociation', 'upa')
            ->join('nupa.systemType', 'st')
            ->andWhere('upa.id = :pk_user_project_association')
            ->andWhere('st.id = 26')
            ->setParameter('pk_user_project_association', $userProjectAssociation->getId())
            ->getQuery()
            ->execute();

        return $inactiveNamespaces;
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

}