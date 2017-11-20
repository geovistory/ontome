<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 17/11/2017
 * Time: 18:09
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Profile;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ProfileController  extends Controller
{
    /**
     * @Route("/profile")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $profiles = $em->getRepository('AppBundle:Profile')
            ->findAll();

        return $this->render('profile/list.html.twig', [
            'profiles' => $profiles
        ]);
    }

}