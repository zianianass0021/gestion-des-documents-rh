<?php

namespace App\Command;

use App\Entity\Employe;
use App\Repository\EmployeRepository;
use App\Repository\ModuleRepository;
use App\Service\ModulePermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:assign-all-modules',
    description: 'Assign all active modules to a user by username',
)]
class AssignAllModulesToUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmployeRepository $employeRepository,
        private ModuleRepository $moduleRepository,
        private ModulePermissionService $modulePermissionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The username of the user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');

        $user = $this->employeRepository->findOneBy(['username' => $username]);

        if (!$user) {
            $io->error(sprintf('User with username "%s" not found.', $username));
            return Command::FAILURE;
        }

        if (!in_array('ROLE_RESPONSABLE_RH', $user->getRoles())) {
            $io->warning(sprintf('User "%s" is not a Responsable RH. Proceeding anyway...', $username));
        }

        // Get all active modules
        $modules = $this->moduleRepository->findAllActiveOrdered();

        if (empty($modules)) {
            $io->error('No active modules found.');
            return Command::FAILURE;
        }

        // Assign all modules
        $this->modulePermissionService->assignModules($user, $modules);

        $io->success(sprintf(
            'Successfully assigned %d module(s) to user "%s" (%s %s).',
            count($modules),
            $username,
            $user->getPrenom(),
            $user->getNom()
        ));

        $io->listing(array_map(fn($m) => $m->getLabel(), $modules));

        return Command::SUCCESS;
    }
}

