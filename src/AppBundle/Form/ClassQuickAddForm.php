<?php

namespace AppBundle\Form;

use AppBundle\Entity\OntoNamespace;
use AppBundle\Form\DataTransformer\OntoClassToNumberTransformer;
use AppBundle\Repository\NamespaceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClassQuickAddForm extends AbstractType
{
    private $transformer;

    public function __construct(OntoClassToNumberTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('namespaces', EntityType::class, array(
                'required' => true,
                'class' => 'AppBundle\Entity\OntoNamespace',
                'placeholder' => 'Choose a namespace',
                'label' => 'Namespace',
                'query_builder' => function (NamespaceRepository $namespaceRepository) {
                    return $namespaceRepository->findAll();
                }
            ))
            ->add('labels', CollectionType::class, array(
                'required' => true,
                'entry_type' => LabelType::class,
                'entry_options' => array('label' => false),
                'error_bubbling' => false,
                'allow_add' => true,
                'by_reference' => false,
            ))
            ->add('textProperties', CollectionType::class, array(
                'required' => true,
                'entry_type' => TextPropertyType::class,
                'entry_options' => array('label' => false),
                'error_bubbling' => false,
                'allow_add' => true,
                'by_reference' => false,
            ))
            ->add('notes');

        $builder->get('childClass')
            ->addModelTransformer($this->transformer);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\ClassAssociation',
            "allow_extra_fields" => true
        ]);
    }
}
