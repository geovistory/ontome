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
use AppBundle\Entity\TextProperty;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Form\ParentClassAssociationForm;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class ClassAssociationController extends Controller
{

    /**
     * @Route("/parent-class-association/new/{childClass}", name="new_parent_class_form")
     */
    public function newParentAction(Request $request, OntoClass $childClass)
    {
        $classAssociation = new ClassAssociation();

        $this->denyAccessUnlessGranted('edit', $childClass);


        $em = $this->getDoctrine()->getManager();
        $systemTypeScopeNote = $em->getRepository('AppBundle:SystemType')->find(1); //systemType 1 = scope note
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 1 = scope note

        $scopeNote = new TextProperty();
        $scopeNote->setClassAssociation($classAssociation);
        $scopeNote->setSystemType($systemTypeScopeNote);
        $scopeNote->setNamespace($childClass->getOngoingNamespace());
        $scopeNote->setCreator($this->getUser());
        $scopeNote->setModifier($this->getUser());
        $scopeNote->setCreationTime(new \DateTime('now'));
        $scopeNote->setModificationTime(new \DateTime('now'));

        $classAssociation->addTextProperty($scopeNote);
        $classAssociation->setChildClass($childClass);

        $form = $this->createForm(ParentClassAssociationForm::class, $classAssociation);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $classAssociation = $form->getData();
            $classAssociation->addNamespace($childClass->getOngoingNamespace());
            $classAssociation->setCreator($this->getUser());
            $classAssociation->setModifier($this->getUser());
            $classAssociation->setCreationTime(new \DateTime('now'));
            $classAssociation->setModificationTime(new \DateTime('now'));

            if($classAssociation->getTextProperties()->containsKey(1)){
                $classAssociation->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $classAssociation->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $classAssociation->getTextProperties()[1]->setSystemType($systemTypeExample);
                $classAssociation->getTextProperties()[1]->setNamespace($childClass->getOngoingNamespace());
                $classAssociation->getTextProperties()[1]->setClassAssociation($classAssociation);
            }


            $em = $this->getDoctrine()->getManager();
            $em->persist($classAssociation);
            $em->flush();

            return $this->redirectToRoute('class_show', [
                'id' => $classAssociation->getChildClass()->getId()
            ]);

        }

        $em = $this->getDoctrine()->getManager();

        $ancestors = $em->getRepository('AppBundle:OntoClass')
            ->findAncestorsById($childClass);
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
            'classAssociation' => $classAssociation
        ));

    }

}