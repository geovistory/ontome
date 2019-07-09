<?php

namespace AppBundle\Form;

use AppBundle\Entity\Project;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MyEnvironmentForm extends AbstractType
{
    private $tokenStorage;
    private $em;

    public function __construct(TokenStorageInterface $tokenStorage, EntityManagerInterface $em)
    {
        $this->tokenStorage = $tokenStorage;
        $this->em = $em;
    }
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $userID = $this->tokenStorage->getToken()->getUser()->getId();
        $user = $this->em->getRepository('AppBundle:User')->find($userID);

        if (!$user) {
            throw new \LogicException(
                'The MyEnvironmentForm cannot be used without an authenticated user!'
            );
        }

        $publicProject = $this->em->getRepository('AppBundle:Project')->find(21); // Public project
        $userProjects = array($publicProject);
        foreach($user->getUserProjectAssociations() as $userProject)
            $userProjects[] = $userProject->getProject();

        $builder
            ->add('activeProject', EntityType::class, [
                'class' => Project::class,
                'choices' => $userProjects
                ]
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
