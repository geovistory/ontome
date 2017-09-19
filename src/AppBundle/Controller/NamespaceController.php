<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 28/06/2017
 * Time: 15:50
 */

namespace AppBundle\Controller;

use AppBundle\Entity\OntoNamespace;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

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

}