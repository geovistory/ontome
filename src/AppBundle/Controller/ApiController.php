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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApiController extends Controller
{


    /**
     * @Route("/api/classes/project/{project}/json", name="classes_project_json", requirements={"project"="^[0-9]+$"})
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
     * @Route("/api/properties/project/{project}/json", name="properties_project_json", requirements={"project"="^[0-9]+$"})
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
            return new JsonResponse($response,500, array('content-type:application/problem+json'));
        }

        if(empty($profiles[0]['json'])) {
            return new JsonResponse('[]',200, array(), true);//envoi d'un tableau JSON vide si pas de résultat
        }

        return new JsonResponse($profiles[0]['json'],200, array(), true);
    }

    /**
     * @Route("/api/classes-profile.json", name="api_classes_profile_json")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse a Json formatted list representation of classes with profile
     */
    public function getClassesWithProfile(Request $request)
    {
        try {
            $lang = $request->get('lang', 'en');
            $availableInProfile = intval($request->get('available-in-profile', 0));
            $selectedByProject = intval($request->get('selected-by-project', 0));

            $em = $this->getDoctrine()->getManager();
            $classes = $em->getRepository('AppBundle:OntoClass')
                ->findClassesWithProfileApi($lang, $availableInProfile, $selectedByProject);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            $status = 'Error';
            $response = array(
                'status' => $status,
                'message' => $message
            );
            return new JsonResponse($response,500, array('content-type:application/problem+json'));
        }

        if(empty($classes[0]['json'])) {
            return new JsonResponse('[]',200, array(), true);//envoi d'un tableau JSON vide si pas de résultat
        }

        return new JsonResponse($classes[0]['json'],200, array(), true);
    }

    /**
     * @Route("/api/properties-profile.json", name="api_properties_profile_json")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse a Json formatted list representation of properties with profile
     */
    public function getPropertiesWithProfile(Request $request)
    {
        try {
            $lang = $request->get('lang', 'en');
            $availableInProfile = intval($request->get('available-in-profile', 0));
            $selectedByProject = intval($request->get('selected-by-project', 0));

            $em = $this->getDoctrine()->getManager();
            $properties = $em->getRepository('AppBundle:Property')
                ->findPropertiesWithProfileApi($lang, $availableInProfile, $selectedByProject);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            $status = 'Error';
            $response = array(
                'status' => $status,
                'message' => $message
            );
            return new JsonResponse($response,500, array('content-type:application/problem+json'));
        }

        if(empty($properties[0]['json'])) {
            return new JsonResponse('[]',200, array(), true);//envoi d'un tableau JSON vide si pas de résultat
        }

        return new JsonResponse($properties[0]['json'],200, array(), true);
    }

    /**
     * @Route("/api/namespaces-rdf-owl.rdf", name="api_classes_and_properties_by_namespace_xml")
     * @Method("GET")
     * @param Request $request
     * @return Response
     */
    public function getClassesAndPropertiesByNamespace(Request $request)
    {
        try {
            $lang = $request->get('lang', 'en');
            $namespaceId = intval($request->get('namespace', 0));
            $em = $this->getDoctrine()->getManager();
            $xml = $em->getRepository('AppBundle:OntoNamespace')
                ->findClassesAndPropertiesByNamespaceIdApi($lang, $namespaceId);
        } catch (\Exception $e) {
            $xml = '<?xml version="1.0" encoding="UTF8" ?>';
            $xml .= '<error code="500" message="Error: '.$e->getMessage().'"/>';
            $response = new Response($xml);
            $response->headers->set('Content-Type', 'application/rdf+xml');
            return $response;
        }

        $response = new Response($xml[0]['result']);
        $response->headers->set('Content-Type', 'application/rdf+xml');
        return $response;
    }

    /**
     * @Route("/api/project-rdf-owl.rdf", name="api_classes_and_properties_by_project_xml")
     * @Method("GET")
     * @param Request $request
     * @return Response XML formatted response of classes and properties related to this project
     */
    public function getClassesAndPropertiesByProject(Request $request)
    {
        try {
            $lang = $request->get('lang', 'en');
            $projectId = intval($request->get('project', 0));
            $em = $this->getDoctrine()->getManager();
            $xml = $em->getRepository('AppBundle:Project')
                ->findClassesAndPropertiesByProjectIdApi($lang, $projectId);
        } catch (\Exception $e) {
            $xml = '<?xml version="1.0" encoding="UTF8" ?>';
            $xml .= '<error code="500" message="Error: '.$e->getMessage().'"/>';
            $response = new Response($xml);
            $response->headers->set('Content-Type', 'application/rdf+xml');
            return $response;
        }

        $response = new Response($xml[0]['result']);
        $response->headers->set('Content-Type', 'application/rdf+xml');
        return $response;
    }

    /**
     * @Route("/api/profile-rdf-owl.rdf", name="api_classes_and_properties_by_profile_xml")
     * @Method("GET")
     * @param Request $request
     * @return Response XML formatted response of classes and properties related to this profile
     */
    public function getClassesAndPropertiesByProfile(Request $request)
    {
        try {
            $lang = $request->get('lang', 'en');
            $profileId = intval($request->get('profile', 0));
            $em = $this->getDoctrine()->getManager();
            $xml = $em->getRepository('AppBundle:Profile')
                ->findClassesAndPropertiesByProfileIdApi($lang, $profileId);
        } catch (\Exception $e) {
            $xml = '<?xml version="1.0" encoding="UTF8" ?>';
            $xml .= '<error code="500" message="Error: '.$e->getMessage().'"/>';
            $response = new Response($xml);
            $response->headers->set('Content-Type', 'application/rdf+xml');
            return $response;
        }

        $response = new Response($xml[0]['result']);
        $response->headers->set('Content-Type', 'application/rdf+xml');
        return $response;
    }

    /**
     * @Route("/api/namespaces-rdfs.rdf", name="api_classes_and_properties_by_namespace_xml_rdfs")
     * @Method("GET")
     * @param Request $request
     * @return Response
     */
    public function getClassesAndPropertiesByNamespaceRdfs(Request $request)
    {
        try {
            $lang = $request->get('lang', 'en');
            $namespaceId = intval($request->get('namespace', 0));
            $em = $this->getDoctrine()->getManager();
            $xml = $em->getRepository('AppBundle:OntoNamespace')
                ->findClassesAndPropertiesByNamespaceIdApiRdfs($lang, $namespaceId);
        } catch (\Exception $e) {
            $xml = '<?xml version="1.0" encoding="UTF8" ?>';
            $xml .= '<error code="500" message="Error: '.$e->getMessage().'"/>';
            $response = new Response($xml);
            $response->headers->set('Content-Type', 'application/rdf+xml');
            return $response;
        }

        $response = new Response($xml[0]['result']);
        $response->headers->set('Content-Type', 'application/rdf+xml');
        return $response;
    }

    /**
     * @Route("/api/profile-rdfs.rdf", name="api_classes_and_properties_by_profile_xml_rdfs")
     * @Method("GET")
     * @param Request $request
     * @return Response XML formatted response of classes and properties related to this profile
     */
    public function getClassesAndPropertiesByProfileRdfs(Request $request)
    {
        try {
            $lang = $request->get('lang', 'en');
            $profileId = intval($request->get('profile', 0));
            $em = $this->getDoctrine()->getManager();
            $xml = $em->getRepository('AppBundle:Profile')
                ->findClassesAndPropertiesByProfileIdApiRdfs($lang, $profileId);
        } catch (\Exception $e) {
            $xml = '<?xml version="1.0" encoding="UTF8" ?>';
            $xml .= '<error code="500" message="Error: '.$e->getMessage().'"/>';
            $response = new Response($xml);
            $response->headers->set('Content-Type', 'application/rdf+xml');
            return $response;
        }

        $response = new Response($xml[0]['result']);
        $response->headers->set('Content-Type', 'application/rdf+xml');
        return $response;
    }


}