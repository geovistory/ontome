<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 19/04/2017
 * Time: 11:22
 */

namespace AppBundle\Security;


use AppBundle\Entity\User;
use AppBundle\Form\LoginForm;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractFormLoginAuthenticator
{
    use TargetPathTrait;
    /**
     * @var FormFactoryInterface
     */
    private $formFactory;
    /**
     * @var EntityManager
     */
    private $em;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var UserPasswordEncoder
     */
    private $passwordEncoder;

    public function __construct(FormFactoryInterface $formFactory, EntityManager $em, RouterInterface $router, UserPasswordEncoderInterface $passwordEncoder)
    {

        $this->formFactory = $formFactory;
        $this->em = $em;
        $this->router = $router;
        $this->passwordEncoder = $passwordEncoder;
    }

    public function getCredentials(Request $request)
    {
        if(!is_null($request->request->get('_target_path')))
        {
            $session = $request->getSession();
            $referer = $request->request->get('_target_path');

            if(explode("#", basename($referer))[0] != 'login')
            {
                $session->set('trueReferer', $referer);
            }
        }

        $isLoginSubmit = $request->getPathInfo() === '/login' && $request->isMethod('POST');
        if (!$isLoginSubmit) {
            //skip authentication
            return;
        }

        $form = $this->formFactory->create(LoginForm::class);
        $form->handleRequest($request);

        $data = $form->getData();
        $request->getSession()->set(
            Security::LAST_USERNAME,
            $data['_username']
        );

        return $data;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $username = $credentials['_username'];

        return $this->em->getRepository('AppBundle:User')
            ->findOneBy(['login' => $username]);

    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        $password = $credentials['_password'];
        if ($this->passwordEncoder->isPasswordValid($user, $password)) {
            return true;
        }

        return false;
    }

    protected function getLoginUrl()
    {
        return $this->router->generate('security_login');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $session = $request->getSession();
        $targetPath = null;

        if(is_null($session->get('trueReferer')))
        {
            if($request->request->get('_target_path')) {
                $targetPath = $request->request->get('_target_path');
            }
        }
        else
        {
            $targetPath = $session->get('trueReferer');
        }

        if (!$targetPath) {
            $targetPath = $this->router->generate('home');
        }

        return new RedirectResponse($targetPath);
    }
}