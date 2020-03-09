<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 18/04/2017
 * Time: 17:12
 */

namespace AppBundle\Entity;


use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\UserRepository")
 * @ORM\Table(schema="che", name="admin_user")
 * @UniqueEntity(fields={"email"}, message="This e-mail is already in use.")
 * @UniqueEntity(fields={"login"}, message="This login is already in use.")
 */
class User implements UserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer",  name="pk_user")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     *
     * @ORM\Column(type="string", unique=true)
     */
    private $login;

    /**
     * @Assert\NotBlank()
     * @Assert\Email()
     *
     * @ORM\Column(type="string", unique=true)
     */
    private $email;

    /**
     * The encoded password
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private $password;

    /**
     * A non-persisted field that's used to create the encoded password.
     * @Assert\NotBlank(groups={"Registration","ResetPassword"})
     * @Assert\Length(
     *      min = 12,
     *      minMessage = "Your password must be at least {{ limit }} characters long",
     * )
     *
     * @Assert\Regex(
     *     pattern="/^(?:(?!.*[<>])(?:(?=.*[~!\[@#$%^&*()_,|`\]\{+=?.\}\\\/<>-])(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]))).*$/",
     *     match=true,
     *     message="Your password must contain at least 1 special character, 1 digit, 1 lowercase and 1 uppercase letter"
     * )
     *
     * @var string
     */
    private $plainPassword;

    /**
     * @Assert\NotBlank()
     *
     * @ORM\Column(type="string", nullable=false)
     */
    private $lastName;

    /**
     * @Assert\NotBlank()
     *
     * @ORM\Column(type="string", nullable=false)
     */
    private $firstName;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $status;

    /**
     * @Assert\NotEqualTo(false, message="You must accept our term of service")
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $hasValidatedPolicy;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $institution;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $roles = [];

    /**
     * @ORM\Column(type="string")
     */
    private $token;

    /**
     * @ORM\Column(type="datetime")
     */
    private $tokenDate;

    /**
     * @Assert\NotBlank()
     * @ORM\OneToMany(targetEntity="UserProjectAssociation", mappedBy="user")
     */
    private $userProjectAssociations;

    /**
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="UserProjectAssociation")
     * @ORM\JoinColumn(name="fk_current_active_project", referencedColumnName="pk_project", nullable=false)
     */
    private $currentActiveProject;

    /**
     * @ORM\OneToMany(targetEntity="Comment", mappedBy="creator")
     */
    private $comments;

    /**
     * User constructor.
     */
    public function __construct()
    {
        $this->userProjectAssociations = new ArrayCollection();
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
    public function getLogin()
    {
        return $this->login;
    }

    public function getUsername()
    {
        return $this->login;
    }

    public function getRoles()
    {
        $roles = $this->roles;

        // give everyone ROLE_USER!
        if (!in_array('ROLE_USER', $roles)) {
            $roles[] = 'ROLE_USER';
        }

        return $roles;
    }

    /**
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return mixed
     */
    public function getTokenDate()
    {
        return $this->tokenDate;
    }

    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return string $email
     */
    public function getEmail()
    {
        return $this->email;
    }

    public function getSalt()
    {
    }

    public function eraseCredentials()
    {
        $this->plainPassword = null;
    }

    /**
     * @param mixed $login
     */
    public function setLogin($login)
    {
        $this->login = $login;
    }

    /**
     * @return mixed
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @param mixed $firstName
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;
    }

    /**
     * @return mixed
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @param mixed $lastName
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
    }

    /**
     * @return mixed
     */
    public function getInstitution()
    {
        return $this->institution;
    }

    /**
     * @param mixed $institution
     */
    public function setInstitution($institution)
    {
        $this->institution = $institution;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @param mixed $roles
     */
    public function setRoles(array $roles)
    {
        $this->roles = $roles;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return string
     */
    public function getPlainPassword()
    {
        return $this->plainPassword;
    }

    /**
     * @param string $plainPassword
     */
    public function setPlainPassword($plainPassword)
    {
        $this->plainPassword = $plainPassword;
        // forces the object to look "dirty" to Doctrine. Avoids
        // Doctrine *not* saving this entity, if only plainPassword changes
        $this->password = null;
    }

    public function getFullName()
    {
        return trim($this->getFirstName().' '.$this->getLastName());
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getHasValidatedPolicy()
    {
        return $this->hasValidatedPolicy;
    }

    /**
     * @param mixed $hasValidatedPolicy
     */
    public function setHasValidatedPolicy($hasValidatedPolicy)
    {
        $this->hasValidatedPolicy = $hasValidatedPolicy;
    }

    /**
     * @return ArrayCollection|UserProjectAssociation
     */
    public function getUserProjectAssociations()
    {
        return $this->userProjectAssociations;
    }

    /**
     * @return string a human readable identification of the object
     */
    public function getObjectIdentification()
    {
        return $this->login;
    }

    /**
     * @return Project
     */
    public function getCurrentActiveProject()
    {
        return $this->currentActiveProject;
    }

    /**
     * @return OntoNamespace the ongoing namespace managed by the current active project
     */
    public function getCurrentOngoingNamespace()
    {
        $namespaces = $this->getCurrentActiveProject()->getManagedNamespaces()->filter(function(OntoNamespace $namespace){
            return $namespace->getIsOngoing();
        });

        if($namespaces->first())
            return $namespaces->first();
        else
            return;
    }

    /**
     * @param mixed $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @param mixed $tokenDate
     */
    public function setTokenDate($tokenDate)
    {
        $this->tokenDate = $tokenDate;
    }

    /**
     * @param mixed $currentActiveProject
     */
    public function setCurrentActiveProject($currentActiveProject)
    {
        $this->currentActiveProject = $currentActiveProject;
    }

    /**
     * @return mixed
     */
    public function getComments()
    {
        return $this->comments;
    }

    public function __toString()
    {
        $s = $this->getFullName();
        if(is_null($s)) {
            $s = 'Anonymous';
        }
        return (string) $s;
    }



}