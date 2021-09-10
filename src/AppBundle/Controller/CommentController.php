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
     * @Route("/comment/{objectType}/{objectId}/json",
     *     name="comment_show_json",
     *     requirements={"objectType"="^(class|property|class-association|property-association|entity-association|text-property|label|namespace){1}$","objectId"="^[0-9]+$"})
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
        else if($objectType === 'entity-association') {
            $associatedEntity = $em->getRepository('AppBundle:EntityAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The entity association n° '.$objectId.' does not exist');
            }
        }
        else if($objectType === 'text-property') {
            $associatedEntity = $em->getRepository('AppBundle:TextProperty')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The text property n° '.$objectId.' does not exist');
            }
        }
        else if($objectType === 'label') {
            $associatedEntity = $em->getRepository('AppBundle:Label')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The label n° '.$objectId.' does not exist');
            }
        }
        else if($objectType === 'namespace') {
            $associatedEntity = $em->getRepository('AppBundle:OntoNamespace')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The namespace n° '.$objectId.' does not exist');
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
     * @Route("/comment/new/{object}/{objectId}", name="comment_new", requirements={"object"="^(class-version|property-version|class-association|property-association|entity-association|text-property|label|namespace){1}$","objectId"="^[0-9]+$"})
     * @Method({ "POST"})
     */
    public function newAction($object, $objectId, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $comment = new Comment();

        if($object === 'class-version') {
            $classVersion = $em->getRepository('AppBundle:OntoClassVersion')->find($objectId);
            $associatedEntity = $classVersion->getClass();
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The class n° '.$objectId.' does not exist');
            }
            $namespaceForVersion = $em->getRepository('AppBundle:OntoNamespace')->find($classVersion->getNamespaceForVersion());
            $comment->setClass($associatedEntity);
        }
        else if($object === 'property-version') {
            $propertyVersion = $em->getRepository('AppBundle:PropertyVersion')->find($objectId);
            $associatedEntity = $propertyVersion->getProperty();
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property n° '.$objectId.' does not exist');
            }
            $namespaceForVersion = $em->getRepository('AppBundle:OntoNamespace')->find($propertyVersion->getNamespaceForVersion());
            $comment->setProperty($associatedEntity);
        }
        else if($object === 'class-association') {
            $associatedEntity = $em->getRepository('AppBundle:ClassAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The class association n° '.$objectId.' does not exist');
            }
            $namespaceForVersion = $em->getRepository('AppBundle:OntoNamespace')->find($associatedEntity->getNamespaceForVersion());
            $comment->setClassAssociation($associatedEntity);
        }
        else if($object === 'property-association') {
            $associatedEntity = $em->getRepository('AppBundle:PropertyAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property association n° '.$objectId.' does not exist');
            }
            $namespaceForVersion = $em->getRepository('AppBundle:OntoNamespace')->find($associatedEntity->getNamespaceForVersion());
            $comment->setPropertyAssociation($associatedEntity);
        }
        else if($object === 'entity-association') {
            $associatedEntity = $em->getRepository('AppBundle:EntityAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The entity association n° '.$objectId.' does not exist');
            }
            $namespaceForVersion = $em->getRepository('AppBundle:OntoNamespace')->find($associatedEntity->getNamespaceForVersion());
            $comment->setEntityAssociation($associatedEntity);
        }
        else if($object === 'text-property') {
            $associatedEntity = $em->getRepository('AppBundle:TextProperty')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The text property n° '.$objectId.' does not exist');
            }
            $namespaceForVersion = $em->getRepository('AppBundle:OntoNamespace')->find($associatedEntity->getNamespaceForVersion());
            $comment->setTextProperty($associatedEntity);
        }
        else if($object === 'label') {
            $associatedEntity = $em->getRepository('AppBundle:Label')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The label n° '.$objectId.' does not exist');
            }
            $namespaceForVersion = null;
            $comment->setLabel($associatedEntity);
        }
        else if($object === 'namespace') {
            $associatedEntity = $em->getRepository('AppBundle:OntoNamespace')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The namespace n° '.$objectId.' does not exist');
            }
            $namespaceForVersion = $associatedEntity;
            $comment->setNamespace($associatedEntity);
        }
        else throw $this->createNotFoundException('The requested object "'.$object.'" does not exist!');


        //$this->denyAccessUnlessGranted('edit', $associatedObject);

        if(!in_array($this->getUser()->getId(), $comment->getViewedBy())){
            $viewedBy = $comment->getViewedBy();
            $viewedBy[] = $this->getUser()->getId();
            $comment->setViewedBy($viewedBy);
        }

        $comment->setCreator($this->getUser());
        $comment->setModifier($this->getUser());
        $comment->setCreationTime(new \DateTime('now'));
        $comment->setModificationTime(new \DateTime('now'));
        $comment->setNamespaceForVersion($namespaceForVersion);


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
            $comment->setNamespaceForVersion($namespaceForVersion);

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

    /**
     * @Route("/comment/{object}/{objectId}/viewedby/json", name="viewed_by_json", requirements={"object"="^(class|property|class-association|property-association|entity-association|text-property|label|namespace|selectedObject){1}$","objectId"="^([0-9]+)|(selectedValue){1}$"})
     * @Method("GET")
     * @return JsonResponse
     */
    public function viewedByJson($object, $objectId, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        if($object === 'class') {
            $associatedEntity = $em->getRepository('AppBundle:OntoClass')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The class n° '.$objectId.' does not exist');
            }

            $comments = $em->getRepository('AppBundle:Comment')->findBy(array("class" => $associatedEntity));
        }
        else if($object === 'property') {
            $associatedEntity = $em->getRepository('AppBundle:Property')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property n° '.$objectId.' does not exist');
            }

            $comments = $em->getRepository('AppBundle:Comment')->findBy(array("property" => $associatedEntity));
        }
        else if($object === 'class-association') {
            $associatedEntity = $em->getRepository('AppBundle:ClassAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The class association n° '.$objectId.' does not exist');
            }

            $comments = $em->getRepository('AppBundle:Comment')->findBy(array("classAssociation" => $associatedEntity));
        }
        else if($object === 'property-association') {
            $associatedEntity = $em->getRepository('AppBundle:PropertyAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The property association n° '.$objectId.' does not exist');
            }

            $comments = $em->getRepository('AppBundle:Comment')->findBy(array("propertyAssociation" => $associatedEntity));
        }
        else if($object === 'entity-association') {
            $associatedEntity = $em->getRepository('AppBundle:EntityAssociation')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The entity association n° '.$objectId.' does not exist');
            }

            $comments = $em->getRepository('AppBundle:Comment')->findBy(array("entityAssociation" => $associatedEntity));
        }
        else if($object === 'text-property') {
            $associatedEntity = $em->getRepository('AppBundle:TextProperty')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The text property n° '.$objectId.' does not exist');
            }

            $comments = $em->getRepository('AppBundle:Comment')->findBy(array("textProperty" => $associatedEntity));
        }
        else if($object === 'label') {
            $associatedEntity = $em->getRepository('AppBundle:Label')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The label n° '.$objectId.' does not exist');
            }

            $comments = $em->getRepository('AppBundle:Comment')->findBy(array("label" => $associatedEntity));
        }
        else if($object === 'namespace') {
            $associatedEntity = $em->getRepository('AppBundle:OntoNamespace')->find($objectId);
            if (!$associatedEntity) {
                throw $this->createNotFoundException('The namespace n° '.$objectId.' does not exist');
            }

            $comments = $em->getRepository('AppBundle:Comment')->findBy(array("namespace" => $associatedEntity));
        }
        else throw $this->createNotFoundException('The requested object "'.$object.'" does not exist!');

        foreach ($comments as $comment){
            if(!in_array($this->getUser()->getId(), $comment->getViewedBy())){
                $viewedBy = $comment->getViewedBy();
                $viewedBy[] = $this->getUser()->getId();
                $comment->setViewedBy($viewedBy);
                $em->persist($comment);
                $em->flush();
            }
        }

        $response = array(
            'status' => 200,
            'message' => "OK"
        );

        return new JsonResponse($response);
    }
}