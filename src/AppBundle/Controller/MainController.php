<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 10/04/2017
 * Time: 11:08
 */

namespace AppBundle\Controller;


use AppBundle\AppBundle;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
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
        $arrayLexemes = array();
        foreach($whatSearch as $wordSearch){
            foreach($wordSearch as $word){
                if($word != "")
                    $arrayLexemes[] = $word;
            }
        }
        return $this->render('main/search.html.twig', array('query' => $query_sanitized, 'resultatTxtp' => $resultatTxtp, 'resultatLbl' => $resultatLbl, 'lexemes' => $arrayLexemes));
    }

    /**
     * @Route("/ns/{name}/{identifierInNamespace}")
     * @param string $query
     */
    public function redirectUriAction($name, $identifierInNamespace)
    {

        // Sécurité anti-injection SQL...
        $name = filter_var($name, FILTER_SANITIZE_STRING);
        $identifierInNamespace = filter_var($identifierInNamespace, FILTER_SANITIZE_STRING);

        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('AppBundle:OntoNamespace')->findEntity($name,$identifierInNamespace);

        if(count($entity) == 0 && substr($identifierInNamespace, -1) === "i"){
            // L'identifier n'a pas été retrouvé mais il se peut que ça soit l'identifiant d'une propriété inverse
            $identifierInNamespace = substr($identifierInNamespace, 0, -1); // Retire le i
            $entity = $em->getRepository('AppBundle:OntoNamespace')->findEntity($name,$identifierInNamespace);
        }

        if(count($entity) == 0){
            // Rien n'a été trouvé
            $this->addFlash('warning', 'URI ontome.net/ns/'.$name."/".$identifierInNamespace." is not valid");
            return $this->redirectToRoute('home');
        }

        return $this->redirectToRoute($entity[0]['entity_type'].'_show', [
            'id' => $entity[0]['entity_id']
        ]);
    }
}