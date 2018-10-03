<?php

namespace AppBundle\Form;

use AppBundle\Form\DataTransformer\PropertyToNumberTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PropertyAssociationEditForm extends AbstractType
{
    private $transformer;

    public function __construct(PropertyToNumberTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('parentProperty')
            ->add('childProperty', HiddenType::class);

        $builder->get('childProperty')
            ->addModelTransformer($this->transformer);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\PropertyAssociation'
        ]);
    }
}
