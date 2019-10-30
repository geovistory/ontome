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
use AppBundle\Entity\Profile;
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

        // Public project = 21
        $publicProject = $em->getRepository('AppBundle:Project')->find(21);

        if(is_null($user->getCurrentActiveProject())){
            $user->setCurrentActiveProject($publicProject);
            $em->persist($user);
            $em->flush();
        }

        // Pour l'onglet My Project
        // On récupère tous les userProjectAssociations de l'utilisateur dans un ArrayCollection
        $userProjectAssociations = new ArrayCollection($em->getRepository('AppBundle:UserProjectAssociation')
            ->findBy(array('user' => $user->getId())));

        // Vérifier si l'utilisateur a déjà le projet public dans ses associations userProject
        $hasUserPublicProjectAssociation = false;
        foreach($userProjectAssociations as $userProjectAssociation){
            if($userProjectAssociation->getProject()->getId() == 21){
                $userPublicProjectAssociation = $userProjectAssociation;
                $hasUserPublicProjectAssociation = true;
                break;
            }
        }

        // Non il ne l'a pas : on le crée et on rajoute dans l'ArrayCollection
        if(!$hasUserPublicProjectAssociation)
        {
            // On crée userProjectAssociation qui relie l'user au projet public
            $upa = new UserProjectAssociation();
            $upa->setUser($user);
            $upa->setProject($publicProject);
            $upa->setCreator($this->getUser());
            $upa->setModifier($this->getUser());
            $upa->setCreationTime(new \DateTime('now'));
            $upa->setModificationTime(new \DateTime('now'));
            $em->persist($upa);
            $em->flush();
            $userProjectAssociations->add($upa);
            $userPublicProjectAssociation = $upa;

            foreach($publicProject->getNamespaces() as $namespace){
                $eupa = $em->getRepository('AppBundle:EntityUserProjectAssociation')
                    ->findOneBy(array(
                            'namespace' => $namespace,
                            'userProjectAssociation' => $userProjectAssociation
                        )
                    );

                // Il n'existe aucune vue.
                if (is_null($eupa)) {
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
                }
            }
        }

        // Retrouver le projet actif.
        $activeProject = $user->getCurrentActiveProject();
        if(is_null($activeProject)){
            $activeProject = $publicProject;
        }

        //Initiliasation, le projet public sera utilisé si aucun projet actif.
        $userActiveProjectAssociation = $userPublicProjectAssociation;

        // Retrouver l'userProjectAssociation qui contient le projet actif, si ça existe
        foreach($userProjectAssociations as $userProjectAssociation){
            if($userProjectAssociation->getProject() == $activeProject){
                $userActiveProjectAssociation = $userProjectAssociation;
                break;
            }
        }

        // Pour l'onglet My Current Namespaces
        $defaultNamespace = null;
        if($userActiveProjectAssociation->getProject()->getId() != 21){ // Le projet public n'a pas de "namespace par défaut"
            $defaultNamespace = $em->getRepository('AppBundle:OntoNamespace')
                ->findDefaultNamespaceForProject($userActiveProjectAssociation->getProject());
        }

        $profilesUserProject = new ArrayCollection($em->getRepository('AppBundle:Profile')
            ->findAllProfilesForUserProject($userActiveProjectAssociation));

        $activeNamespaces = new ArrayCollection($em->getRepository('AppBundle:OntoNamespace')
            ->findAllActiveNamespacesForUserProject($userActiveProjectAssociation));

        $additionalNamespaces = new ArrayCollection($em->getRepository('AppBundle:OntoNamespace')
            ->findAdditionalNamespacesForUserProject($userActiveProjectAssociation));

        // On retire les namespaces actives et selected from project profiles de Additional namespaces
        // On ne doit pas retirer les namespaces actives si le projet est public
        /*if($userActiveProjectAssociation->getProject()->getId() != 21){
            foreach($activeNamespaces as $activeNamespace) {
                if($additionalNamespaces->contains($activeNamespace)) {
                    $additionalNamespaces->removeElement($activeNamespace);
                }
            }
        }*/

        $activeProfiles = new ArrayCollection($em->getRepository('AppBundle:Profile')
            ->findAllActiveProfilesForUserProject($userActiveProjectAssociation));

        $rootNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findBy(array(
                'isTopLevelNamespace' => true
            ));

        return $this->render('user/show.html.twig', array(
            'userProjectAssociations' => $userProjectAssociations,
            'userActiveProjectAssociation' => $userActiveProjectAssociation,
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
        $em = $this->getDoctrine()->getManager();

        $user = $this->getUser();
        $user->setCurrentActiveProject($project);
        $em->persist($user);
        $em->flush();

        // Retrouver le userProjectAssociation
        $userProjectAssociation = $em->getRepository('AppBundle:UserProjectAssociation')
            ->findOneBy(array(
                    'user' => $user,
                    'project' => $project
                )
            );

        // Créer, si nécessaire, les entity_user_project_association. 3 cas possibles.
        if($project->getId() != 21) { // Le projet public n'a pas de "namespace par défaut" ni de profils, il n'est pas nécessaire d'en créer.

            // 1. Le namespace par défaut
            $defaultNamespace = $em->getRepository('AppBundle:OntoNamespace')
                ->findDefaultNamespaceForProject($project);

            // Est-ce que la vue sur defaultNamespace a déjà été initialisée ?
            $eupa = $em->getRepository('AppBundle:EntityUserProjectAssociation')
                ->findOneBy(array(
                        'namespace' => $defaultNamespace->getId(),
                        'userProjectAssociation' => $userProjectAssociation->getId()
                    )
                );

            // Il n'existe aucune vue sur defaultNamespace.
            if (is_null($eupa)) {
                $eupa = new EntityUserProjectAssociation();
                $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(25); //systemType 25 = Selected namespace for user preference
                $eupa->setNamespace($defaultNamespace);
                $eupa->setUserProjectAssociation($userProjectAssociation);
                $eupa->setSystemType($systemTypeSelected);
                $eupa->setCreator($this->getUser());
                $eupa->setModifier($this->getUser());
                $eupa->setCreationTime(new \DateTime('now'));
                $eupa->setModificationTime(new \DateTime('now'));
                $em->persist($eupa);
                $em->flush();
            }

            // 2. Les profils (et leurs namespaces associés) associés au projet
            $profilesUserProject = new ArrayCollection($em->getRepository('AppBundle:Profile')
                ->findAllProfilesForUserProject($userProjectAssociation));

            if (count($profilesUserProject) == 0) {
                foreach ($project->getProfiles() as $profile) {
                    // Vérifier si on a déjà pas un eupa sur ce profile
                    $eupa = $em->getRepository('AppBundle:EntityUserProjectAssociation')
                        ->findOneBy(array(
                                'profile' => $profile->getId(),
                                'userProjectAssociation' => $userProjectAssociation->getId()
                            )
                        );

                    if (is_null($eupa)) {
                        $eupa = new EntityUserProjectAssociation();
                        $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(25); //systemType 25 = Selected namespace for user preference
                        $eupa->setProfile($profile);
                        $eupa->setUserProjectAssociation($userProjectAssociation);
                        $eupa->setSystemType($systemTypeSelected);
                        $eupa->setCreator($this->getUser());
                        $eupa->setModifier($this->getUser());
                        $eupa->setCreationTime(new \DateTime('now'));
                        $eupa->setModificationTime(new \DateTime('now'));
                        $em->persist($eupa);
                        $em->flush();
                    }

                    foreach ($profile->getNamespaces() as $namespace) {
                        // Vérifier si un eupa identique n'a pas déjà etre crée avec un autre profil plus tot:
                        $eupa = $em->getRepository('AppBundle:EntityUserProjectAssociation')
                            ->findOneBy(array(
                                    'namespace' => $namespace->getId(),
                                    'userProjectAssociation' => $userProjectAssociation->getId()
                                )
                            );
                        if (is_null($eupa)) {
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
                        }
                    }
                }
            }
        }
        else {
            // 3. Cas projet public
            // On vérifie s'il existe déjà un userProjectAssociation lié avec le projet public
            $userProjectAssociation = $em->getRepository('AppBundle:UserProjectAssociation')
                ->findOneBy(array(
                        'user' => $user,
                        'project' => $project
                    )
                );

            // Il n'existe pas
            if(is_null($userProjectAssociation)){
                $upa = new UserProjectAssociation();
                $upa->setUser($user);
                $upa->setProject($project);
                $upa->setCreator($this->getUser());
                $upa->setModifier($this->getUser());
                $upa->setCreationTime(new \DateTime('now'));
                $upa->setModificationTime(new \DateTime('now'));
                $em->persist($upa);
                $em->flush();

                $userProjectAssociation = $upa;
            }

            // On récupère seulement les espaces de noms qu'on peut connaître depuis associates_project
            foreach($project->getNamespaces() as $namespace){
                $eupa = $em->getRepository('AppBundle:EntityUserProjectAssociation')
                    ->findOneBy(array(
                            'namespace' => $namespace,
                            'userProjectAssociation' => $userProjectAssociation
                        )
                    );

                // Il n'existe aucune vue.
                if (is_null($eupa)) {
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
                }
            }

        }
        //$this->addFlash('success', 'Current active project updated!');

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
     * @Method({"POST"})
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
                    $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(26); //systemType 26 = Rejected namespace for user preference
                    $eupa->setSystemType($systemTypeSelected);
                    $status = 'Success';
                    $message = 'Namespace successfully rejected';
                    $em->persist($eupa);
                    $em->flush();
                    break;
                }
            }
            $eupa = null;
        }

        if(is_null($eupa)) {
            $eupa = new EntityUserProjectAssociation();
            $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(26); //systemType 25 = Selected namespace for user preference
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
            $message = 'Namespace successfully rejected';
        }

        if(!is_null($eupa))
            $em->persist($eupa);
        $em->flush();

        return new JsonResponse(null, 204);

    }

    /**
     * @Route("/user/{userProjectAssociation}/profile/{profile}/add", name="user_project_profile_association")
     * @Method({"POST"})
     * @param Profile $profile The profile to be associated with an userProjectAssociation
     * @param UserProjectAssociation  $userProjectAssociation    The userProjectAssociation to be associated with a profile
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted profile list
     */
    public function newUserProjectProfileAssociationAction(Profile $profile, UserProjectAssociation $userProjectAssociation, Request $request)
    {
        //$this->denyAccessUnlessGranted('edit', $userProjectAssociation);

        // 1 Vérifier que le namespace n'est pas top level
        // 2 Vérifier qu'une association namespace - userproject identique n'existe pas
        // Si oui : remettre le system type à 25
        // Si non : créer l'association

        $em = $this->getDoctrine()->getManager();

        $eupa = null;

        foreach($userProjectAssociation->getEntityUserProjectAssociations() as $eupa) {
            if($eupa->getProfile() == $profile) {
                // Il existe déjà une association mais est-ce Selected ?
                if($eupa->getSystemType()->getId() == 25) {
                    // On ne fait rien : l'association existe déjà et est selected.
                    $status = 'Error';
                    $message = 'This profile is already used';
                    break;
                }
                elseif ($eupa->getSystemType()->getId() == 26) {
                    // L'association existe déjà, et l'utilisateur veut remettre à selected.
                    $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(25); //systemType 25 = Selected namespace for user preference
                    $eupa->setSystemType($systemTypeSelected);
                    $status = 'Success';
                    $message = 'Profile successfully associated';
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
            $eupa->setProfile($profile);
            $eupa->setUserProjectAssociation($userProjectAssociation);
            $eupa->setSystemType($systemTypeSelected);
            $eupa->setCreator($this->getUser());
            $eupa->setModifier($this->getUser());
            $eupa->setCreationTime(new \DateTime('now'));
            $eupa->setModificationTime(new \DateTime('now'));
            $em->persist($eupa);
            $em->flush();
            $status = 'Success';
            $message = 'Profile successfully associated';
        }

        // Pour chaque namespace du profil mettre à 26
        $arrayNamespaces = array();
        foreach($profile->getNamespaces() as $namespace) {
            $arrayNamespaces[] = $namespace->getId();

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

            if(!is_null($eupa))
                $em->persist($eupa);
            $em->flush();
        }

        $response = array(
            'status' => $status,
            'message' => $message,
            'namespacesSelected' => $arrayNamespaces
        );

        return new JsonResponse($response);
    }

    /**
     * @Route("/user/{userProjectAssociation}/profile/{profile}/delete", name="user_project_profile_disassociation")
     * @Method({ "DELETE"})
     * @param Profile  $profile    The profile to be disassociated from a userProjectAssociation
     * @param UserProjectAssociation  $userProjectAssociation    The userProjectAssociation to be disassociated from a profile
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteUserProjectProfileAssociationAction(Profile $profile, UserProjectAssociation $userProjectAssociation, Request $request)
    {
        //$this->denyAccessUnlessGranted('edit', $userProjectAssociation);

        $em = $this->getDoctrine()->getManager();

        $eupa = null;

        foreach($userProjectAssociation->getEntityUserProjectAssociations() as $eupa) {
            if($eupa->getProfile() == $profile) {
                // Il existe déjà une association mais est-ce Selected ?
                if($eupa->getSystemType()->getId() == 26) {
                    // On ne fait rien : l'association existe et est déjà rejected.
                    $status = 'Error';
                    $message = 'This profile is already rejected';
                    break;
                }
                elseif ($eupa->getSystemType()->getId() == 25) {
                    // L'association existe déjà, et l'utilisateur veut mettre à rejected
                    $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(26); //systemType 26 = Rejected profile for user preference
                    $eupa->setSystemType($systemTypeSelected);
                    $status = 'Success';
                    $message = 'Profile successfully rejected';
                    $em->persist($eupa);
                    $em->flush();
                    break;
                }
            }
            $eupa = null;
        }

        if(is_null($eupa)) {
            $eupa = new EntityUserProjectAssociation();
            $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(26);
            $eupa->setProfile($profile);
            $eupa->setUserProjectAssociation($userProjectAssociation);
            $eupa->setSystemType($systemTypeSelected);
            $eupa->setCreator($this->getUser());
            $eupa->setModifier($this->getUser());
            $eupa->setCreationTime(new \DateTime('now'));
            $eupa->setModificationTime(new \DateTime('now'));
            $em->persist($eupa);
            $em->flush();
            $status = 'Success';
            $message = 'Profile successfully rejected';
        }

        $em->persist($eupa);
        $em->flush();

        $arrayNamespaces = array();
        foreach($profile->getNamespaces() as $namespace) {
            $arrayNamespaces[] = $namespace->getId();

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
                        $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(26); //systemType 26 = Rejected namespace for user preference
                        $eupa->setSystemType($systemTypeSelected);
                        $status = 'Success';
                        $message = 'Namespace successfully rejected';
                        $em->persist($eupa);
                        $em->flush();
                        break;
                    }
                }
                $eupa = null;
            }

            if(is_null($eupa)) {
                $eupa = new EntityUserProjectAssociation();
                $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(26); //systemType 25 = Selected namespace for user preference
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
                $message = 'Namespace successfully rejected';
            }

            if(!is_null($eupa))
                $em->persist($eupa);
            $em->flush();
        }

        $response = array(
            'status' => $status,
            'message' => $message,
            'namespacesRejected' => $arrayNamespaces);

        return new JsonResponse($response);

    }
    /**
     * @Route("/user/{id}/reinitialization", name="user_reinitialization", requirements={"id"="\d+"})
     * @param $user User
     * @return Response
     */
    public function reinitialization(Request $request, User $user){
        // récupérer l'id du projet actif
        $currentProject = $user->getCurrentActiveProject();
        $em = $this->getDoctrine()->getManager();
        $upa = $em->getRepository('AppBundle:UserProjectAssociation')->findOneBy(array(
            "user" => $user,
            "project" => $currentProject
        ));


        // mettre tout à 26 (désactivation)
        $eupas = $em->getRepository('AppBundle:EntityUserProjectAssociation')->findBy(array(
            "userProjectAssociation" => $upa
        ));

        foreach ($eupas as $eupa) {
            $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(26);
            $eupa->setSystemType($systemTypeSelected);
            $eupa->setModifier($this->getUser());
            $eupa->setModificationTime(new \DateTime('now'));
            $em->persist($eupa);
            $em->flush();
        }

        // userProjectAssociation with current active project
        $userCurrentActiveProjectAssociation = $em->getRepository('AppBundle:UserProjectAssociation')
            ->findOneBy(array(
                    'user' => $user->getId(),
                    'project' => $user->getCurrentActiveProject()->getId())
            );

        if($currentProject->getId() != 21){
            // remettre à 25 les profiles/namespaces rattachés au projet par défaut
            $defaultNamespace = $em->getRepository('AppBundle:OntoNamespace')
                ->findDefaultNamespaceForProject($user->getCurrentActiveProject());

            $eupa = $em->getRepository('AppBundle:EntityUserProjectAssociation')
                ->findOneBy(array(
                        'namespace' => $defaultNamespace->getId(),
                        'userProjectAssociation' => $userCurrentActiveProjectAssociation->getId()
                    )
                );

            $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(25);
            $eupa->setSystemType($systemTypeSelected);
            $eupa->setModificationTime(new \DateTime('now'));
            $eupa->setModifier($this->getUser());
            $em->persist($eupa);
            $em->flush();
        }
        else {
            // Cas projet public
            // On vérifie s'il existe déjà un userProjectAssociation lié avec le projet public
            foreach ($userCurrentActiveProjectAssociation->getProject()->getNamespaces() as $namespace) {
                $eupa = $em->getRepository('AppBundle:EntityUserProjectAssociation')
                    ->findOneBy(array(
                            'namespace' => $namespace,
                            'userProjectAssociation' => $userCurrentActiveProjectAssociation
                        )
                    );

                $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(25); //systemType 25 = Selected namespace for user preference
                $eupa->setSystemType($systemTypeSelected);
                $eupa->setModifier($this->getUser());
                $eupa->setModificationTime(new \DateTime('now'));
                $em->persist($eupa);
                $em->flush();
            }
        }

        $profilesUserProject = new ArrayCollection($em->getRepository('AppBundle:Profile')
            ->findAllProfilesForUserProject($userCurrentActiveProjectAssociation));


        foreach ($profilesUserProject as $profile){
            $eupa = $em->getRepository('AppBundle:EntityUserProjectAssociation')
                ->findOneBy(array(
                        'profile' => $profile->getId(),
                        'userProjectAssociation' => $userCurrentActiveProjectAssociation->getId()
                    )
                );

            $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(25);
            $eupa->setSystemType($systemTypeSelected);
            $eupa->setModificationTime(new \DateTime('now'));
            $eupa->setModifier($this->getUser());
            $em->persist($eupa);
            $em->flush();

            foreach($profile->getNamespaces() as $namespace){
                $eupa = $em->getRepository('AppBundle:EntityUserProjectAssociation')
                    ->findOneBy(array(
                            'namespace' => $namespace->getId(),
                            'userProjectAssociation' => $userCurrentActiveProjectAssociation->getId()
                        )
                    );
                $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(25);
                $eupa->setSystemType($systemTypeSelected);
                $eupa->setModificationTime(new \DateTime('now'));
                $eupa->setModifier($this->getUser());
                $em->persist($eupa);
                $em->flush();
            }
        }

        // rediriger sur la page showAction
        return $this->redirectToRoute('user_show', [
            'id' => $user->getId(),
            '_fragment' => 'my-current-namespaces'
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