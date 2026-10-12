# reCAPTCHA — verifica server-side

La verifica reCAPTCHA lato server è first-class nel framework tramite
`Wonder\App\Security\RecaptchaGuard`. Non serve più leggere `$_POST`, gestire
try/catch, controllare `valid`, loggare o lanciare la `RuntimeException(617)` a
mano: tutto è centralizzato nel guard.

## Flow completo

### 1. Frontend — dichiarare il widget

Nel `formSchema()` del Resource (o in qualunque form), una sola riga:

```php
FormField::key('recaptcha')->recaptcha('contact');
```

Emette il widget `.g-recaptcha` più i tre hidden `g-recaptcha-token` /
`g-recaptcha-action` / `g-recaptcha-type`, riempiti runtime dal JS. Con la sola
chiave v3 non compare nessuna casella: vedi «v3 (punteggio) e casella di
riserva». L'argomento (`contact`) è l'**action attesa**; se omesso il default
frontend è `submit`.

### 2. Backend — verificare in una riga

Nel hook API del Resource (tipicamente `mutateRequestValues()` per `store`):

```php
public static function mutateRequestValues(
    array $values,
    string $action,
    string $context = 'backend',
    ?array $oldValues = null
): array {
    if ($context === 'api' && $action === 'store') {
        static::verifyRecaptcha();
    }

    return $values;
}
```

`static::verifyRecaptcha()` **senza argomenti** deriva l'action dal campo
`recaptcha` del `formSchema()`: la `FormField::recaptcha('contact')` è la singola
sorgente di verità, niente stringhe duplicate da tenere allineate. In caso di
fallimento lancia `RuntimeException(__t('notifications.617.text'), 617)` che il
middleware API restituisce al frontend.

Per passare l'action esplicita (form senza campo recaptcha, o con più d'uno):

```php
static::verifyRecaptcha('contact');
```

## Uso diretto fuori dai Resource

`RecaptchaGuard` è usabile ovunque (pagine custom, console, moduli):

```php
use Wonder\App\Security\RecaptchaGuard;

// Throw 617 su fallimento (logga prima di lanciare)
RecaptchaGuard::for('contact')->verify();

// Variante non-throwing: ritorna bool
if (RecaptchaGuard::for('contact')->passes()) {
    // ok
}
```

API fluente:

| Metodo | Effetto |
|--------|---------|
| `RecaptchaGuard::for($action)` | action attesa (`''` = accetta qualunque action ricevuta) |
| `->withRequest($array)` | sorgente dei campi `g-recaptcha-*` (default `$_POST`) |
| `->withEngine($engine)` | engine alternativo `verify($token, $action, $siteKey): object` (default `reCAPTCHA` Enterprise); usato per i test |
| `->withService($s)` / `->withLogAction($a)` / `->withLogFile($f)` | etichette/destinazione del log |
| `->verify(): void` | esegue; logga e lancia `617` su fallimento |
| `->passes(): bool` | come sopra ma ritorna `false` invece di lanciare il `617` o il `618` |
| `->withSiteKeys($checkbox, $score)` | chiavi del sito (default dalle credenziali); usato per i test |
| `->minScore($score)` | punteggio v3 minimo (default `0.5`) |
| `RecaptchaGuard::configured()` | c'è almeno una chiave (casella o v3) |
| `RecaptchaGuard::isFailure($e)` | `$e` è un 617 o un 618 della guardia |
| `RecaptchaGuard::challenged($action)` | il form di `$action` deve mostrare la casella (legge e cancella il segno in sessione) |

## v3 (punteggio) e casella di riserva

Due chiavi in Configurazione → Sicurezza: **Chiave Sito v2 (casella)**
(`g_recaptcha_site_key`, env `G_RECAPTCHA_SITE_KEY`) e **Chiave Sito v3
(punteggio)** (`g_recaptcha_score_site_key`, env `G_RECAPTCHA_SCORE_SITE_KEY`).
**Badge v3** (`g_recaptcha_badge`, env `G_RECAPTCHA_BADGE`, `true` di base)
mostra o nasconde il badge di Google; nascosto, sotto il riquadro compare la
riga di attribuzione con i link a privacy e termini di Google (obbligatoria).

| Chiavi | Riquadro | Punteggio basso |
|--------|----------|-----------------|
| solo casella | casella «Non sono un robot» (come prima) | — |
| solo v3 | nessun clic, badge o attribuzione | `617` |
| tutte e due | nessun clic, badge o attribuzione | `618` e la casella |
| nessuna | come prima (la verifica fallisce con `617`) | — |

Con la v3 la lib chiede un token nuovo a ogni invio del form e manda
`g-recaptcha-type=score`; la guardia sceglie la chiave dal tipo (assente =
`checkbox`). Con punteggio sotto `0.5` (o assente) e la chiave a casella
configurata, la guardia segna l'azione in sessione e lancia
`RecaptchaChallenge` (`618`, «Conferma di non essere un robot»): le API
rispondono 422 con `status: 618`, la lib accende la casella nello stesso
riquadro; i form ridisegnati dal server la mostrano grazie al segno. Il
successo cancella il segno.

**Fail-open.** Se l'engine lancia o Google non risponde davvero (rete, 200
senza JSON, 429, 5xx, 4xx diversi da 400) la verifica si **salta** con una
riga di log (`skipped: true`). Non passano mai: token o azione assenti, tipo
senza chiave, token non valido per Google, HTTP 400.

**Limite noto.** Il ripiego `form.submit()` di `confirm.js` (browser senza
`requestSubmit`) salta l'ascolto del submit della lib: con la v3 il form parte
col token vuoto e il server risponde `617`.

## Casi gestiti

I fallimenti producono il codice utente `617` (o `618` per la casella) e una
riga di log strutturata (file `storage/logs/error/recaptcha.log`) con `reason`;
le verifiche saltate scrivono `skipped: true`:

| `reason` | Quando | Esito | Livello |
|----------|--------|-------|---------|
| `missing` | `g-recaptcha-token` o `g-recaptcha-action` mancanti | `617` | `WARNING` |
| `invalid` | tipo senza chiave configurata (engine non chiamato), oppure Google risponde `valid = false` | `617` | `WARNING` |
| `action_mismatch` | action ricevuta ≠ action attesa (engine non chiamato), oppure action di Google ≠ attesa (`source: google`) | `617` | `WARNING` |
| `low_score` | tipo `score`, punteggio sotto il minimo o assente | `618` con la chiave a casella, altrimenti `617` | `WARNING` |
| `engine_unavailable` | Google non risponde davvero (`http_code` nel log) | salta | `WARNING` (0, 200, 429, 5xx), `ERROR` (altri 4xx: configurazione) |
| `engine_error` | l'engine lancia un'eccezione (loggata l'originale) | salta | `ERROR` |

Il context del log include action attesa, `request_uri`, `remote_addr` e flag di
presenza credenziali (`has_gcp_project_id`, `has_gcp_api_key`, `has_site_key`,
`has_score_site_key`) — senza loggarne i valori. Il campo `event` della risposta
di Google (contiene il token) non va nel log.

## Engine low-level

`RecaptchaGuard` riusa `Wonder\Plugin\Google\Security\reCAPTCHA::verify()` come
unico punto che fa la HTTP call Enterprise. Non duplicare quella chiamata.

## Legacy: `verifyRecaptcha($POST)`

> **Deprecato.** Usa `RecaptchaGuard` / `static::verifyRecaptcha()`.

La funzione procedurale `verifyRecaptcha($POST)` (che muta `$GLOBALS['ALERT']`)
resta solo per backward compatibility e ora **delega internamente al guard**,
preservando firma, return `bool` e `$ALERT`. Nuovo codice non deve usarla.

## Test

Runner standalone, niente phpunit:

```
php tests/Security/RecaptchaGuardTest.php
php tests/Plugin/Google/RecaptchaEngineTest.php
php tests/Themes/RecaptchaComponentTest.php
php tests/Security/RecaptchaConfigTest.php
php tests/Http/ErrorStatusTest.php
php tests/Http/CallHttpCodeTest.php
```

La guardia copre token valido, token/action mancanti, action mismatch (client
e Google), fail-open (eccezione, 429, 5xx, rete, 4xx), `valid=false`, HTTP 400,
v3 col punteggio alto e basso, la sfida `618` e il suo segno in sessione,
`passes()`, action vuota (BC) e la derivazione dell'action da `formSchema()`.
L'engine è iniettato (fake) → nessuna HTTP reale; la classificazione della
risposta di Google è provata a parte con una `Call` finta.
