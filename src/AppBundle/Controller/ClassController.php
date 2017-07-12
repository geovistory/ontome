<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/06/2017
 * Time: 17:11
 */

namespace AppBundle\Controller;


use AppBundle\Entity\OntoClass;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ClassController extends Controller
{
    /**
     * @Route("/class")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $classes = $em->getRepository('AppBundle:OntoClass')
            ->findAllOrderedById();

        return $this->render('class/list.html.twig', [
            'classes' => $classes
        ]);
    }

    /**
     * @Route("/class/{id}", name="class_show")
     * @param string $id
     * @return Response the rendered template
     */
    public function showAction(OntoClass $class)
    {
        $em = $this->getDoctrine()->getManager();

        $ancestors = $em->getRepository('AppBundle:OntoClass')
            ->findAncestorsById($class);

        $descendants = $em->getRepository('AppBundle:OntoClass')
            ->findDescendantsById($class);

        $equivalences = $em->getRepository('AppBundle:OntoClass')
            ->findEquivalencesById($class);

        $outgoingProperties = $em->getRepository('AppBundle:Property')
            ->findOutgoingPropertiesById($class);

        $this->get('logger')
            ->info('Showing genus: '.$class->getIdentifierInNamespace());


        return $this->render('class/show.html.twig', array(
            'class' => $class,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'equivalences' => $equivalences,
            'outgoingProperties' => $outgoingProperties
        ));
    }

}