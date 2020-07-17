<?php

namespace AppBundle\Form;

use AppBundle\Entity\OntoClass;
use AppBundle\Form\DataTransformer\OntoClassToNumberTransformer;
use AppBundle\Repository\ClassRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ClassAssociationEditForm extends AbstractType
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
                'The ParentPropertyAssociationForm cannot be used without an authenticated user!'
            );
        }

        $choices = array();
        foreach ($options['classesVersion'] as $cv){
            if($cv['standardLabel'] != $cv['identifierInNamespace'])
                $choices[$cv['standardLabel']." – ".$cv['identifierInNamespace']] = $cv['id'];
            else
                $choices[$cv['standardLabel']] = $cv['id'];
        }

        $builder
            ->add('parentClassVersion', ChoiceType::class, array(
                'mapped' => false,
                'choices'           => $choices,
                'data'              => $options['defaultParent']
            ))
            ->add('childClass', HiddenType::class);

        $builder->get('childClass')
            ->addModelTransformer($this->transformer);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\ClassAssociation',
            'allow_extra_fields' => true,
            'classesVersion' => null,
            'defaultParent' => null
        ]);
    }
}
