<?php

namespace App\DataFixtures;

use App\Entity\Employe;
use App\Entity\NatureContrat;
use App\Entity\EmployeeContrat;
use App\Entity\Dossier;
use App\Entity\Placard;
use App\Entity\Document;
use App\Entity\Demande;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Créer les types de contrats
        $cdi = new NatureContrat();
        $cdi->setCode('CDI');
        $cdi->setDesignation('Contrat à Durée Indéterminée');
        $manager->persist($cdi);

        $cdd = new NatureContrat();
        $cdd->setCode('CDD');
        $cdd->setDesignation('Contrat à Durée Déterminée');
        $manager->persist($cdd);

        $stage = new NatureContrat();
        $stage->setCode('STAGE');
        $stage->setDesignation('Période de stage');
        $manager->persist($stage);

        // Les documents sont maintenant gérés par DocumentFixtures

        // Créer les utilisateurs
        $administrateurRh = new Employe();
        $administrateurRh->setNom('Admin');
        $administrateurRh->setPrenom('RH');
        $administrateurRh->setEmail('admin@uiass.rh');
        $administrateurRh->setTelephone('+212 6XX XXX XXX');
        $administrateurRh->setRoles(['ROLE_ADMINISTRATEUR_RH']);
        $administrateurRh->setPassword($this->passwordHasher->hashPassword($administrateurRh, 'password123'));
        $manager->persist($administrateurRh);

        $responsableRh = new Employe();
        $responsableRh->setNom('Responsable');
        $responsableRh->setPrenom('RH');
        $responsableRh->setEmail('rh@uiass.rh');
        $responsableRh->setTelephone('+212 6XX XXX XXX');
        $responsableRh->setRoles(['ROLE_RESPONSABLE_RH']);
        $responsableRh->setPassword($this->passwordHasher->hashPassword($responsableRh, 'password123'));
        $manager->persist($responsableRh);

        $employe = new Employe();
        $employe->setNom('John');
        $employe->setPrenom('Doe');
        $employe->setEmail('employe@uiass.rh');
        $employe->setTelephone('+212 6XX XXX XXX');
        $employe->setRoles(['ROLE_EMPLOYEE']);
        $employe->setPassword($this->passwordHasher->hashPassword($employe, 'password123'));
        $manager->persist($employe);

        // Créer un contrat pour l'employé
        $contrat = new EmployeeContrat();
        $contrat->setEmploye($employe);
        $contrat->setNatureContrat($cdi);
        $contrat->setDateDebut(new \DateTime('2022-01-01'));
        $contrat->setStatut('actif');
        $manager->persist($contrat);

        // Créer un dossier pour l'employé
        $dossier = new Dossier();
        $dossier->setEmploye($employe);
        $dossier->setNom('Dossier administratif');
        $dossier->setDescription('Dossier contenant tous les documents administratifs de l\'employé');
        $dossier->setStatus('completed');
        $manager->persist($dossier);

        // Créer un placard pour le dossier
        $placard = new Placard();
        $placard->setName('Placard A1');
        $placard->setLocation('Bureau RH - Étage 2');
        $manager->persist($placard);
        
        // Associer le dossier au placard
        $dossier->setPlacard($placard);

        // Les documents sont maintenant gérés par DocumentFixtures

        // Créer des demandes de test
        $demande1 = new Demande();
        $demande1->setTitre('Demande de congé');
        $demande1->setContenu('Bonjour, je souhaiterais prendre une semaine de congé du 15 au 22 janvier 2025 pour des raisons personnelles. Pourriez-vous me confirmer si cela est possible ?');
        $demande1->setEmploye($employe);
        $demande1->setStatut('en_attente');
        $manager->persist($demande1);

        $demande2 = new Demande();
        $demande2->setTitre('Demande de formation');
        $demande2->setContenu('Je souhaiterais suivre une formation sur Symfony Framework pour améliorer mes compétences techniques. Cette formation durerait 3 jours et coûterait environ 800€. Qu\'en pensez-vous ?');
        $demande2->setEmploye($employe);
        $demande2->setStatut('acceptee');
        $demande2->setResponsableRh($responsableRh);
        $demande2->setReponse('Excellente idée ! Cette formation est tout à fait justifiée pour votre poste. Je valide votre demande. Vous pouvez procéder à l\'inscription.');
        $demande2->setDateReponse(new \DateTimeImmutable('2024-12-20 14:30:00'));
        $manager->persist($demande2);

        $demande3 = new Demande();
        $demande3->setTitre('Réclamation sur les horaires');
        $demande3->setContenu('Je rencontre des difficultés avec mes horaires de travail. Actuellement, je travaille de 8h à 17h, mais j\'aimerais pouvoir commencer à 9h et finir à 18h pour des raisons de transport. Est-ce possible ?');
        $demande3->setEmploye($employe);
        $demande3->setStatut('refusee');
        $demande3->setResponsableRh($responsableRh);
        $demande3->setReponse('Je comprends votre situation, mais malheureusement, les horaires de 8h-17h sont nécessaires pour la coordination avec l\'équipe. Nous pourrions peut-être envisager un aménagement ponctuel si nécessaire.');
        $demande3->setDateReponse(new \DateTimeImmutable('2024-12-18 10:15:00'));
        $manager->persist($demande3);

        $manager->flush();
    }
}
