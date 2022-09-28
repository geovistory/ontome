<?php

namespace AppBundle\Form;

use AppBundle\Form\DataTransformer\UserToNumberTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class NamespaceForm extends AbstractType
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
                'The NamespaceForm cannot be used without an authenticated user!'
            );
        }

        $builder
            ->add('isExternalNamespace', CheckboxType::class, [
                'required' => true,
                'label' => 'External namespace',
                'attr' => ['autocomplete' => 'off']
            ])
            ->add('uriGenerator', TextType::class, array(
                'label' => 'OntoME URI generator',
                'mapped' => false,
                'attr' => ['autocomplete' => 'off']
            ))
            ->add('namespaceURI', UrlType::class, array(
                'label' => 'Base URI',
                'default_protocol' => 'http',
                'attr' => ['autocomplete' => 'off']
            ))
            ->add('rootNamespacePrefix', TextType::class, array(
                'label' => 'Root namespace prefix',
                'attr' => ['autocomplete' => 'off']
            ))
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
            'data_class' => 'AppBundle\Entity\OntoNamespace',
        ));
    }

}