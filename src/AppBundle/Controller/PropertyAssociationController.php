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
use AppBundle\Entity\SystemType;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\PropertyAssociationEditForm;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Form\ParentPropertyAssociationForm;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PropertyAssociationController extends Controller
{

    /**
     * @Route("/parent-property-association/new/{childProperty}", name="new_parent_property_form", requirements={"childProperty"="^[0-9]+$"})
     */
    public function newParentAction(Request $request, Property $childProperty)
    {
        $propertyAssociation = new PropertyAssociation();

        $this->denyAccessUnlessGranted('add_associations', $childProperty->getPropertyVersionForDisplay()->getNamespaceForVersion());


        $em = $this->getDoctrine()->getManager();
        $systemTypeJustification = $em->getRepository('AppBundle:SystemType')->find(15); //systemType 15 = justification
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 7 = example

        $justification = new TextProperty();
        $justification->setPropertyAssociation($propertyAssociation);
        $justification->setSystemType($systemTypeJustification);
        $justification->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
        $justification->setCreator($this->getUser());
        $justification->setModifier($this->getUser());
        $justification->setCreationTime(new \DateTime('now'));
        $justification->setModificationTime(new \DateTime('now'));

        $propertyAssociation->addTextProperty($justification);
        $propertyAssociation->setChildProperty($childProperty);

        // FILTRAGE
        //$namespaceForChildPropertyVersion = $childProperty->getPropertyVersionForDisplay()->getNamespaceForVersion();
        //$namespacesId[] = $namespaceForChildPropertyVersion->getId();
        $namespacesId[] = $this->getUser()->getCurrentOngoingNamespace()->getId();

        // Sans oublier les namespaces références si indisponibles
        //foreach($this->getUser()->getCurrentOngoingNamespace()->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
        foreach($this->getUser()->getCurrentOngoingNamespace()->getAllReferencedNamespaces() as $referencedNamespaces){
            if(!in_array($referencedNamespaces->getId(), $namespacesId)){
                $namespacesId[] = $referencedNamespaces->getId();
            }
        }

        $arrayPropertiesVersion = $em->getRepository('AppBundle:PropertyVersion')
            ->findIdAndStandardLabelOfPropertiesVersionByNamespacesId($namespacesId);

        foreach ($arrayPropertiesVersion as $pv){
            if($pv['id'] == $childProperty->getId()){
                unset($arrayPropertiesVersion[array_search($pv, $arrayPropertiesVersion)]);
            }
        }

        $form = $this->createForm(ParentPropertyAssociationForm::class, $propertyAssociation, array(
            "propertiesVersion" => $arrayPropertiesVersion
        ));

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $propertyAssociation = $form->getData();
            $parentProperty = $em->getRepository("AppBundle:Property")->find($form->get("parentPropertyVersion")->getData());
            $propertyAssociation->setParentProperty($parentProperty);
            $propertyAssociation->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
            $propertyAssociation->setChildPropertyNamespace(
                $em->getRepository("AppBundle:PropertyVersion")
                    ->findPropertyVersionByPropertyAndNamespacesId($childProperty, $namespacesId)
                    ->getNamespaceForVersion()
            );
            $propertyAssociation->setParentPropertyNamespace(
                $em->getRepository("AppBundle:PropertyVersion")
                    ->findPropertyVersionByPropertyAndNamespacesId($parentProperty, $namespacesId)
                    ->getNamespaceForVersion()
            );
            $propertyAssociation->setCreator($this->getUser());
            $propertyAssociation->setModifier($this->getUser());
            $propertyAssociation->setCreationTime(new \DateTime('now'));
            $propertyAssociation->setModificationTime(new \DateTime('now'));

            if($propertyAssociation->getTextProperties()->containsKey(1)){
                $propertyAssociation->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $propertyAssociation->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $propertyAssociation->getTextProperties()[1]->setSystemType($systemTypeExample);
                $propertyAssociation->getTextProperties()[1]->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
                $propertyAssociation->getTextProperties()[1]->setPropertyAssociation($propertyAssociation);
            }


            $em = $this->getDoctrine()->getManager();
            $em->persist($propertyAssociation);
            $em->flush();

            return $this->redirectToRoute('property_show', [
                'id' => $propertyAssociation->getChildProperty()->getId(),
                '_fragment' => 'property-hierarchy'
            ]);

        }

        $em = $this->getDoctrine()->getManager();

        // FILTRAGE : Récupérer les clés de namespaces à utiliser
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }

        $ancestors = $em->getRepository('AppBundle:Property')
            ->findAncestorsByPropertyVersionAndNamespacesId($childProperty->getPropertyVersionForDisplay(), $namespacesId);

        return $this->render('propertyAssociation/newParent.html.twig', [
            'childProperty' => $childProperty,
            'parentPropertyAssociationForm' => $form->createView(),
            'ancestors' => $ancestors
        ]);
    }

    /**
     * @Route("/property-association/{id}", name="property_association_show", requirements={"id"="^[0-9]+$"})
     * @param PropertyAssociation $propertyAssociation
     * @return Response the rendered template
     */
    public function showAction(PropertyAssociation $propertyAssociation, LoggerInterface $logger)
    {
        $logger->info('Showing property association: '.$propertyAssociation->getObjectIdentification());


        return $this->render('propertyAssociation/show.html.twig', array(
            'property' => $propertyAssociation->getChildProperty(),
            'propertyAssociation' => $propertyAssociation
        ));

    }

    /**
     * @Route("/property-association/{id}/edit", name="property_association_edit", requirements={"id"="^[0-9]+$"})
     */
    public function editAction(Request $request, PropertyAssociation $propertyAssociation)
    {
        // Récupérer la version de la propriété demandée
        $childPropertyVersion = $propertyAssociation->getChildProperty()->getPropertyVersionForDisplay();

        // On doit avoir une version de la propriété sinon on lance une exception.
        if(is_null($childPropertyVersion)){
            throw $this->createNotFoundException('The property n°'.$propertyAssociation->getChildProperty()->getId().' has no version. Please contact an administrator.');
        }

        $this->denyAccessUnlessGranted('edit', $propertyAssociation);

        $em = $this->getDoctrine()->getManager();
        /*
        // FILTRAGE : Récupérer les clés de namespaces à utiliser
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }

        $namespacesIdFromChildProperty = array();
        // Affaiblir le filtrage en rajoutant le namespaceForVersion de la classVersion si indisponible
        $namespaceForChildPropertyVersion = $childPropertyVersion->getNamespaceForVersion();
        if(!in_array($namespaceForChildPropertyVersion->getId(), $namespacesIdFromChildProperty)){
            $namespacesIdFromChildProperty[] = $namespaceForChildPropertyVersion->getId();
        }
        // Sans oublier les namespaces références si indisponibles
        foreach($namespaceForChildPropertyVersion->getAllReferencedNamespaces() as $referencedNamespace){
            if(!in_array($referencedNamespace->getId(), $namespacesIdFromChildProperty)){
                $namespacesIdFromChildProperty[] = $referencedNamespace->getId();
            }
        }

        $namespacesId = array_merge($namespacesIdFromUser, $namespacesIdFromChildProperty);*/

        $namespacesId = $this->getUser()->getCurrentOngoingNamespace()->getSelectedNamespacesId();

        $arrayPropertiesVersion = $em->getRepository('AppBundle:PropertyVersion')->findIdAndStandardLabelOfPropertiesVersionByNamespacesId($namespacesId);

        foreach ($arrayPropertiesVersion as $pv){
            if($pv['id'] == $propertyAssociation->getChildProperty()->getId()){
                unset($arrayPropertiesVersion[array_search($pv, $arrayPropertiesVersion)]);
            }
        }

        $form = $this->createForm(PropertyAssociationEditForm::class, $propertyAssociation, array(
            'propertiesVersion' => $arrayPropertiesVersion,
            'defaultParent' => $propertyAssociation->getParentProperty()->getId()));

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $parentProperty = $em->getRepository("AppBundle:Property")->find($form->get("parentPropertyVersion")->getData());
            $parentPropertyVersion = $em->getRepository("AppBundle:PropertyVersion")->findPropertyVersionByPropertyAndNamespacesId($parentProperty, $namespacesId);
            $propertyAssociation->setParentProperty($parentProperty);
            $parentPropertyNamespace = $em->getRepository("AppBundle:PropertyVersion")->findPropertyVersionByPropertyAndNamespacesId($parentProperty, $namespacesId)->getNamespaceForVersion();
            $propertyAssociation->setParentPropertyNamespace($parentPropertyNamespace);
            $propertyAssociation = $form->getData();
            $propertyAssociation->setModifier($this->getUser());
            $propertyAssociation->setModificationTime(new \DateTime('now'));

            $em = $this->getDoctrine()->getManager();
            $em->persist($propertyAssociation);
            $em->flush();

            return $this->redirectToRoute('property_show_with_version', [
                'id' => $propertyAssociation->getChildProperty()->getId(),
                'namespaceFromUrlId' => $propertyAssociation->getChildProperty()->getPropertyVersionForDisplay()->getNamespaceForVersion()->getId(),
                '_fragment' => 'property-hierarchy'
            ]);

        }

        $em = $this->getDoctrine()->getManager();

        return $this->render('propertyAssociation/edit.html.twig', array(
            'property' => $propertyAssociation->getChildProperty(),
            'propertyAssociation' => $propertyAssociation,
            'propertyAssociationForm' => $form->createView(),
        ));
    }

    /**
     * @Route("/property-association/{id}/delete", name="property_association_delete", requirements={"id"="^([0-9]+)|(associationId){1}$"})
     * @param PropertyAssociation $propertyAssociation
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteAction(Request $request, PropertyAssociation $propertyAssociation)
    {
        $this->denyAccessUnlessGranted('delete', $propertyAssociation);

        $em = $this->getDoctrine()->getManager();
        foreach($propertyAssociation->getTextProperties() as $textProperty)
        {
            $em->remove($textProperty);
        }
        foreach($propertyAssociation->getComments() as $comment)
        {
            $em->remove($comment);
        }
        $em->remove($propertyAssociation);
        $em->flush();
        return new JsonResponse(null, 204);
    }

    /**
     * @Route("/property-association/{id}/edit-validity/{validationStatus}", name="property_association_validation_status_edit", requirements={"id"="^[0-9]+$", "validationStatus"="^(26|27|28|37){1}$"})
     * @param PropertyAssociation $propertyAssociation
     * @param SystemType $validationStatus
     * @param Request $request
     * @throws \Exception in case of unsuccessful validation
     * @return RedirectResponse|Response
     */
    public function editValidationStatusAction(PropertyAssociation $propertyAssociation, SystemType $validationStatus, Request $request)
    {
        // On doit avoir une version de l'association sinon on lance une exception.
        if(is_null($propertyAssociation)){
            throw $this->createNotFoundException('The property association n°'.$propertyAssociation->getId().' does not exist. Please contact an administrator.');
        }

        //Denied access if not an authorized validator
        $this->denyAccessUnlessGranted('validate', $propertyAssociation->getChildProperty()->getPropertyVersionForDisplay());

        //Verifier que les références sont cohérents
        $nsRefsPropertyAssociation = $propertyAssociation->getNamespaceForVersion()->getAllReferencedNamespaces();
        $nsParent = $propertyAssociation->getParentPropertyNamespace();
        $nsChild = $propertyAssociation->getChildPropertyNamespace();
        if(!$nsRefsPropertyAssociation->contains($nsParent) || !$nsRefsPropertyAssociation->contains($nsChild)){
            $uriNamespaceMismatches = $this->generateUrl('namespace_show', ['id' => $propertyAssociation->getNamespaceForVersion()->getId(), '_fragment' => 'mismatches']);
            $this->addFlash('warning', 'This relation can\'t be validated. Check <a href="'.$uriNamespaceMismatches.'">mismatches</a>.');
            return $this->redirectToRoute('class_association_show', [
                'id' => $propertyAssociation->getId()
            ]);
        }

        $propertyAssociation->setModifier($this->getUser());

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
            if (in_array($statusId, [26,27,28, 37], true)) {
                $propertyAssociation->setValidationStatus($newValidationStatus);
                $propertyAssociation->setModifier($this->getUser());
                $propertyAssociation->setModificationTime(new \DateTime('now'));

                $em->persist($propertyAssociation);

                $em->flush();

                if ($statusId == 27){
                    return $this->redirectToRoute('property_association_edit', [
                        'id' => $propertyAssociation->getId()
                    ]);
                }
                else return $this->redirectToRoute('property_association_show', [
                    'id' => $propertyAssociation->getId()
                ]);

            }
        }

        return $this->redirectToRoute('property_association_show', [
            'id' => $propertyAssociation->getId()
        ]);
    }
}