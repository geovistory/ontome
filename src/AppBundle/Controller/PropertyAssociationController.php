<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 15/01/2018
 * Time: 15:19
 */

namespace AppBundle\Controller;

use AppBundle\Entity\PropertyAssociation;
use AppBundle\Entity\Property;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\PropertyAssociationEditForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Form\ParentPropertyAssociationForm;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class PropertyAssociationController extends Controller
{

    /**
     * @Route("/parent-property-association/new/{childProperty}", name="new_parent_property_form")
     */
    public function newParentAction(Request $request, Property $childProperty)
    {
        $propertyAssociation = new PropertyAssociation();

        $this->denyAccessUnlessGranted('edit', $childProperty->getPropertyVersionForDisplay());


        $em = $this->getDoctrine()->getManager();
        $systemTypeJustification = $em->getRepository('AppBundle:SystemType')->find(15); //systemType 15 = justification
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 7 = example

        $justification = new TextProperty();
        $justification->setPropertyAssociation($propertyAssociation);
        $justification->setSystemType($systemTypeJustification);
        $justification->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
        $justification->setCreator($this->getUser());
        $justification->setModifier($this->getUser());
        $justification->setCreationTime(new \DateTime('now'));
        $justification->setModificationTime(new \DateTime('now'));

        $propertyAssociation->addTextProperty($justification);
        $propertyAssociation->setChildProperty($childProperty);

        // FILTRAGE
        $namespaceForChildPropertyVersion = $childProperty->getPropertyVersionForDisplay()->getNamespaceForVersion();
        $namespacesId[] = $namespaceForChildPropertyVersion->getId();

        // Sans oublier les namespaces références si indisponibles
        foreach($namespaceForChildPropertyVersion->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            if(!in_array($referencedNamespacesAssociation->getReferencedNamespace()->getId(), $namespacesId)){
                $namespacesId[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
            }
        }

        $arrayPropertiesVersion = $em->getRepository('AppBundle:PropertyVersion')
            ->findIdAndStandardLabelOfPropertiesVersionByNamespacesId($namespacesId);

        $form = $this->createForm(ParentPropertyAssociationForm::class, $propertyAssociation, array(
            "propertiesVersion" => $arrayPropertiesVersion
        ));

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $propertyAssociation = $form->getData();
            $parentProperty = $em->getRepository("AppBundle:Property")->find($form->get("parentPropertyVersion")->getData());
            $propertyAssociation->setParentProperty($parentProperty);
            $propertyAssociation->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
            $propertyAssociation->setChildPropertyNamespace(
                $em->getRepository("AppBundle:PropertyVersion")
                    ->findPropertyVersionByPropertyAndNamespacesId($childProperty, $namespacesId)
                    ->getNamespaceForVersion()
            );
            $propertyAssociation->setParentPropertyNamespace(
                $em->getRepository("AppBundle:PropertyVersion")
                    ->findPropertyVersionByPropertyAndNamespacesId($parentProperty, $namespacesId)
                    ->getNamespaceForVersion()
            );
            $propertyAssociation->setCreator($this->getUser());
            $propertyAssociation->setModifier($this->getUser());
            $propertyAssociation->setCreationTime(new \DateTime('now'));
            $propertyAssociation->setModificationTime(new \DateTime('now'));

            if($propertyAssociation->getTextProperties()->containsKey(1)){
                $propertyAssociation->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $propertyAssociation->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $propertyAssociation->getTextProperties()[1]->setSystemType($systemTypeExample);
                $propertyAssociation->getTextProperties()[1]->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
                $propertyAssociation->getTextProperties()[1]->setPropertyAssociation($propertyAssociation);
            }


            $em = $this->getDoctrine()->getManager();
            $em->persist($propertyAssociation);
            $em->flush();

            return $this->redirectToRoute('property_show', [
                'id' => $propertyAssociation->getChildProperty()->getId(),
                '_fragment' => 'property-hierarchy'
            ]);

        }

        $em = $this->getDoctrine()->getManager();

        // FILTRAGE : Récupérer les clés de namespaces à utiliser
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }

        $ancestors = $em->getRepository('AppBundle:Property')
            ->findAncestorsByPropertyVersionAndNamespacesId($childProperty->getPropertyVersionForDisplay(), $namespacesId);

        return $this->render('propertyAssociation/newParent.html.twig', [
            'childProperty' => $childProperty,
            'parentPropertyAssociationForm' => $form->createView(),
            'ancestors' => $ancestors
        ]);
    }

    /**
     * @Route("/property-association/{id}", name="property_association_show")
     * @param PropertyAssociation $propertyAssociation
     * @return Response the rendered template
     */
    public function showAction(PropertyAssociation $propertyAssociation)
    {
        $this->get('logger')
            ->info('Showing property association: '.$propertyAssociation->getObjectIdentification());


        return $this->render('propertyAssociation/show.html.twig', array(
            'property' => $propertyAssociation->getChildProperty(),
            'propertyAssociation' => $propertyAssociation
        ));

    }

    /**
     * @Route("/property-association/{id}/edit", name="property_association_edit")
     */
    public function editAction(Request $request, PropertyAssociation $propertyAssociation)
    {
        // Récupérer la version de la propriété demandée
        $childPropertyVersion = $propertyAssociation->getChildProperty()->getPropertyVersionForDisplay();

        // On doit avoir une version de la propriété sinon on lance une exception.
        if(is_null($childPropertyVersion)){
            throw $this->createNotFoundException('The property n°'.$propertyAssociation->getChildProperty()->getId().' has no version. Please contact an administrator.');
        }

        $this->denyAccessUnlessGranted('edit', $childPropertyVersion);

        $em = $this->getDoctrine()->getManager();

        // FILTRAGE : Récupérer les clés de namespaces à utiliser
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }

        // Affaiblir le filtrage en rajoutant le namespaceForVersion de la classVersion si indisponible
        $namespaceForChildPropertyVersion = $childPropertyVersion->getNamespaceForVersion();
        if(!in_array($namespaceForChildPropertyVersion->getId(), $namespacesId)){
            $namespacesId[] = $namespaceForChildPropertyVersion->getId();
        }
        // Sans oublier les namespaces références si indisponibles
        foreach($namespaceForChildPropertyVersion->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            if(!in_array($referencedNamespacesAssociation->getReferencedNamespace()->getId(), $namespacesId)){
                $namespacesId[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
            }
        }

        $arrayPropertiesVersion = $em->getRepository('AppBundle:PropertyVersion')
            ->findIdAndStandardLabelOfPropertiesVersionByNamespacesId($namespacesId);

        $form = $this->createForm(PropertyAssociationEditForm::class, $propertyAssociation, array(
            'propertiesVersion' => $arrayPropertiesVersion,
            'defaultParent' => $propertyAssociation->getParentProperty()->getId()));

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $parentProperty = $em->getRepository("AppBundle:Property")->find($form->get("parentPropertyVersion")->getData());
            $propertyAssociation->setParentProperty($parentProperty);

            $propertyAssociation = $form->getData();
            $propertyAssociation->setModifier($this->getUser());
            $propertyAssociation->setModificationTime(new \DateTime('now'));

            $em = $this->getDoctrine()->getManager();
            $em->persist($propertyAssociation);
            $em->flush();

            return $this->redirectToRoute('property_association_edit', [
                'id' => $propertyAssociation->getId()
            ]);

        }

        $em = $this->getDoctrine()->getManager();

        return $this->render('propertyAssociation/edit.html.twig', array(
            'property' => $propertyAssociation->getChildProperty(),
            'propertyAssociation' => $propertyAssociation,
            'propertyAssociationForm' => $form->createView(),
        ));
    }


}