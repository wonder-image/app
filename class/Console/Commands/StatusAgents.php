<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\AI\AgentRegistry;
use Wonder\AI\AgentValidator;
use Wonder\AI\YamlReader;
use Wonder\App\LegacyGlobals;

/**
 * `forge status:agents`
 *
 * Stampa in JSON la lista degli agenti AI scoperti nel progetto corrente,
 * con per ciascuno: slug, modello, file `source` per ogni file canonico
 * (così si vede se l'agente è "puro framework", "puro consumer", o
 * misto), e lista di errori di validazione.
 *
 * Naming coerente con `status:modules` del Module system esistente.
 */
class StatusAgents extends Command
{
    public $name = 'status:agents';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Mostra gli agenti AI scoperti e la loro provenienza (framework/modulo/consumer)');
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

        // Force fresh discovery (utile se l'utente lancia il comando in
        // sequenza dopo aver modificato file).
        AgentRegistry::reset();

        $agents = [];

        foreach (AgentRegistry::all() as $slug => $agent) {
            $agentDir = dirname($agent->promptPath);

            // Rileggi l'agent.yml grezzo per validazione: la registry
            // restituisce l'AgentConfig risolto con default, ma per
            // l'errore "campo obbligatorio mancante" ci serve il raw.
            $rawConfigPath = self::findAgentYml($agent, $agentDir);
            $rawConfig = $rawConfigPath !== null ? (YamlReader::parseFile($rawConfigPath) ?? []) : [];
            $errors = AgentValidator::errors($rawConfig, $slug, $agentDir);

            $agents[] = [
                'slug' => $slug,
                'name' => $agent->config->name,
                'model' => $agent->config->model,
                'provider' => $agent->config->provider,
                'sources' => $agent->sources,   // {agent.yml: 'app'|'module:rsvp'|'consumer', ...}
                'valid' => $errors === [],
                'errors' => $errors,
            ];
        }

        $output->writeln(json_encode([
            'success' => true,
            'count' => count($agents),
            'agents' => $agents,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    /**
     * Risolve il path dell'agent.yml usato dalla cascade. Lo recuperiamo
     * dai `sources` dell'agente: sappiamo da quale source viene
     * `agent.yml`, possiamo riapplicare la stessa logica del resolver
     * o semplicemente cercare il file nello stesso dir del prompt.
     *
     * Implementazione pragmatica: il prompt e l'agent.yml possono venire
     * da layer diversi (file-level cascade), quindi cerchiamo l'agent.yml
     * a partire dalla cartella del prompt e risaliamo nei layer noti.
     * Più semplice ancora: tentiamo accanto al promptPath, poi sui
     * sibling layer noti.
     */
    private static function findAgentYml(\Wonder\AI\Agent $agent, string $agentDir): ?string
    {
        $sibling = $agentDir.'/agent.yml';
        if (is_file($sibling)) {
            return $sibling;
        }

        // Edge case: prompt.md sta nel consumer ma agent.yml nel framework.
        // Cerchiamo `/ai/agents/<slug>/agent.yml` nei layer standard.
        $root = getcwd() ?: '.';
        $candidates = [
            $root.'/ai/agents/'.$agent->slug.'/agent.yml',
            $root.'/vendor/wonder-image/app/ai/agents/'.$agent->slug.'/agent.yml',
        ];

        foreach ($candidates as $c) {
            if (is_file($c)) {
                return $c;
            }
        }

        return null;
    }
}
