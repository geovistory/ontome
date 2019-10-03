<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 16/04/2018
 * Time: 22:43
 */

namespace AppBundle\Security;

use AppBundle\Entity\Project;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProjectVoter extends Voter
{
    const EDIT = 'edit';
    const FULLEDIT = 'full_edit';
    const EDITMANAGER = 'edit_manager';

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::EDIT, self::FULLEDIT, self::EDITMANAGER))) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof Project) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // the user must be logged in; if not, deny access
            return false;
        }

        // we know $subject is a Property object, thanks to supports
        /** @var Project $project */
        $project = $subject;
        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($project, $user);
            case self::FULLEDIT:
                return $this->canFullEdit($project, $user);
            case self::EDITMANAGER:
                return $this->canEditManager($project, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param Project $project
     * @param User $user
     * @return bool TRUE if $user is the administrator or a manager of $project
     */
    private function canEdit(Project $project, User $user)
    {
        foreach ($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if ($userProjectAssociation->getProject() == $project && $userProjectAssociation->getPermission() <= 2 ) { //permission <= means that the user is a project admin or manager
                return true;
            }
        }
        return false;
    }

    /**
     * @param Project $project
     * @param User $user
     * @return bool TRUE if $user is the administrator or a manager of $project
     */
    private function canEditManager(Project $project, User $user)
    {
        foreach ($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if ($userProjectAssociation->getProject() == $project && $userProjectAssociation->getPermission() <= 2 ) { //permission <= means that the user is a project admin or manager
                return true;
            }
        }
        return false;
    }

    /**
     * @param Project $project
     * @param User $user
     * @return bool TRUE if $user is the administrator of $project
     */
    private function canFullEdit(Project $project, User $user)
    {
        foreach ($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if ($userProjectAssociation->getProject() == $project && $userProjectAssociation->getPermission() === 1 ) { //permission <= means that the user is a project admin
                return true;
            }
        }
        return false;
    }
}