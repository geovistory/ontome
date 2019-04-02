<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 20/06/2017
 * Time: 10:26
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class OntoNamespace
 * @ORM\Entity(repositoryClass="AppBundle\Repository\NamespaceRepository")
 * @UniqueEntity("namespaceURI", message="A namespace with the same URI already exists. Please chose another label for your project.")
 * @ORM\Table(schema="che", name="namespace")
 */
class OntoNamespace
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_namespace")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     * @Assert\Url()
     * @ORM\Column(type="text", nullable=false, unique=true)
     */
    private $namespaceURI;

    /**
     * @ORM\Column(type="text")
     */
    private $classPrefix;

    /**
     * @ORM\Column(type="text")
     */
    private $propertyPrefix;

    /**
     * @ORM\Column(type="integer")
     */
    private $importerInteger;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace",  inversedBy="childVersions")
     * @ORM\JoinColumn(name="fk_is_version_of", referencedColumnName="pk_namespace", nullable=true)
     */
    private $referencedVersion;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_top_level_namespace", referencedColumnName="pk_namespace", nullable=true)
     */
    private $topLevelNamespace;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isTopLevelNamespace;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isOngoing;

    /**
     * @ORM\ManyToOne(targetEntity="Project")
     * @ORM\JoinColumn(name="fk_project_for_top_level_namespace", referencedColumnName="pk_project")
     */
    private $projectForTopLevelNamespace;

    /**
     * @ORM\Column(type="string")
     */
    private $standardLabel;

    /**
     * @ORM\Column(type="date")
     */
    private $startDate;

    /**
     * @ORM\Column(type="date")
     */
    private $endDate;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Comment", mappedBy="namespace")
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

    /**
     * @Assert\Valid()
     * @Assert\NotBlank()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Label", mappedBy="namespace", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $labels;

    /**
     * @Assert\NotBlank()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="namespace")
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $textProperties;

    /**
     * @ORM\ManyToMany(targetEntity="OntoClass", mappedBy="namespaces")
     * @ORM\OrderBy({"identifierInNamespace" = "ASC"})
     */
    private $classes;

    /**
     * @ORM\ManyToMany(targetEntity="Property", mappedBy="namespaces")
     * @ORM\OrderBy({"identifierInNamespace" = "ASC"})
     */
    private $properties;

    /**
     * @ORM\ManyToMany(targetEntity="ClassAssociation", mappedBy="namespaces")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $classAssociations;

    /**
     * @ORM\ManyToMany(targetEntity="PropertyAssociation", mappedBy="namespaces")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $propertyAssociations;

    /**
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\Profile", mappedBy="namespaces")
     * @ORM\OrderBy({"standardLabel" = "ASC"})
     */
    private $profiles;

    /**
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\Project", mappedBy="namespaces")
     * @ORM\OrderBy({"standardLabel" = "ASC"})
     */
    private $projects;


    /**
     * @ORM\OneToMany(targetEntity="OntoNamespace", mappedBy="referencedVersion")
     */
    private $childVersions;

    public function __construct()
    {
        $this->classes = new ArrayCollection();
        $this->properties = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->textProperties = new ArrayCollection();
        $this->classAssociations = new ArrayCollection();
        $this->propertyAssociations = new ArrayCollection();
        $this->childVersions = new ArrayCollection();
        $this->profiles = new ArrayCollection();
        $this->projects = new ArrayCollection();
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
     * @return mixed
     */
    public function getNamespaceURI()
    {
        return $this->namespaceURI;
    }

    /**
     * @return mixed
     */
    public function getClassPrefix()
    {
        return $this->classPrefix;
    }

    /**
     * @return mixed
     */
    public function getPropertyPrefix()
    {
        return $this->propertyPrefix;
    }

    /**
     * @return mixed
     */
    public function getImporterInteger()
    {
        return $this->importerInteger;
    }

    /**
     * @return ArrayCollection|OntoNamespace
     */
    public function getReferencedVersion()
    {
        return $this->referencedVersion;
    }

    /**
     * @return OntoNamespace
     */
    public function getTopLevelNamespace()
    {
        return $this->topLevelNamespace;
    }

    /**
     * @return mixed
     */
    public function getIsTopLevelNamespace()
    {
        return $this->isTopLevelNamespace;
    }

    /**
     * @return mixed
     */
    public function getisOngoing()
    {
        return $this->isOngoing;
    }

    /**
     * @return Project
     */
    public function getProjectForTopLevelNamespace()
    {
        return $this->projectForTopLevelNamespace;
    }

    /**
     * @return mixed
     */
    public function getStandardLabel()
    {
        return $this->standardLabel;
    }

    /**
     * @return mixed
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @return mixed
     */
    public function getEndDate()
    {
        return $this->endDate;
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
     * @return ArrayCollection|OntoClass[]
     */
    public function getClasses()
    {
        return $this->classes;
    }

    /**
     * @return ArrayCollection|Property[]
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @return mixed
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * @return ArrayCollection|TextProperty[]
     */
    public function getTextProperties()
    {
        return $this->textProperties;
    }

    /**
     * @return ArrayCollection|ClassAssociation[]
     */
    public function getClassAssociations()
    {
        return $this->classAssociations;
    }

    /**
     * @return mixed
     */
    public function getPropertyAssociations()
    {
        return $this->propertyAssociations;
    }

    /**
     * @return ArrayCollection|Project[]
     */
    public function getProfiles()
    {
        return $this->profiles;
    }

    /**
     * @return ArrayCollection|Project[]
     */
    public function getProjects()
    {
        return $this->projects;
    }

    /**
     * @return ArrayCollection|OntoNamespace[]
     */
    public function getChildVersions()
    {
        return $this->childVersions;
    }

    public function getIdentifierInNamespace(){
        return $this->namespaceURI;
    }

    /**
     * @return string a human readable identification of the object
     */
    public function getObjectIdentification()
    {
        return $this->standardLabel;
    }

    /**
     * @param mixed $namespaceURI
     */
    public function setNamespaceURI($namespaceURI)
    {
        $this->namespaceURI = $namespaceURI;
    }

    /**
     * @param mixed $classPrefix
     */
    public function setClassPrefix($classPrefix)
    {
        $this->classPrefix = $classPrefix;
    }

    /**
     * @param mixed $propertyPrefix
     */
    public function setPropertyPrefix($propertyPrefix)
    {
        $this->propertyPrefix = $propertyPrefix;
    }

    /**
     * @param mixed $referencedVersion
     */
    public function setReferencedVersion($referencedVersion)
    {
        $this->referencedVersion = $referencedVersion;
    }

    /**
     * @param mixed $topLevelNamespace
     */
    public function setTopLevelNamespace($topLevelNamespace)
    {
        $this->topLevelNamespace = $topLevelNamespace;
    }

    /**
     * @param mixed $isTopLevelNamespace
     */
    public function setIsTopLevelNamespace($isTopLevelNamespace)
    {
        $this->isTopLevelNamespace = $isTopLevelNamespace;
    }

    /**
     * @param mixed $isOngoing
     */
    public function setIsOngoing($isOngoing)
    {
        $this->isOngoing = $isOngoing;
    }

    /**
     * @param mixed $projectForTopLevelNamespace
     */
    public function setProjectForTopLevelNamespace($projectForTopLevelNamespace)
    {
        $this->projectForTopLevelNamespace = $projectForTopLevelNamespace;
    }

    /**
     * @param mixed $startDate
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;
    }

    /**
     * @param mixed $endDate
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;
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



    public function addClassAssociation(ClassAssociation $classAssociation)
    {
        if ($this->classAssociations->contains($classAssociation)) {
            return;
        }
        $this->classAssociations[] = $classAssociation;
        // needed to update the owning side of the relationship!
        $classAssociation->addNamespace($this);
    }

    public function addPropertyAssociation(PropertyAssociation $propertyAssociation)
    {
        if ($this->propertyAssociations->contains($propertyAssociation)) {
            return;
        }
        $this->propertyAssociations[] = $propertyAssociation;
        // needed to update the owning side of the relationship!
        $propertyAssociation->addNamespace($this);
    }

    public function addLabel(Label $label)
    {
        if ($this->labels->contains($label)) {
            return;
        }
        $this->labels[] = $label;
        // needed to update the owning side of the relationship!
        $label->setNamespace($this);
    }

    public function __toString()
    {
        $s = $this->getStandardLabel();
        if(empty($s)) $s = $this->namespaceURI;
        return (string) $s;
    }

}