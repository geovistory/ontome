<?php
/**
 * Created by PhpStorm.
 * User: pc-alexandre-pro
 * Date: 07/05/2019
 * Time: 14:45
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class EntityAssociation
 * @ORM\Entity
 * @ORM\Table(name="che.entity_association")
 */
class EntityAssociation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_entity_association")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="sourceEntityAssociations")
     * @ORM\JoinColumn(name="fk_source_class", referencedColumnName="pk_class")
     */
    private $sourceClass;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="targetEntityAssociations")
     * @ORM\JoinColumn(name="fk_target_class", referencedColumnName="pk_class")
     */
    private $targetClass;

    /**
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="entityAssociations")
     * @ORM\JoinColumn(name="fk_source_property", referencedColumnName="pk_property")
     */
    private $sourceProperty;

    /**
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="entityAssociations")
     * @ORM\JoinColumn(name="fk_target_property", referencedColumnName="pk_property")
     */
    private $targetProperty;

    /**
     * @ORM\ManyToOne(targetEntity="SystemType", inversedBy="entityAssociations")
     * @ORM\JoinColumn(name="fk_system_type", referencedColumnName="pk_system_type")
     */
    private $systemType;

    /**
     * @Assert\NotNull()
     * @Assert\Valid()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="entityAssociation", cascade={"persist"})
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $textProperties;

    /**
     * @ORM\ManyToMany(targetEntity="OntoNamespace",  inversedBy="EntityAssociation", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(schema="che", name="associates_namespace",
     *      joinColumns={@ORM\JoinColumn(name="fk_entity_association", referencedColumnName="pk_entity_association")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_namespace", referencedColumnName="pk_namespace")}
     *      )
     */
    private $namespaces;

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
     * EntityAssociation constructor.
     */
    public function __construct()
    {
        $this->textProperties = new ArrayCollection();
        $this->namespaces = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * @param mixed $namespaces
     */
    public function setNamespaces($namespaces)
    {
        $this->namespaces = $namespaces;
    }



    /**
     * @return ArrayCollection|TextProperty[]
     */
    public function getTextProperties()
    {
        return $this->textProperties;
    }

    /**
     * @param mixed $textProperties
     */
    public function setTextProperties($textProperties)
    {
        $this->textProperties = $textProperties;
    }

    /**
     * @return mixed
     */
    public function getSystemType()
    {
        return $this->systemType;
    }

    /**
     * @param mixed $systemType
     */
    public function setSystemType($systemType)
    {
        $this->systemType = $systemType;
    }

    /**
     * @ORM\Column(type="boolean")
     */
    private $directed;

    /**
     * @param mixed $sourceClass
     */
    public function setSourceClass($sourceClass)
    {
        $this->sourceClass = $sourceClass;
    }

    /**
     * @param mixed $targetClass
     */
    public function setTargetClass($targetClass)
    {
        $this->targetClass = $targetClass;
    }

    /**
     * @param mixed $sourceProperty
     */
    public function setSourceProperty($sourceProperty)
    {
        $this->sourceProperty = $sourceProperty;
    }

    /**
     * @param mixed $targetProperty
     */
    public function setTargetProperty($targetProperty)
    {
        $this->targetProperty = $targetProperty;
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
    public function getSourceClass()
    {
        return $this->sourceClass;
    }

    /**
     * @return mixed
     */
    public function getTargetClass()
    {
        return $this->targetClass;
    }

    /**
     * @return mixed
     */
    public function getSourceProperty()
    {
        return $this->sourceProperty;
    }

    /**
     * @return mixed
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * @param mixed $creator
     */
    public function setCreator($creator)
    {
        $this->creator = $creator;
    }

    /**
     * @return mixed
     */
    public function getModifier()
    {
        return $this->modifier;
    }

    /**
     * @param mixed $modifier
     */
    public function setModifier($modifier)
    {
        $this->modifier = $modifier;
    }

    /**
     * @return mixed
     */
    public function getCreationTime()
    {
        return $this->creationTime;
    }

    /**
     * @param mixed $creationTime
     */
    public function setCreationTime($creationTime)
    {
        $this->creationTime = $creationTime;
    }

    /**
     * @return mixed
     */
    public function getModificationTime()
    {
        return $this->modificationTime;
    }

    /**
     * @param mixed $modificationTime
     */
    public function setModificationTime($modificationTime)
    {
        $this->modificationTime = $modificationTime;
    }

    /**
     * @return mixed
     */
    public function getTargetProperty()
    {
        return $this->targetProperty;
    }

    /**
     * @return mixed
     */
    public function getDirected()
    {
        return $this->directed;
    }

    /**
     * @param mixed $directed
     */
    public function setDirected($directed)
    {
        $this->directed = $directed;
    }

    public function addTextProperty(TextProperty $textProperty)
    {
        if ($this->textProperties->contains($textProperty)) {
            return;
        }
        $this->textProperties[] = $textProperty;
        // needed to update the owning side of the relationship!
        $textProperty->setEntityAssociation($this);
    }

    public function __toString()
    {
        return (string) $this->sourceClass.$this->targetClass;
    }

    public function inverseClasses()
    {
        if(!$this->directed)
        {
            $temp = $this->sourceClass;
            $this->sourceClass = $this->targetClass;
            $this->targetClass = $temp;
        }
    }

    public function inverseProperties()
    {
        if(!$this->directed)
        {
            $temp = $this->sourceProperty;
            $this->sourceProperty = $this->targetProperty;
            $this->targetProperty = $temp;
        }
    }

    public function inverseEntities()
    {
        if($this->getSourceClass() != null)
        {
            return $this->inverseClasses();
        }

        if($this->getSourceProperty() != null)
        {
            return $this->inverseProperties();
        }
    }

    public function getSource()
    {
        if($this->getSourceClass() != null)
        {
            return $this->getSourceClass();
        }

        if($this->getSourceProperty() != null)
        {
            return $this->getSourceProperty();
        }
    }

    public function getTarget()
    {
        if($this->getTargetClass() != null)
        {
            return $this->getTargetClass();
        }

        if($this->getTargetProperty() != null)
        {
            return $this->getTargetProperty();
        }
    }

    public function addNamespace(OntoNamespace $namespace)
    {
        if ($this->namespaces->contains($namespace)) {
            return;
        }
        $this->namespaces[] = $namespace;
    }
}