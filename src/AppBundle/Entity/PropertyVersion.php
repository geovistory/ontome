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
 * @ORM\Entity
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
     * @Assert\NotBlank()
     * @ORM\ManyToOne(targetEntity="OntoClass")
     * @ORM\JoinColumn(name="has_range", referencedColumnName="pk_class", nullable=false)
     */
    private $range;

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
     * @ORM\ManyToOne(targetEntity="OntoNamespace", inversedBy="propetyVersions")
     * @ORM\JoinColumn(name="fk_namespace_for_version", referencedColumnName="pk_namespace", nullable=false)
     */
    private $namespaceForVersion;

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
     * @return string the formatted quantifiers string
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
    public function getInvertedLabel()
    {
        if($this->getProperty()->getIdentifierInNamespace() === explode(' (',$this->getStandardLabel())[0]){
            $s = $this->getProperty()->getIdentifierInNamespace();
        }
        else if(!is_null($this->getStandardLabel())) {
            $s = $this->getProperty()->getIdentifierInNamespace().' '.$this->getStandardLabel();
        }
        else $s = $this->getProperty()->getIdentifierInNamespace();

        return (string) $s;
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
}