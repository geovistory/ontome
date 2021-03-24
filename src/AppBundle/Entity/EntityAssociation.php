<?php
/**
 * Created by PhpStorm.
 * User: pc-alexandre-pro
 * Date: 07/05/2019
 * Time: 14:45
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="sourceEntityAssociations")
     * @ORM\JoinColumn(name="fk_source_class", referencedColumnName="pk_class")
     */
    private $sourceClass;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="targetEntityAssociations")
     * @ORM\JoinColumn(name="fk_target_class", referencedColumnName="pk_class")
     */
    private $targetClass;

    /**
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="sourceEntityAssociations")
     * @ORM\JoinColumn(name="fk_source_property", referencedColumnName="pk_property")
     */
    private $sourceProperty;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_source_namespace_for_version", referencedColumnName="pk_namespace", nullable=false)
     */
    private $sourceNamespaceForVersion;

    /**
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="targetEntityAssociations")
     * @ORM\JoinColumn(name="fk_target_property", referencedColumnName="pk_property")
     */
    private $targetProperty;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_target_namespace_for_version", referencedColumnName="pk_namespace", nullable=false)
     */
    private $targetNamespaceForVersion;

    /**
     * @ORM\ManyToOne(targetEntity="SystemType", inversedBy="entityAssociations")
     * @ORM\JoinColumn(name="fk_system_type", referencedColumnName="pk_system_type")
     */
    private $systemType;

    /**
     * @ORM\ManyToOne(targetEntity="SystemType")
     * @ORM\JoinColumn(name="validation_status", referencedColumnName="pk_system_type")
     * @Assert\Type(type="AppBundle\Entity\SystemType")
     */
    private $validationStatus;

    /**
     * @ORM\Column(type="boolean", name="directed")
     */
    private $directed;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace", inversedBy="entityAssociationVersions")
     * @ORM\JoinColumn(name="fk_namespace_for_version", referencedColumnName="pk_namespace", nullable=false)
     */
    private $namespaceForVersion;

    /**
     * @Assert\NotNull()
     * @Assert\Valid()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="entityAssociation", cascade={"persist"})
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
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Comment", mappedBy="entityAssociation")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $comments;

    /**
     * EntityAssociation constructor.
     */
    public function __construct()
    {
        $this->textProperties = new ArrayCollection();
        $this->namespaces = new ArrayCollection();
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
    public function getSystemType()
    {
        return $this->systemType;
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
    public function getDirected()
    {
        return $this->directed;
    }

    /**
     * @return mixed
     */
    public function getTextProperties()
    {
        return $this->textProperties;
    }

    /**
     * @return mixed
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
     * @return mixed OntoClass or Property
     */
    public function getSource()
    {
        $source = null;
        if(!is_null($this->getSourceClass())) {
            $source = $this->getSourceClass();
        }
        else if(!is_null($this->getSourceProperty())) {
            $source = $this->getSourceProperty();
        }
        return $source;
    }

    public function getTarget()
    {
        $target = null;
        if(!is_null($this->getTargetClass())) {
            $target = $this->getTargetClass();
        }

        if(!is_null($this->getTargetProperty())) {
            $target = $this->getTargetProperty();
        }
        return $target;
    }

    /**
     * return String the source object's type
     */
    public function getSourceObjectType()
    {
        $objectType = null;
        if(!is_null($this->getSourceClass())) {
            $objectType = 'class';
        }
        if(!is_null($this->getSourceProperty())) {
            $objectType = 'property';
        }
        return $objectType;
    }

    /**
     * return String the source object's type
     */
    public function getTargetObjectType()
    {
        $objectType = null;
        if(!is_null($this->getTargetClass())) {
            $objectType = 'class';
        }
        if(!is_null($this->getTargetProperty())) {
            $objectType = 'property';
        }
        return $objectType;
    }

    /**
     * @return mixed
     */
    public function getSourceNamespaceForVersion()
    {
        return $this->sourceNamespaceForVersion;
    }

    /**
     * @return mixed
     */
    public function getTargetNamespaceForVersion()
    {
        return $this->targetNamespaceForVersion;
    }

    /**
     * @param mixed $namespaceForVersion
     */
    public function setNamespaceForVersion($namespaceForVersion)
    {
        $this->namespaceForVersion = $namespaceForVersion;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

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
     * @param mixed $systemType
     */
    public function setSystemType($systemType)
    {
        $this->systemType = $systemType;
    }

    /**
     * @param mixed $validationStatus
     */
    public function setValidationStatus($validationStatus)
    {
        $this->validationStatus = $validationStatus;
    }

    /**
     * @param mixed $directed
     */
    public function setDirected($directed)
    {
        $this->directed = $directed;
    }

    /**
     * @param mixed $textProperties
     */
    public function setTextProperties($textProperties)
    {
        $this->textProperties = $textProperties;
    }

    /**
     * @param mixed $namespaces
     */
    public function setNamespaces($namespaces)
    {
        $this->namespaces = $namespaces;
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
     * @param mixed $sourceNamespaceForVersion
     */
    public function setSourceNamespaceForVersion($sourceNamespaceForVersion)
    {
        $this->sourceNamespaceForVersion = $sourceNamespaceForVersion;
    }

    /**
     * @param mixed $targetNamespaceForVersion
     */
    public function setTargetNamespaceForVersion($targetNamespaceForVersion)
    {
        $this->targetNamespaceForVersion = $targetNamespaceForVersion;
    }

    public function addTextProperty(TextProperty $textProperty)
    {
        if ($this->textProperties->contains($textProperty)) {
            return;
        }
        $this->textProperties[] = $textProperty;
        // needed to update the owning side of the relationship!
        $textProperty->setEntityAssociation($this);
    }

    public function addNamespace(OntoNamespace $namespace)
    {
        if ($this->namespaces->contains($namespace)) {
            return;
        }
        $this->namespaces[] = $namespace;
    }

    public function __toString()
    {
        if(!is_null($this->sourceClass))
            return (string) $this->sourceClass->getClassVersionForDisplay().' - '.$this->targetClass->getClassVersionForDisplay().' : '.$this->systemType;
        elseif(!is_null($this->sourceProperty))
            return (string) $this->sourceProperty->getPropertyVersionForDisplay().' - '.$this->targetProperty->getPropertyVersionForDisplay().' : '.$this->systemType;
    }
}