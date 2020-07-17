<?php

namespace AppBundle\Form;

use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoClassVersion;
use AppBundle\Form\DataTransformer\UserToNumberTransformer;
use AppBundle\Repository\ClassRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
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

        $choices = array();
        foreach ($options['classesVersion'] as $cv){
            if($cv['standardLabel'] != $cv['identifierInNamespace'])
                $choices[$cv['standardLabel']." – ".$cv['identifierInNamespace']] = $cv['id'];
            else
                $choices[$cv['standardLabel']] = $cv['id'];
        }

        $builder
            ->add('domainVersion', ChoiceType::class, array(
                'mapped' => false,
                'choices'           => $choices,
                'data'              => $options['defaultDomain']
            ))
            ->add('rangeVersion', ChoiceType::class, array(
                'mapped' => false,
                'choices'           => $choices,
                'data'              => $options['defaultRange']
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
            'data_class' => 'AppBundle\Entity\PropertyVersion',
            'allow_extra_fields' => true,
            'classesVersion' => null,
            'defaultDomain' => null,
            'defaultRange' => null
        ]);
    }
}
