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
use AppBundle\Entity\OntoClassVersion;
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
    public function listAction(){
        $em = $this->getDoctrine()->getManager();

        // FILTRAGE : Récupérer les namespaces
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }

        // Récupérer les classes selon le filtrage obtenu
        $classes = $em->getRepository('AppBundle:OntoClass')->findClassesByNamespacesId($namespacesId);

        return $this->render('class/list.html.twig', [
            'classes' => $classes,
            'namespacesId' => $namespacesId
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

        $classVersion = new OntoClassVersion();
        $classVersion->setClass($class);
        $classVersion->setNamespaceForVersion($namespace);
        $classVersion->setCreator($this->getUser());
        $classVersion->setModifier($this->getUser());
        $classVersion->setCreationTime(new \DateTime('now'));
        $classVersion->setModificationTime(new \DateTime('now'));

        $scopeNote = new TextProperty();
        $scopeNote->setClass($class);
        $scopeNote->setSystemType($systemTypeScopeNote);
        //$scopeNote->addNamespace($namespace); TODO: delete this line after successful test of the SolutionD branch
        $scopeNote->setNamespaceForVersion($namespace);
        $scopeNote->setCreator($this->getUser());
        $scopeNote->setModifier($this->getUser());
        $scopeNote->setCreationTime(new \DateTime('now'));
        $scopeNote->setModificationTime(new \DateTime('now'));

        $class->addTextProperty($scopeNote);

        $label = new Label();
        $label->setClass($class);
        //$label->addNamespace($namespace); TODO: delete this line after succesful test of the SolutionD branch
        $label->setNamespaceForVersion($namespace);
        $label->setIsStandardLabelForLanguage(true);
        $label->setCreator($this->getUser());
        $label->setModifier($this->getUser());
        $label->setCreationTime(new \DateTime('now'));
        $label->setModificationTime(new \DateTime('now'));

        $class->setIsManualIdentifier(is_null($namespace->getTopLevelNamespace()->getClassPrefix()));
        //$class->addNamespace($namespace); TODO: delete this line after successful test of the SolutionD branch
        $class->addLabel($label);
        $class->addClassVersion($classVersion);
        $class->setCreator($this->getUser());
        $class->setModifier($this->getUser());

        $form = $this->createForm(ClassQuickAddForm::class, $class);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $class = $form->getData();
            $class->setIsManualIdentifier(is_null($namespace->getTopLevelNamespace()->getClassPrefix()));
            //$class->addNamespace($namespace); TODO: delete this line after successful test of the SolutionD branch
            $class->setCreator($this->getUser());
            $class->setModifier($this->getUser());
            $class->setCreationTime(new \DateTime('now'));
            $class->setModificationTime(new \DateTime('now'));

            if($class->getTextProperties()->containsKey(1)){
                $class->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $class->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $class->getTextProperties()[1]->setSystemType($systemTypeExample);
                //$class->getTextProperties()[1]->addNamespace($namespace); TODO: delete this line after successful test of the SolutionD branch
                $class->getTextProperties()[1]->setNamespaceForVersion($namespace);
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
        // Récupérer la version de la classe demandée
        // Dans l'ordre : (la version demandée - TO DO) > la version ongoing > la version la plus récente > la première version dans la boucle
        $classVersion = null;
        foreach($class->getClassVersions() as $iClassVersion){
            if(is_null($classVersion)){
                $classVersion = $iClassVersion;
            }
            if($iClassVersion->getNamespaceForVersion()->getIsOngoing()){
                $classVersion = $iClassVersion;
                break;
            }
            if($iClassVersion->getCreationTime() > $classVersion->getCreationTime()){
                $classVersion = $iClassVersion;
            }
        }
        // On doit avoir une version de la classe sinon on lance une exception.
        if(is_null($classVersion)){
            throw $this->createNotFoundException('The class n°'.$class->getId().' has no version. Please contact an administrator.');
        }

        $em = $this->getDoctrine()->getManager();

        // FILTRAGE : Récupérer les clés de namespaces à utiliser
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }

        // Affaiblir le filtrage en rajoutant le namespaceForVersion de la classVersion si indisponible
        $namespaceForClassVersion = $classVersion->getNamespaceForVersion();
        if(!in_array($namespaceForClassVersion->getId(), $namespacesId)){
            $namespacesId[] = $namespaceForClassVersion->getId();
        }
        // Sans oublier les namespaces références si indisponibles
        foreach($namespaceForClassVersion->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            if(!in_array($referencedNamespacesAssociation->getReferencedNamespace()->getId(), $namespacesId)){
                $namespacesId[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
            }
        }

        $ancestors = $em->getRepository('AppBundle:OntoClass')->findAncestorsByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $descendants = $em->getRepository('AppBundle:OntoClass')->findDescendantsByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $relations = $em->getRepository('AppBundle:OntoClass')->findRelationsByClassVersionAndNamespacesId($classVersion, $namespacesId);

        $outgoingProperties = $em->getRepository('AppBundle:property')->findOutgoingPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $outgoingInheritedProperties = $em->getRepository('AppBundle:property')->findOutgoingInheritedPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $ingoingProperties = $em->getRepository('AppBundle:property')->findIngoingPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $ingoingInheritedProperties =  $em->getRepository('AppBundle:property')->findIngoingInheritedPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);

        return $this->render('class/show.html.twig', array(
            'classVersion' => $classVersion,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'relations' => $relations,
            'outgoingProperties' => $outgoingProperties,
            'outgoingInheritedProperties' => $outgoingInheritedProperties,
            'ingoingProperties' => $ingoingProperties,
            'ingoingInheritedProperties' => $ingoingInheritedProperties,
            'namespacesId' => $namespacesId
        ));
    }

    /**
     * @Route("/class/{id}/edit", name="class_edit")
     * @param OntoClass $class
     * @return Response the rendered template
     */
    public function editAction(OntoClass $class, Request $request)
    {
        // Récupérer la version de la classe demandée
        // Dans l'ordre : (la version demandée - TO DO) > la version ongoing > la version la plus récente > la première version dans la boucle
        $classVersion = $class->getClassVersionForDisplay();

        // On doit avoir une version de la classe sinon on lance une exception.
        if(is_null($classVersion)){
            throw $this->createNotFoundException('The class n°'.$class->getId().' has no version. Please contact an administrator.');
        }

        $em = $this->getDoctrine()->getManager();

        // FILTRAGE : Récupérer les clés de namespaces à utiliser
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }

        // Affaiblir le filtrage en rajoutant le namespaceForVersion de la classVersion si indisponible
        $namespaceForClassVersion = $classVersion->getNamespaceForVersion();
        if(!in_array($namespaceForClassVersion->getId(), $namespacesId)){
            $namespacesId[] = $namespaceForClassVersion->getId();
        }
        // Sans oublier les namespaces références si indisponibles
        foreach($namespaceForClassVersion->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            if(!in_array($referencedNamespacesAssociation->getReferencedNamespace()->getId(), $namespacesId)){
                $namespacesId[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
            }
        }

        $ancestors = $em->getRepository('AppBundle:OntoClass')->findAncestorsByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $descendants = $em->getRepository('AppBundle:OntoClass')->findDescendantsByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $relations = $em->getRepository('AppBundle:OntoClass')->findRelationsByClassVersionAndNamespacesId($classVersion, $namespacesId);

        $outgoingProperties = $em->getRepository('AppBundle:property')->findOutgoingPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $outgoingInheritedProperties = $em->getRepository('AppBundle:property')->findOutgoingInheritedPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $ingoingProperties = $em->getRepository('AppBundle:property')->findIngoingPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $ingoingInheritedProperties =  $em->getRepository('AppBundle:property')->findIngoingInheritedPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);

        $this->denyAccessUnlessGranted('edit', $classVersion);

        $classTemp = new OntoClass();
        //$classTemp->addNamespace($class->getOngoingNamespace()); TODO: à supprimer pour le projet Delta
        $classTemp->setIdentifierInNamespace($class->getIdentifierInNamespace());
        $classTemp->setIsManualIdentifier(is_null($classVersion->getNamespaceForVersion()->getTopLevelNamespace()->getClassPrefix()));
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

        $this->get('logger')
            ->info('Showing class: '.$class->getIdentifierInNamespace());


        return $this->render('class/edit.html.twig', array(
            'classVersion' => $classVersion,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'relations' => $relations,
            'outgoingProperties' => $outgoingProperties,
            'outgoingInheritedProperties' => $outgoingInheritedProperties,
            'ingoingProperties' => $ingoingProperties,
            'ingoingInheritedProperties' => $ingoingInheritedProperties,
            'namespacesId' => $namespacesId,
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