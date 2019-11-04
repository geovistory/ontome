<?php

namespace AppBundle\Form;

use AppBundle\Entity\OntoNamespace;
use AppBundle\Entity\ProjectAssociation;
use AppBundle\Form\DataTransformer\UserToNumberTransformer;
use AppBundle\Repository\NamespaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PublicProjectNamespaceAssociationAddForm extends AbstractType
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
                'The LabelForm cannot be used without an authenticated user!'
            );
        }

        $builder
            ->add('namespace', EntityType::class,
                array(
                    'class'=>OntoNamespace::class,
                    "label" => "Namespace",
                    "query_builder" => function(NamespaceRepository $repo) {
                        return $repo->findAvailableNamespacesForPublicProjectAssociation();
                    }
                )
            )
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
            'data_class' => 'AppBundle\Entity\ProjectAssociation',
        ));
    }

}