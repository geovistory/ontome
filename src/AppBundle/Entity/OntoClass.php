<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 09/06/2017
 * Time: 16:29
 */

namespace AppBundle\Entity;

use AppBundle\Utils\StringUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Class OntoClass
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ClassRepository")
 * @ORM\Table(schema="che", name="class")
 */
class OntoClass
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_class")
     */
    private $id;

    /**
     * @Assert\Regex(pattern="/^[a-zA-Z0-9_.]+$/", message="This identifier should be alphanumeric without space. Underscores and dots are allowed.")
     * @ORM\Column(type="string")
     */
    private $identifierInNamespace;

    /**
     * @ORM\Column(type="string", name="identifier_in_uri")
     * @Assert\NotBlank(message="The identifier in URI field cannot be empty")
     */
    private $identifierInURI;

    /**
     * @var boolean
     * A non-persisted field that's used to know if the $identifierInNamespace field is manually set by the user
     * or automatically set by a trigger in the database
     */
    private $isManualIdentifier;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\OntoClassVersion", mappedBy="class", cascade={"persist"})
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $classVersions;

    /**
     * @ORM\Column(type="text")
     */
    private $importerXmlField;

    /**
     * @ORM\Column(type="text")
     */
    private $importerTextField;

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
     * @ORM\OneToMany(targetEntity="ClassAssociation", mappedBy="childClass")
     */
    private $childClassAssociations;

    /**
     * @ORM\OneToMany(targetEntity="ClassAssociation", mappedBy="parentClass")
     */
    private $parentClassAssociations;

    /**
     * @ORM\OneToMany(targetEntity="EntityAssociation", mappedBy="sourceClass")
     */
    private $sourceEntityAssociations;

    /**
     * @ORM\OneToMany(targetEntity="EntityAssociation", mappedBy="targetClass")
     */
    private $targetEntityAssociations;


    /**
     * @ORM\ManyToMany(targetEntity="OntoNamespace",  inversedBy="classes", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(schema="che", name="class_version",
     *      joinColumns={@ORM\JoinColumn(name="fk_class", referencedColumnName="pk_class")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_namespace_for_version", referencedColumnName="pk_namespace")}
     *      )
     */
    private $namespaces;

    /**
     * @Assert\Valid()
     * @Assert\NotNull()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Label", mappedBy="class", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $labels;

    /**
    * @Assert\Valid()
    * @Assert\NotNull()
    * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="class", cascade={"persist"})
    * @ORM\OrderBy({"languageIsoCode" = "ASC", "creationTime" = "DESC"})
    */
    private $textProperties;

    /**
     * @ORM\ManyToMany(targetEntity="Profile",  mappedBy="classes", fetch="LAZY")
     * @ORM\JoinTable(schema="che", name="associates_profile",
     *      joinColumns={@ORM\JoinColumn(name="fk_class", referencedColumnName="pk_class")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_profile", referencedColumnName="pk_profile")}
     *      )
     */
    private $profiles;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Comment", mappedBy="class")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $comments;

    /**
     * @Assert\Valid()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\ProfileAssociation", mappedBy="class", cascade={"persist"})
     * @ORM\OrderBy({"systemType" = "ASC"})
     */
    private $profileAssociations;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isRecursive;

    public function __construct()
    {
        $this->namespaces = new ArrayCollection();
        $this->classVersions = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->textProperties = new ArrayCollection();
        $this->profiles = new ArrayCollection();
        $this->parentClassAssociations = new ArrayCollection();
        $this->childClassAssociations = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    /**
     * @Assert\Callback
     * @param ExecutionContextInterface $context
     * @param $payload
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        // check if the identifier is set when needed
        if ($this->isManualIdentifier && empty($this->identifierInNamespace)) {
            $context->buildViolation('The identifier cannot be null.')
                ->atPath('identifierInNamespace')
                ->addViolation();
        }
        else if($this->isManualIdentifier) {
            // Retrouver l'ensemble d'espaces de noms concernés pour l'identifiant.
            // Il ne faut donc PAS utiliser $this->getNamespaces qui ne retrouve que les namespaces de CETTE classe
            // (d'autres namespaces du même root mais qui n'ont pas cette classe, peuvent donc échapper)
            // il faut donc simplement récupérer le root et boucler dessus
            $rootNamespace = $this->getClassVersionForDisplay()->getNamespaceForVersion()->getTopLevelNamespace();
            $uniqueIdentifiant = true;
            foreach ($rootNamespace->getChildVersions() as $namespace) {
                foreach ($namespace->getClasses() as $class) {
                    if ($class->identifierInNamespace == $this->identifierInNamespace and $class != $this) {
                        $uniqueIdentifiant = false;
                        break;
                    }
                }
                //Il faut aussi boucler sur les identifiants des propriétés
                foreach ($namespace->getProperties() as $property) {
                    if ($property->getIdentifierInNamespace() == $this->identifierInNamespace) {
                        $uniqueIdentifiant = false;
                        break;
                    }
                }
            }
            if(!$uniqueIdentifiant){
                $context->buildViolation('The identifier must be unique within the same namespace. Please enter a different one.')
                    ->atPath('identifierInNamespace')
                    ->addViolation();
            }
        }

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
    public function getIdentifierInNamespace()
    {
        return $this->identifierInNamespace;
    }

    /**
     * @return mixed
     */
    public function getIdentifierInURI()
    {
        return $this->identifierInURI;
    }

    /**
     * @return bool
     */
    public function isManualIdentifier()
    {
        return $this->isManualIdentifier;
    }

    /**
     * @return ArrayCollection|OntoClassVersion[]
     */
    public function getClassVersions()
    {
        return $this->classVersions;
    }

    /**
     * @return mixed
     */
    public function getImporterXmlField()
    {
        return $this->importerXmlField;
    }

    /**
     * @return mixed
     */
    public function getImporterTextField()
    {
        return $this->importerTextField;
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
     * @return ArrayCollection|OntoNamespace[]
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * @return ArrayCollection|Label[]
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
     * @return ArrayCollection|Profile[]
     */
    public function getProfiles()
    {
        return $this->profiles;
    }

    /**
     * @return ArrayCollection|Comment[]
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * @return OntoNamespace
     */
    public function getOngoingNamespace()
    {
        return $this->ongoingNamespace;
    }

    /**
     * @return OntoNamespace
     */
    public function getTopLevelNamespace()
    {
        /** On n'a besoin que d'un seul namespace, donc on pioche le 1er */
        return $this->getNamespaces()[0]->getTopLevelNamespace();
    }

    /**
     * @return ArrayCollection|ClassAssociation[]
     */
    public function getParentClassAssociations()
    {
        return $this->parentClassAssociations;
    }

    /**
     * @return ArrayCollection|ClassAssociation[]
     */
    public function getChildClassAssociations()
    {
        return $this->childClassAssociations;
    }

    /**
     * @return ArrayCollection|ProfileAssociation[]
     */
    public function getProfileAssociations()
    {
        return $this->profileAssociations;
    }

    /**
     * @return string a human readable identification of the object
     */
    public function getObjectIdentification()
    {
        return $this->identifierInNamespace;
    }

    /**
     * @param mixed $identifierInNamespace
     */
    public function setIdentifierInNamespace($identifierInNamespace)
    {
        $this->identifierInNamespace = $identifierInNamespace;
    }

    /**
     * @param mixed $identifierInURI
     */
    public function setIdentifierInURI($identifierInURI)
    {
        $this->identifierInURI = $identifierInURI;
    }


    /**
     * @param bool $isManualIdentifier
     */
    public function setIsManualIdentifier($isManualIdentifier)
    {
        $this->isManualIdentifier = $isManualIdentifier;
    }

    /**
     * @param mixed $propertiesAsDomain
     */
    public function setPropertiesAsDomain($propertiesAsDomain)
    {
        $this->propertiesAsDomain = $propertiesAsDomain;
    }

    /**
     * @param mixed $propertiesAsRange
     */
    public function setPropertiesAsRange($propertiesAsRange)
    {
        $this->propertiesAsRange = $propertiesAsRange;
    }

    /**
     * @param mixed $labels
     */
    public function setLabels($labels)
    {
        $this->labels = $labels;
    }

    /**
     * @param mixed $textProperties
     */
    public function setTextProperties($textProperties)
    {
        $this->textProperties = $textProperties;
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
     * @param OntoClassVersion $classVersion
     */
    public function addClassVersion(OntoClassVersion $classVersion)
    {
        if ($this->classVersions->contains($classVersion)) {
            return;
        }
        $this->classVersions[] = $classVersion;
        // needed to update the owning side of the relationship!
        $classVersion->setClass($this);
    }

    public function addTextProperty(TextProperty $textProperty)
    {
        if ($this->textProperties->contains($textProperty)) {
            return;
        }
        $this->textProperties[] = $textProperty;
        // needed to update the owning side of the relationship!
        $textProperty->setClass($this);
    }

    public function addLabel(Label $label)
    {
        if ($this->labels->contains($label)) {
            return;
        }
        $this->labels[] = $label;
        // needed to update the owning side of the relationship!
        $label->setClass($this);
    }

    public function addNamespace(OntoNamespace $namespace)
    {
        if ($this->namespaces->contains($namespace)) {
            return;
        }
        $this->namespaces[] = $namespace;
    }

    public function addPropertyAsDomain(Property $property)
    {
        if ($this->propertiesAsDomain->contains($property)) {
            return;
        }
        $this->propertiesAsDomain[] = $property;
    }

    public function addProfile(Profile $profile)
    {
        if ($this->profiles->contains($profile)) {
            return;
        }
        $this->profiles[] = $profile;
    }

    public function addProfileAssociation(ProfileAssociation $profileAssociation)
    {
        if ($this->profileAssociations->contains($profileAssociation)) {
            return;
        }
        $this->profileAssociations[] = $profileAssociation;
        // needed to update the owning side of the relationship!
        $profileAssociation->setClass($this);
    }

    /**
     * @return mixed
     */
    public function getSourceEntityAssociations()
    {
        return $this->sourceEntityAssociations;
    }

    /**
     * @param mixed $sourceEntityAssociations
     */
    public function setSourceEntityAssociations($sourceEntityAssociations)
    {
        $this->sourceEntityAssociations = $sourceEntityAssociations;
    }

    /**
     * @return mixed
     */
    public function getTargetEntityAssociations()
    {
        return $this->targetEntityAssociations;
    }

    /**
     * @param mixed $targetEntityAssociations
     */
    public function setTargetEntityAssociations($targetEntityAssociations)
    {
        $this->targetEntityAssociations = $targetEntityAssociations;
    }

    /**
     * @return ArrayCollection|ClassAssociation[]
     */
    public function getEntityAssociations()
    {
        $entityAssociations = new ArrayCollection();
        foreach ($this->sourceEntityAssociations as $entityAssociation){
            $entityAssociations->add($entityAssociation);
        }
        foreach ($this->targetEntityAssociations as $entityAssociation){
            $entityAssociations->add($entityAssociation);
        }
        return $entityAssociations;
    }

    /**
     * @param OntoNamespace|null $namespace
     * @return OntoClassVersion the classVersion to be displayed
     */
    public function getClassVersionForDisplay(OntoNamespace $namespace=null)
    {
        $cvCollection = $this->getClassVersions();

        if(!is_null($namespace)){
            $cvCollection = $this->getClassVersions()->filter(function(OntoClassVersion $classVersion) use ($namespace){
                return $classVersion->getNamespaceForVersion() === $namespace;
            });
        }
        else{
            if($cvCollection->count()>1){
                $cvCollection = $this->getClassVersions()->filter(function(OntoClassVersion $classVersion) {
                    return $classVersion->getNamespaceForVersion()->getIsOngoing();
                });
            }
        }

        // La classe a été trouvée
        if($cvCollection->count() == 1){
            return $cvCollection->first();
        }

        // Avec le filtre IsOngoing ci dessus, si un NS root n'en possède pas, cvCollection est maintenant vide, il faut donc le réinitialiser
        $cvCollection = $this->getClassVersions();
        //filtrer que les NS qui ont bien published_at rempli
        $cvCollection = $cvCollection->filter(function(OntoClassVersion $classVersion) {
            return !is_null($classVersion->getNamespaceForVersion()->getPublishedAt());
        });
        $iterator = $cvCollection->getIterator();
        $iterator->uasort(function ($a, $b) {
            return ($a->getNamespaceForVersion()->getPublishedAt() > $b->getNamespaceForVersion()->getPublishedAt()) ? -1 : 1;
        });
        $collection = new ArrayCollection(iterator_to_array($iterator));
        return $collection->first();
    }

    /**
     * @return ArrayCollection|Mixed Retourne un arbre hierarchique de classes descendantes dans un NS donné
     */
    public function getHierarchicalTreeClasses($namespace, ArrayCollection $tree=null, $depth=1){
        if(is_null($tree)){$tree = new ArrayCollection;}
        $nsRef = $namespace->getAllReferencedNamespaces();
        $nsRef->add($namespace);
        if($this->getParentClassAssociations()->filter(function($v) use ($namespace){return $namespace == $v->getNamespaceForVersion();})->isEmpty()){
            return $tree;
        }
        else
        {
            foreach ($this->getParentClassAssociations()->filter(function($v) use ($namespace){return $namespace == $v->getNamespaceForVersion();}) as $parentClassAssociation)
            {
                $tree->add(array($parentClassAssociation->getChildClass(),$depth, $parentClassAssociation->getChildClassNamespace()));
                $tree = $parentClassAssociation->getChildClass()->getHierarchicalTreeClasses($namespace, $tree, $depth+1);
            }
            return $tree;
        }
    }

    /**
     * @return mixed
     */
    public function getIsRecursive()
    {
        return $this->isRecursive;
    }

    /**
     * @param mixed $isRecursive
     */
    public function setIsRecursive($isRecursive)
    {
        $this->isRecursive = $isRecursive;
    }

    public function updateIdentifierInUri(){
        $uriParameter = $this->getTopLevelNamespace()->getUriParameter();
        switch ($uriParameter){
            case 0: //Entity identifier
                $this->setIdentifierInURI($this->getIdentifierInNamespace());
                break;
            case 1: //Entity identifier + label
                $label = $this->getClassVersionForDisplay()->getStandardLabel(); // classVersionForDisplay renverra l'ongoing par défaut sans paramètres
                // Remplacer les espaces par des underscores
                $label = StringUtils::deleteAccents($label);
                $label = str_replace(array('"', "'"), '', $label);
                $newIdentifierInUri = str_replace(' ', '_', $this->getIdentifierInNamespace() . ' ' . $label);
                $this->setIdentifierInURI($newIdentifierInUri);
                break;
            case 2: //Camel Case
                $label = $this->getClassVersionForDisplay()->getStandardLabel();
                $label = StringUtils::deleteAccents($label);
                $label = str_replace(array('"', "'"), '', $label);
                $words = preg_split('/[^a-zA-Z0-9]+/', $label);
                $camelCaseString = implode('', array_map('ucfirst', $words));
                $newIdentifierInUri = lcfirst($camelCaseString);
                $this->setIdentifierInURI($newIdentifierInUri);
                break;
        }
    }
}