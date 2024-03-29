<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 29/06/2017
 * Time: 12:22
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Label
 * @ORM\Entity(repositoryClass="AppBundle\Repository\LabelRepository")
 * @ORM\Table(schema="che", name="label")
 */
class Label
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_label")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="text", nullable=false)
     */
    private $label;

    /**
     * @ORM\Column(type="text", nullable=false)
     */
    private $inverseLabel;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="text", nullable=false)
     */
    private $languageIsoCode;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace", inversedBy="labelVersions")
     * @ORM\JoinColumn(name="fk_namespace_for_version", referencedColumnName="pk_namespace", nullable=false)
     */
    private $namespaceForVersion;

    /**
     * @ORM\Column(type="integer")
     */
    private $importerInteger;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $isStandardLabelForLanguage;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="labels")
     * @ORM\JoinColumn(name="fk_class", referencedColumnName="pk_class")
     */
    private $class;

    /**
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="labels")
     * @ORM\JoinColumn(name="fk_property", referencedColumnName="pk_property")
     */
    private $property;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace", inversedBy="labels")
     * @ORM\JoinColumn(name="fk_namespace", referencedColumnName="pk_namespace")
     */
    private $namespace;

    /**
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="labels")
     * @ORM\JoinColumn(name="fk_project", referencedColumnName="pk_project")
     */
    private $project;

    /**
     * @ORM\ManyToOne(targetEntity="Profile", inversedBy="labels")
     * @ORM\JoinColumn(name="fk_profile", referencedColumnName="pk_profile")
     */
    private $profile;

    /**
     * @ORM\ManyToOne(targetEntity="SystemType")
     * @ORM\JoinColumn(name="validation_status", referencedColumnName="pk_system_type")
     * @Assert\Type(type="AppBundle\Entity\SystemType")
     */
    private $validationStatus;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Comment", mappedBy="label")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $comments;

    /**
     * @ORM\Column(type="text")
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
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return mixed
     */
    public function getInverseLabel()
    {
        return $this->inverseLabel;
    }

    /**
     * @return mixed
     */
    public function getLanguageIsoCode()
    {
        return $this->languageIsoCode;
    }

    /**
     * @return mixed
     */
    public function getImporterInteger()
    {
        return $this->importerInteger;
    }

    /**
     * @return mixed
     */
    public function getIsStandardLabelForLanguage()
    {
        return $this->isStandardLabelForLanguage;
    }

    /**
     * @return mixed
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
     * @return OntoClass|Property|OntoNamespace|Project|Profile|null the object described by the text property
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
        elseif (!is_null($this->project))
            $object = $this->project;
        elseif (!is_null($this->profile))
            $object = $this->profile;
        return $object;
    }

    /**
     * @return string a human readable identification of the object
     */
    public function getObjectIdentification()
    {
        return $this->label;
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
     * @param mixed $namespaceForVersion
     */
    public function setNamespaceForVersion($namespaceForVersion)
    {
        $this->namespaceForVersion = $namespaceForVersion;
    }

    /**
     * @param mixed $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @param mixed $label
     */
    public function setInverseLabel($inverseLabel)
    {
        $this->inverseLabel = $inverseLabel;
    }

    /**
     * @param mixed $languageIsoCode
     */
    public function setLanguageIsoCode($languageIsoCode)
    {
        $this->languageIsoCode = $languageIsoCode;
    }

    /**
     * @param mixed $isStandardLabelForLanguage
     */
    public function setIsStandardLabelForLanguage($isStandardLabelForLanguage)
    {
        $this->isStandardLabelForLanguage = $isStandardLabelForLanguage;
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
     * @param mixed $profile
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
    }

    /**
     * @param mixed $validationStatus
     */
    public function setValidationStatus($validationStatus)
    {
        $this->validationStatus = $validationStatus;
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

    public function __toString()
    {
        return (string) $this->label;
    }
}