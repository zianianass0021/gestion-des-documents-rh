<?php

namespace App\Form;

use App\Entity\Reclamation;
use App\Entity\Employe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('employe', EntityType::class, [
                'class' => Employe::class,
                'choice_label' => function(Employe $employe) {
                    return $employe->getPrenom() . ' ' . $employe->getNom() . ' (' . $employe->getEmail() . ')';
                },
                'choices' => $options['employees'] ?? [],
                'placeholder' => 'Sélectionner un employé',
                'label' => 'Employé concerné',
                'attr' => [
                    'class' => 'form-select'
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
            'employees' => [],
        ]);
    }
}
