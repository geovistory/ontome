<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 23/06/2017
 * Time: 14:57
 */

namespace AppBundle\Repository;

use AppBundle\Entity\Project;
use AppBundle\Entity\User;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class ProjectRepository extends EntityRepository
{
    /**
     * @param User $user
     * @return QueryBuilder to create the query for the list of project whom user is an admin
     */
    public function findAvailableProjectByAdminId(User $user)
    {
        return $this->createQueryBuilder('project')
            ->join('project.userProjectAssociations','upa')
            ->where('upa.user = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('project.standardLabel','ASC');
    }

    /**
     * @param $lang string the language iso code
     * @param $project int the ID of the project
     * @return array
     * @throws DBALException
     */
    public function findClassesAndPropertiesByProjectIdApi($lang, $project)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT result::text FROM api.get_owl_classes_and_properties_for_profiles(:lang, 0, :project) as result;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array(
            'lang' => $lang,
            'project' => $project
        ));

        return $stmt->fetchAll();
    }
}