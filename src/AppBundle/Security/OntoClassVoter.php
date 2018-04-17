<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 16/04/2018
 * Time: 22:43
 */

namespace AppBundle\Security;

use AppBundle\Entity\OntoClass;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OntoClassVoter extends Voter
{
    const EDIT = 'edit';

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::EDIT))) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof OntoClass) {
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

        // we know $subject is a OntoClass object, thanks to supports
        /** @var OntoClass $class */
        $class = $subject;
        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($class, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param OntoClass $class
     * @param User $user
     * @return bool TRUE if $user and $class have matching namespace (thanks to the $userProjectAssociation)
     */
    private function canEdit(OntoClass $class, User $user)
    {
        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $class->getOngoingNamespace()->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() === 1){
                return true;
            }
        }
        return false;
    }

}