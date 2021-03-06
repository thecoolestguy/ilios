<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\UserRoleInterface;
use App\Repository\UserRepository;
use App\Repository\UserRoleRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use App\Entity\UserInterface;
use App\Service\Directory;

/**
 * Syncs former students from the directory.
 *
 * Class SyncFormerStudentsCommand
 */
class SyncFormerStudentsCommand extends Command
{
    protected UserRepository $userRepository;
    protected UserRoleRepository $userRoleRepository;

    /**
     * @var Directory
     */
    protected $directory;

    public function __construct(
        UserRepository $userRepository,
        UserRoleRepository $userRoleRepository,
        Directory $directory
    ) {
        $this->userRepository = $userRepository;
        $this->userRoleRepository = $userRoleRepository;
        $this->directory = $directory;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ilios:sync-former-students')
            ->setAliases(['ilios:directory:sync-former-students'])
            ->setDescription('Sync former students from the directory.')
            ->addArgument(
                'filter',
                InputArgument::REQUIRED,
                'An LDAP filter to use in finding former students in the directory.'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Starting former student synchronization process.</info>');
        $filter = $input->getArgument('filter');

        $formerStudents = $this->directory->findByLdapFilter($filter);

        if (!$formerStudents) {
            $output->writeln("<error>{$filter} returned no results.</error>");
            return 0;
        }
        $output->writeln('<info>Found ' . count($formerStudents) . ' former students in the directory.</info>');

        $formerStudentsCampusIds = array_map(function (array $arr) {
            return $arr['campusId'];
        }, $formerStudents);

        $notFormerStudents = $this->userRepository->findUsersWhoAreNotFormerStudents($formerStudentsCampusIds);
        $usersToUpdate = $notFormerStudents->filter(function (UserInterface $user) {
            return !$user->isUserSyncIgnore();
        });
        if (!$usersToUpdate->count() > 0) {
            $output->writeln("<info>There are no students to update.</info>");
            return 0;
        }
        $output->writeln(
            '<info>There are ' .
            $usersToUpdate->count() .
            ' students in Ilios who will be marked as a Former Student.</info>'
        );
        $rows = $usersToUpdate->map(function (UserInterface $user) {
            return [
                $user->getCampusId(),
                $user->getFirstName(),
                $user->getLastName(),
                $user->getEmail(),
            ];
        })->toArray();
        $table = new Table($output);
        $table->setHeaders(['Campus ID', 'First', 'Last', 'Email'])->setRows($rows);
        $table->render();

        $helper = $this->getHelper('question');
        $output->writeln('');
        $question = new ConfirmationQuestion(
            '<question>Do you wish to mark these users as Former Students? </question>' . "\n",
            true
        );

        if ($helper->ask($input, $output, $question)) {
            /* @var UserRoleInterface $formerStudentRole */
            $formerStudentRole = $this->userRoleRepository->findOneBy(['title' => 'Former Student']);
            /* @var UserInterface $user */
            foreach ($usersToUpdate as $user) {
                $formerStudentRole->addUser($user);
                $user->addRole($formerStudentRole);
                $this->userRepository->update($user, false);
            }
            $this->userRoleRepository->update($formerStudentRole);

            $output->writeln('<info>Former students updated successfully!</info>');

            return 0;
        } else {
            $output->writeln('<comment>Update canceled,</comment>');

            return 1;
        }
    }
}
