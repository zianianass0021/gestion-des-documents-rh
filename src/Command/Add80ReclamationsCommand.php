<?php

namespace App\Command;

use App\Entity\Reclamation;
use App\Entity\Employe;
use App\Repository\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:add-80-reclamations',
    description: 'Ajoute 80 nouvelles réclamations entre managers et employés'
)]
class Add80ReclamationsCommand extends Command
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
        
        $io->title('Ajout de 80 réclamations');

        // Récupérer tous les managers
        $managers = $this->employeRepository->findByRole('ROLE_MANAGER');
        
        if (empty($managers)) {
            $io->error('Aucun manager trouvé !');
            return Command::FAILURE;
        }

        $io->text(sprintf('Nombre de managers disponibles: %d', count($managers)));

        // Récupérer les responsables RH pour le traitement
        $responsablesRh = $this->employeRepository->findByRole('ROLE_RESPONSABLE_RH');
        
        if (!empty($responsablesRh)) {
            $io->text(sprintf('Nombre de responsables RH disponibles: %d', count($responsablesRh)));
        }

        // Types de réclamations avec commentaires détaillés
        $typesReclamations = [
            'assiduite' => [
                'commentaires' => [
                    'L\'employé a été absent 8 jours ce mois-ci sans justification médicale ou autorisation préalable. Malgré plusieurs rappels, aucun justificatif n\'a été fourni. Cette situation a impacté l\'organisation du service, nécessitant des ajustements d\'équipe de dernière minute qui perturbent le fonctionnement normal du département.',
                    'Absentéisme répété constaté depuis le début de l\'année : 5 absences non justifiées en 3 mois. Les retards quotidiens de 20 à 45 minutes affectent le démarrage des réunions d\'équipe et la productivité générale. Les clients externes ont également mentionné des difficultés à joindre l\'employé en début de journée.',
                    'Retards systématiques observés depuis deux mois. L\'employé arrive régulièrement avec 30 minutes de retard, impactant les rendez-vous clients programmés en début de journée. Des plaintes clients ont été reçues concernant des rendez-vous manqués. Le travail d\'équipe est également affecté car plusieurs projets dépendent de sa présence.',
                    'Absences non notifiées devenue une habitude. L\'employé ne prévient pas de ses absences, laissant l\'équipe dans l\'incertitude. Sur les 6 dernières semaines, 3 absences imprévues ont nécessité le déploiement de personnels de remplacement, engendrant des surcoûts et des désorganisations. Les délais de livraison aux clients ont été impactés.',
                    'Motif d\'absence incohérent soulevé. L\'employé a fourni des justificatifs médicaux provenant de sources différentes, avec des incohérences dans les dates et les motifs. Des vérifications préliminaires indiquent des documents suspects. L\'équipe RH nécessite une intervention rapide pour éclaircir cette situation.',
                    'Tendance à quitter le travail en avance sans autorisation. L\'employé quitte régulièrement 1h avant la fin officielle des horaires, sans prévenir ni obtenir d\'autorisation. Cette conduite crée un précédent négatif au sein de l\'équipe et pose des problèmes opérationnels, notamment pour la réception d\'appels importants en fin de journée.'
                ],
                'statuts' => ['en_attente', 'en_cours', 'traitee'],
                'reponses' => [
                    'en_cours' => 'La réclamation fait actuellement l\'objet d\'une investigation approfondie. Des vérifications des justificatifs médicaux et des horaires ont été lancées. Un entretien avec l\'employé concerné sera organisé dans les prochains jours pour recueillir sa version des faits et l\'informer de l\'importance de la ponctualité et de la notification des absences.',
                    'traitee' => 'Réclamation traitée avec succès. Un entretien disciplinaire a été mené avec l\'employé. Des mesures correctives ont été mises en place : avertissement écrit, rappel des obligations contractuelles, et mise en place d\'un suivi mensuel pendant 6 mois. L\'employé s\'est engagé à respecter les horaires et à notifier toute absence au minimum 24h à l\'avance.'
                ]
            ],
            'accident_travail' => [
                'commentaires' => [
                    'Accident de travail déclaré survenu le 15 mars 2024 à 14h30. L\'employé s\'est blessé au bras droit en manipulant une charge lourde dans l\'entrepôt. Premiers soins prodigués immédiatement par l\'infirmière d\'entreprise. Transport aux urgences pour consultation. Certificat médical reçu indiquant un arrêt de travail de 5 jours. Les mesures de sécurité appropriées ont été vérifiées et renforcées sur le poste de travail.',
                    'Incident de sécurité majeur survenu lors de l\'utilisation de l\'équipement de production. L\'employé a subi une blessure superficielle à la main due à un dysfonctionnement du dispositif de sécurité. L\'arrêt machine immédiat a été effectué et un technicien de maintenance a intervenu pour vérifier et réparer l\'équipement. Une déclaration d\'accident a été établie et transmise à l\'assurance. Un audit complet de l\'équipement est en cours.',
                    'Accident de trajet constaté. L\'employé a été percuté par un véhicule lors de son trajet domicile-travail. Blessures légères constatées (contusions et écorchures), prise en charge aux urgences. Certificat médical attestant d\'un arrêt de travail préventif de 3 jours. Déclaration aux assurances et démarches administratives engagées. L\'employé est informé de ses droits et du processus de remboursement des frais médicaux.',
                    'Exposition à une substance chimique lors du chargement de produits. L\'employé a signalé une irritation respiratoire après manipulation de produits chimiques. Évacuation préventive vers la zone médicale, évaluation des symptômes. Consultation externe organisée pour évaluation approfondie. Vérification du port des équipements de protection individuelle (EPI) et de leur conformité. Remplacement des équipements obsolètes planifié.',
                    'Chute avec blessure dans les escaliers du bureau. L\'employé a chuté dans les escaliers, possiblement due à un éclairage défaillant au niveau du palier du 2ème étage. Blessure au genou gauche avec suspicion de luxation. Transport aux urgences, radiographie prescrite. Signalement immédiat au service maintenance pour réparation de l\'éclairage. Une vérification complète de tous les accès est programmée.',
                    'Accident avec machine : blessure à l\'œil lors d\'usinage. Projection de particule métallique dans l\'œil droit pendant une opération d\'usinage. Lavage immédiat de l\'œil effectué, consultation ophtalmologique urgente. L\'employé avait bien le casque de protection mais pas de lunettes supplémentaires. Renforcement du protocole de sécurité : port obligatoire de lunettes de protection supplémentaires pour toutes les opérations d\'usinage.'
                ],
                'statuts' => ['en_attente', 'en_cours', 'traitee'],
                'reponses' => [
                    'en_cours' => 'Réclamation d\'accident de travail en cours d\'investigation approfondie. Tous les documents médicaux et de déclaration ont été collectés. La déclaration officielle aux assurances a été envoyée. Les services de santé au travail sont informés. Une inspection de sécurité sur le poste de travail concerné est prévue cette semaine pour identifier les mesures préventives supplémentaires à mettre en place.',
                    'traitee' => 'Réclamation traitée dans les délais réglementaires. Déclaration d\'accident complétée et transmise à l\'assurance. Les mesures correctives suivantes ont été mises en place : formation renforcée aux règles de sécurité, affichage de nouveaux panneaux de prévention, remplacement des équipements de protection défaillants, et mise en place d\'un calendrier d\'audit de sécurité mensuel. Le responsable du service a été sensibilisé à la vigilance accrue en matière de sécurité.'
                ]
            ],
            'conduite' => [
                'commentaires' => [
                    'Comportement irrespectueux récurrent envers les collègues signalé par plusieurs membres de l\'équipe. L\'employé utilise un langage inapproprié et des remarques désobligeantes lors des réunions d\'équipe. Des tensions sont apparues au sein du groupe, affectant l\'ambiance de travail et la productivité. Trois cas précis d\'incidents verbaux documentés avec témoins et dates.',
                    'Attitude hostile et agressive constatée à plusieurs reprises. L\'employé refuse toute forme de critique constructive et réagit de manière disproportionnée lors des points d\'équipe. Des échanges tendus ont eu lieu avec plusieurs collègues, créant un climat de tension. Le comportement a été observé lors de 5 incidents distincts sur les deux derniers mois, avec témoignages écrits des personnes concernées.',
                    'Conduite non professionnelle lors de la réunion client du 10 avril. L\'employé s\'est exprimé de manière déplacée en remettant en question les décisions de la direction devant le client, créant un malaise et portant atteinte à l\'image de l\'entreprise. Des excuses ont dû être présentées au client. Ce comportement inapproprié en présence de tiers externes nécessite une intervention rapide.',
                    'Manque de respect hiérarchique manifesté lors de l\'évaluation annuelle. L\'employé a contesté de manière agressive les objectifs fixés, tenant des propos déplacés envers le responsable direct. L\'ambiance de l\'entretien s\'est dégradée rapidement. Un tel comportement remet en question la confiance nécessaire à la relation de travail et à la collaboration effective.',
                    'Conflit persistant avec un membre de l\'équipe causant des perturbations. Les désaccords entre l\'employé et un collègue dépassent le cadre professionnel et impactent l\'ensemble de l\'équipe. Des rumeurs et tensions se sont propagées, affectant le moral et la concentration. Plusieurs membres de l\'équipe ont exprimé leur malaise concernant cette situation devenue intenable.',
                    'Utilisation inappropriée des outils de communication (emails, messagerie interne). L\'employé envoie des messages non professionnels, utilise un ton déplacé dans ses communications écrites, et ignore les règles de courtoisie établies. Des plaintes ont été reçues de clients et de partenaires concernant le ton utilisé dans les échanges. L\'image de l\'entreprise est potentiellement mise à mal.'
                ],
                'statuts' => ['en_attente', 'en_cours', 'traitee', 'rejetee'],
                'reponses' => [
                    'en_cours' => 'Réclamation en cours d\'investigation approfondie. Des entretiens individuels ont été menés avec l\'employé concerné et les membres de l\'équipe. Les témoignages sont en cours de collecte et d\'analyse. Un entretien disciplinaire est programmé dans les prochains jours pour présenter les faits reprochés et recueillir l\'explication de l\'employé. Une médiation interne pourrait être proposée si appropriée.',
                    'traitee' => 'Réclamation traitée après investigation complète. L\'employé a été convoqué à un entretien disciplinaire où toutes les plaintes et témoignages lui ont été présentés. Un avertissement écrit a été délivré avec rappel des obligations comportementales. Un programme d\'accompagnement comportemental sur 3 mois a été mis en place avec des points réguliers mensuels. Des formations en communication et gestion des conflits ont été proposées. Des sanctions supplémentaires pourraient être appliquées en cas de récidive.',
                    'rejetee' => 'Après investigation approfondie, la réclamation n\'a pas été retenue faute d\'éléments probants suffisants. Les faits allégués n\'ont pas pu être corroborés par des témoignages indépendants et fiables. Aucune preuve concrète n\'a pu être établie concernant les comportements reprochés. Cependant, les deux parties ont été conviées à un entretien de médiation pour améliorer la communication et prévenir tout futur conflit. Un suivi informel sera maintenu pendant les prochains mois.'
                ]
            ],
            'performance' => [
                'commentaires' => [
                    'Performance globalement en dessous des objectifs fixés depuis 6 mois consécutifs. L\'employé n\'atteint pas les objectifs quantitatifs définis (production inférieure de 25% par rapport aux objectifs). De plus, la qualité du travail produit présente des lacunes avec plusieurs erreurs détectées qui ont nécessité des corrections et révision de documents clients. Un plan d\'accompagnement avait été mis en place il y a 3 mois mais les améliorations attendues ne sont pas au rendez-vous.',
                    'Lenteur chronique dans l\'exécution des tâches affectant les délais de livraison. L\'employé prend systématiquement 50% plus de temps que prévu pour accomplir des tâches pourtant habituelles. Cette situation a déjà causé des retards sur 3 projets clients importants, nécessitant des interventions d\'urgence de l\'équipe. Les clients commencent à exprimer leur insatisfaction. Des formations ont été proposées mais n\'ont pas eu d\'effet notable sur la rapidité d\'exécution.',
                    'Manque d\'initiative et de proactivité constaté. L\'employé attend constamment des instructions détaillées même pour des tâches routinières. Aucune proposition d\'amélioration de procédures n\'a été faite malgré plusieurs demandes de contribution. En cas de problème, l\'employé reporte systématiquement au manager au lieu de tenter une résolution préalable. Cette attitude entrave l\'autonomie de l\'équipe et alourdit la charge du management.',
                    'Erreurs récurrentes ayant un impact client. Sur les 3 derniers mois, 8 erreurs significatives ont été détectées dans le travail de l\'employé, dont 3 ont directement impacté des clients et nécessité des correctifs en urgence. Les erreurs concernent notamment la saisie de données, la préparation de devis, et le suivi de dossiers. Des rappels des procédures ont été effectués mais les erreurs persistent, suggérant soit un manque d\'attention soit une incompréhension des processus.',
                    'Mauvaise gestion des priorités causant des délais non respectés. L\'employé semble avoir des difficultés à identifier et hiérarchiser les tâches importantes. Des tâches urgentes sont régulièrement reportées au profit d\'activités moins prioritaires. Cette mauvaise gestion du temps a déjà causé le dépassement de deux deadlines clients critiques. Des formations en gestion du temps ont été dispensées mais sans amélioration notable de la situation.',
                    'Incapacité à s\'adapter aux changements organisationnels. Suite à la mise en place de nouveaux outils et procédures il y a 4 mois, l\'employé rencontre des difficultés persistantes à s\'approprier les nouveaux processus. Une résistance au changement est perceptible. Malgré les formations dispensées et un accompagnement personnalisé de 2 mois, l\'employé continue de travailler selon les anciennes méthodes, créant des incohérences et des retards dans le traitement des dossiers. L\'impact sur l\'équipe devient préoccupant.'
                ],
                'statuts' => ['en_attente', 'en_cours', 'traitee'],
                'reponses' => [
                    'en_cours' => 'Réclamation en cours de traitement approfondi. Une analyse détaillée des objectifs manqués et des indicateurs de performance a été réalisée. Des entretiens individuels ont été menés pour comprendre les difficultés rencontrées par l\'employé. Un plan d\'amélioration personnalisé sera élaboré dans les prochains jours, incluant des objectifs clairs et mesurables, ainsi que des ressources de formation complémentaires. Un suivi hebdomadaire sera mis en place pour évaluer les progrès.',
                    'traitee' => 'Réclamation traitée avec mise en place d\'un plan d\'accompagnement complet. Après analyse approfondie, un plan d\'amélioration de la performance a été établi avec l\'employé. Les mesures suivantes ont été décidées : révision des objectifs avec critères clairs et réalisables, formation complémentaire sur les compétences identifiées comme lacunaires, mise en place d\'un mentorat hebdomadaire avec un membre expérimenté de l\'équipe, et évaluations intermédiaires tous les 15 jours. Des ressources supplémentaires ont été allouées pour faciliter l\'atteinte des objectifs. L\'employé s\'est engagé à respecter les nouvelles modalités. Un point de situation sera effectué dans 6 semaines.'
                ]
            ]
        ];

        $managerIds = array_map(fn($mgr) => $mgr->getId(), $managers);
        $responsableRhIds = array_map(fn($rh) => $rh->getId(), $responsablesRh);

        // Récupérer les IDs des employés une seule fois
        $sql = "SELECT id FROM t_employe WHERE roles::text LIKE '%ROLE_EMPLOYEE%'";
        $employeeIdsQuery = $this->em->getConnection()->executeQuery($sql);
        $employeeIds = $employeeIdsQuery->fetchFirstColumn();
        
        if (empty($employeeIds)) {
            $io->error('Aucun employé trouvé !');
            return Command::FAILURE;
        }

        $io->text(sprintf('Nombre d\'employés disponibles: %d', count($employeeIds)));

        $created = 0;

        for ($i = 0; $i < 80; $i++) {
            // Sélectionner un manager aléatoire
            $managerId = $managerIds[array_rand($managerIds)];
            $manager = $this->em->find(Employe::class, $managerId);
            
            if (!$manager) {
                continue;
            }
            
            // Sélectionner un ID d'employé aléatoire
            $employeeId = $employeeIds[array_rand($employeeIds)];
            $employee = $this->em->find(Employe::class, $employeeId);
            
            if (!$employee) {
                continue;
            }
            
            // Sélectionner un type de réclamation aléatoire
            $typeReclamation = array_rand($typesReclamations);
            $config = $typesReclamations[$typeReclamation];
            
            // Sélectionner un commentaire aléatoire
            $commentaire = $config['commentaires'][array_rand($config['commentaires'])];
            
            // Sélectionner un statut aléatoire
            $statut = $config['statuts'][array_rand($config['statuts'])];
            
            // Créer la réclamation
            $reclamation = new Reclamation();
            $reclamation->setEmploye($employee);
            $reclamation->setManager($manager);
            $reclamation->setTypeReclamation($typeReclamation);
            $reclamation->setCommentaire($commentaire);
            $reclamation->setStatut($statut);
            
            // Assigner un responsable RH pour traiter si disponible
            if (!empty($responsableRhIds) && in_array($statut, ['en_cours', 'traitee', 'rejetee'])) {
                $responsableRhId = $responsableRhIds[array_rand($responsableRhIds)];
                $responsableRh = $this->em->find(Employe::class, $responsableRhId);
                if ($responsableRh) {
                    $reclamation->setTraitePar($responsableRh);
                }
            }
            
            // Ajouter une réponse si la réclamation est traitée
            if (isset($config['reponses'][$statut])) {
                $reclamation->setReponseRh($config['reponses'][$statut]);
                $reclamation->setDateTraitement(new \DateTime());
            }
            
            // Date de création dans les 90 derniers jours
            $daysAgo = rand(1, 90);
            $dateCreation = new \DateTime('-' . $daysAgo . ' days');
            $reclamation->setDateCreation($dateCreation);
            
            // Date de traitement si traitée
            if (in_array($statut, ['en_cours', 'traitee', 'rejetee'])) {
                $daysAfter = rand(1, 30);
                $dateTraitement = new \DateTime('-' . ($daysAgo - $daysAfter) . ' days');
                if ($dateTraitement < new \DateTime()) {
                    $reclamation->setDateTraitement($dateTraitement);
                }
            }
            
            $this->em->persist($reclamation);
            $created++;
            
            // Flush tous les 20 enregistrements
            if ($created % 20 == 0) {
                $this->em->flush();
                $this->em->clear();
                $io->text(sprintf('%d réclamations créées...', $created));
            }
        }
        
        // Flush final
        $this->em->flush();
        
        $io->success(sprintf('%d réclamations créées avec succès !', $created));
        
        return Command::SUCCESS;
    }
}

