<?php
/**
 * Created by PhpStorm.
 * User: pc-alexandre-pro
 * Date: 18/02/2019
 * Time: 16:03
 */

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class CharacterLength extends Constraint
{
    public $groups;
    public $min;
    public $message;
}