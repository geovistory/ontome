<?php
/**
 * Created by PhpStorm.
 * User: pc-alexandre-pro
 * Date: 07/05/2019
 * Time: 14:45
 */

namespace AppBundle\Entity;


use Doctrine\ORM\Mapping as ORM;

/**
 * Class EntityAssociation
 * @ORM\Entity
 * @ORM\Table(name="che.entity_association")
 */
class EntityAssociation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_entity_association")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="entityAssociations")
     * @ORM\JoinColumn(name="fk_source_class", referencedColumnName="pk_class")
     */
    private $sourceClass;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="entityAssociations")
     * @ORM\JoinColumn(name="fk_target_class", referencedColumnName="pk_class")
     */
    private $targetClass;

    /**
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="entityAssociations")
     * @ORM\JoinColumn(name="fk_source_property", referencedColumnName="pk_property")
     */
    private $sourceProperty;

    /**
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="entityAssociations")
     * @ORM\JoinColumn(name="fk_source_property", referencedColumnName="pk_property")
     */
    private $targetProperty;

    /**
     * @ORM\Column(type="boolean")
     */
    private $directed;

    /**
     * @param mixed $sourceClass
     */
    public function setSourceClass($sourceClass)
    {
        $this->sourceClass = $sourceClass;
    }

    /**
     * @param mixed $targetClass
     */
    public function setTargetClass($targetClass)
    {
        $this->targetClass = $targetClass;
    }

    /**
     * @param mixed $sourceProperty
     */
    public function setSourceProperty($sourceProperty)
    {
        $this->sourceProperty = $sourceProperty;
    }

    /**
     * @param mixed $targetProperty
     */
    public function setTargetProperty($targetProperty)
    {
        $this->targetProperty = $targetProperty;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getSourceClass()
    {
        return $this->sourceClass;
    }

    /**
     * @return mixed
     */
    public function getTargetClass()
    {
        return $this->targetClass;
    }

    /**
     * @return mixed
     */
    public function getSourceProperty()
    {
        return $this->sourceProperty;
    }

    /**
     * @return mixed
     */
    public function getTargetProperty()
    {
        return $this->targetProperty;
    }

    /**
     * @return mixed
     */
    public function getDirected()
    {
        return $this->directed;
    }

    /**
     * @param mixed $directed
     */
    public function setDirected($directed)
    {
        $this->directed = $directed;
    }

    public function __toString()
    {
        return (string) $this->sourceClass.$this->targetClass;
    }

    public function inverseClasses()
    {
        if(!$this->directed)
        {
            $temp = $this->sourceClass;
            $this->sourceClass = $this->targetClass;
            $this->targetClass = $temp;
        }
    }
}