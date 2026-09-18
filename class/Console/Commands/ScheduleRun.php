<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScheduleRun extends Command
{
    protected function configure(): void
    {
        $this->setName('schedule:run')->setDescription('Esegue le attivita pianificate scadute.');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = getcwd();
        $file = $root.'/bin/scheduler.php';
        if (!is_file($file)) {
            $output->writeln('<error>Eseguire prima forge build dal sito.</error>');
            return Command::FAILURE;
        }
        return \Wonder\App\Scheduler\Process::run([PHP_BINARY, $file], $root, 3620, static fn (string $text) => $output->write($text));
    }
}
