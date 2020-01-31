<?php
/**
 * Created by PhpStorm.
 * User: pc-alexandre-pro
 * Date: 07/05/2019
 * Time: 14:39
 */

namespace AppBundle\Controller;


use AppBundle\Entity\EntityAssociation;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\EntityAssociationForm;
use AppBundle\Form\EntityAssociationEditForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EntityAssociationController extends Controller
{
    /**
     * @Route("/entity-association/new/{object}/{objectId}", name="new_entity_association_form")
     */
    public function newEntityAssociationAction(Request $request, $object, $objectId)
    {
        $em = $this->getDoctrine()->getManager();

        $entityAssociation = new EntityAssociation();

        if($object == 'class')
        {
            $source = $em->getRepository('AppBundle:OntoClass')->find($objectId);
            if (!$source) {
                throw $this->createNotFoundException('The class n° '.$objectId.' does not exist');
            }
            $entityAssociation->setSourceClass($source);
        }
        elseif($object == 'property')
        {
            $source = $em->getRepository('AppBundle:Property')->find($objectId);
            if (!$source) {
                throw $this->createNotFoundException('The property n° '.$objectId.' does not exist');
            }
            $entityAssociation->setSourceProperty($source);
        }

        $this->denyAccessUnlessGranted('edit', $source);

        $systemTypeJustification = $em->getRepository('AppBundle:SystemType')->find(15); //systemType 15 = justification
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 1 = example

        $justification = new TextProperty();
        $justification->setEntityAssociation($entityAssociation);
        $justification->setSystemType($systemTypeJustification);
        $justification->addNamespace($source->$this->getUser()->getCurrentOngoingNamespace());
        $justification->setCreator($this->getUser());
        $justification->setModifier($this->getUser());
        $justification->setCreationTime(new \DateTime('now'));
        $justification->setModificationTime(new \DateTime('now'));

        $entityAssociation->addTextProperty($justification);
        $form = $this->createForm(EntityAssociationForm::class, $entityAssociation, ['object' => $object]);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityAssociation = $form->getData();
            $entityAssociation->addNamespace($this->getUser()->getCurrentOngoingNamespace());
            $entityAssociation->setCreator($this->getUser());
            $entityAssociation->setModifier($this->getUser());
            $entityAssociation->setCreationTime(new \DateTime('now'));
            $entityAssociation->setModificationTime(new \DateTime('now'));
            $entityAssociation->setDirected(FALSE);

            if ($entityAssociation->getTextProperties()->containsKey(1)) {
                $entityAssociation->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $entityAssociation->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $entityAssociation->getTextProperties()[1]->setSystemType($systemTypeExample);
                $entityAssociation->getTextProperties()[1]->addNamespace($this->getUser()->getCurrentOngoingNamespace());
                $entityAssociation->getTextProperties()[1]->setEntityAssociation($entityAssociation);
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($entityAssociation);
            $em->flush();

            $this->addFlash('success', 'Relation created !');

            return $this->redirectToRoute($object.'_edit', [
                'id' => $objectId,
                '_fragment' => 'relations'
            ]);
        }

        return $this->render('entityAssociation/new.html.twig', array(
            'object' => $object,
            'source' => $source,
            'entityAssociationForm' => $form->createView()
        ));
    }

    /**
     * @Route("/entity-association/{id}", name="entity_association_show")
     * @Route("/entity-association/{id}/inverse", name="entity_association_inverse_show")
     * @param EntityAssociation $entityAssociation
     * @return Response the rendered template
     */
    public function showAction(Request $request, EntityAssociation $entityAssociation)
    {
        $inverse = false;
        if($request->attributes->get('_route') == 'entity_association_inverse_show'){
            $inverse = true;
        }

        return $this->render('entityAssociation/show.html.twig', array(
            'entityAssociation' => $entityAssociation,
            'inverse' => $inverse
        ));
    }

    /**
     * @Route("/entity-association/{id}/edit", name="entity_association_edit")
     * @Route("/entity-association/{id}/inverse/edit", name="entity_association_inverse_edit")
     */
    public function editAction(Request $request, EntityAssociation $entityAssociation)
    {
        $inverse = false;
        if($request->attributes->get('_route') == 'entity_association_inverse_edit'){
            $inverse = true;
        }

        $em = $this->getDoctrine()->getManager();

        if($entityAssociation->getSourceObjectType() == 'class' and !$inverse)
        {
            $firstEntity = $em->getRepository('AppBundle:OntoClass')->find($entityAssociation->getSourceClass()->getId());
            if (!$firstEntity) {
                throw $this->createNotFoundException('The class n° '.$entityAssociation->getSourceClass()->getId().' does not exist');
            }
        }
        elseif($entityAssociation->getSourceObjectType() == 'property' and !$inverse)
        {
            $firstEntity = $em->getRepository('AppBundle:Property')->find($entityAssociation->getSourceProperty()->getId());
            if (!$firstEntity) {
                throw $this->createNotFoundException('The property n° '.$entityAssociation->getSourceProperty()->getId().' does not exist');
            }
        }
        elseif($entityAssociation->getTargetObjectType() == 'class' and $inverse)
        {
            $firstEntity = $em->getRepository('AppBundle:OntoClass')->find($entityAssociation->getTargetClass()->getId());
            if (!$firstEntity) {
                throw $this->createNotFoundException('The class n° '.$entityAssociation->getTargetClass()->getId().' does not exist');
            }
        }
        elseif($entityAssociation->getTargetObjectType() == 'property' and $inverse)
        {
            $firstEntity = $em->getRepository('AppBundle:Property')->find($entityAssociation->getTargetProperty()->getId());
            if (!$firstEntity) {
                throw $this->createNotFoundException('The property n° '.$entityAssociation->getTargetProperty()->getId().' does not exist');
            }
        }

        $this->denyAccessUnlessGranted('edit', $firstEntity);

        $form = $this->createForm(EntityAssociationEditForm::class, $entityAssociation, ['object' => $entityAssociation->getSourceObjectType()]);

        if(!$inverse)
        {
            if($entityAssociation->getSourceObjectType() == 'class'){
                $form->remove('sourceClass');
            }

            if($entityAssociation->getSourceObjectType() == 'property'){
                $form->remove('sourceProperty');
            }
        }
        else
        {
            if($entityAssociation->getTargetObjectType() == 'class'){
                $form->remove('targetClass');
            }

            if($entityAssociation->getTargetObjectType() == 'property'){
                $form->remove('targetProperty');
            }
        }

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityAssociation = $form->getData();
            $entityAssociation->setModifier($this->getUser());
            $entityAssociation->setModificationTime(new \DateTime('now'));


            $em = $this->getDoctrine()->getManager();
            $em->persist($entityAssociation);
            $em->flush();

            $this->addFlash('success', 'Relation edited !');

            if(!$inverse){
                return $this->redirectToRoute($entityAssociation->getSourceObjectType().'_edit', [
                    'id' => $entityAssociation->getSource()->getId(),
                    '_fragment' => 'relations'
                ]);
            }
            else{
                return $this->redirectToRoute($entityAssociation->getTargetObjectType().'_edit', [
                    'id' => $entityAssociation->getTarget()->getId(),
                    '_fragment' => 'relations'
                ]);
            }
        }

        $em = $this->getDoctrine()->getManager();
        return $this->render('entityAssociation/edit.html.twig', array(
            'entityAssociation' => $entityAssociation,
            'inverse' => $inverse,
            'entityAssociationForm' => $form->createView(),
        ));
    }
}