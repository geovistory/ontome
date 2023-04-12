<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 03/03/2020
 * Time: 16:29
 */

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class OntoClassVersion
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ClassVersionRepository")
 * @ORM\Table(schema="che", name="class_version")
 */
class OntoClassVersion
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_class_version")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $standardLabel;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="classVersions")
     * @ORM\JoinColumn(name="fk_class", referencedColumnName="pk_class")
     */
    private $class;

    /**
     * @ORM\ManyToOne(targetEntity="SystemType")
     * @ORM\JoinColumn(name="validation_status", referencedColumnName="pk_system_type")
     * @Assert\Type(type="AppBundle\Entity\SystemType")
     */
    private $validationStatus;

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
     * @ORM\ManyToOne(targetEntity="OntoNamespace", inversedBy="classVersions")
     * @ORM\JoinColumn(name="fk_namespace_for_version", referencedColumnName="pk_namespace", nullable=false)
     */
    private $namespaceForVersion;

    public function __construct()
    {

    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return OntoClass
     */
    public function getClass()
    {
        return $this->class;
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
    public function getValidationStatus()
    {
        return $this->validationStatus;
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
     * @return OntoNamespace
     */
    public function getNamespaceForVersion()
    {
        return $this->namespaceForVersion;
    }

    /**
     * @param mixed $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * @param mixed $namespaceForVersion
     */
    public function setNamespaceForVersion($namespaceForVersion)
    {
        $this->namespaceForVersion = $namespaceForVersion;
        $namespaceForVersion->addClassVersion($this);
    }

    /**
     * @param mixed $validationStatus
     */
    public function setValidationStatus($validationStatus)
    {
        $this->validationStatus = $validationStatus;
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
     * @param mixed $standardLabel
     */
    public function setStandardLabel($standardLabel)
    {
        $this->standardLabel = $standardLabel;
    }

    /**
     * Exemple de retour attendu : Activity - E7
     * Sauf si standardLabel est vide ou standardLabel égal Identifier
     * @return string
     */
    public function __toString()
    {
        // Si l'identifier in namespace de la classe est identique au standard label de classVersion, n'afficher que le standard label
        if($this->getClass()->getIdentifierInNamespace() === $this->getStandardLabel()){
            $s = $this->getStandardLabel();
        }
        // Sinon si le standard label n'est pas vide afficher les deux
        else if(!is_null($this->getStandardLabel())){
            $s = $this->getStandardLabel().' – '.$this->getClass()->getIdentifierInNamespace();
        }
        // Si le standard label est vide, n'afficher que l'identifier in namespace
        else{
            $s = $this->getClass()->getIdentifierInNamespace();
        }

        return (string) $s;
    }

    /**
     * Exemple de retour attendu : E7 Activity
     * Sauf si standardLabel est vide ou standardLabel égal Identifier
     * @return string
     */
    public function getInvertedLabel($withRootNamespacePrefix=false){
        if($this->getClass()->getIdentifierInNamespace() === $this->getStandardLabel()){
            $s = $this->getClass()->getIdentifierInNamespace();
        }
        else if(!is_null($this->getStandardLabel())){
            $s = $this->getClass()->getIdentifierInNamespace().' '.$this->getStandardLabel();
        }
        else $s = $this->getClass()->getIdentifierInNamespace();

        if($withRootNamespacePrefix){
            $rootNamespacePrefix = $this->getNamespaceForVersion()->getTopLevelNamespace()->getRootNamespacePrefix().':';
        }
        else{
            $rootNamespacePrefix = '';
        }
        return (string) $rootNamespacePrefix.$s;
    }

    /**
     * @return string
     * Générer l'URI
     */
    public function getURI(){
        $baseUri = $this->getNamespaceForVersion()->getTopLevelNamespace()->getNamespaceURI();

        $identifier = $this->getClass()->getIdentifierInURI();

        // Si la version est externe et qu'elle dispose de sa propre base URI
        if($this->getNamespaceForVersion()->getIsExternalNamespace() && !empty($this->getNamespaceForVersion()->getNamespaceURI())){
            $baseUri = $this->getNamespaceForVersion()->getNamespaceURI();
        }

        // Si la version est interne, utiliser identifier_in_namespace
        if(!$this->getNamespaceForVersion()->getIsExternalNamespace()){
            $identifier = $this->getClass()->getIdentifierInNamespace();
        }

        return $baseUri.$identifier;
    }

    /**
     * @return bool
     * Savoir si l'URI est atteignable. Afin de mettre ou non un lien pour Official URI
     */
    public function getIsLinkableURI()
    {
        if(!$this->getNamespaceForVersion()->getIsExternalNamespace()){
            return false; // Namespace interne, donc non linkable.
        }

        $ch = curl_init($this->getURI());
        curl_setopt($ch,CURLOPT_NOBODY, true);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch,CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch,CURLOPT_MAXREDIRS, 15);
        curl_setopt($ch,CURLOPT_TIMEOUT, 15);
        curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');

        if (isset($options['timeout'])) {
            $timeout = (int) $options['timeout'];
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        }

        curl_exec($ch);
        $returnedStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($returnedStatusCode == 0){print curl_error($ch);}
        curl_close($ch);

        if($returnedStatusCode >= 200 && $returnedStatusCode < 400){
            return true;
        }
        return false;
    }
}