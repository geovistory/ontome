<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 26/04/2017
 * Time: 11:38
 */

namespace AppBundle\Repository;


use AppBundle\Entity\Project;
use Doctrine\ORM\EntityRepository;

class UserRepository extends EntityRepository
{
    /**
     * @param Project $project
     * @return array
     */
    public function findAllNotInProject(Project $project)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT  usr.pk_user AS id,
                        usr.first_name || ' ' || last_name AS fullname,
                        usr.institution AS institution
                FROM che.admin_user usr
                WHERE usr.pk_user NOT IN (SELECT fk_user FROM che.associate_user_to_project WHERE fk_project = :project);";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('project' => $project->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param Project $project
     * @return array
     */
    public function findUsersInProject(Project $project)
    {
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT  usr.pk_user AS id,
                        usr.first_name || ' ' || last_name AS fullname,
                        usr.institution AS institution,
                        aup.pk_associate_user_to_project as \"associationId\",
                        aup.has_permissions_in_project as permission
                FROM che.admin_user usr
                JOIN che.associate_user_to_project aup ON usr.pk_user = aup.fk_user
                WHERE aup.fk_project = :project;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('project' => $project->getId()));

        return $stmt->fetchAll();
    }

}