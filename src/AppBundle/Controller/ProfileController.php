<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 17/11/2017
 * Time: 18:09
 */

namespace AppBundle\Controller;

use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\Profile;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfileController  extends Controller
{

    /**
     * @Route("/profile/{id}", name="profile_show")
     * @param Profile $profile
     * @return Response the rendered template
     */
    public function showAction(Profile $profile)
    {
        $em = $this->getDoctrine()->getManager();

        $classes = $em->getRepository('AppBundle:OntoClass')
            ->findClassesByProfileId($profile);

        $properties = $em->getRepository('AppBundle:Property')
            ->findPropertiesByProfileId($profile);

        return $this->render('profile/show.html.twig', array(
            'profile' => $profile,
            'classes' => $classes,
            'properties' => $properties
        ));

    }

    /**
     * @Route("/profile")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $profiles = $em->getRepository('AppBundle:Profile')
            ->findAll();

        return $this->render('profile/list.html.twig', [
            'profiles' => $profiles
        ]);
    }

    /**
     * @Route("/profile/{id}/edit", name="profile_edit")
     * @param Profile $profile
     * @return Response the rendered template
     */
    public function editAction(Profile $profile)
    {
        $this->denyAccessUnlessGranted('edit', $profile);

        $em = $this->getDoctrine()->getManager();

        $classes = $em->getRepository('AppBundle:OntoClass')
            ->findClassesByProfileId($profile);

        $selectableClasses = $em->getRepository('AppBundle:OntoClass')
            ->findClassesForAssociationWithProfileByProfileId($profile);

        $properties = $em->getRepository('AppBundle:Property')
            ->findPropertiesByProfileId($profile);

        $rootNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findAllNonAssociatedToProfileByProfileId($profile);

        return $this->render('profile/edit.html.twig', array(
            'profile' => $profile,
            'classes' => $classes,
            'selectableClasses' => $selectableClasses,
            'rootNamespaces' => $rootNamespaces,
            'properties' => $properties
        ));
    }

    /**
     * @Route("/profile/{profile}/namespace/{namespace}/add", name="profile_namespace_association")
     * @Method({ "POST"})
     * @param OntoNamespace  $namespace    The namespace to be associated with a profile
     * @param Profile  $profile    The profile to be associated with a namespace
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted namespaces list
     */
    public function newProfileNamespaceAssociationAction(OntoNamespace $namespace, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile);

        if($namespace->getIsTopLevelNamespace()) {
            $status = 'Error';
            $message = 'This namespace is not valid';
        }
        else if ($profile->getNamespaces()->contains($namespace)) {
            $status = 'Error';
            $message = 'This namespace is already used by this profile';
        }
        else {
            $profile->addNamespace($namespace);
            $em = $this->getDoctrine()->getManager();
            $em->persist($profile);
            $em->flush();
            $status = 'Success';
            $message = 'Namespace successfully associated';
        }


        $response = array(
            'status' => $status,
            'message' => $message
        );

        return new JsonResponse($response);

    }

    /**
     * @Route("/profile/{profile}/namespace/{namespace}/delete", name="profile_namespace_disassociation")
     * @Method({ "DELETE"})
     * @param OntoNamespace  $namespace    The namespace to be disassociated from a profile
     * @param Profile  $profile    The profile to be disassociated from a namespace
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteProfileNamespaceAssociationAction(OntoNamespace $namespace, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile);

        $profile->removeNamespace($namespace);
        $em = $this->getDoctrine()->getManager();
        $em->persist($profile);
        $em->flush();

        return new JsonResponse(null, 204);

    }

    /**
     * @Route("/selectable-classes/profile/{profile}/json", name="selectable_classes_profile_json")
     * @Method("GET")
     * @param Profile $profile
     * @return JsonResponse a Json formatted list representation of OntoClasses selectable by Profile
     */
    public function getSelectableClassesByProfile(Profile $profile)
    {
        try{
            $em = $this->getDoctrine()->getManager();
            $classes = $em->getRepository('AppBundle:OntoClass')
                ->findClassesForAssociationWithProfileByProfileId($profile);
            $data['data'] = $classes;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($classes)) {
            return new JsonResponse(null,204, array());
        }

        //return new JsonResponse(null,404, array('content-type'=>'application/problem+json'));
        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/profile/{profile}/class/{class}/add", name="profile_class_association")
     * @Method({ "POST"})
     * @param OntoClass  $class    The class to be associated with a profile
     * @param Profile  $profile    The profile to be associated with a namespace
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted namespaces list
     */
    public function newProfileClassAssociationAction(OntoClass $class, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile);

        if ($profile->getClasses()->contains($class)) {
            $status = 'Error';
            $message = 'This class is already used by this profile';
        }
        else {
            $profile->addClass($class);
            $em = $this->getDoctrine()->getManager();
            $em->persist($profile);
            $em->flush();
            $status = 'Success';
            $message = 'Class successfully associated';
        }


        $response = array(
            'status' => $status,
            'message' => $message
        );

        return new JsonResponse($response);

    }

}