<?php

namespace Wonder\Frontend\Support;

/**
 * Entità schema.org comuni a tutte le pagine del sito: l'organizzazione
 * (dai dati di $SOCIETY) e il sito web. Hanno un `@id` stabile così le
 * entità delle pagine (prodotti, offerte) possono riferirle senza ripeterle.
 */
final class SiteSchema
{
    public static function organizationId(string $site): string
    {
        return rtrim($site, '/').'/#organization';
    }

    public static function webSiteId(string $site): string
    {
        return rtrim($site, '/').'/#website';
    }

    /** @return array<string, mixed>|null */
    public static function organization(object $society, string $site, string $logo = ''): ?array
    {
        $site = rtrim($site, '/');
        $name = trim((string) ($society->name ?? ''));
        if ($site === '' || $name === '') {
            return null;
        }

        $entity = [
            '@type' => 'Organization',
            '@id' => self::organizationId($site),
            'name' => $name,
            'url' => $site.'/',
        ];

        $legalName = trim((string) ($society->legal_name ?? ''));
        if ($legalName !== '' && $legalName !== $name) {
            $entity['legalName'] = $legalName;
        }
        if ($logo !== '') {
            $entity['logo'] = $logo;
        }
        foreach (['email' => 'email', 'tel' => 'telephone'] as $from => $to) {
            $value = trim((string) ($society->$from ?? ''));
            if ($value !== '') {
                $entity[$to] = $value;
            }
        }
        $vat = trim((string) ($society->pi ?? ''));
        if ($vat !== '') {
            $entity['vatID'] = $vat;
        }

        $street = trim(trim((string) ($society->street ?? '')).' '.trim((string) ($society->number ?? '')));
        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'postalCode' => trim((string) ($society->cap ?? '')),
            'addressLocality' => trim((string) ($society->city ?? '')),
            'addressRegion' => trim((string) ($society->province ?? '')),
            'addressCountry' => trim((string) ($society->country ?? '')),
        ], static fn ($value): bool => $value !== '');
        if (count($address) > 1) {
            $entity['address'] = $address;
        }

        $sameAs = [];
        foreach (['instagram', 'facebook', 'tiktok', 'linkedin', 'youtube'] as $network) {
            $url = trim((string) ($society->$network ?? ''));
            if (preg_match('~^https?://~i', $url)) {
                $sameAs[] = $url;
            }
        }
        if ($sameAs !== []) {
            $entity['sameAs'] = $sameAs;
        }

        return $entity;
    }

    /**
     * @param string $searchUrl URL di ricerca con il segnaposto `{search_term_string}`.
     * @return array<string, mixed>|null
     */
    public static function webSite(string $site, string $name, string $lang = '', string $searchUrl = ''): ?array
    {
        $site = rtrim($site, '/');
        if ($site === '') {
            return null;
        }

        $entity = [
            '@type' => 'WebSite',
            '@id' => self::webSiteId($site),
            'url' => $site.'/',
            'publisher' => ['@id' => self::organizationId($site)],
        ];
        if (trim($name) !== '') {
            $entity['name'] = trim($name);
        }
        if ($lang !== '') {
            $entity['inLanguage'] = $lang;
        }
        if (str_contains($searchUrl, '{search_term_string}')) {
            $entity['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $searchUrl],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $entity;
    }

    /**
     * Unisce le entità della pagina con quelle del sito: la pagina vince sullo
     * stesso `@id` o, per Organization/WebSite senza `@id`, sullo stesso tipo.
     * Le offerte della pagina senza `seller` vengono attribuite all'organizzazione.
     *
     * @param array<int, mixed> $page
     * @param array<int, array<string, mixed>|null> $site
     * @return array<int, mixed>
     */
    public static function merge(array $page, array $site): array
    {
        $ids = [];
        $types = [];
        foreach ($page as $entity) {
            if (!is_array($entity)) {
                continue;
            }
            if (isset($entity['@id'])) {
                $ids[(string) $entity['@id']] = true;
            }
            $types[(string) ($entity['@type'] ?? '')] = true;
        }

        $prepend = [];
        $organization = null;
        foreach ($site as $entity) {
            if ($entity === null || isset($ids[(string) $entity['@id']]) || isset($types[(string) $entity['@type']])) {
                continue;
            }
            if ($entity['@type'] === 'Organization') {
                $organization = ['@id' => (string) $entity['@id']];
            }
            $prepend[] = $entity;
        }

        if ($organization !== null) {
            foreach ($page as &$entity) {
                if (is_array($entity) && is_array($entity['offers'] ?? null)) {
                    $entity['offers'] = self::withSeller($entity['offers'], $organization);
                }
            }
            unset($entity);
        }

        return [...$prepend, ...$page];
    }

    /**
     * @param array<int|string, mixed> $offers
     * @param array{'@id': string} $seller
     * @return array<int|string, mixed>
     */
    private static function withSeller(array $offers, array $seller): array
    {
        if (!array_is_list($offers)) {
            if (in_array($offers['@type'] ?? '', ['Offer', 'AggregateOffer'], true) && !isset($offers['seller'])) {
                $offers['seller'] = $seller;
            }

            return $offers;
        }

        return array_map(static fn ($offer) => is_array($offer) ? self::withSeller($offer, $seller) : $offer, $offers);
    }
}
