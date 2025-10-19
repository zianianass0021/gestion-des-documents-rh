<?php

namespace App\DataFixtures;

use App\Entity\NatureContratTypeDocument;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class DocumentRequirementFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Documents requis pour CDI (Contrat à Durée Indéterminée)
        $this->createDocumentRequirement($manager, 'CV', 'CDI', true);
        $this->createDocumentRequirement($manager, 'LET_MOT', 'CDI', true);
        $this->createDocumentRequirement($manager, 'COP_DIP', 'CDI', true);
        $this->createDocumentRequirement($manager, 'COP_ID', 'CDI', true);
        $this->createDocumentRequirement($manager, 'COP_RIB', 'CDI', true);
        $this->createDocumentRequirement($manager, 'PHOTO', 'CDI', false);
        $this->createDocumentRequirement($manager, 'CERT_MED', 'CDI', true);

        // Documents requis pour CDD (Contrat à Durée Déterminée)
        $this->createDocumentRequirement($manager, 'CV', 'CDD', true);
        $this->createDocumentRequirement($manager, 'LET_MOT', 'CDD', true);
        $this->createDocumentRequirement($manager, 'COP_DIP', 'CDD', true);
        $this->createDocumentRequirement($manager, 'COP_ID', 'CDD', true);
        $this->createDocumentRequirement($manager, 'COP_RIB', 'CDD', true);
        $this->createDocumentRequirement($manager, 'PHOTO', 'CDD', false);

        // Documents requis pour STAGE (Période de stage)
        $this->createDocumentRequirement($manager, 'CV', 'STAGE', true);
        $this->createDocumentRequirement($manager, 'LET_MOT', 'STAGE', true);
        $this->createDocumentRequirement($manager, 'COP_DIP', 'STAGE', true);
        $this->createDocumentRequirement($manager, 'CONV_STAGE', 'STAGE', true);
        $this->createDocumentRequirement($manager, 'PHOTO', 'STAGE', false);

        $manager->flush();
    }

    private function createDocumentRequirement(ObjectManager $manager, string $abbreviation, string $contractType, bool $required): void
    {
        $requirement = new NatureContratTypeDocument();
        $requirement->setDocumentAbbreviation($abbreviation);
        $requirement->setContractType($contractType);
        $requirement->setRequired($required);
        
        $manager->persist($requirement);
    }
}
