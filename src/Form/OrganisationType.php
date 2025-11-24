<?php

namespace App\Form;

use App\Entity\Organisation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrganisationType extends AbstractType
{
    // Valeurs de référence selon l'image Excel
    private const VALID_GROUPEMENTS = [
        'FCZ' => 'FCZ',
        'RGA' => 'RGA',
        'SSS' => 'SSS',
        'SST' => 'SST',
    ];

    private const VALID_DAS = [
        'DSIG' => 'DSIG (DAS-SIEGE)',
        'DGST' => 'DGST (DAS-GESTION)',
        'DASO' => 'DASO (DAS-ACTIVITE SOCIALE)',
        'DENS' => 'DENS (DAS-ENSEIGNEMENT & FORMATION)',
        'DSOI' => 'DSOI (DAS-SOINS)',
        'DRST' => 'DRST (DAS-HOTELLERIE-RESTAURATION)',
        'DNUM' => 'DNUM (DAS-INFORMATIQUE ET NUMERIQUE)',
        'DING' => 'DING (DAS-INGENIERIE & TRAVAUX)',
        'DAPR' => 'DAPR (DAS-LOGISTIQUE & APPROVISIONNEMENT)',
        'DPHR' => 'DPHR (DAS-PHARMACEUTIQUE)',
        'DPRD' => 'DPRD (DAS-PRESTATIONS EXTERNALISEES & ACTIVITES DE PRODUCTION)',
        'DEXP' => 'DEXP (DAS-RECHERCHE & EXPERTISE)',
        'DSPR' => 'DSPR (DAS-SERVICES DE PROXIMITE)',
        'EPHR' => 'EPHR (AFRICMED-COMMERCIALISATION PHARMACEUTIQUE)',
        'EPRD' => 'EPRD (SA2S-METIERS ET SERVICES)',
        'EING' => 'EING (SA2S-INGENIERIE & TRAVAUX)',
    ];

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
            ->add('das', ChoiceType::class, [
                'label' => 'DAS',
                'choices' => self::VALID_DAS,
                'placeholder' => 'Sélectionner un DAS',
                'attr' => [
                    'class' => 'form-control form-select'
                ]
            ])
            ->add('groupement', ChoiceType::class, [
                'label' => 'Groupement',
                'choices' => self::VALID_GROUPEMENTS,
                'placeholder' => 'Sélectionner un groupement',
                'attr' => [
                    'class' => 'form-control form-select'
                ]
            ])
            ->add('dossier', ChoiceType::class, [
                'label' => 'Dossier',
                'choices' => self::VALID_DOSSIERS,
                'placeholder' => 'Sélectionner un dossier',
                'attr' => [
                    'class' => 'form-control form-select'
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
