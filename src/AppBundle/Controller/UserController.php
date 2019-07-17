<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 20/04/2017
 * Time: 10:57
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Project;
use AppBundle\Entity\User;
use AppBundle\Entity\UserProjectAssociation;
use AppBundle\Form\MyEnvironmentForm;
use AppBundle\Form\UserEditForm;
use AppBundle\Form\UserRegistrationForm;
use AppBundle\Form\UserRequestPasswordForm;
use AppBundle\Form\UserResetPasswordForm;
use AppBundle\Form\UserSelfEditForm;
use AppBundle\Security\LoginFormAuthenticator;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    private $recaptchaSecret;

    /**
     * UserController constructor.
     * @param $recaptchaSecret
     */
    public function __construct($recaptchaSecret)
    {
        $this->$recaptchaSecret = $recaptchaSecret;
    }

    /**
     * @return mixed
     */
    public function getRecaptchaSecret()
    {
        return $this->recaptchaSecret;
    }



    /**
     * @Route("/register", name="user_register")
     * @param Request $request
     * @return Response a response instance
     */
    public function registerAction(Request $request, LoginFormAuthenticator $authenticator, \Swift_Mailer $mailer)
    {
        $form = $this->createForm(UserRegistrationForm::class);

        $form->handleRequest($request);

        if ($form->isValid() && $this->captchaVerify($request->get('g-recaptcha-response'))){

            /** @var User $user */
            $user = $form->getData();
            $user->setStatus(true);

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            //send Welcome e-mail
            $message = (new \Swift_Message('[OntoME] Welcome to OntoME!'))
                ->setFrom('ontome@dataforhistory.org')
                ->setTo($user->getEmail())
                ->setBody(
                    $this->renderView(
                        'email/registration.html.twig',
                        array('user' => $user)
                    ),
                    'text/html'
                )
                /*
                 * If you also want to include a plaintext version of the message
                ->addPart(
                    $this->renderView(
                        'Emails/registration.txt.twig',
                        array('name' => $name)
                    ),
                    'text/plain'
                )
                */
            ;

            $mailer->send($message);


            $this->addFlash('success', 'Welcome '.$user->getFullName());

            return $this->get('security.authentication.guard_handler')
                ->authenticateUserAndHandleSuccess(
                    $user,
                    $request,
                    $authenticator,
                    'main'
                );
        }

        if($form->isValid() && !$this->captchaVerify($request->get('g-recaptcha-response'))){

            $this->addFlash(
                'error',
                'Are you a human or a machine?'
            );
        }

        return $this->render('user/register.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/request-password", name="user_request_password")
     * @param Request $request
     * @return Response a response instance
     */
    public function requestPasswordAction(Request $request, \Swift_Mailer $mailer)
    {
        $tmpUser = new User();

        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm(UserRequestPasswordForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tmpUser = $form->getData();
            $user = $em->getRepository('AppBundle:User')->findOneBy(['email'=>$tmpUser['email']]);
            if($user){
                $token = md5(random_bytes(10));
                $user->setToken($token);
                $user->setTokenDate(new \DateTime());
                $em->persist($user);
                $em->flush();

                $message = (new \Swift_Message('[OntoME] Reset password request'))
                    ->setFrom('ontome@dataforhistory.org')
                    ->setTo($user->getEmail())
                    ->setBody(
                        $this->renderView(
                            'email/requestPassword.html.twig',
                            array('user' => $user)
                        ),
                        'text/html'
                    )
                ;
                $mailer->send($message);
                $this->addFlash('success', 'An e-mail has been sent.');

                return $this->redirectToRoute("home");
            }
            else {
                return $this->redirectToRoute("user_request_password") ;
            }
        }
        return $this->render('security/requestPassword.html.twig', array(
            'form' => $form->createView()
        ));

    }

    /**
     * @Route("/reset-password/{token}", name="user_reset_password")
     */
    public function resetPasswordAction(Request $request, $token)
    {
        $em = $this->getDoctrine()->getManager();
        $tmpUser = $em->getRepository('AppBundle:User')->findOneBy(['token'=>$token]);
        $formView = null;

        if($tmpUser){
            $now = new \DateTime('now');
            $tokenDate = $tmpUser->getTokenDate();
            $diff = $tokenDate->diff($now);
            if($diff->format('%a') > 1){
                $this->addFlash(
                    'error',
                    'The token has expired. Please make a new password request.'
                );
            }
            else {

                $user = $tmpUser;
                $form = $this->createForm(UserResetPasswordForm::class, $user);
                $form->handleRequest($request);
                $formView = $form->createView();
                if ($form->isSubmitted() && $form->isValid()) {
                    $user->setToken(null); //token remis à null
                    $user->setTokenDate(null); //date du token remise à null
                    $em->persist($user);
                    $em->flush();

                    $this->addFlash('success', 'Password updated!');

                    return $this->redirectToRoute('user_show', [
                        'id' => $user->getId()
                    ]);
                }
            }
        }
        else {
            $this->addFlash(
                'error',
                'This token does not exist. Please make a new password request.'
            );
        }

        return $this->render('user/resetPassword.html.twig', array(
            'form' => $formView,
            'user' => $tmpUser
        ));


    }

    /**
     * @Route("/user/test-mail")
     */
    public function testMailAction(\Swift_Mailer $mailer)
    {
        $user = $this->getUser();
        //send Welcome e-mail
        $message = (new \Swift_Message('[OntoME] Welcome to OntoME!'))
            ->setFrom('ontome@dataforhistory.org')
            ->setTo($user->getEmail())
            ->setBody(
                $this->renderView(
                    'email/registration.html.twig',
                    array('user' => $user)
                ),
                'text/html'
            )
            /*
             * If you also want to include a plaintext version of the message
            ->addPart(
                $this->renderView(
                    'Emails/registration.txt.twig',
                    array('name' => $name)
                ),
                'text/plain'
            )
            */
        ;

        $mailer->send($message);
        return $this->render('main/homepage.html.twig');

    }

    /**
     * @Route("/user")
     */
    public function listAction()
    {
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();

        $users = $em->getRepository('AppBundle:User')
            ->findAll();

        return $this->render('user/list.html.twig', [
            'users' => $users
        ]);
    }

    /**
     * @Route("/user/{id}", name="user_show", requirements={"id"="\d+"})
     * @param $user User
     * @return Response
     */
    public function showAction(Request $request, User $user)
    {
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
        else if ($user != $this->getUser() && !$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();

        // Pour l'onglet My Project
        // On récupère tous les userProjects associations de l'utilisateur dans un ArrayCollection
        $userProjects = new ArrayCollection($em->getRepository('AppBundle:UserProjectAssociation')
            ->findBy(array('user' => $user->getId())));

        // Vérifier si l'utilisateur a déjà le projet public dans ses associations userProject
        $testUserProjectPublicAssociation = $em->getRepository('AppBundle:UserProjectAssociation')
                                            ->findOneBy(array('user'=>$user->getId(), 'project'=>21));

        // Non il ne l'a pas : on le rajoute manuellement
        if(is_null($testUserProjectPublicAssociation))
        {
            // On crée l'entité Projet public et son association userProject "fictif"
            $publicProject = $em->getRepository('AppBundle:Project')->find(21);
            $userProjectPublicAssociation = new UserProjectAssociation();
            $userProjectPublicAssociation->setUser($user);
            $userProjectPublicAssociation->setProject($publicProject);
            $userProjects->add($userProjectPublicAssociation);
        }

        // Pour l'onglet My Current Namespaces
        $defaultNamespace = $em->getRepository('AppBundle:OntoNamespace')->findDefaultNamespaceForProject($user->getCurrentActiveProject());

        // userProjectAssociation with current active project
        $userCurrentActiveProjectAssociation =$em->getRepository('AppBundle:UserProjectAssociation')
            ->findOneBy(array(
                'user' => $user->getId(),
                'project' => $user->getCurrentActiveProject()->getId())
            );

        if(is_null($userCurrentActiveProjectAssociation))
        {
            $userCurrentActiveProjectAssociation = $userProjectPublicAssociation;
        }

        $additionalNamespaces = $em->getRepository('AppBundle:OntoNamespace')->findAdditionalNamespacesForUserProject($userCurrentActiveProjectAssociation);

        return $this->render('user/show.html.twig', array(
            'userProjects' => $userProjects,
            'defaultNamespace' => $defaultNamespace,
            'additionalNamespaces' => $additionalNamespaces,
            'user' => $user
        ));
    }

    /**
     * @Route("/user/editCurrentActiveProject/{project}", name="user_edit_current_active_project")
     * @param $project Project
     * @return Response
     */
    public function editCurrentActiveProjectAction(Project $project)
    {
        $user = $this->getUser();
        $user->setCurrentActiveProject($project);

        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'Current active project updated!');

        return $this->redirectToRoute('user_show', [
            'id' => $user->getId(),
            '_fragment' => 'my-projects'
        ]);
    }

    /**
     * @Route("/user/{id}/edit", name="user_edit")
     */
    public function editAction(User $user, Request $request)
    {
        if ($user != $this->getUser() && !$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(UserSelfEditForm::class, $user);

        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
            $form = $this->createForm(UserEditForm::class, $user);
        }

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'User Updated!');

            return $this->redirectToRoute('user_show', [
                'id' => $user->getId()
            ]);
        }
        if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
            return $this->render('user/edit.html.twig', [
                'userForm' => $form->createView(),
                'user' => $user
            ]);
        }
        else return $this->render('user/selfEdit.html.twig', [
            'userForm' => $form->createView(),
            'user' => $user
        ]);


    }

    function captchaVerify($recaptcha){
        $url = "https://www.google.com/recaptcha/api/siteverify";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            "secret"=>"6Lcvi34UAAAAAERZMen2aVy-9j20JRhkF3n60UBH","response"=>$recaptcha));
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response);

        return $data->success;
    }
}