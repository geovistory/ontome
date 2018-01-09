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

class MainController extends Controller
{
    public function homepageAction()
    {
        return $this->render('main/homepage.html.twig');
    }

    /**
     * @Route("/legal-notice")
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

}