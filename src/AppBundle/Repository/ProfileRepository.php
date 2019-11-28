<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 23/06/2017
 * Time: 14:57
 */

namespace AppBundle\Repository;

use AppBundle\Entity\Profile;
use AppBundle\Entity\UserProjectAssociation;
use AppBundle\Entity\Project;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

class ProfileRepository extends EntityRepository
{
    public function findAllActiveProfilesForUserProject(UserProjectAssociation $userProjectAssociation)
    {
        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata('AppBundle\Entity\Profile', 'prf');

        $sql = "
          SELECT prf.* FROM che.profile prf
          LEFT JOIN che.associates_entity_to_user_project aseup ON aseup.fk_profile = prf.pk_profile 
          WHERE prf.pk_profile IN(
		    SELECT fk_profile FROM che.associates_project
		    WHERE fk_project = :id_project
	        )
          AND ((aseup.fk_system_type = 25 AND aseup.fk_associate_user_to_project IN(
            SELECT pk_associate_user_to_project FROM che.associate_user_to_project
	        WHERE fk_user = :id_user AND fk_project = :id_project)) 
	      OR aseup.fk_system_type IS NULL)
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('id_project', $userProjectAssociation->getProject()->getId());
        $query->setParameter('id_user', $userProjectAssociation->getUser()->getId());
        return $query->getResult();
    }

    public function findAllProfilesForUserProject(UserProjectAssociation $userProjectAssociation)
    {
        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata('AppBundle\Entity\Profile', 'prf');

        $sql = "
          SELECT prf.* FROM che.profile prf
          LEFT JOIN che.associates_entity_to_user_project aseup ON aseup.fk_profile = prf.pk_profile 
          WHERE prf.pk_profile IN(
		    SELECT fk_profile FROM che.associates_project
		    WHERE fk_project = :id_project
	        )
          AND (aseup.fk_associate_user_to_project IN(
            SELECT pk_associate_user_to_project FROM che.associate_user_to_project
	        WHERE fk_user = :id_user AND fk_project = :id_project))
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('id_project', $userProjectAssociation->getProject()->getId());
        $query->setParameter('id_user', $userProjectAssociation->getUser()->getId());
        return $query->getResult();
    }

    /**
     * @return array
     */
    public function findProfileGraph(Profile $profile){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT get_webvowl_json_for_profile AS json FROM che.get_webvowl_json_for_profile(:profile)";

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

}