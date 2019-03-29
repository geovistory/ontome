<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 28/06/2017
 * Time: 15:50
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Label;
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\Project;
use AppBundle\Entity\TextProperty;
use AppBundle\Entity\PropertyAssociation;
use AppBundle\Entity\User;
use AppBundle\Entity\UserProjectAssociation;
use AppBundle\Form\ProjectQuickAddForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;

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

        $namespace = new OntoNamespace();

        $em = $this->getDoctrine()->getManager();
        $systemTypeDescription = $em->getRepository('AppBundle:SystemType')->find(16); //systemType 16 = Description

        $description = new TextProperty();
        $description->setProject($project);
        $description->setSystemType($systemTypeDescription);
        $description->addNamespace($namespace);
        $description->setCreator($this->getUser());
        $description->setModifier($this->getUser());
        $description->setCreationTime(new \DateTime('now'));
        $description->setModificationTime(new \DateTime('now'));

        $project->addTextProperty($description);

        $ongoingNamespace = new OntoNamespace();
        $userProjectAssociation = new UserProjectAssociation();
        $namespaceLabel = new Label();
        $ongoingNamespaceLabel = new Label();
        $errors = null;

        //$now = new \DateTime('now');
        //$now = $now->format('Y-m-d');
        $date = new \DateTime('now');
        $now = $date->format('Y-m-d');
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

            $labelForURI = str_replace(' ', '-', $projectLabel->getLabel());

            $namespace->setNamespaceURI('http://dataforhistory.org/'.$labelForURI.'/');
            $namespace->setIsTopLevelNamespace(true);
            $namespace->setIsOngoing(false);
            $namespace->setTopLevelNamespace($namespace);
            $namespace->setProjectForTopLevelNamespace($project);
            $namespace->setStartDate($now);
            $namespace->setCreator($this->getUser());
            $namespace->setModifier($this->getUser());
            $namespace->setCreationTime(new \DateTime('now'));
            $namespace->setModificationTime(new \DateTime('now'));

            $errors = $this->container->get('validator')->validate($namespace);
            if (count($errors) > 0) {
                return $this->render('project/new.html.twig', array(
                    'errors' => $errors,
                    'project' => $project,
                    'projectForm' => $form->createView()
                ));
            }

            $ongoingNamespace->setNamespaceURI('http://dataforhistory.org/'.$labelForURI.'/ongoing/');
            $ongoingNamespace->setIsTopLevelNamespace(false);
            $ongoingNamespace->setIsOngoing(true);
            $ongoingNamespace->setTopLevelNamespace($namespace);
            $ongoingNamespace->setProjectForTopLevelNamespace($project);
            $ongoingNamespace->setReferencedVersion($namespace);
            $ongoingNamespace->setStartDate($now);
            $ongoingNamespace->setCreator($this->getUser());
            $ongoingNamespace->setModifier($this->getUser());
            $ongoingNamespace->setCreationTime(new \DateTime('now'));
            $ongoingNamespace->setModificationTime(new \DateTime('now'));

            $userProjectAssociation->setUser($this->getUser());
            $userProjectAssociation->setProject($project);
            $userProjectAssociation->setPermission(1);
            $userProjectAssociation->setNotes('Project created by user via OntoME form.');
            $userProjectAssociation->setStartDate($now);
            $userProjectAssociation->setCreator($this->getUser());
            $userProjectAssociation->setModifier($this->getUser());
            $userProjectAssociation->setCreationTime(new \DateTime('now'));
            $userProjectAssociation->setModificationTime(new \DateTime('now'));

            $namespaceLabel->setIsStandardLabelForLanguage(true);
            $namespaceLabel->setLabel($projectLabel->getLabel());
            $namespaceLabel->setLanguageIsoCode($projectLabel->getLanguageIsoCode());
            $namespaceLabel->setCreator($this->getUser());
            $namespaceLabel->setModifier($this->getUser());
            $namespaceLabel->setCreationTime(new \DateTime('now'));
            $namespaceLabel->setModificationTime(new \DateTime('now'));
            $namespace->addLabel($namespaceLabel);

            $ongoingNamespaceLabel->setIsStandardLabelForLanguage(true);
            $ongoingNamespaceLabel->setLabel($projectLabel->getLabel() . " ongoing");
            $ongoingNamespaceLabel->setLanguageIsoCode($projectLabel->getLanguageIsoCode());
            $ongoingNamespaceLabel->setCreator($this->getUser());
            $ongoingNamespaceLabel->setModifier($this->getUser());
            $ongoingNamespaceLabel->setCreationTime(new \DateTime('now'));
            $ongoingNamespaceLabel->setModificationTime(new \DateTime('now'));
            $ongoingNamespace->addLabel($ongoingNamespaceLabel);


            $em = $this->getDoctrine()->getManager();
            $em->persist($project);
            $em->persist($namespace);
            $em->persist($ongoingNamespace);
            $em->persist($userProjectAssociation);
            $em->flush();


            return $this->redirectToRoute('user_show', [
                'id' =>$userProjectAssociation->getUser()->getId()
            ]);

        }

        $em = $this->getDoctrine()->getManager();


        return $this->render('project/new.html.twig', [
            'errors' => $errors,
            'project' => $project,
            'projectForm' => $form->createView()
        ]);
    }

    /**
     * @Route("/project/{id}", name="project_show")
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
     * @Route("/project/{id}/edit", name="project_edit")
     * @param Project $project
     * @return Response the rendered template
     */
    public function editAction(Project $project)
    {
        $this->denyAccessUnlessGranted('edit', $project);

        $em = $this->getDoctrine()->getManager();

        return $this->render('project/edit.html.twig', array(
            'project' => $project
        ));
    }
}