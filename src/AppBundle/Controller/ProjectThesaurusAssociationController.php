<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 07/11/2022
 * Time: 15:34
 */

namespace AppBundle\Controller;


use AppBundle\Entity\ProjectThesaurusAssociation;
use AppBundle\Form\ProjectThesaurusAssociationForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ProjectThesaurusAssociationController extends Controller
{
    /**
     * @Route("/project-thesaurus-association/project/{projectId}/json",
     *     name="project_thesaurus_association_show_json",
     *     requirements={"projectId"="^[0-9]+$"})
     * @Method("GET")
     * @param int  $projectId    The id of the object
     * @return JsonResponse a Json formatted ProjectThesaurusAssociation list
     */
    public function getProjectThesaurusAssociationAction($projectId)
    {
        $em = $this->getDoctrine()->getManager();

        $project = $em->getRepository('AppBundle:Project')->find($projectId);
        if (!$project) {
            throw $this->createNotFoundException('The project n° '.$projectId.' does not exist');
        }

        $projectThesaurusAssociations = [];

        foreach ($project->getProjectThesaurusAssociations() as $projectThesaurusAssociation) {
            $projectThesaurusAssociations[] = [
                'id' => $projectThesaurusAssociation->getId(),
                'creator' => $projectThesaurusAssociation->getCreator(),
                'thesaurusURL' => $projectThesaurusAssociation->getThesaurusURL(),
                'creationTime' => $projectThesaurusAssociation->getCreationTime()->format('M d, Y')
            ];
        }

        $data =[
            'projectThesaurusAssociation' => $projectThesaurusAssociations
        ];
        return new JsonResponse($data);
    }

    /**
     * @Route("/project-thesaurus-association/new/project/{projectId}", name="project_thesaurus_association_new", requirements={"projectId"="^[0-9]+$"})
     * @Method({ "POST"})
     */
    public function newAction($projectId, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $projectThesaurusAssociation = new ProjectThesaurusAssociation();

        $project = $em->getRepository('AppBundle:Project')->find($projectId);

        if (!$project) {
            throw $this->createNotFoundException('The project n° '.$projectId.' does not exist');
        }

        $projectThesaurusAssociation->setProject($project);

        //only managers and administrators can add a thesaurus to their project
        $this->denyAccessUnlessGranted('edit_manager', $project);

        $projectThesaurusAssociation->setCreator($this->getUser());
        $projectThesaurusAssociation->setModifier($this->getUser());
        $projectThesaurusAssociation->setCreationTime(new \DateTime('now'));
        $projectThesaurusAssociation->setModificationTime(new \DateTime('now'));

        $form = $this->createForm(ProjectThesaurusAssociationForm::class, $projectThesaurusAssociation);

        $status = 'error';
        $message = '';

        // only handles data on POST
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projectThesaurusAssociation = $form->getData();
            $projectThesaurusAssociation->setCreator($this->getUser());
            $projectThesaurusAssociation->setModifier($this->getUser());
            $projectThesaurusAssociation->setCreationTime(new \DateTime('now'));
            $projectThesaurusAssociation->setModificationTime(new \DateTime('now'));

            $em = $this->getDoctrine()->getManager();
            $em->persist($projectThesaurusAssociation);
            $em->flush();

            $html = $this->renderView('projectThesaurusAssociation/new.html.twig', [
                'projectThesaurusAssociation' => $projectThesaurusAssociation,
                'projectThesaurusAssociationForm' => $form->createView()
            ]);

            try {
                $em->flush();
                $status = 'Success';
                $message = 'New project/thesaurus association saved';
            } catch (\Exception $e) {
                $message = $e->getMessage();
            }

            $response = array(
                'status' => $status,
                'associationID' => $projectThesaurusAssociation->getId(),
                'message' => $message,
                'html' => $html
            );

            return new JsonResponse($response);

        }
        else if ($form->isSubmitted() && !$form->isValid()) {
            $status = 'Error';
            $message = 'Invalid form validation';
            $html = $this->renderView('projectThesaurusAssociation/new.html.twig', [
                'projectThesaurusAssociation' => $projectThesaurusAssociation,
                'projectThesaurusAssociationForm' => $form->createView()
            ]);
            $response = array(
                'status' => $status,
                'message' => $message,
                'html' => $html
            );
            return new JsonResponse($response);
        }
        else return $this->render('projectThesaurusAssociation/new.html.twig', [
            'projectThesaurusAssociation' => $projectThesaurusAssociation,
            'projectThesaurusAssociationForm' => $form->createView()
        ]);
    }

    /**
     * @Route("/project-thesaurus-association/{id}/delete", name="project_thesaurus_association_delete", requirements={"id"="^[0-9]+$"})
     * @param ProjectThesaurusAssociation $ProjectThesaurusAssociation
     * @return JsonResponse a Json 204 HTTP response
     */
    public function deleteAction(Request $request, ProjectThesaurusAssociation $projectThesaurusAssociation)
    {
        $project = $projectThesaurusAssociation->getProject();

        $this->denyAccessUnlessGranted('edit_manager', $project);

        $em = $this->getDoctrine()->getManager();
        $em->remove($projectThesaurusAssociation);
        $em->flush();
        return new JsonResponse(null, 204);
    }

}