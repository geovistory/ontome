<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 23/06/2017
 * Time: 14:57
 */

namespace AppBundle\Repository;

use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\User;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

class ProfileRepository extends EntityRepository
{
    public function findAllActiveProfilesForUser(User $user)
    {
        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata('AppBundle\Entity\Profile', 'prf');

        $sql = "
          SELECT prf.* FROM che.profile prf
          LEFT JOIN che.associates_entity_to_user_project aseup ON aseup.fk_profile = prf.pk_profile 
          WHERE prf.fk_project_of_belonging = :id_project
          AND ((aseup.fk_system_type = 25 AND aseup.fk_associate_user_to_project IN(
            SELECT pk_associate_user_to_project FROM che.associate_user_to_project
	        WHERE fk_user = :id_user AND fk_project = :id_project)) 
	      OR aseup.fk_system_type IS NULL);
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('id_project', $user->getCurrentActiveProject()->getId());
        $query->setParameter('id_user', $user->getId());
        return $query->getResult();
    }

    public function findAllProfilesForUser(User $user)
    {
        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata('AppBundle\Entity\Profile', 'prf');

        $sql = "
          SELECT prf.* FROM che.profile prf
          LEFT JOIN che.associates_entity_to_user_project aseup ON aseup.fk_profile = prf.pk_profile 
          WHERE prf.fk_project_of_belonging = :id_project
          AND (aseup.fk_associate_user_to_project IN(
            SELECT pk_associate_user_to_project FROM che.associate_user_to_project
	        WHERE fk_user = :id_user AND fk_project = :id_project));
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('id_project', $user->getCurrentActiveProject()->getId());
        $query->setParameter('id_user', $user->getId());
        return $query->getResult();
    }

    /**
     * @return array
     */
    public function findProfileGraph(Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT get_webvowl_json_for_profile AS json FROM api.get_webvowl_json_for_profile(:profile)";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('profile' => $profile->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param $lang string the language iso code
     * @param $selectingProjectId
     * @param $owningProjectId
     * @return array
     */
    public function findProfilesApi($lang, $selectingProjectId, $owningProjectId)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(result)) AS json FROM (SELECT * FROM api.get_profiles_list(:lang, :selectingProject, :owningProject) ) result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(
            'lang' => $lang,
            'selectingProject' => $selectingProjectId,
            'owningProject' => $owningProjectId
        ));

        return $stmt->fetchAll();
    }

    /**
     * @param Project $project
     * @return array
     */
    public function findProfilesByProjectId(Project $project){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT pk_profile AS id, 
                standard_label AS \"standardLabel\",
                was_closed_at AS \"wasClosedAt\",
                end_date AS \"endDate\",
                is_forced_publication AS \"isForcedPublication\",
                is_ongoing AS \"isOngoing\",
                version AS \"version\"
                FROM che.profile
                WHERE pk_profile IN(
                  SELECT fk_profile 
                  FROM che.associates_project 
                  WHERE fk_project = :project
                  );";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('project' => $project->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Project $project
     * @return array
     */
    public function findProfilesForAssociationWithProjectByProjectId(Project $project){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT DISTINCT pk_profile AS \"profileId\", standard_label AS \"standardLabel\"
                FROM che.profile
                WHERE (was_closed_at IS NOT NULL OR is_forced_publication) AND end_date IS NULL AND NOT is_root_profile
                EXCEPT 
                SELECT DISTINCT pk_profile AS id, 
                standard_label AS \"standardLabel\"
                FROM che.profile
                WHERE pk_profile IN(
                  SELECT fk_profile 
                  FROM che.associates_project 
                  WHERE fk_project = :project
                  );";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('project' => $project->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param $lang string the language iso code
     * @param $profile int the ID of the profile
     * @return array
     * @throws DBALException
     */
    public function findClassesAndPropertiesByProfileIdApi($lang, $profile)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT result::text FROM api.get_owl_classes_and_properties_for_profiles(:lang, :profile, 0) as result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(
            'lang' => $lang,
            'profile' => $profile
        ));

        return $stmt->fetchAll();
    }

}