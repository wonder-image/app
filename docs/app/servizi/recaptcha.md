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

Emette il widget `.g-recaptcha` più i due hidden `g-recaptcha-token` /
`g-recaptcha-action`, riempiti runtime dal JS. L'argomento (`contact`) è
l'**action attesa**; se omesso il default frontend è `submit`.

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
| `->withEngine($engine)` | engine alternativo `verify($token,$action): object` (default `reCAPTCHA` Enterprise); usato per i test |
| `->withService($s)` / `->withLogAction($a)` / `->withLogFile($f)` | etichette/destinazione del log |
| `->verify(): void` | esegue; logga e lancia `617` su fallimento |
| `->passes(): bool` | come sopra ma ritorna `false` invece di lanciare il `617` |

## Casi gestiti

Tutti i fallimenti producono **un solo** codice utente (`617`) e una riga di log
strutturata (file `storage/logs/error/recaptcha.log`) con `reason`:

| `reason` | Quando | Livello |
|----------|--------|---------|
| `missing` | `g-recaptcha-token` o `g-recaptcha-action` mancanti | `WARNING` |
| `action_mismatch` | action ricevuta ≠ action attesa (engine non chiamato) | `WARNING` |
| `engine_error` | l'engine lancia un'eccezione (loggata l'originale) | `ERROR` |
| `invalid` | l'engine ritorna `valid = false` | `WARNING` |

Il context del log include action attesa, `request_uri`, `remote_addr` e flag di
presenza credenziali (`has_gcp_project_id`, `has_gcp_api_key`, `has_site_key`) —
senza loggarne i valori.

## Engine low-level

`RecaptchaGuard` riusa `Wonder\Plugin\Google\Security\reCAPTCHA::verify()` come
unico punto che fa la HTTP call Enterprise. Non duplicare quella chiamata.

## Legacy: `verifyRecaptcha($POST)`

> **Deprecato.** Usa `RecaptchaGuard` / `static::verifyRecaptcha()`.

La funzione procedurale `verifyRecaptcha($POST)` (che muta `$GLOBALS['ALERT']`)
resta solo per backward compatibility e ora **delega internamente al guard**,
preservando firma, return `bool` e `$ALERT`. Nuovo codice non deve usarla.

## Test

`tests/Security/RecaptchaGuardTest.php` (runner standalone, niente phpunit):

```
php tests/Security/RecaptchaGuardTest.php
```

Copre token valido, token/action mancanti, action mismatch, eccezione engine,
`valid=false`, `passes()`, action vuota (BC) e la derivazione dell'action da
`formSchema()`. L'engine è iniettato (fake) → nessuna HTTP reale.
