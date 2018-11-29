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
     * @Route("/project/new", name="project_new_user")
     */
    public function newUserProjectAction(Request $request)
    {

        $tokenInterface = $this->get('security.token_storage')->getToken();
        $isAuthenticated = $tokenInterface->isAuthenticated();
        if(!$isAuthenticated) throw new AccessDeniedException('You must be an authenticated user to access this page.');

        $project = new Project();
        $namespace = new OntoNamespace();
        $ongoingNamespace = new OntoNamespace();
        $userProjectAssociation = new UserProjectAssociation();
        $projectLabel = new Label();
        $projectLabel->setProject($project);
        $projectLabel->setIsStandardLabelForLanguage(true);
        $projectLabel->setCreator($this->getUser());
        $projectLabel->setModifier($this->getUser());
        $projectLabel->setCreationTime(new \DateTime('now'));
        $projectLabel->setModificationTime(new \DateTime('now'));

        $namespace->setNamespaceURI('http://dataforhistory.org/'.$projectLabel->getLabel().'/');
        $namespace->setIsTopLevelNamespace(true);
        $namespace->setIsOngoing(false);
        $namespace->setTopLevelNamespace($namespace);
        $namespace->setProjectForTopLevelNamespace($project);
        $namespace->setStartDate(new \DateTime('now'));
        $namespace->setCreationTime(new \DateTime('now'));
        $namespace->setModificationTime(new \DateTime('now'));

        $ongoingNamespace->setNamespaceURI('http://dataforhistory.org/'.$projectLabel->getLabel().'/ongoing/');
        $ongoingNamespace->setIsTopLevelNamespace(false);
        $ongoingNamespace->setIsOngoing(true);
        $ongoingNamespace->setTopLevelNamespace($namespace);
        $ongoingNamespace->setProjectForTopLevelNamespace($project);
        $ongoingNamespace->setReferencedVersion($namespace);
        $ongoingNamespace->setStartDate(new \DateTime('now'));
        $ongoingNamespace->setCreationTime(new \DateTime('now'));
        $ongoingNamespace->setModificationTime(new \DateTime('now'));

        $userProjectAssociation->setUser($this->getUser());
        $userProjectAssociation->setProject($project);
        $userProjectAssociation->setPermission(1);
        $userProjectAssociation->setNotes('Project created by user via OntoME form.');
        $userProjectAssociation->setStartDate(new \DateTime('now'));


        $project->addLabel($projectLabel);

        $form = $this->createForm(ProjectQuickAddForm::class, $project);
        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $project = $form->getData();

            $project->setCreator($this->getUser());
            $project->setModifier($this->getUser());
            $project->setCreationTime(new \DateTime('now'));
            $project->setModificationTime(new \DateTime('now'));



            $em = $this->getDoctrine()->getManager();
            $em->persist($project);
            $em->persist($namespace);
            $em->persist($ongoingNamespace);
            $em->persist($userProjectAssociation);
            $em->flush();

            return $this->redirectToRoute('user_show', [
                'id' =>$this->getUser()
            ]);

        }

        $em = $this->getDoctrine()->getManager();


        return $this->render('project/new.html.twig', [
            'project' => $project,
            'projectForm' => $form->createView()
        ]);
    }

}