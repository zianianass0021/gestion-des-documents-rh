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
    // Les 88 types de dossier valides (3e niveau de contrôle)
    private const VALID_DOSSIERS = [
        // DAS-SIEGE (DSIG)
        'SFCZ' => 'SFCZ',
        'CCGA' => 'CCGA',
        // DAS-GESTION (DGST)
        'GACH' => 'GACH',
        'GCMP' => 'GCMP',
        'GFIN' => 'GFIN',
        'GRSH' => 'GRSH',
        'GJUR' => 'GJUR',
        'GBNQ' => 'GBNQ',
        'GPRG' => 'GPRG',
        // DAS-ACTIVITE SOCIALE (DASO)
        'SUMM' => 'SUMM',
        'SASE' => 'SASE',
        'SPSI' => 'SPSI',
        'SASI' => 'SASI',
        'SPSE' => 'SPSE',
        // DAS-ENSEIGNEMENT & FORMATION (DENS)
        'EUIA' => 'EUIA',
        'ECSM' => 'ECSM',
        'IFCP' => 'IFCP',
        'ECFC' => 'ECFC',
        'ECRI' => 'ECRI',
        'ELEZ' => 'ELEZ',
        'EEAS' => 'EEAS',
        // DAS-SOINS (DSOI)
        'SHCZ' => 'SHCZ',
        'SLMG' => 'SLMG',
        'SDNT' => 'SDNT',
        'SHMK' => 'SHMK',
        'SHMB' => 'SHMB',
        'SHMY' => 'SHMY',
        'SLPD' => 'SLPD',
        'SRAD' => 'SRAD',
        'SLAB' => 'SLAB',
        'SAHR' => 'SAHR',
        'SCOV' => 'SCOV',
        'SOPH' => 'SOPH',
        'SKIN' => 'SKIN',
        'SCVP' => 'SCVP',
        'SGHJ' => 'SGHJ',
        'SGYN' => 'SGYN',
        'SURG' => 'SURG',
        'SHMS' => 'SHMS',
        'SHCT' => 'SHCT',
        'CSDA' => 'CSDA',
        // DAS-HOTELLERIE-RESTAURATION (DRST)
        'RRUR' => 'RRUR',
        'RHUR' => 'RHUR',
        'RHAR' => 'RHAR',
        'RRER' => 'RRER',
        'RRHK' => 'RRHK',
        'RRHY' => 'RRHY',
        'RDAR' => 'RDAR',
        'RRHR' => 'RRHR',
        'RHSR' => 'RHSR',
        'RCER' => 'RCER',
        'RBPR' => 'RBPR',
        'RHCC' => 'RHCC',
        // DAS-INFORMATIQUE ET NUMERIQUE (DNUM)
        'NSIT' => 'NSIT',
        'NARC' => 'NARC',
        'NITS' => 'NITS',
        'NNUM' => 'NNUM',
        // DAS-INGENIERIE & TRAVAUX (DING)
        'IMIN' => 'IMIN',
        'IEIS' => 'IEIS',
        'ICCI' => 'ICCI',
        'IESV' => 'IESV',
        'ISAE' => 'ISAE',
        'ISAM' => 'ISAM',
        'IPTT' => 'IPTT',
        'ISAA' => 'ISAA',
        // DAS-LOGISTIQUE & APPROVISIONNEMENT (DAPR)
        'APCC' => 'APCC',
        'APVR' => 'APVR',
        'APUR' => 'APUR',
        'APLM' => 'APLM',
        'APCH' => 'APCH',
        'LPDP' => 'LPDP',
        // DAS-PHARMACEUTIQUE (DPHR)
        'PVPH' => 'PVPH',
        'PPPH' => 'PPPH',
        'PAMD' => 'PAMD',
        // DAS-PRESTATIONS EXTERNALISEES & ACTIVITES DE PRODUCTION (DPRD)
        'PIMP' => 'PIMP',
        'PPTM' => 'PPTM',
        // DAS-RECHERCHE & EXPERTISE (DEXP)
        'ECBE' => 'ECBE',
        'ECRG' => 'ECRG',
        'EEDF' => 'EEDF',
        // DAS-SERVICES DE PROXIMITE (DSPR)
        'PTEX' => 'PTEX',
        'PBIR' => 'PBIR',
        'PLAV' => 'PLAV',
        'PCAP' => 'PCAP',
        'PEPC' => 'PEPC',
        'PCOF' => 'PCOF',
        'PEVN' => 'PEVN',
        // EPHR (AFRICMED)
        'PGRO' => 'PGRO',
        // EPRD (SA2S-METIERS)
        'PSMS' => 'PSMS',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $options['is_new'] ?? true;
        
        // En mode création, utiliser la recherche AJAX (champ caché)
        // En mode édition, afficher l'employé en lecture seule (non modifiable)
        if ($isNew) {
            $builder->add('employe', EntityType::class, [
                'label' => false,
                'class' => Employe::class,
                'choice_label' => 'fullName',
                'query_builder' => function (EmployeRepository $er) {
                    // Query vide par défaut, sera remplie via AJAX
                    return $er->createQueryBuilder('e')->where('1 = 0');
                },
                'placeholder' => '',
                'required' => false, // La validation sera faite dans le contrôleur
                'attr' => [
                    'class' => 'd-none',
                    'id' => 'dossier-employee-id'
                ]
            ]);
        } else {
            // En mode édition, l'employé est en lecture seule (non mappé)
            $builder->add('employe', EntityType::class, [
                'label' => 'Employé',
                'class' => Employe::class,
                'choice_label' => 'fullName',
                'query_builder' => function (EmployeRepository $er) use ($builder) {
                    // Seulement l'employé actuel du dossier
                    $dossier = $builder->getData();
                    if ($dossier && $dossier->getEmploye()) {
                        return $er->createQueryBuilder('e')
                            ->where('e.id = :id')
                            ->setParameter('id', $dossier->getEmploye()->getId());
                    }
                    return $er->createQueryBuilder('e')->where('1 = 0');
                },
                'disabled' => true, // Désactivé car non modifiable
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true
                ]
            ]);
        }
        
        $builder
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
            ])
            ->add('dossierCode', ChoiceType::class, [
                'label' => 'Type de dossier (3e niveau de contrôle)',
                'choices' => self::VALID_DOSSIERS,
                'required' => true,
                'placeholder' => 'Sélectionner un type de dossier',
                'attr' => [
                    'class' => 'form-control',
                    'data-toggle' => 'select2'
                ],
                'help' => 'Ce type correspond au troisième niveau de contrôle (après groupement et DAS)'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dossier::class,
            'is_new' => true, // Par défaut, c'est une création
        ]);
    }
}
