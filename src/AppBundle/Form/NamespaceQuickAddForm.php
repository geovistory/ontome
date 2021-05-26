<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 02/12/2018
 * Time: 09:19
 */

namespace AppBundle\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NamespaceQuickAddForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('isExternalNamespace', CheckboxType::class, [
                'required' => true,
                'label' => 'External namespace',
            ])
            ->add('namespaceURI', UrlType::class, array(
                'label' => 'Namespace URI',
                'default_protocol' => 'http'
            ))
            ->add('uriGenerator', TextType::class, array(
                'label' => 'OntoME URI generator',
                'mapped' => false
            ))
            ->add('rootNamespacePrefix', TextType::class, array(
                'label' => 'Root namespace prefix'
            ))
            ->add('labels', CollectionType::class, array(
                'label' => 'Enter a label and select a language',
                'entry_type' => LabelType::class,
                'entry_options' => array('label' => false),
                'error_bubbling' => false,
                'allow_add' => true,
                'by_reference' => false,
            ))
            ->add('textProperties', CollectionType::class, array(
                'label' => 'Enter a description and select a language',
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
            'data_class' => 'AppBundle\Entity\OntoNamespace',
            "allow_extra_fields" => true,
            'validation_groups' => ['Default', 'Description']
        ]);
    }

}