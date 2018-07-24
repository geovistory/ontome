<?php

namespace AppBundle\Form;

use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\User;
use AppBundle\Form\DataTransformer\OntoClassToNumberTransformer;
use AppBundle\Form\DataTransformer\UserToNumberTransformer;
use AppBundle\Repository\NamespaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ClassQuickAddForm extends AbstractType
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
                'The ClassQuickAddForm cannot be used without an authenticated user!'
            );
        }

        $builder
            ->add('namespace', EntityType::class, array(
                'required' => true,
                'class' => 'AppBundle\Entity\OntoNamespace',
                'placeholder' => 'Choose a namespace',
                'label' => 'Namespace',
                'query_builder' => function (NamespaceRepository $namespaceRepository, User $user) {
                    return $namespaceRepository->findAllowedOngoingNamespaceByUser($user);
                }
            ))
            ->add('label', CollectionType::class, array(
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
            ));

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\ClassAssociation',
            "allow_extra_fields" => true
        ]);
    }
}
