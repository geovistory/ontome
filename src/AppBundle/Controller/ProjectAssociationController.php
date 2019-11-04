<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 15/01/2018
 * Time: 15:19
 */

namespace AppBundle\Controller;

use AppBundle\Entity\ProjectAssociation;
use AppBundle\Form\PublicProjectNamespaceAssociationAddForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class ProjectAssociationController extends Controller
{

    /**
     * @param Request $request
     * @Route("/public-project-namespace-association/new", name="public_project_namespace_association_new")
     */
    public function newPublicProjectNamespaceAssociationAction(Request $request)
    {
        $projectAssociation = new ProjectAssociation();
        $em = $this->getDoctrine()->getManager();
        $publicProject =  $em->getRepository('AppBundle:Project')->find(21); //public project = 21

        $this->denyAccessUnlessGranted('full_edit', $publicProject); //admins only can add a new namespace to the public project

        $systemType= $em->getRepository('AppBundle:SystemType')->find(17); //systemType 17 = Default display

        $form = $this->createForm(PublicProjectNamespaceAssociationAddForm::class, $projectAssociation);

        // only handles data on POST
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $projectAssociation = $form->getData();
            $projectAssociation->setProject($publicProject);
            $projectAssociation->setSystemType($systemType);
            $projectAssociation->setCreator($this->getUser());
            $projectAssociation->setModifier($this->getUser());
            $projectAssociation->setCreationTime(new \DateTime('now'));
            $projectAssociation->setModificationTime(new \DateTime('now'));


            $em = $this->getDoctrine()->getManager();
            $em->persist($projectAssociation);
            $em->flush();

            return $this->redirectToRoute('project_edit', [
                'id' => $projectAssociation->getProject()->getId(),
                '_fragment' => 'managed-namespaces'
            ]);

        }

        return $this->render('projectAssociation/newPublicProjectNamespaceAssociation.html.twig', [
            'projectAssociation' => $projectAssociation,
            'publicProjectNamespaceAssociationForm' => $form->createView()
        ]);
    }

    /**
     * @param ProjectAssociation $projectAssociation
     * @param Request $request
     * @Route("/project-association/{id}/delete", name="project_association_delete")
     */
    public function deleteAction(Request $request, ProjectAssociation $projectAssociation)
    {
        $this->denyAccessUnlessGranted('full_edit', $projectAssociation->getProject());

        if (!$projectAssociation) {
            throw $this->createNotFoundException('This project association does not exist');
        }

        try {
            $em = $this->getDoctrine()->getManager();
            $em->remove($projectAssociation);
            $em->flush();
        }
        catch (\Exception $e) {
            return new \Exception('Something went wrong!');
        }
        return $this->redirectToRoute('project_edit', [
            'id' => $projectAssociation->getProject()->getId(),
            '_fragment' => 'managed-namespaces'
        ]);
    }


}