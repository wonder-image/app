<?php

namespace Wonder\Elements\Frontend;

use Closure;
use Throwable;
use Wonder\Auth\Frontend\AccountPagination;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\Renderer;
use Wonder\Localization\TranslationProvider;

/**
 * La paginazione del frontend sulle classi `wi-pagination*` della lib:
 * precedente, la pagina corrente con le vicine, successiva. Non conta righe
 * e non legge la richiesta: riceve pagina e totale già calcolati.
 *
 *   // Generica: pagina corrente, totale pagine, come si costruisce il link.
 *   Pagination::make(3, 12)->url('/blog/')            // /blog/?page=3
 *   Pagination::make(3, 12)->url(fn (int $page) => "/blog/p/{$page}/")
 *
 *   // Dal pannello account: i conti di AccountPagination::make().
 *   Pagination::account($pagination, '/account/ordini/')
 */
class Pagination extends Component
{
    use Renderer;

    private ?Closure $url = null;

    public function __construct(int $page = 1, int $pages = 1)
    {
        $pages = max(1, $pages);

        $this->schema('pages', $pages);
        $this->schema('page', min(max(1, $page), $pages));
        $this->schema('window', 2);
        $this->schema('hidden', false);
    }

    public static function make(int $page = 1, int $pages = 1): static
    {
        return new static($page, $pages);
    }

    /**
     * I conti di `AccountPagination::make()` con il link `?pagina=N` e i testi
     * `account.pagination.*`; senza righe non stampa nulla.
     *
     * @param array{page: int, pages: int, from: int, to: int, total: int} $pagination
     */
    public static function account(array $pagination, string $baseUrl): static
    {
        $replacements = [
            'from' => $pagination['from'] ?? 0,
            'to' => $pagination['to'] ?? 0,
            'total' => $pagination['total'] ?? 0,
        ];

        return static::make((int) ($pagination['page'] ?? 1), (int) ($pagination['pages'] ?? 1))
            ->url(static fn (int $page): string => AccountPagination::url($baseUrl, $page))
            ->summary(self::translate('account.pagination.summary', $replacements))
            ->label(self::translate('account.pagination.label'))
            ->previousLabel(self::translate('account.pagination.previous'))
            ->nextLabel(self::translate('account.pagination.next'))
            ->hidden((int) ($pagination['total'] ?? 0) <= 0);
    }

    /**
     * Il link di ogni pagina. Una stringa è la base: la prima pagina resta la
     * base, le altre aggiungono `?{$param}=N` (o `&` se la base ha già una query).
     * Una funzione riceve il numero di pagina e restituisce l'URL.
     */
    public function url(string|callable $url, string $param = 'page'): static
    {
        if (is_string($url)) {
            $base = $url;
            $url = static fn (int $page): string => $page <= 1
                ? $base
                : $base.(str_contains($base, '?') ? '&' : '?').rawurlencode($param).'='.$page;
        }

        $this->url = Closure::fromCallable($url);

        return $this;
    }

    /** Le pagine mostrate per lato della corrente (2 di default). */
    public function window(int $side): static
    {
        return $this->schema('window', max(0, $side));
    }

    /** Il riepilogo accanto ai numeri ("Risultati da 1 a 10 di 42"): con lui il piede `wi-row-table__foot`. */
    public function summary(string $summary): static
    {
        return $this->schema('summary', $summary);
    }

    /** L'`aria-label` dell'elenco. */
    public function label(string $label): static
    {
        return $this->schema('label', $label);
    }

    public function previousLabel(string $label): static
    {
        return $this->schema('previous_label', $label);
    }

    public function nextLabel(string $label): static
    {
        return $this->schema('next_label', $label);
    }

    /** Nascosta, il renderer non stampa nulla (per esempio senza risultati). */
    public function hidden(bool $hidden = true): static
    {
        return $this->schema('hidden', $hidden);
    }

    public function page(): int
    {
        return (int) $this->schema['page'];
    }

    public function pages(): int
    {
        return (int) $this->schema['pages'];
    }

    /** @return list<int> la pagina corrente con le vicine */
    public function visiblePages(): array
    {
        $side = (int) $this->schema['window'];

        return range(max(1, $this->page() - $side), min($this->pages(), $this->page() + $side));
    }

    /** Senza `url()` il link è la sola query: `?` per la prima pagina, `?page=N` per le altre. */
    public function pageUrl(int $page): string
    {
        $url = $this->url ?? static fn (int $page): string => $page <= 1 ? '?' : '?page='.$page;

        return (string) $url($page);
    }

    private static function translate(string $key, array $replacements = []): string
    {
        if (function_exists('__t')) {
            return (string) __t($key, $replacements);
        }

        try {
            return (string) TranslationProvider::get($key, $replacements);
        } catch (Throwable) {
            return $key;
        }
    }
}
