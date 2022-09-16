<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/06/2017
 * Time: 17:11
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Label;
use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\Property;
use AppBundle\Entity\PropertyVersion;
use AppBundle\Entity\SystemType;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\IngoingPropertyQuickAddForm;
use AppBundle\Form\OutgoingPropertyQuickAddForm;
use AppBundle\Form\PropertyEditForm;
use AppBundle\Form\PropertyEditIdentifierForm;
use AppBundle\Form\TextPropertyForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PropertyController extends Controller
{
    /**
     * @Route("/property")
     */
    public function listAction(){
        $em = $this->getDoctrine()->getManager();

        // FILTRAGE : Récupérer les namespaces
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }

        // Compléter avec les références parents (directs/indirects)
        $refsNsId = [];
        foreach($namespacesId as $nsId){
            $ns = $em->getRepository('AppBundle:OntoNamespace')->find($nsId);
            foreach ($ns->getAllReferencedNamespaces() as $refNs){
                if(!in_array($refNs->getId(), $refsNsId)){$refsNsId[] = $refNs->getId();}
            }
        }
        $namespacesId = array_merge($namespacesId, $refsNsId);

        // Récupérer l'ensemble des namespaces root déjà utilisé pour le filtrage
        $rootNamespacesId = [];
        foreach ($namespacesId as $namespaceId){
            $rootNamespacesId[] = $em->getRepository('AppBundle:OntoNamespace')->find($namespaceId)->getTopLevelNamespace()->getId();
        }

        // Récupérer toutes les classes sans le filtrage
        // N'afficher que les classes/propriétés de la version choisie par l'utilisateur sinon dernière publiée d'un espace de noms ou,
        // si l'espace n'a pas de version publiée, la version ongoing.
        // 1- Récuperer tous les roots
        $allRootNamespaces = $em->getRepository('AppBundle:OntoNamespace')->findBy(array("isTopLevelNamespace" => true));
        // 2- Récupérer la bonne version (choisie par l'utilisateur sinon dernière publiée sinon ongoing)
        $allNamespacesId = $namespacesId;

        // Enlever ceux déjà utilisés
        $filteredRootNamespaces = array_filter($allRootNamespaces, function($v) use ($rootNamespacesId) {return !in_array($v->getId(), $rootNamespacesId);});
        foreach ($filteredRootNamespaces as $rootNamespace){
            $defaultNamespace = null;
            foreach ($rootNamespace->getChildVersions() as $childVersion){
                if($childVersion->getIsOngoing() and !$rootNamespace->getHasPublication()){
                    $defaultNamespace = $childVersion;
                }
                elseif(is_null($defaultNamespace) || $defaultNamespace->getPublishedAt() < $childVersion->getPublishedAt()){
                    $defaultNamespace = $childVersion;
                }
            }
            $allNamespacesId[] = $defaultNamespace->getId();
        }

        // Récupérer toutes les propriétés
        $allProperties= $em->getRepository('AppBundle:Property')->findAll(); //->findPropertiesByNamespacesId($allNamespacesId);

        return $this->render('property/list.html.twig', [
            'properties' => $allProperties,
            'allNamespacesId' => $allNamespacesId,
            'namespacesId' => $namespacesId
        ]);
    }

    /**
     * @Route("property/{type}/new/{class}", name="property_new", requirements={"type"="^(ingoing|outgoing){1}$", "class"="^[0-9]+$"})
     */
    public function newAction($type,Request $request, OntoClass $class)
    {
        $property = new Property();

        //get the right version of the class
        $classVersion = $class->getClassVersionForDisplay();

        $this->denyAccessUnlessGranted('add_associations', $this->getUser()->getCurrentOngoingNamespace());

        if($type !== 'ingoing' && $type !== 'outgoing') throw $this->createNotFoundException('The requested property type "'.$type.'" does not exist!');

        $em = $this->getDoctrine()->getManager();
        $systemTypeScopeNote = $em->getRepository('AppBundle:SystemType')->find(1); //systemType 1 = scope note
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 1 = scope note

        $propertyVersion = new PropertyVersion();
        $propertyVersion->setProperty($property);
        $propertyVersion->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
        $propertyVersion->setCreator($this->getUser());
        $propertyVersion->setModifier($this->getUser());
        $propertyVersion->setCreationTime(new \DateTime('now'));
        $propertyVersion->setModificationTime(new \DateTime('now'));

        $scopeNote = new TextProperty();
        $scopeNote->setProperty($property);
        $scopeNote->setSystemType($systemTypeScopeNote);
        $scopeNote->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
        $scopeNote->setCreator($this->getUser());
        $scopeNote->setModifier($this->getUser());
        $scopeNote->setCreationTime(new \DateTime('now'));
        $scopeNote->setModificationTime(new \DateTime('now'));

        $property->addTextProperty($scopeNote);

        $label = new Label();
        $label->setProperty($property);
        $label->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
        $label->setIsStandardLabelForLanguage(true);
        $label->setCreator($this->getUser());
        $label->setModifier($this->getUser());
        $label->setCreationTime(new \DateTime('now'));
        $label->setModificationTime(new \DateTime('now'));

        $property->addLabel($label);
        if($type == 'outgoing') {
            $propertyVersion->setDomain($class);
        }
        elseif ($type == 'ingoing') {
            $propertyVersion->setRange($class);
        }

        $property->addPropertyVersion($propertyVersion);

        //$property->setIsManualIdentifier(is_null($classVersion->getNamespaceForVersion()->getTopLevelNamespace()->getPropertyPrefix()));
        $property->setIsManualIdentifier(is_null($this->getUser()->getCurrentOngoingNamespace()->getTopLevelNamespace()->getPropertyPrefix()));
        $property->setCreator($this->getUser());
        $property->setModifier($this->getUser());

        $em = $this->getDoctrine()->getManager();

        // Filtrage
        $namespaceForPropertyVersion = $propertyVersion->getNamespaceForVersion();
        $namespacesId[] = $namespaceForPropertyVersion->getId();

        // Sans oublier les namespaces références si indisponible
        foreach($propertyVersion->getNamespaceForVersion()->getAllReferencedNamespaces() as $referencedNamespace){
            if(!in_array($referencedNamespace->getId(), $namespacesId)){
                $namespacesId[] = $referencedNamespace->getId();
            }
        }

        $arrayClassesVersion = $em->getRepository('AppBundle:OntoClassVersion')
            ->findIdAndStandardLabelOfClassesVersionByNamespacesId($namespacesId);

        $form = null;
        if($type == 'outgoing') {
            $form = $this->createForm(OutgoingPropertyQuickAddForm::class, $property, array(
                "classesVersion" => $arrayClassesVersion
            ));
        }
        elseif ($type == 'ingoing') {
            $form = $this->createForm(IngoingPropertyQuickAddForm::class, $property, array(
                "classesVersion" => $arrayClassesVersion
            ));
        }

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $property = $form->getData();
            if($type == 'outgoing') {
                $propertyVersion->setDomain($class);
                $domainNamespace = $em->getRepository("AppBundle:OntoClassVersion")->findClassVersionByClassAndNamespacesId($class, $namespacesId)->getNamespaceForVersion();
                $propertyVersion->setDomainNamespace($domainNamespace);
                $range = $em->getRepository("AppBundle:OntoClass")->find($form->get("rangeVersion")->getData());
                $propertyVersion->setRange($range);
                $rangeNamespace = $em->getRepository("AppBundle:OntoClassVersion")->findClassVersionByClassAndNamespacesId($range, $namespacesId)->getNamespaceForVersion();
                $propertyVersion->setRangeNamespace($rangeNamespace);
            }
            elseif ($type == 'ingoing') {
                $propertyVersion->setRange($class);
                $rangeNamespace = $em->getRepository("AppBundle:OntoClassVersion")->findClassVersionByClassAndNamespacesId($class, $namespacesId)->getNamespaceForVersion();
                $propertyVersion->setRangeNamespace($rangeNamespace);
                $domain = $em->getRepository("AppBundle:OntoClass")->find($form->get("domainVersion")->getData());
                $propertyVersion->setDomain($domain);
                $domainNamespace = $em->getRepository("AppBundle:OntoClassVersion")->findClassVersionByClassAndNamespacesId($domain, $namespacesId)->getNamespaceForVersion();
                $propertyVersion->setDomainNamespace($domainNamespace);
            }

            $propertyVersion->setDomainMinQuantifier($form->get("domainMinQuantifierVersion")->getData());
            $propertyVersion->setDomainMaxQuantifier($form->get("domainMaxQuantifierVersion")->getData());
            $propertyVersion->setRangeMinQuantifier($form->get("rangeMinQuantifierVersion")->getData());
            $propertyVersion->setRangeMaxQuantifier($form->get("rangeMaxQuantifierVersion")->getData());

            $property->setCreator($this->getUser());
            $property->setModifier($this->getUser());
            $property->setCreationTime(new \DateTime('now'));
            $property->setModificationTime(new \DateTime('now'));

            if($property->getTextProperties()->containsKey(1)){
                $property->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $property->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $property->getTextProperties()[1]->setSystemType($systemTypeExample);
                //$property->getTextProperties()[1]->addNamespace($this->getUser()->getCurrentOngoingNamespace());TODO: delete this line after successful test of the SolutionD branch
                $property->getTextProperties()[1]->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
                $property->getTextProperties()[1]->setProperty($property);
            }


            $em = $this->getDoctrine()->getManager();
            $em->persist($property);
            $em->flush();

            return $this->redirectToRoute('property_show', [
                'id' => $property->getId()
            ]);

        }

        $em = $this->getDoctrine()->getManager();

        $template = null;
        if($type == 'outgoing') {
            $template = 'property/newOutgoing.html.twig';
        }
        elseif ($type == 'ingoing') {
            $template = 'property/newIngoing.html.twig';
        }
        return $this->render($template, [
            'property' => $property,
            'type' => $type,
            'propertyForm' => $form->createView()
        ]);
    }

    /**
     * @Route("/property/{id}", name="property_show", requirements={"id"="^([0-9]+)|(propertyID){1}$"})
     * @Route("/property/{id}/namespace/{namespaceFromUrlId}", name="property_show_with_version", requirements={"id"="^([0-9]+)|(propertyID){1}$", "namespaceFromUrlId"="^([0-9]+)|(namespaceID){1}$"})
     * @param Property $property
     * @param int|null $namespaceFromUrlId
     * @return Response the rendered template
     */
    public function showAction(Property $property, $namespaceFromUrlId=null)
    {
        //Vérifier si le namespace -si renseigné- est bien associé à la propriété
        $namespaceFromUrl = null;
        if(!is_null($namespaceFromUrlId)) {
            $namespaceFromUrlId = intval($namespaceFromUrlId);
            $pvCollection = $property->getPropertyVersions()->filter(function (PropertyVersion $propertyVersion) use ($namespaceFromUrlId) {
                return $propertyVersion->getNamespaceForVersion()->getId() === $namespaceFromUrlId;
            });
            if($pvCollection->count() == 0){
                return $this->redirectToRoute('property_show', [
                    'id' => $property->getId()
                ]);
            }
            else{
                $namespaceFromUrl = $pvCollection->first()->getNamespaceForVersion();
            }
        }
        // Récupérer la version de la propriété demandée
        $propertyVersion = $property->getPropertyVersionForDisplay($namespaceFromUrl);

        // On doit avoir une version de la propriété sinon on lance une exception.
        if(is_null($propertyVersion)){
            throw $this->createNotFoundException('The property n°'.$property->getId().' has no version. Please contact an administrator.');
        }

        $em = $this->getDoctrine()->getManager();

        //Si le namespace n'est pas specifié dans l'url mais dans My Current Namespace, rediriger
        // $namespacesIdFromUser : Ensemble de tous les namespaces activés par l'utilisateur

        if(is_null($namespaceFromUrlId)) {
            if (is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21) {
                $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
            } else { // Utilisateur connecté et utilisant un autre projet
                $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
            }
            foreach ($namespacesIdFromUser as $namespaceIdFromUser) {
                $namespaceFromUser = $em->getRepository('AppBundle:OntoNamespace')->find($namespaceIdFromUser);
                if ($propertyVersion->getNamespaceForVersion()->getTopLevelNamespace()->getId() === $namespaceFromUser->getTopLevelNamespace()->getId() and $property->getPropertyVersionForDisplay($namespaceFromUser)) {
                    return $this->redirectToRoute('property_show_with_version', [
                        'id' => $property->getId(),
                        'namespaceFromUrlId' => $property->getPropertyVersionForDisplay($namespaceFromUser)->getNamespaceForVersion()->getId()
                    ]);
                }
            }
            return $this->redirectToRoute('property_show_with_version', [
                'id' => $property->getId(),
                'namespaceFromUrlId' => $propertyVersion->getNamespaceForVersion()->getId()
            ]);
        }

        // $namespacesIdFromPropertyVersion : Ensemble de namespaces provenant de la propriété affiché (namespaceForVersion + references)
        // $rootNamespacesFromPropertyVersion : Ensemble des versions racines (pour contrôle en dessous)
        $nsId = $propertyVersion->getNamespaceForVersion()->getId();
        $namespacesIdFromPropertyVersion[] = $nsId;
        $rootNamespacesFromClassVersion[] = $em->getRepository('AppBundle:OntoNamespace')->findOneBy(array('id' => $nsId))->getTopLevelNamespace();

        foreach($propertyVersion->getNamespaceForVersion()->getAllReferencedNamespaces() as $referencedNamespace){
            $nsId = $referencedNamespace->getId();
            $namespacesIdFromPropertyVersion[] = $nsId;
            $rootNamespacesFromClassVersion[] = $em->getRepository('AppBundle:OntoNamespace')->findOneBy(array('id' => $nsId))->getTopLevelNamespace();
        }

        // $namespacesIdFromUser : Ensemble de tous les namespaces activés par l'utilisateur
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){
            $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }
        // sauf ceux automatiquement activés par l'entité
        $namespacesIdFromUser = array_diff($namespacesIdFromUser, $namespacesIdFromPropertyVersion);

        // Créer un array de ns à ajouter (ne pas rajouter ceux dont le root est déjà utilisé
        $nsIdFromUser = array();
        foreach ($namespacesIdFromUser as $namespaceIdFromUser){
            $nsRootUser = $em->getRepository('AppBundle:OntoNamespace')->findOneBy(array('id' => $namespaceIdFromUser))->getTopLevelNamespace();
            if(!in_array($nsRootUser, $rootNamespacesFromClassVersion)){
                $nsIdFromUser[] = $namespaceIdFromUser;
            }
        }

        // $namespacesId : Tous les namespaces trouvés ci-dessus
        $namespacesId = array_merge($namespacesIdFromPropertyVersion, $nsIdFromUser);

        $ancestors = $em->getRepository('AppBundle:Property')->findAncestorsByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);
        $descendants = $em->getRepository('AppBundle:Property')->findDescendantsByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);
        $domainRange = $em->getRepository('AppBundle:Property')->findDomainAndRangeByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);
        $relations = $em->getRepository('AppBundle:Property')->findRelationsByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);

        $this->get('logger')->info('Showing property: ' . $property->getIdentifierInNamespace());

        return $this->render('property/show.html.twig', array(
            'propertyVersion' => $propertyVersion,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'domainRange' => $domainRange,
            'relations' => $relations,
            'namespacesId' => $namespacesId,
            'namespacesIdFromPropertyVersion' => $namespacesIdFromPropertyVersion,
            'namespacesIdFromUser' => $namespacesIdFromUser
        ));
    }

    /**
     * @Route("/property/{id}/edit", name="property_edit", requirements={"id"="^[0-9]+$"})
     * @param Property $property
     * @param Request $request
     * @return Response the rendered template
     * @throws \Exception
     */
    public function editAction(Property $property, Request $request)
    {
        // Récupérer la version de la propriété demandée
        $propertyVersion = $property->getPropertyVersionForDisplay();

        // On doit avoir une version de la propriété sinon on lance une exception.
        if(is_null($propertyVersion)){
            throw $this->createNotFoundException('The property n°'.$property->getId().' has no version. Please contact an administrator.');
        }

        $this->denyAccessUnlessGranted('edit', $propertyVersion);

        $em = $this->getDoctrine()->getManager();

        // $namespacesIdFromClassVersion : Ensemble de namespaces provenant de la classe affiché (namespaceForVersion + references)
        $namespacesIdFromPropertyVersion[] = $propertyVersion->getNamespaceForVersion()->getId();

        //foreach($propertyVersion->getNamespaceForVersion()->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
        foreach($propertyVersion->getNamespaceForVersion()->getAllReferencedNamespaces() as $referencedNamespace){
            $namespacesIdFromPropertyVersion[] = $referencedNamespace->getId();
        }

        // $namespacesIdFromUser : Ensemble de tous les namespaces activés par l'utilisateur
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){
            $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }
        // sauf ceux automatiquement activés par l'entité
        $namespacesIdFromUser = array_diff($namespacesIdFromUser, $namespacesIdFromPropertyVersion);

        // $namespacesId : Tous les namespaces trouvés ci-dessus
        $namespacesId = array_merge($namespacesIdFromPropertyVersion, $namespacesIdFromUser);

        $ancestors = $em->getRepository('AppBundle:Property')->findAncestorsByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);
        $descendants = $em->getRepository('AppBundle:Property')->findDescendantsByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);
        $domainRange = $em->getRepository('AppBundle:Property')->findDomainAndRangeByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);
        $relations = $em->getRepository('AppBundle:Property')->findRelationsByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);

        $arrayClassesVersion = $em->getRepository('AppBundle:OntoClassVersion')
            ->findIdAndStandardLabelOfClassesVersionByNamespacesId($namespacesIdFromPropertyVersion);

        $propertyVersion->setCreator($this->getUser());
        $propertyVersion->setModifier($this->getUser());
        $propertyVersion->setCreationTime(new \DateTime('now'));
        $propertyVersion->setModificationTime(new \DateTime('now'));

        if(!is_null($propertyVersion->getDomain())){
            $defaultDomain = $propertyVersion->getDomain()->getId();
        }
        else{
            $defaultDomain = null;
        }

        if(!is_null($propertyVersion->getRange())){
            $defaultRange = $propertyVersion->getRange()->getId();
        }
        else{
            $defaultRange = null;
        }

        $form = $this->createForm(PropertyEditForm::class, $propertyVersion, array(
            'classesVersion' => $arrayClassesVersion,
            'defaultDomain' => $defaultDomain,
            'defaultRange' => $defaultRange));

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $domain = $em->getRepository("AppBundle:OntoClass")->find($form->get("domainVersion")->getData());
            $propertyVersion->setDomain($domain);
            $domainNamespace = $em->getRepository("AppBundle:OntoClassVersion")->findClassVersionByClassAndNamespacesId($domain, $namespacesId)->getNamespaceForVersion();
            $propertyVersion->setDomainNamespace($domainNamespace);
            $range = $em->getRepository("AppBundle:OntoClass")->find($form->get("rangeVersion")->getData());
            $propertyVersion->setRange($range);
            $rangeNamespace = $em->getRepository("AppBundle:OntoClassVersion")->findClassVersionByClassAndNamespacesId($range, $namespacesId)->getNamespaceForVersion();
            $propertyVersion->setRangeNamespace($rangeNamespace);
            $em = $this->getDoctrine()->getManager();
            $em->persist($propertyVersion);
            $em->flush();

            $this->addFlash('success', 'Property updated!');
            return $this->redirectToRoute('property_edit', [
                'id' => $property->getId(),
                '_fragment' => 'identification'
            ]);
        }

        $propertyTemp = new Property();
        $propertyVersionTemp = new PropertyVersion();
        $propertyVersionTemp->setNamespaceForVersion($propertyVersion->getNamespaceForVersion());
        $propertyVersionTemp->setProperty($propertyTemp);

        //$propertyTemp->addNamespace($property->getOngoingNamespace());
        $propertyTemp->setIdentifierInNamespace($property->getIdentifierInNamespace());
        $propertyTemp->setIsManualIdentifier(is_null($propertyVersion->getNamespaceForVersion()->getTopLevelNamespace()->getPropertyPrefix()));
        $propertyTemp->setCreator($this->getUser());
        $propertyTemp->setModifier($this->getUser());
        $propertyTemp->setCreationTime(new \DateTime('now'));
        $propertyTemp->setModificationTime(new \DateTime('now'));

        $propertyVersionTemp->setCreator($this->getUser());
        $propertyVersionTemp->setModifier($this->getUser());
        $propertyVersionTemp->setCreationTime(new \DateTime('now'));
        $propertyVersionTemp->setModificationTime(new \DateTime('now'));

        $propertyVersionTemp->setDomain($propertyVersion->getDomain());
        $propertyVersionTemp->setRange($propertyVersion->getRange());

        $propertyTemp->addPropertyVersion($propertyVersionTemp);

        $formIdentifier = $this->createForm(PropertyEditIdentifierForm::class, $propertyTemp);
        $formIdentifier->handleRequest($request);
        if ($formIdentifier->isSubmitted() && $formIdentifier->isValid()) {
            $property->setIdentifierInNamespace($propertyTemp->getIdentifierInNamespace());
            $em = $this->getDoctrine()->getManager();
            $em->persist($property);
            $em->persist($propertyVersion);
            $em->flush();

            $this->addFlash('success', 'Property updated!');
            return $this->redirectToRoute('property_edit', [
                'id' => $property->getId(),
                '_fragment' => 'identification'
            ]);
        }

        $this->get('logger')
            ->info('Showing property: '.$property->getIdentifierInNamespace());

        //If validation status is in validation request or is validation, we can't allow edition of the entity and we rended the show template
        if (!is_null($propertyVersion->getValidationStatus()) && ($propertyVersion->getValidationStatus()->getId() === 26 || $propertyVersion->getValidationStatus()->getId() === 28)) {
            return $this->render('property/show.html.twig', [
                'propertyVersion' => $propertyVersion,
                'ancestors' => $ancestors,
                'descendants' => $descendants,
                'domainRange' => $domainRange,
                'relations' => $relations,
                'namespacesId' => $namespacesId
            ]);
        }

        return $this->render('property/edit.html.twig', array(
            'propertyVersion' => $propertyVersion,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'domainRange' => $domainRange,
            'relations' => $relations,
            'propertyForm' => $form->createView(),
            'propertyIdentifierForm' => $formIdentifier->createView(),
            'namespacesId' => $namespacesId,
            'namespacesIdFromPropertyVersion' => $namespacesIdFromPropertyVersion,
            'namespacesIdFromUser' => $namespacesIdFromUser
        ));
    }

    /**
     * @Route("/property/{id}/profile/{profile}", name="property_show_customisation", requirements={"id"="^([0-9]+)|(propertyID){1}$", "profile"="^([0-9]+)|(profileID){1}$"})
     * @param Property $property
     * @param Profile $profile
     * @return Response the rendered template
     */
    public function showCustomisationAction(Property $property, Profile $profile)
    {
        $rootNamespaceProperty = $property->getTopLevelNamespace();
        $referencedNamespace = null;
        foreach($profile->getAllReferencedNamespaces() as $referencedNamespaceProfile){
            if($referencedNamespaceProfile->getTopLevelNamespace() === $rootNamespaceProperty){
                $referencedNamespace = $referencedNamespaceProfile;
                break;
            }
        }
        $propertyVersion = $property->getPropertyVersionForDisplay($referencedNamespace);
        return $this->render('property/show_customisation.html.twig', array('propertyVersion' => $propertyVersion, 'profile' => $profile));
    }

    /**
     * @Route("/property/{id}/profile/{profile}/edit", name="property_edit_customisation", requirements={"id"="^([0-9]+)|(propertyID){1}$", "profile"="^([0-9]+)|(profileID){1}$"})
     * @param Property $property
     * @param Profile $profile
     * @return Response the rendered template
     */
    public function editCustomisationAction(Property $property, Profile $profile, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $rootNamespaceProperty = $property->getTopLevelNamespace();
        $referencedNamespace = null;
        foreach($profile->getAllReferencedNamespaces() as $referencedNamespaceProfile){
            if($referencedNamespaceProfile->getTopLevelNamespace() === $rootNamespaceProperty){
                $referencedNamespace = $referencedNamespaceProfile;
                break;
            }
        }
        $propertyVersion = $property->getPropertyVersionForDisplay($referencedNamespace);

        // Justification form
        $systemTypeJustification = $em->getRepository('AppBundle:SystemType')->find(15);
        $textPropertyJustification = $em->getRepository("AppBundle:TextProperty")->findOneBy(
            array("property" => $property->getId(), "profileForCustom" => $profile->getId(), "systemType" => $systemTypeJustification->getId())
        );
        if(is_null($textPropertyJustification)){
            $textPropertyJustification = new TextProperty();
            $textPropertyJustification->setCreator($this->getUser());
            $textPropertyJustification->setCreationTime(new \DateTime('now'));
            $textPropertyJustification->setModifier($this->getUser());
            $textPropertyJustification->setModificationTime(new \DateTime('now'));
            $textPropertyJustification->setProperty($property);
            $textPropertyJustification->setProfileForCustom($profile);
            $textPropertyJustification->setNamespaceForVersion($propertyVersion->getNamespaceForVersion());
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

            return $this->redirectToRoute('property_edit_customisation', array(
                    'id' => $propertyVersion->getProperty()->getId(),
                    'profile' => $profile->getId())
            );
        }
        // End justification form

        // Use case form
        $systemTypeUseCase = $em->getRepository('AppBundle:SystemType')->find(36);
        $textPropertyUseCase = $em->getRepository("AppBundle:TextProperty")->findOneBy(
            array("property" => $property->getId(), "profileForCustom" => $profile->getId(), "systemType" => $systemTypeUseCase->getId())
        );
        if(is_null($textPropertyUseCase)){
            $textPropertyUseCase = new TextProperty();
            $textPropertyUseCase->setCreator($this->getUser());
            $textPropertyUseCase->setCreationTime(new \DateTime('now'));
            $textPropertyUseCase->setModifier($this->getUser());
            $textPropertyUseCase->setModificationTime(new \DateTime('now'));
            $textPropertyUseCase->setProperty($property);
            $textPropertyUseCase->setProfileForCustom($profile);
            $textPropertyUseCase->setNamespaceForVersion($propertyVersion->getNamespaceForVersion());
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

            return $this->redirectToRoute('property_edit_customisation', array(
                    'id' => $propertyVersion->getProperty()->getId(),
                    'profile' => $profile->getId())
            );
        }
        // End use case form

        // Profile internal note form
        $systemTypeNote = $em->getRepository('AppBundle:SystemType')->find(33);
        $textPropertyNote = $em->getRepository("AppBundle:TextProperty")->findOneBy(
            array("property" => $property->getId(), "profileForCustom" => $profile->getId(), "systemType" => $systemTypeNote->getId())
        );
        if(is_null($textPropertyNote)){
            $textPropertyNote = new TextProperty();
            $textPropertyNote->setCreator($this->getUser());
            $textPropertyNote->setCreationTime(new \DateTime('now'));
            $textPropertyNote->setModifier($this->getUser());
            $textPropertyNote->setModificationTime(new \DateTime('now'));
            $textPropertyNote->setProperty($property);
            $textPropertyNote->setProfileForCustom($profile);
            $textPropertyNote->setNamespaceForVersion($propertyVersion->getNamespaceForVersion());
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

            return $this->redirectToRoute('property_edit_customisation', array(
                    'id' => $propertyVersion->getProperty()->getId(),
                    'profile' => $profile->getId())
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
        $textPropertyNewExample->setProperty($property);
        $textPropertyNewExample->setProfileForCustom($profile);
        $textPropertyNewExample->setNamespaceForVersion($propertyVersion->getNamespaceForVersion());
        $textPropertyNewExample->setSystemType($systemTypeExample);

        $formNewExample = $this->get('form.factory')->createNamed('formNewExample', TextPropertyForm::class, $textPropertyNewExample);
        $formNewExample->handleRequest($request);
        if($formNewExample->isSubmitted() && $formNewExample->isValid()){
            $textPropertyNewExample = $formNewExample->getData();
            $textPropertyNewExample->setModifier($this->getUser());
            $textPropertyNewExample->setModificationTime(new \DateTime('now'));
            $em->persist($textPropertyNewExample);
            $em->flush();

            return $this->redirectToRoute('property_edit_customisation', array(
                    'id' => $propertyVersion->getProperty()->getId(),
                    'profile' => $profile->getId())
            );
        }

        // Next examples is only for edit
        $textPropertyExamples = $em->getRepository("AppBundle:TextProperty")->findBy(
            array("property" => $property->getId(), "profileForCustom" => $profile->getId(), "systemType" => $systemTypeExample->getId())
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

                return $this->redirectToRoute('property_edit_customisation', array(
                        'id' => $propertyVersion->getProperty()->getId(),
                        'profile' => $profile->getId())
                );
            }
            //Create the view *after* calling handleRequest, so data is updated to the form, even if, say, there are validation errors.
            $formsExampleViews[$formName] = $formExample->createView();
        }

        return $this->render('property/edit_customisation.html.twig', array(
            'propertyVersion' => $propertyVersion,
            'profile' => $profile,
            'formJustification' => $formJustification->createView(),
            'formUseCase' => $formUseCase->createView(),
            'formNote' => $formNote->createView(),
            'formNewExample' => $formNewExample->createView(),
            'formsExample' => $formsExampleViews
        ));
    }

    /**
     * @Route("/property-version/{id}/edit-validity/{validationStatus}", name="property_version_validation_status_edit", requirements={"id"="^[0-9]+$", "validationStatus"="^(26|27|28){1}$"})
     * @param PropertyVersion $propertyVersion
     * @param SystemType $validationStatus
     * @param Request $request
     * @throws \Exception in case of unsuccessful validation
     * @return RedirectResponse|Response
     */
    public function editValidationStatusAction(PropertyVersion $propertyVersion, SystemType $validationStatus, Request $request)
    {
        // On doit avoir une version de la classe sinon on lance une exception.
        if(is_null($propertyVersion)){
            throw $this->createNotFoundException('The property version n°'.$propertyVersion->getId().' does not exist. Please contact an administrator.');
        }

        //Denied access if not an authorized validator
        $this->denyAccessUnlessGranted('validate', $propertyVersion);


        $propertyVersion->setModifier($this->getUser());

        $newValidationStatus = new SystemType();

        try{
            $em = $this->getDoctrine()->getManager();
            $newValidationStatus = $em->getRepository('AppBundle:SystemType')
                ->findOneBy(array('id' => $validationStatus->getId()));
        } catch (\Exception $e) {
            throw new BadRequestHttpException('The provided status does not exist.');
        }

        if (!is_null($newValidationStatus)) {
            $statusId = intval($newValidationStatus->getId());
            if (in_array($statusId, [26,27,28], true)) {
                $propertyVersion->setValidationStatus($newValidationStatus);
                $propertyVersion->setModifier($this->getUser());
                $propertyVersion->setModificationTime(new \DateTime('now'));

                $em->persist($propertyVersion);

                $em->flush();

                if ($statusId == 27){
                    return $this->redirectToRoute('property_edit', [
                        'id' => $propertyVersion->getProperty()->getId()
                    ]);
                }
                else return $this->redirectToRoute('property_show', [
                    'id' => $propertyVersion->getProperty()->getId()
                ]);

            }
        }

        return $this->redirectToRoute('property_show', [
            'id' => $propertyVersion->getProperty()->getId()
        ]);
    }

    /**
     * @Route("/properties-tree")
     */
    public function getTreeAction()
    {
        return $this->render('property/tree.html.twig');
    }

    /**
     * @Route("/properties-tree/json", name="properties_tree_json")
     * @Method("GET")
     * @return JsonResponse a Json formatted tree representation of Properties
     */
    public function getTreeJson()
    {
        $em = $this->getDoctrine()->getManager();
        $properties = $em->getRepository('AppBundle:Property')
            ->findPropertiesTree();

        return new JsonResponse($properties[0]['json'],200, array(), true);
    }

    /**
     * @Route("/properties-tree-legend/json", name="properties_tree_legend_json")
     * @Method("GET")
     * @return JsonResponse a Json formatted legend for the Properties tree
     */
    public function getTreeLegendJson()
    {
        $em = $this->getDoctrine()->getManager();
        $legend = $em->getRepository('AppBundle:Property')
            ->findPropertiesTreeLegend();


        return new JsonResponse($legend[0]['json']);
    }

}