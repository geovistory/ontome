<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 09/01/2018
 * Time: 14:54
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ClassAssociation
 * @ORM\Entity
 * @ORM\Table(schema="che", name="is_subclass_of")
 * @UniqueEntity(
 *     fields={"childClass", "parentClass"},
 *     message="This parent class is already associated with this child class"
 * )
 */
class ClassAssociation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_is_subclass_of")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="childClassAssociations")
     * @ORM\JoinColumn(name="is_child_class", referencedColumnName="pk_class")
     */
    private $childClass;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="parentClassAssociations")
     * @ORM\JoinColumn(name="is_parent_class", referencedColumnName="pk_class")
     */
    private $parentClass;

    /**
     * @ORM\Column(type="text")
     */
    private $notes;

    /**
     * @Assert\NotNull()
     * @Assert\Valid()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="classAssociation", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $textProperties;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="creator", referencedColumnName="pk_user", nullable=false)
     */
    private $creator;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="modifier", referencedColumnName="pk_user", nullable=false)
     */
    private $modifier;

    /**
     * @ORM\Column(type="datetime")
     */
    private $creationTime;

    /**
     * @ORM\Column(type="datetime")
     */
    private $modificationTime;

    /**
     * ClassAssociation constructor.
     */
    public function __construct()
    {
        $this->textProperties = new ArrayCollection();
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
    public function getChildClass()
    {
        return $this->childClass;
    }

    /**
     * @Assert\NotBlank()
     * @return mixed
     */
    public function getParentClass()
    {
        return $this->parentClass;
    }

    /**
     * @return mixed
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * @return ArrayCollection|TextProperty[]
     */
    public function getTextProperties()
    {
        return $this->textProperties;
    }

    /**
     * @return mixed
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * @return mixed
     */
    public function getModifier()
    {
        return $this->modifier;
    }

    /**
     * @return mixed
     */
    public function getCreationTime()
    {
        return $this->creationTime;
    }

    /**
     * @return mixed
     */
    public function getModificationTime()
    {
        return $this->modificationTime;
    }

    /**
     * @param mixed $childClass
     */
    public function setChildClass($childClass)
    {
        $this->childClass = $childClass;
    }

    /**
     * @param mixed $textProperties
     */
    public function setTextProperties($textProperties)
    {
        $this->textProperties = $textProperties;
    }

    /**
     * @param mixed $parentClass
     */
    public function setParentClass($parentClass)
    {
        $this->parentClass = $parentClass;
    }

    /**
     * @param mixed $notes
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
    }

    /**
     * @param mixed $creator
     */
    public function setCreator($creator)
    {
        $this->creator = $creator;
    }

    /**
     * @param mixed $modifier
     */
    public function setModifier($modifier)
    {
        $this->modifier = $modifier;
    }

    /**
     * @param mixed $creationTime
     */
    public function setCreationTime($creationTime)
    {
        $this->creationTime = $creationTime;
    }

    /**
     * @param mixed $modificationTime
     */
    public function setModificationTime($modificationTime)
    {
        $this->modificationTime = $modificationTime;
    }

    public function addTextProperty(TextProperty $textProperty)
    {
        if ($this->textProperties->contains($textProperty)) {
            return;
        }
        $this->textProperties[] = $textProperty;
        // needed to update the owning side of the relationship!
        $textProperty->setClassAssociation($this);
    }

}