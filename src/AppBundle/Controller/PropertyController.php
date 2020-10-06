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
use AppBundle\Entity\Project;
use AppBundle\Entity\Property;
use AppBundle\Entity\PropertyVersion;
use AppBundle\Entity\SystemType;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\IngoingPropertyQuickAddForm;
use AppBundle\Form\OutgoingPropertyQuickAddForm;
use AppBundle\Form\PropertyEditForm;
use AppBundle\Form\PropertyEditIdentifierForm;
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

        // FILTRAGE : Récupérer les clés de namespaces à utiliser
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }

        // Récupérer les classes selon le filtrage obtenu
        $properties = $em->getRepository('AppBundle:Property')->findPropertiesByNamespacesId($namespacesId);

        return $this->render('property/list.html.twig', [
            'properties' => $properties,
            'namespacesId' => $namespacesId
        ]);
    }

    /**
     * @Route("property/{type}/new/{class}", name="property_new")
     */
    public function newAction($type,Request $request, OntoClass $class)
    {
        $property = new Property();

        //get the right version of the class
        $classVersion = $class->getClassVersionForDisplay();

        $this->denyAccessUnlessGranted('edit', $classVersion);

        if($type !== 'ingoing' && $type !== 'outgoing') throw $this->createNotFoundException('The requested property type "'.$type.'" does not exist!');

        $em = $this->getDoctrine()->getManager();
        $systemTypeScopeNote = $em->getRepository('AppBundle:SystemType')->find(1); //systemType 1 = scope note
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 1 = scope note

        $propertyVersion = new PropertyVersion();
        $propertyVersion->setProperty($property);
        $propertyVersion->setNamespaceForVersion($classVersion->getNamespaceForVersion());
        $propertyVersion->setCreator($this->getUser());
        $propertyVersion->setModifier($this->getUser());
        $propertyVersion->setCreationTime(new \DateTime('now'));
        $propertyVersion->setModificationTime(new \DateTime('now'));

        $scopeNote = new TextProperty();
        $scopeNote->setProperty($property);
        $scopeNote->setSystemType($systemTypeScopeNote);
        $scopeNote->setNamespaceForVersion($classVersion->getNamespaceForVersion());
        $scopeNote->setCreator($this->getUser());
        $scopeNote->setModifier($this->getUser());
        $scopeNote->setCreationTime(new \DateTime('now'));
        $scopeNote->setModificationTime(new \DateTime('now'));

        $property->addTextProperty($scopeNote);

        $label = new Label();
        $label->setProperty($property);
        $label->setNamespaceForVersion($classVersion->getNamespaceForVersion());
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

        $property->setIsManualIdentifier(is_null($classVersion->getNamespaceForVersion()->getTopLevelNamespace()->getPropertyPrefix()));
        $property->setCreator($this->getUser());
        $property->setModifier($this->getUser());

        $em = $this->getDoctrine()->getManager();

        // Filtrage
        $namespaceForPropertyVersion = $propertyVersion->getNamespaceForVersion();
        $namespacesId[] = $namespaceForPropertyVersion->getId();

        // Sans oublier les namespaces références si indisponibles
        foreach($namespaceForPropertyVersion->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            if(!in_array($referencedNamespacesAssociation->getReferencedNamespace()->getId(), $namespacesId)){
                $namespacesId[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
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
                $property->getTextProperties()[1]->setNamespaceForVersion($classVersion->getNamespaceForVersion());
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
     * @Route("/property/{id}", name="property_show")
     * @Route("/property/{id}/namespace/{namespaceFromUrlId}", name="property_show_with_version")
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

        // FILTRAGE : Récupérer les clés de namespaces à utiliser
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }

        // Affaiblir le filtrage en rajoutant le namespaceForVersion de la classVersion si indisponible
        $namespaceForPropertyVersion = $propertyVersion->getNamespaceForVersion();
        if(!in_array($namespaceForPropertyVersion->getId(), $namespacesId)){
            $namespacesId[] = $namespaceForPropertyVersion->getId();
        }
        // Sans oublier les namespaces références si indisponibles
        foreach($namespaceForPropertyVersion->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            if(!in_array($referencedNamespacesAssociation->getReferencedNamespace()->getId(), $namespacesId)){
                $namespacesId[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
            }
        }

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
            'namespacesId' => $namespacesId
        ));
    }

    /**
     * @Route("/property/{id}/edit", name="property_edit")
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

        // FILTRAGE
        $namespaceForPropertyVersion = $propertyVersion->getNamespaceForVersion();
        $namespacesId[] = $namespaceForPropertyVersion->getId();

        // Sans oublier les namespaces références si indisponibles
        foreach($namespaceForPropertyVersion->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            if(!in_array($referencedNamespacesAssociation->getReferencedNamespace()->getId(), $namespacesId)){
                $namespacesId[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
            }
        }

        $ancestors = $em->getRepository('AppBundle:Property')->findAncestorsByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);
        $descendants = $em->getRepository('AppBundle:Property')->findDescendantsByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);
        $domainRange = $em->getRepository('AppBundle:Property')->findDomainAndRangeByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);
        $relations = $em->getRepository('AppBundle:Property')->findRelationsByPropertyVersionAndNamespacesId($propertyVersion, $namespacesId);

        $arrayClassesVersion = $em->getRepository('AppBundle:OntoClassVersion')
            ->findIdAndStandardLabelOfClassesVersionByNamespacesId($namespacesId);

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
            $range = $em->getRepository("AppBundle:OntoClass")->find($form->get("rangeVersion")->getData());
            $propertyVersion->setRange($range);
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
            'namespacesId' => $namespacesId
        ));
    }

    /**
     * @Route("/property-version/{id}/edit-validity/{validationStatus}", name="property_version_validation_status_edit")
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