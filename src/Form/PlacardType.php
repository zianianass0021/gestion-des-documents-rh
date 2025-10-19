<?php

namespace App\Form;

use App\Entity\Placard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlacardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du placard',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Placard A1, Armoire B2'
                ]
            ])
            ->add('location', TextType::class, [
                'label' => 'Emplacement',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Bureau RH, Archives, Serveur'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Placard::class,
        ]);
    }
}
