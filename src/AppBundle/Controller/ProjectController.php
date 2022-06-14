<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 28/06/2017
 * Time: 15:50
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
use AppBundle\Entity\ProjectAssociation;
use AppBundle\Entity\Property;
use AppBundle\Entity\PropertyAssociation;
use AppBundle\Entity\PropertyVersion;
use AppBundle\Entity\ReferencedNamespaceAssociation;
use AppBundle\Entity\TextProperty;
use AppBundle\Entity\User;
use AppBundle\Entity\UserProjectAssociation;
use AppBundle\Form\ImportNamespaceForm;
use AppBundle\Form\ProjectQuickAddForm;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class ProjectController  extends Controller
{
    /**
     * @Route("/project")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $projects = $em->getRepository('AppBundle:Project')
            ->findAll();

        return $this->render('project/list.html.twig', [
            'projects' => $projects
        ]);
    }

    /**
     * @Route("/project/new", name="project_new_user")
     */
    public function newUserProjectAction(Request $request)
    {

        $tokenInterface = $this->get('security.token_storage')->getToken();
        $isAuthenticated = $tokenInterface->isAuthenticated();
        if(!$isAuthenticated) throw new AccessDeniedException('You must be an authenticated user to access this page.');

        $project = new Project();

        $em = $this->getDoctrine()->getManager();
        $systemTypeDescription = $em->getRepository('AppBundle:SystemType')->find(16); //systemType 16 = Description

        $description = new TextProperty();
        $description->setProject($project);
        $description->setSystemType($systemTypeDescription);
        $description->setCreator($this->getUser());
        $description->setModifier($this->getUser());
        $description->setCreationTime(new \DateTime('now'));
        $description->setModificationTime(new \DateTime('now'));

        $project->addTextProperty($description);

        $userProjectAssociation = new UserProjectAssociation();

        $now = new \DateTime();

        $project->setCreator($this->getUser());
        $project->setModifier($this->getUser());

        $projectLabel = new Label();
        $projectLabel->setProject($project);
        $projectLabel->setIsStandardLabelForLanguage(true);
        $projectLabel->setCreator($this->getUser());
        $projectLabel->setModifier($this->getUser());
        $projectLabel->setCreationTime(new \DateTime('now'));
        $projectLabel->setModificationTime(new \DateTime('now'));

        $project->addLabel($projectLabel);

        $form = $this->createForm(ProjectQuickAddForm::class, $project);
        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $project = $form->getData();
            $project->setStartDate($now);
            $project->setCreator($this->getUser());
            $project->setModifier($this->getUser());
            $project->setCreationTime(new \DateTime('now'));
            $project->setModificationTime(new \DateTime('now'));

            $userProjectAssociation->setUser($this->getUser());
            $userProjectAssociation->setProject($project);
            $userProjectAssociation->setPermission(1);
            $userProjectAssociation->setNotes('Project created by user via OntoME form.');
            $userProjectAssociation->setStartDate($now);
            $userProjectAssociation->setCreator($this->getUser());
            $userProjectAssociation->setModifier($this->getUser());
            $userProjectAssociation->setCreationTime(new \DateTime('now'));
            $userProjectAssociation->setModificationTime(new \DateTime('now'));

            $this->getUser()->setCurrentActiveProject($project);

            $em = $this->getDoctrine()->getManager();
            $em->persist($project);
            $em->persist($userProjectAssociation);
            $em->persist($this->getUser());
            $em->flush();

            return $this->redirectToRoute('user_show', [
                'id' =>$userProjectAssociation->getUser()->getId()
            ]);

        }

        $em = $this->getDoctrine()->getManager();


        return $this->render('project/new.html.twig', [
            'errors' => $form->getErrors(),
            'project' => $project,
            'projectForm' => $form->createView()
        ]);
    }

    /**
     * @Route("/project/{id}", name="project_show", requirements={"id"="^[0-9]+$"})
     * @param string $id
     * @return Response the rendered template
     */
    public function showAction(Project $project)
    {
        $em = $this->getDoctrine()->getManager();

        return $this->render('project/show.html.twig', array(
            'project' => $project
        ));
    }

    /**
     * @Route("/project/{id}/edit", name="project_edit", requirements={"id"="^[0-9]+$"})
     * @param Project $project
     * @return Response the rendered template
     */
    public function editAction(Project $project, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $project);

        $em = $this->getDoctrine()->getManager();

        $users = $em->getRepository('AppBundle:User')
            ->findAllNotInProject($project);

        $namespacesPublicProject = $em->getRepository('AppBundle:OntoNamespace')
            ->findNamespacesInPublicProject();

        $formImport = $this->createForm(ImportNamespaceForm::class);

        $formImport->handleRequest($request);
        if ($formImport->isSubmitted() && $formImport->isValid()) {
            $file = $formImport['uploadXMLFile']->getData();
            if($file->getClientMimeType() == "text/xml"){
                $nodeXmlNamespace = @simplexml_load_file($file->getPathname());
                if($nodeXmlNamespace !== false){
                    $dom=new \DOMDocument();
                    $dom->loadXML($nodeXmlNamespace->asXML());
                    // Import XSD
                    $pathXMLSchema = "../web/documents/schemaImportXmlwithReferences.xml";
                    $simpleXMLElementSchema = @simplexml_load_file($pathXMLSchema);
                    if($dom->schemaValidateSource($simpleXMLElementSchema->asXML())){
                        //Schema valide - on commence donc à mettre dans l'entité Namespace version (non root)
                        // Vérifier si le namespace root existe sinon on arrête tout.
                        $namespaceRoot = $project->getManagedNamespaces()
                            ->filter(function($v){return $v->getIsTopLevelNamespace();})->first();
                        if(!$namespaceRoot){
                            echo "Il faut créer un namespace root";
                            die;
                        }
                        $systemTypeScopeNote = $em->getRepository('AppBundle:SystemType')->find(1); //systemType 1 = scope note
                        $systemTypeExample = $em->getRepository('AppBundle:SystemType')->find(7); // example
                        $systemTypeVersion = $em->getRepository('AppBundle:SystemType')->find(31); //owl:versionInfo
                        $systemTypeContributors = $em->getRepository('AppBundle:SystemType')->find(2); //contributor

                        $newNamespaceVersion = new OntoNamespace();
                        $newNamespaceVersion->setTopLevelNamespace($namespaceRoot);

                        // Label
                        $namespaceLabel = new Label();
                        $namespaceLabel->setIsStandardLabelForLanguage(true);
                        $namespaceLabel->setLabel((string)$nodeXmlNamespace->standardLabel);
                        $namespaceLabel->setLanguageIsoCode((string)$nodeXmlNamespace->standardLabel->attributes()->lang);
                        $namespaceLabel->setCreator($this->getUser());
                        $namespaceLabel->setModifier($this->getUser());
                        $namespaceLabel->setCreationTime(new \DateTime('now'));
                        $namespaceLabel->setModificationTime(new \DateTime('now'));
                        $newNamespaceVersion->addLabel($namespaceLabel);
                        $em->persist($namespaceLabel);

                        // StandardLabel
                        $newNamespaceVersion->setStandardLabel((string)$nodeXmlNamespace->standardLabel);

                        // Version
                        $txtpVersion = new TextProperty();
                        $txtpVersion->setTextProperty((string)$nodeXmlNamespace->version);
                        $txtpVersion->setSystemType($systemTypeVersion);
                        $txtpVersion->setCreator($this->getUser());
                        $txtpVersion->setModifier($this->getUser());
                        $txtpVersion->setCreationTime(new \DateTime('now'));
                        $txtpVersion->setModificationTime(new \DateTime('now'));
                        $newNamespaceVersion->addTextProperty($txtpVersion);
                        $em->persist($txtpVersion);

                        // Contributors
                        if(!empty((string)$nodeXmlNamespace->contributors)){
                            $txtpContributors = new TextProperty();
                            $txtpContributors->setTextProperty((string)$nodeXmlNamespace->contributors);
                            $txtpContributors->setSystemType($systemTypeContributors);
                            $txtpContributors->setCreator($this->getUser());
                            $txtpContributors->setModifier($this->getUser());
                            $txtpContributors->setCreationTime(new \DateTime('now'));
                            $txtpContributors->setModificationTime(new \DateTime('now'));
                            $newNamespaceVersion->addTextProperty($txtpContributors);
                            $em->persist($txtpContributors);
                        }

                        // Références
                        $idsReferences = new ArrayCollection();
                        foreach($nodeXmlNamespace->referenceNamespace as $keyRefNs => $nodeXmlReferenceNamespace){
                            if(!$idsReferences->contains((string)$nodeXmlReferenceNamespace)){
                                $idsReferences->add((integer)$nodeXmlReferenceNamespace);
                            }
                            $referencedNamespaceAssociation = new ReferencedNamespaceAssociation();
                            $referencedNamespaceAssociation->setNamespace($newNamespaceVersion);
                            $referencedNamespace = $em->getRepository('AppBundle:OntoNamespace')->findOneBy(array("id" => (integer)$nodeXmlReferenceNamespace));
                            if(is_null($referencedNamespace)){
                                echo "Le namespace de référence ".(integer)$nodeXmlReferenceNamespace." n'a pas été trouvé.";
                                die;
                            }
                            if($referencedNamespace->getIsTopLevelNamespace()){
                                echo "Le namespace  ".(integer)$nodeXmlReferenceNamespace." est root et ne peut etre utilisé comme reference.";
                                die;
                            }
                            $referencedNamespaceAssociation->setReferencedNamespace($referencedNamespace);
                            $newNamespaceVersion->addReferencedNamespaceAssociation($referencedNamespaceAssociation);
                            $referencedNamespaceAssociation->setCreator($this->getUser());
                            $referencedNamespaceAssociation->setModifier($this->getUser());
                            $referencedNamespaceAssociation->setCreationTime(new \DateTime('now'));
                            $referencedNamespaceAssociation->setModificationTime(new \DateTime('now'));
                            $em->persist($referencedNamespaceAssociation);
                        }

                        $nodeXmlClasses = $nodeXmlNamespace->classes;
                        $nodeXmlProperties = $nodeXmlNamespace->properties;
                        // Vérificateurs
                        $arrayIdentifiers = new ArrayCollection();

                        foreach($nodeXmlClasses->children() as $key => $nodeXmlClass){
                            // Pour vérification de l'unicité des identifiers
                            if(!$arrayIdentifiers->contains((string)$nodeXmlClass->identifierInNamespace)){
                                $arrayIdentifiers->add((string)$nodeXmlClass->identifierInNamespace);
                            }
                            else{
                                echo "2 classes au moins ont le même identifiants";
                                die;
                            }
                            // Class "Root"
                            $class = null;
                            // Vérifier si la classe n'existe déjà pas dans un des namespaces du root namespace (comparaison par identifierInNamespace)
                            foreach($namespaceRoot->getChildVersions() as $childNamespace){
                                foreach($childNamespace->getClasses() as $tempClass){
                                    if($tempClass->getIdentifierInNamespace() == (string)$nodeXmlClass->identifierInNamespace){
                                        $class = $tempClass;
                                        break; // Inutile d'aller plus loin la première vraie égalité suffit
                                    }
                                }
                            }

                            if(is_null($class)){
                                // On a donc une nouvelle classe
                                $class = new OntoClass();
                                $class->setIdentifierInNamespace((string)$nodeXmlClass->identifierInNamespace);
                                $class->setIsManualIdentifier(is_null($newNamespaceVersion->getTopLevelNamespace()->getClassPrefix()));
                                $class->setCreator($this->getUser());
                                $class->setModifier($this->getUser());
                                $class->setCreationTime(new \DateTime('now'));
                                $class->setModificationTime(new \DateTime('now'));
                                $em->persist($class);
                            }

                            // Class Version
                            $newClassVersion = new OntoClassVersion();
                            $newClassVersion->setClass($class);
                            $newClassVersion->setNamespaceForVersion($newNamespaceVersion);
                            $newClassVersion->setCreator($this->getUser());
                            $newClassVersion->setModifier($this->getUser());
                            $newClassVersion->setCreationTime(new \DateTime('now'));
                            $newClassVersion->setModificationTime(new \DateTime('now'));

                            $class->addClassVersion($newClassVersion);
                            $em->persist($newClassVersion);

                            // Scope note
                            $scopeNote = new TextProperty();
                            $scopeNote->setClass($class);
                            $scopeNote->setNamespaceForVersion($newNamespaceVersion);
                            $scopeNote->setTextProperty((string)$nodeXmlClass->textProperties->scopeNote);
                            $scopeNote->setLanguageIsoCode((string)$nodeXmlClass->textProperties->scopeNote->attributes()->lang);
                            $scopeNote->setSystemType($systemTypeScopeNote);
                            $scopeNote->setCreator($this->getUser());
                            $scopeNote->setModifier($this->getUser());
                            $scopeNote->setCreationTime(new \DateTime('now'));
                            $scopeNote->setModificationTime(new \DateTime('now'));

                            $class->addTextProperty($scopeNote);
                            $em->persist($scopeNote);

                            // Examples
                            foreach($nodeXmlClass->textProperties->example as $keyEx => $nodeXmlExample){
                                $example = new TextProperty();
                                $example->setClass($class);
                                $example->setNamespaceForVersion($newNamespaceVersion);
                                $example->setTextProperty("<p>".(string)$nodeXmlExample."</p>");
                                $example->setLanguageIsoCode((string)$nodeXmlExample->attributes()->lang);
                                $example->setSystemType($systemTypeExample);
                                $example->setCreator($this->getUser());
                                $example->setModifier($this->getUser());
                                $example->setCreationTime(new \DateTime('now'));
                                $example->setModificationTime(new \DateTime('now'));

                                $class->addTextProperty($example);
                                $em->persist($example);
                            }

                            // Label
                            $langs = new ArrayCollection();
                            $defaultStandardLabelEn = null;
                            $defaultStandardLabelFr = null;
                            $defaultStandardLabel = null;
                            foreach($nodeXmlClass->standardLabel as $keyLabel => $nodeXmlLabel){
                                $classLabel = new Label();
                                $classLabel->setClass($class);
                                $classLabel->setNamespaceForVersion($newNamespaceVersion);
                                $classLabel->setLabel((string)$nodeXmlLabel);
                                $classLabel->setLanguageIsoCode((string)$nodeXmlLabel->attributes()->lang);
                                if(!$langs->contains((string)$nodeXmlLabel->attributes()->lang)){
                                    $langs->add((string)$nodeXmlLabel->attributes()->lang);
                                    $classLabel->setIsStandardLabelForLanguage(true);
                                }
                                else{
                                    $classLabel->setIsStandardLabelForLanguage(false);
                                }
                                $classLabel->setCreator($this->getUser());
                                $classLabel->setModifier($this->getUser());
                                $classLabel->setCreationTime(new \DateTime('now'));
                                $classLabel->setModificationTime(new \DateTime('now'));

                                $class->addLabel($classLabel);
                                $em->persist($classLabel);


                                if(is_null($defaultStandardLabelEn) || $classLabel->getLanguageIsoCode() == "en"){
                                    $defaultStandardLabelEn = (string)$nodeXmlLabel;
                                }
                                if(is_null($defaultStandardLabelFr) || $classLabel->getLanguageIsoCode() == "fr"){
                                    $defaultStandardLabelFr = (string)$nodeXmlLabel;
                                }
                                if(is_null($defaultStandardLabel)){
                                    $defaultStandardLabel = (string)$nodeXmlLabel;
                                }
                            }
                            if(!is_null($defaultStandardLabelEn)){
                                $newClassVersion->setStandardLabel($defaultStandardLabelEn);
                            }
                            elseif(!is_null($defaultStandardLabelFr)){
                                $newClassVersion->setStandardLabel($defaultStandardLabelEn);
                            }
                            else{
                                $newClassVersion->setStandardLabel($defaultStandardLabel);
                            }
                        }

                        foreach($nodeXmlProperties->children() as $key => $nodeXmlProperty){
                            if(!$arrayIdentifiers->contains((string)$nodeXmlProperty->identifierInNamespace)){
                                $arrayIdentifiers->add((string)$nodeXmlProperty->identifierInNamespace);
                            }
                            else{
                                echo "2 properties au moins ont le même identifiants";
                                die;
                            }

                            // Property "Root"
                            $property = null;
                            // Vérifier si la propriété n'existe déjà pas dans un des namespaces du root namespace (comparaison par identifierInNamespace)
                            foreach($namespaceRoot->getChildVersions() as $childNamespace){
                                foreach($childNamespace->getProperties() as $tempProperty){
                                    if($tempProperty->getIdentifierInNamespace() == (string)$nodeXmlProperty->identifierInNamespace){
                                        $property = $tempProperty;
                                        break; // Inutile d'aller plus loin la première vraie égalité suffit
                                    }
                                }
                            }

                            if(is_null($property)){
                                // On a donc une nouvelle propriété
                                $property = new Property();
                                $property->setIdentifierInNamespace((string)$nodeXmlProperty->identifierInNamespace);
                                $property->setIsManualIdentifier(is_null($newNamespaceVersion->getTopLevelNamespace()->getPropertyPrefix()));
                                $property->setCreator($this->getUser());
                                $property->setModifier($this->getUser());
                                $property->setCreationTime(new \DateTime('now'));
                                $property->setModificationTime(new \DateTime('now'));
                                $em->persist($property);
                            }

                            // Property version
                            $newPropertyVersion = new PropertyVersion();
                            $newPropertyVersion->setProperty($property);
                            $newPropertyVersion->setNamespaceForVersion($newNamespaceVersion);

                            // Quelle version Domain ?
                            $xmlDomainNamespace = $nodeXmlProperty->hasDomain->attributes()->referenceNamespace;
                            //Si attribut referenceNamespace existe, utiliser cet id, sinon ce nouveau namespace
                            if(!is_null($xmlDomainNamespace)){
                                $domainNamespace = $em->getRepository("AppBundle:OntoNamespace")
                                    ->findOneBy(array("id" => (integer)$xmlDomainNamespace));
                                if(!$idsReferences->contains((integer)$xmlDomainNamespace)){
                                    echo "Un namespace de référence pour hasDomain n'a pas été déclaré avec la balise referenceNamespace";
                                    die;
                                }
                            }
                            else{
                                $domainNamespace = $newNamespaceVersion;
                            }
                            $newPropertyVersion->setDomainNamespace($domainNamespace);

                            // Trouver la classe
                            $domain = null;
                            foreach($domainNamespace->getClasses() as $tempClass){
                                if($tempClass->getIdentifierInNamespace() == (string)$nodeXmlProperty->hasDomain){
                                    $domain = $tempClass;
                                    break;
                                }
                            }
                            if(is_null($domain)){
                                echo (string)$nodeXmlProperty->identifierInNamespace." Domain ".(string)$nodeXmlProperty->hasDomain." n'a pas été trouvé";
                                die;
                            }
                            $newPropertyVersion->setDomain($domain);

                            // Quelle version Range ?
                            $xmlRangeNamespace = $nodeXmlProperty->hasRange->attributes()->referenceNamespace;
                            //Si attribut referenceNamespace existe, utiliser cet id, sinon ce nouveau namespace
                            if(!is_null($xmlRangeNamespace)){
                                $rangeNamespace = $em->getRepository("AppBundle:OntoNamespace")
                                    ->findOneBy(array("id" => (integer)$xmlRangeNamespace));
                                if(!$idsReferences->contains((integer)$xmlDomainNamespace)){
                                    echo "Un namespace de référence pour hasRange n'a pas été déclaré avec la balise referenceNamespace";
                                    die;
                                }
                            }
                            else{
                                $rangeNamespace = $newNamespaceVersion;
                            }
                            $newPropertyVersion->setRangeNamespace($rangeNamespace);

                            // Trouver la classe
                            $range = null;
                            foreach($rangeNamespace->getClasses() as $tempClass){
                                if($tempClass->getIdentifierInNamespace() == (string)$nodeXmlProperty->hasRange){
                                    $range = $tempClass;
                                    break;
                                }
                            }
                            if(is_null($range)){
                                echo (string)$nodeXmlProperty->identifierInNamespace." Range ".(string)$nodeXmlProperty->hasRange." n'a pas été trouvé";
                                die;
                            }
                            $newPropertyVersion->setRange($range);

                            $domainMinQuantifier = null;
                            // La balise est dans le XML ?
                            if(isset($nodeXmlProperty->domainInstancesMinQuantifier)){
                                //Inutile de vérifier sa valeur, le schéma XSD l'a déjà fait
                                if((string)$nodeXmlProperty->domainInstancesMinQuantifier == 'n'){
                                    $domainMinQuantifier = -1;
                                }
                                else{
                                    $domainMinQuantifier = (integer)$nodeXmlProperty->domainInstancesMinQuantifier;
                                }
                            }
                            $newPropertyVersion->setDomainMinQuantifier($domainMinQuantifier);

                            $domainMaxQuantifier = null;
                            // La balise est dans le XML ?
                            if(isset($nodeXmlProperty->domainInstancesMaxQuantifier)){
                                //Inutile de vérifier sa valeur, le schéma XSD l'a déjà fait
                                if((string)$nodeXmlProperty->domainInstancesMaxQuantifier == 'n'){
                                    $domainMaxQuantifier = -1;
                                }
                                else{
                                    $domainMaxQuantifier = (integer)$nodeXmlProperty->domainInstancesMaxQuantifier;
                                }
                            }
                            $newPropertyVersion->setDomainMaxQuantifier($domainMaxQuantifier);

                            $rangeMinQuantifier = null;
                            // La balise est dans le XML ?
                            if(isset($nodeXmlProperty->rangeInstancesMinQuantifier)){
                                //Inutile de vérifier sa valeur, le schéma XSD l'a déjà fait
                                if((string)$nodeXmlProperty->rangeInstancesMinQuantifier == 'n'){
                                    $rangeMinQuantifier = -1;
                                }
                                else{
                                    $rangeMinQuantifier = (integer)$nodeXmlProperty->rangeInstancesMinQuantifier;
                                }
                            }
                            $newPropertyVersion->setRangeMinQuantifier($rangeMinQuantifier);

                            $rangeMaxQuantifier = null;
                            // La balise est dans le XML ?
                            if(isset($nodeXmlProperty->rangeInstancesMaxQuantifier)){
                                //Inutile de vérifier sa valeur, le schéma XSD l'a déjà fait
                                if((string)$nodeXmlProperty->rangeInstancesMaxQuantifier == 'n'){
                                    $rangeMaxQuantifier = -1;
                                }
                                else{
                                    $rangeMaxQuantifier = (integer)$nodeXmlProperty->rangeInstancesMaxQuantifier;
                                }
                            }
                            $newPropertyVersion->setRangeMaxQuantifier($rangeMaxQuantifier);

                            $newPropertyVersion->setCreator($this->getUser());
                            $newPropertyVersion->setModifier($this->getUser());
                            $newPropertyVersion->setCreationTime(new \DateTime('now'));
                            $newPropertyVersion->setModificationTime(new \DateTime('now'));

                            // Label
                            $langs = new ArrayCollection();
                            $defaultStandardLabelEn = null;
                            $defaultStandardLabelFr = null;
                            $defaultStandardLabel = null;
                            foreach($nodeXmlProperty->label as $keyLabel => $nodeXmlLabel){
                                $propertyLabel = new Label();
                                $propertyLabel->setProperty($property);
                                $propertyLabel->setNamespaceForVersion($newNamespaceVersion);
                                $propertyLabel->setLabel((string)$nodeXmlLabel->standardLabel);
                                if(!empty((string)$nodeXmlLabel->inverseLabel)){
                                    $propertyLabel->setInverseLabel((string)$nodeXmlLabel->inverseLabel);
                                }
                                $propertyLabel->setLanguageIsoCode((string)$nodeXmlLabel->attributes()->lang);
                                if(!$langs->contains((string)$nodeXmlLabel->attributes()->lang)){
                                    $langs->add((string)$nodeXmlLabel->attributes()->lang);
                                    $propertyLabel->setIsStandardLabelForLanguage(true);
                                }
                                else{
                                    $propertyLabel->setIsStandardLabelForLanguage(false);
                                }
                                $propertyLabel->setCreator($this->getUser());
                                $propertyLabel->setModifier($this->getUser());
                                $propertyLabel->setCreationTime(new \DateTime('now'));
                                $propertyLabel->setModificationTime(new \DateTime('now'));

                                $property->addLabel($propertyLabel);
                                $em->persist($propertyLabel);

                                if(is_null($defaultStandardLabelEn) || $propertyLabel->getLanguageIsoCode() == "en"){
                                    $defaultStandardLabelEn = (string)$nodeXmlLabel->standardLabel;
                                    if(!is_null($propertyLabel->getInverseLabel())){
                                        $defaultStandardLabelEn.= " (".$propertyLabel->getInverseLabel().")";
                                    }
                                }
                                if(is_null($defaultStandardLabelFr) || $propertyLabel->getLanguageIsoCode() == "fr"){
                                    $defaultStandardLabelFr = (string)$nodeXmlLabel->standardLabel;
                                    if(!is_null($propertyLabel->getInverseLabel())){
                                        $defaultStandardLabelFr.= " (".$propertyLabel->getInverseLabel().")";
                                    }
                                }
                                if(is_null($defaultStandardLabel)){
                                    $defaultStandardLabel = (string)$nodeXmlLabel->standardLabel;
                                    if(!is_null($propertyLabel->getInverseLabel())){
                                        $defaultStandardLabel.= " (".$propertyLabel->getInverseLabel().")";
                                    }
                                }
                            }
                            if(!is_null($defaultStandardLabelEn)){
                                $newPropertyVersion->setStandardLabel($defaultStandardLabelEn);
                            }
                            elseif(!is_null($defaultStandardLabelFr)){
                                $newPropertyVersion->setStandardLabel($defaultStandardLabelEn);
                            }
                            else{
                                $newPropertyVersion->setStandardLabel($defaultStandardLabel);
                            }

                            $property->addPropertyVersion($newPropertyVersion);
                            $em->persist($newPropertyVersion);

                            // Scope note
                            $scopeNote = new TextProperty();
                            $scopeNote->setProperty($property);
                            $scopeNote->setNamespaceForVersion($newNamespaceVersion);
                            $scopeNote->setTextProperty((string)$nodeXmlProperty->textProperties->scopeNote);
                            $scopeNote->setLanguageIsoCode((string)$nodeXmlProperty->textProperties->scopeNote->attributes()->lang);
                            $scopeNote->setSystemType($systemTypeScopeNote);
                            $scopeNote->setCreator($this->getUser());
                            $scopeNote->setModifier($this->getUser());
                            $scopeNote->setCreationTime(new \DateTime('now'));
                            $scopeNote->setModificationTime(new \DateTime('now'));

                            $property->addTextProperty($scopeNote);
                            $em->persist($scopeNote);

                            // Examples
                            foreach($nodeXmlProperty->textProperties->example as $keyEx => $nodeXmlExample){
                                $example = new TextProperty();
                                $example->setProperty($property);
                                $example->setNamespaceForVersion($newNamespaceVersion);
                                $example->setTextProperty("<p>".(string)$nodeXmlExample."</p>");
                                $example->setLanguageIsoCode((string)$nodeXmlExample->attributes()->lang);
                                $example->setSystemType($systemTypeExample);
                                $example->setCreator($this->getUser());
                                $example->setModifier($this->getUser());
                                $example->setCreationTime(new \DateTime('now'));
                                $example->setModificationTime(new \DateTime('now'));
                                $property->addTextProperty($example);
                                $em->persist($example);
                            }
                        }

                        // Les entités ont été créées. Maintenant on passe aux relations hierarchiques/autres
                        foreach($nodeXmlClasses->children() as $key => $nodeXmlClass) {

                            //SubClassOf
                            if (!empty($nodeXmlClass->subClassOf)) {
                                $classAssociation = new ClassAssociation();
                                // Quelle version Parent ?
                                $xmlParentClassNamespace = $nodeXmlClass->subClassOf->attributes()->referenceNamespace;
                                //Si attribut referenceNamespace existe, utiliser cet id, sinon ce nouveau namespace
                                if (!is_null($xmlParentClassNamespace)) {
                                    $parentClassNamespace = $em->getRepository("AppBundle:OntoNamespace")
                                        ->findOneBy(array("id" => (integer)$xmlParentClassNamespace));
                                    if(!$idsReferences->contains((integer)$xmlParentClassNamespace)){
                                        echo "Un namespace de référence pour subclassOf n'a pas été déclaré avec la balise referenceNamespace";
                                        die;
                                    }
                                } else {
                                    $parentClassNamespace = $newNamespaceVersion;
                                }
                                // Trouver la classe parente
                                $parentClass = null;
                                foreach ($parentClassNamespace->getClasses() as $tempClass) {
                                    if ($tempClass->getIdentifierInNamespace() == (string)$nodeXmlClass->subClassOf) {
                                        $parentClass = $tempClass;
                                        break;
                                    }
                                }
                                if (is_null($parentClass)) {
                                    echo (string)$nodeXmlClass->identifierInNamespace." Parent class " . (string)$nodeXmlClass->subClassOf . " (".$parentClassNamespace->getId().") n'a pas été trouvé";
                                    die;
                                }

                                // Trouver la classe enfante
                                $childClass = null;
                                foreach ($newNamespaceVersion->getClasses() as $tempClass) {
                                    if ($tempClass->getIdentifierInNamespace() == (string)$nodeXmlClass->identifierInNamespace) {
                                        $childClass = $tempClass;
                                        break;
                                    }
                                }
                                if (is_null($childClass)) {
                                    echo (string)$nodeXmlClass->identifierInNamespace." Child class " . (string)$nodeXmlClass->identifierInNamespace . " n'a pas été trouvé";
                                    die;
                                }

                                //TODO Justification ClassAssociation?
                                $classAssociation->setParentClass($parentClass);
                                $classAssociation->setParentClassNamespace($parentClassNamespace);
                                $classAssociation->setChildClass($childClass);
                                $classAssociation->setChildClassNamespace($newNamespaceVersion);

                                $classAssociation->setNamespaceForVersion($newNamespaceVersion);

                                $classAssociation->setCreator($this->getUser());
                                $classAssociation->setModifier($this->getUser());
                                $classAssociation->setCreationTime(new \DateTime('now'));
                                $classAssociation->setModificationTime(new \DateTime('now'));

                                $newNamespaceVersion->addClassAssociation($classAssociation);
                                $em->persist($classAssociation);
                            }

                            //equivalentClass or disjointWith
                            foreach ($nodeXmlClass->children() as $key => $value) {
                                if ($key == "equivalentClass" || $key == "disjointWith") {
                                    $entityAssociation = new EntityAssociation();
                                    // Quelle version Target ?
                                    if($key == "equivalentClass"){
                                        $nodeXmlEntityAssociation = $nodeXmlClass->equivalentClass;
                                    }
                                    if($key == "disjointWith"){
                                        $nodeXmlEntityAssociation = $nodeXmlClass->disjointWith;
                                    }
                                    $xmlTargetClassNamespace = $nodeXmlEntityAssociation->attributes()->referenceNamespace;
                                    //Si attribut referenceNamespace existe, utiliser cet id, sinon ce nouveau namespace
                                    if (!is_null($xmlTargetClassNamespace)) {
                                        $targetClassNamespace = $em->getRepository("AppBundle:OntoNamespace")
                                            ->findOneBy(array("id" => (integer)$xmlTargetClassNamespace));
                                        if(!$idsReferences->contains((integer)$xmlTargetClassNamespace)){
                                            echo "Un namespace de référence pour targetClass equivalentClass ou disjointWith n'a pas été déclaré avec la balise referenceNamespace";
                                            die;
                                        }
                                    } else {
                                        $targetClassNamespace = $newNamespaceVersion;
                                    }
                                    // Trouver la classe cible
                                    $targetClass = null;
                                    foreach ($targetClassNamespace->getClasses() as $tempClass) {
                                        if ($tempClass->getIdentifierInNamespace() == (string)$nodeXmlEntityAssociation) {
                                            $targetClass = $tempClass;
                                            break;
                                        }
                                    }
                                    if (is_null($targetClass)) {
                                        echo (string)$nodeXmlClass->identifierInNamespace . " Target class " . (string)$nodeXmlEntityAssociation . " n'a pas été trouvé";
                                        die;
                                    }

                                    // Trouver la classe source
                                    $sourceClass = null;
                                    foreach ($newNamespaceVersion->getClasses() as $tempClass) {
                                        if ($tempClass->getIdentifierInNamespace() == (string)$nodeXmlClass->identifierInNamespace) {
                                            $sourceClass = $tempClass;
                                            break;
                                        }
                                    }
                                    if (is_null($sourceClass)) {
                                        echo (string)$nodeXmlClass->identifierInNamespace . " Source class " . (string)$nodeXmlClass->identifierInNamespace . " n'a pas été trouvé";
                                        die;
                                    }
                                    $entityAssociation->setSourceClass($sourceClass);
                                    $entityAssociation->setSourceNamespaceForVersion($newNamespaceVersion);
                                    $entityAssociation->setTargetClass($targetClass);
                                    $entityAssociation->setTargetNamespaceForVersion($targetClassNamespace);

                                    $entityAssociation->setNamespaceForVersion($newNamespaceVersion);

                                    $entityAssociation->setCreator($this->getUser());
                                    $entityAssociation->setModifier($this->getUser());
                                    $entityAssociation->setCreationTime(new \DateTime('now'));
                                    $entityAssociation->setModificationTime(new \DateTime('now'));

                                    $entityAssociation->setDirected(false);

                                    if ($key == "equivalentClass") {
                                        $systemTypeEquivalentClass = $em->getRepository('AppBundle:SystemType')->find(18); //owl:equivalentClass
                                        $entityAssociation->setSystemType($systemTypeEquivalentClass);
                                    }
                                    if ($key == "disjointWith") {
                                        $systemTypeDisjointWith = $em->getRepository('AppBundle:SystemType')->find(19); //owl:disjointWith
                                        $entityAssociation->setSystemType($systemTypeDisjointWith);
                                    }

                                    $newNamespaceVersion->addEntityAssociation($entityAssociation);
                                    $em->persist($entityAssociation);
                                }
                            }
                        }
                        foreach($nodeXmlProperties->children() as $key => $nodeXmlProperty) {
                            //subPropertyOf
                            if (!empty($nodeXmlProperty->subPropertyOf)) {
                                $propertyAssociation = new PropertyAssociation();
                                // Quelle version Parent ?
                                $xmlParentPropertyNamespace = $nodeXmlProperty->subPropertyOf->attributes()->referenceNamespace;
                                //Si attribut referenceNamespace existe, utiliser cet id, sinon ce nouveau namespace
                                if (!is_null($xmlParentPropertyNamespace)) {
                                    $parentPropertyNamespace = $em->getRepository("AppBundle:OntoNamespace")
                                        ->findOneBy(array("id" => (integer)$xmlParentPropertyNamespace));
                                    if(!$idsReferences->contains((integer)$xmlParentPropertyNamespace)){
                                        echo "Un namespace de référence pour subPropertyOf n'a pas été déclaré avec la balise referenceNamespace";
                                        die;
                                    }
                                } else {
                                    $parentPropertyNamespace = $newNamespaceVersion;
                                }
                                // Trouver la propriété parente
                                $parentProperty = null;
                                foreach ($parentPropertyNamespace->getProperties() as $tempProperty) {
                                    if ($tempProperty->getIdentifierInNamespace() == (string)$nodeXmlProperty->subPropertyOf) {
                                        $parentProperty = $tempProperty;
                                        break;
                                    }
                                }
                                if (is_null($parentProperty)) {
                                    echo (string)$nodeXmlProperty->identifierInNamespace . " Parent property " . (string)$nodeXmlProperty->subPropertyOf . " n'a pas été trouvé";
                                    die;
                                }

                                // Trouver la propriété enfante
                                $childProperty = null;
                                foreach ($newNamespaceVersion->getProperties() as $tempProperty) {
                                    if ($tempProperty->getIdentifierInNamespace() == (string)$nodeXmlProperty->identifierInNamespace) {
                                        $childProperty = $tempProperty;
                                        break;
                                    }
                                }
                                if (is_null($childProperty)) {
                                    echo "Child property " . (string)$nodeXmlProperty->identifierInNamespace . " n'a pas été trouvé";
                                    die;
                                }
                                //TODO Justification PropertyAssociation?
                                $propertyAssociation->setParentProperty($parentProperty);
                                $propertyAssociation->setParentPropertyNamespace($parentPropertyNamespace);
                                $propertyAssociation->setChildProperty($childProperty);
                                $propertyAssociation->setChildPropertyNamespace($newNamespaceVersion);

                                $propertyAssociation->setNamespaceForVersion($newNamespaceVersion);

                                $propertyAssociation->setCreator($this->getUser());
                                $propertyAssociation->setModifier($this->getUser());
                                $propertyAssociation->setCreationTime(new \DateTime('now'));
                                $propertyAssociation->setModificationTime(new \DateTime('now'));

                                $newNamespaceVersion->addPropertyAssociation($propertyAssociation);
                                $em->persist($propertyAssociation);
                            }

                            //equivalentProperty or inverseOf
                            foreach ($nodeXmlProperty->children() as $key => $value) {
                                if($key=="equivalentProperty" || $key=="inverseOf"){
                                    $entityAssociation = new EntityAssociation();
                                    // Quelle version Target ?
                                    if($key=="equivalentProperty"){
                                        $nodeXmlEntityAssociation = $nodeXmlProperty->equivalentProperty;
                                    }
                                    if($key=="inverseOf"){
                                        $nodeXmlEntityAssociation = $nodeXmlProperty->inverseOf;
                                    }
                                    $xmlTargetPropertyNamespace = $nodeXmlEntityAssociation->attributes()->referenceNamespace;
                                    //Si attribut referenceNamespace existe, utiliser cet id, sinon ce nouveau namespace
                                    if (!is_null($xmlTargetPropertyNamespace)) {
                                        $targetPropertyNamespace = $em->getRepository("AppBundle:OntoNamespace")
                                            ->findOneBy(array("id" => (integer)$xmlTargetPropertyNamespace));
                                        if(!$idsReferences->contains((integer)$xmlTargetPropertyNamespace)){
                                            echo "Un namespace de référence pour targetProperty equivalentProperty ou inverseOf n'a pas été déclaré avec la balise referenceNamespace";
                                            die;
                                        }
                                    } else {
                                        $targetPropertyNamespace = $newNamespaceVersion;
                                    }
                                    // Trouver la propriété cible
                                    $targetProperty = null;
                                    foreach ($targetPropertyNamespace->getProperties() as $tempProperty) {
                                        if ($tempProperty->getIdentifierInNamespace() == (string)$nodeXmlEntityAssociation) {
                                            $targetProperty = $tempProperty;
                                            break;
                                        }
                                    }
                                    if (is_null($targetProperty)) {
                                        echo (string)$nodeXmlProperty->identifierInNamespace." Target property " . (string)$nodeXmlEntityAssociation . " n'a pas été trouvé";
                                        die;
                                    }

                                    // Trouver la propriété enfante
                                    $sourceProperty = null;
                                    foreach ($newNamespaceVersion->getProperties() as $tempProperty) {
                                        if ($tempProperty->getIdentifierInNamespace() == (string)$nodeXmlProperty->identifierInNamespace) {
                                            $sourceProperty = $tempProperty;
                                            break;
                                        }
                                    }
                                    if (is_null($sourceProperty)) {
                                        echo (string)$nodeXmlProperty->identifierInNamespace." Source property " . (string)$nodeXmlProperty->identifierInNamespace . " n'a pas été trouvé";
                                        die;
                                    }
                                    $entityAssociation->setSourceProperty($sourceProperty);
                                    $entityAssociation->setSourceNamespaceForVersion($newNamespaceVersion);
                                    $entityAssociation->setTargetProperty($targetProperty);
                                    $entityAssociation->setTargetNamespaceForVersion($targetPropertyNamespace);

                                    $entityAssociation->setNamespaceForVersion($newNamespaceVersion);

                                    $entityAssociation->setCreator($this->getUser());
                                    $entityAssociation->setModifier($this->getUser());
                                    $entityAssociation->setCreationTime(new \DateTime('now'));
                                    $entityAssociation->setModificationTime(new \DateTime('now'));

                                    $entityAssociation->setDirected(false);

                                    if($key=="equivalentProperty"){
                                        $systemTypeEquivalentProperty = $em->getRepository('AppBundle:SystemType')->find(18); //owl:equivalentProperty
                                        $entityAssociation->setSystemType($systemTypeEquivalentProperty);
                                    }
                                    if($key=="inverseOf"){
                                        $systemTypeInverseOf = $em->getRepository('AppBundle:SystemType')->find(20); //owl:inverseOf
                                        $entityAssociation->setSystemType($systemTypeInverseOf);
                                    }

                                    $newNamespaceVersion->addEntityAssociation($entityAssociation);
                                    $em->persist($entityAssociation);
                                }
                            }
                        }

                        $newNamespaceVersion->setCreator($this->getUser());
                        $newNamespaceVersion->setModifier($this->getUser());
                        $newNamespaceVersion->setCreationTime(new \DateTime('now'));
                        $newNamespaceVersion->setModificationTime(new \DateTime('now'));
                        $newNamespaceVersion->setIsTopLevelNamespace(false);
                        $newNamespaceVersion->setProjectForTopLevelNamespace($project);
                        $newNamespaceVersion->setIsOngoing(false);
                        $newNamespaceVersion->setIsExternalNamespace(true);
                        $newNamespaceVersion->setReferencedVersion($namespaceRoot);

                        $em->persist($newNamespaceVersion);
                        $em->flush();
                        $this->addFlash('success', 'Namespace imported!');
                    }
                }
                else{
                    echo "Erreur dans le fichier XML";
                    die;
                }
            }
            else{
                echo "Ce n'est pas du XML";
                die;
            }

            return $this->redirectToRoute('project_edit', [
                'id' => $newNamespaceVersion->getTopLevelNamespace()->getProjectForTopLevelNamespace()->getId(),
                '_fragment' => 'managed-namespaces'
            ]);
        }
        return $this->render('project/edit.html.twig', array(
            'project' => $project,
            'formImport' => $formImport->createView(),
            'namespacesPublicProject' => $namespacesPublicProject,
            'users' => $users
        ));
    }

    /**
     * @Route("/selectable-members/project/{project}/json", name="selectable_members_project_json", requirements={"project"="^([0-9]+)|(projectID){1}$"})
     * @Method("GET")
     * @param Project $project
     * @return JsonResponse a Json formatted list representation of Users selectable by Project
     */
    public function getSelectableMembersByProject(Project $project)
    {
        try{
            $em = $this->getDoctrine()->getManager();
            $users = $em->getRepository('AppBundle:User')
                ->findAllNotInProject($project);
            $data['data'] = $users;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($users)) {
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/associated-members/project/{project}/json", name="associated_members_project_json", requirements={"project"="^([0-9]+)|(projectID){1}$"})
     * @Method("GET")
     * @param Project $project
     * @return JsonResponse a Json formatted list representation of Users selected by Project
     */
    public function getAssociatedMembersByProject(Project $project)
    {
        try{
            $em = $this->getDoctrine()->getManager();
            $classes = $em->getRepository('AppBundle:User')
                ->findUsersInProject($project);
            $data['data'] = $classes;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($classes)) {
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/project/{project}/user/{user}/add", name="project_user_association", requirements={"project"="^([0-9]+)|(projectID){1}$", "user"="^([0-9]+)|(id){1}$"})
     * @Method({ "POST"})
     * @param User  $user    The user to be associated with a project
     * @param Project  $project    The project to be associated with a user
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse $response
     */
    public function newProjectUserAssociationAction(Project $project, User $user, Request $request)
    {
        $this->denyAccessUnlessGranted('edit_manager', $project);

        $em = $this->getDoctrine()->getManager();
        $userProjectAssociation = $em->getRepository('AppBundle:UserProjectAssociation')
            ->findOneBy(array('project' => $project->getId(), 'user' => $user->getId()));

        if (!is_null($userProjectAssociation)) {
            $status = 'Error';
            $message = 'This user is already member of this project.';
        }
        else {
            $em = $this->getDoctrine()->getManager();

            $userProjectAssociation = new UserProjectAssociation();
            $userProjectAssociation->setProject($project);
            $userProjectAssociation->setUser($user);
            $userProjectAssociation->setPermission(3); //status 3 = member
            $userProjectAssociation->setCreator($this->getUser());
            $userProjectAssociation->setModifier($this->getUser());
            $userProjectAssociation->setCreationTime(new \DateTime('now'));
            $userProjectAssociation->setModificationTime(new \DateTime('now'));
            $em->persist($userProjectAssociation);

            $em->flush();
            $status = 'Success';
            $message = 'Member successfully associated.';
        }


        $response = array(
            'status' => $status,
            'message' => $message
        );

        return new JsonResponse($response);
    }

    /**
     * @Route("/user-project-association/{id}/permission/{permission}/edit", name="project_member_permission_edit", requirements={"id"="^([0-9]+)|(associationId){1}$", "permission"="^([1-4])|(permissionToken){1}$"})
     * @Method({ "POST"})
     * @param UserProjectAssociation  $userProjectAssociation   The user to project association to be edited
     * @param int  $permission    The permission to
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse $response
     */
    public function editProjectUserAssociationPermissionAction(UserProjectAssociation $userProjectAssociation, $permission, Request $request)
    {
        $this->denyAccessUnlessGranted('full_edit', $userProjectAssociation->getProject());

        if($userProjectAssociation->getUser() == $this->getUser()) {
            //l'utilisateur connecté ne peut pas changer ses propres permissions
            $status = 'Error';
            $message = 'The current user cannot change his own permission.';
        }
        else {
            try{
                $em = $this->getDoctrine()->getManager();

                $userProjectAssociation->setPermission($permission);
                $userProjectAssociation->setModifier($this->getUser());
                $em->persist($userProjectAssociation);
                $em->flush();
                $status = 'Success';
                $message = 'Permission successfully edited.';
            }
            catch (\Exception $e) {
                return new JsonResponse(null, 400, 'content-type:application/problem+json');
            }
        }

        $response = array(
            'status' => $status,
            'message' => $message
        );

        return new JsonResponse($response);
    }

    /**
     * @Route("/user-project-association/{id}/delete", name="project_member_disassociation", requirements={"id"="^([0-9]+)|(associationId){1}$"})
     * @Method({ "POST"})
     * @param UserProjectAssociation  $userProjectAssociation   The user to project association to be deleted
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteProjectUserAssociationAction(UserProjectAssociation $userProjectAssociation, Request $request)
    {
        $this->denyAccessUnlessGranted('edit_manager', $userProjectAssociation->getProject());
        try {
            $em = $this->getDoctrine()->getManager();
            $entityUserProjectsAssociations = $em->getRepository('AppBundle:EntityUserProjectAssociation')
                ->findBy(array('userProjectAssociation' => $userProjectAssociation->getId()));
            foreach ($entityUserProjectsAssociations as $eupa){
                $em->remove($eupa);
            }
            $em->remove($userProjectAssociation);
            $em->flush();
        }
        catch (\Exception $e) {
            return new JsonResponse($e->getMessage(), 400, array('content-type:application/problem+json'));
        }
        return new JsonResponse(null, 204);

    }

    /**
     * @Route("/selectable-profiles/project/{project}/json", name="selectable_profiles_project_json", requirements={"project"="^([0-9]+)|(projectID){1}$"})
     * @Method("GET")
     * @param Project $project
     * @return JsonResponse a Json formatted list representation of Profiles selectable by Project
     */
    public function getSelectableProfilesByProject(Project $project)
    {
        try{
            $em = $this->getDoctrine()->getManager();
            $profiles = $em->getRepository('AppBundle:Profile')
                ->findProfilesForAssociationWithProjectByProjectId($project);
            $data['data'] = $profiles;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($profiles)) {
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/associated-profiles/project/{project}/json", name="associated_profiles_project_json", requirements={"project"="^([0-9]+)|(projectID){1}$"})
     * @Method("GET")
     * @param Project $project
     * @return JsonResponse a Json formatted list representation of Profiles associated with Project
     */
    public function getAssociatedProfilesByProject(Project $project)
    {
        try{
            $em = $this->getDoctrine()->getManager();
            $profiles = $em->getRepository('AppBundle:Profile')
                ->findProfilesByProjectId($project);
            $data['data'] = $profiles;
            $data = json_encode($data);
        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($profiles)) {
            return new JsonResponse('{"data":[]}',200, array(), true);
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/project/{project}/profile/{profile}/add", name="project_profile_association", requirements={"project"="^([0-9]+)|(projectID){1}$", "profile"="^([0-9]+)|(profileID){1}$"})
     * @Method({ "POST"})
     * @param Profile  $profile The profile to be associated with a project
     * @param Project  $project   The project to be associated with a profile
     * @throws \Exception in case of unsuccessful association
     * @return JsonResponse a Json formatted namespaces list
     */
    public function newProjectProfileAssociationAction(Profile $profile, Project $project, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $project);

        $em = $this->getDoctrine()->getManager();
        $projectAssociation = $em->getRepository('AppBundle:ProjectAssociation')
            ->findOneBy(array('project' => $project->getId(), 'profile' => $profile->getId()));

        if (!is_null($projectAssociation)) {
            if($projectAssociation->getSystemType()->getId() == 11) {
                $status = 'Error';
                $message = 'This profile is already used by this project';
            }
            else {
                $systemType = $em->getRepository('AppBundle:SystemType')->find(11); //systemType 11 = Used by project
                $projectAssociation->setSystemType($systemType);

                $em->persist($projectAssociation);
                $em->flush();

                $status = 'Success';
                $message = 'Profile successfully re-associated';
            }
        }
        else {
            $em = $this->getDoctrine()->getManager();

            $projectAssociation = new ProjectAssociation();
            $projectAssociation->setProject($project);
            $projectAssociation->setProfile($profile);
            $systemType = $em->getRepository('AppBundle:SystemType')->find(11); //systemType 11 = Used by project
            $projectAssociation->setSystemType($systemType);
            $projectAssociation->setCreator($this->getUser());
            $projectAssociation->setModifier($this->getUser());
            $projectAssociation->setCreationTime(new \DateTime('now'));
            $projectAssociation->setModificationTime(new \DateTime('now'));
            $em->persist($projectAssociation);

            $em->flush();
            $status = 'Success';
            $message = 'Profile successfully associated';
        }


        $response = array(
            'status' => $status,
            'message' => $message
        );

        return new JsonResponse($response);
    }

    /**
    * @Route("/project/{project}/profile/{profile}/delete", name="project_profile_disassociation", requirements={"project"="^([0-9]+)|(projectID){1}$", "profile"="^([0-9]+)|(profileID){1}$"})
    * @Method({ "POST"})
    * @param Profile  $profile    The profile to be disassociated from a project
    * @param Project  $project    The project to be disassociated from a profile
    * @return JsonResponse a Json 204 HTTP response
    */
    public function deleteProjectProfileAssociationAction(Profile $profile, Project $project, Request $request)
    {
        $this->denyAccessUnlessGranted('edit', $project);
        $em = $this->getDoctrine()->getManager();

        $projectAssociation = $em->getRepository('AppBundle:ProjectAssociation')
            ->findOneBy(array('project' => $project->getId(), 'profile' => $profile->getId()));

        $em->remove($projectAssociation);
        $em->flush();

        return new JsonResponse(null, 204);

    }
}