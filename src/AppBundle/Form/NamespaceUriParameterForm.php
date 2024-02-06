<?php

namespace AppBundle\Form;

use AppBundle\Form\DataTransformer\OntoClassToNumberTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class NamespaceUriParameterForm extends AbstractType
{

    private $transformer;
    private $tokenStorage;
    private $em;

    public function __construct(OntoClassToNumberTransformer $transformer, TokenStorageInterface $tokenStorage, EntityManagerInterface $em)
    {
        $this->transformer = $transformer;
        $this->tokenStorage = $tokenStorage;
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $userID =$this->tokenStorage->getToken()->getUser()->getId();
        $user = $this->em->getRepository('AppBundle:User')->find($userID);

        if (!$user) {
            throw new \LogicException(
                'The NamespaceUriParameterForm cannot be used without an authenticated user!'
            );
        }

        /*
         * 0: Entity identifier
         * 1: Entity identifier + label
         * 2: camelCase
         * 3: No parameter
         */
        $choices = array('Entity identifier' => 0, 'Entity identifier + label' => 1, 'camelCase' => 2, 'No parameter' => 3);
        $builder
            ->add('uriParameter', ChoiceType::class, array(
                'choices' => $choices,
                'label' => false,
                'data' => $options['default_choice']
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\OntoNamespace',
            "allow_extra_fields" => true,
            'default_choice' => 0
        ]);
    }
}
