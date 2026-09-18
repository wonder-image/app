<?php

namespace Wonder\Console;

use Symfony\Component\Console\Command\Command;
use Wonder\App\LegacyGlobals;
use Wonder\App\Module\Discovery;
use Wonder\App\Module\StateRepository;

/**
 * Comandi `forge` dichiarati dai moduli in `console.commands` del manifest.
 * Le classi non valide vengono saltate con un messaggio: un modulo scritto
 * male non deve impedire l'uso di `forge`.
 */
final class ModuleCommands
{
    /**
     * @param iterable<object> $manifests oggetti con slug() e consoleCommands()
     * @return array{commands: list<string>, errors: list<string>}
     */
    public static function fromManifests(iterable $manifests): array
    {
        $commands = [];
        $errors = [];

        foreach ($manifests as $manifest) {
            foreach ($manifest->consoleCommands() as $class) {
                if (!is_string($class) || trim($class) === '') {
                    $errors[] = 'Comando non valido nel modulo '.$manifest->slug()
                        .': nome della classe mancante';
                    continue;
                }

                $class = trim($class);

                if (!class_exists($class) || !is_subclass_of($class, Command::class)) {
                    $errors[] = 'Comando non valido nel modulo '.$manifest->slug().': '.$class
                        .' non esiste o non estende '.Command::class;
                    continue;
                }

                if (!in_array($class, $commands, true)) {
                    $commands[] = $class;
                }
            }
        }

        return ['commands' => $commands, 'errors' => $errors];
    }

    /**
     * Comandi dei moduli abilitati del sito corrente.
     *
     * @return array{commands: list<string>, errors: list<string>}
     */
    public static function all(): array
    {
        $root = getcwd() ?: '.';

        if (!is_file($root.'/vendor/autoload.php')) {
            return ['commands' => [], 'errors' => []];
        }

        LegacyGlobals::share(['ROOT' => $root]);

        $enabled = [];

        foreach (Discovery::discover() as $manifest) {
            if (StateRepository::isEnabled($manifest->slug())) {
                $enabled[] = $manifest;
            }
        }

        return self::fromManifests($enabled);
    }
}
