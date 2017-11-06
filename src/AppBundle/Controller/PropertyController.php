<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/06/2017
 * Time: 17:11
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Property;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PropertyController extends Controller
{
    /**
     * @Route("/property")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $properties = $em->getRepository('AppBundle:Property')
            ->findAllOrderedById();

        return $this->render('property/list.html.twig', [
            'properties' => $properties
        ]);
    }

    /**
     * @Route("/property/{id}", name="property_show")
     * @param string $id
     * @return Response the rendered template
     */
    public function showAction(Property $property)
    {
        $em = $this->getDoctrine()->getManager();

        $ancestors = $em->getRepository('AppBundle:Property')
            ->findAncestorsById($property);

        $descendants = $em->getRepository('AppBundle:Property')
            ->findDescendantsById($property);
/*
        $equivalences = $em->getRepository('AppBundle:Property')
            ->findEquivalencesById($property);

        $outgoingProperties = $em->getRepository('AppBundle:Property')
            ->findOutgoingPropertiesById($property);

        $outgoingInheritedProperties = $em->getRepository('AppBundle:Property')
            ->findOutgoingInheritedPropertiesById($property);

        $ingoingProperties = $em->getRepository('AppBundle:Property')
            ->findIngoingPropertiesById($property);

        $ingoingInheritedProperties = $em->getRepository('AppBundle:Property')
            ->findIngoingInheritedPropertiesById($property);*/

        $this->get('logger')
            ->info('Showing property: '.$property->getIdentifierInNamespace());


        return $this->render('property/show.html.twig', array(
            'property' => $property,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            /*'equivalences' => $equivalences,
            'outgoingProperties' => $outgoingProperties,
            'outgoingInheritedProperties' => $outgoingInheritedProperties,
            'ingoingProperties' => $ingoingProperties,
            'ingoingInheritedProperties' => $ingoingInheritedProperties*/
        ));
    }

    /**
     * @Route("/properties-tree")
     */
    public function getTreeAction()
    {
        return $this->render('property/tree.html.twig');
    }

    /**
     * @Route("/properties-tree/json", name="properties_tree_json")
     * @Method("GET")
     * @return JsonResponse a Json formatted tree representation of Properties
     */
    public function getTreeJson()
    {
        $em = $this->getDoctrine()->getManager();
        $properties = $em->getRepository('AppBundle:Property')
            ->findPropertiesTree();

        return new JsonResponse($properties[0]['json'],200, array(), true);
    }

    /**
     * @Route("/properties-tree-legend/json", name="properties_tree_legend_json")
     * @Method("GET")
     * @return JsonResponse a Json formatted legend for the Properties tree
     */
    public function getTreeLegendJson()
    {
        $em = $this->getDoctrine()->getManager();
        $legend = $em->getRepository('AppBundle:Property')
            ->findPropertiesTreeLegend();


        return new JsonResponse($legend[0]['json']);
    }

}