<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 07/11/2022
 * Time: 15:30
 */

namespace AppBundle\Form;


use AppBundle\Entity\ProjectThesaurusAssociation;
use AppBundle\Form\DataTransformer\UserToNumberTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProjectThesaurusAssociationForm extends AbstractType
{
    public function __construct(UserToNumberTransformer $transformer, TokenStorageInterface $tokenStorage, EntityManagerInterface $em)
    {
        $this->transformer = $transformer;
        $this->tokenStorage = $tokenStorage;
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        try {

            $userID = $this->tokenStorage->getToken()->getUser()->getId();
            $user = $this->em->getRepository('AppBundle:User')->find($userID);
        }
        catch (\Exception $e)
        {
            $message = $e->getMessage();
        }

        $builder
            ->add('thesaurusURL', TextType::class, array(
                'label' => 'Thesaurus URL',
                'data' => 'https://ontomeopentheso.mom.fr/ontomeopentheso/?idt=th8'
            ))
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
            'data_class' => ProjectThesaurusAssociation::class,
        ));
    }

}