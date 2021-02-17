<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 16/04/2018
 * Time: 22:43
 */

namespace AppBundle\Security;

use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OntoNamespaceVoter extends Voter
{
    const EDIT = 'edit';
    const FULLEDIT = 'full_edit';
    const EDITMANAGER = 'edit_manager';
    const ADDASSOCIATIONS = 'add_associations';
    const DELETEASSOCIATIONS = 'delete_associations';
    const VALIDATE = 'validate';
    const PUBLISH = 'publish';

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::EDIT, self::FULLEDIT, self::ADDASSOCIATIONS, self::DELETEASSOCIATIONS, self::VALIDATE, self::PUBLISH))) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof OntoNamespace) {
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

        // we know $subject is a OntoNamespace object, thanks to supports
        /** @var OntoNamespace $namespace */
        $namespace = $subject;
        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($namespace, $user);
            case self::FULLEDIT:
                return $this->canFullEdit($namespace, $user);
            case self::EDITMANAGER:
                return $this->canEditManager($namespace, $user);
            case self::ADDASSOCIATIONS:
                return $this->canAddAssociations($namespace, $user);
            case self::DELETEASSOCIATIONS:
                return $this->canDeleteAssociations($namespace, $user);
            case self::VALIDATE:
                return $this->canValidate($namespace, $user);
            case self::PUBLISH:
                return $this->canPublish($namespace, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param OntoNamespace $namespace
     * @param User $user
     * @return bool TRUE if $user and $namespace have matching namespace (thanks to the $userProjectAssociation)
     */
    private function canFullEdit(OntoNamespace $namespace, User $user)
    {
        if($namespace->getIsTopLevelNamespace()) {
            if($namespace->getHasPublication()) {
                return false;
            }
        }
        else if(!$namespace->getIsOngoing()) {
            return false;
        }
        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $namespace->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() <= 2){
                return true;
            }
        }
        return false;
    }

    /**
     * @param OntoNamespace $namespace
     * @param User $user
     * @return bool TRUE if $user and $namespace have matching namespace (thanks to the $userProjectAssociation)
     */
    private function canEdit(OntoNamespace $namespace, User $user)
    {
        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $namespace->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() <= 2){
                return true;
            }
        }
        return false;
    }

    /**
     * @param OntoNamespace $namespace
     * @param User $user
     * @return bool TRUE if $user and $namespace have matching namespace (thanks to the $userProjectAssociation) and $user is a project manager
     */
    private function canEditManager(OntoNamespace $namespace, User $user)
    {
        if(!$namespace->getIsOngoing()) {
            return false;
        }
        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $namespace->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() <= 2){
                return true;
            }
        }
        return false;
    }

    /**
     * @param OntoNamespace $namespace
     * @param User $user
     * @return bool TRUE if $user and $namespace have matching namespace (thanks to the $userProjectAssociation)
     */
    private function canPublish(OntoNamespace $namespace, User $user)
    {
        if(!$namespace->getIsOngoing()) {
            return false;
        }
        foreach ($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $namespace->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() == 1){
                $atLeastOneClassValidated = false;
                $atLeastOnePropertyValidated = false;
                foreach ($namespace->getClasses()->getIterator() as $j => $class) {
                    $validationStatus = $class->getClassVersionForDisplay($namespace)->getValidationStatus();
                    if (!is_null($validationStatus) && $validationStatus->getId() == 26) {
                        $atLeastOneClassValidated = true;
                    }
                }
                if($atLeastOneClassValidated) {
                    foreach ($namespace->getProperties()->getIterator() as $j => $property) {
                        $validationStatus = $property->getPropertyVersionForDisplay($namespace)->getValidationStatus();
                        if (!is_null($validationStatus) && $validationStatus->getId() == 26) {
                            $atLeastOnePropertyValidated = true;
                        }
                    }
                    if($atLeastOnePropertyValidated) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param OntoNamespace $namespace
     * @param User $user
     * @return bool TRUE if $user is a manager and if the namespace is ongoing
     */
    private function canValidate(OntoNamespace $namespace, User $user)
    {
        if(!$namespace->getIsOngoing()) {
            return false;
        }
        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $namespace->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() <= 2){
                return true;
            }
        }
        return false;
    }

    /**
     * @param OntoNamespace $namespace Entity namespace (ex ClassVersion.namespace). This is not the active project namespace.
     * @param User $user
     * @return bool TRUE if $user and $namespace have matching namespace (thanks to the $userProjectAssociation) and $user is a project manager
     */
    private function canAddAssociations(OntoNamespace $namespace, User $user)
    {
        // Condition 1 : ce namespace est référencé par le namespace du projet actif ?
        // Condition 2 : le namespace du projet actif est ongoing
        // Condition 3 : l'utilisateur peut gérer ce namespace ongoing

        //Récupérer le namespace actif du projet actif
        // Ongoing > Le plus récent > Le premier.
        $activeNamespace = $user->getCurrentActiveProject()->getManagedNamespaces()[0];
        foreach($user->getCurrentActiveProject()->getManagedNamespaces() as $managedNamespace){
            if($managedNamespace->getIsOngoing()){
                $activeNamespace=$managedNamespace;
            }
            elseif($activeNamespace->getIsOngoing() &&
                $activeNamespace->getPublishedAt()<$managedNamespace->getPublishedAt()){
                $activeNamespace=$managedNamespace;
            }
        }

        if(is_null($activeNamespace)){
            return false;
        }

        foreach($user->getUserProjectAssociations()->getIterator() as $i => $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $activeNamespace->getProjectForTopLevelNamespace() && $userProjectAssociation->getPermission() <= 2){
                if(($activeNamespace->getIsOngoing() && $namespace === $activeNamespace)){
                    return true;
                }
                foreach ($activeNamespace->getReferencedNamespaceAssociations() as $referencedNamespaceAssociation){
                    if($referencedNamespaceAssociation->getReferencedNamespace() == $namespace){
                        return true;
                    }
                }
            }
        }

        return false;
    }
}