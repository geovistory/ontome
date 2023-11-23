<?php

namespace AppBundle\Form;

use AppBundle\Entity\OntoClass;
use AppBundle\Form\DataTransformer\UserToNumberTransformer;
use AppBundle\Repository\ClassRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class IngoingPropertyQuickAddForm extends AbstractType
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
        $userID = $this->tokenStorage->getToken()->getUser()->getId();
        $user = $this->em->getRepository('AppBundle:User')->find($userID);

        if (!$user) {
            throw new \LogicException(
                'The IngoingPropertyQuickAddForm cannot be used without an authenticated user!'
            );
        }

        $choices = array();
        foreach ($options['classesVersion'] as $cv){
            if($cv['standardLabel'] != $cv['identifierInNamespace'])
                $choices[$cv['rootNamespacePrefix'].":".$cv['standardLabel']." – ".$cv['identifierInNamespace']] = $cv['id'];
            else
                $choices[$cv['rootNamespacePrefix'].":".$cv['standardLabel']] = $cv['id'];
        }

        $builder
            ->add('identifierInNamespace', TextType::class, array(
            ))
            ->add('labels', CollectionType::class, array(
                'label' => 'Enter a label and select a language',
                'entry_type' => LabelType::class,
                'entry_options' => array('label' => false, 'canInverseLabel' => true),
                'error_bubbling' => false,
                'allow_add' => true,
                'by_reference' => false,
            ))
            ->add('domainVersion', ChoiceType::class, array(
                'mapped' => false,
                'choices'           => $choices
            ))
            ->add('domainMinQuantifierVersion',ChoiceType::class, array(
                'mapped' => false,
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
            ->add('domainMaxQuantifierVersion',ChoiceType::class, array(
                'mapped' => false,
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
            ->add('rangeMinQuantifierVersion',ChoiceType::class, array(
                'mapped' => false,
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
            ->add('rangeMaxQuantifierVersion',ChoiceType::class, array(
                'mapped' => false,
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
            ->add('textProperties', CollectionType::class, array(
                'entry_type' => TextPropertyType::class,
                'entry_options' => array('label' => false),
                'error_bubbling' => false,
                'allow_add' => true,
                'by_reference' => false,
            ));

        if($options['is_external']){
            $builder->add('identifierInUri', TextType::class, array(
                'label' => 'Set an identifier for URI',
                'data' => $options['identifier_in_uri_prefilled'],
                'attr' => array('data-uri-param' => $options['uri_param'], 'data-default-value' => $options['identifier_in_uri_prefilled'])
            ));
        }

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\Property',
            "allow_extra_fields" => true,
            'classesVersion' => null,
            'uri_param' => 0,
            'identifier_in_uri_prefilled' => '',
            'is_external' => false
        ]);
    }
}
