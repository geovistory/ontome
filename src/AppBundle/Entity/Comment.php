<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 04/10/2018
 * Time: 13:40
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Comment
 * @ORM\Entity
 * @ORM\Table(schema="che", name="comment")
 */
class Comment
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_comment")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="text", nullable=false)
     */
    private $comment;

    /**
     * @ORM\OneToMany(targetEntity="Comment", mappedBy="commentedComment")
     */
    private $answers;

    /**
     * @ORM\ManyToOne(targetEntity="Comment", inversedBy="commentedComment")
     * @ORM\JoinColumn(name="fk_commented_comment", referencedColumnName="pk_comment")
     */
    private $commentedComment;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $viewedBy = [];

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="comments")
     * @ORM\JoinColumn(name="fk_class", referencedColumnName="pk_class")
     */
    private $class;

    /**
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="comments")
     * @ORM\JoinColumn(name="fk_property", referencedColumnName="pk_property")
     */
    private $property;

    /**
     * @ORM\ManyToOne(targetEntity="ClassAssociation", inversedBy="comments")
     * @ORM\JoinColumn(name="fk_is_subclass_of", referencedColumnName="pk_is_subclass_of")
     */
    private $classAssociation;

    /**
     * @ORM\ManyToOne(targetEntity="PropertyAssociation", inversedBy="comments")
     * @ORM\JoinColumn(name="fk_is_subproperty_of", referencedColumnName="pk_is_subproperty_of")
     */
    private $propertyAssociation;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\TextProperty", inversedBy="comments")
     * @ORM\JoinColumn(name="fk_text_property", referencedColumnName="pk_text_property")
     */
    private $textProperty;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Label", inversedBy="comments")
     * @ORM\JoinColumn(name="fk_label", referencedColumnName="pk_label")
     */
    private $label;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\OntoNamespace", inversedBy="comments")
     * @ORM\JoinColumn(name="fk_namespace", referencedColumnName="pk_namespace")
     */
    private $namespace;

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
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @return mixed
     */
    public function getAnswers()
    {
        return $this->answers;
    }

    public function getViewedBy()
    {
        return $this->viewedBy;
    }

    /**
     * @return mixed
     */
    public function getCommentedComment()
    {
        return $this->commentedComment;
    }

    /**
     * @return OntoClass
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return Property
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @return ClassAssociation
     */
    public function getClassAssociation()
    {
        return $this->classAssociation;
    }

    /**
     * @return PropertyAssociation
     */
    public function getPropertyAssociation()
    {
        return $this->propertyAssociation;
    }

    /**
     * @return TextProperty
     */
    public function getTextProperty()
    {
        return $this->textProperty;
    }

    /**
     * @return Label
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return OntoNamespace
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return User
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * @return User
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
     * @param mixed $comment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * @param mixed $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * @param mixed $property
     */
    public function setProperty($property)
    {
        $this->property = $property;
    }

    /**
     * @param mixed $classAssociation
     */
    public function setClassAssociation($classAssociation)
    {
        $this->classAssociation = $classAssociation;
    }

    /**
     * @param mixed $propertyAssociation
     */
    public function setPropertyAssociation($propertyAssociation)
    {
        $this->propertyAssociation = $propertyAssociation;
    }

    /**
     * @param mixed $textProperty
     */
    public function setTextProperty($textProperty)
    {
        $this->textProperty = $textProperty;
    }

    /**
     * @param mixed $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @param mixed $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
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
     * @param mixed $roles
     */
    public function setViewedBy(array $viewedBy)
    {
        $this->viewedBy = $viewedBy;
    }


}