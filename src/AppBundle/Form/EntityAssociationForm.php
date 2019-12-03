<?php

namespace AppBundle\Form;

use AppBundle\Entity\OntoClass;
use AppBundle\Entity\Property;
use AppBundle\Form\DataTransformer\SystemTypeToNumberTransformer;
use AppBundle\Repository\ClassRepository;
use AppBundle\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EntityAssociationForm extends AbstractType
{

    private $transformer;
    private $tokenStorage;
    private $em;

    public function __construct(SystemTypeToNumberTransformer $transformer, TokenStorageInterface $tokenStorage, EntityManagerInterface $em)
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
                'The IngoingPropertyQuickAddForm cannot be used without an authenticated user!'
            );
        }

        $builder
            ->add('textProperties', CollectionType::class, array(
                'entry_type' => TextPropertyType::class,
                'entry_options' => array('label' => false),
                'error_bubbling' => false,
                'allow_add' => true,
                'by_reference' => false,
                ));

        if($options['object'] == 'class')
        {
            $builder
                ->add('systemType', ChoiceType::class, array(
                        'choices'  => array(
                        'owl:equivalentClass' => 4,
                        'owl:disjointWith' => 19
                    ),
                    'label' => 'Relation'))
                ->add('targetClass', EntityType::class,
                    array(
                        'class' => OntoClass::class,
                        'label' => "Related class",
                        'query_builder' => function(ClassRepository $repo) use ($user){
                            return $repo->findFilteredClassByActiveProjectOrderedById($user);
                        }
                    ));
        }
        elseif($options['object'] == 'property')
        {
            $builder
                ->add('systemType', ChoiceType::class, array(
                    'choices'  => array(
                        'owl:equivalentProperty' => 18,
                        'owl:inverseOf' => 20
                    ),
                    'label' => 'Relation'))
                ->add('targetProperty', EntityType::class,
                    array(
                        'class' => Property::class,
                        'label' => "Related property",
                        'query_builder' => function(PropertyRepository $repo) use ($user){
                            return $repo->findFilteredPropertiesByActiveProjectOrderedById($user);
                        }
                    ));
        }

        $builder->get('systemType')
            ->addModelTransformer($this->transformer);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\EntityAssociation',
            "allow_extra_fields" => true,
            'object' => 'class'
        ]);
    }
}
