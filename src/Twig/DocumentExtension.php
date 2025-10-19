<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DocumentExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('document_full_name', [$this, 'getDocumentFullName']),
        ];
    }

    public function getDocumentFullName(string $abbreviation): string
    {
        $mapping = [
            'CIN' => 'Carte Nationale d\'Identité',
            'PASS' => 'Passeport',
            'CAE' => 'Carte d\'Accès Électronique',
            'ADN' => 'Acte de Naissance',
            'FORM' => 'Formulaire de Recrutement',
            'CTR' => 'Contrat de Travail',
            'DMED' => 'Déclaration Médicale',
            'BAC' => 'Bulletin d\'Admission au Concours',
            'DIP' => 'Diplôme',
            'CV' => 'Curriculum Vitae',
            'RIB' => 'Relevé d\'Identité Bancaire',
            'FANT' => 'Fiche d\'Antécédents',
            'PHOTO' => 'Photo d\'Identité',
            'EMPR' => 'Attestation d\'Emploi',
            'AUTBIO' => 'Autobiographie',
            'AMAR' => 'Attestation de Mariage',
            'CINCONJ' => 'Carte d\'Identité du Conjoint',
            'ANENF' => 'Acte de Naissance des Enfants',
            'CINENF' => 'Carte d\'Identité des Enfants',
            'CINPAR' => 'Carte d\'Identité des Parents',
            'LET_MOT' => 'Lettre de Motivation',
            'BULLETIN_SALAIRE' => 'Bulletin de Salaire',
            'CERTIFICAT_TRAVAIL' => 'Certificat de Travail',
            'ATTESTATION_EMPLOI' => 'Attestation d\'Emploi'
        ];

        return $mapping[$abbreviation] ?? $abbreviation;
    }
}
