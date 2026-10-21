# reCAPTCHA — verifica server-side

La verifica reCAPTCHA lato server è first-class nel framework tramite
`Wonder\App\Security\RecaptchaGuard`. Non serve più leggere `$_POST`, gestire
try/catch, controllare `valid`, loggare o lanciare la `RuntimeException(617)` (o
`618`) a mano: tutto è centralizzato nel guard.

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
fallimento lancia `RuntimeException(__t('notifications.617.text'), 617)` (o il
`618` se serve la casella) che il middleware API restituisce al frontend: il 618
arriva come HTTP 422 con `status: 618`.

Per passare l'action esplicita (form senza campo recaptcha, o con più d'uno):

```php
static::verifyRecaptcha('contact');
```

## Uso diretto fuori dai Resource

`RecaptchaGuard` è usabile ovunque (pagine custom, console, moduli):

```php
use Wonder\App\Security\RecaptchaGuard;

// Throw 617 (o 618 se serve la casella) su fallimento (logga prima di lanciare)
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
| `->verify(): void` | esegue; logga e lancia `617` su fallimento (o `618` se serve la casella) |
| `->passes(): bool` | come sopra ma ritorna `false` invece di lanciare il `617` o il `618` |
| `->withSiteKeys($checkbox, $score)` | chiavi del sito (default dalle credenziali); usato per i test |
| `->minScore($score)` | punteggio v3 minimo (default `0.5`) |
| `->failClosed(?bool $value = null)` | Google non raggiungibile → `617` invece del salto; senza argomento vale solo in produzione (default della guardia: salto) |
| `RecaptchaGuard::configured()` | c'è almeno una chiave (casella o v3) |
| `RecaptchaGuard::isFailure($e)` | `$e` è un 617 o un 618 della guardia |
| `RecaptchaGuard::challenged($action)` | il form di `$action` deve mostrare la casella (legge e cancella il segno in sessione) |

## v3 (punteggio) e casella di riserva

Due righe in Dev → API e servizi → Credenziali (solo admin), nella scheda
Google Cloud Platform:

- **Google reCAPTCHA v2**: la **Chiave Sito** della casella
  (`g_recaptcha_site_key`, env `G_RECAPTCHA_SITE_KEY`);
- **Google reCAPTCHA v3**: la **Chiave Sito** del punteggio
  (`g_recaptcha_score_site_key`, env `G_RECAPTCHA_SCORE_SITE_KEY`) e il
  **Badge** (`g_recaptcha_badge`, env `G_RECAPTCHA_BADGE`, `true` di base), che
  mostra o nasconde il badge di Google; nascosto, sotto il riquadro compare la
  riga di attribuzione con i link a privacy e termini di Google (obbligatoria).

Non c'è una chiave segreta: la verifica passa da reCAPTCHA Enterprise con l'ID
progetto e la chiave API di Google Cloud Platform. La colonna
`g_recaptcha_secret_key` resta nella tabella ma il backend non la mostra.

In modalità v3 la cella del form che contiene il riquadro resta vuota. Il
componente stampa una volta per pagina una regola che toglie dal flusso la cella
marcata con `wi-recaptcha-cell` finché è vuota (`position:absolute`, larga e alta
zero, con `!important` contro le utility della lib come `w-100`), così la griglia non le dà il gap sopra e sotto. La cella torna al suo
posto da sola quando compare la casella o la riga di attribuzione. Niente
`display:none`: la lib carica Google quando il riquadro entra nel viewport, e un
elemento senza box non ci entra mai.

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

**Fail-open e fail-closed.** Se l'engine lancia o Google non risponde davvero
(rete, 200 senza JSON, 429, 5xx, 4xx diversi da 400, 401 e 403) la verifica si
**salta** con una riga di log (`skipped: true`). Con `->failClosed()` in
produzione dà invece `617` con log `ERROR`: lo usano le cinque azioni
dell'auth frontend (`AuthController`): login (`login`), registrazione
(`signup-request`), completamento della registrazione (`signup-completion`),
recupero password (`password-recovery`) e reimpostazione della password
(`password-restore`). Form contatti, checkout e gli altri
`Resource::verifyRecaptcha()` restano fail-open. Non passano mai: token o
azione assenti, tipo senza chiave, token non valido per Google, HTTP 400.

**401/403 = configurazione.** Google che risponde 401 o 403 vuol dire chiave
API o progetto GCP sbagliati (o API Enterprise non abilitata): `617` con
`reason: config` e log `ERROR`, mai salto, con o senza `failClosed()`.

**In locale si salta.** Con `APP_ENV=local` la verifica si salta sempre, prima
di leggere il token, con una riga `INFO` `reason: local_env`: i login su
`*.test` funzionano anche con le chiavi GCP valorizzate e senza widget.
`APP_ENV` assente o con un valore diverso da `local` vale `production`.
Attenzione: `APP_ENV=local` su un server di produzione disattiva il reCAPTCHA
su tutte le azioni e moltiplica per 10 i limiti di `LoginThrottle`.

**Senza chiavi in produzione.** Senza chiave del sito per il tipo richiesto la
verifica fallisce con `invalid` (engine non chiamato): le cinque azioni
dell'auth frontend rispondono sempre `617`. Le chiavi sono un requisito del
deploy (vedi [Installazione e deploy](../piattaforma/installazione-e-deploy.md)).

**Credenziali server.** La verifica usa reCAPTCHA Enterprise: `GCP_PROJECT_ID`
e `GCP_API_KEY` (chiave API Google Cloud con reCAPTCHA Enterprise API
abilitata), più le chiavi del sito `G_RECAPTCHA_SITE_KEY` /
`G_RECAPTCHA_SCORE_SITE_KEY`. `G_RECAPTCHA_SECRET_KEY` (chiave segreta della
verifica classica `siteverify`) non è usata da nessuna verifica: è
**deprecata**, ancora letta dalle credenziali per compatibilità, e verrà
rimossa.

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
| `invalid` | token oltre 8192 byte o con caratteri fuori da ASCII stampabile (`malformed`), oppure tipo senza chiave configurata (engine non chiamato in tutti e due i casi), oppure Google risponde `valid = false` | `617` | `WARNING` |
| `action_mismatch` | action ricevuta ≠ action attesa (engine non chiamato), oppure action di Google ≠ attesa (`source: google`) | `617` | `WARNING` |
| `low_score` | tipo `score`, punteggio sotto il minimo o assente | `618` con la chiave a casella, altrimenti `617` | `WARNING` |
| `config` | Google risponde 401 o 403 (chiave API o progetto) | `617` | `ERROR` |
| `engine_unavailable` | Google non risponde davvero (`http_code` nel log) | salta; `617` con `failClosed` in produzione (`fail_closed: true`) | `WARNING` (0, 200 senza risposta, 429, 5xx), `ERROR` (ogni altro codice sotto 500: 404…; il 400 va a `invalid`, 401/403 a `config`); `ERROR` con `failClosed` |
| `engine_error` | l'engine lancia un'eccezione (loggata l'originale) | salta; `617` con `failClosed` in produzione | `ERROR` |
| `local_env` | `APP_ENV=local` | salta | `INFO` |

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
e Google), fail-open (eccezione, 429, 5xx, rete, 4xx), fail-closed in
produzione (eccezione e Google non disponibile → `617`), 401/403 come errore
di configurazione, salto con `APP_ENV=local`, `valid=false`, HTTP 400,
v3 col punteggio alto e basso, la sfida `618` e il suo segno in sessione,
`passes()`, action vuota (BC) e la derivazione dell'action da `formSchema()`.
L'engine è iniettato (fake) → nessuna HTTP reale; la classificazione della
risposta di Google è provata a parte con una `Call` finta.
