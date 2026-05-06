<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\LegacyGlobals;
use Wonder\App\Module\Discovery;
use Wonder\App\Module\ManifestValidator;

class ValidateModule extends Command
{
    public $name = 'validate:module';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Valida il manifest e il contratto di un modulo')
            ->addArgument('slug', InputArgument::REQUIRED, 'Slug del modulo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = getcwd() ?: '.';
        $autoload = $root.'/vendor/autoload.php';

        if (!is_file($autoload)) {
            $output->writeln('<error>❌ Autoload consumer non trovato.</error>');
            return Command::FAILURE;
        }

        require_once $autoload;
        LegacyGlobals::share(['ROOT' => $root]);

        $slug = trim((string) $input->getArgument('slug'));

        foreach (Discovery::discover() as $manifest) {
            if ($manifest->slug() !== $slug) {
                continue;
            }

            ManifestValidator::assertValid($manifest);

            $output->writeln(json_encode([
                'success' => true,
                'slug' => $manifest->slug(),
                'name' => $manifest->name(),
                'version' => $manifest->version(),
                'source' => $manifest->source(),
                'root' => $manifest->root(),
                'composer_package' => $manifest->composerPackage(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $output->writeln('<error>❌ Modulo non trovato: '.$slug.'</error>');

        return Command::FAILURE;
    }
}
