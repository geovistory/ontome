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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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
     * @param string $class
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

        $outgoingInheritedProperties = $em->getRepository('AppBundle:Property')
            ->findOutgoingInheritedPropertiesById($class);

        $ingoingProperties = $em->getRepository('AppBundle:Property')
            ->findIngoingPropertiesById($class);

        $ingoingInheritedProperties = $em->getRepository('AppBundle:Property')
            ->findIngoingInheritedPropertiesById($class);

        $this->get('logger')
            ->info('Showing class: '.$class->getIdentifierInNamespace());


        return $this->render('class/show.html.twig', array(
            'class' => $class,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'equivalences' => $equivalences,
            'outgoingProperties' => $outgoingProperties,
            'outgoingInheritedProperties' => $outgoingInheritedProperties,
            'ingoingProperties' => $ingoingProperties,
            'ingoingInheritedProperties' => $ingoingInheritedProperties
        ));
    }

    /**
     * @Route("/classes-tree")
     */
    public function getTreeAction()
    {
        return $this->render('class/tree.html.twig');
    }

    /**
     * @Route("/classes-tree/json", name="classes_tree_json")
     * @Method("GET")
     * @return JsonResponse a Json formatted tree representation of OntoClasses
     */
    public function getTreeJson()
    {
        $em = $this->getDoctrine()->getManager();
        $classes = $em->getRepository('AppBundle:OntoClass')
            ->findClassesTree();

        return new JsonResponse($classes[0]['json'],200, array(), true);
    }

    /**
     * @Route("/classes-tree-legend/json", name="classes_tree_legend_json")
     * @Method("GET")
     * @return JsonResponse a Json formatted legend for the OntoClasses tree
     */
    public function getTreeLegendJson()
    {
        $em = $this->getDoctrine()->getManager();
        $legend = $em->getRepository('AppBundle:OntoClass')
            ->findClassesTreeLegend();


        return new JsonResponse($legend[0]['json']);
    }


}