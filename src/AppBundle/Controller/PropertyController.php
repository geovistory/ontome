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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

        $this->denyAccessUnlessGranted('edit', $class);

        if($type !== 'ingoing' && $type !== 'outgoing') throw $this->createNotFoundException('The requested property type "'.$type.'" does not exist!');

        $em = $this->getDoctrine()->getManager();
        $systemTypeScopeNote = $em->getRepository('AppBundle:SystemType')->find(1); //systemType 1 = scope note
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 1 = scope note

        $scopeNote = new TextProperty();
        $scopeNote->setProperty($property);
        $scopeNote->setSystemType($systemTypeScopeNote);
        $scopeNote->addNamespace($this->getUser()->getCurrentOngoingNamespace());
        $scopeNote->setCreator($this->getUser());
        $scopeNote->setModifier($this->getUser());
        $scopeNote->setCreationTime(new \DateTime('now'));
        $scopeNote->setModificationTime(new \DateTime('now'));

        $property->addTextProperty($scopeNote);

        $label = new Label();
        $label->setProperty($property);
        $label->addNamespace($this->getUser()->getCurrentOngoingNamespace());
        $label->setIsStandardLabelForLanguage(true);
        $label->setCreator($this->getUser());
        $label->setModifier($this->getUser());
        $label->setCreationTime(new \DateTime('now'));
        $label->setModificationTime(new \DateTime('now'));

        $property->addLabel($label);
        if($type == 'outgoing') {
            $property->setDomain($class);
        }
        elseif ($type == 'ingoing') {
            $property->setRange($class);
        }

        $property->setIsManualIdentifier(is_null($class->getOngoingNamespace()->getTopLevelNamespace()->getPropertyPrefix()));
        $property->addNamespace($this->getUser()->getCurrentOngoingNamespace());
        $property->setCreator($this->getUser());
        $property->setModifier($this->getUser());

        $form = null;
        if($type == 'outgoing') {
            $form = $this->createForm(OutgoingPropertyQuickAddForm::class, $property);
        }
        elseif ($type == 'ingoing') {
            $form = $this->createForm(IngoingPropertyQuickAddForm::class, $property);
        }


        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $property = $form->getData();
            if($type == 'outgoing') {
                $property->setDomain($class);
            }
            elseif ($type == 'ingoing') {
                $property->setRange($class);
            }

            $property->setCreator($this->getUser());
            $property->setModifier($this->getUser());
            $property->setCreationTime(new \DateTime('now'));
            $property->setModificationTime(new \DateTime('now'));

            if($property->getTextProperties()->containsKey(1)){
                $property->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $property->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $property->getTextProperties()[1]->setSystemType($systemTypeExample);
                $property->getTextProperties()[1]->addNamespace($this->getUser()->getCurrentOngoingNamespace());
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
     * @param string $id
     * @return Response the rendered template
     */
    public function showAction(Property $property)
    {
        // Récupérer la version de la propriété demandée
        // Dans l'ordre : (la version demandée - TO DO) > la version ongoing > la version la plus récente > la première version dans la boucle

        $propertyVersion = null;
        foreach($property->getPropertyVersions() as $iPropertyVersion){
            if(is_null($propertyVersion)){
                $propertyVersion = $iPropertyVersion;
            }
            if($iPropertyVersion->getNamespaceForVersion()->getIsOngoing()){
                $propertyVersion = $iPropertyVersion;
                break;
            }
            if($iPropertyVersion->getCreationTime() > $propertyVersion->getCreationTime()){
                $propertyVersion = $iPropertyVersion;
            }
        }
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
     * @param string $id
     * @return Response the rendered template
     */
    public function editAction(Property $property, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $property);

        $form = $this->createForm(PropertyEditForm::class, $property);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($property);
            $em->flush();

            $this->addFlash('success', 'Property updated!');
            return $this->redirectToRoute('property_edit', [
                'id' => $property->getId(),
                '_fragment' => 'identification'
            ]);
        }

        $propertyTemp = new Property();
        $propertyTemp->addNamespace($property->getOngoingNamespace());
        $propertyTemp->setIdentifierInNamespace($property->getIdentifierInNamespace());
        $propertyTemp->setIsManualIdentifier(is_null($property->getOngoingNamespace()->getTopLevelNamespace()->getPropertyPrefix()));
        $propertyTemp->setCreator($this->getUser());
        $propertyTemp->setModifier($this->getUser());
        $propertyTemp->setCreationTime(new \DateTime('now'));
        $propertyTemp->setModificationTime(new \DateTime('now'));
        $propertyTemp->setDomain($property->getDomain());
        $propertyTemp->setRange($property->getRange());

        $formIdentifier = $this->createForm(PropertyEditIdentifierForm::class, $propertyTemp);
        $formIdentifier->handleRequest($request);
        if ($formIdentifier->isSubmitted() && $formIdentifier->isValid()) {
            $property->setIdentifierInNamespace($propertyTemp->getIdentifierInNamespace());
            $em = $this->getDoctrine()->getManager();
            $em->persist($property);
            $em->flush();

            $this->addFlash('success', 'Property updated!');
            return $this->redirectToRoute('property_edit', [
                'id' => $property->getId(),
                '_fragment' => 'identification'
            ]);
        }

        $em = $this->getDoctrine()->getManager();

        if (!is_null($this->getUser()) && $this->getUser()->getCurrentActiveProject()->getId() != 21) {
            // L'utilisateur est connecté et le projet actif n'est pas le projet public
            $user = $this->getUser();

            $ancestors = $em->getRepository('AppBundle:Property')
                ->findFilteredAncestorsById($property, $user);

            $descendants = $em->getRepository('AppBundle:Property')
                ->findFilteredDescendantsById($property, $user);

            $domainRange = $em->getRepository('AppBundle:Property')
                ->findDomainRangeById($property);

            $relations = $em->getRepository('AppBundle:Property')
                ->findFilteredRelationsById($property, $user);

            $activeNamespaces = $em->getRepository('AppBundle:OntoNamespace')
                ->findAllActiveNamespacesForUser($user);

            $propertyVersionNamespace = null;
            foreach ($property->getNamespaces() as $namespace){
                if(is_null($propertyVersionNamespace)){
                    $propertyVersionNamespace = $namespace;
                }

                if($namespace->getCreationTime() > $propertyVersionNamespace->getCreationTime()){
                    $propertyVersionNamespace = $namespace;
                }

                if($namespace->getIsOngoing()){
                    $propertyVersionNamespace = $namespace;
                    break;
                }
            }
            $activeNamespaces[] = $propertyVersionNamespace;
        }
        else{
            $ancestors = $em->getRepository('AppBundle:Property')
                ->findAncestorsById($property);

            $descendants = $em->getRepository('AppBundle:Property')
                ->findDescendantsById($property);

            $domainRange = $em->getRepository('AppBundle:Property')
                ->findDomainRangeById($property);

            $relations = $em->getRepository('AppBundle:Property')
                ->findRelationsById($property);

            $activeNamespaces = $em->getRepository('AppBundle:OntoNamespace')
                ->findActiveNamespacesInPublicProject();

            $propertyVersionNamespace = null;
            foreach ($property->getNamespaces() as $namespace){
                if(is_null($propertyVersionNamespace)){
                    $propertyVersionNamespace = $namespace;
                }

                if($namespace->getCreationTime() > $propertyVersionNamespace->getCreationTime()){
                    $propertyVersionNamespace = $namespace;
                }

                if($namespace->getIsOngoing()){
                    $propertyVersionNamespace = $namespace;
                    break;
                }
            }
            $activeNamespaces[] = $propertyVersionNamespace;
        }


        $this->get('logger')
            ->info('Showing property: '.$property->getIdentifierInNamespace());


        return $this->render('property/edit.html.twig', array(
            'property' => $property,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'domainRange' => $domainRange,
            'relations' => $relations,
            'propertyForm' => $form->createView(),
            'propertyIdentifierForm' => $formIdentifier->createView(),
            'activeNamespaces' => $activeNamespaces
        ));
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