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
     * @Assert\Url(message="Please enter a valid URI")
     * @ORM\Column(type="text", nullable=true, unique=true)
     */
    private $namespaceURI;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isExternalNamespace;

    /**
     * @Assert\Url()
     * @ORM\Column(type="text", nullable=true, unique=true)
     */
    private $originalNamespaceURI;

    /**
     * @Assert\Length(
     *     min = 1,
     *     max = 6,
     *     minMessage="Your class prefix must be at least {{ limit }} characters long",
     *     maxMessage="Your class prefix cannot be loger than {{ limit }} characters"
     * )
     * @ORM\Column(type="text")
     */
    private $classPrefix;

    /**
     * @Assert\Length(
     *     min = 1,
     *     max = 6,
     *     minMessage="Your property prefix must be at least {{ limit }} characters long",
     *     maxMessage="Your property prefix cannot be loger than {{ limit }} characters"
     * )
     * @ORM\Column(type="text")
     */
    private $propertyPrefix;

    /**
     * @Assert\GreaterThanOrEqual(
     *     value = 0,
     *     message="Your current class number should be greater than or equal to 0."
     * )
     * @ORM\Column(type="integer")
     */
    private $currentClassNumber;

    /**
     * @Assert\GreaterThanOrEqual(
     *     value = 0,
     *     message="Your current property number should be greater than or equal to 0."
     * )
     * @ORM\Column(type="integer")
     */
    private $currentPropertyNumber;

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
     * @ORM\Column(type="boolean")
     */
    private $isVisible;

    /**
     * @ORM\Column(type="boolean")
     */
    private $hasPublication;

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
     * @Assert\NotNull()
     * @ORM\Column(type="text")
     * @Assert\Regex(
     *     pattern="/^[a-z0-9](-?[a-z0-9])*$/",
     *     match=true,
     *     message="Your prefix can only contain alphanumeric characters without accents and dashes"
     * )
     */
    private $rootNamespacePrefix;

    /**
     * @ORM\Column(type="integer")
     */
    private $uriParameter;
    /*
     * Pour les namespaces root (les namespaces versions n'auront pas besoin de champ = null)
     * 0: Entity identifier
     * 1: Entity identifier + label
     * 2: camelCase
     * 3: No parameter
     */

    /**
     * @Assert\Valid()
     * @Assert\NotBlank()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Label", mappedBy="namespace", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $labels;

    /**
     * @Assert\Valid()
     * @Assert\NotNull()
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
     * @ORM\OneToMany(targetEntity="ClassAssociation", mappedBy="namespaceForVersion")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $classAssociations;

    /**
     * @ORM\OneToMany(targetEntity="EntityAssociation", mappedBy="namespaceForVersion")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $entityAssociations;

    /**
     * @ORM\OneToMany(targetEntity="PropertyAssociation", mappedBy="namespaceForVersion")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $propertyAssociations;

    /**
     * @ORM\ManyToMany(targetEntity="EntityUserProjectAssociation", mappedBy="namespace")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $namespaceUserProjectAssociation;

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
     * @return mixed
     */
    public function getCurrentClassNumber()
    {
        return $this->currentClassNumber;
    }

    /**
     * @param mixed $currentClassNumber
     */
    public function setCurrentClassNumber($currentClassNumber)
    {
        $this->currentClassNumber = $currentClassNumber;
    }

    /**
     * @return mixed
     */
    public function getCurrentPropertyNumber()
    {
        return $this->currentPropertyNumber;
    }

    /**
     * @param mixed $currentPropertyNumber
     */
    public function setCurrentPropertyNumber($currentPropertyNumber)
    {
        $this->currentPropertyNumber = $currentPropertyNumber;
    }


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
     * @return bool
     */
    public function getIsVisible()
    {
        return $this->isVisible;
    }

    /**
     * @param mixed $isVisible
     */
    public function setIsVisible($isVisible)
    {
        $this->isVisible = $isVisible;
    }

    public function __construct()
    {
        $this->classes = new ArrayCollection();
        $this->properties = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->textProperties = new ArrayCollection();
        $this->classAssociations = new ArrayCollection();
        $this->propertyAssociations = new ArrayCollection();
        $this->entityAssociations = new ArrayCollection();
        $this->referencedNamespaceAssociations = new ArrayCollection();
        $this->childVersions = new ArrayCollection();
        $this->profiles = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->projectAssociations = new ArrayCollection();
        $this->classVersions = new ArrayCollection();
        $this->propertyVersions = new ArrayCollection();
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
     * @return mixed
     */
    public function getIsExternalNamespace()
    {
        return $this->isExternalNamespace;
    }

    /**
     * @return bool
     */
    public function getIsOngoing()
    {
        return $this->isOngoing;
    }

    /**
     * @return bool
     */
    public function getHasPublication()
    {
        return $this->hasPublication;
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
     * @return string a human readable identification of the object
     */
    public function getObjectIdentification()
    {
        return $this->standardLabel;
    }

    /**
     * @return ArrayCollection|ReferencedNamespaceAssociation[]
     */
    public function getReferencedNamespaceAssociations()
    {
        return $this->referencedNamespaceAssociations;
    }

    /**
     * @return ArrayCollection|OntoNamespace[] Retourne TOUS les espaces de noms référencés directs
     */
    public function getDirectReferencedNamespaces()
    {
        $referencedNamespaces = new ArrayCollection;
        foreach ($this->referencedNamespaceAssociations as $referencedNamespaceAssociation){
            $referencedNamespaces->add($referencedNamespaceAssociation->getReferencedNamespace());
        }
        return $referencedNamespaces;
    }

    /**
     * @return ArrayCollection|OntoNamespace[] Retourne TOUS les espaces de noms référencés directs ET indirects
     */
    public function getAllReferencedNamespaces(ArrayCollection $allReferencedNamespaces=null){
        if(is_null($allReferencedNamespaces)){$allReferencedNamespaces = new ArrayCollection;}
        if($this->getDirectReferencedNamespaces()->isEmpty()){
            return new ArrayCollection;
        }
        else
        {
            foreach ($this->getDirectReferencedNamespaces() as $directReferencedNamespace){
                if(!$allReferencedNamespaces->contains($directReferencedNamespace)){
                    $allReferencedNamespaces->add($directReferencedNamespace);
                    foreach($directReferencedNamespace->getAllReferencedNamespaces($allReferencedNamespaces) as $ns){
                        if(!$allReferencedNamespaces->contains($ns)){
                            $allReferencedNamespaces->add($ns);
                        }
                    }
                }
            }
            return $allReferencedNamespaces;
        }
    }

    /**
     * @return mixed
     */
    public function getRootNamespacePrefix()
    {
        if($this->isTopLevelNamespace){
            return $this->rootNamespacePrefix;
        }
        else{
            return false;
        }
    }

    /**
     * @param mixed $rootNamespacePrefix
     */
    public function setRootNamespacePrefix($rootNamespacePrefix)
    {
        $this->rootNamespacePrefix = $rootNamespacePrefix;
    }

    /**
     * @param mixed $namespaceURI
     */
    public function setNamespaceURI($namespaceURI)
    {
        $this->namespaceURI = $namespaceURI;
    }

    /**
     * @param mixed $isExternalNamespace
     */
    public function setIsExternalNamespace($isExternalNamespace)
    {
        $this->isExternalNamespace = $isExternalNamespace;
    }

    /**
     * @param mixed $standardLabel
     */
    public function setStandardLabel($standardLabel)
    {
        $this->standardLabel = $standardLabel;
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
     * @param mixed $hasPublication
     */
    public function setHasPublication($hasPublication)
    {
        $this->hasPublication = $hasPublication;
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
    }

    public function addPropertyAssociation(PropertyAssociation $propertyAssociation)
    {
        if ($this->propertyAssociations->contains($propertyAssociation)) {
            return;
        }
        $this->propertyAssociations[] = $propertyAssociation;
    }

    public function addEntityAssociation(EntityAssociation $entityAssociation)
    {
        if ($this->entityAssociations->contains($entityAssociation)) {
            return;
        }
        $this->entityAssociations[] = $entityAssociation;
    }

    public function addReferencedNamespaceAssociation(ReferencedNamespaceAssociation $referencedNamespaceAssociation)
    {
        if ($this->referencedNamespaceAssociations->contains($referencedNamespaceAssociation)) {
            return;
        }
        $this->referencedNamespaceAssociations[] = $referencedNamespaceAssociation;
    }

    public function addClassVersion(OntoClassVersion $classVersion){
        if ($this->classVersions->contains($classVersion)) {
            return;
        }
        $this->classVersions[] = $classVersion;

        if (!$this->classes->contains($classVersion->getClass())) {
            $this->classes[] = $classVersion->getClass();
        }
    }

    public function addPropertyVersion(PropertyVersion $propertyVersion){
        if ($this->propertyVersions->contains($propertyVersion)) {
            return;
        }
        $this->propertyVersions[] = $propertyVersion;

        if (!$this->properties->contains($propertyVersion->getProperty())) {
            $this->properties[] = $propertyVersion->getProperty();
        }
    }

    public function addReferenceNamespaceAssociation(ReferencedNamespaceAssociation $referencedNamespaceAssociation)
    {
        if ($this->referencedNamespaceAssociations->contains($referencedNamespaceAssociation)) {
            return;
        }
        $this->referencedNamespaceAssociations[] = $referencedNamespaceAssociation;
        // needed to update the owning side of the relationship!
        $referencedNamespaceAssociation->setNamespace($this);
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
        if(empty($s)) $s = $this->namespaceURI;
        return (string) $s;
    }

    /**
     * @return string the URI to be displayed
     */
    public function getDisplayURI()
    {
        $s ='';
        if(!empty($this->namespaceURI)){
            $s = $this->namespaceURI;
        }
        else $s =$this->getTopLevelNamespace()->getNamespaceURI();
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

    /**
     * @return integer
     */
    public function getUriParameter()
    {
        return $this->uriParameter;
    }

    /**
     * @param integer $uriParameter
     */
    public function setUriParameter($uriParameter)
    {
        $this->uriParameter = $uriParameter;
    }

    /**
     * @return array
     * Retourne un tableau des id de ces namespaces suivants : ce namespace (this) et ses namespaces référencés, parents y compris.
     */
    public function getSelectedNamespacesId()
    {
        // L'espace de noms 4 est systématiquement référencé par tous les NS c'est purement technique.
        $arrayIds[] = 4;

        $arrayIds[] = $this->getId();
        foreach($this->getAllReferencedNamespaces() as $referencedNamespace){
            $arrayIds[] = $referencedNamespace->getId();
        }

        // array_unique évite les doublons - utile si on veut compter combien de ns différents
        return array_values(array_unique($arrayIds));
    }

    /**
     * @return array
     * Retourne un tableau des id de ces namespaces suivants : tous les namespaces dans le même root que ce namespace (this) et tous les namespaces dans le même root de ses namespaces référencés.
     * Utilisé notamment pour répérer les entités qui n'existent pas dans une version mais dans une autre du même root
     */
    public function getLargeSelectedNamespacesId()
    {
        // L'espace de noms 4 est systématiquement référencé par tous les NS c'est purement technique.
        $arrayIds[] = 4;

        // Boucle sur le root du namespace d'origine :
        foreach ($this->getTopLevelNamespace()->getChildVersions() as $ns)
        {
            $arrayIds[] = $ns->getId();
        }

        foreach($this->getReferencedNamespaceAssociations() as $referencedNamespaceAssociation){
            foreach ($referencedNamespaceAssociation->getReferencedNamespace()->getTopLevelNamespace()->getChildVersions() as $ns)
            {
                $arrayIds[] = $ns->getId();
            }
        }

        // array_unique évite les doublons - utile si on veut compter combien de ns différents
        return array_values(array_unique($arrayIds));
    }

    /**
     * @return Array
     * Retourne un string pour indiquer le paramètre
     */
    public function getUriParameterStrings(){
            switch ($this->getUriParameter()){
                case 0:
                    $arrStr = array('label' => 'Entity identifier', 'classExample' => 'C1', 'propertyExample' => 'P1');
                    break;
                case 1:
                    $arrStr = array('label' => 'Entity identifier + label', 'classExample' => 'C1_Class_Label', 'propertyExample' => 'P1_Property_Label');
                    break;
                case 2:
                    $arrStr = array('label' => 'camelCase label', 'classExample' => 'classLabel', 'propertyExample' => 'propertyLabel');
                    break;
                case 3:
                    $arrStr = array('label' => 'No parameter', 'classExample' => 'user_choice', 'propertyExample' => 'user_choice');
                    break;
                default:
                    $arrStr = array('label' => 'Unknown parameter', 'classExample' => 'Unknown parameter', 'propertyExample' => 'Unknown parameter');
                    break;
            }
            return $arrStr;
    }
}