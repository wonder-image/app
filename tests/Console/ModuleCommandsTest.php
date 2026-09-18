<?php
/** php tests/Console/ModuleCommandsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Symfony\Component\Console\Command\Command;
use Wonder\Console\ModuleCommands;

final class ComandoDiProva extends Command
{
    public $name = 'prova:uno';
}

final class NonUnComando
{
}

final class ManifestDiProva
{
    public function __construct(private string $slug, private array $commands)
    {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function consoleCommands(): array
    {
        return $this->commands;
    }
}

check('classi valide raccolte in ordine di modulo', function () {
    $result = ModuleCommands::fromManifests([
        new ManifestDiProva('gestionale', [ComandoDiProva::class]),
    ]);

    return $result['commands'] === [ComandoDiProva::class] && $result['errors'] === [];
});

check('classe inesistente segnalata e saltata', function () {
    $result = ModuleCommands::fromManifests([
        new ManifestDiProva('gestionale', ['Classe\\Che\\Non\\Esiste']),
    ]);

    return $result['commands'] === []
        && count($result['errors']) === 1
        && str_contains($result['errors'][0], 'gestionale');
});

check('classe che non estende Command segnalata e saltata', function () {
    $result = ModuleCommands::fromManifests([
        new ManifestDiProva('gestionale', [NonUnComando::class]),
    ]);

    return $result['commands'] === [] && count($result['errors']) === 1;
});

check('doppioni tolti', function () {
    $result = ModuleCommands::fromManifests([
        new ManifestDiProva('gestionale', [ComandoDiProva::class]),
        new ManifestDiProva('ecommerce', [ComandoDiProva::class]),
    ]);

    return $result['commands'] === [ComandoDiProva::class];
});

check('manifest senza comandi: nessun errore', function () {
    $result = ModuleCommands::fromManifests([new ManifestDiProva('immobili', [])]);

    return $result['commands'] === [] && $result['errors'] === [];
});

summary();
