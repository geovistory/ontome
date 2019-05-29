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
        }
        if($object == 'class') {
            $relations = $em->getRepository('AppBundle:OntoClass')->findRelationsById($source);
        }
        elseif($object == 'property')
        {
            $relations = $em->getRepository('AppBundle:Property')->findRelationsById($source);
        }

        return $this->render('entityAssociation/new.html.twig', array(
            'object' => $object,
            'source' => $source,
            'entityAssociationForm' => $form->createView(),
            'relations' => $relations
        ));
    }

    /**
     * @Route("/entity-association/{id}", name="entity_association_show")
     * @Route("/entity-association/{id}/{object}/{objectId}", name="entity_association_show")
     * @param EntityAssociation $entityAssociation
     * @param $object
     * @param $objectId
     * @return Response the rendered template
     */
    public function showAction(EntityAssociation $entityAssociation, $object=null, $objectId=null)
    {
        if($object != null && $objectId != null)
        {
            if($object == 'class')
            {
                if($entityAssociation->getSourceClass()->getId() != $objectId && !$entityAssociation->getDirected())
                {
                    $entityAssociation->inverseClasses();
                }
            }
            elseif($object == 'property')
            {
                if($entityAssociation->getSourceProperty()->getId() != $objectId && !$entityAssociation->getDirected())
                {
                    $entityAssociation->inverseProperties();
                }
            }
        }

        return $this->render('entityAssociation/show.html.twig', array(
            'entityAssociation' => $entityAssociation,
            'object' => $object
        ));
    }

    /**
     * @Route("/entity-association/{id}/edit", name="entity_association_edit")
     * @Route("/entity-association/{id}/{object}/{objectId}/edit", name="entity_association_edit")
     */
    public function editAction(Request $request, EntityAssociation $entityAssociation, $object=null, $objectId=null)
    {
        $em = $this->getDoctrine()->getManager();

        if($object == 'class')
        {
            $source = $em->getRepository('AppBundle:OntoClass')->find($objectId);
            if (!$source) {
                throw $this->createNotFoundException('The class n° '.$objectId.' does not exist');
            }
        }
        elseif($object == 'property')
        {
            $source = $em->getRepository('AppBundle:Property')->find($objectId);
            if (!$source) {
                throw $this->createNotFoundException('The property n° '.$objectId.' does not exist');
            }
        }

        if($entityAssociation->getSource() !== $source)
        {
            $entityAssociation->inverseEntities();
        }

        $this->denyAccessUnlessGranted('edit', $source);

        $form = $this->createForm(EntityAssociationForm::class, $entityAssociation);

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

            return $this->redirectToRoute('entity_association_edit', [
                'id' => $entityAssociation->getId(),
                'object' => $object,
                'objectId' => $objectId
            ]);

        }

        $em = $this->getDoctrine()->getManager();

        return $this->render('entityAssociation/edit.html.twig', array(
            'source' => $entityAssociation->getSource(),
            'object' => $object,
            'entityAssociation' => $entityAssociation,
            'entityAssociationForm' => $form->createView(),
        ));
    }
}