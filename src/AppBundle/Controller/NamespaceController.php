<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 28/06/2017
 * Time: 15:50
 */

namespace AppBundle\Controller;

use AppBundle\Entity\OntoNamespace;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class NamespaceController  extends Controller
{
    /**
     * @Route("/namespace")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $namespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findAllOrderedById();

        return $this->render('namespace/list.html.twig', [
            'namespaces' => $namespaces
        ]);
    }

    /**
     * @Route("/namespace/{id}", name="namespace_show")
     * @param string $id
     * @return Response the rendered template
     */
    public function showAction(OntoNamespace $namespace)
    {
        $em = $this->getDoctrine()->getManager();

        return $this->render('namespace/show.html.twig', array(
            'namespace' => $namespace
        ));
    }

    /**
     * @Route("/namespaces-graph/json/{id}", name="namespaces_graph_json")
     * @Method("GET")
     * @param OntoNamespace $namespace
     * @return JsonResponse a Json formatted graph representation of Namespaces
     */
    public function getGraphJson(OntoNamespace $namespace)
    {
        $em = $this->getDoctrine()->getManager();
        $namespaces = $em->getRepository('AppBundle:OntoNamespace')
            ->findNamespacesGraph($namespace);

        return new JsonResponse($namespaces[0]['json'],200, array(), true);
    }

}