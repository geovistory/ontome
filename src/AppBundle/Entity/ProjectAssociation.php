<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 24/10/2019
 * Time: 13:37
 */

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class ProjectAssociation
 * @ORM\Entity
 * @ORM\Table(schema="che", name="associates_project")
 */
class ProjectAssociation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_associates_project")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Project", inversedBy="projectAssociations")
     * @ORM\JoinColumn(name="fk_project", referencedColumnName="pk_project", nullable=false)
     */
    private $project;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Label")
     * @ORM\JoinColumn(name="fk_label", referencedColumnName="pk_label", nullable=true)
     */
    private $label;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\OntoNamespace", inversedBy="projectAssociations")
     * @ORM\JoinColumn(name="fk_namespace", referencedColumnName="pk_namespace", nullable=true)
     */
    private $namespace;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\TextProperty")
     * @ORM\JoinColumn(name="fk_text_property", referencedColumnName="pk_text_property", nullable=true)
     */
    private $textProperty;

    /**
     * @ORM\ManyToOne(targetEntity="SystemType", inversedBy="projectAssociations")
     * @ORM\JoinColumn(name="fk_system_type", referencedColumnName="pk_system_type", nullable=false)
     */
    private $systemType;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Profile")
     * @ORM\JoinColumn(name="fk_profile", referencedColumnName="pk_profile", nullable=false)
     */
    private $profile;

    /**
     * @ORM\Column(type="text")
     */
    private $notes;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="creator", referencedColumnName="pk_user", nullable=false)
     */
    private $creator;

    /**
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
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @return mixed
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return SystemType
     */
    public function getSystemType()
    {
        return $this->systemType;
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
     * @param mixed $project
     */
    public function setProject($project)
    {
        $this->project = $project;
    }

    /**
     * @param mixed $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @param mixed $systemType
     */
    public function setSystemType($systemType)
    {
        $this->systemType = $systemType;
    }

    /**
     * @param mixed $notes
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
    }

    /**
     * @param User $creator
     */
    public function setCreator($creator)
    {
        $this->creator = $creator;
    }

    /**
     * @param User $modifier
     */
    public function setModifier($modifier)
    {
        $this->modifier = $modifier;
    }

    /**
     * @param \DateTime $creationTime
     */
    public function setCreationTime($creationTime)
    {
        $this->creationTime = $creationTime;
    }

    /**
     * @param \DateTime $modificationTime
     */
    public function setModificationTime($modificationTime)
    {
        $this->modificationTime = $modificationTime;
    }

    public function __toString()
    {
        return (string) $this->id;
    }


}