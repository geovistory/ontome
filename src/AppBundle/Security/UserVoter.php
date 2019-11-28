<?php
/**
 * Created by PhpStorm.
 * User: pc-alexandre-pro
 * Date: 26/11/2019
 * Time: 10:40
 */

namespace AppBundle\Security;

use AppBundle\Entity\EntityUserProjectAssociation;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    const EDIT = 'edit';

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::EDIT))) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof User) {
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
        /** @var User $user_ */
        $user_ = $subject;
        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($user_, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param User $user
     * @return bool TRUE if $user is the same
     */
    private function canEdit(User $user_, User $user)
    {
        if($user_ == $user){
            return true;
        }
        return false;
    }
}