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
use AppBundle\Entity\Profile;
use AppBundle\Entity\Project;
use AppBundle\Entity\ProjectAssociation;
use AppBundle\Entity\TextProperty;
use AppBundle\Entity\User;
use AppBundle\Entity\UserProjectAssociation;
use AppBundle\Form\ProjectQuickAddForm;
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



            $em = $this->getDoctrine()->getManager();
            $em->persist($project);
            $em->persist($userProjectAssociation);
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

        $users = $em->getRepository('AppBundle:User')
            ->findAllNotInProject($project);

        $namespacesPublicProject = $em->getRepository('AppBundle:OntoNamespace')
            ->findNamespacesInPublicProject();

        return $this->render('project/edit.html.twig', array(
            'project' => $project,
            'namespacesPublicProject' => $namespacesPublicProject,
            'users' => $users
        ));
    }

    /**
     * @Route("/selectable-members/project/{project}/json", name="selectable_members_project_json")
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
     * @Route("/associated-members/project/{project}/json", name="associated_members_project_json")
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
     * @Route("/project/{project}/user/{user}/add", name="project_user_association")
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
     * @Route("/user-project-association/{id}/permission/{permission}/edit", name="project_member_permission_edit")
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
     * @Route("/user-project-association/{id}/delete", name="project_member_disassociation")
     * @Method({ "POST"})
     * @param UserProjectAssociation  $userProjectAssociation   The user to project association to be deleted
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteProjectUserAssociationAction(UserProjectAssociation $userProjectAssociation, Request $request)
    {
        $this->denyAccessUnlessGranted('edit_manager', $userProjectAssociation->getProject());
        try {
            $em = $this->getDoctrine()->getManager();
            $em->remove($userProjectAssociation);
            $em->flush();
        }
        catch (\Exception $e) {
            return new JsonResponse(null, 400, 'content-type:application/problem+json');
        }
        return new JsonResponse(null, 204);

    }

    /**
     * @Route("/selectable-profiles/project/{project}/json", name="selectable_profiles_project_json")
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
     * @Route("/associated-profiles/project/{project}/json", name="associated_profiles_project_json")
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
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($data,200, array(), true);
    }

    /**
     * @Route("/project/{project}/profile/{profile}/add", name="project_profile_association")
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
    * @Route("/project/{project}/profile/{profile}/delete", name="project_profile_disassociation")
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