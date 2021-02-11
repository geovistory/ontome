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
 * Class PropertyAssociation
 * @ORM\Entity
 * @ORM\Table(schema="che", name="is_subproperty_of")
 * @UniqueEntity(
 *     fields={"childProperty", "parentProperty"},
 *     message="This parent property is already associated with this child property"
 * )
 */
class PropertyAssociation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_is_subproperty_of")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="childPropertyAssociations")
     * @ORM\JoinColumn(name="is_child_property", referencedColumnName="pk_property")
     */
    private $childProperty;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_child_property_namespace", referencedColumnName="pk_namespace", nullable=false)
     */
    private $childPropertyNamespace;

    /**
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="parentPropertyAssociations")
     * @ORM\JoinColumn(name="is_parent_property", referencedColumnName="pk_property")
     */
    private $parentProperty;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_parent_property_namespace", referencedColumnName="pk_namespace", nullable=false)
     */
    private $parentPropertyNamespace;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace", inversedBy="propertyAssociationVersions")
     * @ORM\JoinColumn(name="fk_namespace_for_version", referencedColumnName="pk_namespace", nullable=false)
     */
    private $namespaceForVersion;

    /**
     * @ORM\ManyToOne(targetEntity="SystemType")
     * @ORM\JoinColumn(name="validation_status", referencedColumnName="pk_system_type")
     * @Assert\Type(type="AppBundle\Entity\SystemType")
     */
    private $validationStatus;

    /**
     * @ORM\Column(type="text")
     */
    private $notes;

    /**
     * @Assert\NotNull()
     * @Assert\Valid()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="propertyAssociation", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $textProperties;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Comment", mappedBy="propertyAssociation")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $comments;

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
     * PropertyAssociation constructor.
     */
    public function __construct()
    {
        $this->textProperties = new ArrayCollection();
        $this->namespaces = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return OntoNamespace
     */
    public function getNamespaceForVersion()
    {
        return $this->namespaceForVersion;
    }

    /**
     * @return mixed
     */
    public function getValidationStatus()
    {
        return $this->validationStatus;
    }

    /**
     * @return mixed
     */
    public function getChildProperty()
    {
        return $this->childProperty;
    }

    /**
     * @Assert\NotBlank()
     * @return mixed
     */
    public function getParentProperty()
    {
        return $this->parentProperty;
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
     * @return ArrayCollection|OntoNamespace[]
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * @return ArrayCollection|Comment[]
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * @return string a human readable identification of the object
     */
    public function getObjectIdentification()
    {
        return 'Property association n°'.$this->id;
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
     * @return mixed
     */
    public function getChildPropertyNamespace()
    {
        return $this->childPropertyNamespace;
    }

    /**
     * @param mixed $childPropertyNamespace
     */
    public function setChildPropertyNamespace($childPropertyNamespace)
    {
        $this->childPropertyNamespace = $childPropertyNamespace;
    }

    /**
     * @param mixed $namespaceForVersion
     */
    public function setNamespaceForVersion($namespaceForVersion)
    {
        $this->namespaceForVersion = $namespaceForVersion;
    }

    /**
     * @param mixed $validationStatus
     */
    public function setValidationStatus($validationStatus)
    {
        $this->validationStatus = $validationStatus;
    }

    /**
     * @param mixed $childProperty
     */
    public function setChildProperty($childProperty)
    {
        $this->childProperty = $childProperty;
    }

    /**
     * @param mixed $textProperties
     */
    public function setTextProperties($textProperties)
    {
        $this->textProperties = $textProperties;
    }

    /**
     * @param mixed $namespace
     */
    public function setNamespaces($namespaces)
    {
        $this->namespaces = $namespaces;
    }

    /**
     * @param mixed $parentProperty
     */
    public function setParentProperty($parentProperty)
    {
        $this->parentProperty = $parentProperty;
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

    /**
     * @return mixed
     */
    public function getParentPropertyNamespace()
    {
        return $this->parentPropertyNamespace;
    }

    /**
     * @param mixed $parentPropertyNamespace
     */
    public function setParentPropertyNamespace($parentPropertyNamespace)
    {
        $this->parentPropertyNamespace = $parentPropertyNamespace;
    }

    public function addTextProperty(TextProperty $textProperty)
    {
        if ($this->textProperties->contains($textProperty)) {
            return;
        }
        $this->textProperties[] = $textProperty;
        // needed to update the owning side of the relationship!
        $textProperty->setPropertyAssociation($this);
    }

    public function addNamespace(OntoNamespace $namespace)
    {
        if ($this->namespaces->contains($namespace)) {
            return;
        }
        $this->namespaces[] = $namespace;
        // needed to update the owning side of the relationship!
        $namespace->addPropertyAssociation($this);
    }

    public function __toString()
    {
        return (string) $this->childProperty->getPropertyVersionForDisplay().': parent property association';
    }

}