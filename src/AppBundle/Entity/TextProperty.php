<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 11/07/2017
 * Time: 11:35
 */

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class TextProperty
 * @ORM\Entity
 * @ORM\Table(schema="che", name="text_property")
 */
class TextProperty
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_text_property")
     */
    private $id;

    /**
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
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_class", referencedColumnName="pk_class")
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
     */
    private $systemType;

    /**
     * @ORM\ManyToOne(targetEntity="Profile", inversedBy="textProperties")
     * @ORM\JoinColumn(name="fk_profile", referencedColumnName="pk_profile")
     */
    private $profile;

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
        return $object;
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


}