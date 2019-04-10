<?php

namespace AppBundle\Form;

use AppBundle\Form\DataTransformer\UserToNumberTransformer;
use Doctrine\ORM\EntityManagerInterface;
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


        $builder
            ->add('identifierInNamespace', TextType::class, array(
            ))
            ->add('labels', CollectionType::class, array(
                'label' => 'Enter a label and select a language',
                'entry_type' => LabelType::class,
                'entry_options' => array('label' => false),
                'error_bubbling' => false,
                'allow_add' => true,
                'by_reference' => false,
            ))
            ->add('domain')
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
            ->add('textProperties', CollectionType::class, array(
                'entry_type' => TextPropertyType::class,
                'entry_options' => array('label' => false),
                'error_bubbling' => false,
                'allow_add' => true,
                'by_reference' => false,
            ));

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\Property',
            "allow_extra_fields" => true
        ]);
    }
}
