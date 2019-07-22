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
        $justification->addNamespace($source->getOngoingNamespace());
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
            $entityAssociation->addNamespace($source->getOngoingNamespace());
            $entityAssociation->setCreator($this->getUser());
            $entityAssociation->setModifier($this->getUser());
            $entityAssociation->setCreationTime(new \DateTime('now'));
            $entityAssociation->setModificationTime(new \DateTime('now'));
            $entityAssociation->setDirected(FALSE);

            if ($entityAssociation->getTextProperties()->containsKey(1)) {
                $entityAssociation->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $entityAssociation->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $entityAssociation->getTextProperties()[1]->setSystemType($systemTypeExample);
                $entityAssociation->getTextProperties()[1]->addNamespace($source->getOngoingNamespace());
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
     * @Route("/entity-association/{id}/{object}/{objectId}", name="entity_association_show")
     * @param EntityAssociation $entityAssociation
     * @return Response the rendered template
     */
    public function showAction(EntityAssociation $entityAssociation)
    {
        return $this->render('entityAssociation/show.html.twig', array(
            'entityAssociation' => $entityAssociation
        ));
    }

    /**
     * @Route("/entity-association/{id}/edit", name="entity_association_edit")
     * @Route("/entity-association/{id}/{object}/{objectId}/edit", name="entity_association_objectId_edit")
     */
    public function editAction(Request $request, EntityAssociation $entityAssociation)
    {
        $em = $this->getDoctrine()->getManager();

        if($entityAssociation->getSourceObjectType() == 'class')
        {
            $source = $em->getRepository('AppBundle:OntoClass')->find($entityAssociation->getSourceClass()->getId());
            if (!$source) {
                throw $this->createNotFoundException('The class n° '.$entityAssociation->getSourceClass()->getId().' does not exist');
            }
        }
        elseif($entityAssociation->getSourceObjectType() == 'property')
        {
            $source = $em->getRepository('AppBundle:Property')->find($entityAssociation->getSourceProperty()->getId());
            if (!$source) {
                throw $this->createNotFoundException('The property n° '.$entityAssociation->getSourceProperty()->getId().' does not exist');
            }
        }

        $this->denyAccessUnlessGranted('edit', $source);

        $form = $this->createForm(EntityAssociationEditForm::class, $entityAssociation, ['object' => $entityAssociation->getSourceObjectType()]);

        if(is_null($request->get('objectId')) and $request->get('objectId') == $entityAssociation->getSource()->getId())
        {
            if($entityAssociation->getSourceObjectType() == 'class')
            {
                $form->remove('sourceClass');
            }

            if($entityAssociation->getSourceObjectType() == 'property')
            {
                $form->remove('sourceProperty');
            }
        }
        elseif(is_null($request->get('objectId')) and $request->get('objectId') == $entityAssociation->getTarget()->getId())
        {
            if($entityAssociation->getTargetObjectType() == 'class')
            {
                $form->remove('targetClass');
            }

            if($entityAssociation->getTargetObjectType() == 'property')
            {
                $form->remove('targetProperty');
            }
        }

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityAssociation = $form->getData();
            //$classAssociation->addNamespace($classAssociation->getChildClass()->getOngoingNamespace());
            $entityAssociation->setModifier($this->getUser());
            $entityAssociation->setModificationTime(new \DateTime('now'));


            $em = $this->getDoctrine()->getManager();
            $em->persist($entityAssociation);
            $em->flush();

            $this->addFlash('success', 'Relation edited !');

            $objectId = $entityAssociation->getSource()->getId();
            if(!is_null($request->get('objectId')))
                $objectId = $request->get('objectId');

            return $this->redirectToRoute($entityAssociation->getSourceObjectType().'_edit', [
                'id' => $objectId,
                '_fragment' => 'relations'
            ]);

        }

        $em = $this->getDoctrine()->getManager();
        return $this->render('entityAssociation/edit.html.twig', array(
            'source' => $entityAssociation->getSource(),
            'entityAssociation' => $entityAssociation,
            'entityAssociationForm' => $form->createView(),
        ));
    }
}