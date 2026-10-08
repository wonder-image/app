<?php

namespace Wonder\Docs;

/**
 * I dati delle due pagine del catalogo (indice e scheda), uguali per il
 * backend di un sito e per il server autonomo: le viste in
 * `app/view/pages/docs/` li ricevono così come sono.
 */
final class CatalogPage
{
    /** @return array<string, mixed> */
    public static function index(Urls $urls): array
    {
        $availability = [];

        foreach (Catalog::all() as $slug => $doc) {
            $availability[$slug] = ThemeSupport::for($doc);
        }

        return array_merge(self::shared($urls, $availability), [
            'view' => 'index',
            'doc' => null,
            'counts' => self::counts($availability),
        ]);
    }

    /** @return array<string, mixed>|null null quando lo slug non esiste */
    public static function component(string $slug, Urls $urls): ?array
    {
        $doc = Catalog::find($slug);

        if ($doc === null) {
            return null;
        }

        $availability = [];

        foreach (Catalog::all() as $key => $candidate) {
            $availability[$key] = ThemeSupport::for($candidate);
        }

        $examples = [];

        foreach ($doc->getExamples() as $index => $example) {
            $examples[] = [
                'index' => $index,
                'example' => $example,
                'code' => ExampleRunner::display($doc, $example),
            ];
        }

        return array_merge(self::shared($urls, $availability), [
            'view' => 'component',
            'doc' => $doc,
            'availability' => $availability[$doc->getSlug()],
            'examples' => $examples,
            'api' => ApiReference::for($doc->getClass()),
            'neighbors' => Catalog::neighbors($doc),
            'related' => array_values(array_filter(array_map(
                static fn (string $related): ?ComponentDoc => Catalog::find($related),
                $doc->getRelated()
            ))),
        ]);
    }

    /**
     * @param array<string, array<string, ThemeAvailability>> $availability
     * @return array<string, mixed>
     */
    private static function shared(Urls $urls, array $availability): array
    {
        return [
            'urls' => $urls,
            'themes' => ThemeSupport::themes(),
            'categories' => Catalog::categories(),
            'grouped' => Catalog::grouped(),
            'catalogAvailability' => $availability,
            'total' => count(Catalog::all()),
        ];
    }

    /**
     * @param array<string, array<string, ThemeAvailability>> $availability
     * @return array<string, int> tema => componenti disponibili, più `both` e `total`
     */
    private static function counts(array $availability): array
    {
        $counts = ['total' => count($availability), 'both' => 0];

        foreach (ThemeSupport::themes() as $theme) {
            $counts[$theme] = 0;
        }

        foreach ($availability as $themes) {
            $all = true;

            foreach ($themes as $theme => $support) {
                if ($support->available) {
                    $counts[$theme]++;
                } else {
                    $all = false;
                }
            }

            if ($all) {
                $counts['both']++;
            }
        }

        return $counts;
    }
}
