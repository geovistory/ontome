<?php

namespace AppBundle\Form;

use AppBundle\Entity\TextProperty;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TextPropertyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('textProperty', TextareaType::class, array(
                'attr' => array('class' => 'tinymce')
            ))
            ->add('languageIsoCode', ChoiceType::class, array(
                'choices'  => array(
                    'English' => 'en',
                    'French' => 'fr',
                    'German' => 'de',
                    'Italian' => 'it',
                    'Spanish' => 'es'
                )
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => TextProperty::class,
        ));
    }

}