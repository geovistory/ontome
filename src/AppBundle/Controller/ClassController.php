<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/06/2017
 * Time: 17:11
 */

namespace AppBundle\Controller;


use AppBundle\Entity\OntoClass;
use AppBundle\Entity\Project;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @param OntoClass $class
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
     * @Route("/class/{id}/edit", name="class_edit")
     * @param OntoClass $class
     * @return Response the rendered template
     */
    public function editAction(OntoClass $class)
    {
        $this->denyAccessUnlessGranted('edit', $class);

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


        return $this->render('class/edit.html.twig', array(
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

    /**
     * @Route("/class/{class}/graph/json", name="class_graph_json")
     * @Method("GET")
     * @param OntoClass $class
     * @return JsonResponse a Json formatted tree representation of OntoClasses
     */
    public function getGraphJson(OntoClass $class)
    {
        $em = $this->getDoctrine()->getManager();
        $classes = $em->getRepository('AppBundle:OntoClass')
            ->findClassesGraphById($class);

        return new JsonResponse($classes[0]['json'],200, array(), true);
    }

    /**
     * @Route("/api/classes/project/{project}/json", name="classes_project_json")
     * @Method("GET")
     * @param Project $project
     * @return JsonResponse a Json formatted list representation of OntoClasses related to a Project
     */
    public function getClassesByProject(Project $project)
    {
        try{
            $em = $this->getDoctrine()->getManager();
            $classes = $em->getRepository('AppBundle:OntoClass')
                ->findClassesByProjectId($project);

        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        //return new JsonResponse(null,404, array('content-type'=>'application/problem+json'));
        return new JsonResponse($classes[0]['json'],200, array(), true);
    }

}