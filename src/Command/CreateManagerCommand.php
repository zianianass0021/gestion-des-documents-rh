<?php

namespace App\Command;

use App\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-manager',
    description: 'Create a manager user for testing reclamations',
)]
class CreateManagerCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check if manager already exists
        $existingManager = $this->entityManager->getRepository(Employe::class)
            ->findOneBy(['email' => 'manager@example.com']);

        if ($existingManager) {
            $io->success('Manager already exists!');
            return Command::SUCCESS;
        }

        // Create manager
        $manager = new Employe();
        $manager->setEmail('manager@example.com');
        $manager->setUsername('manager');
        $manager->setPrenom('Jean');
        $manager->setNom('Manager');
        $manager->setTelephone('0123456789');
        $manager->setRoles(['ROLE_MANAGER']);
        $manager->setIsActive(true);

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($manager, 'password');
        $manager->setPassword($hashedPassword);

        $this->entityManager->persist($manager);
        $this->entityManager->flush();

        $io->success('Manager created successfully!');
        $io->note('Email: manager@example.com');
        $io->note('Password: password');

        return Command::SUCCESS;
    }
}