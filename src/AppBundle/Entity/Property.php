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
     * @Assert\NotBlank()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\TextProperty", mappedBy="property")
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
     * @ORM\ManyToMany(targetEntity="OntoNamespace",  inversedBy="Property", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(schema="che", name="associates_namespace",
     *      joinColumns={@ORM\JoinColumn(name="fk_property", referencedColumnName="pk_property")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="fk_namespace", referencedColumnName="pk_namespace")}
     *      )
     */
    private $namespaces;

    /**
     * @Assert\NotBlank()
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Label", mappedBy="property")
     * @ORM\OrderBy({"languageIsoCode" = "ASC"})
     */
    private $labels;

    public function __construct()
    {
        $this->namespaces = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->textProperties = new ArrayCollection();
        $this->profiles = new ArrayCollection();
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
     * @return string a human readable identification of the object
     */
    public function getObjectIdentification()
    {
        return $this->identifierInNamespace;
    }

}