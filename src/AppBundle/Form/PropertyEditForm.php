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

        $builder
            ->add('domain', EntityType::class,
                array(
                    'class' => OntoClass::class,
                    'label' => "domain",
                    'query_builder' => function(ClassRepository $repo) use ($user){
                        return $repo->findFilteredClassByActiveProjectOrderedById($user);
                    }
                ))
            ->add('range', EntityType::class,
                array(
                    'class' => OntoClass::class,
                    'label' => "range",
                    'query_builder' => function(ClassRepository $repo) use ($user){
                        return $repo->findFilteredClassByActiveProjectOrderedById($user);
                    }
                ))
            ->add('domainMinQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Min' => null,
                    '0' => 0,
                    '1' => 1,
                    'n' => -1,
                ),
            ))
            ->add('domainMaxQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Max' => null,
                    '0' => 0,
                    '1' => 1,
                    'n' => -1,
                ),
            ))
            ->add('rangeMinQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Min' => null,
                    '0' => 0,
                    '1' => 1,
                    'n' => -1,
                ),
            ))
            ->add('rangeMaxQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Max' => null,
                    '0' => 0,
                    '1' => 1,
                    'n' => -1,
                ),
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
