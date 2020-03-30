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
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\PropertyVersion", mappedBy="property")
     */
    private $propertyVersions;

    /**
     * @var boolean
     * A non-persisted field that's used to know if the $identifierInNamespace field is manually set by the user
     * or automatically set by a trigger in the database     *
     */
    private $isManualIdentifier;

    /**
     * @ORM\Column(type="text")
     */
    private $importerXmlField;

    /**
     * @ORM\Column(type="text")
     */
    private $importerTextField;

    /**
     * @ORM\Column(type="string")
     */
    private $standardLabel;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="propertiesAsDomain")
     * @ORM\JoinColumn(name="has_domain", referencedColumnName="pk_class", nullable=false)
     */
    private $domain;

    /**
     * @Assert\NotBlank()
     * @ORM\ManyToOne(targetEntity="OntoClass")
     * @ORM\JoinColumn(name="has_range", referencedColumnName="pk_class", nullable=false)
     */
    private $range;

    /**
     * @ORM\Column(type="smallint", name="domain_instances_min_quantifier")
     */
    private $domainMinQuantifier;

    /**
     * @ORM\Column(type="smallint", name="domain_instances_max_quantifier")
     */
    private $domainMaxQuantifier;

    /**
     * @ORM\Column(type="smallint", name="range_instances_min_quantifier")
     */
    private $rangeMinQuantifier;

    /**
     * @ORM\Column(type="smallint", name="range_instances_max_quantifier")
     */
    private $rangeMaxQuantifier;

    /**
     * @ORM\ManyToOne(targetEntity="Property")
     * @ORM\JoinColumn(name="fk_property_of_origin", referencedColumnName="pk_property")
     */
    private $propertyOfOrigin;

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
     * @Assert\NotNull()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="property", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $textProperties;

    /**
     * @ORM\ManyToMany(targetEntity="Profile",  inversedBy="Property", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(schema="che", name="associates_profile",
     *      joinColumns={@ORM\JoinColumn(name="fk_property", referencedColumnName="pk_property")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_profile", referencedColumnName="pk_profile")}
     *      )
     */
    private $profiles;

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
     * @ORM\JoinTable(schema="che", name="associates_namespace",
     *      joinColumns={@ORM\JoinColumn(name="fk_property", referencedColumnName="pk_property")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_namespace", referencedColumnName="pk_namespace")}
     *      )
     */
    private $namespaces;

    /**
     * @Assert\Valid()
     * @Assert\NotNull()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Label", mappedBy="property", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $labels;

    /**
     * @Assert\Valid()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\ProfileAssociation", mappedBy="property", cascade={"persist"})
     * @ORM\OrderBy({"systemType" = "ASC"})
     */
    private $profileAssociations;

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
            foreach ($this->getNamespaces() as $namespace) {
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
     * @return OntoClass
     */
    public function getDomain()
    {
        return $this->domain;
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
    public function getPropertyOfOrigin()
    {
        return $this->propertyOfOrigin;
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
     * @return OntoClass
     */
    public function getRange()
    {
        return $this->range;
    }

    /**
     * @return integer
     */
    public function getDomainMinQuantifier()
    {
        return $this->domainMinQuantifier;
    }

    /**
     * @return integer
     */
    public function getDomainMaxQuantifier()
    {
        return $this->domainMaxQuantifier;
    }

    /**
     * @return integer
     */
    public function getRangeMinQuantifier()
    {
        return $this->rangeMinQuantifier;
    }

    /**
     * @return integer
     */
    public function getRangeMaxQuantifier()
    {
        return $this->rangeMaxQuantifier;
    }

    /**
     * @return string the formatted quantifiers string
     */
    public function getQuantifiers()
    {
        $s = null;

        if(!is_null($this->domainMinQuantifier)&&!is_null($this->domainMaxQuantifier)&&!is_null($this->rangeMinQuantifier)&&!is_null($this->rangeMaxQuantifier)){
            if($this->domainMinQuantifier == -1)
                $domainMinQ = 'n';
            else $domainMinQ = $this->domainMinQuantifier;

            if($this->domainMaxQuantifier == -1)
                $domainMaxQ = 'n';
            else $domainMaxQ = $this->domainMaxQuantifier;

            if($this->rangeMinQuantifier == -1)
                $rangeMinQ = 'n';
            else $rangeMinQ = $this->rangeMinQuantifier;

            if($this->rangeMaxQuantifier == -1)
                $rangeMaxQ = 'n';
            else $rangeMaxQ = $this->rangeMaxQuantifier;

            $s = $domainMinQ.','.$domainMaxQ.':'.$rangeMinQ.','.$rangeMaxQ;
        }

        return $s;

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
     * @param mixed $range
     */
    public function setRange($range)
    {
        $this->range = $range;
    }

    /**
     * @param mixed $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * @param mixed $domainMinQuantifier
     */
    public function setDomainMinQuantifier($domainMinQuantifier)
    {
        $this->domainMinQuantifier = $domainMinQuantifier;
    }

    /**
     * @param mixed $domainMaxQuantifier
     */
    public function setDomainMaxQuantifier($domainMaxQuantifier)
    {
        $this->domainMaxQuantifier = $domainMaxQuantifier;
    }

    /**
     * @param mixed $rangeMinQuantifier
     */
    public function setRangeMinQuantifier($rangeMinQuantifier)
    {
        $this->rangeMinQuantifier = $rangeMinQuantifier;
    }

    /**
     * @param mixed $rangeMaxQuantifier
     */
    public function setRangeMaxQuantifier($rangeMaxQuantifier)
    {
        $this->rangeMaxQuantifier = $rangeMaxQuantifier;
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

    public function addNamespace(OntoNamespace $namespace)
    {
        if ($this->namespaces->contains($namespace)) {
            return;
        }
        $this->namespaces[] = $namespace;
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

    public function getInvertedLabel()
    {
        if($this->getIdentifierInNamespace() === $this->getStandardLabel()){
            $s = $this->getIdentifierInNamespace();
        }
        else if(!is_null($this->getStandardLabel())) {
            $s = $this->getIdentifierInNamespace().' '.$this->getStandardLabel();
        }
        else $s = $this->getIdentifierInNamespace();
        return (string) $s;
    }

    public function getInvertedLabelWithoutInverseLabel()
    {
        if($this->getIdentifierInNamespace() === $this->getStandardLabel()){
            $s = $this->getIdentifierInNamespace();
        }
        else if(!is_null($this->getStandardLabel())) {
            $standardLabelWithoutInverseLabel = "";
            foreach($this->getLabels() as $label){
                if($label->getIsStandardLabelForLanguage() && $label->getLanguageIsoCode() == "en"){
                    $standardLabelWithoutInverseLabel = $label->getLabel();
                    break;
                }
            }
            $s = $this->getIdentifierInNamespace().' '.$standardLabelWithoutInverseLabel;
        }
        else $s = $this->getIdentifierInNamespace();
        return (string) $s;
    }

    public function __toString()
    {
        if($this->getIdentifierInNamespace() === explode(' (',$this->getStandardLabel())[0]){
            $s = $this->getStandardLabel();
        }
        else if(!is_null($this->getStandardLabel())) {
            $s = $this->getStandardLabel().' – '.$this->getIdentifierInNamespace();
        }
        else $s = $this->getIdentifierInNamespace();
        return (string) $s;
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

}