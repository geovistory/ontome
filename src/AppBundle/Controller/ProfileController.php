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
     * @Route("/profile/{id}", name="profile_show")
     * @param Profile $profile
     * @return Response the rendered template
     */
    public function showAction(Profile $profile)
    {
        $em = $this->getDoctrine()->getManager();

        $classes = $em->getRepository('AppBundle:OntoClass')
            ->findClassesByProfileId($profile);
        $properties = $em->getRepository('AppBundle:Property')
            ->findPropertiesByProfileId($profile);

        return $this->render('class/show.html.twig', array(
            'profile' => $profile,
            'classes' => $classes,
            'properties' => $properties
        ));

    }

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