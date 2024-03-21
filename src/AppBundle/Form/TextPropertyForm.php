<?php

namespace AppBundle\Form;

use AppBundle\Entity\TextProperty;
use AppBundle\Form\DataTransformer\SystemTypeToNumberTransformer;
use AppBundle\Form\DataTransformer\UserToNumberTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class TextPropertyForm extends AbstractType
{
    private $transformer;
    private $tokenStorage;
    private $em;

    public function __construct(UserToNumberTransformer $transformer, SystemTypeToNumberTransformer $st_transformer, TokenStorageInterface $tokenStorage, EntityManagerInterface $em)
    {
        $this->transformer = $transformer;
        $this->st_transformer = $st_transformer;
        $this->tokenStorage = $tokenStorage;
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $userID = $this->tokenStorage->getToken()->getUser()->getId();
        $user = $this->em->getRepository('AppBundle:User')->find($userID);

        if (!$user) {
            throw new \LogicException(
                'The TextPropertyForm cannot be used without an authenticated user!'
            );
        }

        $labelTextProperty = $options['labelTextProperty'];

        //if the systemType of the textProperty is 31 (owl:versionInfo), we only need an input text field with of 10 characters long
        if ($options['systemType'] === 31) {
            $builder
                ->add('textProperty', TextType::class, array(
                    'attr' => array(
                        'size' => '10',
                        'maxlength' => '10'
                    ),
                    'label' => $labelTextProperty
                ))
                ->add('languageIsoCode', HiddenType::class, array(
                    'data' => 'en'
                ));
        }
        // Les contributors n'ont pas besoin de l'editeur enrichi
        elseif (isset($options['systemType']) and $options['systemType'] === 2) {
            $builder
                ->add('textProperty', TextareaType::class, array(
                    'label' => $labelTextProperty
                ))
                ->add('languageIsoCode', ChoiceType::class, array(
                    'choices'  => array(
                        'English' => 'en',
                        'French' => 'fr',
                        'German' => 'de',
                        'Greek' => 'el',
                        'Spanish' => 'es',
                        'Italian' => 'it',
                        'Portuguese' => 'pt',
                        'Russian' => 'ru',
                        'Chinese' => 'zh'
                    ),
                    'label' => 'Language'
                ));
        }
        elseif(in_array($options['systemType'],[33,34,35]) and in_array($options['objectType'], ['class-version', 'property-version'])) {
            $builder
                ->add('systemType', ChoiceType::class, array(
                    'choices'  => array(
                        'Internal note' => 33,
                        'Context note' => 34,
                        'Bibliographical note' => 35
                    ),
                    'label' => 'Type of note'
                ))
                ->add('textProperty', TextareaType::class, array(
                    'attr' => array('class' => 'tinymce'),
                    'label' => $labelTextProperty
                ))
                ->add('languageIsoCode', ChoiceType::class, array(
                    'choices'  => array(
                        'English' => 'en',
                        'French' => 'fr',
                        'German' => 'de',
                        'Greek' => 'el',
                        'Spanish' => 'es',
                        'Italian' => 'it',
                        'Portuguese' => 'pt',
                        'Russian' => 'ru',
                        'Chinese' => 'zh'
                    ),
                    'label' => 'Language'
                ));
        }
        else {
            $builder
                ->add('textProperty', TextareaType::class, array(
                    'attr' => array('class' => 'tinymce'),
                    'label' => $labelTextProperty
                ))
                ->add('languageIsoCode', ChoiceType::class, array(
                    'choices'  => array(
                        'English' => 'en',
                        'French' => 'fr',
                        //'Canadian French' => 'fr-CA',
                        'German' => 'de',
                        'Greek' => 'el',
                        'Spanish' => 'es',
                        'Italian' => 'it',
                        'Portuguese' => 'pt',
                        'Russian' => 'ru',
                        'Chinese' => 'zh',
                    ),
                    'label' => 'Language'
                ));
        }

        if($builder->has('systemType')){
            $builder->get('systemType')
                ->addModelTransformer($this->st_transformer);
        }

        $builder
            ->add('creator', HiddenType::class)
            ->add('modifier', HiddenType::class);
        $builder->get('creator')
            ->addModelTransformer($this->transformer);
        $builder->get('modifier')
            ->addModelTransformer($this->transformer);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => TextProperty::class,
            "allow_extra_fields" => true,
            'labelTextProperty' => false,
            'systemType' => 0,
            'objectType' => 'class'
        ));
    }

}