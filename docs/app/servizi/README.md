# Servizi

Questa sezione è divisa in due aree operative:
- setup/configurazione servizi
- Google Tag Manager (configurazione contenitore e integrazioni collegate)

## Sicurezza
- [reCAPTCHA — verifica server-side](recaptcha.md)
- [Throttle di login e recupero password](../concetti/utenti/auth-frontend.md#throttle)

## Google Tag Manager
- [Configurazione](google-tag-manager/configurazione.md)
- [iubenda](google-tag-manager/iubenda.md)
- [Google Analytics](google-tag-manager/google-analytics.md)

## Configurazione
- [Google Cloud Platform](configurazione/google-cloud-platform.md)
- [Google Sign-In OAuth](configurazione/google-sign-in-oauth.md)
- [Sign in with Apple](configurazione/sign-in-with-apple.md)
- [Google Search Console](configurazione/google-search-console.md)
- [Fatture in Cloud](configurazione/fatture-in-cloud.md)
- [Credenziali dei pagamenti](configurazione/pagamenti.md)
- [Brevo](configurazione/brevo.md)
- [Klaviyo](configurazione/klaviyo.md)

## Chiamate HTTP in uscita

I servizi esterni passano da `Wonder\Api\Call` (anche `curl()`, `curlJson()`,
`wiApi()`). Ogni chiamata ha un timeout di default di 10 secondi
(`Call::DEFAULT_TIMEOUT`); senza, cURL aspetterebbe all'infinito. Chi ha bisogno
di altro lo imposta: `->timeout($secondi)` per il totale (connessione inclusa),
`->connectTimeout($secondi)` per la sola connessione. `curlJson()` accetta il
timeout come ultimo parametro.

```php
$call = (new Wonder\Api\Call($url, $values))
    ->contentType('application/json')
    ->connectTimeout(3)
    ->timeout(30);
```

Alla scadenza `call()` restituisce `success => false` con `errno` 28,
`result()` la stringa d'errore di cURL (non `null`), `curlJson()` e
`wiApiJson()` `null`. `IPInfo` (massimo 3 s) e reCAPTCHA (massimo 5 s) usano
timeout più corti; PayPal e Nexi 30 s, la creazione rapida del backend 60 s.
