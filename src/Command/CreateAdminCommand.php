<?php

namespace App\Command;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create admin user for the Hausman system',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private RoleRepository $roleRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find Super Admin role
        $superAdminRole = $this->roleRepository->findByName('ROLE_SUPER_ADMIN');
        if (!$superAdminRole) {
            $io->error('ROLE_SUPER_ADMIN not found. Please run fixtures first.');

            return Command::FAILURE;
        }

        // Create admin user
        $admin = new User();
        $admin->setEmail('admin@hausman.local');
        $admin->setFirstName('System');
        $admin->setLastName('Administrator');
        $admin->setIsActive(true);

        // Hash password: admin123
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);

        // Add role
        $admin->addUserRole($superAdminRole);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success('Admin user created successfully!');
        $io->info('Email: admin@hausman.local');
        $io->info('Password: admin123');
        $io->warning('Please change the default password after first login!');

        return Command::SUCCESS;
    }
}
