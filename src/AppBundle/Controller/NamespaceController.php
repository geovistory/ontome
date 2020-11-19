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
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\ReferencedNamespaceAssociation;
use AppBundle\Entity\TextProperty;
use AppBundle\Form\NamespaceForm;
use AppBundle\Form\NamespacePublicationForm;
use AppBundle\Form\NamespaceQuickAddForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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

}