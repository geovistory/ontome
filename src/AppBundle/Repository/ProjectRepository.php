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
use Doctrine\ORM\EntityRepository;

class ProjectRepository extends EntityRepository
{
    /**
     * @param User $user
     * @return Project[] the list of project whom user is an admin
     */
    public function findAvailableProjectByAdminId(User $user)
    {
        return $this->createQueryBuilder('project')
            ->join('project.userProjectAssociations','upa')
            ->where('upa.user = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('project.standardLabel','ASC');
    }
}