<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 06/02/2018
 * Time: 10:59
 */

namespace AppBundle\Form\DataTransformer;

use AppBundle\Entity\Property;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class PropertyToNumberTransformer implements DataTransformerInterface
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Transforms an object (property) to a string (number).
     *
     * @param  Property|null $property
     * @return string
     */
    public function transform($property)
    {
        if (null === $property) {
            return '';
        }

        return $property->getId();
    }

    /**
     * Transforms a string (number) to an object (property).
     *
     * @param  string $propertyNumber
     * @return Property|null
     * @throws TransformationFailedException if object (property) is not found.
     */
    public function reverseTransform($propertyNumber)
    {
        // no issue number? It's optional, so that's ok
        if (!$propertyNumber) {
            return;
        }

        $property = $this->em
            ->getRepository(Property::class)
            // query for the issue with this id
            ->find($propertyNumber)
        ;

        if (null === $property) {
            // causes a validation error
            // this message is not shown to the user
            // see the invalid_message option
            throw new TransformationFailedException(sprintf(
                'An issue with number "%s" does not exist!',
                $propertyNumber
            ));
        }

        return $property;
    }

}