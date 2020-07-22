<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 03/07/2018
 * Time: 10:56
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Label;
use AppBundle\Entity\OntoClass;
use AppBundle\Entity\Property;
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
        $canInverseLabel = false;

        if(!is_null($label->getClass())){
            $object = $label->getClass();
            $redirectToRoute = 'class_edit';
            $redirectToRouteFragment = 'identification';
        }
        else if(!is_null($label->getProperty())){
            $object = $label->getProperty();
            $redirectToRoute = 'property_edit';
            $redirectToRouteFragment = 'identification';
            $canInverseLabel = true;
        }
        else if(!is_null($label->getProfile())){
            $object = $label->getProfile();
            $redirectToRoute = 'profile_edit';
            $redirectToRouteFragment = 'identification';
        }
        else if(!is_null($label->getProject())){
            $object = $label->getProject();
            $redirectToRoute = 'project_edit';
            $redirectToRouteFragment = 'identification';
        }
        else if(!is_null($label->getNamespace())){
            $object = $label->getNamespace();
            $redirectToRoute = 'namespace_edit';
            $redirectToRouteFragment = 'identification';
        }
        else throw $this->createNotFoundException('The related object for the label n° '.$label->getId().' does not exist. Please contact an administrator.');

        if($object instanceof OntoClass){
            $this->denyAccessUnlessGranted('edit', $object->getClassVersionForDisplay());
        }
        elseif($object instanceof Property){
            $this->denyAccessUnlessGranted('edit', $object->getPropertyVersionForDisplay());
        }
        else{
            $this->denyAccessUnlessGranted('edit', $object);
        }

        $label->setModifier($this->getUser());

        $form = $this->createForm(LabelForm::class, $label, ['canInverseLabel' => $canInverseLabel]);

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
            'label' => $label,
            'canInverseLabel' => $canInverseLabel
        ]);
    }

    /**
     * @Route("/label/new/{object}/{objectId}", name="label_new")
     */
    public function newAction($object, $objectId, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $label = new Label();
        $canInverseLabel = false;

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
            $canInverseLabel = true;
        }
        else if($object === 'profile') {
            $associatedEntity = $em->getRepository('AppBundle:Profile')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The profile n° '.$objectId.' does not exist');
            }
            $label->setProfile($associatedEntity);
            $associatedObject = $associatedEntity;
            $redirectToRoute = 'profile_edit';
            $redirectToRouteFragment = 'identification';
        }
        else if($object === 'project') {
            $associatedEntity = $em->getRepository('AppBundle:Project')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The project n° '.$objectId.' does not exist');
            }
            $label->setProject($associatedEntity);
            $associatedObject = $associatedEntity;
            $redirectToRoute = 'project_edit';
            $redirectToRouteFragment = 'identification';
        }
        else if($object === 'namespace') {
            $associatedEntity = $em->getRepository('AppBundle:OntoNamespace')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The namepsace n° '.$objectId.' does not exist');
            }
            $label->setNamespace($associatedEntity);
            $associatedObject = $associatedEntity;
            $redirectToRoute = 'namespace_edit';
            $redirectToRouteFragment = 'identification';
        }
        else throw $this->createNotFoundException('The requested object "'.$object.'" does not exist!');

        $this->denyAccessUnlessGranted('edit', $associatedObject);

        //ongoingNamespace associated to the label for any kind of object, except Project or Profile
        if($object !== 'project' && $object !== 'profile'  && $object !== 'namespace') {
            $label->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
        }

        $label->setCreator($this->getUser());
        $label->setModifier($this->getUser());
        $label->setCreationTime(new \DateTime('now'));
        $label->setModificationTime(new \DateTime('now'));


        $form = $this->createForm(LabelForm::class, $label, ['canInverseLabel' => $canInverseLabel]);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $label = $form->getData();

            //ongoingNamespace associated to the label for any kind of object, except Project or Profile
            if($object !== 'project' && $object !== 'profile' && $object !== 'namespace') {
                $label->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
            }

            $label->setCreator($this->getUser());
            $label->setModifier($this->getUser());
            $label->setCreationTime(new \DateTime('now'));
            $label->setModificationTime(new \DateTime('now'));

            $em = $this->getDoctrine()->getManager();
            $em->persist($label);
            $em->flush();

            $this->addFlash('success', 'Label created!');

            return $this->redirectToRoute($redirectToRoute, [
                'id' => $objectId,
                '_fragment' => $redirectToRouteFragment
            ]);

        }

        return $this->render('label/new.html.twig', [
            'label' => $label,
            'labelForm' => $form->createView(),
            'canInverseLabel' => $canInverseLabel
        ]);

    }

}