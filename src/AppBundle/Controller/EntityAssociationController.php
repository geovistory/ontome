<?php
/**
 * Created by PhpStorm.
 * User: pc-alexandre-pro
 * Date: 07/05/2019
 * Time: 14:39
 */

namespace AppBundle\Controller;


use AppBundle\Entity\EntityAssociation;
use AppBundle\Entity\OntoClass;
use AppBundle\Entity\Property;
use AppBundle\Entity\SystemType;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\EntityAssociationForm;
use AppBundle\Form\EntityAssociationEditForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
            $namespaceForEntityVersion = $source->getClassVersionForDisplay()->getNamespaceForVersion();
        }
        elseif($object == 'property')
        {
            $source = $em->getRepository('AppBundle:Property')->find($objectId);
            if (!$source) {
                throw $this->createNotFoundException('The property n° '.$objectId.' does not exist');
            }
            $entityAssociation->setSourceProperty($source);
            $namespaceForEntityVersion = $source->getPropertyVersionForDisplay()->getNamespaceForVersion();
        }

        if($source instanceof OntoClass){
            $this->denyAccessUnlessGranted('edit', $source->getClassVersionForDisplay());
        }
        elseif($source instanceof Property){
            $this->denyAccessUnlessGranted('edit', $source->getPropertyVersionForDisplay());
        }

        $systemTypeJustification = $em->getRepository('AppBundle:SystemType')->find(15); //systemType 15 = justification
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 1 = example

        $justification = new TextProperty();
        $justification->setEntityAssociation($entityAssociation);
        $justification->setSystemType($systemTypeJustification);
        $justification->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
        $justification->setCreator($this->getUser());
        $justification->setModifier($this->getUser());
        $justification->setCreationTime(new \DateTime('now'));
        $justification->setModificationTime(new \DateTime('now'));

        $entityAssociation->addTextProperty($justification);

        // Filtrage
        $namespacesId[] = $namespaceForEntityVersion->getId();

        // Sans oublier les namespaces références si indisponibles
        foreach($namespaceForEntityVersion->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            if(!in_array($referencedNamespacesAssociation->getReferencedNamespace()->getId(), $namespacesId)){
                $namespacesId[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
            }
        }

        $entityAssociation->setSourceNamespaceForVersion($namespaceForEntityVersion);

        if($entityAssociation->getSourceObjectType() == "class"){
            $arrayEntitiesVersion = $em->getRepository('AppBundle:OntoClassVersion')
                ->findIdAndStandardLabelOfClassesVersionByNamespacesId($namespacesId);
        }
        elseif($entityAssociation->getSourceObjectType() == "property"){
            $arrayEntitiesVersion = $em->getRepository('AppBundle:PropertyVersion')
                ->findIdAndStandardLabelOfPropertiesVersionByNamespacesId($namespacesId);
        }

        $form = $this->createForm(EntityAssociationForm::class, $entityAssociation, array(
            'object' => $object,
            'entitiesVersion' => $arrayEntitiesVersion));

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if($entityAssociation->getSourceObjectType() == 'class'){
                $targetClass = $em->getRepository("AppBundle:OntoClass")->find($form->get("targetClassVersion")->getData());
                $entityAssociation->setTargetClass($targetClass);
                $targetNamespace = $em->getRepository("AppBundle:OntoClassVersion")
                    ->findClassVersionByClassAndNamespacesId($targetClass, $namespacesId)
                    ->getNamespaceForVersion();
                $entityAssociation->setTargetNamespaceForVersion($targetNamespace);
            }
            elseif($entityAssociation->getSourceObjectType() == 'property'){
                $targetProperty = $em->getRepository("AppBundle:Property")->find($form->get("targetPropertyVersion")->getData());
                $entityAssociation->setTargetProperty($targetProperty);
                $targetNamespace = $em->getRepository("AppBundle:PropertyVersion")
                    ->findPropertyVersionByPropertyAndNamespacesId($targetProperty, $namespacesId)
                    ->getNamespaceForVersion();
                $entityAssociation->setTargetNamespaceForVersion($targetNamespace);
            }

            $entityAssociation = $form->getData();
            $entityAssociation->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
            $entityAssociation->setCreator($this->getUser());
            $entityAssociation->setModifier($this->getUser());
            $entityAssociation->setCreationTime(new \DateTime('now'));
            $entityAssociation->setModificationTime(new \DateTime('now'));
            $entityAssociation->setDirected(FALSE);

            if ($entityAssociation->getTextProperties()->containsKey(1)) {
                $entityAssociation->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $entityAssociation->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $entityAssociation->getTextProperties()[1]->setSystemType($systemTypeExample);
                $entityAssociation->getTextProperties()[1]->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
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
            $namespaceForEntityVersion = $firstEntity->getClassVersionForDisplay()->getNamespaceForVersion();
        }
        elseif($entityAssociation->getSourceObjectType() == 'property' and !$inverse)
        {
            $firstEntity = $em->getRepository('AppBundle:Property')->find($entityAssociation->getSourceProperty()->getId());
            if (!$firstEntity) {
                throw $this->createNotFoundException('The property n° '.$entityAssociation->getSourceProperty()->getId().' does not exist');
            }
            $namespaceForEntityVersion = $firstEntity->getPropertyVersionForDisplay()->getNamespaceForVersion();
        }
        elseif($entityAssociation->getTargetObjectType() == 'class' and $inverse)
        {
            $firstEntity = $em->getRepository('AppBundle:OntoClass')->find($entityAssociation->getTargetClass()->getId());
            if (!$firstEntity) {
                throw $this->createNotFoundException('The class n° '.$entityAssociation->getTargetClass()->getId().' does not exist');
            }
            $namespaceForEntityVersion = $firstEntity->getClassVersionForDisplay()->getNamespaceForVersion();
        }
        elseif($entityAssociation->getTargetObjectType() == 'property' and $inverse)
        {
            $firstEntity = $em->getRepository('AppBundle:Property')->find($entityAssociation->getTargetProperty()->getId());
            if (!$firstEntity) {
                throw $this->createNotFoundException('The property n° '.$entityAssociation->getTargetProperty()->getId().' does not exist');
            }
            $namespaceForEntityVersion = $firstEntity->getPropertyVersionForDisplay()->getNamespaceForVersion();
        }

        $this->denyAccessUnlessGranted('edit', $firstEntity);

        // FILTRAGE
        $namespacesId[] = $namespaceForEntityVersion->getId();

        // Sans oublier les namespaces références si indisponibles
        foreach($namespaceForEntityVersion->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            if(!in_array($referencedNamespacesAssociation->getReferencedNamespace()->getId(), $namespacesId)){
                $namespacesId[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
            }
        }

        if($entityAssociation->getSourceObjectType() == "class"){
            $arrayEntitiesVersion = $em->getRepository('AppBundle:OntoClassVersion')
                ->findIdAndStandardLabelOfClassesVersionByNamespacesId($namespacesId);
        }
        elseif($entityAssociation->getSourceObjectType() == "property"){
            $arrayEntitiesVersion = $em->getRepository('AppBundle:PropertyVersion')
                ->findIdAndStandardLabelOfPropertiesVersionByNamespacesId($namespacesId);
        }

        $form = $this->createForm(EntityAssociationEditForm::class, $entityAssociation, array(
            'object' => $entityAssociation->getSourceObjectType(),
            'inverse' => $inverse,
            'entitiesVersion' => $arrayEntitiesVersion,
            'defaultSource' => $entityAssociation->getSource()->getId(),
            'defaultTarget' => $entityAssociation->getTarget()->getId()
        ));

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if($entityAssociation->getTargetObjectType() == 'class' and $inverse){
                $sourceClass = $em->getRepository("AppBundle:OntoClass")->find($form->get("sourceClassVersion")->getData());
                $entityAssociation->setSourceClass($sourceClass);
                $sourceNamespace = $em->getRepository("AppBundle:OntoClassVersion")
                    ->findClassVersionByClassAndNamespacesId($sourceClass, $namespacesId)
                    ->getNamespaceForVersion();
                $entityAssociation->setSourceNamespaceForVersion($sourceNamespace);
            }
            elseif($entityAssociation->getTargetObjectType() == 'class' and !$inverse){
                $targetClass = $em->getRepository("AppBundle:OntoClass")->find($form->get("targetClassVersion")->getData());
                $entityAssociation->setTargetClass($targetClass);
                $targetNamespace = $em->getRepository("AppBundle:OntoClassVersion")
                    ->findClassVersionByClassAndNamespacesId($targetClass, $namespacesId)
                    ->getNamespaceForVersion();
                $entityAssociation->setTargetNamespaceForVersion($targetNamespace);
            }
            elseif($entityAssociation->getTargetObjectType() == 'property' and $inverse){
                $sourceProperty = $em->getRepository("AppBundle:Property")->find($form->get("sourcePropertyVersion")->getData());
                $entityAssociation->setSourceProperty($sourceProperty);
                $sourceNamespace = $em->getRepository("AppBundle:PropertyVersion")
                    ->findPropertyVersionByPropertyAndNamespacesId($sourceProperty, $namespacesId)
                    ->getNamespaceForVersion();
                $entityAssociation->setSourceNamespaceForVersion($sourceNamespace);
            }
            elseif($entityAssociation->getTargetObjectType() == 'property' and !$inverse){
                $targetProperty = $em->getRepository("AppBundle:Property")->find($form->get("targetPropertyVersion")->getData());
                $entityAssociation->setTargetProperty($targetProperty);
                $targetNamespace = $em->getRepository("AppBundle:PropertyVersion")
                    ->findPropertyVersionByPropertyAndNamespacesId($targetProperty, $namespacesId)
                    ->getNamespaceForVersion();
                $entityAssociation->setTargetNamespaceForVersion($targetNamespace);
            }

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

    /**
     * @Route("/entity-association/{id}/edit-validity/{validationStatus}", name="entity_association_validation_status_edit")
     * @param EntityAssociation $entityAssociation
     * @param SystemType $validationStatus
     * @param Request $request
     * @throws \Exception in case of unsuccessful validation
     * @return RedirectResponse|Response
     */
    public function editValidationStatusAction(EntityAssociation $entityAssociation, SystemType $validationStatus, Request $request)
    {
        // On doit avoir une version de l'association sinon on lance une exception.
        if(is_null($entityAssociation)){
            throw $this->createNotFoundException('The entity association n°'.$entityAssociation->getId().' does not exist. Please contact an administrator.');
        }

        //Denied access if not an authorized validator
        if ($entityAssociation->getSourceObjectType() == 'class') {
            $this->denyAccessUnlessGranted('validate', $entityAssociation->getSourceClass()->getClassVersionForDisplay());
        }
        else if ($entityAssociation->getSourceObjectType() == 'property') {
            $this->denyAccessUnlessGranted('validate', $entityAssociation->getSourceProperty()->getPropertyVersionForDisplay());
        }
        else throw new AccessDeniedHttpException();

        $entityAssociation->setModifier($this->getUser());

        $newValidationStatus = new SystemType();

        try{
            $em = $this->getDoctrine()->getManager();
            $newValidationStatus = $em->getRepository('AppBundle:SystemType')
                ->findOneBy(array('id' => $validationStatus->getId()));
        } catch (\Exception $e) {
            throw new BadRequestHttpException('The provided status does not exist.');
        }

        if (!is_null($newValidationStatus)) {
            $statusId = intval($newValidationStatus->getId());
            if (in_array($statusId, [26,27,28], true)) {
                $entityAssociation->setValidationStatus($newValidationStatus);
                $entityAssociation->setModifier($this->getUser());
                $entityAssociation->setModificationTime(new \DateTime('now'));

                $em->persist($entityAssociation);

                $em->flush();

                if ($statusId == 27){
                    return $this->redirectToRoute('entity_association_edit', [
                        'id' => $entityAssociation->getId()
                    ]);
                }
                else return $this->redirectToRoute('entity_association_show', [
                    'id' => $entityAssociation->getId()
                ]);

            }
        }

        return $this->redirectToRoute('entity_association_show', [
            'id' => $entityAssociation->getId()
        ]);
    }

    /**
     * @Route("/entity-association/{id}/delete", name="entity_association_delete")
     */
    public function deleteAction(Request $request, EntityAssociation $entityAssociation)
    {
        $this->denyAccessUnlessGranted('delete_associations', $entityAssociation->getNamespaceForVersion());

        $em = $this->getDoctrine()->getManager();
        foreach($entityAssociation->getTextProperties() as $textProperty)
        {
            $em->remove($textProperty);
        }
        $em->remove($entityAssociation);
        $em->flush();
        return new JsonResponse(null, 204);
    }
}