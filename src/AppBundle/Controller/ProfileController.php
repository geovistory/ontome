<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 17/11/2017
 * Time: 18:09
 */

namespace AppBundle\Controller;

use AppBundle\Entity\EntityUserProjectAssociation;
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
use AppBundle\Form\TextPropertyForm;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ProfileController  extends Controller
{

    /**
     * @Route("/profile/{id}", name="profile_show", requirements={"id"="^([0-9]+)|(profileID){1}$"})
     * @param Profile $profile
     * @return Response the rendered template
     */
    public function showAction(Profile $profile)
    {
        $em = $this->getDoctrine()->getManager();

        $classes = $em->getRepository('AppBundle:OntoClass')
            ->findClassesByProfileId($profile);

        //$properties = $em->getRepository('AppBundle:Property')->findPropertiesByProfileId($profile);

        $profileAssociations = $em->getRepository('AppBundle:ProfileAssociation')
            ->findBy(array('profile' => $profile));

        return $this->render('profile/show.html.twig', array(
            'profile' => $profile,
            'classes' => $classes,
            //'properties' => $properties,
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
     * @Route("profile/new/{project}", name="profile_new", requirements={"project"="^[0-9]+$"})
     */
    public function newAction(Request $request, Project $project)
    {
        $this->denyAccessUnlessGranted('edit', $project);

        $em = $this->getDoctrine()->getManager();

        $systemTypeDescription = $em->getRepository('AppBundle:SystemType')->find(16); //systemType 16 = description

        // Root
        $rootProfile = new Profile();
        $rootProfile->setIsRootProfile(true);
        $rootProfile->setIsOngoing(false);
        $rootProfile->setProjectOfBelonging($project);
        $rootProfile->setIsForcedPublication(false);
        $rootProfile->setCreator($this->getUser());
        $rootProfile->setModifier($this->getUser());
        $rootProfile->setCreationTime(new \DateTime('now'));
        $rootProfile->setModificationTime(new \DateTime('now'));

        // Root description (TextProperty)
        $rootDescription = new TextProperty();
        $rootDescription->setProfile($rootProfile);
        $rootDescription->setSystemType($systemTypeDescription);
        $rootDescription->setCreator($this->getUser());
        $rootDescription->setModifier($this->getUser());
        $rootDescription->setCreationTime(new \DateTime('now'));
        $rootDescription->setModificationTime(new \DateTime('now'));
        $rootProfile->addTextProperty($rootDescription);

        // Root label (Label)
        $rootProfileLabel = new Label();
        $rootProfileLabel->setIsStandardLabelForLanguage(true);
        $rootProfileLabel->setCreator($this->getUser());
        $rootProfileLabel->setModifier($this->getUser());
        $rootProfileLabel->setCreationTime(new \DateTime('now'));
        $rootProfileLabel->setModificationTime(new \DateTime('now'));
        $rootProfile->addLabel($rootProfileLabel);

        $allProfiles = $em->getRepository('AppBundle:Profile')->findAll();
        $allLabels = new ArrayCollection();
        foreach ($allProfiles as $profile){
            foreach ($profile->getLabels() as $label){
                $allLabels->add($label->getLabel());
            }
        }

        $form = $this->createForm(ProfileQuickAddForm::class, $rootProfile);
        // only handles data on POST
        $form->handleRequest($request);

        //Vérification si le label n'a jamais été utilisé ailleurs
        $isLabelValid = true;
        if($form->isSubmitted()){
            $labels = $form->get('labels');
            foreach ($labels as $label){
                if($allLabels->contains($label->get('label')->getData())){
                    $label->get('label')->addError(new FormError('This label is already used by another profile, please enter a different one.'));
                    $isLabelValid = false;
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid() && $isLabelValid) {
            // Root profile with form submissions (Description+lang, Label+lang)
            $rootProfile = $form->getData();

            // Create an ongoing profile
            $ongoingProfile = new Profile();
            $ongoingProfile->setIsRootProfile(false);
            $ongoingProfile->setIsOngoing(true);
            $ongoingProfile->setProjectOfBelonging($project);
            $ongoingProfile->setIsForcedPublication(false);
            $ongoingProfile->setVersion(1);
            $ongoingProfile->setRootProfile($rootProfile);
            $ongoingProfile->setCreator($this->getUser());
            $ongoingProfile->setModifier($this->getUser());
            $ongoingProfile->setCreationTime(new \DateTime('now'));
            $ongoingProfile->setModificationTime(new \DateTime('now'));
            $ongoingProfile->setIsForcedPublication(false);

            // Create a label for ongoing profile
            $ongoingProfileLabel = clone $rootProfile->getLabels()[0];
            $ongoingProfileLabel->setLabel($ongoingProfileLabel->getLabel().' ongoing');
            $ongoingProfile->addLabel($ongoingProfileLabel);

            // Create a description for ongoing profile
            if($rootProfile->getTextProperties()->containsKey(1)){
                // Here we have an ongoing description different ([1]) from root description ([0])
                // Add metadata
                $ongoingDescription = $rootProfile->getTextProperties()[1];
                $ongoingDescription->setSystemType($systemTypeDescription);
                $ongoingDescription->setCreator($this->getUser());
                $ongoingDescription->setModifier($this->getUser());
                $ongoingDescription->setCreationTime(new \DateTime('now'));
                $ongoingDescription->setModificationTime(new \DateTime('now'));
                $ongoingDescription->setLanguageIsoCode($rootProfileLabel->getLanguageIsoCode());
            }
            else {
                // Here we have a same ongoing and root description (form are not expanded). Clone the first TxtP for Ongoing.
                $ongoingDescription = clone $rootProfile->getTextProperties()[0];
            }
            $ongoingDescription->setProfile($ongoingProfile);
            $ongoingProfile->addTextProperty($ongoingDescription);

            $em = $this->getDoctrine()->getManager();
            $em->persist($rootProfile);
            $em->persist($ongoingProfile);
            $em->flush();

            return $this->redirectToRoute('profile_show', [
                'id' => $rootProfile->getId()
            ]);

        }


        return $this->render('profile/new.html.twig', [
            'profile' => $rootProfile,
            'profileForm' => $form->createView()
        ]);
    }

    /**
     * @Route("/profile/{id}/edit", name="profile_edit", requirements={"id"="^[0-9]+$"})
     * @param Profile $profile
     * @param Request $request
     * @return Response the rendered template
     */
    public function editAction(Profile $profile, Request $request)
    {

        if(is_null($profile)) {
            throw $this->createNotFoundException('The profile n° '.$profile->getId().' does not exist. Please contact an administrator.');
        }

        if($profile->getIsRootProfile()) {
            $this->denyAccessUnlessGranted('edit', $profile);

        }
        else {
            $this->denyAccessUnlessGranted('edit', $profile->getRootProfile());
        }

        $profile->setModifier($this->getUser());

        $form = $this->createForm(ProfileEditForm::class, $profile);

        $em = $this->getDoctrine()->getManager();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $profile->setModifier($this->getUser());
            $profile->setModificationTime(new \DateTime('now'));
            if (!$profile->getIsRootProfile()) {
                $profile->setProjectOfBelonging($profile->getRootProfile()->getProjectOfBelonging());
            }
            else {
                foreach ($profile->getChildProfiles() as $childProfile) {
                    $childProfile->setProjectOfBelonging($profile->getProjectOfBelonging());
                    $childProfile->setModifier($this->getUser());
                    $childProfile->setModificationTime(new \DateTime('now'));
                    $em->persist($childProfile);
                }
            }
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

        //$properties = $em->getRepository('AppBundle:Property')->findPropertiesByProfileId($profile);

        $rootNamespacesBrut = $em->getRepository('AppBundle:OntoNamespace')
            ->findAllNonAssociatedToProfileByProfileId($profile);

        $rootNamespaces = new ArrayCollection();
        foreach($rootNamespacesBrut as $rootNamespace){
            $rootNamespaces->add($em->getRepository('AppBundle:OntoNamespace')->find($rootNamespace['id']));
        }
        $rootNamespaces = $rootNamespaces->filter(function($v){return $v->getId() != 5;});


        $profileAssociations = $em->getRepository('AppBundle:ProfileAssociation')
            ->findBy(array('profile' => $profile));

        return $this->render('profile/edit.html.twig', array(
            'profile' => $profile,
            'profileIdentificationForm' => $form->createView(),
            'classes' => $classes,
            'selectableClasses' => $selectableClasses,
            'rootNamespaces' => $rootNamespaces,
            //'properties' => $properties,
            'profileAssociations' => $profileAssociations
        ));
    }

    /**
     * @Route("/profile/{id}/publish", name="profile_publish", requirements={"id"="^([0-9]+)|(profileID){1}$"})
     * @param Profile $profile
     * @param Request $request
     * @return Response the rendered template
     */
    public function publishAction(Profile $profile, Request $request)
    {
        if(!$profile->isPublishable()){
            throw $this->createAccessDeniedException('The profile n° '.$profile->getId().' can\'t be published. Please verify your profile.');
        }

        if(is_null($profile)) {
            throw $this->createNotFoundException('The profile n° '.$profile->getId().' does not exist. Please contact an administrator.');
        }

        //only the project of belonging administrator car publish a profile
        $this->denyAccessUnlessGranted('full_edit', $profile->getProjectOfBelonging());

        $em = $this->getDoctrine()->getManager();

        $profile->setIsOngoing(false);
        $profile->setIsForcedPublication(false);
        $profile->setStartDate(new \DateTime('now'));
        $profile->setWasClosedAt(new \DateTime('now'));
        $stateOfVisibility = $profile->getIsVisible();
        $profile->setIsVisible(true);

        $em->persist($profile);
        $em->flush();

        //we delete the word "ongoing" from all the profile labels
        foreach ($profile->getLabels() as $label) {
            $txt = $label->getLabel();
            $label->setLabel(str_replace('ongoing', '', $txt));
            $em->persist($label);
            $em->flush();
        }

        //Duplication of the published profile to create a new ongoing one
        $newProfile = new Profile();

        $newProfile->setIsOngoing(true);
        $newProfile->setIsForcedPublication(false);
        $newProfile->setVersion($profile->getVersion()+1);
        $newProfile->setIsRootProfile(false);
        $newProfile->setRootProfile($profile->getRootProfile());
        $newProfile->setProjectOfBelonging($profile->getRootProfile()->getProjectOfBelonging());

        $newProfile->setCreator($this->getUser());
        $newProfile->setModifier($this->getUser());
        $newProfile->setCreationTime(new \DateTime('now'));
        $newProfile->setModificationTime(new \DateTime('now'));
        $newProfile->setIsVisible($stateOfVisibility);

        foreach ($profile->getTextProperties() as $textProperty){
            $newTextProperty = clone $textProperty;
            $newProfile->addTextProperty($newTextProperty);
        }

        foreach ($profile->getLabels() as $label){
            $newLabel = clone $label;
            $newLabel->setLabel(str_replace('ongoing', '', $profile->getStandardLabel()).' ongoing'); // On évite les "ongoing" en trop
            $newProfile->addLabel($newLabel);
        }

        foreach ($profile->getProfileAssociations() as $profileAssociation){
            $newProfileAssociation = clone $profileAssociation;
            $newProfile->addProfileAssociation($newProfileAssociation);
        }

        foreach ($profile->getNamespaces() as $namespace){
            $newProfile->addNamespace($namespace);
        }

        $em->persist($newProfile);
        $em->flush();

        $this->addFlash('success', 'Profile Published!');

        return $this->redirectToRoute('profile_edit', [
            'id' => $profile->getId(),
            '_fragment' => 'identification'
        ]);
    }

    /**
     * @Route("/profile/{id}/deprecate", name="profile_deprecate", requirements={"id"="^([0-9]+)|(profileID){1}$"})
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
     * @Route("/profile/{profile}/namespace/{namespace}/add", name="profile_namespace_association", requirements={"profile"="^([0-9]+)|(profileID)$", "namespace"="^([0-9]+)|(selectedValue)$"})
     * @Method({ "POST"})
     * @param OntoNamespace  $namespace    The namespace to be associated with a profile
     * @param Profile  $profile    The profile to be associated with a namespace
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted namespaces list
     */
    public function newProfileNamespaceAssociationAction(OntoNamespace $namespace, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile->getRootProfile());

        $arrayAllReferencesNamespacesForProfile = new ArrayCollection();
        foreach($profile->getNamespaces() as $prfnamespace){
            foreach ($prfnamespace->getAllReferencedNamespaces() as $referencedNamespace){
                if(!$arrayAllReferencesNamespacesForProfile->contains($referencedNamespace) and $referencedNamespace != $namespace){
                    $arrayAllReferencesNamespacesForProfile->add($referencedNamespace);
                }
            }
        }

        if($namespace->getIsTopLevelNamespace()) {
            $status = 'Error';
            $message = 'This namespace is not valid';
        }
        else if ($profile->getNamespaces()->contains($namespace) or $arrayAllReferencesNamespacesForProfile->contains($namespace)) {
            $status = 'Error';
            $message = 'This namespace is already used by this profile';
        }
        else {
            $em = $this->getDoctrine()->getManager();
            $profile->addNamespace($namespace);
            foreach ($namespace->getAllReferencedNamespaces() as $referencedNamespace){
                if(!$arrayAllReferencesNamespacesForProfile->contains($referencedNamespace) and $referencedNamespace != $namespace){
                    $arrayAllReferencesNamespacesForProfile->add($referencedNamespace);
                }
            }
            //Si l'un de ces namespaces références est déjà associé - supprimer cette association.
            foreach ($profile->getNamespaces() as $prfnamespace){
                if($arrayAllReferencesNamespacesForProfile->contains($prfnamespace)){
                    $profile->removeNamespace($prfnamespace);

                    //Mettre les eupa concernés à 29
                    $userProjectAssociations = $em->getRepository('AppBundle:UserProjectAssociation')->findByProject($profile->getProjectOfBelonging());
                    foreach ($userProjectAssociations as $userProjectAssociation) {
                        $eupas = $em->getRepository('AppBundle:EntityUserProjectAssociation')->findBy(array(
                            'userProjectAssociation' => $userProjectAssociation, 'namespace' => $prfnamespace));
                        foreach ($eupas as $eupa) {
                            $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(29); //systemType 29 = Unselected namespace for user preference
                            $eupa->setSystemType($systemTypeSelected);
                            $em->persist($eupa);
                        }
                    }
                }
            }
            $em->persist($profile);

            // Créer les entity_to_user_project pour les activer par défaut
            $userProjectAssociations = $em->getRepository('AppBundle:UserProjectAssociation')->findByProject($profile->getProjectOfBelonging());
            foreach ($userProjectAssociations as $userProjectAssociation) {
                // Vérifier si l'association EUPA n'existe déjà pas (chaque EUPA doit être unique)
                $eupas = $em->getRepository('AppBundle:EntityUserProjectAssociation')->findBy(array(
                    'userProjectAssociation' => $userProjectAssociation, 'namespace' => $namespace));
                if(count($eupas) == 0) {
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
                }
            }

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
     * @Route("/profile/{profile}/namespace/{namespace}/delete", name="profile_namespace_disassociation", requirements={"profile"="^([0-9]+)|(profileID){1}$", "namespace"="^([0-9]+)|(selectedValue){1}$"})
     * @Method({ "DELETE"})
     * @param OntoNamespace  $namespace    The namespace to be disassociated from a profile
     * @param Profile  $profile    The profile to be disassociated from a namespace
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteProfileNamespaceAssociationAction(OntoNamespace $namespace, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile->getRootProfile());

        $profile->removeNamespace($namespace);
        $em = $this->getDoctrine()->getManager();
        $em->persist($profile);
        $em->flush();

        return new JsonResponse(null, 204);
    }

    /**
     * @Route("/profile/{profile}/namespace/{associatedNamespace}/newNamespace/{newAssociatedNamespace}/change", name="namespace_associated_profile_change", requirements={"profile"="^([0-9]+)|(profileID){1}$", "associatedNamespace"="^([0-9]+)|(oldNsId){1}$", "newAssociatedNamespace"="^([0-9]+)|(newNsId){1}$"})
     * @Method({ "GET"})
     * @param Profile  $profile    The profile to be changed from an associated namespace
     * @param OntoNamespace  $associatedNamespace    The associated namespace to be changed from a new associated namespace
     * @param OntoNamespace  $newAssociatedNamespace    The new associated namespace to be changed from an associated namespace
     * @return JsonResponse
     */
    public function changeReferencedNamespaceAssociationAction(Profile $profile, OntoNamespace $associatedNamespace, OntoNamespace $newAssociatedNamespace, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile->getRootProfile());

        $em = $this->getDoctrine()->getManager();
        $profile->removeNamespace($associatedNamespace);

        if($newAssociatedNamespace->getIsTopLevelNamespace()) {
            $status = 'Error';
            $message = 'This namespace is not valid';
        }
        else if ($profile->getNamespaces()->contains($newAssociatedNamespace)) {
            $status = 'Error';
            $message = 'This namespace is already used by this profile';
        }
        else {
            $profile->addNamespace($newAssociatedNamespace);
            $em = $this->getDoctrine()->getManager();
            $em->persist($profile);

            // Créer les entity_to_user_project pour les activer par défaut
            $userProjectAssociations = $em->getRepository('AppBundle:UserProjectAssociation')->findByProject($profile->getProjectOfBelonging());
            foreach ($userProjectAssociations as $userProjectAssociation) {
                // Vérifier si l'association EUPA n'existe déjà pas (chaque EUPA doit être unique)
                $eupas = $em->getRepository('AppBundle:EntityUserProjectAssociation')->findBy(array('userProjectAssociation' => $userProjectAssociation, 'namespace' => $newAssociatedNamespace));
                if(count($eupas) == 0){
                    $eupa = new EntityUserProjectAssociation();
                    $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(25); //systemType 25 = Selected namespace for user preference
                    $eupa->setNamespace($newAssociatedNamespace);
                    $eupa->setUserProjectAssociation($userProjectAssociation);
                    $eupa->setSystemType($systemTypeSelected);
                    $eupa->setCreator($this->getUser());
                    $eupa->setModifier($this->getUser());
                    $eupa->setCreationTime(new \DateTime('now'));
                    $eupa->setModificationTime(new \DateTime('now'));
                    $em->persist($eupa);
                }
            }

            // Modifier les profileAssociations class/property-associatedNamespace
            foreach ($profile->getProfileAssociations() as $profileAssociation){
                if($profileAssociation->getEntityNamespaceForVersion() == $associatedNamespace
                    and in_array($profileAssociation->getSystemType()->getId(),array(5,6))){

                    if(!is_null($profileAssociation->getClass())){
                        foreach ($profileAssociation->getClass()->getClassVersions() as $classVersion){
                            if($classVersion->getNamespaceForVersion() == $newAssociatedNamespace){
                                $profileAssociation->setEntityNamespaceForVersion($newAssociatedNamespace);
                            }
                        }
                    }

                    if(!is_null($profileAssociation->getProperty())){
                        foreach ($profileAssociation->getProperty()->getPropertyVersions() as $propertyVersion){
                            if($propertyVersion->getNamespaceForVersion() == $newAssociatedNamespace){
                                $profileAssociation->setEntityNamespaceForVersion($newAssociatedNamespace);
                            }
                        }
                    }
                }
            }

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
     * @Route("/selectable-classes/profile/{profile}/json", name="selectable_classes_profile_json", requirements={"profile"="^([0-9]+)|(profileID){1}$"})
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
            return new JsonResponse(null,404, array('content-type:application/problem+json'));
        }

        if(empty($classes)) {
            return new JsonResponse('{"data":[]}',200, array(), true);
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/associated-classes/profile/{profile}/json", name="associated_classes_profile_json", requirements={"profile"="^([0-9]+)|(profileID){1}$"})
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
            return new JsonResponse(null,404, array('content-type:application/problem+json'));
        }

        if(empty($classes)) {
            return new JsonResponse('{"data":[]}',200, array(), true);
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/profile/{profile}/class/{class}/add", name="profile_class_association", requirements={"profile"="^([0-9]+)|(profileID){1}$", "class"="^([0-9]+)|(classID){1}$"})
     * @Method({"POST"})
     * @param OntoClass  $class    The class to be associated with a profile
     * @param Profile  $profile    The profile to be associated with a namespace
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted namespaces list
     */
    public function newProfileClassAssociationAction(OntoClass $class, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile->getRootProfile());

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

            $namespacesId = array();
            foreach($profile->getNamespaces() as $namespace){
                $namespacesId[] = $namespace->getId();
                foreach($namespace->getAllReferencedNamespaces() as $referencedNamespace){
                    if(!in_array($referencedNamespace->getId(), $namespacesId)){
                        $namespacesId[] = $referencedNamespace->getId();
                    }
                }
            }
            $classVersion = $em->getRepository("AppBundle:OntoClassVersion")->findClassVersionByClassAndNamespacesId($class, $namespacesId);
            $profileAssociation->setEntityNamespaceForVersion($classVersion->getNamespaceForVersion());

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
     * @Route("/profile/{profile}/property/{property}/add", name="profile_property_association", requirements={"profile"="^([0-9]+)|(profileID){1}$", "property"="^([0-9]+)|(propertyID){1}$"})
     * @Method({ "POST"})
     * @param Property  $property    The property to be associated with a profile
     * @param Profile  $profile    The profile to be associated with a namespace
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted namespaces list
     */
    public function newProfilePropertyAssociationAction(Property $property, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile->getRootProfile());

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

            $namespacesId = array();
            foreach($profile->getNamespaces() as $namespace){
                $namespacesId[] = $namespace->getId();
                foreach($namespace->getAllReferencedNamespaces() as $referencedNamespace){
                    if(!in_array($referencedNamespace->getId(), $namespacesId)){
                        $namespacesId[] = $referencedNamespace->getId();
                    }
                }
            }
            $propertyVersion = $em->getRepository("AppBundle:PropertyVersion")->findPropertyVersionByPropertyAndNamespacesId($property, $namespacesId);
            $profileAssociation->setEntityNamespaceForVersion($propertyVersion->getNamespaceForVersion());

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
     * @Route("/profile/{profile}/property/{property}/domain/{domain}/range/{range}/add", name="profile_inherited_property_association", requirements={"profile"="^([0-9]+)|(profileID){1}$", "property"="^([0-9]+)|(propertyID){1}$", "domain"="^([0-9]+)|(domainID){1}$", "range"="^([0-9]+)|(rangeID){1}$"})
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
        $this->denyAccessUnlessGranted('edit', $profile->getRootProfile());

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

            $namespacesId = array();
            foreach($profile->getNamespaces() as $namespace){
                $namespacesId[] = $namespace->getId();
                foreach($namespace->getAllReferencedNamespaces() as $referencedNamespace){
                    if(!in_array($referencedNamespace->getId(), $namespacesId)){
                        $namespacesId[] = $referencedNamespace->getId();
                    }
                }
            }
            $propertyVersion = $em->getRepository("AppBundle:PropertyVersion")->findPropertyVersionByPropertyAndNamespacesId($property, $namespacesId);
            $profileAssociation->setEntityNamespaceForVersion($propertyVersion->getNamespaceForVersion());

            $profileAssociation->setDomain($domain);
            $domainVersion = $em->getRepository("AppBundle:OntoClassVersion")->findClassVersionByClassAndNamespacesId($domain, $namespacesId);
            $profileAssociation->setDomainNamespace($domainVersion->getNamespaceForVersion());

            $profileAssociation->setRange($range);
            $rangeVersion = $em->getRepository("AppBundle:OntoClassVersion")->findClassVersionByClassAndNamespacesId($range, $namespacesId);
            $profileAssociation->setRangeNamespace($rangeVersion->getNamespaceForVersion());

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
     * @Route("/profile/{profile}/class/{class}/delete", name="profile_class_disassociation", requirements={"profile"="^([0-9]+)|(profileID){1}$", "class"="^([0-9]+)|(classID){1}$"})
     * @Method({ "POST"})
     * @param OntoClass  $class    The class to be disassociated from a profile
     * @param Profile  $profile    The profile to be disassociated from a namespace
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteProfileClassAssociationAction(OntoClass $class, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile->getRootProfile());
        $em = $this->getDoctrine()->getManager();

        $profileAssociation = $em->getRepository('AppBundle:ProfileAssociation')
            ->findOneBy(array('profile' => $profile->getId(), 'class' => $class->getId()));

        $systemType = $em->getRepository('AppBundle:SystemType')->find(6); //systemType 6 = rejected

        $profileAssociation->setSystemType($systemType);

        $classNamespace = $profileAssociation->getEntityNamespaceForVersion();

        $profileAssociationsWithPropertiesToDeselect = $this->getProfileAssociationsWithPropertiesDeselectablesIfProfileAssociationWithThisClassWillDeselect($profile, $class);
        if(!empty($profileAssociationsWithPropertiesToDeselect)){
            foreach ($profileAssociationsWithPropertiesToDeselect as $profileAssociation){
                $systemType = $em->getRepository('AppBundle:SystemType')->find(6); //systemType 6 = rejected
                $profileAssociation->setSystemType($systemType);
            }
        }

        $em->persist($profile);
        $em->flush();

        // Renvoyer une réponse si le profil n'a plus aucune association active avec ce namespace
        // (pour réactiver le bouton remove)
        $isRemovableNamespace = false;
        if(!is_null($classNamespace)){
            $profileAssociations = $em->getRepository('AppBundle:ProfileAssociation')
                ->findBy(array('profile' => $profile->getId(), 'entityNamespaceForVersion' => $classNamespace->getId(), 'systemType' => '5'));
            if(count($profileAssociations) == 0){
                $isRemovableNamespace = true;
            }
        }
        $response = array('isRemovableNamespace' => $isRemovableNamespace);

        return new JsonResponse($response);
    }

    /**
     * @Route("/profile/{profile}/property/{property}/delete", name="profile_property_disassociation", requirements={"profile"="^([0-9]+)|(profileID){1}$", "property"="^([0-9]+)|(propertyID){1}$"})
     * @Method({ "POST"})
     * @param Property  $property    The property to be disassociated from a profile
     * @param Profile  $profile    The profile to be disassociated from a namespace
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteProfilePropertyAssociationAction(Property $property, Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile->getRootProfile());
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
     * @Route("/profile/{profile}/property/{property}/domain/{domain}/range/{range}/delete", name="profile_inherited_property_disassociation", requirements={"profile"="^([0-9]+)|(profileID){1}$", "property"="^([0-9]+)|(propertyID){1}$", "domain"="^([0-9]+)|(domainID){1}$", "range"="^([0-9]+)|(rangeID){1}$"})
     * @Method({ "POST"})
     * @param Property  $property    The property to be disassociated from a profile
     * @param Profile  $profile    The profile to be disassociated from a namespace
     * @param OntoClass  $domain    The domain to be disassociated
     * @param OntoClass  $range    The range to be disassociated
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteProfileInheritedPropertyAssociationAction(Property $property, Profile $profile, OntoClass $domain, OntoClass $range, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $profile->getRootProfile());
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
            return new JsonResponse(null, 400, array('content-type:application/problem+json'));
        }
        return new JsonResponse(null, 204);

    }

    /**
     * @Route("/profile/{profile}/class/{class}/properties/edit", name="profile_properties_edit", requirements={"profile"="^([0-9]+)|(profileID){1}$", "class"="^([0-9]+)|(classID){1}$"})
     * @param Profile $profile
     * @param OntoClass $class
     * @return Response the rendered template
     */
    public function editProfilePropertiesAction(OntoClass $class, Profile $profile)
    {
        $this->denyAccessUnlessGranted('edit', $profile->getRootProfile());
        //TODO: mettre erreur 403 en cas d'accès à une classe non associée au profil
        return $this->render('profile/editProperties.html.twig', array(
            'class' => $class,
            'profile' => $profile
        ));
    }

    /**
     * @Route("/selectable-outgoing-properties/profile/{profile}/class/{class}/json", name="selectable_outgoing_properties_class_profile_json", requirements={"profile"="^([0-9]+)|(profileID){1}$", "class"="^([0-9]+)|(classID){1}$"})
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
            return new JsonResponse(null,404, array('content-type:application/problem+json'));
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/selectable-incoming-properties/profile/{profile}/class/{class}/json", name="selectable_incoming_properties_class_profile_json", requirements={"profile"="^([0-9]+)|(profileID){1}$", "class"="^([0-9]+)|(classID){1}$"})
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
            return new JsonResponse(null,404, array('content-type:application/problem+json'));
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/selectable-outgoing-inherited-properties/profile/{profile}/class/{class}/json", name="selectable_outgoing_inherited_properties_class_profile_json", requirements={"profile"="^([0-9]+)|(profileID){1}$", "class"="^([0-9]+)|(classID){1}$"})
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
            return new JsonResponse(null,404, array('content-type:application/problem+json'));
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/selectable-incoming-inherited-properties/profile/{profile}/class/{class}/json", name="selectable_incoming_inherited_properties_class_profile_json", requirements={"profile"="^([0-9]+)|(profileID){1}$", "class"="^([0-9]+)|(classID){1}$"})
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
            return new JsonResponse(null,404, array('content-type:application/problem+json'));
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/selectable-descendent-class/profile/{profile}/class/{class}/property/{property}/json", name="selectable_descendent_class_profile_json", requirements={"profile"="^([0-9]+)|(profileID){1}$", "class"="^([0-9]+)|(classID){1}$", "property"="^([0-9]+)|(propertyID){1}$"})
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
            return new JsonResponse(null,404, array('content-type:application/problem+json'));
        }

        if(empty($classes)) {
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/selectable-descendent-domain/profile/{profile}/domain/{domain}/range/{range}/property/{property}/json", name="selectable_descendent_domain_profile_json", requirements={"profile"="^([0-9]+)|(profileID){1}$", "domain"="^([0-9]+)|(domainID){1}$", "range"="^([0-9]+)|(rangeID){1}$", "property"="^([0-9]+)|(propertyID){1}$"})
     * @Method("GET")
     * @param Profile $profile
     * @param OntoClass $domain
     * @param OntoClass $range
     * @param Property $property
     * @param Request $request
     * @return JsonResponse a Json formatted list representation of selectable descendent classes for properties by Class, Property and Profile
     */
    public function getSelectableDescendentDomain(OntoClass $domain, OntoClass $range, Profile $profile, Property $property, Request $request)
    {
        try {
            $searchTerm = $request->get('term'); //récupération du paramètre "term" envoyé par select2 pour la requête AJAX

            $em = $this->getDoctrine()->getManager();
            $classes = $em->getRepository('AppBundle:OntoClass')
                ->findDescendantsDomainByProfileAndDomainAndRangeId($profile, $domain, $range, $property, $searchTerm);
            $data['results'] = $classes;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, array('content-type:application/problem+json'));
        }

        if(empty($classes)) {
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/selectable-descendent-range/profile/{profile}/domain/{domain}/range/{range}/property/{property}/json", name="selectable_descendent_range_profile_json", requirements={"profile"="^([0-9]+)|(profileID){1}$", "domain"="^([0-9]+)|(domainID){1}$", "range"="^([0-9]+)|(rangeID){1}$", "property"="^([0-9]+)|(propertyID){1}$"})
     * @Method("GET")
     * @param Profile $profile
     * @param OntoClass $domain
     * @param OntoClass $range
     * @param Property $property
     * @param Request $request
     * @return JsonResponse a Json formatted list representation of selectable descendent classes for properties by Class, Property and Profile
     */
    public function getSelectableDescendentRange(OntoClass $domain, OntoClass $range, Profile $profile, Property $property, Request $request)
    {
        try {
            $searchTerm = $request->get('term'); //récupération du paramètre "term" envoyé par select2 pour la requête AJAX

            $em = $this->getDoctrine()->getManager();
            $classes = $em->getRepository('AppBundle:OntoClass')
                ->findDescendantsRangeByProfileAndDomainAndRangeId($profile, $domain, $range, $property, $searchTerm);
            $data['results'] = $classes;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, array('content-type:application/problem+json'));
        }

        if(empty($classes)) {
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/profile/{id}/json", name="profile_json", schemes={"https"}, requirements={"id"="^[0-9]+$"})
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

    /**
     * @Route("/profileAssociationPropertyDeselectable/profile/{profile}/class/{class}/", name="profile_association_property_deselectable", schemes={"https"}, requirements={"profile"="^([0-9]+)|(profileID){1}$", "class"="^([0-9]+)|(classID){1}$"})
     * @Method("GET")
     * @param Profile $profile
     * @param OntoClass $class
     * @return ProfileAssociation[] l'ensemble des profileAssociations dont la propriété dont l'un uniquement de domain ou de la range est égale à $class et l'autre inferred
     */
    public function getProfileAssociationsWithPropertiesDeselectablesIfProfileAssociationWithThisClassWillDeselect(Profile $profile, OntoClass $class)
    {
        $profileAssociationsDeselectables = [];

        // Trouver les classes selected du profile
        $selectedClass = [];
        foreach($profile->getProfileAssociations() as $profileAssociation){
            if($profileAssociation->getSystemType()->getId() == 5 && !is_null($profileAssociation->getClass())){
                $selectedClass[] = $profileAssociation->getClass();
            }
        }

        foreach($profile->getProfileAssociations() as $profileAssociation){
            if($profileAssociation->getSystemType()->getId() == 5 && !is_null($profileAssociation->getProperty())){
                // Cas 1 : Propriété inherited : domain = class
                if($profileAssociation->getDomain() == $class){
                    // est ce que range est inferred
                    if(!in_array($profileAssociation->getRange(), $selectedClass)){
                        $profileAssociationsDeselectables[] = $profileAssociation;
                    }
                }
                // Cas 2 : Propriété inherited : range = class
                elseif($profileAssociation->getRange() == $class){
                    // est ce que domain est inferred
                    if(!in_array($profileAssociation->getDomain(), $selectedClass)){
                        $profileAssociationsDeselectables[] = $profileAssociation;
                    }
                }
                // Cas 3 : Propriété non inherited : property.domain = class
                elseif($profileAssociation->getProperty()->getPropertyVersionForDisplay($profileAssociation->getEntityNamespaceForVersion())->getDomain() == $class){
                    // est ce que range est inferred
                    if(!in_array($profileAssociation->getProperty()->getPropertyVersionForDisplay($profileAssociation->getEntityNamespaceForVersion())->getRange(), $selectedClass)){
                        $profileAssociationsDeselectables[] = $profileAssociation;
                    }

                }
                // Cas 4 : Propriété non inherited : property.range = class
                elseif($profileAssociation->getProperty()->getPropertyVersionForDisplay($profileAssociation->getEntityNamespaceForVersion())->getRange() == $class){
                    // est ce que range est inferred
                    if(!in_array($profileAssociation->getProperty()->getPropertyVersionForDisplay($profileAssociation->getEntityNamespaceForVersion())->getDomain(), $selectedClass)){
                        $profileAssociationsDeselectables[] = $profileAssociation;
                    }

                }
            }
        }

        return $profileAssociationsDeselectables;
    }

    /**
     * @Route("/alert-class-if-deselect/profile/{profile}/class/{class}/json", name="alert_class_if_deselect_json", requirements={"profile"="^([0-9]+)|(profileID){1}$", "class"="^([0-9]+)|(classID){1}$"})
     * @Method("POST")
     * @param Profile $profile
     * @param OntoClass $class
     * @return JsonResponse
     */
    public function getAlertClassIfDeselect(Profile $profile, OntoClass $class)
    {
        $message = 'not-alert';
        if(!empty($this->getProfileAssociationsWithPropertiesDeselectablesIfProfileAssociationWithThisClassWillDeselect($profile, $class))){
            $message = 'alert';
        }
        return new JsonResponse(array('message' => $message));
    }

    /**
     * @Route("/profile/{profile}/recreate", name="profile_recreate", requirements={"profile"="^[0-9]+$|(profileID){1}$"})
     * @Method({ "GET"})
     * @param Profile  $profile    The published profile to be recreate
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function recreateProfileFromPublishedProfileAction(Profile $profile, Request $request)
    {
        $this->denyAccessUnlessGranted('duplicate', $profile);
        $em = $this->getDoctrine()->getManager();

        $newProfile = new Profile();

        $newProfile->setStandardLabel($profile->getStandardLabel().' ongoing');
        $newProfile->setIsOngoing(true);
        $newProfile->setIsForcedPublication(false);
        $newProfile->setVersion($profile->getVersion()+1);
        $newProfile->setIsRootProfile(false);
        $newProfile->setRootProfile($profile->getRootProfile());
        $newProfile->setProjectOfBelonging($profile->getRootProfile()->getProjectOfBelonging());

        $newProfile->setCreator($this->getUser());
        $newProfile->setModifier($this->getUser());
        $newProfile->setCreationTime(new \DateTime('now'));
        $newProfile->setModificationTime(new \DateTime('now'));

        foreach ($profile->getTextProperties() as $textProperty){
            $newTextProperty = clone $textProperty;
            $newProfile->addTextProperty($newTextProperty);
        }

        foreach ($profile->getLabels() as $label){
            $newLabel = clone $label;
            $newLabel->setLabel($profile->getStandardLabel().' ongoing');
            $newProfile->addLabel($newLabel);
        }

        foreach ($profile->getProfileAssociations() as $profileAssociation){
            $newProfileAssociation = clone $profileAssociation;
            $newProfile->addProfileAssociation($newProfileAssociation);
        }

        foreach ($profile->getNamespaces() as $namespace){
            $newProfile->addNamespace($namespace);
        }

        $em->persist($newProfile);
        $em->flush();

        return $this->redirectToRoute('project_show', [
            'id' => $profile->getProjectOfBelonging()->getId(),
            '_fragment' => 'profiles'
        ]);

    }

    /**
     * @Route("/profileAssociation/{id}/custom", name="show_customisation", requirements={"id"="^([0-9]+)|(profileAssociationID){1}$"})
     * @param ProfileAssociation $profileAssociation
     * @return Response the rendered template
     */
    public function showCustomisationAction(ProfileAssociation $profileAssociation)
    {
        return $this->render('profileAssociation/show_customisation.html.twig', array('profileAssociation' => $profileAssociation));
    }

    /**
     * @Route("/profileAssociation/{id}/custom/edit", name="edit_customisation", requirements={"id"="^([0-9]+)|(profileAssociationID){1}$"})
     * @param Property $property
     * @param Profile $profile
     * @return Response the rendered template
     */
    public function editCustomisationAction(ProfileAssociation $profileAssociation, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        // Justification form
        $systemTypeJustification = $em->getRepository('AppBundle:SystemType')->find(15);
        $textPropertyJustification = $em->getRepository("AppBundle:TextProperty")->findOneBy(
            array("profileAssociation" => $profileAssociation->getId(), "systemType" => $systemTypeJustification->getId())
        );
        if(is_null($textPropertyJustification)){
            $textPropertyJustification = new TextProperty();
            $textPropertyJustification->setCreator($this->getUser());
            $textPropertyJustification->setCreationTime(new \DateTime('now'));
            $textPropertyJustification->setModifier($this->getUser());
            $textPropertyJustification->setModificationTime(new \DateTime('now'));
            $textPropertyJustification->setProfileAssociation($profileAssociation);
            $textPropertyJustification->setNamespaceForVersion($profileAssociation->getEntityNamespaceForVersion());
            $textPropertyJustification->setSystemType($systemTypeJustification);
        }

        $formJustification = $this->get('form.factory')->createNamed('formJustification', TextPropertyForm::class, $textPropertyJustification);
        $formJustification->handleRequest($request);
        if($formJustification->isSubmitted() && $formJustification->isValid()){
            $textPropertyJustification = $formJustification->getData();
            $textPropertyJustification->setModifier($this->getUser());
            $textPropertyJustification->setModificationTime(new \DateTime('now'));
            $em->persist($textPropertyJustification);
            $em->flush();

            return $this->redirectToRoute('edit_customisation', array(
                    'id' => $profileAssociation->getId()
                )
            );
        }
        // End justification form

        // Use case form
        $systemTypeUseCase = $em->getRepository('AppBundle:SystemType')->find(36);
        $textPropertyUseCase = $em->getRepository("AppBundle:TextProperty")->findOneBy(
            array("profileAssociation" => $profileAssociation->getId(), "systemType" => $systemTypeUseCase->getId())
        );
        if(is_null($textPropertyUseCase)){
            $textPropertyUseCase = new TextProperty();
            $textPropertyUseCase->setCreator($this->getUser());
            $textPropertyUseCase->setCreationTime(new \DateTime('now'));
            $textPropertyUseCase->setModifier($this->getUser());
            $textPropertyUseCase->setModificationTime(new \DateTime('now'));
            $textPropertyUseCase->setProfileAssociation($profileAssociation);
            $textPropertyUseCase->setNamespaceForVersion($profileAssociation->getEntityNamespaceForVersion());
            $textPropertyUseCase->setSystemType($systemTypeUseCase);
        }

        $formUseCase = $this->get('form.factory')->createNamed('formUseCase', TextPropertyForm::class, $textPropertyUseCase);
        $formUseCase->handleRequest($request);
        if($formUseCase->isSubmitted() && $formUseCase->isValid()){
            $textPropertyUseCase = $formUseCase->getData();
            $textPropertyUseCase->setModifier($this->getUser());
            $textPropertyUseCase->setModificationTime(new \DateTime('now'));
            $em->persist($textPropertyUseCase);
            $em->flush();

            return $this->redirectToRoute('edit_customisation', array(
                    'id' => $profileAssociation->getId()
                )
            );
        }
        // End use case form

        // Profile internal note form
        $systemTypeNote = $em->getRepository('AppBundle:SystemType')->find(33);
        $textPropertyNote = $em->getRepository("AppBundle:TextProperty")->findOneBy(
            array("profileAssociation" => $profileAssociation->getId(), "systemType" => $systemTypeNote->getId())
        );
        if(is_null($textPropertyNote)){
            $textPropertyNote = new TextProperty();
            $textPropertyNote->setCreator($this->getUser());
            $textPropertyNote->setCreationTime(new \DateTime('now'));
            $textPropertyNote->setModifier($this->getUser());
            $textPropertyNote->setModificationTime(new \DateTime('now'));
            $textPropertyNote->setProfileAssociation($profileAssociation);
            $textPropertyNote->setNamespaceForVersion($profileAssociation->getEntityNamespaceForVersion());
            $textPropertyNote->setSystemType($systemTypeNote);
        }

        $formNote = $this->get('form.factory')->createNamed('formNote', TextPropertyForm::class, $textPropertyNote);
        $formNote->handleRequest($request);
        if($formNote->isSubmitted() && $formNote->isValid()){
            $textPropertyNote = $formNote->getData();
            $textPropertyNote->setModifier($this->getUser());
            $textPropertyNote->setModificationTime(new \DateTime('now'));
            $em->persist($textPropertyNote);
            $em->flush();

            return $this->redirectToRoute('edit_customisation', array(
                    'id' => $profileAssociation->getId()
                )
            );
        }
        // End profile internal note form

        // Additionnals examples form
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7);

        // First $textPropertyNewExample is only for add
        $textPropertyNewExample =  new TextProperty();
        $textPropertyNewExample->setCreator($this->getUser());
        $textPropertyNewExample->setCreationTime(new \DateTime('now'));
        $textPropertyNewExample->setModifier($this->getUser());
        $textPropertyNewExample->setModificationTime(new \DateTime('now'));
        $textPropertyNewExample->setProfileAssociation($profileAssociation);
        $textPropertyNewExample->setNamespaceForVersion($profileAssociation->getEntityNamespaceForVersion());
        $textPropertyNewExample->setSystemType($systemTypeExample);

        $formNewExample = $this->get('form.factory')->createNamed('formNewExample', TextPropertyForm::class, $textPropertyNewExample);
        $formNewExample->handleRequest($request);
        if($formNewExample->isSubmitted() && $formNewExample->isValid()){
            $textPropertyNewExample = $formNewExample->getData();
            $textPropertyNewExample->setModifier($this->getUser());
            $textPropertyNewExample->setModificationTime(new \DateTime('now'));
            $em->persist($textPropertyNewExample);
            $em->flush();

            return $this->redirectToRoute('edit_customisation', array(
                    'id' => $profileAssociation->getId()
                )
            );
        }

        // Next examples is only for edit
        $textPropertyExamples = $em->getRepository("AppBundle:TextProperty")->findBy(
            array("profileAssociation" => $profileAssociation->getId(), "systemType" => $systemTypeExample->getId())
        );

        $forms = array();
        foreach($textPropertyExamples as $example){
            $uniqueFormName = 'formExample'.$example->getId();
            $form = $this->get('form.factory')->createNamed($uniqueFormName, TextPropertyForm::class, $example);
            $forms[$uniqueFormName] = $form;
        }

        $formsExampleViews = [];
        foreach ($forms as $formName => $formExample){
            $formExample->handleRequest($request);
            if ($formExample->isSubmitted() && $formExample->isValid())
            {
                $textPropertyExample = $formExample->getData();
                $textPropertyExample->setModifier($this->getUser());
                $textPropertyExample->setModificationTime(new \DateTime('now'));
                $em->persist($textPropertyExample);
                $em->flush();

                return $this->redirectToRoute('edit_customisation', array(
                        'id' => $profileAssociation->getId()
                    )
                );
            }
            //Create the view *after* calling handleRequest, so data is updated to the form, even if, say, there are validation errors.
            $formsExampleViews[$formName] = $formExample->createView();
        }

        return $this->render('profileAssociation/edit_customisation.html.twig', array(
            'profileAssociation' => $profileAssociation,
            'formJustification' => $formJustification->createView(),
            'formUseCase' => $formUseCase->createView(),
            'formNote' => $formNote->createView(),
            'formNewExample' => $formNewExample->createView(),
            'formsExample' => $formsExampleViews
        ));
    }

    /**
     * @Route("/profile/{id}/makevisible", name="profile_make_visible", requirements={"id"="^([0-9]+)|(profileID){1}$"})
     * @param Profile $profile
     * @return JsonResponse
     */
    public function makeVisibleAction(Profile $profile)
    {
        $this->denyAccessUnlessGranted('edit', $profile);
        try {
            $profile->setIsVisible(true);
            $em = $this->getDoctrine()->getManager();
            $em->persist($profile);
            $em->flush();
        }
        catch (\Exception $e) {
            return new JsonResponse(null, 500, array('content-type:application/problem+json'));
        }
        return new JsonResponse(null, 204);
    }
}