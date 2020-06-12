<?php

namespace AppBundle\Form;

use AppBundle\Entity\OntoClass;
use AppBundle\Form\DataTransformer\UserToNumberTransformer;
use AppBundle\Repository\ClassRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PropertyEditForm extends AbstractType
{

    private $transformer;
    private $tokenStorage;
    private $em;

    public function __construct(UserToNumberTransformer $transformer, TokenStorageInterface $tokenStorage, EntityManagerInterface $em)
    {
        $this->transformer = $transformer;
        $this->tokenStorage = $tokenStorage;
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $userID =$this->tokenStorage->getToken()->getUser()->getId();
        $user = $this->em->getRepository('AppBundle:User')->find($userID);

        if (!$user) {
            throw new \LogicException(
                'The ParentClassAssociationForm cannot be used without an authenticated user!'
            );
        }

        // FILTRAGE : Récupérer les clés de namespaces à utiliser
        // Il n'y a pas besoin de rajouter le namespace de la propriété actuelle : il doit être activé pour le formulaire.
        if(is_null($user) || $user->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $this->em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $this->em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($user);
        }

        $builder
            ->add('domain', EntityType::class,
                array(
                    'class' => OntoClass::class,
                    'label' => "domain",
                    'query_builder' => function(ClassRepository $repo) use ($namespacesId){
                        return $repo->findClassesByNamespacesIdQueryBuilder($namespacesId);
                    }
                ))
            ->add('range', EntityType::class,
                array(
                    'class' => OntoClass::class,
                    'label' => "range",
                    'query_builder' => function(ClassRepository $repo) use ($namespacesId){
                        return $repo->findClassesByNamespacesIdQueryBuilder($namespacesId);
                    }
                ))
            ->add('domainMinQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Min' => null,
                    '0' => 0,
                    '1' => 1,
                    'n' => -1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                ),
                'preferred_choices' => [null, 0, 1, -1],
            ))
            ->add('domainMaxQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Max' => null,
                    '1' => 1,
                    'n' => -1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                ),
                'preferred_choices' => [null, 1, -1],
            ))
            ->add('rangeMinQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Min' => null,
                    '0' => 0,
                    '1' => 1,
                    'n' => -1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                ),
                'preferred_choices' => [null, 0, 1, -1],
            ))
            ->add('rangeMaxQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Max' => null,
                    '1' => 1,
                    'n' => -1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                ),
                'preferred_choices' => [null, 1, -1],
            ))
        ;

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\Property'
        ]);
    }
}
