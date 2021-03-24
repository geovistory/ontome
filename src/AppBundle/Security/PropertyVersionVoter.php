<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 16/04/2018
 * Time: 22:43
 */

namespace AppBundle\Security;

use AppBundle\Entity\Property;
use AppBundle\Entity\PropertyVersion;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PropertyVersionVoter extends Voter
{
    const EDIT = 'edit';
    const VALIDATE = 'validate';

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::EDIT, self::VALIDATE))) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof PropertyVersion) {
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
        /** @var PropertyVersion $propertyVersion */
        $propertyVersion = $subject;
        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($propertyVersion, $user);
            case self::VALIDATE:
                return $this->canValidate($propertyVersion, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param PropertyVersion $propertyVersion
     * @param User $user
     * @return bool TRUE if $user and $property have matching namespace (thanks to the $userProjectAssociation)
     */
    private function canEdit(PropertyVersion $propertyVersion, User $user)
    {
        // La propriété doit être dans une version ongoing
        // Ne pas autoriser l'utilisateur à modifier une propriété, même ongoing, s'il n'a pas activé le projet d'appartenance
        if(!$propertyVersion->getNamespaceForVersion()->getIsOngoing() ||
            $user->getCurrentActiveProject() !== $propertyVersion->getNamespaceForVersion()->getProjectForTopLevelNamespace()){
            return false;
        }

        // Authorizhation level
        foreach($user->getUserProjectAssociations() as $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $propertyVersion->getNamespaceForVersion()->getProjectForTopLevelNamespace()
                && $userProjectAssociation->getPermission() <= 3){
                return true;
            }
        }
        return false;
    }

    /**
     * @param PropertyVersion $propertyVersion
     * @param User $user
     * @return bool TRUE if $user is the administrator or a manager of $class project and $user and $class have matching namespace (thanks to the $userProjectAssociation)
     */
    private function canValidate(PropertyVersion $propertyVersion, User $user)
    {
        // La propriété doit être dans une version ongoing
        // Ne pas autoriser l'utilisateur à modifier une propriété, même ongoing, s'il n'a pas activé le projet d'appartenance
        if(!$propertyVersion->getNamespaceForVersion()->getIsOngoing() ||
            $user->getCurrentActiveProject() !== $propertyVersion->getNamespaceForVersion()->getProjectForTopLevelNamespace()){
            return false;
        }

        // Authorizhation level
        foreach($user->getUserProjectAssociations() as $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $propertyVersion->getNamespaceForVersion()->getProjectForTopLevelNamespace()
                && $userProjectAssociation->getPermission() <= 2){
                return true;
            }
        }
        return false;
    }

}