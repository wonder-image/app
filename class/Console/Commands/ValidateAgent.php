<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\AI\AgentRegistry;
use Wonder\AI\AgentValidator;
use Wonder\AI\YamlReader;
use Wonder\App\LegacyGlobals;

/**
 * `forge validate:agent <slug>`
 *
 * Valida la shape di un singolo agente (campi obbligatori, tipi, file
 * prompt presente). Output JSON. Exit 0 = OK, exit 1 = errori.
 *
 * Coerente naming con `validate:module` del Module system.
 */
class ValidateAgent extends Command
{
    public $name = 'validate:agent';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Valida la shape di un agente AI (agent.yml + prompt.md)')
            ->addArgument('slug', InputArgument::REQUIRED, 'Slug dell\'agente (nome cartella)');
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

        AgentRegistry::reset();

        $slug = trim((string) $input->getArgument('slug'));

        if (!AgentRegistry::has($slug)) {
            $output->writeln('<error>❌ Agente non trovato: '.$slug.'</error>');
            $available = AgentRegistry::slugs();
            if (!empty($available)) {
                $output->writeln('<comment>   Agenti disponibili: '.implode(', ', $available).'</comment>');
            }
            return Command::FAILURE;
        }

        $agent = AgentRegistry::get($slug);
        $agentDir = dirname($agent->promptPath);

        // Re-parsing del raw agent.yml per validare contro la shape attesa.
        $rawConfigPath = $this->locateAgentYml($slug, $agentDir, $root);
        $rawConfig = $rawConfigPath !== null ? (YamlReader::parseFile($rawConfigPath) ?? []) : [];
        $errors = AgentValidator::errors($rawConfig, $slug, $agentDir);

        $output->writeln(json_encode([
            'success' => $errors === [],
            'slug' => $slug,
            'name' => $agent->config->name,
            'model' => $agent->config->model,
            'provider' => $agent->config->provider,
            'sources' => $agent->sources,
            'agent_yml_path' => $rawConfigPath,
            'prompt_path' => $agent->promptPath,
            'errors' => $errors,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $errors === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function locateAgentYml(string $slug, string $agentDir, string $root): ?string
    {
        $sibling = $agentDir.'/agent.yml';
        if (is_file($sibling)) {
            return $sibling;
        }

        $candidates = [
            $root.'/ai/agents/'.$slug.'/agent.yml',
            $root.'/vendor/wonder-image/app/ai/agents/'.$slug.'/agent.yml',
        ];

        foreach ($candidates as $c) {
            if (is_file($c)) {
                return $c;
            }
        }

        return null;
    }
}
