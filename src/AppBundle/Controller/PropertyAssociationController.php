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
        $systemTypeJustification = $em->getRepository('AppBundle:SystemType')->find(15); //systemType 15 = justification
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 7 = example

        $justification = new TextProperty();
        $justification->setPropertyAssociation($propertyAssociation);
        $justification->setSystemType($systemTypeJustification);
        $justification->addNamespace($childProperty->getOngoingNamespace());
        $justification->setCreator($this->getUser());
        $justification->setModifier($this->getUser());
        $justification->setCreationTime(new \DateTime('now'));
        $justification->setModificationTime(new \DateTime('now'));

        $propertyAssociation->addTextProperty($justification);
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
                $propertyAssociation->getTextProperties()[1]->addNamespace($childProperty->getOngoingNamespace());
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