<?php

namespace AppBundle\Form;

use AppBundle\Entity\OntoClass;
use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\User;
use AppBundle\Form\DataTransformer\OntoClassToNumberTransformer;
use AppBundle\Form\DataTransformer\UserToNumberTransformer;
use AppBundle\Repository\ClassRepository;
use AppBundle\Repository\NamespaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class OutgoingPropertyQuickAddForm extends AbstractType
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
                'The OutgoingPropertyQuickAddForm cannot be used without an authenticated user!'
            );
        }

        // FILTRAGE : Récupérer les clés de namespaces à utiliser
        // Il n'y a pas besoin de rajouter le namespace de la propriété actuelle : il doit être activé pour le formulaire.
        if(is_null($user) || $user->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $this->em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $this->em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($user);
        }

        $builder
            ->add('identifierInNamespace', TextType::class, array(
            ))
            ->add('labels', CollectionType::class, array(
                'label' => 'Enter a label and select a language',
                'entry_type' => LabelType::class,
                'entry_options' => array('label' => false, 'canInverseLabel' => true),
                'error_bubbling' => false,
                'allow_add' => true,
                'by_reference' => false,
            ))
            ->add('range', EntityType::class,
                array(
                    'class' => OntoClass::class,
                    'label' => "Range",
                    'query_builder' => function(ClassRepository $repo) use ($namespacesId){
                        return $repo->findClassesByNamespacesIdQueryBuilder($namespacesId);
                    }
                ))
            ->add('domainMinQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Min' => null,
                    '0' => 0,
                    '1' => 1,
                    'n' => -1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                ),
                'preferred_choices' => [null, 0, 1, -1],
            ))
            ->add('domainMaxQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Max' => null,
                    '1' => 1,
                    'n' => -1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                ),
                'preferred_choices' => [null, 1, -1],
            ))
            ->add('rangeMinQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Min' => null,
                    '0' => 0,
                    '1' => 1,
                    'n' => -1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                ),
                'preferred_choices' => [null, 0, 1, -1],
            ))
            ->add('rangeMaxQuantifier',ChoiceType::class, array(
                'choices'  => array(
                    'Max' => null,
                    '1' => 1,
                    'n' => -1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5,
                ),
                'preferred_choices' => [null, 1, -1],
            ))
            ->add('textProperties', CollectionType::class, array(
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
            'data_class' => 'AppBundle\Entity\Property',
            "allow_extra_fields" => true
        ]);
    }
}
