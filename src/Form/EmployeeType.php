<?php

namespace App\Form;

use App\Entity\Employe;
use App\Entity\NatureContrat;
use App\Entity\Organisation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;

class EmployeeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
            ->add('isManager', CheckboxType::class, [
                'label' => 'Cet employé est un manager',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'id' => 'is_manager_checkbox'
                ],
                'help' => 'Les managers ont accès aux fonctionnalités d\'employé et de manager'
            ])
            ->add('dossiersGeres', TextType::class, [
                'label' => 'Dossiers gérés',
                'required' => false,
                'mapped' => true,
                'attr' => [
                    'style' => 'display: none;',
                    'id' => 'dossiers_geres_input'
                ]
            ]);

        // Pré-remplir isManager et dossierGere si l'employé est déjà manager (en mode édition)
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $employee = $event->getData();

            if ($employee && $employee->getId()) {
                // En mode édition, vérifier si l'employé a déjà le rôle ROLE_MANAGER
                $isManager = in_array('ROLE_MANAGER', $employee->getRoles());
                
                // Forcer la valeur de la checkbox
                if ($form->has('isManager')) {
                    $form->get('isManager')->setData($isManager);
                }
                
                // Pré-remplir les dossiers gérés si l'employé est manager
                if ($isManager && $form->has('dossiersGeres')) {
                    $dossiersGeres = $employee->getDossiersGeres();
                    if ($dossiersGeres && !empty($dossiersGeres)) {
                        $form->get('dossiersGeres')->setData(json_encode($dossiersGeres));
                    } elseif ($employee->getDossierGere()) {
                        // Migration depuis l'ancien format
                        $form->get('dossiersGeres')->setData(json_encode([$employee->getDossierGere()]));
                    }
                }
            }
        });
        
        $builder
            // Section Contrat
            ->add('natureContrat', EntityType::class, [
                'class' => NatureContrat::class,
                'choice_label' => 'designation',
                'label' => 'Type de contrat',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('organisation', EntityType::class, [
                'class' => Organisation::class,
                'choice_label' => 'dossierDesignation',
                'label' => 'Organisation',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'Sélectionner une organisation...',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('dateDebutContrat', DateType::class, [
                'label' => 'Date de début du contrat',
                'widget' => 'single_text',
                'mapped' => false,
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('dateFinContrat', DateType::class, [
                'label' => 'Date de fin du contrat',
                'widget' => 'single_text',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            
            // Section Deuxième Contrat (optionnel)
            ->add('natureContrat2', EntityType::class, [
                'class' => NatureContrat::class,
                'choice_label' => 'designation',
                'label' => 'Type de contrat (Secondaire)',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'Sélectionner un deuxième contrat...',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('organisation2', EntityType::class, [
                'class' => Organisation::class,
                'choice_label' => 'dossierDesignation',
                'label' => 'Organisation (Secondaire)',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'Sélectionner une deuxième organisation...',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('dateDebutContrat2', DateType::class, [
                'label' => 'Date de début du contrat (Secondaire)',
                'widget' => 'single_text',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('dateFinContrat2', DateType::class, [
                'label' => 'Date de fin du contrat (Secondaire)',
                'widget' => 'single_text',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Employe::class,
            'is_new' => true,
        ]);
    }

    /**
     * Retourne les choix de dossiers (niveau 3 de contrôle)
     * Même liste que dans OrganisationType
     */
    private function getDossierChoices(): array
    {
        return [
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
    }
}
