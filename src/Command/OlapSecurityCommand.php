<?php

namespace App\Command;

use App\Service\ClickHouseClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'app:olap:security',
    description: 'Manage ClickHouse OLAP security: create users, rotate credentials',
)]
class OlapSecurityCommand extends Command
{
    private ClickHouseClient $clickHouseClient;

    public function __construct(ClickHouseClient $clickHouseClient)
    {
        parent::__construct();
        $this->clickHouseClient = $clickHouseClient;
    }

    protected function configure(): void
    {
        $this
            ->addOption('create-user', null, InputOption::VALUE_NONE, 'Create least-privilege OLAP user')
            ->addOption('rotate-password', null, InputOption::VALUE_OPTIONAL, 'Rotate password for a user (specify username)', false)
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, 'Username for password rotation', 'rh_olap_app')
            ->addOption('new-password', 'p', InputOption::VALUE_OPTIONAL, 'New password (will prompt if not provided)', null)
            ->setHelp('
This command manages ClickHouse OLAP security.

Examples:
  php bin/console app:olap:security --create-user
  php bin/console app:olap:security --rotate-password --username=rh_olap_app
  php bin/console app:olap:security --rotate-password -u rh_olap_readonly -p "new_secure_password"
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('ClickHouse OLAP Security Management');

        try {
            if ($input->getOption('create-user')) {
                return $this->createUser($io);
            }

            if ($input->getOption('rotate-password') !== false) {
                $username = $input->getOption('username');
                $newPassword = $input->getOption('new-password');
                
                if ($newPassword === null) {
                    $helper = $this->getHelper('question');
                    $question = new Question('Enter new password: ');
                    $question->setHidden(true);
                    $question->setHiddenFallback(false);
                    $newPassword = $helper->ask($input, $output, $question);
                    
                    if (empty($newPassword)) {
                        $io->error('Password cannot be empty');
                        return Command::FAILURE;
                    }
                    
                    $question = new Question('Confirm new password: ');
                    $question->setHidden(true);
                    $confirmPassword = $helper->ask($input, $output, $question);
                    
                    if ($newPassword !== $confirmPassword) {
                        $io->error('Passwords do not match');
                        return Command::FAILURE;
                    }
                }
                
                return $this->rotatePassword($io, $username, $newPassword);
            }

            $io->error('Please specify an action: --create-user or --rotate-password');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Security operation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createUser(SymfonyStyle $io): int
    {
        try {
            // Read the SQL script
            $sqlScript = file_get_contents(__DIR__ . '/../../olap/03_create_olap_user.sql');
            
            if ($sqlScript === false) {
                throw new \RuntimeException('Could not read user creation script');
            }

            $io->section('Creating OLAP users...');
            
            // Execute the script (ClickHouse requires statements to be executed separately)
            $statements = array_filter(
                array_map('trim', explode(';', $sqlScript)),
                fn($stmt) => !empty($stmt) && !preg_match('/^--/', $stmt)
            );

            foreach ($statements as $statement) {
                if (empty(trim($statement))) {
                    continue;
                }
                
                try {
                    $this->clickHouseClient->execute($statement . ';');
                    $io->text('✓ ' . substr($statement, 0, 60) . '...');
                } catch (\Exception $e) {
                    // Ignore "already exists" errors
                    if (strpos($e->getMessage(), 'already exists') !== false) {
                        $io->text('⚠ ' . substr($statement, 0, 60) . '... (already exists)');
                    } else {
                        throw $e;
                    }
                }
            }

            $io->success('OLAP users created successfully!');
            $io->note([
                'Created users:',
                '  - rh_olap_app (read-write)',
                '  - rh_olap_readonly (read-only)',
                '',
                'IMPORTANT: Update passwords in production!',
                'Run: php bin/console app:olap:security --rotate-password -u rh_olap_app'
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to create users: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function rotatePassword(SymfonyStyle $io, string $username, string $newPassword): int
    {
        try {
            $io->section("Rotating password for user: {$username}");

            // Check if user exists
            $checkQuery = "SELECT name FROM system.users WHERE name = '{$username}'";
            $users = $this->clickHouseClient->select($checkQuery);

            if (empty($users)) {
                $io->error("User '{$username}' does not exist");
                return Command::FAILURE;
            }

            // Rotate password using ALTER USER
            // Note: ClickHouse requires sha256 hash or plaintext depending on auth type
            // For simplicity, we'll use plaintext password (ClickHouse will hash it)
            $alterQuery = "ALTER USER {$username} IDENTIFIED WITH sha256_password BY '{$newPassword}'";
            
            $this->clickHouseClient->execute($alterQuery);

            $io->success("Password rotated successfully for user: {$username}");
            $io->note([
                'IMPORTANT: Update the password in your .env file:',
                "CLICKHOUSE_PASSWORD={$newPassword}",
                '',
                'Then restart your application.'
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Password rotation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

