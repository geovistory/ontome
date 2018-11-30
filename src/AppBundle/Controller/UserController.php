<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 20/04/2017
 * Time: 10:57
 */

namespace AppBundle\Controller;


use AppBundle\Entity\User;
use AppBundle\Form\UserEditForm;
use AppBundle\Form\UserRegistrationForm;
use AppBundle\Security\LoginFormAuthenticator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * @Route("/register", name="user_register")
     * @param Request $request
     * @return Response a response instance
     */
    public function registerAction(Request $request, LoginFormAuthenticator $authenticator)
    {
        $form = $this->createForm(UserRegistrationForm::class);

        $form->handleRequest($request);

        if ($form->isValid()){
            /** @var User $user */
            $user = $form->getData();

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Welcome '.$user->getFullName());

            return $this->get('security.authentication.guard_handler')
                ->authenticateUserAndHandleSuccess(
                    $user,
                    $request,
                    $authenticator,
                    'main'
                );
        }

        return $this->render('user/register.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/user")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $users = $em->getRepository('AppBundle:User')
            ->findAll();

        return $this->render('user/list.html.twig', [
            'users' => $users
        ]);
    }

    /**
     * @Route("/user/{id}", name="user_show")
     */
    public function showAction(User $user)
    {
        $em = $this->getDoctrine()->getManager();
        $profiles = $em->getRepository('AppBundle:Profile')
            ->findBy(array('creator' => $user->getId()));
        $namespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findBy(array('creator' => $user->getId()));
        return $this->render('user/show.html.twig', array(
            'profiles' => $profiles,
            'namespaces' => $namespaces,
            'user' => $user
        ));
    }

    /**
     * @Route("/user/{id}/edit", name="user_edit")
     */
    public function editAction(User $user, Request $request)
    {
        $form = $this->createForm(UserEditForm::class, $user);

        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'User Updated!');

            return $this->redirectToRoute('user_edit', [
                'id' => $user->getId()
            ]);
        }

        return $this->render('user/edit.html.twig', [
            'userForm' => $form->createView(),
            'user' => $user
        ]);

    }
}