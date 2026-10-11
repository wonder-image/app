<?php

namespace Wonder\App\Support\GooglePlaces;

/**
 * Lettura di un luogo con Place Details di Places API (New). Il task usa
 * questa interfaccia: i test passano un client finto, un sito può
 * sostituire il trasporto senza toccare mapper e confronto.
 */
interface PlaceDetailsClient
{
    /**
     * Risposta JSON già decodificata; eccezione se la richiesta fallisce.
     *
     * @return array<string, mixed>
     */
    public function details(string $placeId, string $language): array;
}
