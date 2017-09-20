<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 28/06/2017
 * Time: 15:50
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Project;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

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
    public function showAction(OntoProject $project)
    {
        $em = $this->getDoctrine()->getManager();

        return $this->render('project/show.html.twig', array(
            'project' => $project
        ));
    }

}