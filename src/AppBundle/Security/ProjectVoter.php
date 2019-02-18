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

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::EDIT))) {
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
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param Project $project
     * @param User $user
     * @return bool TRUE if $user is the creator of $project
     */
    private function canEdit(Project $project, User $user)
    {
        if($user->getId() == $project->getCreator()->getId())
        {
            return true;
        }
        return false;
    }
}