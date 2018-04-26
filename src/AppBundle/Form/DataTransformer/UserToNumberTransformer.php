<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 06/02/2018
 * Time: 10:59
 */

namespace AppBundle\Form\DataTransformer;

use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class UserToNumberTransformer implements DataTransformerInterface
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Transforms an object (user) to a string (number).
     *
     * @param  User|null $user
     * @return string
     */
    public function transform($user)
    {
        if (null === $user) {
            return '';
        }

        return $user->getId();
    }

    /**
     * Transforms a string (number) to an object (user).
     *
     * @param  string $userNumber
     * @return User|null
     * @throws TransformationFailedException if object (user) is not found.
     */
    public function reverseTransform($userNumber)
    {
        // no issue number? It's optional, so that's ok
        if (!$userNumber) {
            return;
        }

        $user = $this->em
            ->getRepository(User::class)
            // query for the issue with this id
            ->find($userNumber)
        ;

        if (null === $user) {
            // causes a validation error
            // this message is not shown to the user
            // see the invalid_message option
            throw new TransformationFailedException(sprintf(
                'An issue with number "%s" does not exist!',
                $userNumber
            ));
        }

        return $user;
    }

}