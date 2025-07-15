<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Config extends Command
{
    public $name = 'config';

    protected function configure(): void
    {
        $this->setName($this->name);
        $this->setDescription('Crea package.json e installa wonder-image + tutte le dipendenze NPM');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $cwd = getcwd();
        $packageJsonPath = $cwd . '/package.json';

        // Crea package.json se non esiste
        if (!file_exists($packageJsonPath)) {
            $package = [
                'private' => true,
                'dependencies' => new \stdClass()
            ];

            file_put_contents($packageJsonPath, json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $output->writeln('<info>✅ package.json creato.</info>');
        } else {
            $output->writeln('<comment>⚠️ package.json già presente.</comment>');
        }

        // Installa wonder-image
        $output->writeln('<info>📦 Eseguo: npm install wonder-image</info>');
        passthru('npm install wonder-image');

        // Installa tutte le dipendenze (inclusa quella appena aggiunta)
        $output->writeln('<info>📦 Eseguo: npm install</info>');
        passthru('npm install');

        $output->writeln("<info>✅ Aggiornamento effettuato con successo!</info>");

        return Command::SUCCESS;

    }
}
