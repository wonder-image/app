<?php

namespace Wonder\App\Media;

use Closure;

/**
 * Punto d'ingresso della libreria Media per chi non è una sua Resource:
 * lo store dei media (sostituibile nei test) e la ricerca del selettore
 * del campo media.
 *
 * Nessuna query finché non si chiama uno di questi metodi: lo store si
 * crea e si collega al database solo al primo uso.
 */
final class MediaLibrary
{
    /** Media per pagina nel selettore. */
    public const PAGE_SIZE = 24;

    /** Lunghezza massima del testo cercato. */
    public const TERM_LENGTH = 100;

    private static ?Closure $factory = null;
    private static ?MediaStore $store = null;

    public static function store(): MediaStore
    {
        return self::$store ??= self::$factory !== null ? (self::$factory)() : new DatabaseMediaStore();
    }

    /** @param MediaStore|Closure(): MediaStore|null $store null torna al database */
    public static function useStore(MediaStore|Closure|null $store): void
    {
        self::$store = $store instanceof MediaStore ? $store : null;
        self::$factory = $store instanceof Closure ? $store : null;
    }

    /**
     * Una pagina di media per il selettore: solo id, nome, slug, testo
     * alternativo, tipo e indirizzo dell'anteprima. Il tipo deve essere uno
     * di `MediaTypes`, altrimenti `null`: chi chiama risponde 422. Con
     * `$scope` (la tabella di una sezione) solo i media caricati da lì o
     * usati lì; uno scope che non è un nome di tabella dà `null`.
     *
     * @return array{items: list<array{id: int, name: string, slug: string, alt: string, type: string, thumbnail: string}>, page: int, pages: int, total: int}|null
     */
    public static function search(mixed $type, mixed $term = '', mixed $page = 1, mixed $scope = '', mixed $extensions = ''): ?array
    {
        if (!is_string($type) || !MediaTypes::has($type)) {
            return null;
        }

        // Una tabella o niente: un valore diverso non è una sezione.
        $scope = is_string($scope) ? trim($scope) : '';

        if ($scope !== '' && preg_match('/^[A-Za-z0-9_]{1,64}$/D', $scope) !== 1) {
            return null;
        }

        // Estensioni separate da virgola: solo quelle del tipo.
        $extensions = is_string($extensions) ? array_map(static fn (string $v): string => strtolower(trim($v)), explode(',', $extensions)) : [];
        $extensions = array_values(array_intersect(array_unique(array_filter($extensions)), MediaTypes::extensions($type)));

        $term = is_scalar($term) ? trim((string) $term) : '';
        $term = function_exists('mb_substr') ? mb_substr($term, 0, self::TERM_LENGTH) : substr($term, 0, self::TERM_LENGTH);
        $page = max(1, is_numeric($page) ? (int) $page : 1);

        $found = self::store()->search($type, $term, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE, $scope, $extensions);
        $items = [];

        foreach ($found['rows'] as $row) {
            $media = MediaFile::fromRow($row);

            if ($media !== null) {
                $items[] = $media->summary();
            }
        }

        $total = max(0, (int) $found['total']);

        return [
            'items' => $items,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'total' => $total,
        ];
    }
}
