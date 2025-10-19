<?php

namespace App\DataFixtures;

use App\Entity\NatureContratTypeDocument;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ComprehensiveDocumentMatrixFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Document matrix data - mapping from your provided structure
        $documentMatrix = [
            [
                'document' => 'CIN',
                'libelle_complet' => 'Carte d\'identité nationale',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => true,
                ]
            ],
            [
                'document' => 'PASS',
                'libelle_complet' => 'Passeport',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => false,
                    'CDD' => false,
                    'STAGE' => false,
                ]
            ],
            [
                'document' => 'CAE',
                'libelle_complet' => 'Carte Auto-Entrepreneur',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => false,
                    'CDD' => false,
                    'STAGE' => false,
                ]
            ],
            [
                'document' => 'ADN',
                'libelle_complet' => 'Acte de naissance',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => true,
                ]
            ],
            [
                'document' => 'FORM',
                'libelle_complet' => 'Formulaire (recrutement, biométrique, etc.)',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => true,
                ]
            ],
            [
                'document' => 'CONTRAT',
                'libelle_complet' => 'Contrat de travail / convention',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => true,
                ]
            ],
            [
                'document' => 'DMED',
                'libelle_complet' => 'Dossier médical',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => false,
                ]
            ],
            [
                'document' => 'BAC',
                'libelle_complet' => 'Diplôme du baccalauréat',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => true,
                ]
            ],
            [
                'document' => 'DIP',
                'libelle_complet' => 'Diplômes / certificats',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => true,
                ]
            ],
            [
                'document' => 'CV',
                'libelle_complet' => 'Curriculum Vitae',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => true,
                ]
            ],
            [
                'document' => 'RIB',
                'libelle_complet' => 'Relevé d\'identité bancaire',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => true,
                ]
            ],
            [
                'document' => 'FANT',
                'libelle_complet' => 'Fiche anthropométrique',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => false,
                    'CDD' => false,
                    'STAGE' => false,
                ]
            ],
            [
                'document' => 'PHOTO',
                'libelle_complet' => 'Photo d\'identité',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => true,
                ]
            ],
            [
                'document' => 'EMPR',
                'libelle_complet' => 'Empreinte',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => false,
                ]
            ],
            [
                'document' => 'AUTBIO',
                'libelle_complet' => 'Autorisation de collecte de données biométriques',
                'type' => 'Personnel',
                'contracts' => [
                    'CDI' => true,
                    'CDD' => true,
                    'STAGE' => false,
                ]
            ],
            [
                'document' => 'AMAR',
                'libelle_complet' => 'Acte de mariage',
                'type' => 'Ayant Droits',
                'contracts' => [
                    'CDI' => false,
                    'CDD' => false,
                    'STAGE' => false,
                ]
            ],
            [
                'document' => 'CINCONJ',
                'libelle_complet' => 'CIN conjoint',
                'type' => 'Ayant Droits',
                'contracts' => [
                    'CDI' => false,
                    'CDD' => false,
                    'STAGE' => false,
                ]
            ],
            [
                'document' => 'ANENF',
                'libelle_complet' => 'Acte de naissance enfant',
                'type' => 'Ayant Droits',
                'contracts' => [
                    'CDI' => false,
                    'CDD' => false,
                    'STAGE' => false,
                ]
            ],
            [
                'document' => 'CINENF',
                'libelle_complet' => 'CIN enfants',
                'type' => 'Ayant Droits',
                'contracts' => [
                    'CDI' => false,
                    'CDD' => false,
                    'STAGE' => false,
                ]
            ],
            [
                'document' => 'CINPAR',
                'libelle_complet' => 'CIN parents',
                'type' => 'Ayant Droits',
                'contracts' => [
                    'CDI' => false,
                    'CDD' => false,
                    'STAGE' => false,
                ]
            ],
        ];

        // Create NatureContratTypeDocument entities
        foreach ($documentMatrix as $docData) {
            foreach ($docData['contracts'] as $contractType => $required) {
                $nctd = new NatureContratTypeDocument();
                $nctd->setDocumentAbbreviation($docData['document']);
                $nctd->setContractType($contractType);
                $nctd->setRequired($required);
                
                $manager->persist($nctd);
            }
        }

        $manager->flush();
    }
}
