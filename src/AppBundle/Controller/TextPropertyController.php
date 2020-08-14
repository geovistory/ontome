<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 15/10/2017
 * Time: 15:03
 */

namespace AppBundle\Controller;

use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoClassVersion;
use AppBundle\Entity\Property;
use AppBundle\Entity\SystemType;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\TextPropertyForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
     * @Route("/text-property/{id}/inverse/edit", name="text_property_inverse_edit")
     * @param TextProperty $textProperty
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction(TextProperty $textProperty, Request $request)
    {
        if(!is_null($textProperty->getClassAssociation())){
            $object = $textProperty->getClassAssociation();
            $redirectToRoute = 'class_association_edit';
            $redirectToRouteFragment = 'justifications';
        }
        else if(!is_null($textProperty->getPropertyAssociation())){
            $object = $textProperty->getPropertyAssociation();
            $redirectToRoute = 'property_association_edit';
            $redirectToRouteFragment = 'justifications';
        }
        else if(!is_null($textProperty->getEntityAssociation())){
            $object = $textProperty->getEntityAssociation();

            $redirectToRoute = 'entity_association_edit';
            if($request->attributes->get('_route') == 'text_property_inverse_edit'){
                $redirectToRoute = 'entity_association_inverse_edit';
            }

            $redirectToRouteFragment = 'justifications';
        }
        else if(!is_null($textProperty->getClass())){
            $object = $textProperty->getClass();
            $redirectToRoute = 'class_edit';
            $redirectToRouteFragment = 'definition';
        }
        else if(!is_null($textProperty->getProperty())){
            $object = $textProperty->getProperty();
            $redirectToRoute = 'property_edit';
            $redirectToRouteFragment = 'definition';
        }
        else if(!is_null($textProperty->getProject())){
            $object = $textProperty->getProject();
            $redirectToRoute = 'project_edit';
            $redirectToRouteFragment = 'definition';
        }
        else if(!is_null($textProperty->getProfile())){
            $object = $textProperty->getProfile();
            $redirectToRoute = 'profile_edit';
            $redirectToRouteFragment = 'identification';
        }
        else if(!is_null($textProperty->getNamespace())){
            $object = $textProperty->getNamespace();
            $redirectToRoute = 'namespace_edit';
            $redirectToRouteFragment = 'definition';
        }
        else throw $this->createNotFoundException('The related object for the text property  n° '.$textProperty->getId().' does not exist. Please contact an administrator.');

        if(!is_null($textProperty->getClassAssociation())){
            $this->denyAccessUnlessGranted('edit', $object->getChildClass()->getClassVersionForDisplay());
        }
        else if(!is_null($textProperty->getPropertyAssociation())){
            $this->denyAccessUnlessGranted('edit', $object->getChildProperty()->getPropertyVersionForDisplay());
        }
        else if(!is_null($textProperty->getEntityAssociation())){
            if($object->getSource() instanceof OntoClass){
                $this->denyAccessUnlessGranted('edit', $object->getSource()->getClassVersionForDisplay());
            }
            elseif($object->getSource() instanceof Property){
                $this->denyAccessUnlessGranted('edit', $object->getSource()->getPropertyVersionForDisplay());
            }
        }
        else{
            $this->denyAccessUnlessGranted('edit', $object);
        }

        $textProperty->setModifier($this->getUser());

        $form = $this->createForm(TextPropertyForm::class, $textProperty);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $textProperty->setModifier($this->getUser());
            $em->persist($textProperty);
            $em->flush();

            $this->addFlash('success', $textProperty->getSystemType().' updated!');

            return $this->redirectToRoute($redirectToRoute, [
                'id' => $object->getId(),
                '_fragment' => $redirectToRouteFragment
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
     * @Route("/text-property/{type}/new/{object}/{objectId}/inverse", name="text_property_inverse_new")
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
            $associatedObject = $associatedEntity->getChildClass()->getClassVersionForDisplay();
            $redirectToRoute = 'class_association_edit';
            $redirectToRouteFragment = 'justifications';
        }
        else if($object === 'property-association') {
            $associatedEntity = $em->getRepository('AppBundle:PropertyAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property association n° '.$objectId.' does not exist');
            }
            $textProperty->setPropertyAssociation($associatedEntity);
            $associatedObject = $associatedEntity->getChildProperty()->getPropertyVersionForDisplay();
            $redirectToRoute = 'property_association_edit';
            $redirectToRouteFragment = 'justifications';
        }
        else if($object === 'class') {
            $associatedEntity = $em->getRepository('AppBundle:OntoClass')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The class n° '.$objectId.' does not exist');
            }
            $textProperty->setClass($associatedEntity);
            $associatedObject = $associatedEntity->getClassVersionForDisplay();
            $redirectToRoute = 'class_edit';
            $redirectToRouteFragment = 'definition';
        }
        else if($object === 'property') {
            $associatedEntity = $em->getRepository('AppBundle:Property')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property n° '.$objectId.' does not exist');
            }
            $textProperty->setProperty($associatedEntity);
            $associatedObject = $associatedEntity->getPropertyVersionForDisplay();
            $redirectToRoute = 'property_edit';
            $redirectToRouteFragment = 'definition';
        }
        else if($object === 'project') {
            $associatedEntity = $em->getRepository('AppBundle:Project')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The project n° '.$objectId.' does not exist');
            }
            $textProperty->setProject($associatedEntity);
            $associatedObject = $associatedEntity;
            $redirectToRoute = 'project_edit';
            $redirectToRouteFragment = 'definition';
        }
        else if($object === 'profile') {
            $associatedEntity = $em->getRepository('AppBundle:Profile')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The profile n° '.$objectId.' does not exist');
            }
            $textProperty->setProfile($associatedEntity);
            $associatedObject = $associatedEntity;
            $redirectToRoute = 'profile_edit';
            $redirectToRouteFragment = 'definition';
        }
        else if($object === 'namespace') {
            $associatedEntity = $em->getRepository('AppBundle:OntoNamespace')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The namespace n° '.$objectId.' does not exist');
            }
            $textProperty->setNamespace($associatedEntity);
            $associatedObject = $associatedEntity;
            $redirectToRoute = 'namespace_edit';
            $redirectToRouteFragment = 'definition';
        }
        else if($object === 'entity-association') {
            $associatedEntity = $em->getRepository('AppBundle:EntityAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The entity association n° '.$objectId.' does not exist');
            }
            $textProperty->setEntityAssociation($associatedEntity);
            $associatedObject = $associatedEntity->getSource();

            $redirectToRoute = 'entity_association_edit';
            if($request->attributes->get('_route') == 'text_property_inverse_new'){
                $redirectToRoute = 'entity_association_inverse_edit';
            }

            $redirectToRouteFragment = 'justifications';
        }
        else throw $this->createNotFoundException('The requested object "'.$object.'" does not exist!');

        if($type === 'scope-note') {
            $systemType = $em->getRepository('AppBundle:SystemType')->find(1); //systemType 1 = scope note
        }
        else if($type === 'example') {
            $systemType = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 7 = example
        }
        else if($type === 'justification') {
            $systemType = $em->getRepository('AppBundle:SystemType')->find(15); //systemType 15 = justification
        }
        else if($type === 'additional-note') {
            $systemType = $em->getRepository('AppBundle:SystemType')->find(12); //systemType 12 = additional-note
        }
        else if($type === 'definition') {
            $systemType = $em->getRepository('AppBundle:SystemType')->find(16); //systemType 16 = description
        }
        else if($type === 'dct:contributor') {
            $systemType = $em->getRepository('AppBundle:SystemType')->find(2); //systemType 16 = description
        }
        else throw $this->createNotFoundException('The requested text property type "'.$type.'" does not exist!');

        $this->denyAccessUnlessGranted('edit', $associatedObject);

        $textProperty->setSystemType($systemType);

        //ongoingNamespace associated to the textProperty for any kind of object, except Project or Profile
        if($object !== 'project' && $object !== 'profile' && $object !== 'namespace') {
            $textProperty->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
        }


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

            //ongoingNamespace associated to the textProperty for any kind of object, except Project or Profile
            if($object !== 'project' && $object !== 'profile' && $object !== 'namespace') {
                $textProperty->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
            }

            $textProperty->setCreator($this->getUser());
            $textProperty->setModifier($this->getUser());
            $textProperty->setCreationTime(new \DateTime('now'));
            $textProperty->setModificationTime(new \DateTime('now'));

            $em = $this->getDoctrine()->getManager();
            $em->persist($textProperty);
            $em->flush();

            $this->addFlash('success',  $textProperty->getSystemType().' created!');

            return $this->redirectToRoute($redirectToRoute, [
                'id' => $objectId,
                '_fragment' => $redirectToRouteFragment
            ]);

        }

        return $this->render('textProperty/new.html.twig', [
            'textProperty' => $textProperty,
            'textPropertyForm' => $form->createView()
        ]);

    }

    /**
     * @Route("/text-property/{id}/edit-validity/{status}", name="text_property_validation_status_edit")
     * @Method({"POST"})
     * @param TextProperty $textProperty
     * @param SystemType $validationStatus
     * @param Request $request
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted namespaces list
     */
    public function editValidationStatusAction(TextProperty $textProperty, SystemType $validationStatus, Request $request)
    {
        if(!is_null($textProperty->getClassAssociation())){
            $object = $textProperty->getClassAssociation();
            $redirectToRoute = 'class_association_edit';
            $redirectToRouteFragment = 'justifications';
        }
        else if(!is_null($textProperty->getPropertyAssociation())){
            $object = $textProperty->getPropertyAssociation();
            $redirectToRoute = 'property_association_edit';
            $redirectToRouteFragment = 'justifications';
        }
        else if(!is_null($textProperty->getEntityAssociation())){
            $object = $textProperty->getEntityAssociation();

            $redirectToRoute = 'entity_association_edit';
            if($request->attributes->get('_route') == 'text_property_inverse_edit'){
                $redirectToRoute = 'entity_association_inverse_edit';
            }

            $redirectToRouteFragment = 'justifications';
        }
        else if(!is_null($textProperty->getClass())){
            $object = $textProperty->getClass();
            $redirectToRoute = 'class_edit';
            $redirectToRouteFragment = 'definition';
        }
        else if(!is_null($textProperty->getProperty())){
            $object = $textProperty->getProperty();
            $redirectToRoute = 'property_edit';
            $redirectToRouteFragment = 'definition';
        }
        else if(!is_null($textProperty->getProject())){
            $object = $textProperty->getProject();
            $redirectToRoute = 'project_edit';
            $redirectToRouteFragment = 'definition';
        }
        else if(!is_null($textProperty->getProfile())){
            $object = $textProperty->getProfile();
            $redirectToRoute = 'profile_edit';
            $redirectToRouteFragment = 'identification';
        }
        else if(!is_null($textProperty->getNamespace())){
            $object = $textProperty->getNamespace();
            $redirectToRoute = 'namespace_edit';
            $redirectToRouteFragment = 'definition';
        }
        else throw $this->createNotFoundException('The related object for the text property  n° '.$textProperty->getId().' does not exist. Please contact an administrator.');

        if(!is_null($textProperty->getClassAssociation())){
            $this->denyAccessUnlessGranted('edit', $object->getChildClass()->getClassVersionForDisplay());
        }
        else if(!is_null($textProperty->getPropertyAssociation())){
            $this->denyAccessUnlessGranted('edit', $object->getChildProperty()->getPropertyVersionForDisplay());
        }
        else if(!is_null($textProperty->getEntityAssociation())){
            if($object->getSource() instanceof OntoClass){
                $this->denyAccessUnlessGranted('edit', $object->getSource()->getClassVersionForDisplay());
            }
            elseif($object->getSource() instanceof Property){
                $this->denyAccessUnlessGranted('edit', $object->getSource()->getPropertyVersionForDisplay());
            }
        }
        else{
            $this->denyAccessUnlessGranted('edit', $object);
        }

        $textProperty->setModifier($this->getUser());

        $newValidationStatus = new SystemType();

        try{
            $em = $this->getDoctrine()->getManager();
            $newValidationStatus = $em->getRepository('AppBundle:SystemType')
                ->findOneBy(array('id' => $validationStatus->getId()));
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $status = 'Error';
            $response = array(
                'status' => $status,
                'message' => $message
            );
            return new JsonResponse($response,400, 'content-type:application/problem+json');
        }

        if (!is_null($newValidationStatus)) {
            $statusId = int_val($newValidationStatus->getId());
            if (in_array($statusId, [26,27,28], true)) {
                $textProperty->setValidationStatus($newValidationStatus);
                $textProperty->setModifier($this->getUser());
                $textProperty->setModificationTime(new \DateTime('now'));

                $em->persist($textProperty);

                $em->flush();
                $status = 'Success';
                $message = 'Property successfully associated';
            }
        }

        $response = array(
            'status' => $status,
            'message' => $message
        );

        return new JsonResponse($response);
    }
}