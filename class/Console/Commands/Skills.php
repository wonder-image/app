<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Skills extends Config
{
    public $name = 'skills';

    protected function configure(): void
    {
        $this->setName($this->name);
        $this->setDescription('Installa o aggiorna le AI skills consigliate per Wonder tramite npx skills');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->isCiEnvironment()) {
            $output->writeln('<comment>ℹ️ Ambiente CI rilevato: php forge skills è pensato solo per il locale.</comment>');
            return Command::SUCCESS;
        }

        if (!$this->ensureWonderNodeToolchain($output, true)) {
            return Command::FAILURE;
        }

        if (!$this->installRecommendedSkills($output)) {
            return Command::FAILURE;
        }

        $output->writeln('<info>✅ Skills AI installate.</info>');

        return Command::SUCCESS;
    }
}
