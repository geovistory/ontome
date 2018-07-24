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
use AppBundle\Entity\TextProperty;
use AppBundle\Form\ClassQuickAddForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
     * @Route("class/new/", name="new_class_form")
     */
    public function newAction(Request $request)
    {
        $class = new OntoClass();

        //$this->denyAccessUnlessGranted('edit', $childClass);


        $em = $this->getDoctrine()->getManager();
        $systemTypeScopeNote = $em->getRepository('AppBundle:SystemType')->find(1); //systemType 1 = scope note
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 1 = scope note

        $scopeNote = new TextProperty();
        $scopeNote->setClass($class);
        $scopeNote->setSystemType($systemTypeScopeNote);
        $scopeNote->setCreator($this->getUser());
        $scopeNote->setModifier($this->getUser());
        $scopeNote->setCreationTime(new \DateTime('now'));
        $scopeNote->setModificationTime(new \DateTime('now'));

        $class->addTextProperty($scopeNote);

        $label = new Label();
        $label->setClass($class);
        $label->setIsStandardLabelForLanguage(true);
        $label->setCreator($this->getUser());
        $label->setModifier($this->getUser());
        $label->setCreationTime(new \DateTime('now'));
        $label->setModificationTime(new \DateTime('now'));

        $class->addlabel($label);

        $form = $this->createForm(ClassQuickAddForm::class, $class);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $class = $form->getData();
            $class->addNamespace($class->getNamespace());
            $class->setCreator($this->getUser());
            $class->setModifier($this->getUser());
            $class->setCreationTime(new \DateTime('now'));
            $class->setModificationTime(new \DateTime('now'));

            if($class->getTextProperties()->containsKey(1)){
                $class->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $class->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $class->getTextProperties()[1]->setSystemType($systemTypeExample);
                $class->getTextProperties()[1]->setNamespace($class->getNamespace());
                $class->getTextProperties()[1]->setClass($class);
            }


            $em = $this->getDoctrine()->getManager();
            $em->persist($class);
            $em->flush();

            return $this->redirectToRoute('class_show', [
                'id' => $class->getId()
            ]);

        }

        $em = $this->getDoctrine()->getManager();


        return $this->render('class/new.html.twig', [
            'class' => $class,
            'classForm' => $form->createView()
        ]);
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