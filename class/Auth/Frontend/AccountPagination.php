<?php

namespace Wonder\Auth\Frontend;

/** Conti della paginazione del pannello: la pagina chiesta si riporta tra 1 e l'ultima. */
final class AccountPagination
{
    /** @return array{page: int, pages: int, per_page: int, offset: int, from: int, to: int, total: int, limit: string} */
    public static function make(int $total, mixed $page, int $perPage = 10): array
    {
        $total = max(0, $total);
        $perPage = max(1, $perPage);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = is_numeric($page) ? (int) $page : 1;
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
            'offset' => $offset,
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => min($total, $offset + $perPage),
            'total' => $total,
            'limit' => $offset.', '.$perPage,
        ];
    }

    /** Il `?pagina=` della richiesta, così com'è: lo ripulisce `make()`. */
    public static function requested(): mixed
    {
        return $_GET['pagina'] ?? 1;
    }

    /** Il link a una pagina; la prima è la base senza parametro. */
    public static function url(string $base, int $page): string
    {
        return $page <= 1 ? $base : $base.(str_contains($base, '?') ? '&' : '?').'pagina='.$page;
    }

    /**
     * Le pagine da mostrare: la corrente con due per lato.
     *
     * @param array{page: int, pages: int} $pagination
     * @return list<int>
     */
    public static function window(array $pagination): array
    {
        return range(max(1, $pagination['page'] - 2), min($pagination['pages'], $pagination['page'] + 2));
    }
}
