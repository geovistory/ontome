<?php

namespace AppBundle\Form;

use AppBundle\Form\DataTransformer\SystemTypeToNumberTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntityAssociationEditForm extends AbstractType
{

    public function __construct(SystemTypeToNumberTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if($options['object'] == 'class')
        {
            $builder
                ->add('systemType', ChoiceType::class, array(
                        'choices'  => array(
                        'owl:equivalentClass' => 4,
                        'owl:disjointWith' => 19
                    ),
                    'label' => 'Type relation'))
                ->add('targetClass');
        }
        elseif($options['object'] == 'property')
        {
            $builder
                ->add('systemType', ChoiceType::class, array(
                    'choices'  => array(
                        'owl:equivalentProperty' => 18,
                        'owl:inverseOf' => 20
                    ),
                    'label' => 'Type relation'))
                ->add('targetProperty');
        }

        $builder->get('systemType')
            ->addModelTransformer($this->transformer);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\EntityAssociation',
            "allow_extra_fields" => true,
            'object' => 'class'
        ]);
    }
}
