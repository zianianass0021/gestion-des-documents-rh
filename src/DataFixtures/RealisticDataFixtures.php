<?php

namespace App\DataFixtures;

use App\Entity\Employe;
use App\Entity\EmployeeContrat;
use App\Entity\Dossier;
use App\Entity\Document;
use App\Entity\NatureContrat;
use App\Entity\Organisation;
use App\Entity\OrganisationEmployeeContrat;
use App\Entity\Placard;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RealisticDataFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Récupérer les entités existantes
        $natureContrats = $manager->getRepository(NatureContrat::class)->findAll();
        $organisations = $manager->getRepository(Organisation::class)->findAll();
        
        if (empty($natureContrats) || empty($organisations)) {
            throw new \Exception('Les données de base (NatureContrat, Organisation) doivent être chargées en premier');
        }

        // Données d'employés marocains
        $employeesData = [
            ['nom' => 'Alaoui', 'prenom' => 'Fatima', 'email' => 'fatima.alaoui@uiass.ma', 'telephone' => '+212 6 61 23 45 67'],
            ['nom' => 'Benali', 'prenom' => 'Ahmed', 'email' => 'ahmed.benali@uiass.ma', 'telephone' => '+212 6 62 34 56 78'],
            ['nom' => 'Chraibi', 'prenom' => 'Aicha', 'email' => 'aicha.chraibi@uiass.ma', 'telephone' => '+212 6 63 45 67 89'],
            ['nom' => 'Dakir', 'prenom' => 'Youssef', 'email' => 'youssef.dakir@uiass.ma', 'telephone' => '+212 6 64 56 78 90'],
            ['nom' => 'El Fassi', 'prenom' => 'Khadija', 'email' => 'khadija.elfassi@uiass.ma', 'telephone' => '+212 6 65 67 89 01'],
            ['nom' => 'Gharbi', 'prenom' => 'Mohamed', 'email' => 'mohamed.gharbi@uiass.ma', 'telephone' => '+212 6 66 78 90 12'],
            ['nom' => 'Hassani', 'prenom' => 'Zineb', 'email' => 'zineb.hassani@uiass.ma', 'telephone' => '+212 6 67 89 01 23'],
            ['nom' => 'Idrissi', 'prenom' => 'Omar', 'email' => 'omar.idrissi@uiass.ma', 'telephone' => '+212 6 68 90 12 34'],
            ['nom' => 'Jabri', 'prenom' => 'Naima', 'email' => 'naima.jabri@uiass.ma', 'telephone' => '+212 6 69 01 23 45'],
            ['nom' => 'Kabbaj', 'prenom' => 'Rachid', 'email' => 'rachid.kabbaj@uiass.ma', 'telephone' => '+212 6 70 12 34 56']
        ];

        $contratTypes = ['CDI', 'CDD', 'STAGE'];
        $documentTypes = ['CV', 'LET_MOT', 'PHOTO', 'CNI', 'DIPLOME', 'CONTRAT', 'BULLETIN_SALAIRE', 'RIB', 'CERTIFICAT_TRAVAIL', 'ATTESTATION_EMPLOI'];

        $employees = [];
        
        // Créer les employés
        foreach ($employeesData as $index => $employeeData) {
            $employee = new Employe();
            $employee->setEmail($employeeData['email']);
            $employee->setNom($employeeData['nom']);
            $employee->setPrenom($employeeData['prenom']);
            $employee->setTelephone($employeeData['telephone']);
            $employee->setRoles(['ROLE_EMPLOYEE']);
            $employee->setIsActive(true);
            
            // Générer un username unique basé sur le nom et prénom
            $username = strtolower($employeeData['prenom'] . '.' . $employeeData['nom']);
            $username = preg_replace('/[^a-z0-9.]/', '', $username);
            $employee->setUsername($username);
            
            // Hasher le mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($employee, 'password123');
            $employee->setPassword($hashedPassword);
            
            $manager->persist($employee);
            $employees[] = $employee;

            // Créer un contrat pour chaque employé
            $contrat = new EmployeeContrat();
            $contrat->setEmploye($employee);
            $contrat->setDateDebut(new \DateTime());
            $contrat->setDateFin(new \DateTime('+1 year'));
            $contrat->setSalaire(8000 + ($index * 500)); // Salaire variable
            $contrat->setNatureContrat($natureContrats[array_rand($natureContrats)]);
            
            $manager->persist($contrat);

            // Créer une liaison organisation
            $orgContrat = new OrganisationEmployeeContrat();
            $orgContrat->setEmployeeContrat($contrat);
            $orgContrat->setOrganisation($organisations[array_rand($organisations)]);
            $orgContrat->setDateDebut(new \DateTime());
            
            $manager->persist($orgContrat);

            // Créer un dossier pour chaque employé
            $dossier = new Dossier();
            $dossier->setNom('Dossier Personnel - ' . $employeeData['nom'] . ' ' . $employeeData['prenom']);
            $dossier->setDescription('Dossier administratif et professionnel de ' . $employeeData['prenom'] . ' ' . $employeeData['nom']);
            $dossier->setStatus($index < 7 ? 'completed' : ($index < 9 ? 'in_progress' : 'pending'));
            $dossier->setEmploye($employee);
            $dossier->setCreatedAt(new \DateTime());
            
            $manager->persist($dossier);

            // Créer quelques documents pour chaque dossier
            $numDocuments = rand(3, 8); // Entre 3 et 8 documents par dossier
            for ($j = 0; $j < $numDocuments; $j++) {
                $document = new Document();
                $documentType = $documentTypes[array_rand($documentTypes)];
                
                $document->setAbbreviation($documentType);
                $document->setLibelleComplet($this->getDocumentFullName($documentType));
                $document->setTypeDocument($this->getDocumentType($documentType));
                $document->setUsage('Recrutement et gestion RH');
                $document->setDossier($dossier);
                $document->setUploadedBy($employeeData['prenom'] . ' ' . $employeeData['nom']);
                
                // Simuler certains documents comme téléchargés
                if (rand(0, 1)) {
                    $document->setFilePath('uploads/documents/' . strtolower($documentType) . '_' . $employee->getId() . '_' . time() . '.pdf');
                }
                
                $manager->persist($document);
            }
        }

        // Créer quelques placards
        $placardsData = [
            ['nom' => 'Placard RH Principal', 'location' => 'Bâtiment A - 1er étage'],
            ['nom' => 'Archives Médicales', 'location' => 'Bâtiment B - Sous-sol'],
            ['nom' => 'Dossiers Administratifs', 'location' => 'Bâtiment C - 2ème étage']
        ];

        foreach ($placardsData as $placardData) {
            $placard = new Placard();
            $placard->setName($placardData['nom']);
            $placard->setLocation($placardData['location']);
            $placard->setCreatedAt(new \DateTimeImmutable());
            
            $manager->persist($placard);
        }

        $manager->flush();
    }

    private function getDocumentFullName(string $abbreviation): string
    {
        $mapping = [
            'CV' => 'Curriculum Vitae',
            'LET_MOT' => 'Lettre de Motivation',
            'PHOTO' => 'Photo d\'identité',
            'CNI' => 'Carte Nationale d\'Identité',
            'DIPLOME' => 'Diplôme de formation',
            'CONTRAT' => 'Contrat de travail',
            'BULLETIN_SALAIRE' => 'Bulletin de salaire',
            'RIB' => 'Relevé d\'Identité Bancaire',
            'CERTIFICAT_TRAVAIL' => 'Certificat de travail',
            'ATTESTATION_EMPLOI' => 'Attestation d\'emploi'
        ];

        return $mapping[$abbreviation] ?? $abbreviation;
    }

    private function getDocumentType(string $abbreviation): string
    {
        $mapping = [
            'CV' => 'Document personnel',
            'LET_MOT' => 'Document personnel',
            'PHOTO' => 'Image',
            'CNI' => 'Document officiel',
            'DIPLOME' => 'Document académique',
            'CONTRAT' => 'Document contractuel',
            'BULLETIN_SALAIRE' => 'Document financier',
            'RIB' => 'Document bancaire',
            'CERTIFICAT_TRAVAIL' => 'Document professionnel',
            'ATTESTATION_EMPLOI' => 'Document professionnel'
        ];

        return $mapping[$abbreviation] ?? 'Document général';
    }
}
