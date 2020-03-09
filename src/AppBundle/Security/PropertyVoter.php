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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PropertyVoter extends Voter
{
    const EDIT = 'edit';

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
        }

        throw new \LogicException('This code should not be reached!');
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
            if(is_null($property->getOngoingNamespace()) || is_null($user->getCurrentOngoingNamespace())) {
                return false;
            }

            if($userProjectAssociation->getProject() === $property->getOngoingNamespace()->getProjectForTopLevelNamespace()
                && $userProjectAssociation->getPermission() <= 3){
                return true;
            }
        }
        return false;
    }

}