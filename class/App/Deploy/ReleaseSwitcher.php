<?php

namespace Wonder\App\Deploy;

use RuntimeException;

/**
 * Sposta il link `current` su una release in modo atomico e tiene lo storico.
 *
 * Il link nuovo nasce con un nome temporaneo e sostituisce `current` con `rename()`,
 * che su un unico filesystem è atomico: chi legge vede sempre la release vecchia o
 * quella nuova, mai un link mancante. Lo stato (`deploy-state.json`, accanto al link)
 * ricorda la release attiva e le precedenti, per il ritorno indietro.
 * Non tocca mai il database.
 */
final class ReleaseSwitcher
{
    public const STATE_FILE = 'deploy-state.json';
    private const HISTORY = 20;

    public function __construct(private readonly string $link)
    {
    }

    public function link(): string
    {
        return rtrim($this->link, '/');
    }

    /** Cartella della release a cui punta oggi il link (null se non esiste o non è un link). */
    public function currentTarget(): ?string
    {
        $link = $this->link();

        if (!is_link($link)) {
            return null;
        }

        $real = realpath($link);

        return $real === false ? null : $real;
    }

    /** @return array{current: ?string, previous: ?string, history: list<string>} */
    public function state(): array
    {
        $path = dirname($this->link()).'/'.self::STATE_FILE;
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $history = is_array($data) && is_array($data['history'] ?? null)
            ? array_values(array_filter($data['history'], 'is_string'))
            : [];

        return [
            'current' => $history[0] ?? null,
            'previous' => $history[1] ?? null,
            'history' => $history,
        ];
    }

    /**
     * @return array{release: string, previous: ?string, link: string}
     */
    public function activate(string $releaseDir): array
    {
        $dir = realpath($releaseDir);

        if ($dir === false || !is_dir($dir)) {
            throw new RuntimeException('La cartella della release non esiste: '.$releaseDir);
        }

        $link = $this->link();

        if (file_exists($link) && !is_link($link)) {
            throw new RuntimeException($link.' esiste e non è un link: non lo sostituisco.');
        }

        if (!is_dir(dirname($link))) {
            throw new RuntimeException('La cartella del link non esiste: '.dirname($link));
        }

        $release = basename($dir);
        $state = $this->state();
        $before = $this->currentTarget();
        $previous = $before !== null ? basename($before) : $state['current'];

        // Link relativo se la release sta in `releases/` accanto al link: regge i mount diversi (container).
        $linkParent = realpath(dirname($link));
        $target = $linkParent !== false && dirname($dir) === $linkParent.'/releases'
            ? 'releases/'.$release
            : $dir;

        $tmp = $link.'.tmp-'.getmypid();
        @unlink($tmp);

        if (!@symlink($target, $tmp)) {
            throw new RuntimeException('Impossibile creare il link temporaneo '.$tmp.'.');
        }

        if (!@rename($tmp, $link)) {
            @unlink($tmp);
            throw new RuntimeException('Impossibile spostare '.$link.' su '.$release.'.');
        }

        $history = $state['history'];
        if ($previous !== null && ($history[0] ?? null) !== $previous) {
            array_unshift($history, $previous);
        }
        $history = array_values(array_unique(array_merge([$release], array_filter($history, static fn (string $id): bool => $id !== $release))));

        $this->writeState(array_slice($history, 0, self::HISTORY));

        return ['release' => $release, 'previous' => $previous !== $release ? $previous : ($history[1] ?? null), 'link' => $link];
    }

    /** Release a cui tornare: quella indicata, altrimenti la precedente nello storico. */
    public function rollbackTarget(?string $to): string
    {
        $current = $this->currentTarget();
        $currentId = $current !== null ? basename($current) : null;

        if ($to !== null && $to !== '') {
            $id = $to;
        } else {
            $candidates = array_values(array_filter($this->state()['history'], static fn (string $id): bool => $id !== $currentId));
            $id = $candidates[0] ?? '';
        }

        if (!ReleaseManifest::validId($id)) {
            throw new RuntimeException('Nessuna release precedente nello storico: indica quella di destinazione con --to.');
        }

        $dir = dirname($this->link()).'/releases/'.$id;

        if (!is_dir($dir)) {
            throw new RuntimeException('La release '.$id.' non c\'è più in '.dirname($dir).'.');
        }

        return $dir;
    }

    /** @param list<string> $history */
    private function writeState(array $history): void
    {
        $path = dirname($this->link()).'/'.self::STATE_FILE;
        $tmp = $path.'.tmp-'.getmypid();
        $json = json_encode([
            'current' => $history[0] ?? null,
            'previous' => $history[1] ?? null,
            'history' => $history,
            'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if ($json === false || file_put_contents($tmp, $json.PHP_EOL) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Il link è stato spostato ma non sono riuscito a scrivere '.$path.'.');
        }
    }
}
