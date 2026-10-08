<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

/**
 * Una finestra di anteprima: un `<iframe>` con una barra che permette di
 * scegliere fra più sorgenti (per esempio il tema Wonder e il tema Bootstrap
 * dello stesso componente) e, dove ha senso, di passare da chiaro a scuro.
 *
 * Ogni sorgente è una pagina a sé, con il proprio CSS: è l'unico modo di
 * mostrare due temi sulla stessa pagina senza che i fogli di stile si
 * sovrappongano. Una sorgente senza URL compare spenta, con il motivo.
 */
class Preview extends Component
{
    use CanSpanColumn, Renderer;

    private const SCHEMES = ['light', 'dark'];

    public function __construct(string $title = '')
    {
        $this->schema('sources', []);
        $this->title($title);
        $this->scheme('light');
        $this->autoHeight();
        $this->openInNewTab();
    }

    public static function make(string $title = ''): static
    {
        return new static($title);
    }

    public function title(string $title): static
    {
        return $this->schema('title', $title);
    }

    /**
     * Una sorgente dell'anteprima. Senza `$url` la scheda resta spenta e
     * `reason` spiega perché.
     *
     * Opzioni: `schemes` (bool, mostra il passaggio chiaro/scuro), `reason`
     * (string), `icon` (string, classe Bootstrap Icons), `srcdoc` (string, HTML
     * inline al posto dell'URL), `reload` (bool, ricarica la pagina quando
     * cambia lo schema invece di avvisarla via `postMessage`).
     */
    public function source(string $key, string $label, ?string $url, array $options = []): static
    {
        $key = strtolower(trim($key));

        if (!preg_match('/^[a-z0-9_-]+$/', $key)) {
            throw new InvalidArgumentException('La chiave della sorgente deve contenere solo lettere, numeri, trattini e underscore.');
        }

        $sources = $this->schema['sources'] ?? [];
        $sources[$key] = [
            'label' => $label,
            'url' => $url !== null && trim($url) !== '' ? trim($url) : null,
            'srcdoc' => isset($options['srcdoc']) && is_string($options['srcdoc']) ? $options['srcdoc'] : null,
            'schemes' => (bool) ($options['schemes'] ?? false),
            'reason' => (string) ($options['reason'] ?? ''),
            'icon' => (string) ($options['icon'] ?? ''),
            'reload' => (bool) ($options['reload'] ?? false),
        ];

        return $this->schema('sources', $sources);
    }

    /** La sorgente mostrata all'apertura; senza, la prima disponibile. */
    public function active(string $key): static
    {
        return $this->schema('active', strtolower(trim($key)));
    }

    /** Lo schema iniziale (`light` o `dark`) per le sorgenti che lo supportano. */
    public function scheme(string $scheme): static
    {
        $scheme = strtolower(trim($scheme));

        if (!in_array($scheme, self::SCHEMES, true)) {
            throw new InvalidArgumentException('Schema non valido. Valori ammessi: '.implode(', ', self::SCHEMES));
        }

        return $this->schema('scheme', $scheme);
    }

    /** Altezza minima del riquadro in pixel. */
    public function height(int $height): static
    {
        return $this->schema('height', max(0, $height));
    }

    /**
     * Segue l'altezza del contenuto: la pagina nell'iframe la comunica con
     * `postMessage({ type: 'wi-preview:height', height })`.
     */
    public function autoHeight(bool $autoHeight = true): static
    {
        return $this->schema('auto_height', $autoHeight);
    }

    /**
     * Le anteprime con lo stesso gruppo condividono sorgente e schema: un
     * selettore di pagina (`data-wi-preview-switch`) le cambia tutte insieme e
     * la scelta resta in `localStorage`.
     */
    public function group(string $group): static
    {
        $group = strtolower(trim($group));

        if ($group !== '' && !preg_match('/^[a-z0-9_-]+$/', $group)) {
            throw new InvalidArgumentException('Il gruppo deve contenere solo lettere, numeri, trattini e underscore.');
        }

        return $this->schema('group', $group);
    }

    public function openInNewTab(bool $open = true): static
    {
        return $this->schema('open_in_new_tab', $open);
    }

    /** @return array<string, array<string, mixed>> */
    public function getSources(): array
    {
        $sources = $this->schema['sources'] ?? [];

        return is_array($sources) ? $sources : [];
    }
}
