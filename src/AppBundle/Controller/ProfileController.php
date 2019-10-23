<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 17/11/2017
 * Time: 18:09
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Label;
use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\Profile;
use AppBundle\Entity\ProfileAssociation;
use AppBundle\Entity\Project;
use AppBundle\Entity\Property;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\ProfileEditForm;
use AppBundle\Form\ProfileQuickAddForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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
     * @Route("profile/new/{project}", name="profile_new")
     */
    public function newAction(Request $request, Project $project)
    {
        $profile = new Profile();

        $this->denyAccessUnlessGranted('edit', $project);


        $em = $this->getDoctrine()->getManager();
        $systemTypeDescription = $em->getRepository('AppBundle:SystemType')->find(16); //systemType 16 = description
        $systemTypeAdditionalNote = $em->getRepository('AppBundle:SystemType')->find(12); //systemType 12 = additional note

        $description = new TextProperty();
        $description->setProfile($profile);
        $description->setSystemType($systemTypeDescription);
        $description->setCreator($this->getUser());
        $description->setModifier($this->getUser());
        $description->setCreationTime(new \DateTime('now'));
        $description->setModificationTime(new \DateTime('now'));

        $profile->addTextProperty($description);

        $label = new Label();
        $label->setProfile($profile);
        $label->setIsStandardLabelForLanguage(true);
        $label->setCreator($this->getUser());
        $label->setModifier($this->getUser());
        $label->setCreationTime(new \DateTime('now'));
        $label->setModificationTime(new \DateTime('now'));

        $profile->setIsOngoing(true);
        $profile->setProjectOfBelonging($project);
        $profile->addLabel($label);
        $profile->setCreator($this->getUser());
        $profile->setModifier($this->getUser());

        $form = $this->createForm(ProfileQuickAddForm::class, $profile);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $profile = $form->getData();
            $profile->setIsOngoing(true);
            $profile->setProjectOfBelonging($project);
            $profile->setCreator($this->getUser());
            $profile->setModifier($this->getUser());
            $profile->setCreationTime(new \DateTime('now'));
            $profile->setModificationTime(new \DateTime('now'));

            if($profile->getTextProperties()->containsKey(1)){
                $profile->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $profile->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $profile->getTextProperties()[1]->setSystemType($systemTypeAdditionalNote);
                $profile->getTextProperties()[1]->setClass($profile);
            }


            $em = $this->getDoctrine()->getManager();
            $em->persist($profile);
            $em->flush();

            return $this->redirectToRoute('profile_show', [
                'id' => $profile->getId()
            ]);

        }

        $em = $this->getDoctrine()->getManager();


        return $this->render('profile/new.html.twig', [
            'profile' => $profile,
            'profileForm' => $form->createView()
        ]);
    }

    /**
     * @Route("/profile/{id}/edit", name="profile_edit")
     * @param Profile $profile
     * @param Request $request
     * @return Response the rendered template
     */
    public function editAction(Profile $profile, Request $request)
    {

        if(is_null($profile)) {
            throw $this->createNotFoundException('The profile n° '.$profile->getId().' does not exist. Please contact an administrator.');
        }

        $this->denyAccessUnlessGranted('edit', $profile);

        $profile->setModifier($this->getUser());

        $form = $this->createForm(ProfileEditForm::class, $profile);

        $em = $this->getDoctrine()->getManager();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $profile->setModifier($this->getUser());
            $em->persist($profile);
            $em->flush();

            $this->addFlash('success', 'Profile Updated!');

            return $this->redirectToRoute('profile_edit', [
                'id' => $profile->getId(),
                '_fragment' => 'identification'
            ]);
        }

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
            'profileIdentificationForm' => $form->createView(),
            'classes' => $classes,
            'selectableClasses' => $selectableClasses,
            'rootNamespaces' => $rootNamespaces,
            'properties' => $properties,
            'profileAssociations' => $profileAssociations
        ));
    }

    /**
     * @Route("/profile/{id}/publish", name="profile_publish")
     * @param Profile $profile
     * @param Request $request
     * @return Response the rendered template
     */
    public function publishAction(Profile $profile, Request $request)
    {
        if(is_null($profile)) {
            throw $this->createNotFoundException('The profile n° '.$profile->getId().' does not exist. Please contact an administrator.');
        }

        //only the project of belonging administrator car publish a profile
        $this->denyAccessUnlessGranted('full_edit', $profile->getProjectOfBelonging());

        $em = $this->getDoctrine()->getManager();

        $profile->setIsOngoing(false);
        $profile->setStartDate(new \DateTime('now'));
        $profile->setWasClosedAt(new \DateTime('now'));

        $em->persist($profile);
        $em->flush();

        //we delete the word "ongoing" from all the profile labels
        foreach ($profile->getLabels() as $label) {
            $txt = $label->getLabel();
            $label->setLabel(str_replace('ongoing', '', $txt));
            $em->persist($label);
            $em->flush();
        }

        $this->addFlash('success', 'Profile Published!');

        return $this->redirectToRoute('profile_edit', [
            'id' => $profile->getId(),
            '_fragment' => 'identification'
        ]);
    }

    /**
     * @Route("/profile/{id}/deprecate", name="profile_deprecate")
     * @param Profile $profile
     * @param Request $request
     * @return Response the rendered template
     */
    public function deprecateAction(Profile $profile, Request $request)
    {
        if(is_null($profile)) {
            throw $this->createNotFoundException('The profile n° '.$profile->getId().' does not exist. Please contact an administrator.');
        }

        //only the project of belonging administrator car deprecate a profile
        $this->denyAccessUnlessGranted('full_edit', $profile->getProjectOfBelonging());

        $em = $this->getDoctrine()->getManager();

        $profile->setEndDate(new \DateTime('now'));

        $em->persist($profile);
        $em->flush();

        $this->addFlash('success', 'Profile deprecated!');

        return $this->redirectToRoute('profile_edit', [
            'id' => $profile->getId(),
            '_fragment' => 'identification'
        ]);
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
            ->findOneBy(array('profile' => $profile->getId(), 'property' => $property->getId(), 'domain' => null, 'range' => null));

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
            ->findOneBy(array('profile' => $profile->getId(), 'property' => $property->getId(), 'domain' => null, 'range' => null));

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


        try {
            $profileAssociation = $em->getRepository('AppBundle:ProfileAssociation')
                ->findOneBy(array('profile' => $profile->getId(), 'property' => $property->getId(), 'domain' => $domain->getId(), 'range' => $range->getId()));

            $systemType = $em->getRepository('AppBundle:SystemType')->find(6); //systemType 6 = rejected

            $profileAssociation->setSystemType($systemType);

            $em->persist($profile);
            $em->flush();
        }

        catch (\Exception $e) {
            return new JsonResponse(null, 400, 'content-type:application/problem+json');
        }
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
            $data['recordsTotal'] = count($properties); //mandatory for datatable
            $data['recordsFiltered'] = count($properties); //mandatory for datatable
            $data['data'] = $properties;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
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
            $data['recordsTotal'] = count($properties); //mandatory for datatable
            $data['recordsFiltered'] = count($properties); //mandatory for datatable
            $data['data'] = $properties;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
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
            $data['recordsTotal'] = count($properties); //mandatory for datatable
            $data['recordsFiltered'] = count($properties); //mandatory for datatable
            $data['data'] = $properties;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
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
            $data['recordsTotal'] = count($properties); //mandatory for datatable
            $data['recordsFiltered'] = count($properties); //mandatory for datatable
            $data['data'] = $properties;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/selectable-descendent-class/profile/{profile}/class/{class}/property/{property}/json", name="selectable_descendent_class_profile_json")
     * @Method("GET")
     * @param Profile $profile
     * @param OntoClass $class
     * @param Property $property
     * @param Request $request
     * @return JsonResponse a Json formatted list representation of selectable descendent classes for properties by Class, Property and Profile
     */
    public function getSelectableDescendentClassByClassAndProfile(OntoClass $class, Profile $profile, Property $property, Request $request)
    {
        try {
            $searchTerm = $request->get('term'); //récupération du paramètre "term" envoyé par select2 pour la requête AJAX

            $em = $this->getDoctrine()->getManager();
            $classes = $em->getRepository('AppBundle:OntoClass')
                ->findDescendantsByProfileAndClassId($profile, $class, $property, $searchTerm);
            $data['results'] = $classes;
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
     * @Route("/profile/{id}/json", name="profile_json", schemes={"http"})
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