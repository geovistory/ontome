<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 16/04/2018
 * Time: 22:43
 */

namespace AppBundle\Security;

use AppBundle\Entity\OntoClassVersion;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OntoClassVersionVoter extends Voter
{
    const EDIT = 'edit';

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::EDIT))) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof OntoClassVersion) {
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
        /** @var OntoClassVersion $classVersion */
        $classVersion = $subject;
        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($classVersion, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param OntoClassVersion $classVersion
     * @param User $user
     * @return bool TRUE if $user and $classVersion have matching namespace (thanks to the $userProjectAssociation)
     */
    private function canEdit(OntoClassVersion $classVersion, User $user)
    {
        // La classe doit être dans une version ongoing
        // Ne pas autoriser l'utilisateur à modifier une classe, même ongoing, s'il n'a pas activé le projet d'appartenance
        if(!$classVersion->getNamespaceForVersion()->getIsOngoing() ||
            $user->getCurrentActiveProject() !== $classVersion->getNamespaceForVersion()->getProjectForTopLevelNamespace()){
            return false;
        }

        foreach($user->getUserProjectAssociations() as $userProjectAssociation) {
            if($userProjectAssociation->getProject() === $classVersion->getNamespaceForVersion()->getProjectForTopLevelNamespace()
                && $userProjectAssociation->getPermission() <= 3){
                return true;
            }
        }
        return false;
    }

}