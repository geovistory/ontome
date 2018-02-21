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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Form\childClassAssociationForm;
use AppBundle\Form\parentClassAssociationForm;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ClassAssociationController extends Controller
{

    /**
     * @Route("/parent-class-association/new/{childClass}", name="new-parent-class-form")
     * @param OntoClass $childClass
     */
    public function newParentAction(Request $request, OntoClass $childClass)
    {
        $classAssociation = new ClassAssociation();
        $classAssociation->setChildClass($childClass);
        $form = $this->createForm(parentClassAssociationForm::class, $classAssociation);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $classAssociation = $form->getData();
            $classAssociation->setCreator($this->getUser());
            $classAssociation->setModifier($this->getUser());
            $classAssociation->setCreationTime(new \DateTime('now'));
            $classAssociation->setModificationTime(new \DateTime('now'));

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

}