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

        $this->denyAccessUnlessGranted('edit', $childProperty);


        $em = $this->getDoctrine()->getManager();
        $systemTypeScopeNote = $em->getRepository('AppBundle:SystemType')->find(1); //systemType 1 = scope note
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 1 = scope note

        $scopeNote = new TextProperty();
        $scopeNote->setPropertyAssociation($propertyAssociation);
        $scopeNote->setSystemType($systemTypeScopeNote);
        $scopeNote->setNamespace($childProperty->getOngoingNamespace());
        $scopeNote->setCreator($this->getUser());
        $scopeNote->setModifier($this->getUser());
        $scopeNote->setCreationTime(new \DateTime('now'));
        $scopeNote->setModificationTime(new \DateTime('now'));

        $propertyAssociation->addTextProperty($scopeNote);
        $propertyAssociation->setChildProperty($childProperty);

        $form = $this->createForm(ParentPropertyAssociationForm::class, $propertyAssociation);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $propertyAssociation = $form->getData();
            $propertyAssociation->addNamespace($childProperty->getOngoingNamespace());
            $propertyAssociation->setCreator($this->getUser());
            $propertyAssociation->setModifier($this->getUser());
            $propertyAssociation->setCreationTime(new \DateTime('now'));
            $propertyAssociation->setModificationTime(new \DateTime('now'));

            if($propertyAssociation->getTextProperties()->containsKey(1)){
                $propertyAssociation->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $propertyAssociation->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $propertyAssociation->getTextProperties()[1]->setSystemType($systemTypeExample);
                $propertyAssociation->getTextProperties()[1]->setNamespace($childProperty->getOngoingNamespace());
                $propertyAssociation->getTextProperties()[1]->setPropertyAssociation($propertyAssociation);
            }


            $em = $this->getDoctrine()->getManager();
            $em->persist($propertyAssociation);
            $em->flush();

            return $this->redirectToRoute('property_show', [
                'id' => $propertyAssociation->getChildProperty()->getId()
            ]);

        }

        $em = $this->getDoctrine()->getManager();

        $ancestors = $em->getRepository('AppBundle:Property')
            ->findAncestorsById($childProperty);
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

        $this->denyAccessUnlessGranted('edit', $propertyAssociation->getChildProperty());

        $form = $this->createForm(PropertyAssociationEditForm::class, $propertyAssociation);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $propertyAssociation = $form->getData();
            //$propertyAssociation->addNamespace($propertyAssociation->getChildProperty()->getOngoingNamespace());
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