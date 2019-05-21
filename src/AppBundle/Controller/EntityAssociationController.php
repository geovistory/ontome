<?php
/**
 * Created by PhpStorm.
 * User: pc-alexandre-pro
 * Date: 07/05/2019
 * Time: 14:39
 */

namespace AppBundle\Controller;


use AppBundle\Entity\EntityAssociation;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EntityAssociationController extends Controller
{
    /**
     * @Route("/entity-association/{id}", name="entity_association_show")
     * @Route("/entity-association/{id}/{idClass}", name="entity_association_show")
     * @param EntityAssociation $entityAssociation
     * @param $idClass
     * @return Response the rendered template
     */
    public function showAction(EntityAssociation $entityAssociation, $idClass=null)
    {
        if($idClass != null && $entityAssociation->getSourceClass()->getId() != $idClass && !$entityAssociation->getDirected())
        {
            $entityAssociation->inverseClasses();
        }

        return $this->render('entityAssociation/show.html.twig', array(
            'entityAssociation' => $entityAssociation
        ));
    }

    /**
     * @Route("/entity-association/new/{object}/{objectId}", name="new_entity_association_form")
     * @param $request
     * @param $object
     * @param $objectId
     */
    public function newEntityAssociationAction(Request $request, $object, $objectId)
    {
        return $this->render('entityAssociation/new.html.twig', array());
    }
}