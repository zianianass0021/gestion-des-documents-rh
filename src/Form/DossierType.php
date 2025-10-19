<?php

namespace App\Form;

use App\Entity\Dossier;
use App\Entity\Employe;
use App\Entity\Placard;
use App\Repository\EmployeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DossierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('employe', EntityType::class, [
                'label' => 'Employé',
                'class' => Employe::class,
                'choice_label' => 'fullName',
                'query_builder' => function(EmployeRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->leftJoin('e.dossier', 'd')
                        ->where('e.id IN (:ids)')
                        ->andWhere('d.id IS NULL')
                        ->setParameter('ids', $er->getEmployeeIdsByRole('ROLE_EMPLOYEE'))
                        ->orderBy('e.nom', 'ASC');
                },
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom du dossier',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Dossier administratif, Dossier médical'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description du dossier'
                ]
            ])
            ->add('placard', EntityType::class, [
                'label' => 'Placard',
                'class' => Placard::class,
                'choice_label' => function(Placard $placard) {
                    return $placard->getName() . ' (' . $placard->getLocation() . ')';
                },
                'required' => false,
                'placeholder' => 'Sélectionner un placard (optionnel)',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('emplacement', TextType::class, [
                'label' => 'Emplacement dans le placard',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: A1, B2, étagère 3',
                    'maxlength' => 12
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut du dossier',
                'choices' => [
                    'En attente' => 'pending',
                    'En cours' => 'in_progress',
                    'Complété' => 'completed'
                ],
                'attr' => [
                    'class' => 'form-control'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dossier::class,
        ]);
    }
}
