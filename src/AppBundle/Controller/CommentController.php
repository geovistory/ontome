<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 15/10/2018
 * Time: 10:17
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Comment;
use AppBundle\Form\CommentForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CommentController extends Controller
{
    /**
     * @Route("/comment/new/{object}/{objectId}", name="comment_new")
     * @Method({ "POST"})
     */
    public function newAction($object, $objectId, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $comment = new Comment();

        if($object === 'class') {
            $associatedEntity = $em->getRepository('AppBundle:OntoClass')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The class n° '.$objectId.' does not exist');
            }
            $comment->setClass($associatedEntity);
            $associatedObject = $associatedEntity;
        }
        /*else if($object === 'property') {
            $associatedEntity = $em->getRepository('AppBundle:Property')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property n° '.$objectId.' does not exist');
            }
            $comment->setProperty($associatedEntity);
            $associatedObject = $associatedEntity;
        }
        else throw $this->createNotFoundException('The requested object "'.$object.'" does not exist!');*/


        //$this->denyAccessUnlessGranted('edit', $associatedObject);

        $comment->setCreator($this->getUser());
        $comment->setModifier($this->getUser());
        $comment->setCreationTime(new \DateTime('now'));
        $comment->setModificationTime(new \DateTime('now'));


        $form = $this->createForm(CommentForm::class, $comment);

        $status = 'error';
        $message = '';

        // only handles data on POST
        $form->handleRequest($request);
        if(!$form->isSubmitted()){
            return $this->render('comment/new.html.twig', [
                'comment' => $comment,
                'commentForm' => $form->createView()
            ]);
        }
        else {
            if ($form->isSubmitted() && $form->isValid()) {
                $comment = $form->getData();
                $comment->setCreator($this->getUser());
                $comment->setModifier($this->getUser());
                $comment->setCreationTime(new \DateTime('now'));
                $comment->setModificationTime(new \DateTime('now'));

                $em = $this->getDoctrine()->getManager();
                $em->persist($comment);
                $em->flush();

                try {
                    $em->flush();
                    $status = 'Success';
                    $message = 'New comment saved';
                } catch (\Exception $e) {
                    $message = $e->getMessage();
                }


            }
            else {
                $message = 'Invalid form data';
            }

            $response = array(
                'status' => $status,
                'message' => $message
            );

            return new JsonResponse($response);
        }
    }
}