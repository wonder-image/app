---
icon: shield-halved
---

# Token CSRF

## Cos'è

`Wonder\Http\Csrf` è il token CSRF di sessione del framework: lo genera, lo
scrive nei form e nel `<head>`, e lo confronta. Un token solo per sessione,
creato al primo uso.

{% hint style="warning" %}
**Oggi il token viene solo emesso.** Nessuna richiesta viene bloccata dal
framework: `RouteDispatcher` non chiama `Csrf::verify()`. Chi vuole proteggere
una propria richiesta lo chiama a mano (vedi [Verifica](#verifica)).
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
| `scheduler_csrf` (Scheduler) | Invariato, con un token proprio. |
| `Impersonation` | Invariato: token per scopo, cancellato alla fine dell'impersonazione. |

## Riferimenti

- `class/Http/Csrf.php`
- `tests/Http/CsrfTest.php`
- [Auth frontend e pannello account](../utenti/auth-frontend.md)
