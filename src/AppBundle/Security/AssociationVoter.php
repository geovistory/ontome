<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 16/04/2018
 * Time: 22:43
 */

namespace AppBundle\Security;

use AppBundle\Entity\ClassAssociation;
use AppBundle\Entity\PropertyAssociation;
use AppBundle\Entity\EntityAssociation;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AssociationVoter extends Voter
{
    const EDIT = 'edit';
    const DELETE = 'delete';

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::EDIT, self::DELETE))) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!($subject instanceof ClassAssociation || $subject instanceof PropertyAssociation || $subject instanceof EntityAssociation)) {
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

        // we know $subject is a (Class/Property/Entity)Association object, thanks to supports
        /** @var mixed $association */
        $association = $subject;
        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($association, $user);
            case self::DELETE:
                return $this->canDelete($association, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param mixed $association
     * @param User $user
     * @return bool TRUE if $user and $association have matching namespace (thanks to the $userProjectAssociation)
     */
    private function canEdit($association, User $user)
    {
        if(is_null($user->getCurrentOngoingNamespace())){return false;}
        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $association->getNamespaceForVersion()->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() <= 2 && $user->getCurrentOngoingNamespace()->getProjectForTopLevelNamespace() === $association->getNamespaceForVersion()->getProjectForTopLevelNamespace()){
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed $association
     * @param User $user
     * @return bool TRUE if $user and $namespace have matching namespace (thanks to the $userProjectAssociation) and $user is a project manager
     */
    private function canDelete($association, User $user)
    {
        if(is_null($user->getCurrentOngoingNamespace())){return false;}
        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $association->getNamespaceForVersion()->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() <= 2 && $user->getCurrentOngoingNamespace()->getProjectForTopLevelNamespace() === $association->getNamespaceForVersion()->getProjectForTopLevelNamespace()){
                if($association->getNamespaceForVersion()->getIsOngoing() && count($association->getNamespaceForVersion()->getTopLevelNamespace()->getChildVersions()) <= 1){
                    return true;
                }
            }
        }
        return false;
    }
}