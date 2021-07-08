<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 11/07/2017
 * Time: 11:35
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\GroupSequenceProviderInterface;
use AppBundle\Validator\Constraints\CharacterLength;


/**
 * Class TextProperty
 * @ORM\Entity(repositoryClass="AppBundle\Repository\TextPropertyRepository")
 * @ORM\Table(schema="che", name="text_property")
 * @Assert\GroupSequenceProvider()
 */
class TextProperty implements GroupSequenceProviderInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_text_property")
     */
    private $id;

    /**
     * @CharacterLength(
     *      groups={"Description"},
     *      min = 40,
     *      message = "Your description must be at least 40 characters long",
     * )
     * @Assert\NotBlank()
     * @ORM\Column(type="text", nullable=false)
     */
    private $textProperty;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="text", nullable=false)
     */
    private $languageIsoCode;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_namespace_for_version", referencedColumnName="pk_namespace", nullable=false)
     */
    private $namespaceForVersion;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_class", referencedColumnName="pk_class")
     * @Assert\Type(type="AppBundle\Entity\OntoClass")
     * @Assert\Valid()
     */
    private $class;

    /**
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_property", referencedColumnName="pk_property")
     */
    private $property;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_namespace", referencedColumnName="pk_namespace")
     */
    private $namespace;

    /**
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_project", referencedColumnName="pk_project")
     */
    private $project;

    /**
     * @ORM\ManyToOne(targetEntity="SystemType", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_text_property_type", referencedColumnName="pk_system_type")
     * @Assert\Type(type="AppBundle\Entity\SystemType")
     */
    private $systemType;

    /**
     * @ORM\ManyToOne(targetEntity="SystemType")
     * @ORM\JoinColumn(name="validation_status", referencedColumnName="pk_system_type")
     * @Assert\Type(type="AppBundle\Entity\SystemType")
     */
    private $validationStatus;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Profile", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_profile", referencedColumnName="pk_profile")
     * @Assert\Type(type="AppBundle\Entity\Profile")
     * @Assert\Valid()
     */
    private $profile;

    /**
     * @ORM\ManyToOne(targetEntity="ClassAssociation", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_is_subclass_of", referencedColumnName="pk_is_subclass_of")
     * @Assert\Type(type="AppBundle\Entity\ClassAssociation")
     * @Assert\Valid()
     */
    private $classAssociation;

    /**
     * @ORM\ManyToOne(targetEntity="EntityAssociation", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_entity_association", referencedColumnName="pk_entity_association")
     * @Assert\Type(type="AppBundle\Entity\EntityAssociation")
     * @Assert\Valid()
     */
    private $entityAssociation;

    /**
     * @ORM\ManyToOne(targetEntity="PropertyAssociation", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_is_subproperty_of", referencedColumnName="pk_is_subproperty_of")
     * @Assert\Type(type="AppBundle\Entity\PropertyAssociation")
     * @Assert\Valid()
     */
    private $propertyAssociation;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Comment", mappedBy="textProperty")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $comments;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $notes;

    /**
     * @Assert\NotBlank()
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="creator", referencedColumnName="pk_user", nullable=false)
     */
    private $creator;

    /**
     * @Assert\NotBlank()
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

    public function __construct()
    {
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
    public function getTextProperty()
    {
        return $this->textProperty;
    }

    /**
     * @return mixed
     */
    public function getLanguageIsoCode()
    {
        return $this->languageIsoCode;
    }

    /**
     * @return OntoClass
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return mixed
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @return mixed
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return mixed
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @return mixed
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * @return ClassAssociation
     */
    public function getClassAssociation()
    {
        return $this->classAssociation;
    }

    /**
     * @return PropertyAssociation
     */
    public function getPropertyAssociation()
    {
        return $this->propertyAssociation;
    }

    /**
     * @return OntoClass|Property|OntoNamespace|null the object described by the text property
     */
    public function getObject()
    {
        $object = null;
        if(!is_null($this->class))
            $object =  $this->class;
        elseif (!is_null($this->property))
            $object = $this->property;
        elseif (!is_null($this->namespace))
            $object = $this->namespace;
        elseif (!is_null($this->classAssociation))
            $object = $this->classAssociation;
        elseif (!is_null($this->propertyAssociation))
            $object = $this->propertyAssociation;
        elseif (!is_null($this->entityAssociation))
            $object = $this->entityAssociation;
        elseif (!is_null($this->profile))
            $object = $this->profile;
        elseif (!is_null($this->project))
            $object = $this->project;
        return $object;
    }

    /**
     * @return ArrayCollection|Comment[]
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * @return SystemType
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
    public function getNotes()
    {
        return $this->notes;
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
     * @return string a human readable identification of the object
     */
    public function getObjectIdentification()
    {
        return 'Text property n°'.$this->id;
    }

    /**
     * @param mixed $namespaceForVersion
     */
    public function setNamespaceForVersion($namespaceForVersion)
    {
        $this->namespaceForVersion = $namespaceForVersion;
    }

    /**
     * @param mixed $textProperty
     */
    public function setTextProperty($textProperty)
    {
        $this->textProperty = $textProperty;
    }

    /**
     * @param mixed $languageIsoCode
     */
    public function setLanguageIsoCode($languageIsoCode)
    {
        $this->languageIsoCode = $languageIsoCode;
    }

    /**
     * @param mixed $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * @param mixed $property
     */
    public function setProperty($property)
    {
        $this->property = $property;
    }

    /**
     * @param mixed $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @param mixed $project
     */
    public function setProject($project)
    {
        $this->project = $project;
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
     * @param mixed $profile
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
    }

    /**
     * @param ClassAssociation $classAssociation
     */
    public function setClassAssociation(ClassAssociation $classAssociation = null)
    {
        $this->classAssociation = $classAssociation;
    }

    /**
     * @param PropertyAssociation $propertyAssociation
     */
    public function setPropertyAssociation($propertyAssociation)
    {
        $this->propertyAssociation = $propertyAssociation;
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
    public function getEntityAssociation()
    {
        return $this->entityAssociation;
    }

    /**
     * @param mixed $entityAssociation
     */
    public function setEntityAssociation($entityAssociation)
    {
        $this->entityAssociation = $entityAssociation;
    }


    /**
     * Returns which validation groups should be used for a certain state
     * of the object.
     *
     * @return array An array of validation groups
     */
    public function getGroupSequence()
    {
        if(!is_null($this->systemType) && $this->systemType->getId() == 16)
        {
            return ['TextProperty', 'Description'];
        }
        return ['TextProperty'];
    }
}