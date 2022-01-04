<?php
/**
 * Created by PhpStorm.
 * User: pc-alexandre-pro
 * Date: 26/11/2019
 * Time: 10:40
 */

namespace AppBundle\Security;

use AppBundle\Entity\TextProperty;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TextPropertyVoter extends Voter
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
        if (!$subject instanceof TextProperty) {
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
        /** @var TextProperty $textProperty */
        $textProperty = $subject;
        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($textProperty, $user);
            case self::VALIDATE:
                return $this->canValidate($textProperty, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param TextProperty $textProperty
     * @param User $user
     * @return bool TRUE if User has authorization to edit textproperty
     */
    private function canEdit(TextProperty $textProperty, User $user)
    {
        // Ne pas autoriser l'utilisateur à editer même ongoing, s'il n'a pas activé le projet d'appartenance
        if(!is_null($textProperty->getNamespace()) and $user->getCurrentActiveProject() !== $textProperty->getNamespace()->getProjectForTopLevelNamespace()){
            return false;
        }
        if(!is_null($textProperty->getNamespaceForVersion()) and $user->getCurrentActiveProject() !== $textProperty->getNamespaceForVersion()->getProjectForTopLevelNamespace()){
            return false;
        }

        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            // Is it a textproperty for what ? Class/Property/... or for Profile or for Project ?
            if(!is_null($textProperty->getNamespaceForVersion()) and $textProperty->getNamespaceForVersion()->getIsOngoing()){
                //Txtp associated with Class/Property/SubclassOf/SubPropertyOf/EntityAssociation
                if($userProjectAssociation->getProject() === $textProperty->getNamespaceForVersion()->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() <= 3){
                    return true;
                }
            }
            elseif(!is_null($textProperty->getNamespace())) {
                //Txtp associated with Namespace
                if($userProjectAssociation->getProject() === $textProperty->getNamespace()->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() <= 3){
                    return true;
                }
            }
            elseif(!is_null($textProperty->getProject())) {
                //Txtp associated with Project
                if($userProjectAssociation->getProject() === $textProperty->getProject() && $userProjectAssociation->getPermission() <= 3){
                    return true;
                }
            }
            elseif(!is_null($textProperty->getProfile())) {
                //Txtp associated with Profile
                if($userProjectAssociation->getProject() === $textProperty->getProfile()->getProjectOfBelonging() && $userProjectAssociation->getPermission() <= 3){
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param TextProperty $textProperty
     * @param User $user
     * @return bool TRUE if User has authorization to validate textproperty
     */
    private function canValidate(TextProperty $textProperty, User $user)
    {
        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            // Is it a textproperty for what ? Class/Property/... or for Profile or for Project ?
            if(!is_null($textProperty->getNamespaceForVersion()) and $textProperty->getNamespaceForVersion()->getIsOngoing()){
                // Ne pas autoriser l'utilisateur à valider même ongoing, s'il n'a pas activé le projet d'appartenance
                if(!$textProperty->getNamespaceForVersion()->getIsOngoing() ||
                    $user->getCurrentActiveProject() !== $textProperty->getNamespaceForVersion()->getProjectForTopLevelNamespace()){
                    return false;
                }
                //Txtp associated with Class/Property/SubclassOf/SubPropertyOf/EntityAssociation
                if($userProjectAssociation->getProject() === $textProperty->getNamespaceForVersion()->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() <= 2){
                    return true;
                }
            }
            elseif(!is_null($textProperty->getNamespace())){
                // Ne pas autoriser l'utilisateur à valider même ongoing, s'il n'a pas activé le projet d'appartenance
                if($user->getCurrentActiveProject() !== $textProperty->getNamespace()->getProjectForTopLevelNamespace()){
                    return false;
                }
                //Txtp associated with Namespace
                if($userProjectAssociation->getProject() === $textProperty->getNamespaceForVersion()->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() <= 2){
                    return true;
                }
            }
        }
        return false;
    }
}