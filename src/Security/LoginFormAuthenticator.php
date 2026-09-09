<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 19/04/2017
 * Time: 11:22
 */

namespace App\Security;


use App\Entity\User;
use App\Form\LoginForm;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
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

    public function __construct(FormFactoryInterface $formFactory, EntityManager $em, RouterInterface $router)
    {

        $this->formFactory = $formFactory;
        $this->em = $em;
        $this->router = $router;
    }

    public function supports(Request $request): bool
    {
        return $request->getPathInfo() == '/login' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $session = $request->getSession();
        if (
            null !== $request->get('_target_path')
            && explode('#', basename((string) $request->request->get('_target_path')))[0] !== ''
            && null === $session->get('trueReferer')
            && null === $session->get('_security.main.target_path')
        )
        {
            $session->set('trueReferer', $request->get('_target_path'));
        }

        $form = $this->formFactory->create(LoginForm::class);
        $form->handleRequest($request);

        $data = $form->getData() ?? [];
        $username = (string) ($data['_username'] ?? '');
        $password = (string) ($data['_password'] ?? '');

        $request->getSession()->set(Security::LAST_USERNAME, $username);

        return new Passport(
            new UserBadge($username, function (string $userIdentifier) {
                $user = $this->em->getRepository(User::class)
                    ->findOneBy(['login' => $userIdentifier]);

                if (!$user) {
                    throw new UserNotFoundException(sprintf('Utilisateur "%s" introuvable.', $userIdentifier));
                }

                return $user;
            }),
            new PasswordCredentials($password)
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->router->generate('security_login');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();
        $targetPath = null;

        if(!is_null($session->get('_security.main.target_path')))
        {
            $targetPath = $session->get('_security.main.target_path');
        }

        if(is_null($targetPath) && !is_null($session->get('trueReferer')))
        {
            if(explode("#", basename($session->get('trueReferer')))[0] != 'login')
            {
                $targetPath = $session->get('trueReferer');
            }
        }

        if(is_null($targetPath) && !is_null($request->get('_target_path'))
            && explode("#", basename($request->get('_target_path')))[0] != ''
            && is_null($targetPath))
        {
            if(explode("#", basename($request->get('_target_path')))[0] != 'login')
            {
                $targetPath = $request->get('_target_path');
            }
        }

        if (is_null($targetPath)) {
            $targetPath = $this->router->generate('home');
        }

        return new RedirectResponse($targetPath);
    }
}