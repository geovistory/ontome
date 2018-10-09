<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 21/11/2017
 * Time: 11:22
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Project;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApiController extends Controller
{


    /**
     * @Route("/api/classes/project/{project}/json", name="classes_project_json")
     * @Method("GET")
     * @param Project $project
     * @return JsonResponse a Json formatted list representation of OntoClasses related to a Project
     */
    public function getClassesByProject(Project $project)
    {
        try{
            $em = $this->getDoctrine()->getManager();
            $classes = $em->getRepository('AppBundle:OntoClass')
                ->findClassesByProjectId($project);

        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($classes[0]['json'])) {
            return new JsonResponse(null,204, array());
        }

        //return new JsonResponse(null,404, array('content-type'=>'application/problem+json'));
        return new JsonResponse($classes[0]['json'],200, array(), true);
    }

    /**
     * @Route("/api/properties/project/{project}/json", name="properties_project_json")
     * @Method("GET")
     * @param Project $project
     * @return JsonResponse a Json formatted list representation of Property related to a Project
     */
    public function getPropertiesByProject(Project $project)
    {
        try {
            $em = $this->getDoctrine()->getManager();
            $properties = $em->getRepository('AppBundle:Property')
                ->findPropertiesByProjectId($project);

        }
        catch (NotFoundHttpException $e) {
            return new JsonResponse(null,404, 'content-type:application/problem+json');
        }

        if(empty($properties[0]['json'])) {
            return new JsonResponse(null,204, array());
        }

        return new JsonResponse($properties[0]['json'],200, array(), true);
    }


}