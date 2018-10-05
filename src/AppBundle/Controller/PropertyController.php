<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/06/2017
 * Time: 17:11
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Label;
use AppBundle\Entity\OntoClass;
use AppBundle\Entity\Project;
use AppBundle\Entity\Property;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\IngoingPropertyQuickAddForm;
use AppBundle\Form\OutgoingPropertyQuickAddForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @Route("property/{type}/new/{class}", name="property_new")
     */
    public function newAction($type,Request $request, OntoClass $class)
    {
        $property = new Property();

        $this->denyAccessUnlessGranted('edit', $class);

        if($type !== 'ingoing' && $type !== 'outgoing') throw $this->createNotFoundException('The requested property type "'.$type.'" does not exist!');

        $em = $this->getDoctrine()->getManager();
        $systemTypeScopeNote = $em->getRepository('AppBundle:SystemType')->find(1); //systemType 1 = scope note
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 1 = scope note

        $scopeNote = new TextProperty();
        $scopeNote->setProperty($property);
        $scopeNote->setSystemType($systemTypeScopeNote);
        $scopeNote->addNamespace($class->getOngoingNamespace());
        $scopeNote->setCreator($this->getUser());
        $scopeNote->setModifier($this->getUser());
        $scopeNote->setCreationTime(new \DateTime('now'));
        $scopeNote->setModificationTime(new \DateTime('now'));

        $property->addTextProperty($scopeNote);

        $label = new Label();
        $label->setProperty($property);
        $label->setIsStandardLabelForLanguage(true);
        $label->setCreator($this->getUser());
        $label->setModifier($this->getUser());
        $label->setCreationTime(new \DateTime('now'));
        $label->setModificationTime(new \DateTime('now'));

        $property->addLabel($label);
        if($type == 'outgoing') {
            $property->setDomain($class);
        }
        elseif ($type == 'ingoing') {
            $property->setRange($class);
        }

        $property->setIsManualIdentifier(is_null($class->getOngoingNamespace()->getClassPrefix()));
        $property->setCreator($this->getUser());
        $property->setModifier($this->getUser());

        $form = null;
        if($type == 'outgoing') {
            $form = $this->createForm(OutgoingPropertyQuickAddForm::class, $property);
        }
        elseif ($type == 'ingoing') {
            $form = $this->createForm(IngoingPropertyQuickAddForm::class, $property);
        }


        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $property = $form->getData();
            $property->addNamespace($class->getOngoingNamespace());
            if($type == 'outgoing') {
                $property->setDomain($class);
            }
            elseif ($type == 'ingoing') {
                $property->setRange($class);
            }

            $property->setCreator($this->getUser());
            $property->setModifier($this->getUser());
            $property->setCreationTime(new \DateTime('now'));
            $property->setModificationTime(new \DateTime('now'));

            if($property->getTextProperties()->containsKey(1)){
                $property->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $property->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $property->getTextProperties()[1]->setSystemType($systemTypeExample);
                $property->getTextProperties()[1]->addNamespace($class->getOngoingNamespace());
                $property->getTextProperties()[1]->setProperty($property);
            }


            $em = $this->getDoctrine()->getManager();
            $em->persist($property);
            $em->flush();

            return $this->redirectToRoute('property_show', [
                'id' => $property->getId()
            ]);

        }

        $em = $this->getDoctrine()->getManager();

        $template = null;
        if($type == 'outgoing') {
            $template = 'property/newOutgoing.html.twig';
        }
        elseif ($type == 'ingoing') {
            $template = 'property/newIngoing.html.twig';
        }
        return $this->render($template, [
            'property' => $property,
            'type' => $type,
            'propertyForm' => $form->createView()
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

        $domainRange = $em->getRepository('AppBundle:Property')
            ->findDomainRangeById($property);


        $this->get('logger')
            ->info('Showing property: '.$property->getIdentifierInNamespace());


        return $this->render('property/show.html.twig', array(
            'property' => $property,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'domainRange' => $domainRange
        ));
    }

    /**
     * @Route("/property/{id}/edit", name="property_edit")
     * @param string $id
     * @return Response the rendered template
     */
    public function editAction(Property $property)
    {
        $em = $this->getDoctrine()->getManager();

        $ancestors = $em->getRepository('AppBundle:Property')
            ->findAncestorsById($property);

        $descendants = $em->getRepository('AppBundle:Property')
            ->findDescendantsById($property);

        $domainRange = $em->getRepository('AppBundle:Property')
            ->findDomainRangeById($property);


        $this->get('logger')
            ->info('Showing property: '.$property->getIdentifierInNamespace());


        return $this->render('property/edit.html.twig', array(
            'property' => $property,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'domainRange' => $domainRange
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