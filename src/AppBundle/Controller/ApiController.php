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
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * @Route("/api/profiles.json", name="api_profiles_json")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse a Json formatted list representation of profiles
     */
    public function getProfiles(Request $request)
    {
        try {
            $lang = $request->get('lang', 'en');
            $selectingProject = intval($request->get('selected-by-project', 0));
            $owningProject = intval($request->get('owned-by-project', 0));

            $em = $this->getDoctrine()->getManager();
            $profiles = $em->getRepository('AppBundle:Profile')
                ->findProfilesApi($lang, $selectingProject, $owningProject);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            $status = 'Error';
            $response = array(
                'status' => $status,
                'message' => $message
            );
            return new JsonResponse($response,500, 'content-type:application/problem+json');
        }

        if(empty($profiles[0]['json'])) {
            return new JsonResponse('[]',200, array(), true);//envoi d'un tableau JSON vide si pas de résultat
        }

        return new JsonResponse($profiles[0]['json'],200, array(), true);
    }


}