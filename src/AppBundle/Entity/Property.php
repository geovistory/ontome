<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/07/2017
 * Time: 12:00
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Class Property
 * @ORM\Entity(repositoryClass="AppBundle\Repository\PropertyRepository")
 * @ORM\Table(schema="che", name="property")
 */
class Property
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_property")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $identifierInNamespace;

    /**
     * @ORM\Column(type="text")
     */
    private $importerXmlField;

    /**
     * @ORM\Column(type="text")
     */
    private $importerTextField;

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
     * @ORM\Column(type="text")
     */
    private $notes;

    /**
     * @var boolean
     * A non-persisted field that's used to know if the $identifierInNamespace field is manually set by the user
     * or automatically set by a trigger in the database     *
     */
    private $isManualIdentifier;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\PropertyVersion", mappedBy="property", cascade={"persist"})
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $propertyVersions;

    /**
     * @Assert\Valid()
     * @Assert\NotNull()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="property", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $textProperties;

    /**
     * @Assert\Valid()
     * @Assert\NotNull()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Label", mappedBy="property", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $labels;

    /**
     * @ORM\ManyToMany(targetEntity="Profile",  inversedBy="Property", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(schema="che", name="associates_profile",
     *      joinColumns={@ORM\JoinColumn(name="fk_property", referencedColumnName="pk_property")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_profile", referencedColumnName="pk_profile")}
     *      )
     */
    private $profiles;

    /**
     * @Assert\Valid()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\ProfileAssociation", mappedBy="property", cascade={"persist"})
     * @ORM\OrderBy({"systemType" = "ASC"})
     */
    private $profileAssociations;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Comment", mappedBy="property")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $comments;

    /**
    * @ORM\OneToMany(targetEntity="PropertyAssociation", mappedBy="childProperty")
    */
    private $childPropertyAssociations;

    /**
     * @ORM\OneToMany(targetEntity="PropertyAssociation", mappedBy="parentProperty")
     */
    private $parentPropertyAssociations;

    /**
     * @ORM\OneToMany(targetEntity="EntityAssociation", mappedBy="sourceProperty")
     */
    private $sourceEntityAssociations;

    /**
     * @ORM\OneToMany(targetEntity="EntityAssociation", mappedBy="targetProperty")
     */
    private $targetEntityAssociations;

    /**
     * @ORM\ManyToMany(targetEntity="OntoNamespace",  inversedBy="Property", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(schema="che", name="property_version",
     *      joinColumns={@ORM\JoinColumn(name="fk_property", referencedColumnName="pk_property")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_namespace_for_version", referencedColumnName="pk_namespace")}
     *      )
     */
    private $namespaces;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_ongoing_namespace", referencedColumnName="pk_namespace", nullable=true)
     */
    private $ongoingNamespace;

    public function __construct()
    {
        $this->namespaces = new ArrayCollection();
        $this->propertyVersions = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->textProperties = new ArrayCollection();
        $this->profiles = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->profileAssociations = new ArrayCollection();
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
            $rootNamespace = $this->getPropertyVersionForDisplay()->getNamespaceForVersion()->getTopLevelNamespace();
            foreach ($rootNamespace->getChildVersions() as $namespace) {
                foreach ($namespace->getProperties() as $property) {
                    if ($property->identifierInNamespace == $this->identifierInNamespace) {
                        $context->buildViolation('The identifier must be unique. Please enter another one.')
                            ->atPath('identifierInNamespace')
                            ->addViolation();
                        break;
                    }
                }
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
    public function getImporterXmlField()
    {
        return $this->importerXmlField;
    }

    /**
     * @return bool
     */
    public function isManualIdentifier()
    {
        return $this->isManualIdentifier;
    }

    /**
     * @return mixed
     */
    public function getPropertyVersions()
    {
        return $this->propertyVersions;
    }

    /**
     * @return mixed
     */
    public function getImporterTextField()
    {
        return $this->importerTextField;
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
     * @return OntoNamespace
     */
    public function getOngoingNamespace()
    {
        return $this->ongoingNamespace;
    }

    /**
     * @return ArrayCollection|PropertyAssociation[]
     */
    public function getParentPropertyAssociations()
    {
        return $this->parentPropertyAssociations;
    }

    /**
     * @return ArrayCollection|PropertyAssociation[]
     */
    public function getChildPropertyAssociations()
    {
        return $this->childPropertyAssociations;
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
     * @param bool $isManualIdentifier
     */
    public function setIsManualIdentifier($isManualIdentifier)
    {
        $this->isManualIdentifier = $isManualIdentifier;
    }

    /**
     * @param mixed $propertyVersions
     */
    public function setPropertyVersions($propertyVersions)
    {
        $this->propertyVersions = $propertyVersions;
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
     * @param mixed $textProperties
     */
    public function setTextProperties($textProperties)
    {
        $this->textProperties = $textProperties;
    }

    /**
     * @param mixed $profiles
     */
    public function setProfiles($profiles)
    {
        $this->profiles = $profiles;
    }

    /**
     * @param mixed $namespaces
     */
    public function setNamespaces($namespaces)
    {
        $this->namespaces = $namespaces;
    }

    /**
     * @param mixed $labels
     */
    public function setLabels($labels)
    {
        $this->labels = $labels;
    }

    /**
     * @param PropertyVersion $propertyVersion
     */
    public function addPropertyVersion(PropertyVersion $propertyVersion)
    {
        if ($this->propertyVersions->contains($propertyVersion)) {
            return;
        }
        $this->propertyVersions[] = $propertyVersion;
        // needed to update the owning side of the relationship!
        $propertyVersion->setProperty($this);
    }

    public function addTextProperty(TextProperty $textProperty)
    {
        if ($this->textProperties->contains($textProperty)) {
            return;
        }
        $this->textProperties[] = $textProperty;
        // needed to update the owning side of the relationship!
        $textProperty->setProperty($this);
    }

    public function addLabel(Label $label)
    {
        if ($this->labels->contains($label)) {
            return;
        }
        $this->labels[] = $label;
        // needed to update the owning side of the relationship!
        $label->setProperty($this);
    }

    public function addProfileAssociation(ProfileAssociation $profileAssociation)
    {
        if ($this->profileAssociations->contains($profileAssociation)) {
            return;
        }
        $this->profileAssociations[] = $profileAssociation;
        // needed to update the owning side of the relationship!
        $profileAssociation->setProperty($this);
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

    public function getEntityAssociations()
    {
        return array_merge($this->getSourceEntityAssociations()->toArray(), $this->getTargetEntityAssociations()->toArray());
    }

    /**
     * @param OntoNamespace|null $namespace
     * @return PropertyVersion the propertyVersion to be displayed
     */
    public function getPropertyVersionForDisplay(OntoNamespace $namespace=null)
    {
        $pvCollection = $this->getPropertyVersions();
        if(!is_null($namespace)){
            $pvCollection = $this->getPropertyVersions()->filter(function(PropertyVersion $propertyVersion) use ($namespace){
                return $propertyVersion->getNamespaceForVersion() === $namespace;
            });
        }
        else{
            if($pvCollection->count()>1){
                $pvCollection = $this->getPropertyVersions()->filter(function(PropertyVersion $propertyVersion) {
                    return $propertyVersion->getNamespaceForVersion()->getIsOngoing();
                });
            }
        }
        return $pvCollection->first();
    }

}