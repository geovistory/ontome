<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 09/06/2017
 * Time: 16:29
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
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
     * @ORM\Column(type="string")
     */
    private $identifierInNamespace;

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
     * @ORM\ManyToMany(targetEntity="OntoNamespace",  inversedBy="OntoClass", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(schema="che", name="associates_namespace",
     *      joinColumns={@ORM\JoinColumn(name="fk_class", referencedColumnName="pk_class")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_namespace", referencedColumnName="pk_namespace")}
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
    * @ORM\OrderBy({"languageIsoCode" = "ASC"})
    */
    private $textProperties;

    /**
     * @ORM\ManyToMany(targetEntity="Profile",  inversedBy="OntoClass", fetch="EXTRA_LAZY")
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
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_ongoing_namespace", referencedColumnName="pk_namespace", nullable=true)
     */
    private $ongoingNamespace;

    /**
     * @ORM\OneToMany(targetEntity="Property", mappedBy="domain", cascade={"persist"})
     */
    private $propertiesAsDomain;

    private $propertiesAsRange;

    public function __construct()
    {
        $this->namespaces = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->textProperties = new ArrayCollection();
        $this->profiles = new ArrayCollection();
        $this->parentClassAssociations = new ArrayCollection();
        $this->childClassAssociations = new ArrayCollection();
        $this->propertiesAsDomain = new ArrayCollection();
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
            foreach ($this->getNamespaces() as $namespace) {
                foreach ($namespace->getClasses() as $class) {
                    if ($class->identifierInNamespace === $this->identifierInNamespace) {
                        $context->buildViolation('The identifier must be unique. Please choose another one.')
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
     * @return bool
     */
    public function isManualIdentifier()
    {
        return $this->isManualIdentifier;
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
        $topLevelNamespace = null;
        foreach($this->getNamespaces() as $namespace)
        {
            $topLevelNamespace = $namespace->getTopLevelNamespace();
            break;
        }
        return $topLevelNamespace;
    }

    /**
     * @return Property
     */
    public function getPropertiesAsDomain()
    {
        return $this->propertiesAsDomain;
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
     * @param mixed $propertiesAsDomain
     */
    public function setPropertiesAsDomain($propertiesAsDomain)
    {
        $this->propertiesAsDomain = $propertiesAsDomain;
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

    public function __toString()
    {
        if($this->getIdentifierInNamespace() === $this->getStandardLabel()){
            $s = $this->getIdentifierInNamespace();
        }
        else if(!is_null($this->getStandardLabel())) {
            $s = $this->getStandardLabel().' – '.$this->getIdentifierInNamespace();
        }
        else $s = $this->getIdentifierInNamespace();
        return (string) $s;
    }

}