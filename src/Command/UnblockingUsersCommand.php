<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Команда для сброса разблокировки юзеров по истечению времени
 */
class UnblockingUsersCommand extends Command
{
    public function __construct(
        protected \Doctrine\Persistence\ManagerRegistry $doctrine,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:unblocking-users')
            ->setDescription('Разблокировка пользователей по истечению времени')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Выполнение команды app:unblocking-users');
        $this->unblockingUsersAction();
        $output->writeln('Команда app:unblocking-users выполнена успешно.');

        return Command::SUCCESS;
    }

    public function unblockingUsersAction() 
    {
        $conn = $this->doctrine->getManager()->getConnection();
        $sql = 'UPDATE `table1` SET `enabled` = 1, `disabled_before` = NULL WHERE `enabled` = 0 AND `disabled_before` IS NOT NULL 
                    AND `disabled_before` <= UNIX_TIMESTAMP()';
        $stmt = $conn->prepare($sql);
        $stmt->executeQuery();
    }
}
