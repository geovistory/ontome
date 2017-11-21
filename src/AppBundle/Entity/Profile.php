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
 * @ORM\Entity
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
     * @Assert\NotBlank()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="profile")
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $textProperties;

    /**
     * @Assert\NotBlank()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Label", mappedBy="profile")
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $labels;

    /**
     * @ORM\ManyToMany(targetEntity="OntoClass", mappedBy="profiles")
     * @ORM\OrderBy({"identifierInNamespace" = "ASC"})
     */
    private $classes;

    /**
     * @ORM\ManyToMany(targetEntity="Property", mappedBy="profiles")
     * @ORM\OrderBy({"identifierInNamespace" = "ASC"})
     */
    private $properties;

    /**
     * Profile constructor.
     * @param $childProfiles
     * @param $textProperties
     * @param $labels
     */
    public function __construct($childProfiles, $textProperties, $labels)
    {
        $this->childProfiles = $childProfiles;
        $this->textProperties = $textProperties;
        $this->labels = $labels;
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


}