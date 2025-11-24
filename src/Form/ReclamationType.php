<?php

namespace App\Form;

use App\Entity\Reclamation;
use App\Entity\Employe;
use App\Repository\EmployeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('employe', EntityType::class, [
                'class' => Employe::class,
                'choice_label' => 'fullName',
                'query_builder' => function (EmployeRepository $er) {
                    // Query vide par défaut, sera remplie via AJAX
                    return $er->createQueryBuilder('e')->where('1 = 0');
                },
                'placeholder' => '',
                'label' => false,
                'required' => false, // La validation sera faite dans le contrôleur
                'attr' => [
                    'class' => 'd-none',
                    'id' => 'employee-id'
                ]
            ])
            ->add('employee_search', TextType::class, [
                'label' => 'Employé concerné',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'placeholder' => 'Rechercher un employé (nom ou prénom) - minimum 2 caractères',
                    'autocomplete' => 'off',
                    'id' => 'employee-search'
                ]
            ])
            ->add('typeReclamation', ChoiceType::class, [
                'choices' => [
                    'Problème d\'Assiduité' => 'assiduite',
                    'Accident de Travail' => 'accident_travail',
                ],
                'label' => 'Type de Réclamation',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Décrivez la réclamation en détail...'
                ]
            ])
            ->add('document', FileType::class, [
                'label' => 'Document/Photo (optionnel)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.jpg,.jpeg,.png,.gif,.pdf,.doc,.docx'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamation::class,
        ]);
    }
}
