<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 02/12/2018
 * Time: 09:19
 */

namespace AppBundle\Form;


use AppBundle\Entity\TextProperty;
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
            ->add('automaticIdentifierManagement', CheckboxType::class, [
                'mapped' => false,
                'data' => true,
                'label' => 'Automatic identifier management',
            ])
            ->add('classPrefix', TextType::class, array(
                'label' => 'Class prefix',
                'attr' => array(
                    'size' => '6',
                    'maxlength' => '6'
                ),
            ))
            ->add('propertyPrefix', TextType::class, array(
                'label' => 'Property prefix',
                'attr' => array(
                    'size' => '6',
                    'maxlength' => '6'
                ),
            ))
            ->add('textProperties', CollectionType::class, array(
                'label' => 'Enter a description and select a language',
                'entry_type' => TextPropertyType::class,
                'entry_options' => array('label' => false),
                'error_bubbling' => false,
                'allow_add' => true,
                'by_reference' => false,
            ))
            ->add('contributors', TextPropertyType::class, array(
                'label' => 'Contributors to the ongoing namespace',
                'error_bubbling' => false,
                'by_reference' => false,
                'mapped' => false,
                'systemType' => 2, //Contributors: pas besoin d'éditeur enrichi,
            ))
            ->add('referenceNamespaces', HiddenType::class, array(
                'mapped' => false
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\OntoNamespace',
            "allow_extra_fields" => true,
            'validation_groups' => ['Default', 'Description'],
            'txtpContributors' => new TextProperty()
        ]);
    }

}