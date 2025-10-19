<?php

namespace App\DataFixtures;

use App\Entity\Document;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class DocumentFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Données exactes du tableau "Tableau des documents administratifs des employés"
        $documents = [
            ['CIN', 'Carte d\'identité nationale', 'Identité', 'Document principal d\'identification'],
            ['PASS', 'Passeport', 'Identité', 'Alternative ou complément au CIN'],
            ['CAE', 'Carte Auto-Entrepreneur', 'Statut professionnel', 'Justifie l\'activité indépendante'],
            ['ADN', 'Acte de naissance', 'État civil', 'Nécessaire pour la constitution du dossier RH'],
            ['FORM', 'Formulaire', 'Administratif', 'Ex. formulaire d\'embauche ou d\'autorisation'],
            ['CTR', 'Contrat de travail', 'RH', 'Document juridique liant l\'employé'],
            ['DMED', 'Dossier médical', 'Médical', 'Confidentialité requise'],
            ['BAC', 'Diplôme du baccalauréat', 'Diplôme', 'Vérification du niveau d\'études'],
            ['DIP', 'Diplômes', 'Diplôme', 'Autres diplômes ou certificats'],
            ['CV', 'Curriculum Vitae', 'Recrutement', 'Parcours professionnel et académique'],
            ['RIB', 'Relevé d\'identité bancaire', 'Financier', 'Nécessaire pour le versement du salaire'],
            ['FANT', 'Fiche anthropométrique', 'Médical / Sécurité', 'Peut contenir données physiques'],
            ['PHOTO', 'Photo d\'identité', 'Identité', 'Identification interne'],
            ['EMPR', 'Empreinte', 'Biométrique', 'Donnée biométrique utilisée pour contrôle d\'accès'],
            ['AUTBIO', 'Autorisation de collecte de données biométriques', 'Consentement', 'Nécessaire pour la conformité RGPD'],
            ['AMAR', 'Acte de mariage', 'État civil', 'Justifie la situation familiale'],
            ['CINCONJ', 'CIN conjoint', 'Identité / Famille', 'Justifie lien familial'],
            ['ANENF', 'Acte de naissance enfant', 'État civil', 'Pour prestations ou dossiers familiaux'],
            ['CINENF', 'CIN enfants', 'Identité / Famille', 'Pour enfants majeurs à charge'],
            ['CINPAR', 'CIN parents', 'Identité / Famille', 'Pour justificatifs familiaux spécifiques'],
        ];

        foreach ($documents as $documentData) {
            $document = new Document();
            $document->setAbbreviation($documentData[0]);
            $document->setLibelleComplet($documentData[1]);
            $document->setTypeDocument($documentData[2]);
            $document->setUsage($documentData[3]);

            $manager->persist($document);
        }

        $manager->flush();
    }
}
