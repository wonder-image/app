# reCAPTCHA Guard — verifica server-side first-class nei Resource

Data: 2026-06-18
Repo: `wonder-image/app` (framework)
Scope approvato: **Servizio dedicato + convenience statica su `Resource`** (no aggancio automatico al pipeline API; BC garantita).

## Problema

La verifica reCAPTCHA lato server è oggi boilerplate copia-incollato in ogni
`Resource`/handler API del sito: lettura manuale di `$_POST['g-recaptcha-token']`
e `$_POST['g-recaptcha-action']`, try/catch sulla chiamata Enterprise, check su
`$result->valid`, logging strutturato e `throw new RuntimeException(__t('notifications.617.text'), 617)`.
L'unica alternativa esistente è il legacy `verifyRecaptcha($POST)`, procedurale e
basato su `$GLOBALS['ALERT']`, inadatto al flow moderno dei `Resource`.

## Obiettivi

1. Eliminare la lettura manuale di token/action da `$_POST`.
2. Eliminare il boilerplate ripetuto (try/catch, `valid`, logging, `RuntimeException(617)`).
3. Integrazione idiomatica con i `Resource`, coerente con `FormField::recaptcha(...)`.
4. Nessun `$GLOBALS['ALERT']` nel flow moderno.
5. Backward compatibility totale con codice esistente (`verifyRecaptcha`, engine low-level).
6. Centralizzare comportamento, messaggi d'errore e logging.

## Vincoli noti dal codice

- Engine low-level: `Wonder\Plugin\Google\Security\reCAPTCHA::verify($token, $action): object`
  (ritorna `{result, valid}`). **Unico** punto che fa la HTTP call — da riusare, non duplicare.
- `Logger::log(Throwable $e, string $service, string $action, string $level='ERROR', string $file='error', array $context=[], bool $renderDebug=true)`
  richiede un `Throwable`.
- `__t('notifications.617.text')` + codice `617` = contratto d'errore utente esistente.
- Autoload: `Wonder\ => class/`. Nuova classe → `class/App/Security/RecaptchaGuard.php`.
- Nessun framework di test installato → test = script standalone eseguibile con `php`.

## Architettura

Due unità + un wrapper di compatibilità.

### 1. `Wonder\App\Security\RecaptchaGuard` (nuovo) — il servizio

Single responsibility: data una request e un'action attesa, garantire che il
token sia valido **oppure** lanciare il fallimento standard (617). È l'unica sede
del comportamento. Tutto il resto delega qui.

Forma fluente, immutabile-ish (builder):

```php
RecaptchaGuard::for('contact')          // action attesa
    ->withRequest($_POST)               // sorgente (default $_POST)
    ->withEngine($engine)               // engine iniettabile (default: new reCAPTCHA())
    ->withService('recaptcha')          // etichetta logger (default 'recaptcha')
    ->verify();                         // void | throw RuntimeException(617)
```

- `for(string $action): self` — action attesa (sorgente di verità: vedi §2 per il
  caso "action omessa, derivata dalla resource").
- `withRequest(array $request): self` — default `$_POST`. Iniettabile per i test.
- `withEngine(object $engine): self` — qualsiasi oggetto con `verify($token,$action): object`.
  Default lazy: `new reCAPTCHA()`. Rende il servizio testabile senza HTTP.
- `withService(string $service): self` — label `service` nel logger. Default `'recaptcha'`.
- `verify(): void` — esegue. Non ritorna nulla in caso di successo; in caso di
  fallimento **logga e lancia** `RuntimeException(__t('notifications.617.text'), 617)`.
- `passes(): bool` — variante non-throwing (try/catch interno) per chi vuole il
  branch manuale senza riscrivere la logica. Utile per pagine non-Resource.

Gestione dei 3 casi standard, tutti → log + `RuntimeException(617)`:

| Caso | Rilevazione | Log level | Context |
|------|-------------|-----------|---------|
| token/action mancanti | `g-recaptcha-token` vuoto o `g-recaptcha-action` vuoto | `WARNING` | `{reason: 'missing', has_token, has_action}` |
| action mismatch | action ricevuta ≠ action attesa | `WARNING` | `{reason: 'action_mismatch', expected, received}` |
| eccezione in verify | `Throwable` da `engine->verify()` | `ERROR` | `{reason: 'engine_error'}` — logga l'eccezione **originale**, poi lancia 617 |
| verify non valida | `$result->valid === false` | `WARNING` | `{reason: 'invalid', expected}` |

Per i casi non-eccezione (missing/mismatch/invalid) costruiamo la
`RuntimeException(617)` e la passiamo a `Logger::log()` (che richiede un `Throwable`),
poi la rilanciamo: un solo oggetto, un solo punto di throw.

Nota su `g-recaptcha-response`: il legacy faceva il match
`g-recaptcha-response === g-recaptcha-token`. Il flow moderno (componente Wonder)
emette solo `g-recaptcha-token`/`g-recaptcha-action`. Il guard **non** richiede
`g-recaptcha-response`; se presente lo ignora. Il match legacy resta confinato nel
wrapper `verifyRecaptcha()` (§3) per non rompere chi lo usa.

### 2. `Resource::verifyRecaptcha()` — la convenience idiomatica

Metodo statico sulla base `Wonder\App\Resource`. È la riga unica che il consumer
scrive nel proprio handler API:

```php
public static function verifyRecaptcha(?string $action = null): void
{
    RecaptchaGuard::for($action ?? static::recaptchaAction())
        ->withService('resource:'.static::slug())
        ->verify();
}
```

- `action` opzionale. Se omessa, deriva dall'**unico** campo `recaptcha` dichiarato
  in `formSchema()` (`recaptcha_action` nel context del `FormField`) → la stessa
  `FormField::recaptcha('contact')` diventa la singola sorgente di verità. Helper
  privato `recaptchaAction(): string` che introspeziona `formSchema()`; se non trova
  un campo recaptcha o se ce ne sono più d'uno ambigui, richiede l'action esplicita
  (eccezione di configurazione chiara, non un 617 silenzioso).
- `service` del logger valorizzato con lo slug della resource → log attribuibili.

Risultato lato consumer:

```php
// formSchema()
FormField::key('recaptcha')->recaptcha('contact');

// handler API — UNICA riga, nessun altro boilerplate
static::verifyRecaptcha();            // action 'contact' derivata dal form
// oppure esplicita: static::verifyRecaptcha('contact');
```

### 3. `verifyRecaptcha($POST)` legacy — wrapper BC

Riscritto per delegare al guard, preservando firma, return `bool` e `$ALERT`:

```php
function verifyRecaptcha($POST) {
    global $ALERT;
    // match legacy v2 preservato per BC
    if (($POST['g-recaptcha-response'] ?? null) !== ($POST['g-recaptcha-token'] ?? null)) {
        $ALERT = 617;
        return false;
    }
    if (RecaptchaGuard::for($POST['g-recaptcha-action'] ?? '')
            ->withRequest($POST)->passes()) {
        $ALERT = null;
        return true;
    }
    $ALERT = 617;
    return false;
}
```

Comportamento centralizzato nel guard, ma nessuna rottura per i consumer legacy.
Docblock marcato `@deprecated` con puntatore a `RecaptchaGuard` / `Resource::verifyRecaptcha`.

## Data flow

```
handler API del Resource
  └─ static::verifyRecaptcha()                     (Resource, convenience)
       └─ RecaptchaGuard::for(action)->verify()    (servizio: legge $_POST, valida)
            ├─ engine->verify(token, action)        (reCAPTCHA low-level, HTTP)
            ├─ Logger::log(...)                      (su qualunque fallimento)
            └─ throw RuntimeException(617)           (su qualunque fallimento)
```

## Error handling

- Un solo codice utente: `617` con `__t('notifications.617.text')`.
- Un solo punto di throw (il guard). `Resource::verifyRecaptcha` e il legacy non
  rilanciano logiche proprie.
- Logging sempre presente prima del throw, con `service`/`action`/`context`
  consistenti — niente fallimenti silenziosi.

## Testing

Script standalone `tests/Security/RecaptchaGuardTest.php` eseguibile via
`php tests/Security/RecaptchaGuardTest.php` (no phpunit). Engine fake iniettato via
`withEngine()`. Casi coperti:

1. token valido + action corretta → `verify()` non lancia; `passes()` true.
2. token mancante → `RuntimeException` codice 617; log emesso (`reason: missing`).
3. action mancante → 617.
4. action mismatch (attesa `contact`, ricevuta `submit`) → 617 (`reason: action_mismatch`).
5. engine lancia eccezione → 617; loggata l'eccezione originale (`reason: engine_error`).
6. engine ritorna `valid=false` → 617 (`reason: invalid`).
7. `passes()` ritorna bool senza propagare l'eccezione.
8. derivazione action da `formSchema()` (`Resource::verifyRecaptcha()` senza arg).

Il logger viene reindirizzato su file temporaneo / o si verifica solo il throw, per
non dipendere dall'I/O reale. `__t()` stubbabile o tollerato (ritorna la key).

## Documentazione

Aggiornare `docs/app/servizi/` con una pagina `recaptcha.md` (o sezione) che documenta:
il flow frontend `FormField::recaptcha('contact')`, la riga `static::verifyRecaptcha()`
nel handler, l'uso diretto di `RecaptchaGuard` fuori dai Resource, e la deprecazione
di `verifyRecaptcha($POST)`. Aggiornare `docs/app/SUMMARY.md` se indicizza i servizi.

## File toccati

- `class/App/Security/RecaptchaGuard.php` — nuovo servizio.
- `class/App/Resource.php` — `verifyRecaptcha()` + `recaptchaAction()` (privato).
- `app/function/frontend/plugin/reCAPTCHA.php` — refactor legacy a wrapper + `@deprecated`.
- `tests/Security/RecaptchaGuardTest.php` — test standalone.
- `docs/app/servizi/recaptcha.md` (+ SUMMARY) — documentazione.

## Out of scope (esplicito)

- Aggancio automatico al pipeline API (`ApiSchema::recaptcha()` auto-run su store/update):
  non in questa iterazione. Promovibile in seguito sopra `RecaptchaGuard` senza rotture.
- Modifica dell'engine HTTP low-level.
