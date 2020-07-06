<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 09/01/2018
 * Time: 14:54
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ClassAssociation
 * @ORM\Entity
 * @ORM\Table(schema="che", name="is_subclass_of")
 * @UniqueEntity(
 *     fields={"childClass", "parentClass"},
 *     message="This parent class is already associated with this child class"
 * )
 */
class ClassAssociation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_is_subclass_of")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="childClassAssociations")
     * @ORM\JoinColumn(name="is_child_class", referencedColumnName="pk_class")
     */
    private $childClass;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_child_class_namespace", referencedColumnName="pk_namespace", nullable=false)
     */
    private $childClassNamespace;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="parentClassAssociations")
     * @ORM\JoinColumn(name="is_parent_class", referencedColumnName="pk_class")
     */
    private $parentClass;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_child_class_namespace", referencedColumnName="pk_namespace", nullable=false)
     */
    private $parentClassNamespace;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace", inversedBy="classAssociationVersions")
     * @ORM\JoinColumn(name="fk_namespace_for_version", referencedColumnName="pk_namespace", nullable=false)
     */
    private $namespaceForVersion;

    /**
     * @ORM\Column(type="text")
     */
    private $notes;

    /**
     * @Assert\NotNull()
     * @Assert\Valid()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="classAssociation", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $textProperties;

    /**
     * @ORM\ManyToMany(targetEntity="OntoNamespace",  inversedBy="OntoClass", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(schema="che", name="is_subclass_of",
     *      joinColumns={@ORM\JoinColumn(name="fk_is_subclass_of", referencedColumnName="pk_is_subclass_of")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_namespace_for_version", referencedColumnName="pk_namespace")}
     *      )
     */
    private $namespaces;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Comment", mappedBy="classAssociation")
     * @ORM\OrderBy({"creationTime" = "DESC"})
     */
    private $comments;

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
     * ClassAssociation constructor.
     */
    public function __construct()
    {
        $this->textProperties = new ArrayCollection();
        $this->namespaces = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return OntoNamespace
     */
    public function getNamespaceForVersion()
    {
        return $this->namespaceForVersion;
    }

    /**
     * @return mixed
     */
    public function getChildClass()
    {
        return $this->childClass;
    }

    /**
     * @Assert\NotBlank()
     * @return mixed
     */
    public function getParentClass()
    {
        return $this->parentClass;
    }

    /**
     * @return mixed
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * @return ArrayCollection|TextProperty[]
     */
    public function getTextProperties()
    {
        return $this->textProperties;
    }

    /**
     * @return ArrayCollection|OntoNamespace[]
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * @return ArrayCollection|Comment[]
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * @return string a human readable identification of the object
     */
    public function getObjectIdentification()
    {
        return 'Class association n°'.$this->id;
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
     * @return mixed
     */
    public function getChildClassNamespace()
    {
        return $this->childClassNamespace;
    }

    /**
     * @return mixed
     */
    public function getParentClassNamespace()
    {
        return $this->parentClassNamespace;
    }

    /**
     * @param mixed $namespaceForVersion
     */
    public function setNamespaceForVersion($namespaceForVersion)
    {
        $this->namespaceForVersion = $namespaceForVersion;
    }

    /**
     * @param mixed $childClass
     */
    public function setChildClass($childClass)
    {
        $this->childClass = $childClass;
    }

    /**
     * @param mixed $textProperties
     */
    public function setTextProperties($textProperties)
    {
        $this->textProperties = $textProperties;
    }

    /**
     * @param mixed $namespace
     */
    public function setNamespaces($namespaces)
    {
        $this->namespaces = $namespaces;
    }

    /**
     * @param mixed $parentClass
     */
    public function setParentClass($parentClass)
    {
        $this->parentClass = $parentClass;
    }

    /**
     * @param mixed $notes
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
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
     * @param mixed $childClassNamespace
     */
    public function setChildClassNamespace($childClassNamespace)
    {
        $this->childClassNamespace = $childClassNamespace;
    }

    /**
     * @param mixed $parentClassNamespace
     */
    public function setParentClassNamespace($parentClassNamespace)
    {
        $this->parentClassNamespace = $parentClassNamespace;
    }

    public function addTextProperty(TextProperty $textProperty)
    {
        if ($this->textProperties->contains($textProperty)) {
            return;
        }
        $this->textProperties[] = $textProperty;
        // needed to update the owning side of the relationship!
        $textProperty->setClassAssociation($this);
    }

    public function addNamespace(OntoNamespace $namespace)
    {
        if ($this->namespaces->contains($namespace)) {
            return;
        }
        $this->namespaces[] = $namespace;
        // needed to update the owning side of the relationship!
        $namespace->addClassAssociation($this);
    }

    public function __toString()
    {
        return (string) $this->childClass->getClassVersionForDisplay().': parent class association';
    }

}