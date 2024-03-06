<?php

namespace AppBundle\Form;

use AppBundle\Entity\TextProperty;
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

class TextPropertyType extends AbstractType
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
                'The TextPropertyType cannot be used without an authenticated user!'
            );
        }

        $labelTextProperty = $options['labelTextProperty'];

        //if the systemType of the textProperty is 31 (owl:versionInfo), we only need an input text field with of 10 characters long
        if (isset($options['systemType']) and $options['systemType'] === 31) {
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
                    'label' => $labelTextProperty,
                    'constraints' => array(
                        new \Symfony\Component\Validator\Constraints\NotBlank()
                    )
                ))
                ->add('languageIsoCode', ChoiceType::class, array(
                    'choices'  => array(
                        'English' => 'en',
                        'French' => 'fr',
                        'Canadian French' => 'fr-CA',
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
            'labelTextProperty' => false,
            'systemType' => 0
        ));
    }

}