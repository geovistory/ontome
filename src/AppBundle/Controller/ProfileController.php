<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 17/11/2017
 * Time: 18:09
 */

namespace AppBundle\Controller;

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

        $properties = $em->getRepository('AppBundle:Property')
            ->findPropertiesByProfileId($profile);

        $rootNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findBy(array('isTopLevelNamespace' => true));

        $rootNamespaces = new ArrayCollection($rootNamespaces);

        $rootNamespaces = $rootNamespaces
            ->filter(function(OntoNamespace $namespace) use($profile) {
                $referencedNamespaces = $namespace->getReferencedVersion();
                $intersect = array_intersect($referencedNamespaces->toArray(), $profile->getNamespaces()->toArray());
                return is_null($intersect);
            });

        return $this->render('profile/edit.html.twig', array(
            'profile' => $profile,
            'classes' => $classes,
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
        else if ($profile->getNamespaces()->contains($profile)) {
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

}