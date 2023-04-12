<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 03/03/2020
 * Time: 12:00
 */

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class PropertyVersion
 * @ORM\Entity(repositoryClass="AppBundle\Repository\PropertyVersionRepository")
 * @ORM\Table(schema="che", name="property_version")
 */
class PropertyVersion
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="pk_property_version")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $standardLabel;

    /**
     * @ORM\ManyToOne(targetEntity="Property", inversedBy="propertyVersion")
     * @ORM\JoinColumn(name="fk_property", referencedColumnName="pk_property")
     */
    private $property;

    /**
     * @ORM\ManyToOne(targetEntity="OntoClass", inversedBy="propertiesAsDomain")
     * @ORM\JoinColumn(name="has_domain", referencedColumnName="pk_class", nullable=false)
     */
    private $domain;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_domain_namespace", referencedColumnName="pk_namespace", nullable=false)
     */
    private $domainNamespace;

    /**
     * @Assert\NotBlank()
     * @ORM\ManyToOne(targetEntity="OntoClass")
     * @ORM\JoinColumn(name="has_range", referencedColumnName="pk_class", nullable=false)
     */
    private $range;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace")
     * @ORM\JoinColumn(name="fk_range_namespace", referencedColumnName="pk_namespace", nullable=false)
     */
    private $rangeNamespace;

    /**
     * @ORM\Column(type="smallint", name="domain_instances_min_quantifier")
     */
    private $domainMinQuantifier;

    /**
     * @ORM\Column(type="smallint", name="domain_instances_max_quantifier")
     */
    private $domainMaxQuantifier;

    /**
     * @ORM\Column(type="smallint", name="range_instances_min_quantifier")
     */
    private $rangeMinQuantifier;

    /**
     * @ORM\Column(type="smallint", name="range_instances_max_quantifier")
     */
    private $rangeMaxQuantifier;

    /**
     * @ORM\ManyToOne(targetEntity="Property")
     * @ORM\JoinColumn(name="fk_property_of_origin", referencedColumnName="pk_property")
     */
    private $propertyOfOrigin;

    /**
     * @ORM\ManyToOne(targetEntity="OntoNamespace", inversedBy="propertyVersions")
     * @ORM\JoinColumn(name="fk_namespace_for_version", referencedColumnName="pk_namespace", nullable=false)
     */
    private $namespaceForVersion;

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
     * @return Property
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @return OntoNamespace
     */
    public function getNamespaceForVersion()
    {
        return $this->namespaceForVersion;
    }

    /**
     * @return OntoClass
     */
    public function getDomain()
    {
        return $this->domain;
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
    public function getPropertyOfOrigin()
    {
        return $this->propertyOfOrigin;
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
     * @return OntoClass
     */
    public function getRange()
    {
        return $this->range;
    }

    /**
     * @return integer
     */
    public function getDomainMinQuantifier()
    {
        return $this->domainMinQuantifier;
    }

    /**
     * @return integer
     */
    public function getDomainMaxQuantifier()
    {
        return $this->domainMaxQuantifier;
    }

    /**
     * @return integer
     */
    public function getRangeMinQuantifier()
    {
        return $this->rangeMinQuantifier;
    }

    /**
     * @return integer
     */
    public function getRangeMaxQuantifier()
    {
        return $this->rangeMaxQuantifier;
    }

    /**
     * @return mixed
     */
    public function getDomainNamespace()
    {
        return $this->domainNamespace;
    }

    /**
     * @return mixed
     */
    public function getRangeNamespace()
    {
        return $this->rangeNamespace;
    }

    /**
     * @return string the formatted quantifiers string (UML)
     */
    public function getQuantifiers()
    {
        $s = null;

        if(!is_null($this->domainMinQuantifier)&&!is_null($this->domainMaxQuantifier)&&!is_null($this->rangeMinQuantifier)&&!is_null($this->rangeMaxQuantifier)){
            if($this->domainMinQuantifier == -1)
                $domainMinQ = 'n';
            else $domainMinQ = $this->domainMinQuantifier;

            if($this->domainMaxQuantifier == -1)
                $domainMaxQ = 'n';
            else $domainMaxQ = $this->domainMaxQuantifier;

            if($this->rangeMinQuantifier == -1)
                $rangeMinQ = 'n';
            else $rangeMinQ = $this->rangeMinQuantifier;

            if($this->rangeMaxQuantifier == -1)
                $rangeMaxQ = 'n';
            else $rangeMaxQ = $this->rangeMaxQuantifier;

            $s = $domainMinQ.','.$domainMaxQ.':'.$rangeMinQ.','.$rangeMaxQ;
        }

        return $s;

    }

    /**
     * @return string the formatted quantifiers string (Merise)
     */
    public function getQuantifiersMerise()
    {
        $s = null;

        if(!is_null($this->domainMinQuantifier)&&!is_null($this->domainMaxQuantifier)&&!is_null($this->rangeMinQuantifier)&&!is_null($this->rangeMaxQuantifier)){
            if($this->domainMinQuantifier == -1)
                $domainMinQ = 'n';
            else $domainMinQ = $this->domainMinQuantifier;

            if($this->domainMaxQuantifier == -1)
                $domainMaxQ = 'n';
            else $domainMaxQ = $this->domainMaxQuantifier;

            if($this->rangeMinQuantifier == -1)
                $rangeMinQ = 'n';
            else $rangeMinQ = $this->rangeMinQuantifier;

            if($this->rangeMaxQuantifier == -1)
                $rangeMaxQ = 'n';
            else $rangeMaxQ = $this->rangeMaxQuantifier;

            $s = $rangeMinQ.','.$rangeMaxQ.':'.$domainMinQ.','.$domainMaxQ;
        }

        return $s;

    }

    /**
     * @return string the formatted quantifiers string
     */
    public function getQuantifiersString()
    {
        $s = null;

        if(!is_null($this->domainMinQuantifier)&&!is_null($this->domainMaxQuantifier)&&!is_null($this->rangeMinQuantifier)&&!is_null($this->rangeMaxQuantifier)){
            if($this->domainMaxQuantifier == -1 || $this->domainMaxQuantifier > 1){$s = "many";}
            if($this->domainMaxQuantifier == 1){$s = "one";}
            $s .= ' to ';
            if($this->rangeMaxQuantifier == -1 || $this->rangeMaxQuantifier > 1){$s .= "many";}
            if($this->rangeMaxQuantifier == 1){$s .= "one";}
            if($this->rangeMinQuantifier >= 1){$s .= ", necessary";}
            if($this->domainMinQuantifier == 1){$s .= ", dependant";}
        }
        return $s;
    }

    /**
     * @return mixed
     */
    public function getValidationStatus()
    {
        return $this->validationStatus;
    }

    /**
     * @param mixed $range
     */
    public function setRange($range)
    {
        $this->range = $range;
    }

    /**
     * @param mixed $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * @param mixed $domainMinQuantifier
     */
    public function setDomainMinQuantifier($domainMinQuantifier)
    {
        $this->domainMinQuantifier = $domainMinQuantifier;
    }

    /**
     * @param mixed $domainMaxQuantifier
     */
    public function setDomainMaxQuantifier($domainMaxQuantifier)
    {
        $this->domainMaxQuantifier = $domainMaxQuantifier;
    }

    /**
     * @param mixed $rangeMinQuantifier
     */
    public function setRangeMinQuantifier($rangeMinQuantifier)
    {
        $this->rangeMinQuantifier = $rangeMinQuantifier;
    }

    /**
     * @param mixed $rangeMaxQuantifier
     */
    public function setRangeMaxQuantifier($rangeMaxQuantifier)
    {
        $this->rangeMaxQuantifier = $rangeMaxQuantifier;
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
     * @param mixed $property
     */
    public function setProperty($property)
    {
        $this->property = $property;
    }

    /**
     * @param mixed $namespaceForVersion
     */
    public function setNamespaceForVersion($namespaceForVersion)
    {
        $this->namespaceForVersion = $namespaceForVersion;
        $namespaceForVersion->addPropertyVersion($this);
    }

    /**
     * @param mixed $domainNamespace
     */
    public function setDomainNamespace($domainNamespace)
    {
        $this->domainNamespace = $domainNamespace;
    }

    /**
     * @param mixed $rangeNamespace
     */
    public function setRangeNamespace($rangeNamespace)
    {
        $this->rangeNamespace = $rangeNamespace;
    }

    /**
     * @param mixed $standardLabel
     */
    public function setStandardLabel($standardLabel)
    {
        $this->standardLabel = $standardLabel;
    }

    /**
     * @param mixed $validationStatus
     */
    public function setValidationStatus($validationStatus)
    {
        $this->validationStatus = $validationStatus;
    }

    /**
     * Exemple de retour attendu : altered (was altered by) – O18
     * Autre exemple : added – P111
     * @return string
     */
    public function __toString()
    {
        if($this->getProperty()->getIdentifierInNamespace() === explode(' (',$this->getStandardLabel())[0]){
            $s = $this->getStandardLabel();
        }
        else if(!is_null($this->getStandardLabel())){
            $s = $this->getStandardLabel().' – '.$this->getProperty()->getIdentifierInNamespace();
        }
        else{
            $s = $this->getProperty()->getIdentifierInNamespace();
        }

        return (string) $s;
    }

    /**
     * Exemple de retour attendu : O18 altered (was altered by)
     * Autre exemple : P111 added
     * @return string
     */
    public function getInvertedLabel($withRootNamespacePrefix=false)
    {
        if($this->getProperty()->getIdentifierInNamespace() === explode(' (',$this->getStandardLabel())[0]){
            $s = $this->getProperty()->getIdentifierInNamespace();
        }
        else if(!is_null($this->getStandardLabel())) {
            $s = $this->getProperty()->getIdentifierInNamespace().' '.$this->getStandardLabel();
        }
        else $s = $this->getProperty()->getIdentifierInNamespace();

        if($withRootNamespacePrefix){
            $rootNamespacePrefix = $this->getNamespaceForVersion()->getTopLevelNamespace()->getRootNamespacePrefix().':';
        }
        else{
            $rootNamespacePrefix = '';
        }
        return (string) $rootNamespacePrefix.$s;
    }

    /**
     * Exemple de retour attendu : O18 altered
     * Autre exemple : P111 added
     * @return string
     */
    public function getInvertedLabelWithoutInverseLabel()
    {
        if($this->getProperty()->getIdentifierInNamespace() === $this->getStandardLabel()){
            $s = $this->getProperty()->getIdentifierInNamespace();
        }
        else if(!is_null($this->getStandardLabel())){
            $standardLabelWithoutInverseLabel = "";
            foreach($this->getProperty()->getLabels() as $label){
                if($label->getIsStandardLabelForLanguage() && $label->getLanguageIsoCode() == "en"){
                    $standardLabelWithoutInverseLabel = $label->getLabel();
                    break;
                }
            }
            $s = $this->getProperty()->getIdentifierInNamespace().' '.$standardLabelWithoutInverseLabel;
        }
        else{
            $s = $this->getProperty()->getIdentifierInNamespace();
        }

        return (string) $s;
    }

    /**
     * Retour attendu : (0,1) ou (0,n) etc...
     * @return string
     */
    public function getDomainQuantifiers()
    {
        $s = '-';
        if(!is_null($this->getDomainMinQuantifier()) && !is_null($this->getDomainMaxQuantifier())){
            if($this->getDomainMinQuantifier() == -1){
                $min = 'n';
            }
            else{
                $min = $this->getDomainMinQuantifier();
            }

            if($this->getDomainMaxQuantifier() == -1){
                $max = 'n';
            }
            else{
                $max = $this->getDomainMaxQuantifier();
            }

            $s = '('.$min.','.$max.')';
        }

        return (string) $s;
    }

    /**
     * Retour attendu : (0,1) ou (0,n) etc...
     * @return string
     */
    public function getRangeQuantifiers()
    {
        $s = '-';
        if(!is_null($this->getRangeMinQuantifier()) && !is_null($this->getRangeMaxQuantifier())){
            if($this->getRangeMinQuantifier() == -1){
                $min = 'n';
            }
            else{
                $min = $this->getRangeMinQuantifier();
            }

            if($this->getRangeMaxQuantifier() == -1){
                $max = 'n';
            }
            else{
                $max = $this->getRangeMaxQuantifier();
            }

            $s = '('.$min.','.$max.')';
        }

        return (string) $s;
    }

    /**
     * @return string
     * Générer l'URI
     */
    public function getURI(){
        $baseUri = $this->getNamespaceForVersion()->getTopLevelNamespace()->getNamespaceURI();

        $identifier = $this->getProperty()->getIdentifierInURI();

        // Si la version est externe et qu'elle dispose de sa propre base URI
        if($this->getNamespaceForVersion()->getIsExternalNamespace() && !empty($this->getNamespaceForVersion()->getNamespaceURI())){
                $baseUri = $this->getNamespaceForVersion()->getNamespaceURI();
        }

        // Si la version est interne, utiliser identifier_in_namespace
        if(!$this->getNamespaceForVersion()->getIsExternalNamespace()){
            $identifier = $this->getProperty()->getIdentifierInNamespace();
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