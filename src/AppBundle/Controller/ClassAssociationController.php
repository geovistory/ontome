<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 15/01/2018
 * Time: 15:19
 */

namespace AppBundle\Controller;

use AppBundle\Entity\ClassAssociation;
use AppBundle\Entity\OntoClass;
use AppBundle\Entity\SystemType;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\ClassAssociationEditForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Form\ParentClassAssociationForm;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ClassAssociationController extends Controller
{

    /**
     * @Route("/parent-class-association/new/{childClass}", name="new_parent_class_form")
     */
    public function newParentAction(Request $request, OntoClass $childClass)
    {
        $classAssociation = new ClassAssociation();

        $this->denyAccessUnlessGranted('edit', $childClass->getClassVersionForDisplay());


        $em = $this->getDoctrine()->getManager();
        $systemTypeJustification = $em->getRepository('AppBundle:SystemType')->find(15); //systemType 15 = justification
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 1 = example

        $justification = new TextProperty();
        $justification->setClassAssociation($classAssociation);
        $justification->setSystemType($systemTypeJustification);
        $justification->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
        $justification->setCreator($this->getUser());
        $justification->setModifier($this->getUser());
        $justification->setCreationTime(new \DateTime('now'));
        $justification->setModificationTime(new \DateTime('now'));

        $classAssociation->addTextProperty($justification);
        $classAssociation->setChildClass($childClass);

        // Filtrage : ID de la version de childClass
        $namespaceForChildClassVersion = $childClass->getClassVersionForDisplay()->getNamespaceForVersion();
        $namespacesId[] = $namespaceForChildClassVersion->getId();

        // Sans oublier les namespaces références
        foreach($namespaceForChildClassVersion->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            $namespacesId[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
        }

        // Sans oublier OntoME internal model - active version
        $namespacesId[] = 4;

        $arrayClassesVersion = $em->getRepository('AppBundle:OntoClassVersion')
            ->findIdAndStandardLabelOfClassesVersionByNamespacesId($namespacesId);

        $form = $this->createForm(ParentClassAssociationForm::class, $classAssociation, array(
            "classesVersion" => $arrayClassesVersion
        ));

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $classAssociation = $form->getData();
            $parentClass = $em->getRepository("AppBundle:OntoClass")->find($form->get("parentClassVersion")->getData());
            $classAssociation->setParentClass($parentClass);
            $classAssociation->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
            $classAssociation->setChildClassNamespace(
                $em->getRepository("AppBundle:OntoClassVersion")
                    ->findClassVersionByClassAndNamespacesId($childClass, $namespacesId)
                    ->getNamespaceForVersion()
            );
            $classAssociation->setParentClassNamespace(
                $em->getRepository("AppBundle:OntoClassVersion")
                    ->findClassVersionByClassAndNamespacesId($parentClass, $namespacesId)
                    ->getNamespaceForVersion()
            );
            $classAssociation->setCreator($this->getUser());
            $classAssociation->setModifier($this->getUser());
            $classAssociation->setCreationTime(new \DateTime('now'));
            $classAssociation->setModificationTime(new \DateTime('now'));

            if($classAssociation->getTextProperties()->containsKey(1)){
                $classAssociation->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $classAssociation->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $classAssociation->getTextProperties()[1]->setSystemType($systemTypeExample);
                $classAssociation->getTextProperties()[1]->setNamespaceForVersion($this->getUser()->getCurrentOngoingNamespace());
                $classAssociation->getTextProperties()[1]->setClassAssociation($classAssociation);
            }


            $em = $this->getDoctrine()->getManager();
            $em->persist($classAssociation);
            $em->flush();

            return $this->redirectToRoute('class_show', [
                'id' => $classAssociation->getChildClass()->getId(),
                '_fragment' => 'class-hierarchy'
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

        $ancestors = $em->getRepository('AppBundle:OntoClass')->findAncestorsByClassVersionAndNamespacesId($childClass->getClassVersionForDisplay(), $namespacesId);

        return $this->render('classAssociation/newParent.html.twig', [
            'childClass' => $childClass,
            'parentClassAssociationForm' => $form->createView(),
            'ancestors' => $ancestors
        ]);
    }

    /**
     * @Route("/class-association/{id}", name="class_association_show")
     * @param ClassAssociation $classAssociation
     * @return Response the rendered template
     */
    public function showAction(ClassAssociation $classAssociation)
    {
        $this->get('logger')
            ->info('Showing class association: '.$classAssociation->getObjectIdentification());


        return $this->render('classAssociation/show.html.twig', array(
            'class' => $classAssociation->getChildClass(),
            'classAssociation' => $classAssociation
        ));

    }

    /**
     * @Route("/class-association/{id}/edit", name="class_association_edit")
     */
    public function editAction(Request $request, ClassAssociation $classAssociation)
    {
        // Récupérer la version de la classe demandée
        $childClassVersion = $classAssociation->getChildClass()->getClassVersionForDisplay();

        // On doit avoir une version de la classe sinon on lance une exception.
        if(is_null($childClassVersion)){
            throw $this->createNotFoundException('The class n°'.$classAssociation->getChildClass()->getId().' has no version. Please contact an administrator.');
        }

        $this->denyAccessUnlessGranted('edit', $childClassVersion);

        $em = $this->getDoctrine()->getManager();

        // Filtrage : ID de la version de childClass
        $namespaceForChildClassVersion = $childClassVersion->getNamespaceForVersion();
        $namespacesId[] = $namespaceForChildClassVersion->getId();

        // Sans oublier les namespaces références
        foreach($namespaceForChildClassVersion->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            $namespacesId[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
        }

        // Sans oublier OntoME internal model - active version
        $namespacesId[] = 4;

        $arrayClassesVersion = $em->getRepository('AppBundle:OntoClassVersion')
            ->findIdAndStandardLabelOfClassesVersionByNamespacesId($namespacesId);

        $form = $this->createForm(ClassAssociationEditForm::class, $classAssociation, array(
            'classesVersion' => $arrayClassesVersion,
            'defaultParent' => $classAssociation->getParentClass()->getId()));

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $parentClass = $em->getRepository("AppBundle:OntoClass")->find($form->get("parentClassVersion")->getData());
            $classAssociation->setParentClass($parentClass);
            $classAssociation = $form->getData();
            $classAssociation->setModifier($this->getUser());
            $classAssociation->setModificationTime(new \DateTime('now'));


            $em = $this->getDoctrine()->getManager();
            $em->persist($classAssociation);
            $em->flush();

            return $this->redirectToRoute('class_edit', [
                'id' => $classAssociation->getChildClass()->getId(),
                '_fragment' => 'class-hierarchy'
            ]);

        }

        $em = $this->getDoctrine()->getManager();

        return $this->render('classAssociation/edit.html.twig', array(
            'class' => $classAssociation->getChildClass(),
            'classAssociation' => $classAssociation,
            'classAssociationForm' => $form->createView(),
        ));
    }

    /**
     * @Route("/class-association/{id}/edit-validity/{validationStatus}", name="class_association_validation_status_edit")
     * @param ClassAssociation $classAssociation
     * @param SystemType $validationStatus
     * @param Request $request
     * @throws \Exception in case of unsuccessful validation
     * @return RedirectResponse|Response
     */
    public function editValidationStatusAction(ClassAssociation $classAssociation, SystemType $validationStatus, Request $request)
    {
        // On doit avoir une version de l'association sinon on lance une exception.
        if(is_null($classAssociation)){
            throw $this->createNotFoundException('The class association n°'.$classAssociation->getId().' does not exist. Please contact an administrator.');
        }

        //Denied access if not an authorized validator
        $this->denyAccessUnlessGranted('validate', $classAssociation->getChildClass());


        $classAssociation->setModifier($this->getUser());

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
                $classAssociation->setValidationStatus($newValidationStatus);
                $classAssociation->setModifier($this->getUser());
                $classAssociation->setModificationTime(new \DateTime('now'));

                $em->persist($classAssociation);

                $em->flush();

                if ($statusId == 27){
                    return $this->redirectToRoute('class_association_edit', [
                        'id' => $classAssociation->getId()
                    ]);
                }
                else return $this->redirectToRoute('class_association_show', [
                    'id' => $classAssociation->getId()
                ]);

            }
        }

        return $this->redirectToRoute('class_association_show', [
            'id' => $classAssociation->getId()
        ]);
    }

}