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
use AppBundle\Entity\SystemType;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\ClassEditIdentifierForm;
use AppBundle\Form\NamespaceEditIdentifiersForm;
use AppBundle\Form\ClassQuickAddForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

        // Compléter avec les références parents (directs/indirects)
        $refsNsId = [];
        foreach($namespacesId as $nsId){
            $ns = $em->getRepository('AppBundle:OntoNamespace')->find($nsId);
            foreach ($ns->getAllReferencedNamespaces() as $refNs){
                if(!in_array($refNs->getId(), $refsNsId)){$refsNsId[] = $refNs->getId();}
            }
        }
        $namespacesId = array_merge($namespacesId, $refsNsId);

        // Récupérer l'ensemble des namespaces root déjà utilisé pour le filtrage
        $rootNamespacesId = [];
        foreach ($namespacesId as $namespaceId){
            $rootNamespacesId[] = $em->getRepository('AppBundle:OntoNamespace')->find($namespaceId)->getTopLevelNamespace()->getId();
        }

        // Récupérer toutes les classes sans le filtrage
        // N'afficher que les classes/propriétés de la version choisie par l'utilisateur sinon dernière publiée d'un espace de noms ou,
        // si l'espace n'a pas de version publiée, la version ongoing.
        // 1- Récuperer tous les roots
        $allRootNamespaces = $em->getRepository('AppBundle:OntoNamespace')->findBy(array("isTopLevelNamespace" => true));
        // 2- Récupérer la bonne version (choisie par l'utilisateur sinon dernière publiée sinon ongoing)
        $allNamespacesId = $namespacesId;

        // Enlever ceux déjà utilisés
        $filteredRootNamespaces = array_filter($allRootNamespaces, function($v) use ($rootNamespacesId) {return !in_array($v->getId(), $rootNamespacesId);});
        foreach ($filteredRootNamespaces as $rootNamespace){
            $defaultNamespace = null;
            foreach ($rootNamespace->getChildVersions() as $childVersion){
                if($childVersion->getIsOngoing() and !$rootNamespace->getHasPublication()){
                    $defaultNamespace = $childVersion;
                }
                elseif(is_null($defaultNamespace) || $defaultNamespace->getPublishedAt() < $childVersion->getPublishedAt()){
                    $defaultNamespace = $childVersion;
                }
            }
            $allNamespacesId[] = $defaultNamespace->getId();
        }

        // Récupérer toutes les classes selon $allNamespacesId
        $allClasses = $em->getRepository('AppBundle:OntoClass')->findClassesByNamespacesId($allNamespacesId);

        return $this->render('class/list.html.twig', [
            'classes' => $allClasses,
            'allNamespacesId' => $allNamespacesId,
            'namespacesId' => $namespacesId
        ]);
    }

    /**
     * @Route("class/new/{namespace}", name="class_new", requirements={"namespace"="^[0-9]+$"})
     * @param Request $request
     * @param OntoNamespace $namespace
     * @return RedirectResponse|Response
     * @throws \Exception
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

        $class->addClassVersion($classVersion);

        $scopeNote = new TextProperty();
        $scopeNote->setClass($class);
        $scopeNote->setSystemType($systemTypeScopeNote);
        $scopeNote->setNamespaceForVersion($namespace);
        $scopeNote->setCreator($this->getUser());
        $scopeNote->setModifier($this->getUser());
        $scopeNote->setCreationTime(new \DateTime('now'));
        $scopeNote->setModificationTime(new \DateTime('now'));

        $class->addTextProperty($scopeNote);

        $label = new Label();
        $label->setClass($class);
        $label->setNamespaceForVersion($namespace);
        $label->setIsStandardLabelForLanguage(true);
        $label->setCreator($this->getUser());
        $label->setModifier($this->getUser());
        $label->setCreationTime(new \DateTime('now'));
        $label->setModificationTime(new \DateTime('now'));

        $class->addLabel($label);

        $class->setIsManualIdentifier(is_null($namespace->getTopLevelNamespace()->getClassPrefix()));

        $class->setCreator($this->getUser());
        $class->setModifier($this->getUser());

        $form = $this->createForm(ClassQuickAddForm::class, $class);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $class = $form->getData();
            $class->setIsManualIdentifier(is_null($namespace->getTopLevelNamespace()->getClassPrefix()));
            $class->setCreator($this->getUser());
            $class->setModifier($this->getUser());
            $class->setCreationTime(new \DateTime('now'));
            $class->setModificationTime(new \DateTime('now'));

            if($class->getTextProperties()->containsKey(1)){
                $class->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $class->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $class->getTextProperties()[1]->setSystemType($systemTypeExample);
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
     * @Route("/class/{id}", name="class_show", requirements={"id"="^[0-9]+$"})
     * @Route("/class/{id}/namespace/{namespaceFromUrlId}", name="class_show_with_version", requirements={"id"="^([0-9]+)|(classID){1}$", "namespaceFromUrlId"="^([0-9]+)|(namespaceFromUrlId){1}$"})
     * @param OntoClass $class
     * @param int|null $namespaceFromUrlId
     * @return Response the rendered template
     */
    public function showAction(OntoClass $class, $namespaceFromUrlId=null)
    {
        //Vérifier si le namespace -si renseigné- est bien associé à la classe
        $namespaceFromUrl = null;
        if(!is_null($namespaceFromUrlId)) {
            $namespaceFromUrlId = intval($namespaceFromUrlId);
            $cvCollection = $class->getClassVersions()->filter(function (OntoClassVersion $classVersion) use ($namespaceFromUrlId) {
                return $classVersion->getNamespaceForVersion()->getId() === $namespaceFromUrlId;
            });
            if($cvCollection->count() == 0){
                return $this->redirectToRoute('class_show', [
                    'id' => $class->getId()
                ]);
            }
            else{
                $namespaceFromUrl = $cvCollection->first()->getNamespaceForVersion();
            }
        }

        // Récupérer la version de la classe demandée
        // Dans l'ordre : la version demandée > la version ongoing > la version la plus récente > la première version dans la boucle
        $classVersion = $class->getClassVersionForDisplay($namespaceFromUrl);

        // On doit avoir une version de la classe sinon on lance une exception.
        if(is_null($classVersion)){
            throw $this->createNotFoundException('The class n°'.$class->getId().' has no version. Please contact an administrator.');
        }

        $em = $this->getDoctrine()->getManager();

        //Si le namespace n'est pas specifié dans l'url mais dans My Current Namespace, rediriger
        // $namespacesIdFromUser : Ensemble de tous les namespaces activés par l'utilisateur

        if(is_null($namespaceFromUrlId)) {
            if (is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21) {
                $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
            } else { // Utilisateur connecté et utilisant un autre projet
                $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
            }
            foreach ($namespacesIdFromUser as $namespaceIdFromUser) {
                $namespaceFromUser = $em->getRepository('AppBundle:OntoNamespace')->find($namespaceIdFromUser);
                if ($classVersion->getNamespaceForVersion()->getTopLevelNamespace()->getId() === $namespaceFromUser->getTopLevelNamespace()->getId() and $class->getClassVersionForDisplay($namespaceFromUser)) {
                    return $this->redirectToRoute('class_show_with_version', [
                        'id' => $class->getId(),
                        'namespaceFromUrlId' => $namespaceIdFromUser
                    ]);
                }
            }
            return $this->redirectToRoute('class_show_with_version', [
                'id' => $class->getId(),
                'namespaceFromUrlId' => $classVersion->getNamespaceForVersion()->getId()
            ]);
        }

        // $namespacesIdFromClassVersion : Ensemble de namespaces provenant de la classe affiché (namespaceForVersion + references)
        // $rootNamespacesFromClassVersion : Ensemble des versions racines (pour contrôle en dessous)
        $nsId = $classVersion->getNamespaceForVersion()->getId();
        $namespacesIdFromClassVersion[] = $nsId;
        $rootNamespacesFromClassVersion[] = $em->getRepository('AppBundle:OntoNamespace')->findOneBy(array('id' => $nsId))->getTopLevelNamespace();

        foreach($classVersion->getNamespaceForVersion()->getAllReferencedNamespaces() as $referencedNamespaces){
            $nsId = $referencedNamespaces->getId();
            $namespacesIdFromClassVersion[] = $nsId;
            $rootNamespacesFromClassVersion[] = $em->getRepository('AppBundle:OntoNamespace')->findOneBy(array('id' => $nsId))->getTopLevelNamespace();
        }

        // $namespacesIdFromUser : Ensemble de tous les namespaces activés par l'utilisateur
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){
            $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un projet
            $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }
        // sauf ceux automatiquement activés par l'entité
        $namespacesIdFromUser = array_diff($namespacesIdFromUser, $namespacesIdFromClassVersion);

        // Créer un array de ns à ajouter (ne pas rajouter ceux dont le root est déjà utilisé
        $nsIdFromUser = array();
        foreach ($namespacesIdFromUser as $namespaceIdFromUser){
            $nsRootUser = $em->getRepository('AppBundle:OntoNamespace')->findOneBy(array('id' => $namespaceIdFromUser))->getTopLevelNamespace();
            if(!in_array($nsRootUser, $rootNamespacesFromClassVersion)){
                $nsIdFromUser[] = $namespaceIdFromUser;
            }
        }

        // $namespacesId : Tous les namespaces trouvés ci-dessus
        $namespacesId = array_merge($namespacesIdFromClassVersion, $nsIdFromUser);

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
            'namespacesId' => $namespacesId,
            'namespacesIdFromClassVersion' => $namespacesIdFromClassVersion,
            'namespacesIdFromUser' => $namespacesIdFromUser

        ));
    }

    /**
     * @Route("/class/{id}/edit", name="class_edit", requirements={"id"="^[0-9]+$"})
     * @param OntoClass $class
     * @return Response the rendered template
     */
    public function editAction(OntoClass $class, Request $request)
    {
        // Récupérer la version de la classe demandée
        // En mode Edit on a besoin de la version ongoing
        // la version automatiquement retournée par cette fonction est la version ongoing.
        $classVersion = $class->getClassVersionForDisplay();

        // On doit avoir une version de la classe sinon on lance une exception.
        if(is_null($classVersion)){
            throw $this->createNotFoundException('The class n°'.$class->getId().' has no version. Please contact an administrator.');
        }

        $em = $this->getDoctrine()->getManager();

        // $namespacesIdFromClassVersion : Ensemble de namespaces provenant de la classe affiché (namespaceForVersion + references)
        $namespacesIdFromClassVersion[] = $classVersion->getNamespaceForVersion()->getId();

        foreach($classVersion->getNamespaceForVersion()->getReferencedNamespaceAssociations() as $referencedNamespacesAssociation){
            $namespacesIdFromClassVersion[] = $referencedNamespacesAssociation->getReferencedNamespace()->getId();
        }

        // $namespacesIdFromUser : Ensemble de tous les namespaces activés par l'utilisateur
        if(is_null($this->getUser()) || $this->getUser()->getCurrentActiveProject()->getId() == 21){
            $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesIdFromUser = $em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($this->getUser());
        }
        // sauf ceux automatiquement activés par l'entité
        $namespacesIdFromUser = array_diff($namespacesIdFromUser, $namespacesIdFromClassVersion);

        // $namespacesId : Tous les namespaces trouvés ci-dessus
        $namespacesId = array_merge($namespacesIdFromClassVersion, $namespacesIdFromUser);

        $ancestors = $em->getRepository('AppBundle:OntoClass')->findAncestorsByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $descendants = $em->getRepository('AppBundle:OntoClass')->findDescendantsByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $relations = $em->getRepository('AppBundle:OntoClass')->findRelationsByClassVersionAndNamespacesId($classVersion, $namespacesId);

        $outgoingProperties = $em->getRepository('AppBundle:property')->findOutgoingPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $outgoingInheritedProperties = $em->getRepository('AppBundle:property')->findOutgoingInheritedPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $ingoingProperties = $em->getRepository('AppBundle:property')->findIngoingPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
        $ingoingInheritedProperties =  $em->getRepository('AppBundle:property')->findIngoingInheritedPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);

        $this->denyAccessUnlessGranted('edit', $classVersion);

        $classVersionTemp = new OntoClassVersion();
        $classVersionTemp->setNamespaceForVersion($classVersion->getNamespaceForVersion());

        $classTemp = new OntoClass();
        $classTemp->addClassVersion($classVersionTemp);
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

        //If validation status is in validation request or is validation, we can't allow edition of the entity and we rended the show template
        if (!is_null($classVersion->getValidationStatus()) && ($classVersion->getValidationStatus()->getId() === 26 || $classVersion->getValidationStatus()->getId() === 28)) {
            return $this->render('class/show.html.twig', [
                'classVersion' => $classVersion,
                'ancestors' => $ancestors,
                'descendants' => $descendants,
                'relations' => $relations,
                'outgoingProperties' => $outgoingProperties,
                'outgoingInheritedProperties' => $outgoingInheritedProperties,
                'ingoingProperties' => $ingoingProperties,
                'ingoingInheritedProperties' => $ingoingInheritedProperties,
                'namespacesId' => $namespacesId
            ]);
        }


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
            'namespacesIdFromClassVersion' => $namespacesIdFromClassVersion,
            'namespacesIdFromUser' => $namespacesIdFromUser,
            'classIdentifierForm' => $formIdentifier->createView()
        ));
    }

    /**
     * @Route("/class-version/{id}/edit-validity/{validationStatus}", name="class_version_validation_status_edit", requirements={"id"="^[0-9]+$", "validationStatus"="^26|27|28$"})
     * @param OntoClassVersion $classVersion
     * @param SystemType $validationStatus
     * @param Request $request
     * @throws \Exception in case of unsuccessful validation
     * @return RedirectResponse|Response
     */
    public function editValidationStatusAction(OntoClassVersion $classVersion, SystemType $validationStatus, Request $request)
    {
        // On doit avoir une version de la classe sinon on lance une exception.
        if(is_null($classVersion)){
            throw $this->createNotFoundException('The class version n°'.$classVersion->getId().' does not exist. Please contact an administrator.');
        }

        //Denied access if not an authorized validator
        $this->denyAccessUnlessGranted('validate', $classVersion);


        $classVersion->setModifier($this->getUser());

        $newValidationStatus = new SystemType();

        try{
            $em = $this->getDoctrine()->getManager();
            $newValidationStatus = $em->getRepository('AppBundle:SystemType')
                ->findOneBy(array('id' => $validationStatus->getId()));
        } catch (\Exception $e) {
            throw new BadRequestHttpException('The provided status does not exist.');
        }

        if (!is_null($newValidationStatus)) {
            $statusId = intval($newValidationStatus->getId());
            if (in_array($statusId, [26,27,28], true)) {
                $classVersion->setValidationStatus($newValidationStatus);
                $classVersion->setModifier($this->getUser());
                $classVersion->setModificationTime(new \DateTime('now'));

                $em->persist($classVersion);

                $em->flush();

                if ($statusId == 27){
                    return $this->redirectToRoute('class_edit', [
                        'id' => $classVersion->getClass()->getId()
                    ]);
                }
                else return $this->redirectToRoute('class_show', [
                    'id' => $classVersion->getClass()->getId()
                ]);

            }
        }

        return $this->redirectToRoute('class_show', [
            'id' => $classVersion->getClass()->getId()
        ]);
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
     * @Route("/class/{class}/graph/json", name="class_graph_json", requirements={"class"="^[0-9]+$"})
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
     * @Route("/api/classes/project/{project}/json", name="classes_project_json", requirements={"project"="^[0-9]+$"})
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