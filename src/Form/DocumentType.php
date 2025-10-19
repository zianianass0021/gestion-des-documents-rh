<?php

namespace App\Form;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('abbreviation', TextType::class, [
                'label' => 'Abréviation',
                'constraints' => [
                    new Length([
                        'max' => 20,
                        'maxMessage' => 'L\'abréviation ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: CIN, CV, DIP...',
                    'maxlength' => 20
                ]
            ])
            ->add('libelleComplet', TextType::class, [
                'label' => 'Libellé Complet',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Carte d\'identité nationale'
                ]
            ])
            ->add('typeDocument', TextType::class, [
                'label' => 'Type de Document',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Identité, Diplôme, RH...'
                ]
            ])
            ->add('usage', TextareaType::class, [
                'label' => 'Usage',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description de l\'usage du document'
                ]
            ])
            ->add('statutAjout', ChoiceType::class, [
                'label' => 'Statut d\'ajout',
                'choices' => [
                    'Non ajouté' => 'non_ajoute',
                    'Ajouté' => 'ajoute'
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('statutTelechargement', ChoiceType::class, [
                'label' => 'Statut de téléchargement',
                'choices' => [
                    'Non téléchargé' => 'non_telecharge',
                    'Téléchargé' => 'telecharge'
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('file', FileType::class, [
                'label' => 'Fichier',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Callback([
                        'callback' => [$this, 'validateFile'],
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png,.gif'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
        ]);
    }

    public function validateFile($value, ExecutionContextInterface $context): void
    {
        if (!$value) {
            return; // Le fichier est optionnel
        }

        // Vérifier la taille du fichier (10MB max)
        $maxSize = 10 * 1024 * 1024; // 10MB en bytes
        if ($value->getSize() > $maxSize) {
            $context->buildViolation('Le fichier ne peut pas dépasser 10MB.')
                ->addViolation();
            return;
        }

        // Vérifier l'extension du fichier
        $originalName = $value->getClientOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($extension, $allowedExtensions)) {
            $context->buildViolation('Veuillez télécharger un fichier valide (PDF, DOC, DOCX, JPG, PNG, GIF).')
                ->addViolation();
        }
    }
}