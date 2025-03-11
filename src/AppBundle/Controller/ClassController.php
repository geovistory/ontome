<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/06/2017
 * Time: 17:11
 */

namespace AppBundle\Controller;


use AppBundle\Entity\ClassAssociation;
use AppBundle\Entity\EntityAssociation;
use AppBundle\Entity\Label;
use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoClassVersion;
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\SystemType;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\ClassEditIdentifierForm;
use AppBundle\Form\ClassEditUriIdentifierForm;
use AppBundle\Form\NamespaceEditIdentifiersForm;
use AppBundle\Form\ClassQuickAddForm;
use AppBundle\Form\NamespaceUriParameterForm;
use AppBundle\Form\TextPropertyForm;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\DBALException;
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

        // Récupérer toutes les classes
        $allClasses = $em->getRepository('AppBundle:OntoClass')->findAll(); //->findClassesByNamespacesId($allNamespacesId);

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

        $uriParam = $namespace->getTopLevelNamespace()->getUriParameter();
        $identifierInUriPrefilled = '';
        if(!is_null($namespace->getTopLevelNamespace()->getClassPrefix())){
            $identifierInUriPrefilled = $namespace->getTopLevelNamespace()->getClassPrefix().($namespace->getTopLevelNamespace()->getCurrentClassNumber()+1);
        }
        switch ($uriParam){
            case 0:
                // Rien à faire
                break;
            case 1:
                if(!is_null($namespace->getTopLevelNamespace()->getClassPrefix())) {
                    $identifierInUriPrefilled = $identifierInUriPrefilled . '_';
                }
                break;
            default:
                $identifierInUriPrefilled = ''; // Pour les cas 2 et 3
        }

        if(!$namespace->getTopLevelNamespace()->getIsExternalNamespace()){
            if(empty($identifierInUriPrefilled)){
                // Si vide (à cause gestionnaire automatique désactivée)
                $identifierInUriPrefilled = 'identifierInUriPrefilled';
            }
            $class->setIdentifierInURI($identifierInUriPrefilled); // On attribue le même identifiant si namespace interne car non géré par le formulaire pour les NS internes
        }

        $form = $this->createForm(ClassQuickAddForm::class, $class, array(
            'uri_param' => $uriParam,
            'identifier_in_uri_prefilled' => $identifierInUriPrefilled,
            'is_external' => $classVersion->getNamespaceForVersion()->getTopLevelNamespace()->getIsExternalNamespace()
        ));

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $class = $form->getData();
            // Dans le cas où l'utilisateur a desactivé la gestion automatique des identifiers dans son NS interne
            // il faut remplir correctement l'identifier in uri qui est vide après formulaire
            if(!$namespace->getTopLevelNamespace()->getIsExternalNamespace() && $class->getIdentifierInURI() === 'identifierInUriPrefilled'){
                $class->setIdentifierInURI($class->getIdentifierInNamespace());
            }
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
     * @Route("/ontology/c{id}", name="class_uri_show", requirements={"id"="^[0-9]+$"})
     * @Route("/class/{id}/namespace/{namespaceFromUrlId}", name="class_show_with_version", requirements={"id"="^([0-9]+)|(classID){1}$", "namespaceFromUrlId"="^([0-9]+)|(namespaceFromUrlId){1}$"})
     * @param OntoClass $class
     * @param int|null $namespaceFromUrlId
     * @return Response the rendered template
     * @throws DBALException
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
                if ($classVersion->getNamespaceForVersion()->getTopLevelNamespace()->getId() === $namespaceFromUser->getTopLevelNamespace()->getId() and $class->getClassVersionForDisplay($namespaceFromUser)->getNamespaceForVersion() === $namespaceFromUser) {
                    return $this->redirectToRoute('class_show_with_version', [
                        'id' => $class->getId(),
                        'namespaceFromUrlId' => $class->getClassVersionForDisplay($namespaceFromUser)->getNamespaceForVersion()->getId()
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
            $isCompatible = true;
            $nsUser = $em->getRepository('AppBundle:OntoNamespace')->findOneBy(array('id' => $namespaceIdFromUser));
            $nsRootUser = $nsUser->getTopLevelNamespace();
            if(in_array($nsRootUser, $rootNamespacesFromClassVersion) and !in_array($nsUser->getId(), $namespacesIdFromClassVersion)){
                $isCompatible = false;
            }
            foreach ($nsUser->getAllReferencedNamespaces() as $referencedNamespace){
                if(in_array($referencedNamespace->getTopLevelNamespace(), $rootNamespacesFromClassVersion) and !in_array($referencedNamespace->getId(), $namespacesIdFromClassVersion)){
                    $isCompatible = false;
                }
            }
            if($isCompatible){
                $nsIdFromUser[] = $namespaceIdFromUser;
            }
        }

        // $namespacesId : Tous les namespaces trouvés ci-dessus
        $namespacesId = array_merge($namespacesIdFromClassVersion, $nsIdFromUser);

        $ancestors = array();
        $descendants = array();
        $relations = array();

        $outgoingProperties = array();
        $outgoingInheritedProperties = array();
        $ingoingProperties = array();
        $ingoingInheritedProperties = array();
        $error = '';
        $isE55Descendant = false;

        try {
            $ancestors = $em->getRepository('AppBundle:OntoClass')->findAncestorsByClassVersionAndNamespacesId($classVersion, $namespacesId);
            $descendants = $em->getRepository('AppBundle:OntoClass')->findDescendantsByClassVersionAndNamespacesId($classVersion, $namespacesId);
            $relations = $em->getRepository('AppBundle:OntoClass')->findRelationsByClassVersionAndNamespacesId($classVersion, $namespacesId);

            $outgoingProperties = $em->getRepository('AppBundle:property')->findOutgoingPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
            $outgoingInheritedProperties = $em->getRepository('AppBundle:property')->findOutgoingInheritedPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
            $ingoingProperties = $em->getRepository('AppBundle:property')->findIngoingPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);
            $ingoingInheritedProperties =  $em->getRepository('AppBundle:property')->findIngoingInheritedPropertiesByClassVersionAndNamespacesId($classVersion, $namespacesId);

            $isE55Descendant = $em->getRepository('AppBundle:OntoClass')->findE55ChildClasses($class->getId());
        }
        catch (DBALException $e) {
            $error = $e->getMessage();
        }

        //Tri
        // En tête, les relations appartenant à la même version que cette classe
        // Puis par identifiant / label
        // Ensuite, les autres versions
        // Puis par Préfixe, identifiant / label
        function sortRelationsByClasses($a, $b, OntoNamespace $version, $type, $class=null){
            if($type == 'childClassAssociations'){
                $classA = $a->getParentClass();
                $classB = $b->getParentClass();
                $classNamespaceA = $a->getParentClassNamespace();
                $classNamespaceB = $b->getParentClassNamespace();
            }
            if($type == 'parentClassAssociations'){
                $classA = $a->getChildClass();
                $classB = $b->getChildClass();
                $classNamespaceA = $a->getChildClassNamespace();
                $classNamespaceB = $b->getChildClassNamespace();
            }
            if($type == 'entityAssociations'){
                if($a->getSystemType()->getId() > $b->getSystemType()->getId()){
                    return 1;
                }
                elseif($a->getSystemType()->getId() < $b->getSystemType()->getId()){
                    return -1;
                }
                else{
                    if($class == $a->getSourceClass()){
                        $classA = $a->getTargetClass();
                        $classNamespaceA = $a->getTargetNamespaceForVersion();
                    }
                    else{
                        $classA = $a->getSourceClass();
                        $classNamespaceA = $a->getSourceNamespaceForVersion();
                    }

                    if($class == $b->getSourceClass()){
                        $classB = $b->getTargetClass();
                        $classNamespaceB = $b->getTargetNamespaceForVersion();
                    }
                    else{
                        $classB = $b->getSourceClass();
                        $classNamespaceB =$b->getSourceNamespaceForVersion();
                    }
                }
            }

            if($classNamespaceA === $version && $classNamespaceB !== $version){
                return -1;
            }
            elseif($classNamespaceB === $version && $classNamespaceA !== $version){
                return 1;
            }
            else{
                $prefixA = $classNamespaceA->getTopLevelNamespace()->getRootNamespacePrefix();
                $prefixB = $classNamespaceB->getTopLevelNamespace()->getRootNamespacePrefix();
                $identifierInNamespaceA =  $classA->getIdentifierInNamespace();
                $identifierInNamespaceB =  $classB->getIdentifierInNamespace();

                if($prefixA == $prefixB){
                    if(strlen($identifierInNamespaceA) == strlen($identifierInNamespaceB)){
                        return strcmp($identifierInNamespaceA, $identifierInNamespaceB);
                    }
                    elseif(strlen($identifierInNamespaceA) > strlen($identifierInNamespaceB)){
                        return 1;
                    }
                    elseif(strlen($identifierInNamespaceA) < strlen($identifierInNamespaceB)){
                        return -1;
                    }
                }
                else{
                    return strcmp($prefixA, $prefixB);
                }
            }
        }

        // -- Associations Subclass of
        $childClassAssociations = $classVersion->getClass()->getChildClassAssociations()
            ->filter(function($v) use ($classVersion, $namespacesId){
                return $classVersion->getNamespaceForVersion() == $v->getNamespaceForVersion() || in_array($v->getNamespaceForVersion()->getId(), $namespacesId);
            });
        $iterator = $childClassAssociations->getIterator();
        $iterator->uasort(function($a, $b) use ($classVersion){
            return sortRelationsByClasses($a, $b, $classVersion->getNamespaceForVersion(), 'childClassAssociations');
        });
        $childClassAssociations = new ArrayCollection(iterator_to_array($iterator));

        // -- Associations Superclass of
        $parentClassAssociations = $classVersion->getClass()->getParentClassAssociations()
            ->filter(function($v) use ($classVersion, $namespacesId){
                return $classVersion->getNamespaceForVersion() == $v->getNamespaceForVersion() || in_array($v->getNamespaceForVersion()->getId(), $namespacesId);
            });
        $iterator = $parentClassAssociations->getIterator();
        $iterator->uasort(function($a, $b) use ($classVersion){
            return sortRelationsByClasses($a, $b, $classVersion->getNamespaceForVersion(), 'parentClassAssociations');
        });
        $parentClassAssociations = new ArrayCollection(iterator_to_array($iterator));

        // -- Relations
        $entityAssociations = $classVersion->getClass()->getEntityAssociations()
            ->filter(function($v) use ($classVersion, $namespacesId){
            return $classVersion->getNamespaceForVersion() == $v->getNamespaceForVersion() || in_array($v->getNamespaceForVersion()->getId(), $namespacesId);
        });
        $iterator = $entityAssociations->getIterator();
        $iterator->uasort(function($a, $b) use ($classVersion){
            return sortRelationsByClasses($a, $b, $classVersion->getNamespaceForVersion(), 'entityAssociations', $classVersion->getClass());
        });
        $entityAssociations = new ArrayCollection(iterator_to_array($iterator));

        return $this->render('class/show.html.twig', array(
            'classVersion' => $classVersion,
            'childClassAssociations' => $childClassAssociations,
            'parentClassAssociations' => $parentClassAssociations,
            'entityAssociations' => $entityAssociations,
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
            'isE55Descendant' => $isE55Descendant,
            'error' => $error
        ));
    }

    /**
     * @Route("/class/{id}/edit", name="class_edit", requirements={"id"="^[0-9]+$"})
     * @param OntoClass $class
     * @param Request $request
     * @return Response the rendered template
     * @throws DBALException
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

        $isE55Descendant = $em->getRepository('AppBundle:OntoClass')->findE55ChildClasses($class->getId());

        $this->denyAccessUnlessGranted('edit', $classVersion);

        $classTemp = clone $class;

        $formIdentifier = $this->createForm(ClassEditIdentifierForm::class, $classTemp);
        $formIdentifier->handleRequest($request);
        if ($formIdentifier->isSubmitted() && $formIdentifier->isValid()) {
            $class->setIdentifierInNamespace($classTemp->getIdentifierInNamespace());
            if(!$classVersion->getNamespaceForVersion()->getTopLevelNamespace()->getIsExternalNamespace()){
                $class->setIdentifierInURI($class->getIdentifierInNamespace()); // On attribue le même identifiant si namespace interne
            }
            else{
                $class->updateIdentifierInUri();
            }

            $em->persist($class);
            $em->flush();

            $this->addFlash('success', 'Class updated!');
            return $this->redirectToRoute('class_edit', [
                'id' => $class->getId(),
                '_fragment' => 'identification'
            ]);
        }

        $formUriIdentifier = $this->createForm(ClassEditUriIdentifierForm::class, $class);
        $formUriIdentifier->handleRequest($request);
        if ($formUriIdentifier->isSubmitted() && $formUriIdentifier->isValid()) {
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

        //Tri
        // En tête, les relations appartenant à la même version que cette classe
        // Puis par identifiant / label
        // Ensuite, les autres versions
        // Puis par Préfixe, identifiant / label
        function sortRelationsByClasses($a, $b, OntoNamespace $version, $type, $class=null){
            if($type == 'childClassAssociations'){
                $classA = $a->getParentClass();
                $classB = $b->getParentClass();
                $classNamespaceA = $a->getParentClassNamespace();
                $classNamespaceB = $b->getParentClassNamespace();
            }
            if($type == 'parentClassAssociations'){
                $classA = $a->getChildClass();
                $classB = $b->getChildClass();
                $classNamespaceA = $a->getChildClassNamespace();
                $classNamespaceB = $b->getChildClassNamespace();
            }
            if($type == 'entityAssociations'){
                if($a->getSystemType()->getId() > $b->getSystemType()->getId()){
                    return 1;
                }
                elseif($a->getSystemType()->getId() < $b->getSystemType()->getId()){
                    return -1;
                }
                else{
                    if($class == $a->getSourceClass()){
                        $classA = $a->getTargetClass();
                        $classNamespaceA = $a->getTargetNamespaceForVersion();
                    }
                    else{
                        $classA = $a->getSourceClass();
                        $classNamespaceA = $a->getSourceNamespaceForVersion();
                    }

                    if($class == $b->getSourceClass()){
                        $classB = $b->getTargetClass();
                        $classNamespaceB = $b->getTargetNamespaceForVersion();
                    }
                    else{
                        $classB = $b->getSourceClass();
                        $classNamespaceB =$b->getSourceNamespaceForVersion();
                    }
                }
            }

            if($classNamespaceA === $version && $classNamespaceB !== $version){
                return -1;
            }
            elseif($classNamespaceB === $version && $classNamespaceA !== $version){
                return 1;
            }
            else{
                $prefixA = $classNamespaceA->getTopLevelNamespace()->getRootNamespacePrefix();
                $prefixB = $classNamespaceB->getTopLevelNamespace()->getRootNamespacePrefix();
                $identifierInNamespaceA =  $classA->getIdentifierInNamespace();
                $identifierInNamespaceB =  $classB->getIdentifierInNamespace();

                if($prefixA == $prefixB){
                    if(strlen($identifierInNamespaceA) == strlen($identifierInNamespaceB)){
                        return strcmp($identifierInNamespaceA, $identifierInNamespaceB);
                    }
                    elseif(strlen($identifierInNamespaceA) > strlen($identifierInNamespaceB)){
                        return 1;
                    }
                    elseif(strlen($identifierInNamespaceA) < strlen($identifierInNamespaceB)){
                        return -1;
                    }
                }
                else{
                    return strcmp($prefixA, $prefixB);
                }
            }
        }

        // -- Associations Subclass of
        $childClassAssociations = $classVersion->getClass()->getChildClassAssociations()
            ->filter(function($v) use ($classVersion, $namespacesId){
                return $classVersion->getNamespaceForVersion() == $v->getNamespaceForVersion() || in_array($v->getNamespaceForVersion()->getId(), $namespacesId);
            });
        $iterator = $childClassAssociations->getIterator();
        $iterator->uasort(function($a, $b) use ($classVersion){
            return sortRelationsByClasses($a, $b, $classVersion->getNamespaceForVersion(), 'childClassAssociations');
        });
        $childClassAssociations = new ArrayCollection(iterator_to_array($iterator));

        // -- Associations Superclass of
        $parentClassAssociations = $classVersion->getClass()->getParentClassAssociations()
            ->filter(function($v) use ($classVersion, $namespacesId){
                return $classVersion->getNamespaceForVersion() == $v->getNamespaceForVersion() || in_array($v->getNamespaceForVersion()->getId(), $namespacesId);
            });
        $iterator = $parentClassAssociations->getIterator();
        $iterator->uasort(function($a, $b) use ($classVersion){
            return sortRelationsByClasses($a, $b, $classVersion->getNamespaceForVersion(), 'parentClassAssociations');
        });
        $parentClassAssociations = new ArrayCollection(iterator_to_array($iterator));

        // -- Relations
        $entityAssociations = $classVersion->getClass()->getEntityAssociations()
            ->filter(function($v) use ($classVersion, $namespacesId){
                return $classVersion->getNamespaceForVersion() == $v->getNamespaceForVersion() || in_array($v->getNamespaceForVersion()->getId(), $namespacesId);
            });
        $iterator = $entityAssociations->getIterator();
        $iterator->uasort(function($a, $b) use ($classVersion){
            return sortRelationsByClasses($a, $b, $classVersion->getNamespaceForVersion(), 'entityAssociations', $classVersion->getClass());
        });
        $entityAssociations = new ArrayCollection(iterator_to_array($iterator));


        //If validation status is in validation request or is validation, we can't allow edition of the entity and we rended the show template
        if (!is_null($classVersion->getValidationStatus()) && ($classVersion->getValidationStatus()->getId() === 26 || $classVersion->getValidationStatus()->getId() === 28)) {
            return $this->render('class/show.html.twig', [
                'classVersion' => $classVersion,
                'childClassAssociations' => $childClassAssociations,
                'parentClassAssociations' => $parentClassAssociations,
                'entityAssociations' => $entityAssociations,
                'ancestors' => $ancestors,
                'descendants' => $descendants,
                'relations' => $relations,
                'outgoingProperties' => $outgoingProperties,
                'outgoingInheritedProperties' => $outgoingInheritedProperties,
                'ingoingProperties' => $ingoingProperties,
                'ingoingInheritedProperties' => $ingoingInheritedProperties,
                'isE55Descendant' => $isE55Descendant,
                'namespacesId' => $namespacesId
            ]);
        }

        return $this->render('class/edit.html.twig', array(
            'classVersion' => $classVersion,
            'childClassAssociations' => $childClassAssociations,
            'parentClassAssociations' => $parentClassAssociations,
            'entityAssociations' => $entityAssociations,
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'relations' => $relations,
            'outgoingProperties' => $outgoingProperties,
            'outgoingInheritedProperties' => $outgoingInheritedProperties,
            'ingoingProperties' => $ingoingProperties,
            'ingoingInheritedProperties' => $ingoingInheritedProperties,
            'isE55Descendant' => $isE55Descendant,
            'namespacesId' => $namespacesId,
            'namespacesIdFromClassVersion' => $namespacesIdFromClassVersion,
            'namespacesIdFromUser' => $namespacesIdFromUser,
            'classIdentifierForm' => $formIdentifier->createView(),
            'classUriIdentifierForm' => $formUriIdentifier->createView()
        ));
    }

    /**
     * @Route("/class-version/{id}/edit-validity/{validationStatus}", name="class_version_validation_status_edit", requirements={"id"="^[0-9]+$", "validationStatus"="^26|27|28|37$"})
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

        $txtpValides = $classVersion->getClass()->getTextProperties()->filter(function($t)use($classVersion){
            return $t->getSystemType()->getId() == 1
                && $t->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                && !is_null($t->getValidationStatus())
                && $t->getValidationStatus()->getId() == 26;
        });
        $lblValides = $classVersion->getClass()->getLabels()->filter(function($l)use($classVersion){
            return $l->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                && !is_null($l->getValidationStatus())
                && $l->getValidationStatus()->getId() == 26;
        });

        $isMinimumRequirementReached = $txtpValides->count() > 0 && $lblValides->count() > 0;

        try{
            $em = $this->getDoctrine()->getManager();
            $newValidationStatus = $em->getRepository('AppBundle:SystemType')
                ->findOneBy(array('id' => $validationStatus->getId()));
        } catch (\Exception $e) {
            throw new BadRequestHttpException('The provided status does not exist.');
        }

        if (!is_null($newValidationStatus)) {
            $statusId = intval($newValidationStatus->getId());
            if (in_array($statusId, [26,27,28,37], true)) {
                $classVersion->setValidationStatus($newValidationStatus);
                $classVersion->setModifier($this->getUser());
                $classVersion->setModificationTime(new \DateTime('now'));

                // Validation request && minimum not reached => all related candidate entities (txtp, lbl) must be "validation request"
                if($statusId == 28 && !$isMinimumRequirementReached){
                    $txtpCandidates = $classVersion->getClass()->getTextProperties()->filter(function($t)use($classVersion){
                        return $t->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                            && is_null($t->getValidationStatus());
                    });
                    $lblCandidates = $classVersion->getClass()->getLabels()->filter(function($l)use($classVersion){
                        return $l->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                            && is_null($l->getValidationStatus());
                    });
                    $childClassAssociationCandidates = $classVersion->getClass()->getChildClassAssociations()->filter(function($c)use($classVersion){
                        return $c->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                            && is_null($c->getValidationStatus());
                    });
                    $sourceEntityAssociationCandidates = $classVersion->getClass()->getSourceEntityAssociations()->filter(function($c)use($classVersion){
                        return $c->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                            && is_null($c->getValidationStatus());
                    });
                    $targetEntityAssociationCandidates = $classVersion->getClass()->getTargetEntityAssociations()->filter(function($c)use($classVersion){
                        return $c->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                            && is_null($c->getValidationStatus());
                    });

                    $allEntitiesCandidates = array(
                        $txtpCandidates,
                        $lblCandidates,
                        $childClassAssociationCandidates,
                        $sourceEntityAssociationCandidates,
                        $targetEntityAssociationCandidates
                    );

                    foreach ($allEntitiesCandidates as $entitiesCandidates){
                        foreach ($entitiesCandidates as $entitieCandidate){
                            $entitieCandidate->setValidationStatus($newValidationStatus);
                            $entitieCandidate->setModifier($this->getUser());
                            $entitieCandidate->setModificationTime(new \DateTime('now'));
                            $em->persist($entitieCandidate);
                        }
                    }
                }

                // On met une classe en Denied: Ses textproperties et labels doivent être en "Under revision".
                // Les relations à cette classe seront dévalidés dans l'if suivant
                if($statusId == 27){
                    $validatedTextProperties = $classVersion->getClass()->getTextProperties()->filter(function($t)use($classVersion){
                        return $t->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                            && !is_null($t->getValidationStatus())
                            && $t->getValidationStatus()->getId() == 26;
                    });
                    $validatedLabels = $classVersion->getClass()->getLabels()->filter(function($l)use($classVersion){
                        return $l->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                            && !is_null($l->getValidationStatus())
                            && $l->getValidationStatus()->getId() == 26;
                    });

                    $allValidatedEntities = array(
                        $validatedTextProperties,
                        $validatedLabels
                    );

                    $underRevisionStatus = $em->getRepository('AppBundle:SystemType')->findOneBy(array('id' => 37));

                    foreach ($allValidatedEntities as $validatedEntities){
                        foreach ($validatedEntities as $entityValidated){
                            $entityValidated->setValidationStatus($underRevisionStatus);
                            $entityValidated->setModifier($this->getUser());
                            $entityValidated->setModificationTime(new \DateTime('now'));
                            $em->persist($entityValidated);
                        }
                    }
                }

                // Dé-validation d'une classe: on dévalide les relations à cette classe dans le même namespace
                if(in_array($statusId, [27, 28, 37])){
                    $validatedChildClassAssociations = $classVersion->getClass()->getChildClassAssociations()->filter(function($c)use($classVersion){
                        return $c->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                            && !is_null($c->getValidationStatus())
                            && $c->getValidationStatus()->getId() == 26;
                    });
                    $validatedParentClassAssociations = $classVersion->getClass()->getParentClassAssociations()->filter(function($c)use($classVersion){
                        return $c->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                            && !is_null($c->getValidationStatus())
                            && $c->getValidationStatus()->getId() == 26;
                    });
                    $validatedSourceEntityAssociations = $classVersion->getClass()->getSourceEntityAssociations()->filter(function($c)use($classVersion){
                        return $c->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                            && !is_null($c->getValidationStatus())
                            && $c->getValidationStatus()->getId() == 26;
                    });
                    $validatedTargetEntityAssociations = $classVersion->getClass()->getTargetEntityAssociations()->filter(function($c)use($classVersion){
                        return $c->getNamespaceForVersion() == $classVersion->getNamespaceForVersion()
                            && !is_null($c->getValidationStatus())
                            && $c->getValidationStatus()->getId() == 26;
                    });
                    // Retrouver les propriétés validés ayant cette classe en domain ou range
                    $validatedProperties = $classVersion->getNamespaceForVersion()->getPropertyVersions()->filter(function($p)use($classVersion){
                        return ($p->getDomain() == $classVersion->getClass() || $p->getRange() == $classVersion->getClass())
                            && !is_null($p->getValidationStatus())
                            && $p->getValidationStatus()->getId() == 26;
                    });

                    $allValidatedEntities = array(
                        $validatedChildClassAssociations,
                        $validatedParentClassAssociations,
                        $validatedSourceEntityAssociations,
                        $validatedTargetEntityAssociations,
                        $validatedProperties
                    );

                    // Retrouver toutes les relations validées de ces propriétés validées de la classe
                    foreach($validatedProperties as $validatedPropertyVersion) {
                        $validatedChildPropertyAssociations = $validatedPropertyVersion->getProperty()->getChildPropertyAssociations()->filter(function ($c) use ($validatedPropertyVersion) {
                            return $c->getNamespaceForVersion() == $validatedPropertyVersion->getNamespaceForVersion()
                                && !is_null($c->getValidationStatus())
                                && $c->getValidationStatus()->getId() == 26;
                        });
                        $validatedParentPropertyAssociations = $validatedPropertyVersion->getProperty()->getParentPropertyAssociations()->filter(function ($c) use ($validatedPropertyVersion) {
                            return $c->getNamespaceForVersion() == $validatedPropertyVersion->getNamespaceForVersion()
                                && !is_null($c->getValidationStatus())
                                && $c->getValidationStatus()->getId() == 26;
                        });
                        $validatedSourceEntityAssociations = $validatedPropertyVersion->getProperty()->getSourceEntityAssociations()->filter(function ($c) use ($validatedPropertyVersion) {
                            return $c->getNamespaceForVersion() == $validatedPropertyVersion->getNamespaceForVersion()
                                && !is_null($c->getValidationStatus())
                                && $c->getValidationStatus()->getId() == 26;
                        });
                        $validatedTargetEntityAssociations = $validatedPropertyVersion->getProperty()->getTargetEntityAssociations()->filter(function ($c) use ($validatedPropertyVersion) {
                            return $c->getNamespaceForVersion() == $validatedPropertyVersion->getNamespaceForVersion()
                                && !is_null($c->getValidationStatus())
                                && $c->getValidationStatus()->getId() == 26;
                        });

                        $allValidatedEntities = array_merge($allValidatedEntities, array(
                                $validatedChildPropertyAssociations,
                                $validatedParentPropertyAssociations,
                                $validatedSourceEntityAssociations,
                                $validatedTargetEntityAssociations)
                        );
                    }

                    if(!empty($allValidatedEntities)){
                        $this->addFlash('warning', 'The devalidation of this class has led to the devalidation of one or more relations or entities linked to this entity.');
                    }

                    $underRevisionStatus = $em->getRepository('AppBundle:SystemType')->findOneBy(array('id' => 37));
                    foreach ($allValidatedEntities as $validatedEntities){
                        foreach ($validatedEntities as $entityValidated){
                            $entityValidated->setValidationStatus($underRevisionStatus);
                            $entityValidated->setModifier($this->getUser());
                            $entityValidated->setModificationTime(new \DateTime('now'));
                            $em->persist($entityValidated);
                        }
                    }
                }

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
     * @Route("/class/{class}/{namespace}/graph/json", name="class_graph_json", requirements={"class"="^[0-9]+$"})
     * @Method("GET")
     * @param OntoClass $class
     * @param OntoNamespace $namespace
     * @return JsonResponse a Json formatted tree representation of OntoClasses
     */
    public function getGraphJson(OntoClass $class, OntoNamespace $namespace)
    {
        $em = $this->getDoctrine()->getManager();
        $classes = $em->getRepository('AppBundle:OntoClass')
            ->findClassesGraphById($class, $namespace);

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
