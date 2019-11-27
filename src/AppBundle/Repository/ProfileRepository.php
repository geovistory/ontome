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
use Doctrine\ORM\EntityRepository;

class ProfileRepository extends EntityRepository
{

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