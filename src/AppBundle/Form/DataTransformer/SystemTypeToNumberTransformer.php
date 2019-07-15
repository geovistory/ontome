<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 06/02/2018
 * Time: 10:59
 */

namespace AppBundle\Form\DataTransformer;

use AppBundle\Entity\SystemType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class SystemTypeToNumberTransformer implements DataTransformerInterface
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Transforms an object (systemtype) to a string (number).
     *
     * @param  SystemType|null $class
     * @return string
     */
    public function transform($systemType)
    {
        if (null === $systemType) {
            return '';
        }

        return $systemType->getId();
    }

    /**
     * Transforms a string (number) to an object (systemtype).
     *
     * @param  string $systemTypeNumber
     * @return SystemType|null
     * @throws TransformationFailedException if object (systemtype) is not found.
     */
    public function reverseTransform($systemTypeNumber)
    {
        // no issue number? It's optional, so that's ok
        if (!$systemTypeNumber) {
            return;
        }

        $systemType = $this->em
            ->getRepository(SystemType::class)
            // query for the issue with this id
            ->find($systemTypeNumber)
        ;

        if (null === $systemType) {
            // causes a validation error
            // this message is not shown to the user
            // see the invalid_message option
            throw new TransformationFailedException(sprintf(
                'An issue with number "%s" does not exist!',
                $systemTypeNumber
            ));
        }

        return $systemType;
    }

}