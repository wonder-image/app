# Cloudflare

`php forge cloudflare:security` allinea la zona Cloudflare del sito a una
configurazione di sicurezza comune: HTTPS, impostazioni di sicurezza, Page
Shield, una regola WAF per `/api/` e un rate limit. È dichiarativo: si può
rilanciare quante volte si vuole, cambia solo ciò che è diverso e tocca solo
le regole che crea lui.

Non tutti i siti usano Cloudflare: il comando parte solo se il sito lo
dichiara con `APP_PROXY=cloudflare` (vedi [APP_PROXY](#app_proxy)).

## Dove gira

| Dove | Come | Cosa fa |
|---|---|---|
| Locale, prima configurazione | `php forge provision` (step Cloudflare) | Chiede il token, lo salva in Bitwarden con `CLOUDFLARE_ZONE_ID` e `CLOUDFLARE_ACCOUNT_ID`, poi lancia il comando |
| Locale, a mano | `php forge cloudflare:security [--dry-run] [--domain esempio.it]` | Applica (o con `--dry-run` mostra) le modifiche |
| Deploy (GitHub Actions) | Step `☁️ Cloudflare` prima di `🗄️ Update App` | Riallinea la zona a ogni deploy; se fallisce il deploy prosegue |

Lo step del deploy gira solo se `vars.APP_PROXY == 'cloudflare'` e il token è
presente nel project Bitwarden; altrimenti viene saltato.

## Token

Il token lo crea l'utente in Cloudflare (My Profile › API Tokens › Create
Token › Custom token), limitato alla zona del sito, con questi permessi:

| Permesso | Serve per |
|---|---|
| Zone › Zone › Read | Trovare la zona dal dominio |
| Zone › Zone Settings › Edit | SSL, HTTPS, TLS, Security Level, Browser Integrity Check |
| Zone › Zone WAF › Edit | Regola WAF e rate limit |
| Zone › Bot Management › Edit | Leggere e impostare Bot Fight Mode |
| Zone › Page Shield › Edit | Attivare Page Shield |
| Zone › SSL and Certificates › Read | Stato dei certificati |
| Zone › DNS › Read | Record @ e www (verifica origine e avvisi), DNSSEC |

Un permesso mancante non blocca gli altri passi: il passo fallisce con il
messaggio `Permesso del token da verificare: …` e il comando esce con errore.

### Dove sta

| Variabile | Dove | Note |
|---|---|---|
| `CLOUDFLARE_API_TOKEN` | Project Bitwarden del sito | **Non** va nel `.env` di produzione: il deploy lo esclude come `FTP_*` e `BWS_*` |
| `CLOUDFLARE_ZONE_ID` | Project Bitwarden del sito | Scritto da provision; evita la ricerca della zona |
| `CLOUDFLARE_ACCOUNT_ID` | Project Bitwarden del sito | Scritto da provision |

Il comando cerca il token in quest'ordine:

1. variabile d'ambiente (o `.env` locale);
2. project Bitwarden (`BWS_ACCESS_TOKEN` + `BWS_PROJECT_ID`, serve il comando `bws`);
3. domanda nascosta, solo da terminale interattivo e fuori dalla CI.

Senza token il comando si chiude con successo e non fa nulla.

## Cosa configura

### HTTPS

| Impostazione | Valore |
|---|---|
| SSL/TLS | Full (strict), **solo** se il certificato dell'origine è valido |
| Always Use HTTPS | on |
| Automatic HTTPS Rewrites | on |
| TLS minimo | 1.2 |
| TLS 1.3 | on |

Prima di passare a Full (strict) il comando legge gli IP dell'origine dai
record DNS di `@` e `www` e verifica il certificato collegandosi
direttamente a quegli IP. Se il certificato non è valido, o non ci sono
record, la modalità SSL resta quella attuale; con `flexible` o `off`
avvisa che il tratto Cloudflare → origine non è cifrato.

### Sicurezza

| Impostazione | Valore predefinito |
|---|---|
| Security Level | medium |
| Browser Integrity Check | on |
| Page Shield | on |
| Bot Fight Mode | **off** |

Bot Fight Mode resta spento perché sfida anche le chiamate del deploy su
`/api/app/update/` e non si può escludere con una regola. In CI e con
`--dry-run` lo stato viene solo segnalato, mai cambiato; si attiva
dalla config (vedi sotto) lanciando il comando in locale.

### Regola WAF «wonder-image»

Regola personalizzata (fase `http_request_firewall_custom`, ref
`wonder_image_api_skip`):

```
(starts_with(http.request.uri.path, "/api/") and not starts_with(http.request.uri.path, "/api/frontend/"))
```

Azione **skip** di Security Level e Browser Integrity Check: le chiamate
server-to-server (deploy, gestionale, MCP) su `/api/` non ricevono la
sfida JavaScript, che un client non-browser non può superare. `/api/frontend/`
resta protetto perché lo chiamano i browser.

### Rate limit su `/api/`

Regola di rate limit (fase `http_ratelimit`, ref
`wonder_image_api_ratelimit`): blocco per 10 secondi oltre 60 richieste in
10 secondi per IP e data center. Sono i parametri ammessi dal piano free,
che consente **una sola** regola di rate limit: se la zona ne ha già
un'altra, il comando non la tocca e segnala che la nostra è bloccata.

Il rate limit serve anche con Bot Fight Mode spento: la regola skip toglie
le sfide su `/api/`, e il rate limit è l'unico freno lato Cloudflare
contro chi martella gli endpoint.

### Regole esistenti

Il comando riconosce le proprie regole dal `ref` e aggiorna solo quelle.
Le altre regole della zona restano com'erano; la nostra viene aggiunta in
cima, così lo skip vale prima delle altre regole. Con `--dry-run` le scritture sui ruleset vengono validate da
Cloudflare (`dry_run=true`) senza applicarle.

## Avvisi

Il comando non cambia queste cose, ma le segnala:

- **HSTS** resta all'origine (header del sito, vedi
  [Sicurezza](sicurezza.md)): non va attivato anche in Cloudflare.
- **DNSSEC** non attivo: si attiva in Cloudflare (DNS › Settings) e poi si
  inserisce il record DS dal registrar.
- **Record @ o www non proxati** (nuvola grigia): il traffico non passa da
  Cloudflare e nessuna di queste protezioni si applica.

## Configurazione per sito

I valori predefiniti si cambiano con `custom/config/cloudflare.php` nel
sito. Vengono lette solo le chiavi note:

```php
<?php

return [
    'bot_fight_mode' => false,     // true per attivarlo (solo da locale)
    'security_level' => 'medium',  // off, essentially_off, low, medium, high, under_attack
    'page_shield' => true,
    'api_skip' => true,            // regola WAF «wonder-image» su /api/
    'rate_limit' => true,
    'rate_limit_requests' => 60,   // richieste ogni 10 secondi per IP
];
```

## APP_PROXY

`php forge provision` chiede che proxy c'è davanti al sito:

| Risposta | `APP_PROXY` | `APP_TRUST_PROXY` | Step Cloudflare |
|---|---|---|---|
| nessuno | `nessuno` | `false` | no |
| cloudflare | `cloudflare` | `true` | sì |
| altro | `altro` | `true` | no |

Il default è `cloudflare` se i nameserver del dominio sono
`*.ns.cloudflare.com`; altrimenti si parte dal valore già salvato. Entrambe
le variabili finiscono nelle GitHub Variables della repo. Vedi
[Multi-ambiente](multi-ambiente.md#github-actions-variables).

Con `APP_TRUST_PROXY=true` il sito si fida di `X-Forwarded-For` e
`X-Forwarded-Proto`: è corretto solo se l'origine accetta traffico
esclusivamente dal proxy. Con Cloudflare l'origine resta raggiungibile
anche direttamente finché il firewall del server non limita le connessioni
agli IP di Cloudflare.

## Esempio

```bash
php forge cloudflare:security --dry-run
```

```
☁️  Cloudflare per esempio.it (dry-run)
   CLOUDFLARE_ZONE_ID=…
   CLOUDFLARE_ACCOUNT_ID=…
   → ssl: "full" → "strict" (origine verificata: esempio.it, www.esempio.it)
   ✓ always_use_https = "on"
   …
   → Page Shield: spento → attivo
ℹ️  HSTS resta all'origine (header del sito): non attivarlo anche in Cloudflare.
✅ Cloudflare allineato.
```
