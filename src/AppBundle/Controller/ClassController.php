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
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\Project;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\ClassEditIdentifierForm;
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

        if (!is_null($this->getUser())) {
            if($this->getUser()->getCurrentActiveProject()->getId() == 21){
                $classes = $em->getRepository('AppBundle:OntoClass')
                    ->findFilteredByPublicProjectOrderedById();
            }
            else{
                $classes = $em->getRepository('AppBundle:OntoClass')
                    ->findFilteredByActiveProjectOrderedById($this->getUser());
            }
        }
        else{
            $classes = $em->getRepository('AppBundle:OntoClass')
                ->findFilteredByPublicProjectOrderedById();
        }

        return $this->render('class/list.html.twig', [
            'classes' => $classes
        ]);
    }

    /**
     * @Route("class/new/{namespace}", name="class_new")
     */
    public function newAction(Request $request, OntoNamespace $namespace)
    {
        $class = new OntoClass();

        $this->denyAccessUnlessGranted('edit', $namespace);


        $em = $this->getDoctrine()->getManager();
        $systemTypeScopeNote = $em->getRepository('AppBundle:SystemType')->find(1); //systemType 1 = scope note
        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); //systemType 1 = scope note

        $scopeNote = new TextProperty();
        $scopeNote->setClass($class);
        $scopeNote->setSystemType($systemTypeScopeNote);
        $scopeNote->addNamespace($namespace);
        $scopeNote->setCreator($this->getUser());
        $scopeNote->setModifier($this->getUser());
        $scopeNote->setCreationTime(new \DateTime('now'));
        $scopeNote->setModificationTime(new \DateTime('now'));

        $class->addTextProperty($scopeNote);

        $label = new Label();
        $label->setClass($class);
        $label->addNamespace($namespace);
        $label->setIsStandardLabelForLanguage(true);
        $label->setCreator($this->getUser());
        $label->setModifier($this->getUser());
        $label->setCreationTime(new \DateTime('now'));
        $label->setModificationTime(new \DateTime('now'));

        $class->setIsManualIdentifier(is_null($namespace->getTopLevelNamespace()->getClassPrefix()));
        $class->addNamespace($namespace);
        $class->addLabel($label);
        $class->setCreator($this->getUser());
        $class->setModifier($this->getUser());

        $form = $this->createForm(ClassQuickAddForm::class, $class);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $class = $form->getData();
            $class->setIsManualIdentifier(is_null($namespace->getTopLevelNamespace()->getClassPrefix()));
            $class->addNamespace($namespace);
            $class->setCreator($this->getUser());
            $class->setModifier($this->getUser());
            $class->setCreationTime(new \DateTime('now'));
            $class->setModificationTime(new \DateTime('now'));

            if($class->getTextProperties()->containsKey(1)){
                $class->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $class->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $class->getTextProperties()[1]->setSystemType($systemTypeExample);
                $class->getTextProperties()[1]->addNamespace($namespace);
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
     * @Route("/class/{id}", name="class_show")
     * @param OntoClass $class
     * @return Response the rendered template
     */
    public function showAction(OntoClass $class)
    {
        $em = $this->getDoctrine()->getManager();

        if (!is_null($this->getUser())) {
            // L'utilisateur est connecté
            $user = $this->getUser();

            $ancestors = $em->getRepository('AppBundle:OntoClass')
                ->findFilteredAncestorsById($class, $user);

            $descendants = $em->getRepository('AppBundle:OntoClass')
                ->findFilteredDescendantsById($class, $user);

            $equivalences = $em->getRepository('AppBundle:OntoClass')
                ->findFilteredEquivalencesById($class, $user);

            $relations = $em->getRepository('AppBundle:OntoClass')
                ->findFilteredRelationsById($class, $user);

            $outgoingProperties = $em->getRepository('AppBundle:Property')
                ->findFilteredOutgoingPropertiesById($class, $user);

            $outgoingInheritedProperties = $em->getRepository('AppBundle:Property')
                ->findFilteredOutgoingInheritedPropertiesById($class, $user);

            $ingoingProperties = $em->getRepository('AppBundle:Property')
                ->findFilteredIngoingPropertiesById($class, $user);

            $ingoingInheritedProperties = $em->getRepository('AppBundle:Property')
                ->findFilteredIngoingInheritedPropertiesById($class, $user);

            $userProjectAssociation = $em->getRepository('AppBundle:UserProjectAssociation')
                ->findOneBy(array('user' => $user, 'project' => $user->getCurrentActiveProject()));

            $activeNamespaces = $em->getRepository('AppBundle:OntoNamespace')
                ->findAllActiveNamespacesForUser($user);

            $classVersionNamespace = null;
            foreach ($class->getNamespaces() as $namespace){
                if(is_null($classVersionNamespace)){
                    $classVersionNamespace = $namespace;
                }

                if($namespace->getCreationTime() > $classVersionNamespace->getCreationTime()){
                    $classVersionNamespace = $namespace;
                }

                if($namespace->getIsOngoing()){
                    $classVersionNamespace = $namespace;
                    break;
                }
            }
            $activeNamespaces[] = $classVersionNamespace;
        }
        else{ // L'utilisateur n'est pas connecté
            $ancestors = $em->getRepository('AppBundle:OntoClass')
                ->findAncestorsById($class);

            $descendants = $em->getRepository('AppBundle:OntoClass')
                ->findDescendantsById($class);

            $equivalences = $em->getRepository('AppBundle:OntoClass')
                ->findEquivalencesById($class);

            $relations = $em->getRepository('AppBundle:OntoClass')
                ->findRelationsById($class);

            $outgoingProperties = $em->getRepository('AppBundle:Property')
                ->findOutgoingPropertiesById($class);

            $outgoingInheritedProperties = $em->getRepository('AppBundle:Property')
                ->findOutgoingInheritedPropertiesById($class);

            $ingoingProperties = $em->getRepository('AppBundle:Property')
                ->findIngoingPropertiesById($class);

            $ingoingInheritedProperties = $em->getRepository('AppBundle:Property')
                ->findIngoingInheritedPropertiesById($class);

            $activeNamespaces = $em->getRepository('AppBundle:OntoNamespace')
                ->findActiveNamespacesInPublicProject();

            $classVersionNamespace = null;
            foreach ($class->getNamespaces() as $namespace){
                if(is_null($classVersionNamespace)){
                    $classVersionNamespace = $namespace;
                }

                if($namespace->getCreationTime() > $classVersionNamespace->getCreationTime()){
                    $classVersionNamespace = $namespace;
                }

                if($namespace->getIsOngoing()){
                    $classVersionNamespace = $namespace;
                    break;
                }
            }
            $activeNamespaces[] = $classVersionNamespace;
        }

        $this->get('logger')
            ->info('Showing class: '.$class->getIdentifierInNamespace());


        return $this->render('class/show.html.twig', array(
            'class' => $class,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'equivalences' => $equivalences,
            'relations' => $relations,
            'outgoingProperties' => $outgoingProperties,
            'outgoingInheritedProperties' => $outgoingInheritedProperties,
            'ingoingProperties' => $ingoingProperties,
            'ingoingInheritedProperties' => $ingoingInheritedProperties,
            'activeNamespaces' => $activeNamespaces
        ));
    }

    /**
     * @Route("/class/{id}/edit", name="class_edit")
     * @param OntoClass $class
     * @return Response the rendered template
     */
    public function editAction(OntoClass $class, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $class);

        $classTemp = new OntoClass();
        $classTemp->addNamespace($class->getOngoingNamespace());
        $classTemp->setIdentifierInNamespace($class->getIdentifierInNamespace());
        $classTemp->setIsManualIdentifier(is_null($class->getOngoingNamespace()->getTopLevelNamespace()->getClassPrefix()));
        $classTemp->setCreator($this->getUser());
        $classTemp->setModifier($this->getUser());
        $classTemp->setCreationTime(new \DateTime('now'));
        $classTemp->setModificationTime(new \DateTime('now'));

        $formIdentifier = $this->createForm(ClassEditIdentifierForm::class, $classTemp);
        $formIdentifier->handleRequest($request);
        if ($formIdentifier->isSubmitted() && $formIdentifier->isValid()) {
            $class->setIdentifierInNamespace($classTemp->getIdentifierInNamespace());
            $em = $this->getDoctrine()->getManager();
            $em->persist($class);
            $em->flush();

            $this->addFlash('success', 'Class updated!');
            return $this->redirectToRoute('class_edit', [
                'id' => $class->getId(),
                '_fragment' => 'identification'
            ]);
        }

        $em = $this->getDoctrine()->getManager();

        if (!is_null($this->getUser())) {
            // L'utilisateur est connecté
            $user = $this->getUser();

            $ancestors = $em->getRepository('AppBundle:OntoClass')
                ->findFilteredAncestorsById($class, $user);

            $descendants = $em->getRepository('AppBundle:OntoClass')
                ->findFilteredDescendantsById($class, $user);

            $equivalences = $em->getRepository('AppBundle:OntoClass')
                ->findFilteredEquivalencesById($class, $user);

            $relations = $em->getRepository('AppBundle:OntoClass')
                ->findFilteredRelationsById($class, $user);

            $outgoingProperties = $em->getRepository('AppBundle:Property')
                ->findFilteredOutgoingPropertiesById($class, $user);

            $outgoingInheritedProperties = $em->getRepository('AppBundle:Property')
                ->findFilteredOutgoingInheritedPropertiesById($class, $user);

            $ingoingProperties = $em->getRepository('AppBundle:Property')
                ->findFilteredIngoingPropertiesById($class, $user);

            $ingoingInheritedProperties = $em->getRepository('AppBundle:Property')
                ->findFilteredIngoingInheritedPropertiesById($class, $user);

            $activeNamespaces = $em->getRepository('AppBundle:OntoNamespace')
                ->findAllActiveNamespacesForUser($user);

            $classVersionNamespace = null;
            foreach ($class->getNamespaces() as $namespace){
                if(is_null($classVersionNamespace)){
                    $classVersionNamespace = $namespace;
                }

                if($namespace->getCreationTime() > $classVersionNamespace->getCreationTime()){
                    $classVersionNamespace = $namespace;
                }

                if($namespace->getIsOngoing()){
                    $classVersionNamespace = $namespace;
                    break;
                }
            }
            $activeNamespaces[] = $classVersionNamespace;
        }
        else{ // L'utilisateur n'est pas connecté
            $ancestors = $em->getRepository('AppBundle:OntoClass')
                ->findAncestorsById($class);

            $descendants = $em->getRepository('AppBundle:OntoClass')
                ->findDescendantsById($class);

            $equivalences = $em->getRepository('AppBundle:OntoClass')
                ->findEquivalencesById($class);

            $relations = $em->getRepository('AppBundle:OntoClass')
                ->findRelationsById($class);

            $outgoingProperties = $em->getRepository('AppBundle:Property')
                ->findOutgoingPropertiesById($class);

            $outgoingInheritedProperties = $em->getRepository('AppBundle:Property')
                ->findOutgoingInheritedPropertiesById($class);

            $ingoingProperties = $em->getRepository('AppBundle:Property')
                ->findIngoingPropertiesById($class);

            $ingoingInheritedProperties = $em->getRepository('AppBundle:Property')
                ->findIngoingInheritedPropertiesById($class);

            $activeNamespaces = $em->getRepository('AppBundle:OntoNamespace')
                ->findActiveNamespacesInPublicProject();

            $classVersionNamespace = null;
            foreach ($class->getNamespaces() as $namespace){
                if(is_null($classVersionNamespace)){
                    $classVersionNamespace = $namespace;
                }

                if($namespace->getCreationTime() > $classVersionNamespace->getCreationTime()){
                    $classVersionNamespace = $namespace;
                }

                if($namespace->getIsOngoing()){
                    $classVersionNamespace = $namespace;
                    break;
                }
            }
            $activeNamespaces[] = $classVersionNamespace;
        }

        $this->get('logger')
            ->info('Showing class: '.$class->getIdentifierInNamespace());


        return $this->render('class/edit.html.twig', array(
            'class' => $class,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'equivalences' => $equivalences,
            'relations' => $relations,
            'outgoingProperties' => $outgoingProperties,
            'outgoingInheritedProperties' => $outgoingInheritedProperties,
            'ingoingProperties' => $ingoingProperties,
            'ingoingInheritedProperties' => $ingoingInheritedProperties,
            'activeNamespaces' => $activeNamespaces,
            'classIdentifierForm' => $formIdentifier->createView()
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

        if (!is_null($this->getUser()) && $this->getUser()->getCurrentActiveProject()->getId() != 21) {
            $classes = $em->getRepository('AppBundle:OntoClass')
                ->findFilteredClassesTree($this->getUser());
        }
        else{
            $classes = $em->getRepository('AppBundle:OntoClass')
                ->findClassesTree();
        }

        return new JsonResponse($classes[0]['json'],200, array(), true);
    }

    /**
     * @Route("/classes-tree-legend/json", name="classes_tree_legend_json")
     * @Method("GET")
     * @return JsonResponse a Json formatted legend for the OntoClasses tree
     */
    public function getTreeLegendJson()
    {
        if (!is_null($this->getUser()) && $this->getUser()->getCurrentActiveProject()->getId() != 21) {
            $context = 'public_namespace_classes_tree';
        }
        else{
            $context = 'public_namespace_classes_tree';
        }

        $em = $this->getDoctrine()->getManager();
        $legend = $em->getRepository('AppBundle:OntoClass')
            ->findClassesTreeLegend($context);


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