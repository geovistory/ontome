<?php

namespace AppBundle\Form;

use AppBundle\Entity\Property;
use AppBundle\Form\DataTransformer\SystemTypeToNumberTransformer;
use AppBundle\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EntityAssociationEditForm extends AbstractType
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
                'The EntityAssociationEditForm cannot be used without an authenticated user!'
            );
        }

        $choices = array();
        foreach ($options['entitiesVersion'] as $ev){
            if($ev['standardLabel'] != $ev['identifierInNamespace'])
                $choices[$ev['standardLabel']." – ".$ev['identifierInNamespace']] = $ev['id'];
            else
                $choices[$ev['standardLabel']] = $ev['id'];
        }

        if($options['object'] == 'class' and $options['inverse'])
        {
            $builder
                ->add('systemType', ChoiceType::class, array(
                        'choices'  => array(
                        'owl:equivalentClass' => 4,
                        'owl:disjointWith' => 19
                    ),
                    'label' => 'Relation'))
                ->add('sourceClassVersion', ChoiceType::class,
                    array(
                        'mapped' => false,
                        'label' => "Source class",
                        'choices'           => $choices,
                        'data'              => $options['defaultSource']
                    ));
        }
        elseif($options['object'] == 'class' and !$options['inverse'])
        {
            $builder
                ->add('systemType', ChoiceType::class, array(
                    'choices'  => array(
                        'owl:equivalentClass' => 4,
                        'owl:disjointWith' => 19
                    ),
                    'label' => 'Relation'))
                ->add('targetClassVersion', ChoiceType::class,
                    array(
                        'mapped' => false,
                        'label' => "Target class",
                        'choices'           => $choices,
                        'data'              => $options['defaultTarget']
                    ));
        }
        elseif($options['object'] == 'property' and $options['inverse'])
        {
            $builder
                ->add('systemType', ChoiceType::class, array(
                    'choices'  => array(
                        'owl:equivalentProperty' => 18,
                        'owl:inverseOf' => 20
                    ),
                    'label' => 'Relation'))
                ->add('sourcePropertyVersion', ChoiceType::class,
                    array(
                        'mapped' => false,
                        'label' => "Source property",
                        'choices'           => $choices,
                        'data'              => $options['defaultSource']
                    ));
        }
        elseif($options['object'] == 'property' and !$options['inverse'])
        {
            $builder
                ->add('systemType', ChoiceType::class, array(
                    'choices'  => array(
                        'owl:equivalentProperty' => 18,
                        'owl:inverseOf' => 20
                    ),
                    'label' => 'Relation'))
                ->add('targetPropertyVersion', ChoiceType::class,
                    array(
                        'mapped' => false,
                        'label' => "Target property",
                        'choices'           => $choices,
                        'data'              => $options['defaultTarget']
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
            'object' => 'class',
            'inverse' => false,
            'entitiesVersion' => null,
            'defaultSource' => null,
            'defaultTarget' => null
        ]);
    }
}
