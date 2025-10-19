<?php

namespace App\DataFixtures;

use App\Entity\Demande;
use App\Entity\Employe;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class DemandesFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Récupérer tous les employés (pas les admins)
        $employees = $manager->getRepository(Employe::class)->findBy(['isActive' => true]);
        $employees = array_filter($employees, function($emp) {
            return in_array('ROLE_EMPLOYEE', $emp->getRoles());
        });

        // Récupérer le responsable RH
        $responsableRh = $manager->getRepository(Employe::class)->findOneBy(['username' => 'rh']);

        if (empty($employees) || !$responsableRh) {
            throw new \Exception('Les employés et le responsable RH doivent exister avant de créer les demandes');
        }

        // Types de demandes réalistes
        $typesDemandes = [
            [
                'titre' => 'Demande de congés annuels',
                'contenu' => 'Je souhaiterais poser mes congés annuels du [DATE_DEBUT] au [DATE_FIN] pour des raisons personnelles.',
                'statuts' => ['en_attente', 'acceptee', 'refusee'],
                'reponses' => [
                    'acceptee' => 'Votre demande de congés a été acceptée. Bonnes vacances !',
                    'refusee' => 'Malheureusement, votre demande de congés ne peut pas être acceptée pour cette période en raison des contraintes opérationnelles.'
                ]
            ],
            [
                'titre' => 'Demande de formation professionnelle',
                'contenu' => 'Je souhaiterais suivre une formation en [FORMATION] pour améliorer mes compétences professionnelles. Cette formation se déroulerait du [DATE_DEBUT] au [DATE_FIN].',
                'statuts' => ['en_attente', 'acceptee', 'refusee'],
                'reponses' => [
                    'acceptee' => 'Excellente initiative ! Votre demande de formation a été acceptée. Les frais seront pris en charge par l\'entreprise.',
                    'refusee' => 'Pour le moment, cette formation ne peut pas être financée. Nous reviendrons vers vous lors du prochain budget formation.'
                ]
            ],
            [
                'titre' => 'Demande de télétravail',
                'contenu' => 'Je souhaiterais bénéficier du télétravail [JOURS_PAR_SEMAINE] jours par semaine pour améliorer mon équilibre vie professionnelle/vie privée.',
                'statuts' => ['en_attente', 'acceptee', 'refusee'],
                'reponses' => [
                    'acceptee' => 'Votre demande de télétravail a été acceptée. Veuillez respecter les modalités définies dans notre charte télétravail.',
                    'refusee' => 'Votre demande de télétravail ne peut pas être accordée pour le moment en raison des besoins opérationnels de votre service.'
                ]
            ],
            [
                'titre' => 'Demande d\'augmentation salariale',
                'contenu' => 'Après [DUREE] dans l\'entreprise et suite à mes performances, je souhaiterais discuter d\'une possible augmentation salariale.',
                'statuts' => ['en_attente', 'acceptee', 'refusee'],
                'reponses' => [
                    'acceptee' => 'Votre demande d\'augmentation a été étudiée positivement. Une augmentation de [MONTANT] sera appliquée dès le prochain mois.',
                    'refusee' => 'Malheureusement, aucune augmentation ne peut être accordée cette année en raison des contraintes budgétaires.'
                ]
            ],
            [
                'titre' => 'Demande de changement de poste',
                'contenu' => 'Je souhaiterais être considéré pour le poste de [POSTE] qui correspond mieux à mes aspirations professionnelles.',
                'statuts' => ['en_attente', 'acceptee', 'refusee'],
                'reponses' => [
                    'acceptee' => 'Votre profil correspond parfaitement à ce poste. Nous allons organiser un entretien pour finaliser cette mutation.',
                    'refusee' => 'Pour le moment, ce poste n\'est pas disponible. Nous garderons votre candidature en mémoire pour les futures opportunités.'
                ]
            ],
            [
                'titre' => 'Demande d\'équipement informatique',
                'contenu' => 'Mon ordinateur actuel ne répond plus aux exigences de mon travail. Je souhaiterais recevoir un nouvel équipement informatique.',
                'statuts' => ['en_attente', 'acceptee', 'refusee'],
                'reponses' => [
                    'acceptee' => 'Votre demande d\'équipement a été acceptée. Le service informatique vous contactera pour la livraison.',
                    'refusee' => 'Pour le moment, le budget équipement est épuisé. Nous réévaluerons votre demande lors du prochain budget.'
                ]
            ],
            [
                'titre' => 'Demande de congé maladie prolongé',
                'contenu' => 'Suite à un problème de santé, je dois prolonger mon arrêt maladie. Je joins le certificat médical.',
                'statuts' => ['en_attente', 'acceptee'],
                'reponses' => [
                    'acceptee' => 'Votre prolongation d\'arrêt maladie a été acceptée. Prenez soin de vous et rétablissez-vous bien.'
                ]
            ],
            [
                'titre' => 'Demande de prime de performance',
                'contenu' => 'Suite aux excellents résultats de cette année, je souhaiterais être considéré pour la prime de performance.',
                'statuts' => ['en_attente', 'acceptee', 'refusee'],
                'reponses' => [
                    'acceptee' => 'Félicitations ! Votre prime de performance a été accordée en reconnaissance de vos excellents résultats.',
                    'refusee' => 'Malheureusement, les critères pour cette prime ne sont pas tous remplis cette année.'
                ]
            ]
        ];

        $demandesCreees = 0;

        // Créer des demandes pour chaque employé
        foreach ($employees as $employee) {
            // Chaque employé aura entre 2 et 5 demandes
            $nombreDemandes = rand(2, 5);
            
            for ($i = 0; $i < $nombreDemandes; $i++) {
                $typeDemande = $typesDemandes[array_rand($typesDemandes)];
                $statut = $typeDemande['statuts'][array_rand($typeDemande['statuts'])];
                
                $demande = new Demande();
                $demande->setTitre($typeDemande['titre']);
                
                // Personnaliser le contenu
                $contenu = str_replace(
                    ['[DATE_DEBUT]', '[DATE_FIN]', '[FORMATION]', '[JOURS_PAR_SEMAINE]', '[DUREE]', '[MONTANT]', '[POSTE]'],
                    [
                        (new \DateTime('+1 month'))->format('d/m/Y'),
                        (new \DateTime('+1 month +1 week'))->format('d/m/Y'),
                        'Gestion de projet agile',
                        rand(1, 3),
                        rand(1, 5) . ' an(s)',
                        rand(500, 2000) . ' DH',
                        'Chef de projet'
                    ],
                    $typeDemande['contenu']
                );
                
                $demande->setContenu($contenu);
                $demande->setStatut($statut);
                $demande->setEmploye($employee);
                $demande->setResponsableRh($responsableRh);
                
                // Ajouter une réponse si la demande est traitée
                if (isset($typeDemande['reponses'][$statut])) {
                    $reponse = str_replace(
                        ['[MONTANT]'],
                        [rand(500, 1500) . ' DH'],
                        $typeDemande['reponses'][$statut]
                    );
                    $demande->setReponse($reponse);
                    $demande->setDateReponse(new \DateTimeImmutable());
                }
                
                // Dates de création variées (derniers 6 mois)
                $dateCreation = new \DateTimeImmutable('-' . rand(1, 180) . ' days');
                $demande->setDateCreation($dateCreation);
                
                $manager->persist($demande);
                $demandesCreees++;
            }
        }

        $manager->flush();
        
        echo "✅ Création de $demandesCreees demandes pour " . count($employees) . " employés\n";
    }
}
