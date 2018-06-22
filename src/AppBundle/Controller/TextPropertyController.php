<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 15/10/2017
 * Time: 15:03
 */

namespace AppBundle\Controller;

use AppBundle\Entity\ClassAssociation;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\TextPropertyForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TextPropertyController extends Controller
{
    /**
     * @Route("/text-property/{id}", name="text_property_show")
     * @param string $id
     * @return Response the rendered template
     */
    public function showAction(TextProperty $textProperty)
    {
        $this->get('logger')
            ->info('Showing text property: ' . $textProperty->getId());
        return $this->render('textProperty/show.html.twig', array(
            'textProperty' => $textProperty
        ));
    }

    /**
     * @Route("/text-property/{id}/edit", name="text_property_edit")
     */
    public function editAction(TextProperty $textProperty, Request $request)
    {
        if(!is_null($textProperty->getClassAssociation())){
            $object = $textProperty->getClassAssociation()->getChildClass();
        }
        else if(!is_null($textProperty->getPropertyAssociation())){
            $object = $textProperty->getPropertyAssociation()->getChildProperty();
        }
        else throw $this->createNotFoundException('The related object for the text property  n° '.$textProperty->getId().' does not exist. Please contact an administrator.');

        $this->denyAccessUnlessGranted('edit', $object);

        $form = $this->createForm(TextPropertyForm::class, $textProperty);

        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $textProperty->setModifier($this->getUser());
            $em->persist($textProperty);
            $em->flush();

            $this->addFlash('success', 'Text property Updated!');

            return $this->redirectToRoute('text_property_edit', [
                'id' => $textProperty->getId()
            ]);
        }

        return $this->render('textProperty/edit.html.twig', [
            'textPropertyForm' => $form->createView(),
            'associatedObject' => $object,
            'textProperty' => $textProperty
        ]);

    }

    /**
     * @Route("/text-property/{type}/new/{object}/{objectId}", name="text_property_new")
     */
    public function newAction($type, $object, $objectId, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $textProperty = new TextProperty();

        if($object === 'class-association') {
            $associatedEntity = $em->getRepository('AppBundle:ClassAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The class association n° '.$objectId.' does not exist');
            }
            $textProperty->setClassAssociation($associatedEntity);
            $associatedObject = $associatedEntity->getChildClass();
        }
        else if($object === 'property-association') {
            $associatedEntity = $em->getRepository('AppBundle:PropertyAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property association n° '.$objectId.' does not exist');
            }
            $textProperty->setPropertyAssociation($associatedEntity);
            $associatedObject = $associatedEntity->getChildProperty();
        }
        else throw $this->createNotFoundException('The requested object "'.$object.'" does not exist!');

        if($type === 'scope-note') {
            $systemType = $em->getRepository('AppBundle:SystemType')->find(1); //systemType 1 = scope note
        }
        else if($type === 'example') {
            $systemType = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 7 = example
        }
        else throw $this->createNotFoundException('The requested text property type "'.$type.'" does not exist!');

        $this->denyAccessUnlessGranted('edit', $associatedObject);

        $textProperty->setSystemType($systemType);
        $textProperty->setNamespace($associatedObject->getOngoingNamespace());
        $textProperty->setCreator($this->getUser());
        $textProperty->setModifier($this->getUser());
        $textProperty->setCreationTime(new \DateTime('now'));
        $textProperty->setModificationTime(new \DateTime('now'));


        $form = $this->createForm(TextPropertyForm::class, $textProperty);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $textProperty = $form->getData();
            $textProperty->setSystemType($systemType);
            $textProperty->setNamespace($associatedObject->getOngoingNamespace());
            $textProperty->setCreator($this->getUser());
            $textProperty->setModifier($this->getUser());
            $textProperty->setCreationTime(new \DateTime('now'));
            $textProperty->setModificationTime(new \DateTime('now'));

            $em = $this->getDoctrine()->getManager();
            $em->persist($textProperty);
            $em->flush();

            $this->addFlash('success', 'Text property Created!');

            return $this->redirectToRoute('text_property_edit', [
                'id' => $textProperty->getId()
            ]);

        }

        return $this->render('textProperty/new.html.twig', [
            'textProperty' => $textProperty,
            'textPropertyForm' => $form->createView()
        ]);

    }
}