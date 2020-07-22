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
     * @Assert\Regex(
     *     pattern="/^[a-z0-9\-]+$/",
     *     message="The characters string for this namspace's OntoME URI must contain only lower case non accent letters and dash"
     * )
     * @ORM\Column(type="text", nullable=false, unique=true)
     */
    private $namespaceURI;

    /**
     * @Assert\Url()
     * @ORM\Column(type="text", nullable=true, unique=true)
     */
    private $originalNamespaceURI;

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
     * @ORM\Column(type="datetime")
     */
    private $publishedAt;

    /**
     * @ORM\Column(type="datetime")
     */
    private $deprecatedAt;

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
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="namespace", cascade={"persist"})
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
     * @ORM\ManyToMany(targetEntity="EntityAssociation", mappedBy="namespaces")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $entityAssociations;

    /**
     * @return mixed
     */
    public function getEntityAssociations()
    {
        return $this->entityAssociations;
    }

    /**
     * @param mixed $entityAssociations
     */
    public function setEntityAssociations($entityAssociations)
    {
        $this->entityAssociations = $entityAssociations;
    }

    /**
     * @ORM\ManyToMany(targetEntity="PropertyAssociation", mappedBy="namespaces")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $propertyAssociations;

    /**
     * @ORM\ManyToMany(targetEntity="EntityUserProjectAssociation", mappedBy="namespace")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $namespaceUserProjectAssociation;

    /**
     * @return mixed
     */
    public function getNamespaceUserProjectAssociation()
    {
        return $this->namespaceUserProjectAssociation;
    }

    /**
     * @param mixed $namespaceUserProjectAssociation
     */
    public function setNamespaceUserProjectAssociation($namespaceUserProjectAssociation)
    {
        $this->namespaceUserProjectAssociation = $namespaceUserProjectAssociation;
    }

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
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\ProjectAssociation", mappedBy="namespace")
     */
    private $projectAssociations;

    /**
     * @ORM\OneToMany(targetEntity="OntoNamespace", mappedBy="referencedVersion")
     */
    private $childVersions;

    /**
     * @ORM\OneToMany(targetEntity="ReferencedNamespaceAssociation", mappedBy="namespace")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $referencedNamespaceAssociations;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Comment", mappedBy="namespaceForVersion")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $commentVersions;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\ClassAssociation", mappedBy="namespaceForVersion")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $classAssociationVersions;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\EntityAssociation", mappedBy="namespaceForVersion")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $entityAssociationVersions;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Label", mappedBy="namespaceForVersion")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $labelVersions;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\OntoClassVersion", mappedBy="namespaceForVersion")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $classVersions;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\PropertyVersion", mappedBy="namespaceForVersion")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $propertyVersions;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\PropertyAssociation", mappedBy="namespaceForVersion")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $propertyAssociationVersions;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="namespaceForVersion")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $textPropertyVersions;

    public function __construct()
    {
        $this->classes = new ArrayCollection();
        $this->properties = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->textProperties = new ArrayCollection();
        $this->classAssociations = new ArrayCollection();
        $this->propertyAssociations = new ArrayCollection();
        $this->referencedNamespaceAssociations = new ArrayCollection();
        $this->childVersions = new ArrayCollection();
        $this->profiles = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->projectAssociations = new ArrayCollection();
        $this->classAssociationVersions = new ArrayCollection();
        $this->commentVersions = new ArrayCollection();
        $this->entityAssociationVersions = new ArrayCollection();
        $this->labelVersions = new ArrayCollection();
        $this->classVersions = new ArrayCollection();
        $this->propertyVersions = new ArrayCollection();
        $this->propertyAssociationVersions = new ArrayCollection();
        $this->textPropertyVersions = new ArrayCollection();
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
    public function getOriginalNamespaceURI()
    {
        return $this->originalNamespaceURI;
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
     * @return bool
     */
    public function getIsOngoing()
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
    public function getPublishedAt()
    {
        return $this->publishedAt;
    }

    /**
     * @return mixed
     */
    public function getDeprecatedAt()
    {
        return $this->deprecatedAt;
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
     * @return ArrayCollection|ProjectAssociation
     */
    public function getProjectAssociations()
    {
        return $this->projectAssociations;
    }

    /**
     * @return ArrayCollection
     */
    public function getCommentVersions()
    {
        return $this->commentVersions;
    }

    /**
     * @return ArrayCollection
     */
    public function getClassAssociationVersions()
    {
        return $this->classAssociationVersions;
    }

    /**
     * @return ArrayCollection
     */
    public function getEntityAssociationVersions()
    {
        return $this->entityAssociationVersions;
    }

    /**
     * @return ArrayCollection
     */
    public function getLabelVersions()
    {
        return $this->labelVersions;
    }

    /**
     * @return ArrayCollection
     */
    public function getClassVersions()
    {
        return $this->classVersions;
    }

    /**
     * @return ArrayCollection
     */
    public function getPropertyVersions()
    {
        return $this->propertyVersions;
    }

    /**
     * @return ArrayCollection
     */
    public function getPropertyAssociationVersions()
    {
        return $this->propertyAssociationVersions;
    }

    /**
     * @return ArrayCollection
     */
    public function getTextPropertyVersions()
    {
        return $this->textPropertyVersions;
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
     * @param mixed $originalNamespaceURI
     */
    public function setOriginalNamespaceURI($originalNamespaceURI)
    {
        $this->originalNamespaceURI = $originalNamespaceURI;
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
     * @param mixed $publishedAt
     */
    public function setPublishedAt($publishedAt)
    {
        $this->publishedAt = $publishedAt;
    }

    /**
     * @param mixed $deprecatedAt
     */
    public function setDeprecatedAt($deprecatedAt)
    {
        $this->deprecatedAt = $deprecatedAt;
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
     * @return ArrayCollection|ReferencedNamespaceAssociation[]
     */
    public function getReferencedNamespaceAssociations()
    {
        return $this->referencedNamespaceAssociations;
    }

    /**
     * @param mixed $referencedNamespaceAssociations
     */
    public function setReferencedNamespaceAssociations($referencedNamespaceAssociations)
    {
        $this->referencedNamespaceAssociations = $referencedNamespaceAssociations;
    }

    /**
     * @param ArrayCollection $labels
     */
    public function setLabels($labels)
    {
        $this->labels = $labels;
    }

    /**
     * @param ArrayCollection $textProperties
     */
    public function setTextProperties($textProperties)
    {
        $this->textProperties = $textProperties;
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

    public function addTextProperty(TextProperty $textProperty)
    {
        if ($this->textProperties->contains($textProperty)) {
            return;
        }
        $this->textProperties[] = $textProperty;
        // needed to update the owning side of the relationship!
        $textProperty->setNamespace($this);
    }

    public function __toString()
    {
        $s = $this->getStandardLabel();
        if(empty($s)) $s = 'https://dataforhistory.org/'.$this->namespaceURI;
        return (string) $s;
    }

    /**
     * @return string the URI to be displayed
     */
    public function getDisplayURI()
    {
        $s ='';
        if(!empty($this->originalNamespaceURI)){
            $s = $this->originalNamespaceURI;
        }
        else $s = 'https://ontome.dataforhistory.org/'.$this->namespaceURI;
        return $s;
    }

    /**
     * @return array
     * Utilisé pour le dropdown sélection espace de noms sur une classe / propriété / etc
     * Peut être réutilisé sur une fonctionnalité similaire
     */
    public function getStatus()
    {
        if($this->getIsOngoing()){
            return ["classCss"=>"warning", "label"=>"Ongoing"];
        }
        elseif(is_null($this->getDeprecatedAt())){
            return ["classCss"=>"success", "label"=>"Published"];
        }
        else{
            return ["classCss"=>"danger", "label"=>"Deprecated"];
        }
    }
}