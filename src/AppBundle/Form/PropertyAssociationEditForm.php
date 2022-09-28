<?php

namespace AppBundle\Form;

use AppBundle\Entity\Property;
use AppBundle\Form\DataTransformer\PropertyToNumberTransformer;
use AppBundle\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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

        $choices = array();
        foreach ($options['propertiesVersion'] as $pv){
            if($pv['standardLabel'] != $pv['identifierInNamespace']){
                $choices[$pv['rootNamespacePrefix'] . ":" . $pv['identifierInNamespace'] . " " . $pv['standardLabel']] = $pv['id'];

            }
            else {
                $choices[$pv['rootNamespacePrefix'] . ":" . $pv['standardLabel']] = $pv['id'];
            }
        }

        $builder
            ->add('parentPropertyVersion', ChoiceType::class, array(
                'mapped' => false,
                'choices'           => $choices,
                'data'              => $options['defaultParent']
            ))
            ->add('childProperty', HiddenType::class);

        $builder->get('childProperty')
            ->addModelTransformer($this->transformer);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'AppBundle\Entity\PropertyAssociation',
            'allow_extra_fields' => true,
            'propertiesVersion' => null,
            'defaultParent' => null
        ]);
    }
}
