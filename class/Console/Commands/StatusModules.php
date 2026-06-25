<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\LegacyGlobals;
use Wonder\App\Module\Discovery;
use Wonder\App\Module\ManifestValidator;
use Wonder\App\Module\StateRepository;

class StatusModules extends Command
{
    public $name = 'status:modules';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Mostra i moduli scoperti e il loro stato');
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

        $modules = [];

        foreach (Discovery::discover() as $manifest) {
            $errors = ManifestValidator::errors($manifest);

            $modules[] = [
                'slug' => $manifest->slug(),
                'name' => $manifest->name(),
                'version' => $manifest->version(),
                'enabled' => StateRepository::isEnabled($manifest->slug()),
                'valid' => $errors === [],
                'source' => $manifest->source(),
                'root' => $manifest->root(),
                'errors' => $errors,
            ];
        }

        $output->writeln(json_encode([
            'success' => true,
            'count' => count($modules),
            'modules' => $modules,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
