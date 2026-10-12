<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

/**
 * Google reCAPTCHA — il riquadro: casella v2 e/o v3 a punteggio, con la
 * casella di riserva.
 *
 * Element standalone (non ha nulla a che vedere con `Submit`): rende
 * il widget `g-recaptcha` più gli input hidden richiesti dalla
 * verifica server-side (`verifyRecaptcha`).
 *
 * Setter coerenti con `Wonder\Plugin\Custom\Input\reCAPTCHA`:
 *  - `theme()`  → light | dark   (default light)
 *  - `size()`   → compact | normal (default normal)
 *  - `action()` → action logica   (default submit)
 *
 * Le chiavi (`g_recaptcha_site_key`, `g_recaptcha_score_site_key`) e il
 * badge (`g_recaptcha_badge`) le legge il renderer da
 * `\Wonder\App\Credentials::api()`: sono credenziali globali del sito,
 * non parametri per chiamata.
 */
class reCAPTCHA extends Field
{
    public string $type = 'recaptcha';

    public function __construct(string $name = 'g-recaptcha')
    {
        parent::__construct($name);

        $this->schema('recaptcha_theme', 'light');
        $this->schema('recaptcha_size', 'normal');
        $this->schema('recaptcha_action', 'submit');
    }

    /**
     * Tema del widget reCAPTCHA. Valori ammessi: `light` (default), `dark`.
     * Valori vuoti vengono ignorati (mantenuto il default).
     */
    public function theme(?string $value): self
    {
        if ($value === null || $value === '') {
            return $this;
        }

        return $this->schema('recaptcha_theme', $value);
    }

    /**
     * Dimensione del widget. Valori ammessi: `normal` (default), `compact`.
     * Valori vuoti vengono ignorati (mantenuto il default).
     */
    public function size(?string $value): self
    {
        if ($value === null || $value === '') {
            return $this;
        }

        return $this->schema('recaptcha_size', $value);
    }

    /**
     * Action logica passata al widget e validata server-side.
     * Default: `submit`. Valori vuoti vengono ignorati.
     */
    public function action(?string $value): self
    {
        if ($value === null || $value === '') {
            return $this;
        }

        return $this->schema('recaptcha_action', $value);
    }
}
