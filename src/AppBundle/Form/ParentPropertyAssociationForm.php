<?php

namespace AppBundle\Form;

use AppBundle\Entity\OntoClass;
use AppBundle\Entity\Property;
use AppBundle\Form\DataTransformer\PropertyToNumberTransformer;
use AppBundle\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ParentPropertyAssociationForm extends AbstractType
{
    private $transformer;
    private $tokenStorage;
    private $em;

    public function __construct(PropertyToNumberTransformer  $transformer, TokenStorageInterface $tokenStorage, EntityManagerInterface $em)
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

        $builder
            ->add('parentProperty', EntityType::class,
                array(
                    'class' => Property::class,
                    'label' => "Parent property",
                    'query_builder' => function(PropertyRepository $repo) use ($user){
                        return $repo->findFilteredPropertiesByActiveProjectOrderedById($user);
                    }
                ))
            ->add('childProperty', HiddenType::class)
            ->add('textProperties', CollectionType::class, array(
                'entry_type' => TextPropertyType::class,
                'entry_options' => array('label' => false),
                'error_bubbling' => false,
                'allow_add' => true,
                'by_reference' => false,
            ));

        $builder->get('childProperty')
            ->addModelTransformer($this->transformer);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\PropertyAssociation',
            "allow_extra_fields" => true
        ]);
    }
}
