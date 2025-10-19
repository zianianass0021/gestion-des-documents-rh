<?php

namespace App\Form;

use App\Entity\Organisation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrganisationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: ORG001'
                ]
            ])
            ->add('divisionActivitesStrategiques', TextType::class, [
                'label' => 'Division d\'Activités Stratégiques',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Direction Générale'
                ]
            ])
            ->add('das', TextType::class, [
                'label' => 'DAS',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: DAS01'
                ]
            ])
            ->add('groupement', TextType::class, [
                'label' => 'Groupement',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: GRP01'
                ]
            ])
            ->add('dossier', TextType::class, [
                'label' => 'Dossier',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: DOS01'
                ]
            ])
            ->add('dossierDesignation', TextType::class, [
                'label' => 'Désignation du Dossier',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Service des Ressources Humaines'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Organisation::class,
        ]);
    }
}
