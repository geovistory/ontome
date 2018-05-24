<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 15/10/2017
 * Time: 15:03
 */

namespace AppBundle\Controller;

use AppBundle\Entity\TextProperty;
use AppBundle\Form\TextPropertyForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
     */
    public function editAction(TextProperty $textProperty, Request $request)
    {
        $childClass = $textProperty->getClassAssociation()->getChildClass();
        $this->denyAccessUnlessGranted('edit', $childClass);

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

        if(!is_null($textProperty->getClassAssociation())){

        }

        return $this->render('textProperty/edit.html.twig', [
            'textPropertyForm' => $form->createView(),
            'class' => $childClass,
            'textProperty' => $textProperty
        ]);

    }
}