<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 28/06/2017
 * Time: 15:50
 */

namespace AppBundle\Controller;

use AppBundle\AppBundle;
use AppBundle\Entity\ClassAssociation;
use AppBundle\Entity\EntityUserProjectAssociation;
use AppBundle\Entity\Label;
use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoClassVersion;
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\PropertyVersion;
use AppBundle\Entity\ReferencedNamespaceAssociation;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\NamespaceEditIdentifiersForm;
use AppBundle\Form\NamespaceForm;
use AppBundle\Form\NamespacePublicationForm;
use AppBundle\Form\NamespaceQuickAddForm;
use Doctrine\Common\Collections\ArrayCollection;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\TextBreak;
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
     * @Route("/namespace/new/{project}", name="namespace_new", requirements={"project"="^[0-9]+$"})
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
        $ongoingNamespace = new OntoNamespace();
        $namespaceLabel = new Label();
        $ongoingNamespaceLabel = new Label();

        $em = $this->getDoctrine()->getManager();
        $systemTypeDescription = $em->getRepository('AppBundle:SystemType')->find(16); //systemType 16 = Description
        $systemTypeContributors = $em->getRepository('AppBundle:SystemType')->find(2); //systemType 2 = Contributors

        $description = new TextProperty();
        $description->setNamespace($namespace);
        $description->setSystemType($systemTypeDescription);
        $description->setCreator($this->getUser());
        $description->setModifier($this->getUser());
        $description->setCreationTime(new \DateTime('now'));
        $description->setModificationTime(new \DateTime('now'));

        $namespace->addTextProperty($description);

        $now = new \DateTime();

        $namespace->setCreator($this->getUser());
        $namespace->setModifier($this->getUser());

        $namespaceLabel->setIsStandardLabelForLanguage(true);
        $namespaceLabel->setCreator($this->getUser());
        $namespaceLabel->setModifier($this->getUser());
        $namespaceLabel->setCreationTime(new \DateTime('now'));
        $namespaceLabel->setModificationTime(new \DateTime('now'));

        $namespace->addLabel($namespaceLabel);

        // Pour forcer le champ Contributors à ne pas avoir l'éditeur enrichi
        $txtpContributors = new TextProperty();
        $txtpContributors->setSystemType($txtpContributors);

        $form = $this->createForm(NamespaceQuickAddForm::class, $namespace, array("txtpContributors" => $txtpContributors));
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

            // Même si pas automatique, autant remplir dès maintenant les current number
            $namespace->setCurrentClassNumber(0);
            $namespace->setCurrentPropertyNumber(0);

            // Ajout des namespaces références si choisi(s)
            $referencesNamespaces = json_decode($form->get("referenceNamespaces")->getData());
            foreach ($referencesNamespaces as $referenceNamespace => $labelNamespace){
                $referencedNamespaceAssociation = new ReferencedNamespaceAssociation();
                $referencedNamespaceAssociation->setNamespace($ongoingNamespace);
                $referenceNamespace = $em->getRepository("AppBundle:OntoNamespace")->find(intval($referenceNamespace));
                $referencedNamespaceAssociation->setReferencedNamespace($referenceNamespace);
                $referencedNamespaceAssociation->setCreator($this->getUser());
                $referencedNamespaceAssociation->setModifier($this->getUser());
                $referencedNamespaceAssociation->setCreationTime(new \DateTime('now'));
                $referencedNamespaceAssociation->setModificationTime(new \DateTime('now'));
                $ongoingNamespace->addReferenceNamespaceAssociation($referencedNamespaceAssociation);
                $em->persist($referencedNamespaceAssociation);
            }

            //just in case, we set the domain to ontome.net for non external namespaces
            if (!$namespace->getIsExternalNamespace() && strpos($namespace->getNamespaceURI(), 'https://ontome.net/ns') !== 0 ) {
                $u = parse_url($namespace->getNamespaceURI());
                $uri = 'https://ontome.net/ns'.$u['path']; //if the user tries to change the domain, we force it to be ontome.net
                $namespace->setNamespaceURI($uri);
            }

            //$ongoingNamespace->setNamespaceURI($namespace->getNamespaceURI());
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
            $ongoingNamespaceLabel->setLabel($namespaceLabel->getLabel().' ongoing');
            $ongoingNamespaceLabel->setLanguageIsoCode($namespaceLabel->getLanguageIsoCode());
            $ongoingNamespaceLabel->setCreator($this->getUser());
            $ongoingNamespaceLabel->setModifier($this->getUser());
            $ongoingNamespaceLabel->setCreationTime(new \DateTime('now'));
            $ongoingNamespaceLabel->setModificationTime(new \DateTime('now'));
            $ongoingNamespace->addLabel($ongoingNamespaceLabel);
            $ongoingDescription = clone $description;
            $ongoingDescription->setNamespace($ongoingNamespace);
            $ongoingNamespace->addTextProperty($ongoingDescription);

            $contributors = $form->get("contributors")->getData();
            $contributors->setNamespace($ongoingNamespace);
            $contributors->setSystemType($systemTypeContributors);
            $contributors->setCreator($this->getUser());
            $contributors->setModifier($this->getUser());
            $contributors->setCreationTime(new \DateTime('now'));
            $contributors->setModificationTime(new \DateTime('now'));
            $ongoingNamespace->addTextProperty($contributors);

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

        $rootNamespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findBy(array("isTopLevelNamespace" => true), array("standardLabel" => "ASC"));

        return $this->render('namespace/new.html.twig', [
            'namespace' => $namespace,
            'namespaceForm' => $form->createView(),
            'rootNamespaces' => $rootNamespaces
        ]);
    }

    /**
     * @Route("/namespace/{id}", name="namespace_show", requirements={"id"="^([0-9]+)|(selectedValue|refId){1}$"})
     * @param OntoNamespace $namespace
     * @return Response the rendered template
     */
    public function showAction(OntoNamespace $namespace)
    {
        $em = $this->getDoctrine()->getManager();

        $textProperties = $em
            ->getRepository('AppBundle:TextProperty')
            ->findBy(array("namespaceForVersion" => $namespace->getId()));
        return $this->render('namespace/show.html.twig', array(
            'namespace' => $namespace,
            'textProperties' => $textProperties
        ));
    }

    /**
     * @Route("/namespace/{id}/edit", name="namespace_edit", requirements={"id"="^[0-9]+$"})
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

        $rootNamespacesBrut = $em->getRepository('AppBundle:OntoNamespace')
            ->findAllNonAssociatedToNamespaceByNamespaceId($namespace);

        $rootNamespaces = new ArrayCollection();
        foreach($rootNamespacesBrut as $rootNamespace){
            $rootNamespaces->add($em->getRepository('AppBundle:OntoNamespace')->find($rootNamespace['id']));
        }

        $textProperties = $em
            ->getRepository('AppBundle:TextProperty')
            ->findBy(array("namespaceForVersion" => $namespace->getId()));

        if($this->isGranted('full_edit', $namespace)) {

            $ongoingNamespaceHasChanged = $em->getRepository('AppBundle:OntoNamespace')
                ->checkNamespaceChange($namespace);

            $form = $this->createForm(NamespaceForm::class, $namespace);

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $em->merge($namespace);
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
            $formIdentifiers = $this->createForm(NamespaceEditIdentifiersForm::class, $namespace);
            $formIdentifiers->handleRequest($request);
            if ($formIdentifiers->isSubmitted() && $formIdentifiers->isValid()) {

                $em = $this->getDoctrine()->getManager();
                $em->persist($namespace);
                $em->flush();

                $this->addFlash('success', 'Namespace identifiers updated!');
                return $this->redirectToRoute('namespace_edit', [
                    'id' => $namespace->getId(),
                    '_fragment' => 'identifiers'
                ]);
            }
            return $this->render('namespace/edit.html.twig', [
                'namespaceForm' => $form->createView(),
                'namespaceIdentifiersForm' => $formIdentifiers->createView(),
                'namespace' => $namespace,
                'rootNamespaces' => $rootNamespaces,
                'textProperties' => $textProperties,
                'hasChanged' => $ongoingNamespaceHasChanged,
            ]);
        }
        else {
            return $this->render('namespace/edit.html.twig', [
                'namespaceForm' => null,
                'namespace' => $namespace,
                'rootNamespaces' => $rootNamespaces,
                'textProperties' => $textProperties,
                'hasChanged' => null,
            ]);
        }
    }

    /**
     * @Route("/namespace/{id}/publish", name="namespace_publication", requirements={"id"="^[0-9]+$"})
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
     * @Route("/namespace/{id}/validate-publication", name="namespace_validate_publication", requirements={"id"="^[0-9]+$"})
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
     * @Route("/namespace/{id}/toggle-automatic-identifier-management", name="namespace_toggle_identifier_management", requirements={"id"="^[0-9]+$"})
     * @Method("GET")
     * @param OntoNamespace $namespace
     * @return JsonResponse
     */
    public function toggleAutomaticIdentifierManagement(OntoNamespace $namespace, Request $request)
    {
        if(is_null($namespace)) {
            throw $this->createNotFoundException('The namespace n° '.$namespace->getId().' does not exist. Please contact an administrator.');
        }

        if(!$namespace->getIsTopLevelNamespace()){
            throw $this->createAccessDeniedException('The namespace n° '.$namespace->getId().' is not root and can\'t manage identifiers. Please contact an administrator.');
        }

        $this->denyAccessUnlessGranted('edit', $namespace);

        if(is_null($namespace->getClassPrefix()) && is_null($namespace->getPropertyPrefix())){
            $namespace->setClassPrefix("C");
            $namespace->setPropertyPrefix("P");
        }
        else{
            $namespace->setClassPrefix(null);
            $namespace->setPropertyPrefix(null);
        }

        if(is_null($namespace->getCurrentClassNumber())){
            $namespace->setCurrentClassNumber(0);
            $namespace->setCurrentPropertyNumber(0);
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($namespace);
        $em->flush();

        return new JsonResponse("",204, array(), true);
    }

    /**
     * @Route("/namespace/root-namespace/{id}/json", name="namespaces_by_root_id_list_json", requirements={"id"="^([0-9]+)|(selectedValue){1}$"})
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
            $user = $this->getUser();
            foreach ($rootNamespace->getChildVersions()->filter(function($v)use($user){return $v->getIsVisible() or $v->getProjectForTopLevelNamespace()->getuserProjectAssociations()->filter(function($v)use($user){return $v->getUser() == $user;})->count() == 1; }) as $namespace) {
                $referencedNamespaces = array();
                foreach ($namespace->getAllReferencedNamespaces() as $referencedNamespace){
                    $referencedNamespaces[$referencedNamespace->getTopLevelNamespace()->getId()] = [$referencedNamespace->getId(), $referencedNamespace->getStandardLabel()];
                }
                $warningOngoing = false;
                if($namespace->getIsOngoing()){$warningOngoing = true;}
                foreach ($namespace->getAllReferencedNamespaces() as $referencedNamespace){
                    if($referencedNamespace->getIsOngoing()){$warningOngoing = true;}
                }

                $namespaces[] = [
                    'id' => $namespace->getId(),
                    'standardLabel' => $namespace->getStandardLabel(),
                    'topLevelNamespaceId' => $namespace->getTopLevelNamespace()->getId(),
                    'warningOngoing' => $warningOngoing,
                    'referencedNamespaces' => $referencedNamespaces
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
     * @Route("/namespace/profile/{id}/json", name="root_namespaces_list_for_profile_json", requirements={"id"="^[0-9]+$"})
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
     * @Route("/namespace/{id}/json", name="namespace_json", schemes={"https"}, requirements={"id"="^[0-9]+"})
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
     * @Route("/namespace/{namespace}/referenced-namespace/{referencedNamespace}/add", name="namespace_referenced_namespace_association", requirements={"namespace"="^([0-9]+)|(namespaceID){1}$", "referencedNamespace"="^([0-9]+)|(selectedValue){1}$"})
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
     * @Route("/namespace/{namespace}/referenced-namespace/{referencedNamespace}/delete", name="namespace_referenced_namespace_disassociation", requirements={"namespace"="^([0-9]+)|(namespaceID){1}$", "referencedNamespace"="^([0-9]+)|(selectedValue){1}$"})
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
     * @Route("/namespace/{namespace}/referenced-namespace/{referencedNamespace}/new-referenced-namespace/{newReferencedNamespace}/change", name="namespace_referenced_namespace_change", requirements={"namespace"="^([0-9]+)|(namespaceID){1}$", "referencedNamespace"="^([0-9]+)|(oldRefNsId){1}$", "newReferencedNamespace"="^([0-9]+)|(newRefNsId){1}$"})
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
                if(!is_null($relation->getSourceClass()) && $relation->getSource()->getClassVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace){
                    $relation->setSourceNamespaceForVersion($newReferencedNamespace);
                }
                if(!is_null($relation->getSourceProperty()) && $relation->getSource()->getPropertyVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace){
                    $relation->setSourceNamespaceForVersion($newReferencedNamespace);
                }
            }

            //Target
            if($relation->getTargetNamespaceForVersion() == $referencedNamespace){
                //Verifier si la classe ou propriété target existe dans la nouvelle référence
                if(!is_null($relation->getSourceClass()) && $relation->getTarget()->getClassVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace){
                    $relation->setTargetNamespaceForVersion($newReferencedNamespace);
                }
                if(!is_null($relation->getSourceProperty()) && $relation->getTarget()->getPropertyVersionForDisplay($newReferencedNamespace)->getNamespaceForVersion() == $newReferencedNamespace){
                    $relation->setTargetNamespaceForVersion($newReferencedNamespace);
                }
            }
        }

        $em->flush();

        $response = array();

        return new JsonResponse($response);

    }

    /**
     * @Route("/namespace/{namespace}/choices", name="get_choices_namespaces", requirements={"namespace"="^([0-9]+)|(selectedValue){1}$"})
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
      * @Route("/namespace/{namespace}/document", name="namespace_document", requirements={"namespace"="^([0-9]+)|(namespaceID){1}$"})
     * @Method({ "GET"})
     * @param OntoNamespace  $namespace    The namespace
     * @return BinaryFileResponse
     */
    public function getNamespaceOdt(OntoNamespace $namespace, Request $request)
    {
        function specialCharactersConversion($string, $forHTML=false){
            $string = htmlspecialchars_decode($string, ENT_QUOTES);
            $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
            if($forHTML){
                $string = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$string;
            }
            return $string;
        }

        $em = $this->getDoctrine()->getManager();

        $optionCardinality = $request->get('optCardinality', 'cardinality-opt-uml'); //cardinality-opt-er est l'autre option
        $optionTextCardinality = filter_var($request->get('optTextCardinality', true), FILTER_VALIDATE_BOOLEAN);; //false est l'autre option
        $optionFol = filter_var($request->get('optFol', true), FILTER_VALIDATE_BOOLEAN);; //false est l'autre option

        $allNamespacesReferences = $namespace->getAllReferencedNamespaces();
        $allNamespacesReferences->add($namespace);
        $allNamespacesReferences->add($em->getRepository('AppBundle:OntoNamespace')->findOneBy(array('id' => 4)));
        //var_dump($allNamespacesReferences->map(function($v){return $v->getId();})->toArray()); die;

        foreach ($namespace->getTextProperties()->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;}) as $textProperty){
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
            if($textProperty->getSystemType()->getId() == 31 and !isset($textp_version)){
                $textp_version = $textProperty;
            }
        }

        // Creating the new document...
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::EN_GB));
        $phpWord->setDefaultFontName("Times New Roman");

        // STYLES
        $paragrapheCentre = 'pCentre';
        $phpWord->addParagraphStyle($paragrapheCentre, array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
        $phpWord->addTitleStyle(1, array('bold' => true, 'size' => 20), array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
        $phpWord->addTitleStyle(2, array('bold' => true, 'size' => 18));
        $phpWord->addTitleStyle(3, array('bold' => true, 'size' => 16));

        $phpWord->addFontStyle("gras", array('bold' => true));
        $phpWord->addFontStyle("italic11", array('italic' => true, 'size' => 11));

        $fancyTableStyleName2 = 'Fancy Table 2';
        $fancyTableStyle = array('borderSize' => 1, 'borderColor' => '000000', 'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP);
        $fancyTableFirstRowStyle = array();
        $phpWord->addTableStyle($fancyTableStyleName2, $fancyTableStyle, $fancyTableFirstRowStyle);

        // Couverture
        $section = $phpWord->addSection(array('vAlign' => VerticalJc::CENTER));
        //-- Definition of
        $section->addTitle('Definition of '.$namespace->getStandardLabel(), 1);
        $section->addTextBreak(10);
        //-- Version
        if(isset($textp_version)){
            $section->addText('Version: '.specialCharactersConversion($textp_version->getTextProperty()), null, $paragrapheCentre);
            $section->addTextBreak(1);
        }
        //-- Date
        $namespaceStatut = "Published";
        $namespaceDate  = $namespace->getPublishedAt();
        if($namespace->getIsOngoing() or is_null($namespaceDate)){
            $namespaceStatut = "Ongoing";
            $namespaceDate = new \DateTime();
        }
        $section->addText(date_format($namespaceDate,"Y/m/d"), null, $paragrapheCentre);
        $section->addTextBreak(5);
        //-- Contributors
        if(isset($textp_contributors)){
            //$string = htmlentities($textp_contributors->getTextProperty());
            //$string = str_replace($allowedTagsEncoded, $allowedTagsDecoded, $string);
            $string = specialCharactersConversion($textp_contributors->getTextProperty());
            $section->addText("Contributors: ".strip_tags($string), null, $paragrapheCentre);
        }
        $section->addTextBreak(15);
        //-- Licence
        $section->addText('License information: Creative Commons Licence Attribution-ShareAlike 4.0 International', null, $paragrapheCentre);
        $txtrun = $section->addTextRun(array('alignment' => Jc::CENTER));
        $txtrun->addText('Exported from OntoME: ');
        $txtrun->addText('https://ontome.net/namespace/'.$namespace->getId());

        $centerTableStyleName = 'Center Table';
        $centerTableStyle = array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER);
        $centerTableStyleFirstRow = array();
        $phpWord->addTableStyle($centerTableStyleName, $centerTableStyle, $centerTableStyleFirstRow);
        $footer = $section->addFooter();
        $textRun = $footer->addTextRun(array('alignment' => Jc::END));
        $textRun->addField('PAGE', array('format' => 'ARABIC'));
        //$textRun = $footer->addTextRun(array('alignment' => Jc::CENTER));
        //$textRun->addField('PAGE', array('format' => 'ARABIC'));
        //$textRun->addText(' of ');
        //$textRun->addField('NUMPAGES', array('format' => 'ARABIC'));

        // Blank page
        $section = $phpWord->addSection();

        // Introduction
        $section = $phpWord->addSection();
        $section->addTitle('Introduction', 2);
        $section->addTextBreak();
        $section->addTitle('Scope', 3);
        if(isset($textp_definition)){
            $section->addTextBreak();
            //$string = htmlentities($textp_definition->getTextProperty());
            //$string = str_replace($allowedTagsEncoded, $allowedTagsDecoded, $string);
            $string = specialCharactersConversion($textp_definition->getTextProperty(), true);
            //$section->addText($string);
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $string, true, false);
        }
        $section->addTextBreak(2);
        $section->addTitle('Status', 3);
        $section->addTextBreak();
        $section->addText($namespaceStatut." version");

        // Class hierarchy
        $section = $phpWord->addSection();
        if($namespace->getDirectReferencedNamespaces()->count() > 0){ //S'il y a les NS références
            $directNamespacesReferences = $namespace->getDirectReferencedNamespaces();
            $standardLabelDirectNamespacesReferences = $directNamespacesReferences->map(function(OntoNamespace $ns){return $ns->getStandardLabel();});
            $nsCRM = $allNamespacesReferences->filter(function($v){return $v->getTopLevelNamespace()->getId() == 7;});

            // Plusieurs formulations
            // Soit le CIDOC CRM est l'unique référence
            if($directNamespacesReferences->count() == 1 and $directNamespacesReferences->first()->getTopLevelNamespace()->getId() == 7)
            {
                $section->addTitle($namespace->getStandardLabel().' class hierarchy, aligned with portions from the CIDOC CRM hierarchy', 2);
            }

            // Soit le CIDOC CRM n'est ni espace de référence direct ni espace de référence d'un espace de référence
            if($nsCRM->count() == 0)
            {
                if($directNamespacesReferences->count() > 1){
                    $section->addTitle($namespace->getStandardLabel().' class hierarchy, aligned with portions from the '.implode($standardLabelDirectNamespacesReferences->toArray(), ', ').'class hierarchies', 2);
                }
            }

            // Soit le CIDOC CRM est un espace de réf direct ou indirect
            if($nsCRM->count() == 1 and $directNamespacesReferences->count() != 1)
            {
                $section->addTitle($namespace->getStandardLabel().' class hierarchy, aligned with portions from the '.implode($standardLabelDirectNamespacesReferences->toArray(), ', ').' and the CIDOC CRM class hierarchies', 2);
            }
            $section->addTextBreak(2);
            $section->addText('This class hierarchy lists:');
            $section->addTextBreak();
            $section->addListItem('all classes declared in '.$namespace->getStandardLabel());
            $section->addTextBreak();
            // Les NS ref CRM
            foreach ($namespace->getAllReferencedNamespaces()->filter(function($v){return $v->getTopLevelNamespace()->getId() == 7;}) as $ns){
                $version = '';
                $ns_txtp_versions = $ns
                    ->getTextProperties()
                    ->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;})
                    ->filter(function($v){return $v->getSystemType()->getId() == 31;}
                    );
                if($ns_txtp_versions->count() > 0){$version = ' version '.$ns_txtp_versions->first()->getTextProperty();}
                $section->addListItem('all classes declared in CIDOC CRM'.$version.' that are declared as superclasses of classes declared in the '.$namespace->getStandardLabel());
                $section->addTextBreak();
            }
            // Les NS direct sauf CRM
            foreach ($directNamespacesReferences->filter(function($v){return $v->getTopLevelNamespace()->getId() != 7;}) as $ns){
                $version = '';
                $ns_txtp_versions = $ns
                    ->getTextProperties()
                    ->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;})
                    ->filter(function($v){return $v->getSystemType()->getId() == 31;}
                    );
                if($ns_txtp_versions->count() > 0){$version = ' version '.$ns_txtp_versions->first()->getTextProperty();}
                $section->addListItem('all classes declared in '.$ns->getStandardLabel().$version.' that are declared as superclasses of classes declared in the '.$namespace->getStandardLabel());
                $section->addTextBreak();
            }
            // Les NS ref CRM
            foreach ($namespace->getAllReferencedNamespaces()->filter(function($v){return $v->getTopLevelNamespace()->getId() == 7;}) as $ns){
                $version = '';
                $ns_txtp_versions = $ns
                    ->getTextProperties()
                    ->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;})
                    ->filter(function($v){return $v->getSystemType()->getId() == 31;});
                if($ns_txtp_versions->count() > 0){$version = ' version '.$ns_txtp_versions->first()->getTextProperty();}
                $section->addListItem('all classes declared in CIDOC CRM'.$version.' that are either domain or range for a property declared in  the '.$namespace->getStandardLabel());
                $section->addTextBreak();
            }
            // Les NS direct sauf CRM
            foreach ($directNamespacesReferences->filter(function($v){return $v->getTopLevelNamespace()->getId() != 7;}) as $ns){
                $version = '';
                $ns_txtp_versions = $ns
                    ->getTextProperties()
                    ->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;})
                    ->filter(function($v){return $v->getSystemType()->getId() == 31;}
                    );
                if($ns_txtp_versions->count() > 0){$version = ' version '.$ns_txtp_versions->first()->getTextProperty();}
                $section->addListItem('all classes declared in '.$ns->getStandardLabel().$version.' that are either domain or range for a property declared in  the '.$namespace->getStandardLabel());
                $section->addTextBreak();
            }
        }
        else{ //Pas de NS réferences
            $section->addTitle($namespace->getStandardLabel().' Class hierarchy', 2);
        }

        $section->addTextBreak();
        $section->addText('Table 1: Class Hierarchy', 'italic11');
        $fancyTableStyleName = 'Fancy Table';
        $fancyTableStyle = array('borderSize' => 1, 'borderColor' => 'FFFFFF');
        $fancyTableFirstRowStyle = array();
        $phpWord->addTableStyle($fancyTableStyleName, $fancyTableStyle, $fancyTableFirstRowStyle);

        // Peut on construire un tableau hierarchique ? (trouver les classes qui n'ont pas de superclass dans les ns ref)
        $nsRef = $namespace->getAllReferencedNamespaces();
        $nsRef->add($namespace);
        $allClasses = $namespace->getClasses()
            ->filter(function($v)use($namespace){// On retire les classes denied
                return is_null($v->getClassVersionForDisplay($namespace)->getValidationStatus()) || !is_null($v->getClassVersionForDisplay($namespace)->getValidationStatus()) && $v->getClassVersionForDisplay($namespace)->getValidationStatus()->getId() != 27;
            });
        foreach($namespace->getAllReferencedNamespaces() as $referencedNamespace){
            foreach ($referencedNamespace->getClasses() as $class){
                if(is_null($class->getClassVersionForDisplay($referencedNamespace)->getValidationStatus()) || !is_null($class->getClassVersionForDisplay($referencedNamespace)) && $class->getClassVersionForDisplay($referencedNamespace)->getValidationStatus()->getId() != 27) {// On retire les classes denied
                    $allClasses->add($class);
                }
            }
        }
        $rootClasses = $allClasses
            ->filter(function($v) use ($nsRef, $namespace){
                return $v->getChildClassAssociations()
                        ->filter(function($w) use ($nsRef, $namespace){
                            return $namespace == $w->getNamespaceForVersion()
                                and $nsRef->contains($w->getParentClassNamespace());
                        })
                        ->count() == 0
                    and $v->getParentClassAssociations()
                        ->filter(function($w) use ($namespace){
                            return $w->getNamespaceForVersion() == $namespace;
                        })
                        ->count() > 0;
            });
        /*foreach($namespace->getAllReferencedNamespaces() as $referencedNamespace){
            $rootClassesRef = $referencedNamespace->getClasses()->filter(function ($v) use ($namespace, $nsRef){return $v->getChildClassAssociations()->filter(function($w) use ($namespace, $nsRef){return $namespace == $w->getNamespaceForVersion() and $nsRef->contains($w->getParentClassNamespace());})->count() == 0;});
            foreach ($rootClassesRef as $rootClassRef){
                $rootClasses->add($rootClassRef);
            }
        }*/

        $nbColumn = 2;
        foreach ($rootClasses as $rootClass){
            foreach ($rootClass->getHierarchicalTreeClasses($namespace) as $tuple){
                if($nbColumn < $tuple[1]){
                    $nbColumn = $tuple[1]+2;
                }
            }
        }

        // tableau
        $table = $section->addTable($fancyTableStyleName);
        foreach ($rootClasses as $rootClass){
            $classVersion = $rootClass->getClassVersions()->filter(function($v) use ($nsRef){return $nsRef->contains($v->getNamespaceForVersion());})->first();
            $table->addRow();
            $cell = $table->addCell(1000);
            $cell->addText($rootClass->getIdentifierInNamespace());
            //$table->addCell(null, array('gridSpan' => $nbColumn))->addText($classVersion->getStandardLabel());
            $cell = $table->addCell(4000);
            $cell->getStyle()->setGridSpan($nbColumn-1);
            $cell->addText($classVersion->getStandardLabel());
            foreach($rootClass->getHierarchicalTreeClasses($namespace) as $tuple){
                $table->addRow();
                $table->addCell(1000)->addText($tuple[0]->getIdentifierInNamespace());
                for ($i = 0; $i <= $tuple[1]; $i++){
                    if($i < $tuple[1]) {
                        $table->addCell(400)->addText('-');
                    }
                    else{
                        $cell = $table->addCell(4000);
                        $cell->getStyle()->setGridSpan($nbColumn-$i-1);
                        $cell->addText($tuple[0]->getClassVersionForDisplay($tuple[2])->getStandardLabel());
                    }
                }
            }
        }

        $classesVersionWithNSref = new ArrayCollection();
        // Domains & Ranges utilisés NSref
        foreach($namespace->getPropertyVersions()
                    ->filter(function($v){
                        return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27; /*On retire les denied*/
                    })
                as $propertyVersion){
            if(!is_null($propertyVersion->getDomain()) && $propertyVersion->getDomainNamespace() != $namespace && $propertyVersion->getDomainNamespace()->getId() != 4 and !$classesVersionWithNSref->contains($propertyVersion->getDomain())){
                if(!$classesVersionWithNSref->contains($propertyVersion->getDomain()->getClassVersionForDisplay($propertyVersion->getDomainNamespace()))){
                    $classesVersionWithNSref->add($propertyVersion->getDomain()->getClassVersionForDisplay($propertyVersion->getDomainNamespace()));
                }
            }
            if(!is_null($propertyVersion->getRange()) && $propertyVersion->getRangeNamespace() != $namespace && $propertyVersion->getRangeNamespace()->getId() != 4 and !$classesVersionWithNSref->contains($propertyVersion->getRange())){
                if(!$classesVersionWithNSref->contains($propertyVersion->getRange()->getClassVersionForDisplay($propertyVersion->getRangeNamespace()))){
                    $classesVersionWithNSref->add($propertyVersion->getRange()->getClassVersionForDisplay($propertyVersion->getRangeNamespace()));
                }
            }
        }

        // Hierarchy classes utilisés NSref
        foreach($namespace->getClassAssociations()->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27; /*On retire les denied*/}) as $classAssociation){
            if($classAssociation->getParentClassNamespace() != $namespace && $classAssociation->getParentClassNamespace()->getId() != 4 and !$classesVersionWithNSref->contains($classAssociation->getParentClass())){
                if(!$classesVersionWithNSref->contains($classAssociation->getParentClass()->getClassVersionForDisplay($classAssociation->getParentClassNamespace()))){
                    $classesVersionWithNSref->add($classAssociation->getParentClass()->getClassVersionForDisplay($classAssociation->getParentClassNamespace()));
                }
            }
            if($classAssociation->getChildClassNamespace() != $namespace && $classAssociation->getChildClassNamespace()->getId() != 4 and !$classesVersionWithNSref->contains($classAssociation->getChildClass())){
                if(!$classesVersionWithNSref->contains($classAssociation->getChildClass()->getClassVersionForDisplay($classAssociation->getChildClassNamespace()))){
                    $classesVersionWithNSref->add($classAssociation->getChildClass()->getClassVersionForDisplay($classAssociation->getChildClassNamespace()));
                }
            }
        }

        // Relations autres
        foreach($namespace->getEntityAssociations()
                    ->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27; /*On retire les denied*/})
                    ->filter(function($v){return !is_null($v->getSourceClass());})
                as $classAssociation){
            if($classAssociation->getSourceNamespaceForVersion() != $namespace && $classAssociation->getSourceNamespaceForVersion()->getId() != 4 and !$classesVersionWithNSref->contains($classAssociation->getSourceClass())){
                if(!$classesVersionWithNSref->contains($classAssociation->getSourceClass()->getClassVersionForDisplay($classAssociation->getSourceNamespaceForVersion()))){
                    $classesVersionWithNSref->add($classAssociation->getSourceClass()->getClassVersionForDisplay($classAssociation->getSourceNamespaceForVersion()));
                }
            }
            if($classAssociation->getTargetNamespaceForVersion() != $namespace && $classAssociation->getTargetNamespaceForVersion()->getId() != 4 and !$classesVersionWithNSref->contains($classAssociation->getTargetClass())){
                if(!$classesVersionWithNSref->contains($classAssociation->getTargetClass()->getClassVersionForDisplay($classAssociation->getTargetNamespaceForVersion()))){
                    $classesVersionWithNSref->add($classAssociation->getTargetClass()->getClassVersionForDisplay($classAssociation->getTargetNamespaceForVersion()));
                }
            }
        }

        // Trier d'abord CRM les autres puis par identifier
        $iterator = $classesVersionWithNSref->getIterator();
        $iterator->uasort(function ($a,$b){
            if($a->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7 and $b->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7){
                return strnatcmp($a->getClass()->getIdentifierInNamespace(), $b->getClass()->getIdentifierInNamespace());
            }
            elseif($a->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7 and $b->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7){
                // C'est la même chose que le premier if mais plus clair à la lecture.
                return strnatcmp($a->getClass()->getIdentifierInNamespace(), $b->getClass()->getIdentifierInNamespace());
            }
            elseif($a->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7 and $b->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7){
                return false;
            }
            elseif($a->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7 and $b->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7){
                return true;
            }
        });
        $classesVersionWithNSref = new ArrayCollection(iterator_to_array($iterator));

        if($classesVersionWithNSref->count() >0){
            $section->addTextBreak();
            $section->addTitle('List of external classes used in '.$namespace->getStandardLabel(), 2);
            $section->addTextBreak();
            $section->addText('Table 2: List of external classes grouped by model and ordered by model (exception: CRMbase always goes first) and then by class identifier.', 'italic11');

            $table = $section->addTable($fancyTableStyleName2);
            $table->addRow();
            $table->addCell(2250)->addText('Class identifier', array('bold' => true));
            $table->addCell(2250)->addText('Class name', array('bold' => true));
            $table->addCell(2250)->addText('Model', array('bold' => true));
            $table->addCell(2250)->addText('Version', array('bold' => true));

            foreach ($classesVersionWithNSref as $classVersion){
                $table->addRow();
                $table->addCell(2000)->addText($classVersion->getClass()->getIdentifierInNamespace());
                $table->addCell(2000)->addText($classVersion->getStandardLabel());
                $table->addCell(2000)->addText($classVersion->getNamespaceForVersion()->getTopLevelNamespace());
                $txtps_version = $classVersion->getNamespaceForVersion()->getTextProperties()->filter(function($v){return $v->getSystemType()->getId() == 31;});
                if($txtps_version->count() > 0){
                    $table->addCell(2000)->addText($txtps_version->first()->getTextProperty());
                }
                else{
                    $table->addCell(1000)->addText('');
                }
            }
        }

        // Property hierarchy
        $section = $phpWord->addSection();
        if($namespace->getDirectReferencedNamespaces()->count() > 0){ //S'il y a les NS références
            $directNamespacesReferences = $namespace->getDirectReferencedNamespaces();
            $standardLabelDirectNAmespacesReferences = $directNamespacesReferences->map(function(OntoNamespace $ns){return $ns->getStandardLabel();});
            $nsCRM = $allNamespacesReferences->filter(function($v){return $v->getTopLevelNamespace()->getId() == 7;});

            // Plusieurs formulations
            // Soit le CIDOC CRM est l'unique référence
            if($directNamespacesReferences->count() == 1 and $directNamespacesReferences->first()->getTopLevelNamespace()->getId() == 7)
            {
                $section->addTitle($namespace->getStandardLabel().' property hierarchy, aligned with portions from the CIDOC CRM hierarchy', 2);
            }

            // Soit le CIDOC CRM n'est ni espace de référence direct ni espace de référence d'un espace de référence
            if($nsCRM->count() == 0)
            {
                if($directNamespacesReferences->count() > 1) {
                    $section->addTitle($namespace->getStandardLabel().' property hierarchy, aligned with portions from the '.implode($standardLabelDirectNAmespacesReferences->toArray(), ', ').'property hierarchies', 2);
                }
            }

            // Soit le CIDOC CRM est un espace de réf direct ou indirect
            if($nsCRM->count() == 1 and $directNamespacesReferences->count() != 1)
            {
                $section->addTitle($namespace->getStandardLabel().' property hierarchy, aligned with portions from the '.implode($standardLabelDirectNAmespacesReferences->toArray(), ', ').' and the CIDOC CRM property hierarchies', 2);
            }
            $section->addTextBreak(2);
            $section->addText('This property hierarchy lists:');
            $section->addTextBreak();
            $section->addListItem('all properties declared in '.$namespace->getStandardLabel());
            $section->addTextBreak();
            // Les NS ref CRM
            foreach ($namespace->getAllReferencedNamespaces()->filter(function($v){return $v->getTopLevelNamespace()->getId() == 7;}) as $ns){
                $version = '';
                $ns_txtp_versions = $ns->getTextProperties()
                    ->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27; /*On retire les denied*/})
                    ->filter(function($v){return $v->getSystemType()->getId() == 31;});
                if($ns_txtp_versions->count() > 0){$version = ' version '.$ns_txtp_versions->first()->getTextProperty();}
                $section->addListItem('all properties declared in CIDOC CRM'.$version.' that are declared as superproperties of properties declared in the '.$namespace->getStandardLabel());
                $section->addTextBreak();
            }
            // Les NS direct sauf CRM
            foreach ($directNamespacesReferences->filter(function($v){return $v->getTopLevelNamespace()->getId() != 7;}) as $ns){
                $version = '';
                $ns_txtp_versions = $ns->getTextProperties()
                    ->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27; /*On retire les denied*/})
                    ->filter(function($v){return $v->getSystemType()->getId() == 31;});
                if($ns_txtp_versions->count() > 0){$version = ' version '.$ns_txtp_versions->first()->getTextProperty();}
                $section->addListItem('all properties declared in '.$ns->getStandardLabel().$version.' that are declared as superproperties of properties declared in the '.$namespace->getStandardLabel());
                $section->addTextBreak();
            }
        }
        else{ //Pas de NS réferences
            $section->addTitle($namespace->getStandardLabel().' Property hierarchy', 2);
        }
        $section->addTextBreak();
        $section->addText('Table 3: Property Hierarchy', 'italic11');


        // Peut on construire un tableau hierarchique ? (trouver les propriétés sans parents)
        $nsRef = $namespace->getAllReferencedNamespaces();
        $nsRef->add($namespace);
        $allProperties = $namespace->getProperties()->filter(function($v)use($namespace){// On retire les propriétés denied
            return is_null($v->getPropertyVersionForDisplay($namespace)->getValidationStatus()) || !is_null($v->getPropertyVersionForDisplay($namespace)->getValidationStatus()) && $v->getPropertyVersionForDisplay($namespace)->getValidationStatus()->getId() != 27;
        });
        foreach($namespace->getAllReferencedNamespaces() as $referencedNamespace){
            foreach ($referencedNamespace->getProperties() as $property){
                if(is_null($property->getPropertyVersionForDisplay($referencedNamespace)->getValidationStatus()) || !is_null($property->getPropertyVersionForDisplay($referencedNamespace)->getValidationStatus()) && $property->getPropertyVersionForDisplay($referencedNamespace)->getValidationStatus()->getId() != 27) {// On retire les propriétés denied
                    $allProperties->add($property);
                }
            }
        }
        $rootProperties = $allProperties->filter(function($v) use ($nsRef, $namespace){return $v->getChildPropertyAssociations()->filter(function($w) use ($nsRef, $namespace){return $namespace == $w->getNamespaceForVersion() and $nsRef->contains($w->getParentPropertyNamespace());})->count() == 0 and $v->getParentPropertyAssociations()->filter(function($w) use ($namespace){return $w->getNamespaceForVersion() == $namespace;})->count() > 0;});

        // Trier d'abord CRM les autres puis par identifier
        $iterator = $rootProperties->getIterator();
        $iterator->uasort(function ($a,$b){
            if($a->getPropertyVersions()->first()->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7 and $b->getPropertyVersions()->first()->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7){
                return strnatcmp($a->getIdentifierInNamespace(), $b->getIdentifierInNamespace());
            }
            elseif($a->getPropertyVersions()->first()->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7 and $b->getPropertyVersions()->first()->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7){
                // C'est la même chose que le premier if mais plus clair à la lecture.
                return strnatcmp($a->getIdentifierInNamespace(), $b->getIdentifierInNamespace());
            }
            elseif($a->getPropertyVersions()->first()->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7 and $b->getPropertyVersions()->first()->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7){
                return false;
            }
            elseif($a->getPropertyVersions()->first()->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7 and $b->getPropertyVersions()->first()->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7){
                return true;
            }
        });
        $rootProperties = new ArrayCollection(iterator_to_array($iterator));

        // tableau
        $table = $section->addTable($fancyTableStyleName);
        $table->addRow();
        $table->addCell(2000)->addText('Property id', array('bold' => true));
        $table->addCell(2000)->addText('Property Name', array('bold' => true));
        $table->addCell(2000)->addText('Entity – Domain', array('bold' => true));
        $table->addCell(2000)->addText('Entity - Range', array('bold' => true));;
        foreach ($rootProperties as $rootProperty){
            $propertyVersion = $rootProperty->getPropertyVersions()->filter(function($v) use ($nsRef){return $nsRef->contains($v->getNamespaceForVersion());})->first();

            $table->addRow();
            $table->addCell(1000, array('valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER))->addText($rootProperty->getIdentifierInNamespace());
            $table->addCell(2000, array('valign' => 'center'))->addText($propertyVersion->getStandardLabel());
            if(!is_null($propertyVersion->getDomain())){
                $table->addCell(2000, array('valign' => 'center'))->addText(html_entity_decode(strip_tags($propertyVersion->getDomain()->getClassVersionForDisplay($propertyVersion->getDomainNamespace())->getInvertedLabel())));
            }
            else{
                $table->addCell(2000, array('valign' => 'center'))->addText('');
            }

            if(!is_null($propertyVersion->getRange())) {
                $table->addCell(2000, array('valign' => 'center'))->addText(html_entity_decode(strip_tags($propertyVersion->getRange()->getClassVersionForDisplay($propertyVersion->getRangeNamespace())->getInvertedLabel())));
            }
            else{
                $table->addCell(2000, array('valign' => 'center'))->addText('');
            }
            // Trier d'abord CRM les autres puis par identifier
            $hierarchicalTreeProperties = $rootProperty->getHierarchicalTreeProperties($namespace);
            foreach($hierarchicalTreeProperties as $tuple){
                $table->addRow();
                $table->addCell(1000, array('valign' => 'center'))->addText($tuple[0]->getIdentifierInNamespace());
                $table->addCell(2000, array('valign' => 'center'))->addText(str_repeat('-  ', $tuple[1]).$tuple[0]->getPropertyVersionForDisplay($tuple[2])->getStandardLabel());
                if(!is_null($tuple[0]->getPropertyVersionForDisplay($tuple[2])->getDomain())) {
                    $table->addCell(2000, array('valign' => 'center'))->addText(html_entity_decode(strip_tags($tuple[0]->getPropertyVersionForDisplay($tuple[2])->getDomain()->getClassVersionForDisplay($tuple[0]->getPropertyVersionForDisplay($tuple[2])->getDomainNamespace())->getInvertedLabel())));
                }
                else{
                    $table->addCell(2000, array('valign' => 'center'))->addText('');
                }

                if(!is_null($tuple[0]->getPropertyVersionForDisplay($tuple[2])->getDomain())) {
                    $table->addCell(2000, array('valign' => 'center'))->addText(html_entity_decode(strip_tags($tuple[0]->getPropertyVersionForDisplay($tuple[2])->getRange()->getClassVersionForDisplay($tuple[0]->getPropertyVersionForDisplay($tuple[2])->getRangeNamespace())->getInvertedLabel())));
                }
                else{
                    $table->addCell(2000, array('valign' => 'center'))->addText('');
                }
            }
        }

        $propertiesVersionWithNSref = new ArrayCollection();

        // Hierarchy properties utilisés NSref
        foreach($namespace->getPropertyAssociations()
                    ->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;}) /*On retire les denied*/
                as $propertyAssociation){
            if($propertyAssociation->getParentPropertyNamespace() != $namespace && $propertyAssociation->getParentPropertyNamespace()->getId() != 4 and !$propertiesVersionWithNSref->contains($propertyAssociation->getParentProperty())){
                if(!$propertiesVersionWithNSref->contains($propertyAssociation->getParentProperty()->getPropertyVersionForDisplay($propertyAssociation->getParentPropertyNamespace()))){
                    $propertiesVersionWithNSref->add($propertyAssociation->getParentProperty()->getPropertyVersionForDisplay($propertyAssociation->getParentPropertyNamespace()));
                }
            }
            if($propertyAssociation->getChildPropertyNamespace() != $namespace && $propertyAssociation->getChildPropertyNamespace()->getId() != 4 and !$propertiesVersionWithNSref->contains($propertyAssociation->getChildProperty())){
                if(!$propertiesVersionWithNSref->contains($propertyAssociation->getChildProperty()->getPropertyVersionForDisplay($propertyAssociation->getChildPropertyNamespace()))){
                    $propertiesVersionWithNSref->add($propertyAssociation->getChildProperty()->getPropertyVersionForDisplay($propertyAssociation->getChildPropertyNamespace()));
                }
            }
        }

        // Relations autres
        foreach($namespace->getEntityAssociations()
                    ->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;}) /*On retire les denied*/
                    ->filter(function($v){return !is_null($v->getSourceProperty());})
                as $propertyAssociation){
            if($propertyAssociation->getSourceNamespaceForVersion() != $namespace && $propertyAssociation->getSourceNamespaceForVersion()->getId() != 4 and !$propertiesVersionWithNSref->contains($propertyAssociation->getSourceProperty())){
                if(!$propertiesVersionWithNSref->contains($propertyAssociation->getSourceProperty()->getPropertyVersionForDisplay($propertyAssociation->getSourceNamespaceForVersion()))){
                    $propertiesVersionWithNSref->add($propertyAssociation->getSourceProperty()->getPropertyVersionForDisplay($propertyAssociation->getSourceNamespaceForVersion()));
                }
            }
            if($propertyAssociation->getTargetNamespaceForVersion() != $namespace && $propertyAssociation->getTargetNamespaceForVersion()->getId() != 4 and !$propertiesVersionWithNSref->contains($propertyAssociation->getTargetProperty())){
                if(!$propertiesVersionWithNSref->contains($propertyAssociation->getTargetProperty()->getPropertyVersionForDisplay($propertyAssociation->getTargetNamespaceForVersion()))){
                    $propertiesVersionWithNSref->add($propertyAssociation->getTargetProperty()->getPropertyVersionForDisplay($propertyAssociation->getTargetNamespaceForVersion()));
                }
            }
        }

        $iterator = $propertiesVersionWithNSref->getIterator();
        $iterator->uasort(function ($a,$b){
            if($a->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7 and $b->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7){
                return strnatcmp($a->getProperty()->getIdentifierInNamespace(), $b->getProperty()->getIdentifierInNamespace());
            }
            elseif($a->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7 and $b->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7){
                // C'est la même chose que le premier if mais plus clair à la lecture.
                return strnatcmp($a->getProperty()->getIdentifierInNamespace(), $b->getProperty()->getIdentifierInNamespace());
            }
            elseif($a->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7 and $b->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7){
                return false;
            }
            elseif($a->getNamespaceForVersion()->getTopLevelNamespace()->getId() != 7 and $b->getNamespaceForVersion()->getTopLevelNamespace()->getId() == 7){
                return true;
            }
        });
        $propertiesVersionWithNSref = new ArrayCollection(iterator_to_array($iterator));

        if($propertiesVersionWithNSref->count() >0){
            $section->addTextBreak();
            $section->addTitle('List of external properties used in '.$namespace->getStandardLabel(), 2);
            $section->addTextBreak();
            $section->addText('Table 4: List of external properties grouped by model and ordered by model and then by property identifier.', 'italic11');
            $table = $section->addTable($fancyTableStyleName2);
            foreach ($propertiesVersionWithNSref as $propertyVersion){
                $table->addRow();
                $table->addCell(1000)->addText($propertyVersion->getProperty()->getIdentifierInNamespace());
                $table->addCell(2000)->addText($propertyVersion->getStandardLabel());
                $table->addCell(2000)->addText($propertyVersion->getNamespaceForVersion()->getTopLevelNamespace());
                $txtps_version = $propertyVersion->getNamespaceForVersion()->getTextProperties()->filter(function($v){return $v->getSystemType()->getId() == 31;});
                if($txtps_version->count() > 0){
                    $table->addCell(2000)->addText($txtps_version->first()->getTextProperty());
                }
                else{
                    $table->addCell(2000)->addText('');
                }
            }
        }

        // Section suivante
        $section = $phpWord->addSection();
        $section->addTitle($namespace->getStandardLabel().' Class Declarations', 2);
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
        // Trier naturellement par identifiant A1 A2 B1 B2...
        $classesVersion = $namespace->getClassVersions()->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;});
        $iterator = $classesVersion->getIterator();
        $iterator->uasort(function ($a,$b){ return strnatcasecmp($a->getClass()->getIdentifierInNamespace(), $b->getClass()->getIdentifierInNamespace());});
        $classesVersion = new ArrayCollection(iterator_to_array($iterator));

        foreach ($classesVersion as $classVersion) {
            $section->addTextBreak(2);
            $section->addTitle($classVersion->getClass()->getIdentifierInNamespace()." ".$classVersion->getStandardLabel(), 3);
            $childAssociations = $classVersion->getClass()->getChildClassAssociations()->filter(function($v) use ($namespace){return $v->getNamespaceForVersion() == $namespace && $v->getParentClassNamespace()->getId() != 4 && (is_null($v->getValidationStatus()) || $v->getValidationStatus()->getId() != 27);});

            if($childAssociations->count() > 0){
                $section->addTextBreak();
                $section->addText('Subclass of: ', "gras");
                // Trier naturellement par identifiant A1 A2 B1 B2...
                $iterator = $childAssociations->getIterator();
                $iterator->uasort(function ($a,$b){ return strnatcasecmp($a->getParentClass()->getIdentifierInNamespace(), $b->getParentClass()->getIdentifierInNamespace());});
                $childAssociations = new ArrayCollection(iterator_to_array($iterator));
                foreach ($childAssociations as $association) {
                    $section->addText($association->getParentClass()->getIdentifierInNamespace() . ' ' . $association->getParentClass()->getClassVersionForDisplay($association->getParentClassNamespace())->getStandardLabel(), null, array('indentation' => array('left' => 1100)));
                }
            }

            $parentAssociations = $classVersion->getClass()->getParentClassAssociations()->filter(function($v) use ($namespace){return $v->getNamespaceForVersion() == $namespace && $v->getChildClassNamespace()->getId() != 4 && (is_null($v->getValidationStatus()) || $v->getValidationStatus()->getId() != 27);});
            if($parentAssociations->count() > 0){
                $section->addTextBreak();
                $section->addText('Superclass of:', "gras");
                // Trier naturellement par identifiant A1 A2 B1 B2...
                $iterator = $parentAssociations->getIterator();
                $iterator->uasort(function ($a,$b){ return strnatcasecmp($a->getChildClass()->getIdentifierInNamespace(), $b->getChildClass()->getIdentifierInNamespace());});
                $parentAssociations = new ArrayCollection(iterator_to_array($iterator));

                foreach ($parentAssociations as $association) {
                    $section->addText($association->getChildClass()->getIdentifierInNamespace() . ' ' . $association->getChildClass()->getClassVersionForDisplay($association->getChildClassNamespace())->getStandardLabel(), null, array('indentation' => array('left' => 1100)));
                }
            }

            foreach ($classVersion->getClass()->getTextProperties()->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;}) as $textProperty){
                //if(in_array($textProperty->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                if(!is_null($textProperty->getNamespaceForVersion()) and $allNamespacesReferences->contains($textProperty->getNamespaceForVersion())){
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
                $section->addText('Scope note:', "gras");
                $string = specialCharactersConversion($textp_scopenote->getTextProperty(), true);
                \PhpOffice\PhpWord\Shared\Html::addHtml($section, $string, true, false);

                // Impossible de mettre l'indentation avec addHtml ou dans l'HTML, phpword ne prévoit pas ça.
                // Récupérer le dernier élément concerné et mettre l'indentation.
                $arrayElements = $section->getElements();
                $lastElement = end($arrayElements);
                if($lastElement->getFontStyle() !== null){
                    $lastElement->getFontStyle()->getParagraph()->setIndentation(array('left' => 1100));
                }
                elseif($lastElement->getParagraphStyle() !== null){
                    foreach (array_reverse($arrayElements) as $element){
                        if($element->getText() != 'Scope note:'){ // On remonte les éléments crées par addHtml jusqu'à l'élément crée par le dernier addText
                            $lastElements[] = $element->getParagraphStyle()->setIndentation(array('left' => 1100));;
                        }
                        else{
                            break;
                        }
                    }
                }
            }

            $i = 0;
            foreach ($classVersion->getClass()->getTextProperties()->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;}) as $textProperty){
                //if(in_array($textProperty->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                if($allNamespacesReferences->contains($textProperty->getNamespaceForVersion())){
                    if($textProperty->getSystemType()->getId() == 7){
                        if($i == 0){
                            $section->addTextBreak();
                            $section->addText('Examples:', "gras");
                            $i++;
                        }
                        $string = specialCharactersConversion($textProperty->getTextProperty(), true);
                        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $string, true, false);

                        // Impossible de mettre l'indentation avec addHtml ou dans l'HTML, phpword ne prévoit pas ça. DONC bricolage by Alex...
                        // Récupérer le dernier élément concerné et mettre l'indentation.
                        $arrayElements = $section->getElements();
                        $lastElement = end($arrayElements);
                        if($lastElement->getFontStyle() !== null){
                            $lastElement->getFontStyle()->getParagraph()->setIndentation(array('left' => 1100));
                        }
                        elseif($lastElement->getParagraphStyle() !== null){
                            foreach (array_reverse($arrayElements) as $element){
                                if($element->getText() != 'Examples:'){ // On remonte les éléments crées par addHtml jusqu'à l'élément crée par le dernier addText
                                    $lastElements[] = $element->getParagraphStyle()->setIndentation(array('left' => 1100));;
                                }
                                else{
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            if($childAssociations->count() > 0 && $optionFol){
                $section->addTextBreak();
                $textRun = $section->addTextRun();
                $textRun->addText('In First Order Logic: ', "gras");
                foreach ($childAssociations as $association) {
                    $section->addText($association->getChildClass()->getIdentifierInNamespace() . '(x) ⇒ ' . $association->getParentClass()->getIdentifierInNamespace(). '(x)', null, array('indentation' => array('left' => 1100)));
                }
            }

            $i = 0;
            $em = $this->getDoctrine()->getManager();
            //$outgoingProperties = $em->getRepository('AppBundle:property')->findOutgoingPropertiesByClassVersionAndNamespacesId($classVersion, $namespace->getLargeSelectedNamespacesId());
            $outgoingProperties = $em->getRepository('AppBundle:property')->findOutgoingPropertiesByClassVersionAndNamespacesId($classVersion, $allNamespacesReferences->map(function($v){return $v->getId();})->toArray());
            foreach($outgoingProperties as $outgoingProperty){
                if($i == 0){
                    $section->addTextBreak();
                    $section->addText('Properties:', "gras");
                    $i++;
                }
                $propertyVersion = $em->getRepository('AppBundle:PropertyVersion')->findOneBy(array("property"=>$outgoingProperty['propertyId'], "namespaceForVersion"=>$outgoingProperty['propertyNamespaceId']));
                $section->addText($propertyVersion->getInvertedLabel().": ".$propertyVersion->getRange()->getIdentifierInNamespace().' '.$propertyVersion->getRange()->getClassVersionForDisplay($propertyVersion->getRangeNamespace())->getStandardLabel(), null, array('indentation' => array('left' => 1100)));
            }
        }

        // Section suivante
        $section = $phpWord->addSection();
        $section->addTitle($namespace->getStandardLabel().' Property Declarations', 2);
        $section->addTextBreak();
        $section->addText('The properties are comprehensively declared in this section using the following format:');
        $section->addTextBreak();
        $section->addListItem('Property names are presented as headings in bold face, preceded by unique property identifiers;');
        $section->addListItem('The line “Domain:” declares the class for which the property is defined;');
        $section->addListItem('The line “Range:” declares the class to which the property points, or that provides the values for the property;');
        $section->addListItem('The line “Superproperty of:” is a cross-reference to any subproperties the property may have;');
        $section->addListItem('The line “Quantification:” declares the possible number of occurrences for domain and range class instances for the property. Possible values are: one to many, many to many, many to one. Quantifications are presented in UML format and in ER format (used by the CIDOC CRM);');
        $section->addListItem('The line “Scope note:” contains the textual definition of the concept the property represents;');
        $section->addListItem('The line “Examples:” contains a bulleted list of examples of instances of this property.');
        $section->addTextBreak(2);

        /** @var PropertyVersion $propertyVersion */
        // Trier naturellement par identifiant A1 A2 B1 B2...
        $propertiesVersion = $namespace->getPropertyVersions()->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;});
        $iterator = $propertiesVersion->getIterator();
        $iterator->uasort(function ($a,$b){ return strnatcasecmp($a->getProperty()->getIdentifierInNamespace(), $b->getProperty()->getIdentifierInNamespace());});
        $propertiesVersion = new ArrayCollection(iterator_to_array($iterator));

        foreach ($propertiesVersion as $propertyVersion) {
            $label = $propertyVersion->getProperty()->getIdentifierInNamespace() . " " . $propertyVersion->getStandardLabel();
            $section->addTitle($label, 3);
            $section->addTextBreak();
            if(!is_null($propertyVersion->getDomain())){
                $label = $propertyVersion->getDomain()->getIdentifierInNamespace()." ".$propertyVersion->getDomain()->getClassVersionForDisplay($propertyVersion->getDomainNamespace())->getStandardLabel();
                $section->addText('Domain: ', "gras");
                $section->addText($label, null, array('indentation' => array('left' => 1100)));
            }
            if(!is_null($propertyVersion->getRange())){
                $label = $propertyVersion->getRange()->getIdentifierInNamespace()." ".$propertyVersion->getRange()->getClassVersionForDisplay($propertyVersion->getRangeNamespace())->getStandardLabel();
                $section->addText('Range: ', "gras");
                $section->addText($label, null, array('indentation' => array('left' => 1100)));
            }
            $associations = $propertyVersion->getProperty()->getChildPropertyAssociations()->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;});
            $parentPropertyVersion = null;
            foreach ($associations as $association){
                //if(in_array($association->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                if($allNamespacesReferences->contains($association->getNamespaceForVersion())){
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
                $section->addText('Subproperty of: ', "gras");
                $section->addText($label, null, array('indentation' => array('left' => 1100)));
            }

            if(!is_null($propertyVersion->getQuantifiersString())){
                $section->addTextBreak();
                $section->addText('Quantification: ',"gras");
                $trun = $section->addTextRun(array('indentation' => array('left' => 1100)));
                if($optionTextCardinality){
                    $trun->addText($propertyVersion->getQuantifiersString());
                }
                if($optionCardinality == "cardinality-opt-uml"){
                    $trun->addText(" (".$propertyVersion->getQuantifiers().')');
                }
                if($optionCardinality == "cardinality-opt-er"){
                    $trun->addText(" (".$propertyVersion->getQuantifiersMerise().')');
                }
            }

            foreach ($propertyVersion->getProperty()->getTextProperties()->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;}) as $textProperty){
                //if(!is_null($textProperty->getNamespaceForVersion()) and in_array($textProperty->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                if(!is_null($textProperty->getNamespaceForVersion()) and $allNamespacesReferences->contains($textProperty->getNamespaceForVersion())){
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
                $section->addText('Scope note:', "gras");
                $string = specialCharactersConversion($textp_scopenote->getTextProperty(), true);
                \PhpOffice\PhpWord\Shared\Html::addHtml($section, $string, true, false);

                // Impossible de mettre l'indentation avec addHtml ou dans l'HTML, phpword ne prévoit pas ça. DONC bricolage by Alex...
                // Récupérer le dernier élément concerné et mettre l'indentation.
                $arrayElements = $section->getElements();
                $lastElement = end($arrayElements);
                if($lastElement->getFontStyle() !== null){
                    $lastElement->getFontStyle()->getParagraph()->setIndentation(array('left' => 1100));
                }
                elseif($lastElement->getParagraphStyle() !== null){
                    foreach (array_reverse($arrayElements) as $element){
                        if($element->getText() != 'Scope note:'){ // On remonte les éléments crées par addHtml jusqu'à l'élément crée par le dernier addText
                            $lastElements[] = $element->getParagraphStyle()->setIndentation(array('left' => 1100));;
                        }
                        else{
                            break;
                        }
                    }
                }
            }

            $i = 0;
            foreach ($propertyVersion->getProperty()->getTextProperties()->filter(function($v){return is_null($v->getValidationStatus()) || !is_null($v->getValidationStatus()) && $v->getValidationStatus()->getId() != 27;}) as $textProperty){
                //if(!is_null($textProperty->getNamespaceForVersion()) and in_array($textProperty->getNamespaceForVersion()->getId(), $namespace->getLargeSelectedNamespacesId())){
                if(!is_null($textProperty->getNamespaceForVersion()) and $allNamespacesReferences->contains($textProperty->getNamespaceForVersion())){
                    if($textProperty->getSystemType()->getId() == 7){
                        if($i == 0){
                            $section->addTextBreak();
                            $section->addText('Examples:', "gras");
                            $i++;
                        }
                        $string = specialCharactersConversion($textProperty->getTextProperty(), true);
                        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $string, true, false);

                        // Impossible de mettre l'indentation avec addHtml ou dans l'HTML, phpword ne prévoit pas ça. DONC bricolage by Alex...
                        // Récupérer les derniers éléments concernés et mettre l'indentation.
                        $arrayElements = $section->getElements();
                        $lastElement = end($arrayElements);
                        if($lastElement->getFontStyle() !== null){
                            $lastElement->getFontStyle()->getParagraph()->setIndentation(array('left' => 1100));
                        }
                        elseif($lastElement->getParagraphStyle() !== null){
                            foreach (array_reverse($arrayElements) as $element){
                                if($element->getText() != 'Examples:'){ // On remonte les éléments crées par addHtml jusqu'à l'élément crée par le dernier addText
                                    $lastElements[] = $element->getParagraphStyle()->setIndentation(array('left' => 1100));;
                                }
                                else{
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            if($optionFol){
                $section->addTextBreak();
                $section->addText('In First Order Logic:', "gras");
                if(!is_null($propertyVersion->getDomain())){
                    $section->addText($propertyVersion->getProperty()->getIdentifierInNamespace().'(x,y) ⇒ '.$propertyVersion->getDomain()->getIdentifierInNamespace().'(x)', null, array('indentation' => array('left' => 1100)));
                }
                if(!is_null($propertyVersion->getRange())){
                    $section->addText($propertyVersion->getProperty()->getIdentifierInNamespace().'(x,y) ⇒ '.$propertyVersion->getRange()->getIdentifierInNamespace().'(y)', null, array('indentation' => array('left' => 1100)));
                }
                if(!is_null($parentPropertyVersion)){
                    $section->addText($propertyVersion->getProperty()->getIdentifierInNamespace().'(x,y) ⇒ '.$parentPropertyVersion->getProperty()->getIdentifierInNamespace().'(x,y)', null, array('indentation' => array('left' => 1100)));
                }
            }
            $section->addTextBreak(2);
        }

        // Saving the document as OOXML file...
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');

        // Create a temporal file in the system
        $fileName = 'namespace-'.preg_replace('/[^a-zA-Z0-9\-\._]/','',$namespace->getStandardLabel()).'.docx';
        //var_dump($fileName); die;
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

    /**
     * @Route("/namespace/{id}/makevisible", name="namespace_make_visible", requirements={"id"="^([0-9]+)|(namespaceID){1}$"})
     * @param OntoNamespace $namespace
     * @return JsonResponse
     */
    public function makeVisibleAction(OntoNamespace $namespace)
    {
        $namespace->setIsVisible(true);
        $em = $this->getDoctrine()->getManager();
        $em->persist($namespace);
        $em->flush();

        return new JsonResponse(null, 204);
    }
}