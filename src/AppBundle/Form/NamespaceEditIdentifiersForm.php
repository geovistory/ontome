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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class NamespaceEditIdentifiersForm extends AbstractType
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
        $builder
            ->add('classPrefix', TextType::class, array(
                'label' => false
            ))
            ->add('propertyPrefix', TextType::class, array(
                'label' => false
            ))
            ->add('currentClassNumber', IntegerType::class, array(
                'label' => false
            ))
            ->add('currentPropertyNumber', IntegerType::class, array(
                'label' => false
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\OntoNamespace',
            "allow_extra_fields" => true
        ]);
    }
}
