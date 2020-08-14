<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 16/04/2018
 * Time: 22:43
 */

namespace AppBundle\Security;

use AppBundle\Entity\Property;
use AppBundle\Entity\User;
use LogicException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PropertyVoter extends Voter
{
    const EDIT = 'edit';
    const EDITMANAGER = 'edit_manager';

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::EDIT))) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof Property) {
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
        /** @var Property $property */
        $property = $subject;
        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($property, $user);
            case self::EDITMANAGER:
                return $this->canEditManager($property, $user);
        }

        throw new LogicException('This code should not be reached!');
    }

    /**
     * @param Property $property
     * @param User $user
     * @return bool TRUE if $user and $property have matching namespace (thanks to the $userProjectAssociation)
     */
    private function canEdit(Property $property, User $user)
    {
        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            // Il faut absolument un espace de nom pour pouvoir associer les entités à un espace de nom.
            // Or les projets n'ont pas forcément un espace de nom ongoing. Il faut donc à ce moment là empêcher le mode Edit.
            if(!$property->getPropertyVersionForDisplay()->getNamespaceForVersion()->getIsOngoing() || count($property->getPropertyVersions()) > 1) {
                return false;
            }

            if($userProjectAssociation->getProject() === $property->getPropertyVersionForDisplay()->getNamespaceForVersion()->getProjectForTopLevelNamespace()
                && $userProjectAssociation->getPermission() <= 3){
                return true;
            }
        }
        return false;
    }

    /**
     * @param Property $property
     * @param User $user
     * @return bool TRUE if $user is the administrator or a manager of $class project and $user and $class have matching namespace (thanks to the $userProjectAssociation)
     */
    private function canEditManager(Property $property, User $user)
    {
        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            // On ne peut éditer une property que si elle dispose d'une seule version
            if(!$property->getPropertyVersionForDisplay()->getNamespaceForVersion()->getIsOngoing() || count($property->getPropertyVersions()) > 1) {
                return false;
            }

            if($userProjectAssociation->getProject() === $property->getPropertyVersionForDisplay()->getNamespaceForVersion()->getProjectForTopLevelNamespace()
                && $userProjectAssociation->getPermission() <= 2){
                return true;
            }
        }
        return false;
    }

}