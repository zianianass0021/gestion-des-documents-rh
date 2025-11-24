<?php

namespace App\Form;

use App\Entity\Employe;
use App\Entity\Module;
use App\Repository\ModuleRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class ResponsableRhType extends AbstractType
{
    public function __construct(
        private ModuleRepository $moduleRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Get all active modules for the choice field
        $modules = $this->moduleRepository->findAllActiveOrdered();
        $moduleChoices = [];
        foreach ($modules as $module) {
            $moduleChoices[$module->getLabel()] = $module->getId();
        }

        // Get current modules for pre-population (when editing)
        $currentModules = $options['current_modules'] ?? [];

        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez le prénom'
                ]
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez le nom'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'exemple@uiass.rh'
                ]
            ])
            ->add('username', TextType::class, [
                'label' => 'Nom d\'utilisateur',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'nom.utilisateur'
                ]
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+212 6XX XXX XXX'
                ]
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'required' => $options['is_new'] ?? true,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '••••••••'
                ]
            ])
            ->add('modules', ChoiceType::class, [
                'label' => 'Modules autorisés',
                'choices' => $moduleChoices,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'mapped' => false,
                'data' => $currentModules,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ]
            ])
            ->add('organisation_permissions', TextType::class, [
                'label' => 'Permissions Organisationnelles',
                'required' => false,
                'mapped' => false,
                'data' => $options['current_org_permissions'] ?? '[]',
                'attr' => [
                    'class' => 'form-control',
                    'style' => 'display: none;',
                    'id' => 'organisation_permissions_input'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Employe::class,
            'is_new' => true,
            'current_modules' => [],
            'current_org_permissions' => '[]',
        ]);
    }
}
