<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 28/06/2017
 * Time: 15:50
 */

namespace AppBundle\Controller;

use AppBundle\Entity\EntityUserProjectAssociation;
use AppBundle\Entity\Label;
use AppBundle\Entity\OntoClassVersion;
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\PropertyVersion;
use AppBundle\Entity\ReferencedNamespaceAssociation;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\NamespaceForm;
use AppBundle\Form\NamespacePublicationForm;
use AppBundle\Form\NamespaceQuickAddForm;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\VerticalJc;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\TemplateProcessor;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class NamespaceController  extends Controller
{
    /**
     * @Route("/namespace")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $namespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findAll();
        //->findAllOrderedById();

        return $this->render('namespace/list.html.twig', [
            'namespaces' => $namespaces
        ]);
    }

    /**
     * @Route("/namespace/new/{project}", name="namespace_new")
     */
    public function newNamespaceAction(Project $project, Request $request)
    {

        $tokenInterface = $this->get('security.token_storage')->getToken();
        $isAuthenticated = $tokenInterface->isAuthenticated();
        if(!$isAuthenticated) throw new AccessDeniedException('You must be an authenticated user to access this page.');

        $this->denyAccessUnlessGranted('edit_manager', $project);

        if (!$project->getManagedNamespaces()->isEmpty()) {
            throw new AccessDeniedException('You cannot add a new namespace to this project because it already has a managed namespace.');
        }

        $namespace = new OntoNamespace();

        $em = $this->getDoctrine()->getManager();
        $systemTypeDescription = $em->getRepository('AppBundle:SystemType')->find(16); //systemType 16 = Description

        $description = new TextProperty();
        $description->setNamespace($namespace);
        $description->setSystemType($systemTypeDescription);
        $description->setCreator($this->getUser());
        $description->setModifier($this->getUser());
        $description->setCreationTime(new \DateTime('now'));
        $description->setModificationTime(new \DateTime('now'));

        $ongoingDescription = new TextProperty();
        $ongoingDescription->setNamespace($namespace);
        $ongoingDescription->setSystemType($systemTypeDescription);
        $ongoingDescription->setCreator($this->getUser());
        $ongoingDescription->setModifier($this->getUser());
        $ongoingDescription->setCreationTime(new \DateTime('now'));
        $ongoingDescription->setModificationTime(new \DateTime('now'));

        $namespace->addTextProperty($description);

        $ongoingNamespace = new OntoNamespace();
        $namespaceLabel = new Label();
        $ongoingNamespaceLabel = new Label();

        $now = new \DateTime();

        $namespace->setCreator($this->getUser());
        $namespace->setModifier($this->getUser());

        $namespaceLabel->setIsStandardLabelForLanguage(true);
        $namespaceLabel->setCreator($this->getUser());
        $namespaceLabel->setModifier($this->getUser());
        $namespaceLabel->setCreationTime(new \DateTime('now'));
        $namespaceLabel->setModificationTime(new \DateTime('now'));

        $namespace->addLabel($namespaceLabel);

        $form = $this->createForm(NamespaceQuickAddForm::class, $namespace);
        // only handles data on POST
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $namespace = $form->getData();
            $namespace->setProjectForTopLevelNamespace($project);
            $namespace->setTopLevelNamespace($namespace);
            $namespace->setCreator($this->getUser());
            $namespace->setModifier($this->getUser());
            $namespace->setCreationTime(new \DateTime('now'));
            $namespace->setModificationTime(new \DateTime('now'));
            $namespace->setIsTopLevelNamespace(true);
            $namespace->setIsOngoing(false);

            //just in case, we set the domain to ontome.net for non external namespaces
            if (!$namespace->getIsExternalNamespace() && strpos($namespace->getNamespaceURI(), 'https://ontome.net/ns') !== 0 ) {
                $u = parse_url($namespace->getNamespaceURI());
                $uri = 'https://ontome.net/ns'.$u['path']; //if the user tries to change the domain, we force it to be ontome.net
                $namespace->setNamespaceURI($uri);
            }


            $ongoingNamespace->setNamespaceURI($namespace->getNamespaceURI());
            $ongoingNamespace->setIsExternalNamespace($namespace->getIsExternalNamespace());
            $ongoingNamespace->setIsTopLevelNamespace(false);
            $ongoingNamespace->setIsOngoing(true);
            $ongoingNamespace->setTopLevelNamespace($namespace);
            $ongoingNamespace->setProjectForTopLevelNamespace($project);
            $ongoingNamespace->setReferencedVersion($namespace);
            $ongoingNamespace->setCreator($this->getUser());
            $ongoingNamespace->setModifier($this->getUser());
            $ongoingNamespace->setCreationTime(new \DateTime('now'));
            $ongoingNamespace->setModificationTime(new \DateTime('now'));

            $ongoingNamespaceLabel->setIsStandardLabelForLanguage(true);
            $ongoingNamespaceLabel->setLabel($namespaceLabel->getLabel());
            $ongoingNamespaceLabel->setLanguageIsoCode($namespaceLabel->getLanguageIsoCode());
            $ongoingNamespaceLabel->setCreator($this->getUser());
            $ongoingNamespaceLabel->setModifier($this->getUser());
            $ongoingNamespaceLabel->setCreationTime(new \DateTime('now'));
            $ongoingNamespaceLabel->setModificationTime(new \DateTime('now'));
            $ongoingNamespace->addLabel($ongoingNamespaceLabel);

            if($namespace->getTextProperties()->containsKey(1)){
                $namespace->getTextProperties()[1]->setCreationTime(new \DateTime('now'));
                $namespace->getTextProperties()[1]->setModificationTime(new \DateTime('now'));
                $namespace->getTextProperties()[1]->setSystemType($systemTypeDescription);
                $namespace->getTextProperties()[1]->setNamespace($ongoingNamespace);
            }
            else {
                $ongoingDescription = $description;
                $ongoingNamespace->addTextProperty($ongoingDescription);
            }

            // Créer les entity_to_user_project pour les activer par défaut
            $userProjectAssociations = $em->getRepository('AppBundle:UserProjectAssociation')->findByProject($project);
            foreach ($userProjectAssociations as $userProjectAssociation) {
                $eupa = new EntityUserProjectAssociation();
                $systemTypeSelected = $em->getRepository('AppBundle:SystemType')->find(25); //systemType 25 = Selected namespace for user preference
                $eupa->setNamespace($ongoingNamespace);
                $eupa->setUserProjectAssociation($userProjectAssociation);
                $eupa->setSystemType($systemTypeSelected);
                $eupa->setCreator($this->getUser());
                $eupa->setModifier($this->getUser());
                $eupa->setCreationTime(new \DateTime('now'));
                $eupa->setModificationTime(new \DateTime('now'));
                $em->persist($eupa);
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($namespace);
            $em->persist($ongoingNamespace);
            $em->flush();


            return $this->redirectToRoute('namespace_edit', [
                'id' =>$ongoingNamespace->getId()
            ]);

        }

        $em = $this->getDoctrine()->getManager();


        return $this->render('namespace/new.html.twig', [
            'namespace' => $namespace,
            'namespaceForm' => $form->createView()
        ]);
    }

    /**
     * @Route("/namespace/{id}", name="namespace_show")
     * @param OntoNamespace $namespace
     * @return Response the rendered template
     */
    public function showAction(OntoNamespace $namespace)
    {
        $em = $this->getDoctrine()->getManager();

        return $this->render('namespace/show.html.twig', array(
            'namespace' => $namespace
        ));
    }

    /**
     * @Route("/namespace/{id}/edit", name="namespace_edit")
     * @param string $namespace
     * @return Response the rendered template
     */
    public function editAction(OntoNamespace $namespace, Request $request)
    {
        if(is_null($namespace)) {
            throw $this->createNotFoundException('The namespace n° '.$namespace->getId().' does not exist. Please contact an administrator.');
        }

        $this->denyAccessUnlessGranted('edit', $namespace);

        $namespace->setModifier($this->getUser());

        $em = $this->getDoctrine()->getManager();

        $rootNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findAllNonAssociatedToNamespaceByNamespaceId($namespace);

        if($this->isGranted('full_edit', $namespace)) {

            $ongoingNamespaceHasChanged = $em->getRepository('AppBundle:OntoNamespace')
                ->checkNamespaceChange($namespace);

            $form = $this->createForm(NamespaceForm::class, $namespace);

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                if (!$namespace->getIsExternalNamespace() && strpos($namespace->getNamespaceURI(), 'https://ontome.net/ns') !== 0 ) {
                    $u = parse_url($namespace->getNamespaceURI());
                    $uri = 'https://ontome.net/ns'.$u['path']; //if the user tries to change the domain, we force it to be ontome.net
                    $namespace->setNamespaceURI($uri);
                }
                $namespace->setModifier($this->getUser());
                $em->persist($namespace);

                //update URI and isExternalNamespace values for child namespaces

                if ($namespace->getIsTopLevelNamespace()) {
                    foreach ($namespace->getChildVersions() as $childNamespace) {
                        if (!$namespace->getIsExternalNamespace()) {
                            $childNamespace->setNamespaceURI(null);
                        }
                        $childNamespace->setIsExternalNamespace($namespace->getIsExternalNamespace());
                        $em->persist($namespace);
                    }
                }

                $em->flush();
                $this->addFlash('success', 'Namespace Updated!');

                return $this->redirectToRoute('namespace_edit', [
                    'id' => $namespace->getId()
                ]);
            }
            return $this->render('namespace/edit.html.twig', [
                'namespaceForm' => $form->createView(),
                'namespace' => $namespace,
                'rootNamespaces' => $rootNamespaces,
                'hasChanged' => $ongoingNamespaceHasChanged,
            ]);
        }
        else {
            return $this->render('namespace/edit.html.twig', [
                'namespaceForm' => null,
                'namespace' => $namespace,
                'rootNamespaces' => $rootNamespaces,
                'hasChanged' => null,
            ]);
        }
    }

    /**
     * @Route("/namespace/{id}/publish", name="namespace_publication")
     * @param OntoNamespace $namespace
     * @return Response the rendered template
     */
    public function publishAction(OntoNamespace $namespace, Request $request)
    {
        if(is_null($namespace)) {
            throw $this->createNotFoundException('The namespace n° '.$namespace->getId().' does not exist. Please contact an administrator.');
        }

        $this->denyAccessUnlessGranted('publish', $namespace);

        return $this->render('namespace/publish.html.twig', array(
            'namespace' => $namespace
        ));
    }

    /**
     * @Route("/namespace/{id}/validate-publication", name="namespace_validate_publication")
     * @param OntoNamespace $namespace
     * @return Response the rendered template
     */
    public function validatePublicationAction(OntoNamespace $namespace, Request $request)
    {
        if(is_null($namespace)) {
            throw $this->createNotFoundException('The namespace n° '.$namespace->getId().' does not exist. Please contact an administrator.');
        }

        $this->denyAccessUnlessGranted('publish', $namespace);

        $namespace->setModifier($this->getUser());
        if ($namespace->getTopLevelNamespace()->getIsExternalNamespace()) {
            $namespace->setNamespaceURI(str_replace('-ongoing', '',$namespace->getNamespaceURI()));
        }

        $em = $this->getDoctrine()->getManager();

        $newNamespaceId = $em->getRepository('AppBundle:OntoNamespace')
            ->publishNamespace($namespace);

        $newNamespace = $em->getRepository('AppBundle:OntoNamespace')->findOneBy(['id'=>$newNamespaceId]);

        $newNamespaceLabel = new Label();
        $newNamespaceLabel->setIsStandardLabelForLanguage(true);
        $newNamespaceLabel->setCreator($this->getUser());
        $newNamespaceLabel->setModifier($this->getUser());
        $newNamespaceLabel->setCreationTime(new \DateTime('now'));
        $newNamespaceLabel->setModificationTime(new \DateTime('now'));
        $newNamespaceLabel->setLabel(str_replace(' ongoing','', $namespace->getStandardLabel()));

        $newNamespace->addLabel($newNamespaceLabel);
        $newNamespace->setStandardLabel($newNamespaceLabel->getLabel());
        $newNamespace->setIsExternalNamespace($namespace->getIsExternalNamespace());

        $em->persist($namespace);
        $em->persist($newNamespace);
        $em->flush();

        return $this->redirectToRoute('namespace_show', [
            'id' => $newNamespaceId
        ]);
    }

    /**
     * @Route("/namespace/root-namespace/{id}/json", name="namespaces_by_root_id_list_json")
     * @Method("GET")
     * @param OntoNamespace  $rootNamespace    The root namespace
     * @return JsonResponse a Json formatted namespaces list
     */
    public function getNamespacesByRootNamespaceID(OntoNamespace $rootNamespace)
    {
        $namespaces = [];

        if($rootNamespace->getIsTopLevelNamespace()) {
            $status = 'Success';
            $message = 'This namespace is valid';
            foreach ($rootNamespace->getChildVersions() as $namespace) {
                $namespaces[] = [
                    'id' => $namespace->getId(),
                    'standardLabel' => $namespace->getStandardLabel()
                ];
            }
        }

        else {
            $status = 'Error';
            $message = 'This namespace is not a top level namespace';
        }



        $response = array(
            'status' => $status,
            'message' => $message,
            'namespaces' => $namespaces
        );

        return new JsonResponse($response);
    }

    /**
     * @Route("/namespace/profile/{id}/json", name="root_namespaces_list_for_profile_json")
     * @Method("GET")
     * @param Profile  $profile    The profile to be associated with a namespace
     * @return JsonResponse a Json formatted namespaces list
     */
    public function getRootNamespacesForAssociationWithProfile(Profile $profile)
    {
        $em = $this->getDoctrine()->getManager();
        $rootNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findAllNonAssociatedToProfileByProfileId($profile);

        if(!is_null($rootNamespaces)) {
            $status = 'Success';
            $message = 'Root namespaces list retrieved';
        }
        else {
            $status = 'Error';
            $message = 'This profile cannot be associated with another namespace';
        }

        $response = array(
            'status' => $status,
            'message' => $message,
            'namespaces' => $rootNamespaces
        );

        return new JsonResponse($response);
    }



    /**
     * @Route("/namespace/{id}/json", name="namespace_json", schemes={"http"})
     * @Method("GET")
     * @param OntoNamespace $namespace
     * @return JsonResponse a Json formatted graph representation of Namespaces
     */
    public function getGraphJson(OntoNamespace $namespace)
    {
        $em = $this->getDoctrine()->getManager();
        $namespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findNamespacesGraph($namespace);

        return new JsonResponse($namespaces[0]['json'],200, array(), true);
    }

    /**
     * @Route("/namespace/{namespace}/referenced-namespace/{referencedNamespace}/add", name="namespace_referenced_namespace_association")
     * @Method({ "POST"})
     * @param OntoNamespace  $namespace    The namespace to be associated with a referenced namespace
     * @param OntoNamespace  $referencedNamespace    The referenced namespace to be associated with a namespace
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted namespaces list
     */
    public function newReferencedNamespaceAssociationAction(OntoNamespace $namespace, OntoNamespace $referencedNamespace, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $namespace);

        $em = $this->getDoctrine()->getManager();

        $referencedNamespaceAssociation = $em->getRepository('AppBundle:ReferencedNamespaceAssociation')
            ->findOneBy(array('namespace' => $namespace, 'referencedNamespace' => $referencedNamespace));

        if($namespace->getIsTopLevelNamespace()) {
            $status = 'Error';
            $message = 'This namespace is not valid';
        }
        else if (!is_null($referencedNamespaceAssociation)) {
            $status = 'Error';
            $message = 'This namespace is already associated with a referenced namespace';
        }
        else {
            $referencedNamespaceAssociation = new ReferencedNamespaceAssociation();
            $referencedNamespaceAssociation->setNamespace($namespace);
            $referencedNamespaceAssociation->setReferencedNamespace($referencedNamespace);
            $referencedNamespaceAssociation->setCreator($this->getUser());
            $referencedNamespaceAssociation->setModifier($this->getUser());
            $referencedNamespaceAssociation->setCreationTime(new \DateTime('now'));
            $referencedNamespaceAssociation->setModificationTime(new \DateTime('now'));

            $em = $this->getDoctrine()->getManager();
            $em->persist($referencedNamespaceAssociation);

            $em->flush();
            $status = 'Success';
            $message = 'Namespace successfully associated';
        }


        $response = array(
            'status' => $status,
            'message' => $message,
            'nbchildversions' => count($referencedNamespace->getTopLevelNamespace()->getChildVersions())
        );

        return new JsonResponse($response);

    }

    /**
     * @Route("/namespace/{namespace}/referenced-namespace/{referencedNamespace}/delete", name="namespace_referenced_namespace_disassociation")
     * @Method({ "DELETE"})
     * @param OntoNamespace  $namespace    The namespace to be disassociated from a referenced namespace
     * @param OntoNamespace  $referencedNamespace    The referenced namespace to be disassociated from a namespace
     * @return JsonResponse
     */
    public function deleteReferencedNamespaceAssociationAction(OntoNamespace $namespace, OntoNamespace $referencedNamespace, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $namespace);

        $em = $this->getDoctrine()->getManager();

        $referencedNamespaceAssociation = $em->getRepository('AppBundle:ReferencedNamespaceAssociation')
            ->findOneBy(array('namespace' => $namespace, 'referencedNamespace' => $referencedNamespace));

        $em->remove($referencedNamespaceAssociation);
        $em->flush();

        $rootNamespaceReselectable = $referencedNamespace->getTopLevelNamespace();

        $response = array(
            'idRoot' => $rootNamespaceReselectable->getId(),
            'labelRoot' => $rootNamespaceReselectable->getStandardLabel()
        );

        return new JsonResponse($response);

    }

    /**
     * @Route("/namespace/{namespace}/referenced-namespace/{referencedNamespace}/new-referenced-namespace/{newReferencedNamespace}/change", name="namespace_referenced_namespace_change")
     * @Method({ "GET"})
     * @param OntoNamespace  $namespace    The namespace to be changed from a referenced namespace
     * @param OntoNamespace  $referencedNamespace    The referenced namespace to be changed from a namespace
     * @return JsonResponse
     */
    public function changeReferencedNamespaceAssociationAction(OntoNamespace $namespace, OntoNamespace $referencedNamespace, OntoNamespace $newReferencedNamespace, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $namespace);

        $em = $this->getDoctrine()->getManager();

        $referencedNamespaceAssociation = $em->getRepository('AppBundle:ReferencedNamespaceAssociation')
            ->findOneBy(array('namespace' => $namespace, 'referencedNamespace' => $referencedNamespace));

        $referencedNamespaceAssociation->setReferencedNamespace($newReferencedNamespace);

        // Modifier les relations appartenant au namespace pointant sur l'ancien namespace reference, ils doivent maintenant pointer sur le nouveau namespace de référence
        // A condition que l'entité cible existe dans le nouveau namespace, sinon ne pas le modifier (= Mismatch)

        // Relations hierarchiques class
        foreach ($namespace->getClassAssociations() as $classAssociation){
            // Parent
            if($classAssociation->getParentClassNamespace() == $referencedNamespace){
                //Verifier si la classe parente existe dans la nouvelle référence
                if($classAssociation->getParentClass()->getClassVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace)
                {
                    //Elle existe, on peut modifier la classAssociation
                    $classAssociation->setParentClassNamespace($newReferencedNamespace);
                }
            }

            // Child
            if($classAssociation->getChildClassNamespace() == $referencedNamespace){
                //Verifier si la classe child existe dans la nouvelle référence
                if($classAssociation->getChildClass()->getClassVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace)
                {
                    //Elle existe, on peut modifier la classAssociation
                    $classAssociation->setChildClassNamespace($newReferencedNamespace);
                }
            }
        }

        // Relations hierarchiques property
        foreach ($namespace->getPropertyAssociations() as $propertyAssociation){
            // Parent
            if($propertyAssociation->getParentPropertyNamespace() == $referencedNamespace){
                //Verifier si la propriété parente existe dans la nouvelle référence
                if($propertyAssociation->getParentProperty()->getPropertyVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace)
                {
                    //Elle existe, on peut modifier la propertyAssociation
                    $propertyAssociation->setParentPropertyNamespace($newReferencedNamespace);
                }
            }

            // Child
            if($propertyAssociation->getChildPropertyNamespace() == $referencedNamespace){
                //Verifier si la propriété child existe dans la nouvelle référence
                if($propertyAssociation->getChildProperty()->getPropertyVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace)
                {
                    //Elle existe, on peut modifier la propertyAssociation
                    $propertyAssociation->setChildPropertyNamespace($newReferencedNamespace);
                }
            }
        }

        // Propriétés - domain
        foreach ($namespace->getPropertyVersions() as $propertyVersion){
            // Domain
            if($propertyVersion->getDomainNamespace() == $referencedNamespace){
                //Verifier si le domain existe dans la nouvelle référence
                if($propertyVersion->getDomain()->getClassVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace)
                {
                    //Il existe, on peut modifier la propriété
                    $propertyVersion->setDomainNamespace($newReferencedNamespace);
                }
            }

            // Range
            if($propertyVersion->getRangeNamespace() == $referencedNamespace){
                //Verifier si la range existe dans la nouvelle référence
                if($propertyVersion->getRange()->getClassVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace)
                {
                    //Elle existe, on peut modifier la propriété
                    $propertyVersion->setRangeNamespace($newReferencedNamespace);
                }
            }
        }

        // Relations
        foreach ($namespace->getEntityAssociations() as $relation){
            //Source
            if($relation->getSourceNamespaceForVersion() == $referencedNamespace){
                //Verifier si la classe ou propriété source existe dans la nouvelle référence
                if($relation->getSource()->getClassVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace){
                    $relation->setSourceNamespaceForVersion($newReferencedNamespace);
                }
            }

            //Target
            if($relation->getTargetNamespaceForVersion() == $referencedNamespace){
                //Verifier si la classe ou propriété target existe dans la nouvelle référence
                if($relation->getTarget()->getClassVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace){
                    $relation->setTargetNamespaceForVersion($newReferencedNamespace);
                }
            }
        }

        $em->flush();

        $response = array();

        return new JsonResponse($response);

    }

    /**
     * @Route("/namespace/{namespace}/choices", name="get_choices_namespaces")
     * @Method({ "GET"})
     * @param OntoNamespace  $namespace    The namespace
     * @return JsonResponse
     * Cette fonction retrouve les espaces de noms soeurs
     */
    public function getChoicesNamespaceAssociation(OntoNamespace $namespace)
    {
        $em = $this->getDoctrine()->getManager();

        $rootNamespace = $namespace->getTopLevelNamespace();

        $childVersions = array();
        foreach($rootNamespace->getChildVersions() as $childVersion){
            $childVersions[$childVersion->getId()] = $childVersion->getStandardLabel();
        }

        $response = array("rootNamespaceChildVersions" => $childVersions);

        return new JsonResponse($response);

    }

    /**
     * @Route("/namespace/{namespace}/document", name="namespace_document")
     * @Method({ "GET"})
     * @param OntoNamespace  $namespace    The namespace
     * @return BinaryFileResponse
     */
    public function getNamespaceOdt(OntoNamespace $namespace)
    {
        foreach ($namespace->getTextProperties() as $textProperty){
            if($textProperty->getSystemType()->getId() == 2 and $textProperty->getLanguageIsoCode() == "en"){
                $textp_contributors = $textProperty;
            }
            if($textProperty->getSystemType()->getId() == 2 and !isset($textp_contributors)){
                $textp_contributors = $textProperty;
            }
            if($textProperty->getSystemType()->getId() == 16 and $textProperty->getLanguageIsoCode() == "en"){
                $textp_definition = $textProperty;
            }
            if($textProperty->getSystemType()->getId() == 16 and !isset($textp_definition)){
                $textp_definition = $textProperty;
            }
        }

        // Creating the new document...
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::EN_GB));

        // STYLES
        $paragrapheCentre = 'pCentre';
        $phpWord->addParagraphStyle($paragrapheCentre, array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
        $phpWord->addTitleStyle(1, array('bold' => true, 'size' => 20), array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
        $phpWord->addTitleStyle(2, array('bold' => true, 'size' => 18));
        $phpWord->addTitleStyle(3, array('bold' => true, 'size' => 16));

        $phpWord->addFontStyle("gras", array('bold' => true));


        // Couverture
        $section = $phpWord->addSection(array('vAlign' => VerticalJc::CENTER));
        $section->addTitle('Definition of '.$namespace->getStandardLabel(), 1);
        $section->addTextBreak(20);
        $status = "Published";
        $namespaceStatut = "Published";
        $namespaceDate  = $namespace->getPublishedAt();
        if($namespace->getIsOngoing()){
            $namespaceStatut = "Ongoing";
            $namespaceDate = $namespace->getModificationTime();
        }
        if($namespace->getIsOngoing()){$status = "Ongoing";}
        $section->addText('Editorial Status: '.$namespaceStatut, null, $paragrapheCentre);
        $section->addTextBreak(2);
        $section->addText(date_format($namespaceDate,"Y/m/d H:i:s"), null, $paragrapheCentre);
        $section->addTextBreak(20);
        if(isset($textp_contributors)){
            $section->addText("Contributors: ".html_entity_decode(strip_tags($textp_contributors->getTextProperty())), null, $paragrapheCentre);
        }

        $footer = $section->addFooter(Footer::AUTO);
        $textRun = $footer->addTextRun(array('alignment' => Jc::CENTER));
        $textRun->addField('PAGE', array('format' => 'ARABIC'));
        $textRun->addText(' of ');
        $textRun->addField('NUMPAGES', array('format' => 'ARABIC'));

        // Section suivante
        $section = $phpWord->addSection();
        $section->addTitle('1.1 Introduction', 2);
        $section->addTextBreak();
        $section->addTitle('1.1.1 Scope', 3);
        if(isset($textp_definition)){
            $section->addTextBreak();
            $section->addText(html_entity_decode(strip_tags($textp_definition->getTextProperty())));
        }
        $section->addTextBreak(2);
        $section->addTitle('1.1.2 Status', 3);
        $section->addTextBreak();
        $section->addText($namespaceStatut." version");

        // Section suivante
        $section = $phpWord->addSection();
        $section->addTitle('1.4 Class Declarations', 2);
        $section->addTextBreak();
        $section->addText('The classes are comprehensively declared in this section using the following format:');
        $section->addTextBreak();
        $section->addListItem('Class names are presented as headings in bold face, preceded by the class’ unique identifier;');
        $section->addListItem('The line “Subclass of:” declares the superclass of the class from which it inherits properties;');
        $section->addListItem('The line “Superclass of:” is a cross-reference to the subclasses of this class;');
        $section->addListItem('The line “Scope note:” contains the textual definition of the concept the class represents;');
        $section->addListItem('The line “Examples:” contains a bulleted list of examples of instances of this class.');
        $section->addListItem('The line “Properties:” declares the list of the class’s properties;');
        $section->addListItem('Each property is represented by its unique identifier, its forward name and the range class that it links to, separated by colons;');
        $section->addListItem('Inherited properties are not represented;');

        /** @var OntoClassVersion $classVersion */
        foreach ($namespace->getClassVersions() as $classVersion) {
            $section->addTextBreak(2);
            $section->addTitle($classVersion->getClass()->getIdentifierInNamespace()." - ".$classVersion->getStandardLabel(), 3);
            $associations = $classVersion->getClass()->getChildClassAssociations();
            foreach ($associations as $association){
                if(in_array($association->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                    $parentClassVersion = $association->getParentClass()->getClassVersionForDisplay($association->getParentClassNamespace());
                }
            }
            if(isset($parentClassVersion)){
                $section->addTextBreak();
                $textRun = $section->addTextRun();
                $textRun->addText('Subclass of: ', "gras");
                $textRun->addText($parentClassVersion->getStandardLabel());
            }

            $associations = $classVersion->getClass()->getParentClassAssociations();
            $i = 0;
            foreach ($associations as $association){
                if($i == 0){
                    $section->addTextBreak();
                    $section->addText('Superclass of:', "gras");
                    $i++;
                }
                if(in_array($association->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                    $childClassVersion = $association->getChildClass()->getClassVersionForDisplay($association->getChildClassNamespace());
                    $section->addText($childClassVersion->getStandardLabel(), null, array('indentation' => array('left' => 800)));
                }
            }

            foreach ($classVersion->getClass()->getTextProperties() as $textProperty){
                if(in_array($textProperty->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                    if($textProperty->getSystemType()->getId() == 1 and $textProperty->getLanguageIsoCode() == "en"){
                        $textp_scopenote = $textProperty;
                    }
                    if($textProperty->getSystemType()->getId() == 1 and !isset($textp_scopenote)){
                        $textp_scopenote = $textProperty;
                    }
                }
            }
            if(isset($textp_scopenote)){
                $section->addTextBreak();
                $textRun = $section->addTextRun();
                $textRun->addText('Scope note: ', "gras");
                $textRun->addText(html_entity_decode(strip_tags($textp_scopenote->getTextProperty())));
            }

            $i = 0;
            foreach ($classVersion->getClass()->getTextProperties() as $textProperty){
                if(in_array($textProperty->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                    if($textProperty->getSystemType()->getId() == 7){
                        if($i == 0){
                            $section->addTextBreak();
                            $section->addText('Examples:', "gras");
                            $i++;
                        }
                        $section->addText(html_entity_decode(strip_tags($textProperty->getTextProperty())), null, array('indentation' => array('left' => 800)));
                    }
                }
            }
            if(isset($parentClassVersion)){
                $section->addTextBreak();
                $textRun = $section->addTextRun();
                $textRun->addText('In First Order Logic: ', "gras");
                $textRun->addText($classVersion->getClass()->getIdentifierInNamespace().' ⊃ '.$parentClassVersion->getClass()->getIdentifierInNamespace());
            }

            $i = 0;
            $em = $this->getDoctrine()->getManager();
            $outgoingProperties = $em->getRepository('AppBundle:property')->findOutgoingPropertiesByClassVersionAndNamespacesId($classVersion, $namespace->getLargeSelectedNamespacesId());
            foreach($outgoingProperties as $outgoingProperty){
                if($i == 0){
                    $section->addTextBreak();
                    $section->addText('Properties:', "gras");
                    $i++;
                }
                $propertyVersion = $em->getRepository('AppBundle:PropertyVersion')->findOneBy(array("property"=>$outgoingProperty['propertyId'], "namespaceForVersion"=>$outgoingProperty['propertyNamespaceId']));
                $section->addText($propertyVersion->getDomain()->getIdentifierInNamespace()." ".$propertyVersion->getStandardLabel()." (".$propertyVersion->getInvertedLabel()."): ".$propertyVersion->getRange()->getIdentifierInNamespace(), null, array('indentation' => array('left' => 800)));
            }
        }

        // Section suivante
        $section = $phpWord->addSection();
        $section->addTitle('1.4 Property Declarations', 2);
        $section->addTextBreak();
        $section->addText('The properties are comprehensively declared in this section using the following format:');
        $section->addTextBreak();
        $section->addListItem('Property names are presented as headings in bold face, preceded by unique property identifiers;');
        $section->addListItem('The line “Domain:” declares the class for which the property is defined;');
        $section->addListItem('The line “Range:” declares the class to which the property points, or that provides the values for the property;');
        $section->addListItem('The line “Superproperty of:” is a cross-reference to any subproperties the property may have;');
        $section->addListItem('The line “Scope note:” contains the textual definition of the concept the property represents;');
        $section->addListItem('The line “Examples:” contains a bulleted list of examples of instances of this property.');
        $section->addTextBreak(2);

        /** @var PropertyVersion $propertyVersion */
        foreach ($namespace->getPropertyVersions() as $propertyVersion) {
            $label = $propertyVersion->getProperty()->getIdentifierInNamespace() . " - " . $propertyVersion->getStandardLabel();
            $section->addTitle($label, 3);
            $section->addTextBreak();
            if(!is_null($propertyVersion->getDomain())){
                $label = $propertyVersion->getDomain()->getIdentifierInNamespace()." ".$propertyVersion->getDomain()->getClassVersionForDisplay($propertyVersion->getDomainNamespace())->getStandardLabel();
                $textRun = $section->addTextRun();
                $textRun->addText('Domain: ', "gras");
                $textRun->addText($label);
            }
            if(!is_null($propertyVersion->getRange())){
                $label = $propertyVersion->getRange()->getIdentifierInNamespace()." ".$propertyVersion->getRange()->getClassVersionForDisplay($propertyVersion->getRangeNamespace())->getStandardLabel();
                $textRun = $section->addTextRun();
                $textRun->addText('Range: ', "gras");
                $textRun->addText($label);
            }
            $associations = $propertyVersion->getProperty()->getChildPropertyAssociations();
            $parentPropertyVersion = null;
            foreach ($associations as $association){
                if(in_array($association->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                    $parentPropertyVersion = $association->getParentProperty()->getPropertyVersionForDisplay($association->getParentPropertyNamespace());
                }
            }
            if(!is_null($parentPropertyVersion)){
                $labelDomain = "";
                $labelRange = "";
                $label = $parentPropertyVersion->getProperty()->getIdentifierInNamespace()." ".$parentPropertyVersion->getStandardLabel();
                if(!is_null($parentPropertyVersion->getDomain())){$labelDomain = $parentPropertyVersion->getDomain()->getIdentifierInNamespace()." ".$parentPropertyVersion->getDomain()->getClassVersionForDisplay($parentPropertyVersion->getDomainNamespace())->getStandardLabel().":";}
                if(!is_null($parentPropertyVersion->getRange())){$labelRange = ":".$parentPropertyVersion->getRange()->getIdentifierInNamespace()." ".$parentPropertyVersion->getRange()->getClassVersionForDisplay($parentPropertyVersion->getRangeNamespace())->getStandardLabel();}
                $label = $labelDomain.$label.$labelRange;
                $section->addTextBreak();
                $textRun = $section->addTextRun();
                $textRun->addText('Subproperty of: ', "gras");
                $textRun->addText($label);
            }

            if(!is_null($propertyVersion->getQuantifiersString())){
                $section->addTextBreak();
                $textRun = $section->addTextRun();
                $textRun->addText('Quantification: ',"gras");
                $textRun->addText($propertyVersion->getQuantifiersString());
                $section->addText("UML: ".$propertyVersion->getQuantifiers());
                $section->addText("Merise: ".$propertyVersion->getQuantifiersMerise());
            }

            foreach ($propertyVersion->getProperty()->getTextProperties() as $textProperty){
                if(in_array($textProperty->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                    if($textProperty->getSystemType()->getId() == 1 and $textProperty->getLanguageIsoCode() == "en"){
                        $textp_scopenote = $textProperty;
                    }
                    if($textProperty->getSystemType()->getId() == 1 and !isset($textp_scopenote)){
                        $textp_scopenote = $textProperty;
                    }
                }
            }
            if(isset($textp_scopenote)){
                $section->addTextBreak();
                $textRun = $section->addTextRun();
                $textRun->addText('Scope note: ', "gras");
                $textRun->addText(html_entity_decode(strip_tags($textp_scopenote->getTextProperty())));
            }

            $i = 0;
            foreach ($propertyVersion->getProperty()->getTextProperties() as $textProperty){
                if(in_array($textProperty->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                    if($textProperty->getSystemType()->getId() == 7){
                        if($i == 0){
                            $section->addTextBreak();
                            $section->addText('Examples:', "gras");
                            $i++;
                        }
                        $section->addText(html_entity_decode(strip_tags($textProperty->getTextProperty())), null, array('indentation' => array('left' => 800)));
                    }
                }
            }
            $section->addTextBreak();
            $section->addText('In First Order Logic:', "gras");
            if(!is_null($propertyVersion->getDomain())){
                $section->addText($propertyVersion->getProperty()->getIdentifierInNamespace().'(x,y) ⊃ '.$propertyVersion->getDomain()->getIdentifierInNamespace().'(x)', null, array('indentation' => array('left' => 800)));
            }
            if(!is_null($propertyVersion->getRange())){
                $section->addText($propertyVersion->getProperty()->getIdentifierInNamespace().'(x,y) ⊃ '.$propertyVersion->getRange()->getIdentifierInNamespace().'(y)', null, array('indentation' => array('left' => 800)));
            }
            if(!is_null($parentPropertyVersion)){
                $section->addText($propertyVersion->getProperty()->getIdentifierInNamespace().'(x,y) ⊃ '.$parentPropertyVersion->getProperty()->getIdentifierInNamespace().'(x,y)', null, array('indentation' => array('left' => 800)));
            }
            $section->addTextBreak(2);
        }

        // Saving the document as OOXML file...
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');

        // Create a temporal file in the system
        $fileName = 'namespace-'.$namespace->getStandardLabel().'.docx';
        $temp_file = tempnam(sys_get_temp_dir(), $fileName);

        // Write in the temporal filepath
        $objWriter->save($temp_file);

        // Send the temporal file as response (as an attachment)
        $response = new BinaryFileResponse($temp_file);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $fileName
        );

        return $response;
    }

}