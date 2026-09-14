<?php

namespace App\Form;

use App\Entity\Establishment;
use App\Entity\Group;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/** @extends AbstractType<User> */
class InscriptionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pseudo', TextType::class, [
                'label' => 'Mon identité secrète',
                'mapped' => false,
                'data' => $options['nom_depart'],
                'attr' => ['readonly' => true],
            ])
            ->add('establishment', EntityType::class, [
                'label' => 'Mon établissement',
                'mapped' => false,
                'class' => Establishment::class,
                'choice_label' => 'name',
                'placeholder' => '-- Sélectionnez votre établissement --',
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner votre établissement.'),
                ],
            ])
            ->add('groupLevel', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'label' => 'Ma classe',
                'mapped' => false,
                'placeholder' => '-- Sélectionnez votre classe --',
                'choices' => array_combine(Group::LEVELS, Group::LEVELS),
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner votre classe.'),
                ],
            ])
            ->add('groupCode', TextType::class, [
                'label' => 'Code de groupe',
                'mapped' => false, // sert à retrouver le groupe, le lien est porté par user.group_id
                'attr' => [
                    'placeholder' => 'Ex : GRP0001',
                    'maxlength' => 10,
                    'autocomplete' => 'off',
                ],
                'help' => 'Fourni par votre accompagnateur avant l\'événement.',
                'constraints' => [
                    new NotBlank(message: 'Le code de groupe est obligatoire.'),
                    new Length(
                        max: 10,
                        maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.'
                    ),
                    new Regex(
                        pattern: '/^[A-Z]{3}\d{4}$/i',
                        message: 'Format attendu : 3 lettres suivies de 4 chiffres (ex : GRP0001).'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'nom_depart' => '',
        ]);

        $resolver->setAllowedTypes('nom_depart', 'string');
    }
}
