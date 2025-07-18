<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Dotenv\Dotenv;


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
        $envPath = $cwd . '/.env';
        $packageJsonPath = $cwd . '/package.json';

        // Verifico se il file .env ha la APP_KEY, se non ne ha una la creo
        $ENV_FILE = Dotenv::createImmutable($cwd);
        $ENV_FILE->safeLoad();

        if (!isset($_ENV['APP_KEY'])) {
            
            $lines = file($envPath, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $i => $line) {
                if (preg_match("/^APP_URL=/", $line)) {

                    $appKey = bin2hex(random_bytes(32));

                    array_splice($lines, $i + 1, 0, "APP_KEY=$appKey");

                    break;

                }
            }
            
            file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);

            $output->writeln('<info>✅ Aggiungo un APP_KEY al file .env</info>');

        }

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
