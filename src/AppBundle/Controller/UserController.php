<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 20/04/2017
 * Time: 10:57
 */

namespace AppBundle\Controller;


use AppBundle\Entity\EntityUserProjectAssociation;
use AppBundle\Entity\OntoNamespace;
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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $defaultNamespace = $em->getRepository('AppBundle:OntoNamespace')
            ->findDefaultNamespaceForProject($user->getCurrentActiveProject());

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

        $additionalNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findAdditionalNamespacesForUserProject($userCurrentActiveProjectAssociation);

        $activeNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findAllActiveNamespacesForUserProject($userCurrentActiveProjectAssociation);

        $activeProfiles = $em->getRepository('AppBundle:Profile')
            ->findAllActiveProfilesForUserProject($userCurrentActiveProjectAssociation);

        $rootNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findBy(array(
                'isTopLevelNamespace' => true
            ));

        return $this->render('user/show.html.twig', array(
            'userProjects' => $userProjects,
            'userCurrentActiveProjectAssociation' => $userCurrentActiveProjectAssociation,
            'defaultNamespace' => $defaultNamespace,
            'additionalNamespaces' => $additionalNamespaces,
            'activeNamespaces' => $activeNamespaces,
            'activeProfiles' => $activeProfiles,
            'rootNamespaces' => $rootNamespaces,
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

    /**
     * @Route("/user/{userProjectAssociation}/namespace/{namespace}/add", name="user_project_namespace_association")
     * @Method({ "POST"})
     * @param OntoNamespace  $namespace    The namespace to be associated with an userProjectAssociation
     * @param UserProjectAssociation  $userProjectAssociation    The userProjectAssociation to be associated with a namespace
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted namespaces list
     */
    public function newUserProjectNamespaceAssociationAction(OntoNamespace $namespace, UserProjectAssociation $userProjectAssociation, Request $request)
    {
        //$this->denyAccessUnlessGranted('edit', $userProjectAssociation);

        // 1 Vérifier que le namespace n'est pas top level
        // 2 Vérifier qu'une association namespace - userproject identique n'existe pas
            // Si oui : remettre le system type à 25
            // Si non : créer l'association

        $em = $this->getDoctrine()->getManager();

        $eupa = null;
        if($namespace->getIsTopLevelNamespace()) {
            $status = 'Error';
            $message = 'This namespace is not valid';
        }
        else {
            foreach($userProjectAssociation->getEntityUserProjectAssociations() as $eupa) {
                if($eupa->getNamespace() == $namespace) {
                    // Il existe déjà une association mais est-ce Selected ?
                    if($eupa->getSystemType()->getId() == 25) {
                        // On ne fait rien : l'association existe déjà et est selected.
                        $status = 'Error';
                        $message = 'This namespace is already used';
                        break;
                    }
                    elseif ($eupa->getSystemType()->getId() == 26) {
                        // L'association existe déjà, et l'utilisateur veut remettre à selected.
                        $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(25); //systemType 25 = Selected namespace for user preference
                        $eupa->setSystemType($systemTypeSelected);
                        $status = 'Success';
                        $message = 'Namespace successfully associated';
                        $em->persist($eupa);
                        $em->flush();
                        break;
                    }
                }
                $eupa = null;
            }

            if(is_null($eupa)) {
                $eupa = new EntityUserProjectAssociation();
                $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(25); //systemType 25 = Selected namespace for user preference
                $eupa->setNamespace($namespace);
                $eupa->setUserProjectAssociation($userProjectAssociation);
                $eupa->setSystemType($systemTypeSelected);
                $eupa->setCreator($this->getUser());
                $eupa->setModifier($this->getUser());
                $eupa->setCreationTime(new \DateTime('now'));
                $eupa->setModificationTime(new \DateTime('now'));
                $em->persist($eupa);
                $em->flush();
                $status = 'Success';
                $message = 'Namespace successfully associated';
            }
        }

        /*
        if($namespace->getIsTopLevelNamespace()) {
            $status = 'Error';
            $message = 'This namespace is not valid';
        }
        else if ($userProjectAssociation->getEntityUserProjectAssociations()) {
            $status = 'Error';
            $message = 'This namespace is already used';
        }
        else {
            $namespaceUserProjectAssociation->setNamespace($namespace);
            $em = $this->getDoctrine()->getManager();
            $em->persist($userProjectAssociation);
            $em->flush();
            $status = 'Success';
            $message = 'Namespace successfully associated';
        }*/

        $response = array(
            'status' => $status,
            'message' => $message
        );

        return new JsonResponse($response);
    }

    /**
     * @Route("/user/{userProjectAssociation}/namespace/{namespace}/delete", name="user_project_namespace_disassociation")
     * @Method({ "DELETE"})
     * @param OntoNamespace  $namespace    The namespace to be disassociated from a userProjectAssociation
     * @param UserProjectAssociation  $userProjectAssociation    The userProjectAssociation to be disassociated from a namespace
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteUserProjectNamespaceAssociationAction(OntoNamespace $namespace, UserProjectAssociation $userProjectAssociation, Request $request)
    {
        //$this->denyAccessUnlessGranted('edit', $userProjectAssociation);

        $em = $this->getDoctrine()->getManager();

        $eupa = null;

        foreach($userProjectAssociation->getEntityUserProjectAssociations() as $eupa) {
            if($eupa->getNamespace() == $namespace) {
                // Il existe déjà une association mais est-ce Selected ?
                if($eupa->getSystemType()->getId() == 26) {
                    // On ne fait rien : l'association existe et est déjà rejected.
                    $status = 'Error';
                    $message = 'This namespace is already rejected';
                    break;
                }
                elseif ($eupa->getSystemType()->getId() == 25) {
                    // L'association existe déjà, et l'utilisateur veut mettre à rejected
                    $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(26); //systemType 25 = Selected namespace for user preference
                    $eupa->setSystemType($systemTypeSelected);
                    $status = 'Success';
                    $message = 'Namespace successfully associated';
                    $em->persist($eupa);
                    $em->flush();
                    break;
                }
            }
            $eupa = null;
        }
        if(!is_null($eupa))
            $em->persist($eupa);
        $em->flush();

        return new JsonResponse(null, 204);

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