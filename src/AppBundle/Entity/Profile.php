<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 16/11/2017
 * Time: 11:34
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Profile
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ProfileRepository")
 * @ORM\Table(schema="che", name="profile")
 */
class Profile
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_profile")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $standardLabel;

    /**
     * @ORM\Column(type="datetime")
     */
    private $startDate;

    /**
     * @ORM\Column(type="datetime")
     */
    private $endDate;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isOngoing;

    /**
     * @ORM\ManyToOne(targetEntity="Profile",  inversedBy="childProfiles")
     * @ORM\JoinColumn(name="fk_is_subprofile_of", referencedColumnName="pk_profile", nullable=true)
     */
    private $parentProfile;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Project", inversedBy="ownedProfiles")
     * @ORM\JoinColumn(name="fk_project_of_belonging", referencedColumnName="pk_project")
     */
    private $projectOfBelonging;

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
     * @ORM\OneToMany(targetEntity="Profile", mappedBy="parentProfile")
     */
    private $childProfiles;

    /**
     * @Assert\Valid()
     * @Assert\NotNull()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="profile", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $textProperties;

    /**
     * @Assert\Valid()
     * @Assert\NotNull()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Label", mappedBy="profile", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $labels;

    /**
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\OntoClass",  inversedBy="profiles", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(schema="che", name="associates_profile",
     *      joinColumns={@ORM\JoinColumn(name="fk_profile", referencedColumnName="pk_profile")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_class", referencedColumnName="pk_class")}
     *      )
     */
    private $classes;

    /**
     * @ORM\ManyToMany(targetEntity="Property", mappedBy="profiles")
     * @ORM\OrderBy({"identifierInNamespace" = "ASC"})
     */
    private $properties;

    /**
     * @ORM\ManyToMany(targetEntity="Project", mappedBy="profiles")
     */
    private $projects;

    /**
     * @ORM\ManyToMany(targetEntity="OntoNamespace",  inversedBy="profiles", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(schema="che", name="associates_referenced_namespace",
     *      joinColumns={@ORM\JoinColumn(name="fk_profile", referencedColumnName="pk_profile")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_referenced_namespace", referencedColumnName="pk_namespace")}
     *      )
     */
    private $namespaces;

    /**
     * @Assert\Valid()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\ProfileAssociation", mappedBy="profile", cascade={"persist"})
     * @ORM\OrderBy({"systemType" = "ASC"})
     */
    private $profileAssociations;

    public function __construct()
    {
        $this->childProfiles = new ArrayCollection();
        $this->textProperties = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->namespaces = new ArrayCollection();
        $this->classes = new ArrayCollection();
        $this->profileAssociations = new ArrayCollection();
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
     * @return mixed
     */
    public function getisOngoing()
    {
        return $this->isOngoing;
    }

    /**
     * @return mixed
     */
    public function getParentProfile()
    {
        return $this->parentProfile;
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
     * @return ArrayCollection|Profile[]
     */
    public function getChildProfiles()
    {
        return $this->childProfiles;
    }

    /**
     * @return ArrayCollection|TextProperty[]
     */
    public function getTextProperties()
    {
        return $this->textProperties;
    }

    /**
     * @return ArrayCollection|Label[]
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * @return mixed
     */
    public function getProjectOfBelonging()
    {
        return $this->projectOfBelonging;
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
     * @return ArrayCollection|Project[]
     */
    public function getProjects()
    {
        return $this->projects;
    }

    /**
     * @return ArrayCollection|OntoNamespace[]
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * @return string a human readable identification of the object
     */
    public function getObjectIdentification()
    {
        return $this->standardLabel;
    }

    /**
     * @param mixed $textProperties
     */
    public function setTextProperties($textProperties)
    {
        $this->textProperties = $textProperties;
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

    public function addClass(OntoClass $class)
    {
        if ($this->classes->contains($class)) {
            return;
        }
        $this->classes[] = $class;
    }

    public function removeNamespace(OntoNamespace $namespace)
    {
        $this->namespaces->removeElement($namespace);
    }

    public function removeClass(OntoClass $class)
    {
        $this->classes->removeElement($class);
    }

    public function __toString()
    {
        return $this->standardLabel;
    }
}