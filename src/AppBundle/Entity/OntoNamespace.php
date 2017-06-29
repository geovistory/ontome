<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 20/06/2017
 * Time: 10:26
 */

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class OntoClass
 * @ORM\Entity
 * @ORM\Table(schema="che", name="namespace")
 */
class OntoNamespace
{
    /*
pk_namespace serial NOT NULL,
namespace_uri text,
notes text,
importer_integer integer,
fk_is_version_of integer,*/

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_namespace")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     * @Assert\Url()
     * @ORM\Column(type="text", nullable=false, unique=true)
     */
    private $namespaceURI;

    /**
     * @ORM\Column(type="integer")
     */
    private $importerInteger;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_is_version_of", referencedColumnName="pk_namespace", nullable=true)
     */
    private $referencedVersion;

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
     * @ORM\ManyToMany(targetEntity="OntoClass", mappedBy="namespaces")
     * @ORM\OrderBy({"identifierInNamespace" = "ASC"})
     */
    private $classes;

    public function __construct()
    {
        $this->classes = new ArrayCollection();
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
    public function getNamespaceURI()
    {
        return $this->namespaceURI;
    }

    /**
     * @return mixed
     */
    public function getImporterInteger()
    {
        return $this->importerInteger;
    }

    /**
     * @return mixed
     */
    public function getReferencedVersion()
    {
        return $this->referencedVersion;
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
     * @return ArrayCollection|OntoClass[]
     */
    public function getClasses()
    {
        return $this->classes;
    }


}