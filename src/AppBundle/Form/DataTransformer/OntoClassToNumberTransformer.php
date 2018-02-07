<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 06/02/2018
 * Time: 10:59
 */

namespace AppBundle\Form\DataTransformer;

use AppBundle\Entity\OntoClass;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class OntoClassToNumberTransformer implements DataTransformerInterface
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Transforms an object (ontoclass) to a string (number).
     *
     * @param  OntoClass|null $class
     * @return string
     */
    public function transform($class)
    {
        if (null === $class) {
            return '';
        }

        return $class->getId();
    }

    /**
     * Transforms a string (number) to an object (ontoclass).
     *
     * @param  string $classNumber
     * @return OntoClass|null
     * @throws TransformationFailedException if object (ontoclass) is not found.
     */
    public function reverseTransform($classNumber)
    {
        // no issue number? It's optional, so that's ok
        if (!$classNumber) {
            return;
        }

        $class = $this->em
            ->getRepository(OntoClass::class)
            // query for the issue with this id
            ->find($classNumber)
        ;

        if (null === $class) {
            // causes a validation error
            // this message is not shown to the user
            // see the invalid_message option
            throw new TransformationFailedException(sprintf(
                'An issue with number "%s" does not exist!',
                $classNumber
            ));
        }

        return $class;
    }

}