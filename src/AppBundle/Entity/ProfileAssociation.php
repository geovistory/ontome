<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 04/04/2018
 * Time: 22:07
 */

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ProfileAssociation
 * @ORM\Entity
 * @ORM\Table(schema="che", name="associates_profile")
 */
class ProfileAssociation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_associates_profile")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Profile", inversedBy="profileAssociations")
     * @ORM\JoinColumn(name="fk_profile", referencedColumnName="pk_profile", nullable=false)
     */
    private $profile;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Property")
     * @ORM\JoinColumn(name="fk_property", referencedColumnName="pk_property")
     */
    private $property;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\OntoClass")
     * @ORM\JoinColumn(name="fk_class", referencedColumnName="pk_class")
     */
    private $class;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_entity_namespace_for_version", referencedColumnName="pk_namespace", nullable=false)
     */
    private $entityNamespaceForVersion;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\OntoClass")
     * @ORM\JoinColumn(name="fk_inheriting_domain_class", referencedColumnName="pk_class")
     */
    private $domain;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_domain_namespace", referencedColumnName="pk_namespace", nullable=false)
     */
    private $domainNamespace;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\OntoClass")
     * @ORM\JoinColumn(name="fk_inheriting_range_class", referencedColumnName="pk_class")
     */
    private $range;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_range_namespace", referencedColumnName="pk_namespace", nullable=false)
     */
    private $rangeNamespace;

    /**
     * @ORM\ManyToOne(targetEntity="SystemType", inversedBy="profileAssociations")
     * @ORM\JoinColumn(name="fk_system_type", referencedColumnName="pk_system_type")
     */
    private $systemType;

    /**
     * @Assert\Valid()
     * @Assert\NotNull()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="profileAssociation", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $textProperties;

    /**
     * @ORM\Column(type="text")
     */
    private $notes;

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
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getProfile()
    {
        return $this->profile;
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
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return mixed
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @return mixed
     */
    public function getRange()
    {
        return $this->range;
    }

    /**
     * @return SystemType
     */
    public function getSystemType()
    {
        return $this->systemType;
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
     * @return mixed
     */
    public function getEntityNamespaceForVersion()
    {
        return $this->entityNamespaceForVersion;
    }

    /**
     * @return mixed
     */
    public function getObjectType()
    {
        if(!is_null($this->getClass())){
            return 'class';
        }
        if(!is_null($this->getProperty())){
            return 'property';
        }
    }

    /**
     * @return mixed
     */
    public function getDomainNamespace()
    {
        return $this->domainNamespace;
    }

    /**
     * @return mixed
     */
    public function getRangeNamespace()
    {
        return $this->rangeNamespace;
    }

    /**
     * @param mixed $profile
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
    }

    /**
     * @param mixed $property
     */
    public function setProperty($property)
    {
        $this->property = $property;
    }

    /**
     * @param mixed $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * @param mixed $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * @param mixed $range
     */
    public function setRange($range)
    {
        $this->range = $range;
    }

    /**
     * @param mixed $systemType
     */
    public function setSystemType($systemType)
    {
        $this->systemType = $systemType;
    }

    /**
     * @param mixed $textProperty
     */
    public function addTextProperty(TextProperty $textProperty)
    {
        if ($this->textProperties->contains($textProperty)) {
            return;
        }
        $this->textProperties[] = $textProperty;
        // needed to update the owning side of the relationship!
        $textProperty->setProfileAssociation($this);
    }


    /**
     * @param mixed $notes
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
    }

    /**
     * @param User $creator
     */
    public function setCreator($creator)
    {
        $this->creator = $creator;
    }

    /**
     * @param User $modifier
     */
    public function setModifier($modifier)
    {
        $this->modifier = $modifier;
    }

    /**
     * @param \DateTime $creationTime
     */
    public function setCreationTime($creationTime)
    {
        $this->creationTime = $creationTime;
    }

    /**
     * @param \DateTime $modificationTime
     */
    public function setModificationTime($modificationTime)
    {
        $this->modificationTime = $modificationTime;
    }

    /**
     * @param mixed $entityNamespaceForVersion
     */
    public function setEntityNamespaceForVersion($entityNamespaceForVersion)
    {
        $this->entityNamespaceForVersion = $entityNamespaceForVersion;
    }

    /**
     * @param mixed $domainNamespace
     */
    public function setDomainNamespace($domainNamespace)
    {
        $this->domainNamespace = $domainNamespace;
    }

    /**
     * @param mixed $rangeNamespace
     */
    public function setRangeNamespace($rangeNamespace)
    {
        $this->rangeNamespace = $rangeNamespace;
    }

    public function __toString()
    {
        return (string) $this->id;
    }


}