<?php

namespace AppBundle\Form;

use AppBundle\Entity\ClassAssociation;
use AppBundle\Form\DataTransformer\OntoClassToNumberTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class parentClassAssociationForm extends AbstractType
{
    private $transformer;

    public function __construct(OntoClassToNumberTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('parentClass')
            ->add('childClass', HiddenType::class
            )
            ->add('notes');

        $builder->get('childClass')
            ->addModelTransformer($this->transformer);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\ClassAssociation'
        ]);
    }
}
