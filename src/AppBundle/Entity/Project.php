<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 19/07/2017
 * Time: 16:19
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Project
 * @ORM\Entity
 * @ORM\Table(schema="che", name="project")
 */
class Project
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_project")
     */
    private $id;

    /**
     * @ORM\Column(type="date")
     */
    private $startDate;

    /**
     * @ORM\Column(type="date")
     */
    private $endDate;

    /**
     * @ORM\Column(type="text")
     */
    private $notes;

    /**
     * @ORM\Column(type="text")
     */
    private $standardLabel;

    /**
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="childProjects")
     * @ORM\JoinColumn(name="fk_is_subproject_of", referencedColumnName="pk_project", nullable=true)
     */
    private $parentProject;

    /**
     * @ORM\OneToMany(targetEntity="Profile", mappedBy="projectOfBelonging")
     */
    private $ownedProfiles;

    /**
     * @ORM\OneToMany(targetEntity="OntoNamespace", mappedBy="projectForTopLevelNamespace")
     */
    private $managedNamespaces;

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
     * @ORM\ManyToMany(targetEntity="Profile",  inversedBy="Project", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(schema="che", name="associates_project",
     *      joinColumns={@ORM\JoinColumn(name="fk_project", referencedColumnName="pk_project")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_profile", referencedColumnName="pk_profile")}
     *      )
     */
    private $profiles;

    /**
     * @Assert\NotBlank()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="project")
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $textProperties;

    /**
     * @Assert\Valid()
     * @Assert\NotBlank()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Label", mappedBy="project", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $labels;

    /**
     * @ORM\OneToMany(targetEntity="Project", mappedBy="parentProject")
     */
    private $childProjects;

    /**
     * @Assert\NotBlank()
     * @ORM\OneToMany(targetEntity="UserProjectAssociation", mappedBy="project")
     */
    private $userProjectAssociations;

    public function __construct()
    {
        $this->ownedProfiles = new ArrayCollection();
        $this->managedNamespaces = new ArrayCollection();
        $this->profiles = new ArrayCollection();
        $this->textProperties = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->childProjects = new ArrayCollection();
        $this->userProjectAssociations = new ArrayCollection();
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
    public function getNotes()
    {
        return $this->notes;
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
    public function getParentProject()
    {
        return $this->parentProject;
    }

    /**
     * @return ArrayCollection|Project[]
     */
    public function getChildProjects()
    {
        return $this->childProjects;
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
     * @return ArrayCollection|Profile[]
     */
    public function getOwnedProfiles()
    {
        return $this->ownedProfiles;
    }

    /**
     * @return ArrayCollection|OntoNamespace[]
     */
    public function getManagedNamespaces()
    {
        return $this->managedNamespaces;
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
     * @return ArrayCollection|UserProjectAssociation
     */
    public function getUserProjectAssociations()
    {
        return $this->userProjectAssociations;
    }

    /**
     * @return string a human readable identification of the object
     */
    public function getObjectIdentification()
    {
        return $this->standardLabel;
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
     * @param mixed $labels
     */
    public function setLabels($labels)
    {
        $this->labels = $labels;
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

    public function addLabel(Label $label)
    {
        if ($this->labels->contains($label)) {
            return;
        }
        $this->labels[] = $label;
        // needed to update the owning side of the relationship!
        $label->setProject($this);
    }

    public function __toString()
    {
        $s = $this->standardLabel();
        return (string) $s;
    }
}