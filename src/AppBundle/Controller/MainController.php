<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 10/04/2017
 * Time: 11:08
 */

namespace AppBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Twig_Environment;

class MainController extends Controller
{
    public function homepageAction()
    {
        return $this->render('main/homepage.html.twig');
    }

    /**
     * @Route("/terms-of-service")
     */
    public function legalNoticeAction()
    {
        return $this->render('main/legalNotice.html.twig');
    }

    /**
     * @Route("/the-data-for-history-consortium")
     */
    public function projectDescriptionAction()
    {
        return $this->render('main/projectDescription.html.twig');
    }

    /**
     * @Route("/search")
     * @Route("/search/")
     * @Route("/search/{query}", name="search_query")
     * @param string $query
     */
    public function searchAction($query="")
    {
        $em = $this->getDoctrine()->getManager();

        // Sécurité anti-injection SQL...
        $query_sanitized = filter_var($query, FILTER_SANITIZE_STRING);

        // Retrouver les txtp & labels correspondants à la recherche
        $resultatTxtp = $em->getRepository('AppBundle:TextProperty')->findByFullTextSearch($query_sanitized);
        $resultatLbl = $em->getRepository('AppBundle:Label')->findByFullTextSearch($query_sanitized);

        // Retrouver les termes qui ont été réellement comparées (pour information à l'utilisateur)
        $whatSearch = $em->getRepository('AppBundle:TextProperty')->findWhatSearch($query_sanitized);
        return $this->render('main/search.html.twig', array('query' => $query_sanitized, 'resultatTxtp' => $resultatTxtp, 'resultatLbl' => $resultatLbl, 'whatSearch' => $whatSearch));
    }
}