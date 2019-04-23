<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 03/07/2018
 * Time: 10:56
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Label;
use AppBundle\Form\LabelForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LabelController  extends Controller
{
    /**
     * @Route("/label/{id}", name="label_show")
     * @param string $id
     * @return Response the rendered template
     */
    public function showAction(Label $label)
    {
        $this->get('logger')
            ->info('Showing text property: ' . $label->getId());
        return $this->render('label/show.html.twig', array(
            'label' => $label
        ));
    }

    /**
     * @Route("/label/{id}/edit", name="label_edit")
     */
    public function editAction(Label $label, Request $request)
    {
        if(!is_null($label->getClass())){
            $object = $label->getClass();
            $redirectToRoute = 'class_edit';
            $redirectToRouteFragment = 'identification';
        }
        else if(!is_null($label->getProperty())){
            $object = $label->getProperty();
            $redirectToRoute = 'property_edit';
            $redirectToRouteFragment = 'identification';
        }
        else throw $this->createNotFoundException('The related object for the label n° '.$label->getId().' does not exist. Please contact an administrator.');

        $this->denyAccessUnlessGranted('edit', $object);

        $label->setModifier($this->getUser());

        $form = $this->createForm(LabelForm::class, $label);

        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $label->setModifier($this->getUser());
            $em->persist($label);
            $em->flush();

            $this->addFlash('success', 'Label updated!');

            return $this->redirectToRoute($redirectToRoute, [
                'id' => $object->getId(),
                '_fragment' => $redirectToRouteFragment
            ]);
        }

        return $this->render('label/edit.html.twig', [
            'labelForm' => $form->createView(),
            'associatedObject' => $object,
            'label' => $label
        ]);

    }

    /**
     * @Route("/label/new/{object}/{objectId}", name="label_new")
     */
    public function newAction($object, $objectId, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $label = new Label();

        if($object === 'class') {
            $associatedEntity = $em->getRepository('AppBundle:OntoClass')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The class n° '.$objectId.' does not exist');
            }
            $label->setClass($associatedEntity);
            $associatedObject = $associatedEntity;
            $redirectToRoute = 'class_edit';
            $redirectToRouteFragment = 'identification';
        }
        else if($object === 'property') {
            $associatedEntity = $em->getRepository('AppBundle:Property')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property n° '.$objectId.' does not exist');
            }
            $label->setProperty($associatedEntity);
            $associatedObject = $associatedEntity;
            $redirectToRoute = 'property_edit';
            $redirectToRouteFragment = 'identification';
        }
        else if($object === 'property') {
            $associatedEntity = $em->getRepository('AppBundle:Property')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property n° '.$objectId.' does not exist');
            }
            $label->setProperty($associatedEntity);
            $associatedObject = $associatedEntity;
            $redirectToRoute = 'property_edit';
            $redirectToRouteFragment = 'identification';
        }
        else throw $this->createNotFoundException('The requested object "'.$object.'" does not exist!');

        $this->denyAccessUnlessGranted('edit', $associatedObject);
        
        $label->addNamespace($associatedObject->getOngoingNamespace());
        $label->setCreator($this->getUser());
        $label->setModifier($this->getUser());
        $label->setCreationTime(new \DateTime('now'));
        $label->setModificationTime(new \DateTime('now'));


        $form = $this->createForm(LabelForm::class, $label);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $label = $form->getData();
            $label->addNamespace($associatedObject->getOngoingNamespace());
            $label->setCreator($this->getUser());
            $label->setModifier($this->getUser());
            $label->setCreationTime(new \DateTime('now'));
            $label->setModificationTime(new \DateTime('now'));

            $em = $this->getDoctrine()->getManager();
            $em->persist($label);
            $em->flush();

            $this->addFlash('success', 'Label created!');

            return $this->redirectToRoute($redirectToRoute, [
                'id' => $object->getId(),
                '_fragment' => $redirectToRouteFragment
            ]);

        }

        return $this->render('label/new.html.twig', [
            'label' => $label,
            'labelForm' => $form->createView()
        ]);

    }

}