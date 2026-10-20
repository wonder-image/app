---
icon: shield-halved
---

# Token CSRF

## Cos'è

`Wonder\Http\Csrf` è il token CSRF di sessione del framework: lo genera, lo
scrive nei form e nel `<head>`, e lo confronta. Un token solo per sessione,
creato al primo uso.

{% hint style="warning" %}
**La verifica non è centrale.** `RouteDispatcher` verifica il token solo sulle
route che lo dichiarano con `->csrf()` (vedi [Route con `->csrf()`](#route-con-csrf)):
oggi il gruppo delle API backend di sessione (`/api/backend/*`). Le altre
richieste lo chiamano a mano (vedi [Verifica](#verifica)).
{% endhint %}

## API

| Metodo | Cosa fa |
| --- | --- |
| `Csrf::token()` | Il token della sessione; lo crea se manca. |
| `Csrf::field()` | Campo nascosto `_csrf` già valorizzato (`InputHidden`). `Csrf::field('altro_nome')` cambia il nome. |
| `Csrf::verify()` | `true` se la richiesta porta il token giusto. |
| `Csrf::active()` | `true` se il token può finire nell'HTML senza che la pagina lo chieda. |
| `Csrf::fieldFor($method)` | HTML del campo per un form con quel metodo; stringa vuota per `GET`/`HEAD` o se `active()` è falso. |

Costanti: `Csrf::FIELD` (`_csrf`) e `Csrf::HEADER` (`X-WI-CSRF`).

## Dove esce da solo

Non serve scrivere il campo a mano in questi punti:

- il renderer di `Wonder\Elements\Form\Form`, in entrambi i temi;
- `Button::post()`, dentro il form che il bottone genera;
- le voci `method => 'post'` del `Dropdown`, dentro il form di ogni voce;
- `Modal::form()` con metodo `post`, dentro il form della finestra;
- la pagina form delle Resource (`app/view/pages/backend/resource/form.php`),
  sia con layout sia senza;
- i layout `backend.base` e `frontend.base`, che scrivono nel `<head>`:

```html
<meta name="wi-csrf" content="…">
```

Un form con metodo `GET` non riceve mai il campo: il token finirebbe nell'URL.

Il campo si chiama `_csrf` e arriva nel `$_POST` insieme agli altri. Il
salvataggio delle Resource legge solo i campi dello schema, quindi lo ignora.
Un handler di sito che scorre tutto `$_POST` (per esempio per comporre una
mail) lo vede: va escluso lì.

## Dove va scritto a mano

In ogni `<form>` scritto in una vista, se la sua richiesta verrà verificata:

```php
<form method="post" action="<?=e($action)?>">
    <?=\Wonder\Http\Csrf::field()?>
    …
</form>
```

Per una chiamata JavaScript si legge il meta e si manda l'header:

```js
const token = document.querySelector('meta[name="wi-csrf"]')?.content;

fetch(url, { method: 'POST', headers: { 'X-WI-CSRF': token }, body });
```

## Verifica

```php
use Wonder\Http\Csrf;

if (!Csrf::verify()) {
    // rifiuta la richiesta
}
```

`verify()` accetta tre forme:

- senza argomenti: legge `$_POST['_csrf']` e, se manca, l'header `X-WI-CSRF`;
- un array: il corpo della richiesta, da cui legge `_csrf`;
- una stringa: il token da confrontare.

Un token vuoto o assente non è mai valido, nemmeno a sessione appena nata.

## Route con `->csrf()`

Un gruppo o una singola route possono chiedere la verifica al dispatcher:

```php
Route::name('backend.')
    ->prefix('/backend')
    ->backend()
    ->csrf()
    ->group(function () use ($ROOT_APP) {
        Route::post('/delete/', $ROOT_APP.'/http/api/backend/delete.php')->guarded();

        // Solo Bearer: la singola route esce dal gruppo.
        Route::post('/alert/', $ROOT_APP.'/http/api/backend/alert.php')->csrf(false);
    });
```

- Il default è `false`: è un opt-in, non vale per tutte le route.
- `Wonder\Http\CsrfGuard` controlla i metodi diversi da `GET`, `HEAD` e
  `OPTIONS`, dopo la sessione e l'autorizzazione (`authorizeUser()`) e prima
  dell'handler.
- Token mancante o sbagliato: `ForbiddenHttpException`, cioè `403` con il
  payload JSON delle API (`{"success": false, "status": 403, "response": …}`)
  o la pagina d'errore HTML. L'handler non viene incluso.
- Il token arriva da `_csrf` nel corpo o dall'header `X-WI-CSRF`, che la lib
  manda da `ajaxRequest`, `fetch`, `ApiClient`, DataTables ed Editor.js.
  L'header è ammesso in CORS (`Access-Control-Allow-Headers`).
- Le route autenticate solo con Bearer (Resource API, token di pagina, task,
  `/api/app/update/`) e i flussi OAuth non la dichiarano: il browser non manda
  da solo l'header `Authorization`.

Rotte del core con `->csrf()`: tutte quelle sotto `/api/backend/` tranne
`/api/backend/alert/` (`list/table`, `change/boolean`, `active`, `visible`,
`delete`, `authority`, `move`, `file/delete`, `file/move`, `delete-icon`,
`oauth/revoke`, `editorjs/file`, `editorjs/image`).

## Pagine backend del core

Le pagine backend con un POST che scrive verificano il token prima di
scrivere:

| Pagina | Senza token valido |
|---|---|
| CRUD delle Resource (`ResourcePageController`: `store`, `update`) | il form torna con i valori inviati e l'alert `929` |
| Eliminazione da pagina (`POST /{id}/delete/`) e pagina-form (`submit`) | redirect con `FlashAlert::code(929)` |
| Utenti (`UserManagementPageController`: creazione e modifica) | il form torna con l'alert `929` |
| Account (`/backend/account/`: profilo e password) | alert `929`, nessuno dei due form scrive |
| Uploader massivo, file di configurazione | alert `929`, nessun file salvato |
| `POST /backend/` (home) | alert `929`; il POST non scrive comunque nulla |
| Form legacy che includono `app/html/backend/index.php` (`upload`, `upload-add`) | alert `929`, nessun `sqlInsert`/`sqlModify`, nessun redirect |
| Creazione rapida (`backend.resource.quick-create`) | `403` JSON, il controller non viene chiamato |

Il codice `929` («Richiesta non valida») è in `notifications.json` e si
mostra con il meccanismo di alert esistente (`$ALERT` o `FlashAlert`). I form
di queste pagine portano già `_csrf` (`Csrf::field()` o `Csrf::fieldFor()`).
Login e recupero password del backend non sono in questo elenco.

Il form legacy lo scrive il sito, non il core: deve portare
`<?=\Wonder\Http\Csrf::field()->render()?>` dentro `<form>`.

## Sessione e cache

Il token è personale: non deve finire in HTML servito a più persone.

- Senza sessione attiva il campo e il meta non vengono emessi. Succede nelle
  pagine servite prima del bootstrap (per esempio la pagina di errore HTTP) e
  da riga di comando.
- Ogni pagina instradata apre la sessione, e PHP risponde con
  `Cache-Control: no-store, no-cache`: nessun proxy la conserva.
- Se una pagina dichiara la risposta condivisibile con
  `session_cache_limiter('public')`, l'emissione automatica si spegne.

{% hint style="danger" %}
Una CDN configurata per mettere in cache anche l'HTML ignorando
`Cache-Control` ("cache everything") servirebbe a tutti il token del primo
visitatore. Il framework non può accorgersene: le pagine con un form o con il
meta vanno escluse da quella regola.
{% endhint %}

## Token già esistenti

| Token | Stato |
| --- | --- |
| `AuthSession::csrfToken()` / `verify()` | Delega a `Csrf`: stesso token, stessa chiave di sessione, campo `csrf_token` invariato. |
| `_contact_csrf` (pannelli Contatti) | Invariato; usa già lo stesso token tramite `AuthSession`. |
| Scheduler (azioni backend) | Usa `Csrf`: `toggle`, `run` e il form verificano `Csrf::verify()`. |
| `Impersonation` | Invariato: token per scopo, cancellato alla fine dell'impersonazione. |

## Riferimenti

- `class/Http/Csrf.php`
- `class/Http/CsrfGuard.php`
- `tests/Http/CsrfTest.php`
- `tests/Http/BackendApiCsrfTest.php`
- [Auth frontend e pannello account](../utenti/auth-frontend.md)
