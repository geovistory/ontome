<?php

namespace AppBundle\Form;

use AppBundle\Entity\Property;
use AppBundle\Form\DataTransformer\PropertyToNumberTransformer;
use AppBundle\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PropertyAssociationEditForm extends AbstractType
{
    private $transformer;
    private $tokenStorage;
    private $em;

    public function __construct(PropertyToNumberTransformer $transformer, TokenStorageInterface $tokenStorage, EntityManagerInterface $em)
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

        // FILTRAGE : Récupérer les clés de namespaces à utiliser
        // Il n'y a pas besoin de rajouter le namespace de la propriété actuelle : il doit être activé pour le formulaire.
        if(is_null($user) || $user->getCurrentActiveProject()->getId() == 21){ // Utilisateur non connecté OU connecté et utilisant le projet public
            $namespacesId = $this->em->getRepository('AppBundle:OntoNamespace')->findPublicProjectNamespacesId();
        }
        else{ // Utilisateur connecté et utilisant un autre projet
            $namespacesId = $this->em->getRepository('AppBundle:OntoNamespace')->findNamespacesIdByUser($user);
        }

        $builder
            ->add('parentProperty', EntityType::class,
                array(
                    'class' => Property::class,
                    'label' => "Parent property",
                    'query_builder' => function(PropertyRepository $repo) use ($namespacesId){
                        return $repo->findPropertiesByNamespacesIdQueryBuilder($namespacesId);
                    }
                ))
            ->add('childProperty', HiddenType::class);

        $builder->get('childProperty')
            ->addModelTransformer($this->transformer);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\PropertyAssociation'
        ]);
    }
}
