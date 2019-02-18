<?php
/**
 * Created by PhpStorm.
 * User: pc-alexandre-pro
 * Date: 18/02/2019
 * Time: 16:04
 */

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CharacterLengthValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof CharacterLength)
        {
            throw new UnexpectedTypeException($constraint, CharacterLength::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if (null === $value || '' === $value)
        {
            return;
        }

        if (strlen(strip_tags($value)) < $constraint->min)
        {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}