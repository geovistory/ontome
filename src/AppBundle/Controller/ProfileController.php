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
use AppBundle\Entity\ProfileAssociation;
use AppBundle\Entity\Property;
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

        $profileAssociations = $em->getRepository('AppBundle:ProfileAssociation')
            ->findBy(array('profile' => $profile));

        return $this->render('profile/show.html.twig', array(
            'profile' => $profile,
            'classes' => $classes,
            'properties' => $properties,
            'profileAssociations' => $profileAssociations
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

        $profileAssociations = $em->getRepository('AppBundle:ProfileAssociation')
            ->findBy(array('profile' => $profile));

        return $this->render('profile/edit.html.twig', array(
            'profile' => $profile,
            'classes' => $classes,
            'selectableClasses' => $selectableClasses,
            'rootNamespaces' => $rootNamespaces,
            'properties' => $properties,
            'profileAssociations' => $profileAssociations
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

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/associated-classes/profile/{profile}/json", name="associated_classes_profile_json")
     * @Method("GET")
     * @param Profile $profile
     * @return JsonResponse a Json formatted list representation of OntoClasses selectable by Profile
     */
    public function getAssociatedClassesByProfile(Profile $profile)
    {
        try{
            $em = $this->getDoctrine()->getManager();
            $classes = $em->getRepository('AppBundle:OntoClass')
                ->findClassesByProfileId($profile);
            $data['data'] = $classes;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($classes)) {
            return new JsonResponse(null,204, array());
        }

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

        $em = $this->getDoctrine()->getManager();
        $profileAssociation = $em->getRepository('AppBundle:ProfileAssociation')
            ->findOneBy(array('profile' => $profile->getId(), 'class' => $class->getId()));

        if (!is_null($profileAssociation)) {
            if($profileAssociation->getSystemType()->getId() == 5) {
                $status = 'Error';
                $message = 'This class is already used by this profile';
            }
            else {
                $systemType = $em->getRepository('AppBundle:SystemType')->find(5); //systemType 5 = selected
                $profileAssociation->setSystemType($systemType);
                $em->persist($profileAssociation);

                $em->flush();
                $status = 'Success';
                $message = 'Class successfully re-associated';
            }
        }
        else {
            $em = $this->getDoctrine()->getManager();

            $profileAssociation = new ProfileAssociation();
            $profileAssociation->setProfile($profile);
            $profileAssociation->setClass($class);
            $systemType = $em->getRepository('AppBundle:SystemType')->find(5); //systemType 5 = selected
            $profileAssociation->setSystemType($systemType);
            $profileAssociation->setCreator($this->getUser());
            $profileAssociation->setModifier($this->getUser());
            $profileAssociation->setCreationTime(new \DateTime('now'));
            $profileAssociation->setModificationTime(new \DateTime('now'));
            $em->persist($profileAssociation);

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

    /**
     * @Route("/profile/{profile}/property/{property}/add", name="profile_property_association")
     * @Method({ "POST"})
     * @param Property  $property    The property to be associated with a profile
     * @param Profile  $profile    The profile to be associated with a namespace
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted namespaces list
     */
    public function newProfilePropertyAssociationAction(Property $property, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile);

        $em = $this->getDoctrine()->getManager();
        $profileAssociation = $em->getRepository('AppBundle:ProfileAssociation')
            ->findOneBy(array('profile' => $profile->getId(), 'property' => $property->getId()));

        if (!is_null($profileAssociation)) {
            if($profileAssociation->getSystemType()->getId() == 5) {
                $status = 'Error';
                $message = 'This property is already used by this profile';
            }
            else {
                $systemType = $em->getRepository('AppBundle:SystemType')->find(5); //systemType 5 = selected
                $profileAssociation->setSystemType($systemType);
                $em->persist($profileAssociation);

                $em->flush();
                $status = 'Success';
                $message = 'property successfully re-associated';
            }
        }
        else {
            $em = $this->getDoctrine()->getManager();

            $profileAssociation = new ProfileAssociation();
            $profileAssociation->setProfile($profile);
            $profileAssociation->setProperty($property);
            $systemType = $em->getRepository('AppBundle:SystemType')->find(5); //systemType 5 = selected
            $profileAssociation->setSystemType($systemType);
            $profileAssociation->setCreator($this->getUser());
            $profileAssociation->setModifier($this->getUser());
            $profileAssociation->setCreationTime(new \DateTime('now'));
            $profileAssociation->setModificationTime(new \DateTime('now'));
            $em->persist($profileAssociation);

            $em->flush();
            $status = 'Success';
            $message = 'Property successfully associated';
        }


        $response = array(
            'status' => $status,
            'message' => $message
        );

        return new JsonResponse($response);
    }

    /**
     * @Route("/profile/{profile}/property/{property}/domain/{domain}/range/{range}/add", name="profile_inherited_property_association")
     * @Method({ "POST"})
     * @param Property  $property    The property to be associated with a profile
     * @param Profile  $profile    The profile to be associated with a property
     * @param OntoClass  $domain    The domain to be associated
     * @param OntoClass  $range    The range to be associated
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted namespaces list
     */
    public function newProfileInheritedPropertyAssociationAction(Property $property, Profile $profile, OntoClass $domain, OntoClass $range, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile);

        $em = $this->getDoctrine()->getManager();
        $profileAssociation = $em->getRepository('AppBundle:ProfileAssociation')
            ->findOneBy(array('profile' => $profile->getId(), 'property' => $property->getId(), 'domain' => $domain->getId(), 'range' => $range->getId()));

        if (!is_null($profileAssociation)) {
            if($profileAssociation->getSystemType()->getId() == 5) {
                $status = 'Error';
                $message = 'This property is already used by this profile';
            }
            else {
                $systemType = $em->getRepository('AppBundle:SystemType')->find(5); //systemType 5 = selected
                $profileAssociation->setSystemType($systemType);
                $em->persist($profileAssociation);

                $em->flush();
                $status = 'Success';
                $message = 'property successfully re-associated';
            }
        }
        else {
            $em = $this->getDoctrine()->getManager();

            $profileAssociation = new ProfileAssociation();
            $profileAssociation->setProfile($profile);
            $profileAssociation->setProperty($property);
            $profileAssociation->setDomain($domain);
            $profileAssociation->setRange($range);
            $systemType = $em->getRepository('AppBundle:SystemType')->find(5); //systemType 5 = selected
            $profileAssociation->setSystemType($systemType);
            $profileAssociation->setCreator($this->getUser());
            $profileAssociation->setModifier($this->getUser());
            $profileAssociation->setCreationTime(new \DateTime('now'));
            $profileAssociation->setModificationTime(new \DateTime('now'));
            $em->persist($profileAssociation);

            $em->flush();
            $status = 'Success';
            $message = 'Property successfully associated';
        }


        $response = array(
            'status' => $status,
            'message' => $message
        );

        return new JsonResponse($response);
    }

    /**
     * @Route("/profile/{profile}/class/{class}/delete", name="profile_class_disassociation")
     * @Method({ "POST"})
     * @param OntoClass  $class    The class to be disassociated from a profile
     * @param Profile  $profile    The profile to be disassociated from a namespace
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteProfileClassAssociationAction(OntoClass $class, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile);
        $em = $this->getDoctrine()->getManager();

        /*$profile->removeClass($class);
        $em->persist($profile);*/

        $inferredClassId = $em->getRepository('AppBundle:OntoClass')
            ->findInferredClassesByProfileAndClassId($profile, $class);


        if($class->getId() == $inferredClassId) {
            $profile->removeClass($class);
        }
        else {
            $profileAssociation = $em->getRepository('AppBundle:ProfileAssociation')
                ->findOneBy(array('profile' => $profile->getId(), 'class' => $class->getId()));

            $systemType = $em->getRepository('AppBundle:SystemType')->find(6); //systemType 6 = rejected

            $profileAssociation->setSystemType($systemType);
        }
        $em->persist($profile);
        $em->flush();

        return new JsonResponse(null, 204);

    }

    /**
     * @Route("/profile/{profile}/property/{property}/delete", name="profile_property_disassociation")
     * @Method({ "POST"})
     * @param Property  $property    The property to be disassociated from a profile
     * @param Profile  $profile    The profile to be disassociated from a namespace
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteProfilePropertyAssociationAction(Property $property, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile);
        $em = $this->getDoctrine()->getManager();


        $profileAssociation = $em->getRepository('AppBundle:ProfileAssociation')
            ->findOneBy(array('profile' => $profile->getId(), 'property' => $property->getId()));

        $systemType = $em->getRepository('AppBundle:SystemType')->find(6); //systemType 6 = rejected

        $profileAssociation->setSystemType($systemType);

        $em->persist($profile);
        $em->flush();

        return new JsonResponse(null, 204);

    }

    /**
     * @Route("/profile/{profile}/property/{property}/domain/{domain}/range/{range}/delete", name="profile_inherited_property_disassociation")
     * @Method({ "POST"})
     * @param Property  $property    The property to be disassociated from a profile
     * @param Profile  $profile    The profile to be disassociated from a namespace
     * @param OntoClass  $domain    The domain to be disassociated
     * @param OntoClass  $range    The range to be disassociated
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteProfileInheritedPropertyAssociationAction(Property $property, Profile $profile, OntoClass $domain, OntoClass $range, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile);
        $em = $this->getDoctrine()->getManager();


        $profileAssociation = $em->getRepository('AppBundle:ProfileAssociation')
            ->findOneBy(array('profile' => $profile->getId(), 'property' => $property->getId(), 'domain' => $domain->getId(), 'range' => $range->getId()));

        $systemType = $em->getRepository('AppBundle:SystemType')->find(6); //systemType 6 = rejected

        $profileAssociation->setSystemType($systemType);

        $em->persist($profile);
        $em->flush();

        return new JsonResponse(null, 204);

    }

    /**
     * @Route("/profile/{profile}/class/{class}/properties/edit", name="profile_properties_edit")
     * @param Profile $profile
     * @param OntoClass $class
     * @return Response the rendered template
     */
    public function editProfilePropertiesAction(OntoClass $class, Profile $profile)
    {
        $this->denyAccessUnlessGranted('edit', $profile);
        //TODO: mettre erreur 403 en cas d'accès à une classe non associée au profil
        return $this->render('profile/editProperties.html.twig', array(
            'class' => $class,
            'profile' => $profile
        ));
    }

    /**
     * @Route("/selectable-outgoing-properties/profile/{profile}/class/{class}/json", name="selectable_outgoing_properties_class_profile_json")
     * @Method("GET")
     * @param Profile $profile
     * @param OntoClass $class
     * @return JsonResponse a Json formatted list representation of outgoing Properties selectable by Class and Profile
     */
    public function getSelectableOutgoingPropertiesByClassAndProfile(OntoClass $class, Profile $profile)
    {
        try {
            $em = $this->getDoctrine()->getManager();
            $properties = $em->getRepository('AppBundle:Property')
                ->findOutgoingPropertiesByClassAndProfileId($class, $profile);
            $data['data'] = $properties;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($properties)) {
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/selectable-incoming-properties/profile/{profile}/class/{class}/json", name="selectable_incoming_properties_class_profile_json")
     * @Method("GET")
     * @param Profile $profile
     * @param OntoClass $class
     * @return JsonResponse a Json formatted list representation of incoming Properties selectable by Class and Profile
     */
    public function getSelectableIncomingPropertiesByClassAndProfile(OntoClass $class, Profile $profile)
    {
        try{
            $em = $this->getDoctrine()->getManager();
            $properties = $em->getRepository('AppBundle:Property')
                ->findIncomingPropertiesByClassAndProfileId($class, $profile);
            $data['data'] = $properties;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($properties)) {
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/selectable-outgoing-inherited-properties/profile/{profile}/class/{class}/json", name="selectable_outgoing_inherited_properties_class_profile_json")
     * @Method("GET")
     * @param Profile $profile
     * @param OntoClass $class
     * @return JsonResponse a Json formatted list representation of outgoing inherited Properties selectable by Class and Profile
     */
    public function getSelectableOutgoingInheritedPropertiesByClassAndProfile(OntoClass $class, Profile $profile)
    {
        try{
            $em = $this->getDoctrine()->getManager();
            $properties = $em->getRepository('AppBundle:Property')
                ->findOutgoingInheritedPropertiesByClassAndProfileId($class, $profile);
            $data['data'] = $properties;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($properties)) {
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/selectable-incoming-inherited-properties/profile/{profile}/class/{class}/json", name="selectable_incoming_inherited_properties_class_profile_json")
     * @Method("GET")
     * @param Profile $profile
     * @param OntoClass $class
     * @return JsonResponse a Json formatted list representation of incoming inherited Properties selectable by Class and Profile
     */
    public function getSelectableIncomingInheritedPropertiesByClassAndProfile(OntoClass $class, Profile $profile)
    {
        try{
            $em = $this->getDoctrine()->getManager();
            $properties = $em->getRepository('AppBundle:Property')
                ->findIncomingInheritedPropertiesByClassAndProfileId($class, $profile);
            $data['data'] = $properties;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($properties)) {
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/profile/{id}/json", name="profile_json")
     * @Method("GET")
     * @param Profile $profile
     * @return JsonResponse a Json formatted graph representation of Profile
     */
    public function getGraphJson(Profile $profile)
    {
        $em = $this->getDoctrine()->getManager();
        $profile = $em->getRepository('AppBundle:Profile')
            ->findProfileGraph($profile);

        return new JsonResponse($profile[0]['json'],200, array(), true);
    }

}