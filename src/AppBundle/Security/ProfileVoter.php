<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 16/04/2018
 * Time: 22:43
 */

namespace AppBundle\Security;

use AppBundle\Entity\Profile;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProfileVoter extends Voter
{
    const EDIT = 'edit';

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::EDIT))) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof Profile) {
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
        /** @var Profile $profile */
        $profile = $subject;
        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($profile, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param Profile $profile
     * @param User $user
     * @return bool TRUE if $user is the creator of $profile and the project is still ongoing
     */
    private function canEdit(Profile $profile, User $user)
    {
        $canEdit = false;
        /*if(!$profile->getIsOngoing()) {
            $canEdit = false;
        }
        else {*/
            foreach ($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
                if ($userProjectAssociation->getProject()->getOwnedProfiles()->contains($profile) && $userProjectAssociation->getPermission() <= 2 ) { //permission <= means that the user is a project admin or manager
                    $canEdit = true;
                }
            }
        //}

        /*if($user->getId() == $profile->getCreator()->getId() && $profile->getIsOngoing())
        {
            return true;
        }*/
        return $canEdit;
    }
}