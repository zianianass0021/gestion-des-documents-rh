<?php

namespace App\Command;

use App\Entity\Demande;
use App\Entity\Employe;
use App\Repository\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:add-100-demandes',
    description: 'Ajoute 100 nouvelles demandes pour des employés aléatoires'
)]
class Add100DemandesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmployeRepository $employeRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Ajout de 100 demandes pour des employés');

        // Récupérer tous les employés (ROLE_EMPLOYEE)
        $employees = $this->employeRepository->findByRole('ROLE_EMPLOYEE');
        
        if (empty($employees)) {
            $io->error('Aucun employé trouvé !');
            return Command::FAILURE;
        }

        $io->text(sprintf('Nombre d\'employés disponibles: %d', count($employees)));

        // Types de demandes
        $typesDemandes = [
            [
                'titre' => 'Demande de congé',
                'contenu' => 'Je souhaiterais prendre [JOURS] jours de congé du [DATE_DEBUT] au [DATE_FIN]. Je justifie cette demande par [RAISON].',
                'statuts' => ['en_attente', 'acceptee'],
                'reponses' => [
                    'acceptee' => 'Votre demande de congé a été acceptée. Profitez bien de votre repos !'
                ]
            ],
            [
                'titre' => 'Demande de télétravail',
                'contenu' => 'Je souhaiterais bénéficier du télétravail [JOURS_PAR_SEMAINE] jours par semaine pour des raisons personnelles.',
                'statuts' => ['en_attente', 'acceptee', 'refusee'],
                'reponses' => [
                    'acceptee' => 'Votre demande de télétravail a été acceptée. Merci de respecter les horaires de travail.',
                    'refusee' => 'Votre demande ne peut pas être acceptée actuellement pour des raisons organisationnelles.'
                ]
            ],
            [
                'titre' => 'Demande de formation',
                'contenu' => 'Je souhaite suivre une formation en "[FORMATION]" pour améliorer mes compétences dans mon poste actuel.',
                'statuts' => ['en_attente', 'acceptee', 'refusee'],
                'reponses' => [
                    'acceptee' => 'Votre demande de formation a été acceptée. Les frais seront couverts par l\'entreprise.',
                    'refusee' => 'Votre demande de formation ne peut pas être acceptée pour l\'instant.'
                ]
            ],
            [
                'titre' => 'Demande de changement de poste',
                'contenu' => 'Je souhaiterais postuler pour le poste de "[POSTE]" qui me permettrait de développer mes compétences.',
                'statuts' => ['en_attente', 'refusee'],
                'reponses' => [
                    'refusee' => 'Votre demande a été examinée mais ne peut pas être acceptée pour le moment.'
                ]
            ],
            [
                'titre' => 'Demande de prime',
                'contenu' => 'Pour mes performances exceptionnelles ce mois-ci, je souhaiterais recevoir une prime de [MONTANT].',
                'statuts' => ['en_attente', 'acceptee', 'refusee'],
                'reponses' => [
                    'acceptee' => 'Votre demande de prime a été acceptée et sera versée sur votre prochain salaire.',
                    'refusee' => 'Votre demande ne peut pas être acceptée cette fois-ci.'
                ]
            ],
            [
                'titre' => 'Demande de document',
                'contenu' => 'J\'aurais besoin d\'une attestation de travail pour mes démarches administratives.',
                'statuts' => ['en_attente', 'acceptee'],
                'reponses' => [
                    'acceptee' => 'Votre demande de document a été acceptée. Le document sera prêt sous 48h.'
                ]
            ],
            [
                'titre' => 'Demande de modification d\'horaire',
                'contenu' => 'Je souhaiterais modifier mes horaires de travail pour des raisons familiales.',
                'statuts' => ['en_attente', 'acceptee', 'refusee'],
                'reponses' => [
                    'acceptee' => 'Votre demande de modification d\'horaire a été acceptée.',
                    'refusee' => 'Votre demande ne peut pas être acceptée pour des raisons d\'organisation.'
                ]
            ],
            [
                'titre' => 'Demande de remboursement frais',
                'contenu' => 'Je souhaite être remboursé des frais de déplacement engagés lors de ma mission du [DATE_DEBUT], d\'un montant de [MONTANT].',
                'statuts' => ['en_attente', 'acceptee'],
                'reponses' => [
                    'acceptee' => 'Votre demande de remboursement a été acceptée et sera traitée sous 10 jours ouvrés.'
                ]
            ]
        ];

        // Récupérer un responsable RH aléatoire
        $responsablesRh = $this->employeRepository->findByRole('ROLE_RESPONSABLE_RH');
        $responsableRh = !empty($responsablesRh) ? $responsablesRh[array_rand($responsablesRh)] : null;

        $created = 0;
        $employeeIds = array_map(fn($emp) => $emp->getId(), $employees);
        $responsableRhId = $responsableRh ? $responsableRh->getId() : null;

        for ($i = 0; $i < 100; $i++) {
            // Sélectionner un ID d'employé aléatoire
            $employeeId = $employeeIds[array_rand($employeeIds)];
            $employee = $this->em->find(Employe::class, $employeeId);
            
            if (!$employee) {
                continue;
            }
            
            // Sélectionner un type de demande aléatoire
            $typeDemande = $typesDemandes[array_rand($typesDemandes)];
            
            // Sélectionner un statut aléatoire
            $statut = $typeDemande['statuts'][array_rand($typeDemande['statuts'])];
            
            // Créer la demande
            $demande = new Demande();
            $demande->setTitre($typeDemande['titre']);
            
            // Générer le contenu personnalisé
            $contenu = str_replace(
                [
                    '[JOURS]',
                    '[DATE_DEBUT]',
                    '[DATE_FIN]',
                    '[FORMATION]',
                    '[JOURS_PAR_SEMAINE]',
                    '[DUREE]',
                    '[MONTANT]',
                    '[POSTE]',
                    '[RAISON]'
                ],
                [
                    rand(1, 15),
                    (new \DateTime('+' . rand(7, 60) . ' days'))->format('d/m/Y'),
                    (new \DateTime('+' . rand(15, 90) . ' days'))->format('d/m/Y'),
                    'Gestion de projet agile, Leadership, Communication interpersonnelle',
                    rand(1, 3),
                    rand(1, 3) . ' an(s)',
                    rand(500, 2000) . ' DH',
                    'Chef de projet, Développeur senior, Manager',
                    'raisons familiales, problèmes de santé, formation personnelle'
                ],
                $typeDemande['contenu']
            );
            
            $demande->setContenu($contenu);
            $demande->setStatut($statut);
            $demande->setEmploye($employee);
            
            if ($responsableRhId) {
                $responsableRhEntity = $this->em->find(Employe::class, $responsableRhId);
                if ($responsableRhEntity) {
                    $demande->setResponsableRh($responsableRhEntity);
                }
            }
            
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
            
            // Date de création dans les 60 derniers jours
            $daysAgo = rand(1, 60);
            $dateCreation = new \DateTimeImmutable('-' . $daysAgo . ' days');
            $demande->setDateCreation($dateCreation);
            
            $this->em->persist($demande);
            $created++;
            
            // Flush tous les 20 enregistrements pour améliorer les performances
            if ($created % 20 == 0) {
                $this->em->flush();
                $this->em->clear();
                $io->text(sprintf('%d demandes créées...', $created));
            }
        }
        
        // Flush final
        $this->em->flush();
        
        $io->success(sprintf('%d demandes créées avec succès !', $created));
        
        return Command::SUCCESS;
    }
}

