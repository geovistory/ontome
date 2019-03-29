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
     * @Route("/comment/{objectType}/{objectId}/json", name="comment_show_json")
     * @Method("GET")
     * @param string $objectType    The object type name
     * @param int  $objectId    The id of the object
     * @return JsonResponse a Json formatted comments list
     */
    public function getCommentsAction($objectType, $objectId)
    {
        $em = $this->getDoctrine()->getManager();

        //$objectType = 'class';
        //$objectId = 268;
        if($objectType === 'class') {
            $associatedEntity = $em->getRepository('AppBundle:OntoClass')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The class n° '.$objectId.' does not exist');
            }
        }
        else if($objectType === 'property') {
            $associatedEntity = $em->getRepository('AppBundle:Property')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property n° '.$objectId.' does not exist');
            }
        }
        else if($objectType === 'class-association') {
            $associatedEntity = $em->getRepository('AppBundle:ClassAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The class association n° '.$objectId.' does not exist');
            }
        }
        else if($objectType === 'property-association') {
            $associatedEntity = $em->getRepository('AppBundle:PropertyAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property association n° '.$objectId.' does not exist');
            }
        }
        else throw $this->createNotFoundException('The requested object "'.$objectType.'" does not exist!');

        $comments = [];

        foreach ($associatedEntity->getComments() as $comment) {
            $comments[] = [
                'id' => $comment->getId(),
                'creator' => $comment->getCreator(),
                'text' => $comment->getComment(),
                'creationTime' => $comment->getCreationTime()->format('M d, Y')
            ];
        }

        $data =[
            'comments' => $comments
        ];
        return new JsonResponse($data);
    }

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
        }
        /*else if($object === 'property') {
            $associatedEntity = $em->getRepository('AppBundle:Property')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property n° '.$objectId.' does not exist');
            }
            $comment->setProperty($associatedEntity);
        }*/
        else if($object === 'class-association') {
            $associatedEntity = $em->getRepository('AppBundle:ClassAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The class association n° '.$objectId.' does not exist');
            }
            $comment->setClassAssociation($associatedEntity);
        }
        else if($object === 'property-association') {
            $associatedEntity = $em->getRepository('AppBundle:PropertyAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property association n° '.$objectId.' does not exist');
            }
            $comment->setPropertyAssociation($associatedEntity);
        }
        else if($object === 'text-property') {
            $associatedEntity = $em->getRepository('AppBundle:TextProperty')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The text property n° '.$objectId.' does not exist');
            }
            $comment->setProperty($associatedEntity);
        }
        else throw $this->createNotFoundException('The requested object "'.$object.'" does not exist!');


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

        if ($form->isSubmitted() && $form->isValid()) {
            $comment = $form->getData();
            $comment->setCreator($this->getUser());
            $comment->setModifier($this->getUser());
            $comment->setCreationTime(new \DateTime('now'));
            $comment->setModificationTime(new \DateTime('now'));

            $em = $this->getDoctrine()->getManager();
            $em->persist($comment);
            $em->flush();

            $html = $this->renderView('comment/new.html.twig', [
                'comment' => $comment,
                'commentForm' => $form->createView()
            ]);

            try {
                $em->flush();
                $status = 'Success';
                $message = 'New comment saved';
            } catch (\Exception $e) {
                $message = $e->getMessage();
            }

            $response = array(
                'status' => $status,
                'message' => $message,
                'html' => $html
            );

            return new JsonResponse($response);

        }
        else if ($form->isSubmitted() && !$form->isValid()) {
            $status = 'Error';
            $message = 'Invalid form validation';
            $html = $this->renderView('comment/new.html.twig', [
                'comment' => $comment,
                'commentForm' => $form->createView()
            ]);
            $response = array(
                'status' => $status,
                'message' => $message,
                'html' => $html
            );
            return new JsonResponse($response);
        }
        else return $this->render('comment/new.html.twig', [
            'comment' => $comment,
            'commentForm' => $form->createView()
        ]);
    }
}