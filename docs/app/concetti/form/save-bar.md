---
icon: floppy-disk
---

# Barra di salvataggio

## Cos'è

Nei form del backend, quando i bottoni di salvataggio escono dallo schermo, le
loro copie compaiono in un'isola fissa in basso. Se il form ha modifiche non
salvate, l'isola mostra "Operazione non salvata" e l'uscita dalla pagina chiede
conferma.

Il comportamento, JS e CSS, sta tutto in `wonder-image/lib`. L'app dichiara
solo gli attributi sul `<form>`. Senza JS, o in un browser senza le API che
servono alla lib, restano i bottoni originali.

## Come si dichiara

| Attributo | Dove | Significato |
|---|---|---|
| `data-wi-save-bar` | `<form>` | attiva l'isola per il form |
| `data-wi-save-bar-dirty` | `<form>` | il form nasce "non salvato"; conta la presenza, non il valore |
| `data-wi-save-bar-ignore` | bottone, campo o contenitore | il bottone non si copia; i campi dentro non contano |
| `data-wi-save-bar-cancel` | `<a href>` | l'isola mostra "Annulla" prima dei bottoni copiati, con lo stesso `href`; senza attributo non c'è |
| `data-wi-save-bar-hide-when-open` | qualsiasi elemento nel `body` | finché l'elemento esiste l'isola si nasconde (popup di widget terzi) |

### Annulla

Il chevron "indietro" di `layout/backend/form.php` (`$BACK_URL`) porta
`data-wi-save-bar-cancel`: la lib ne riusa l'`href` per il bottone Annulla, che
sta alla sinistra dei bottoni di salvataggio (i bottoni stanno sempre a
destra). Senza `$BACK_URL` non c'è chevron e quindi nemmeno Annulla. Il link è
una normale navigazione: con modifiche non salvate scatta l'avviso di uscita.
Altre pagine possono marcare un loro link allo stesso modo.

### Resource

Le Resource non dichiarano niente: `resource/form.php` mette gli attributi sul
form, sia con `formLayoutSchema()` sia nel ramo legacy. Un form in sola lettura
senza campi modificabili non li riceve; uno in sola lettura parziale sì.

Una vista che usa `ResourceFormLayoutRenderer::render()` in proprio passa gli
attributi con l'opzione `attributes`:

```php
<?php $saveBar = empty($READONLY) || !empty($READONLY_EDITABLE); ?>

<?=\Wonder\Backend\Support\ResourceFormLayoutRenderer::render($FORM_LAYOUT, [
    'action' => (string) ($FORM_ACTION ?? ''),
    'attributes' => ['data-wi-save-bar' => $saveBar, 'data-wi-save-bar-dirty' => $saveBar && !empty($FORM_ERRORS)],
])?>
```

L'opzione passa da `AttributeString::render()`: `true` stampa l'attributo
senza valore, `false` e `null` lo omettono. Le chiavi `id`, `method`,
`enctype`, `action`, `onsubmit` e `class` si ignorano, perché le imposta il
renderer. Un'app più vecchia ignora l'opzione: nessun attributo e nessun
errore.

### Form scritti a mano

L'attributo fisso si scrive letterale, quello condizionale inline:

```php
<form method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()" data-wi-save-bar<?=!empty($ERRORS) ? ' data-wi-save-bar-dirty' : ''?>>
```

Così si attiva anche nei moduli copia-incolla e nei siti legacy, che non
ricevono patch. Il vecchio `preventFormSubmit(form)`, che arma l'avviso di
uscita a ogni apertura e lo toglie all'invio, è superato: con
`data-wi-save-bar` l'avviso parte solo se ci sono modifiche. Si toglie quando
si aggiunge l'attributo.

Restano senza attributo i form che non salvano un record: login, recupero,
ripristino e impostazione della password, "Esegui update" in home, filtri GET,
dashboard dello scheduler ed eliminazione riga di `ScheduleResource`,
`Button::post`, sql-download, demo frontend in `app/build/src/docs/*` e
caricamento massivo dei media.

## Stato iniziale sporco

`data-wi-save-bar-dirty` si mette quando la pagina ridisegna valori che non
sono stati salvati, cioè dopo un POST fallito:

| Pagina | Condizione |
|---|---|
| Resource e scheduler | `!empty($FORM_ERRORS)` |
| Account, form profilo | `$PROFILE_DIRTY`: POST `modify` che non ha scritto |
| Gestione utenti | `$SAVE_BAR_DIRTY`: stessa regola |
| File di configurazione | `!empty($ERRORS)` |

Il segnale è la scrittura mancata, non l'ALERT. `user()` restituisce il campo
`written`: `false` di default, `true` subito dopo l'insert o l'update della
riga `user`. Un ALERT può arrivare anche dopo una scrittura riuscita (hook,
consensi, mail) e un errore può non avere un codice riconoscibile.

Mai un valore vuoto: la lib guarda la presenza, quindi
`data-wi-save-bar-dirty=""` segna il form sporco. L'attributo si legge solo
all'avvio; `reset()` e un invio non annullato azzerano lo stato senza toglierlo
dal DOM.

## Submit da script e AJAX

```js
// Invio da script: form.submit() non genera l'evento submit.
window.wiSaveBar?.reset(form);
form.submit();

// Salvataggio AJAX: nella callback di successo.
window.wiSaveBar?.reset(form);
```

Senza `reset()`, un invio da script fa partire l'avviso di uscita. Dopo un
salvataggio AJAX, le modifiche fatte durante la richiesta risultano salvate.

| Membro | Uso |
|---|---|
| `wiSaveBar.changed(elOrForm?)` | un valore è stato scritto senza evento: ricontrolla tutti i form tracciati |
| `wiSaveBar.reset(form)` | lo stato attuale diventa il punto di partenza |
| `wiSaveBar.isDirty(form)` | stato attuale del form |
| `wiSaveBar.absorb(el)` | i valori dentro `el` diventano il punto di partenza, il resto no |
| `wiSaveBar.labels` | `{ group, unsaved, confirmOther }`, sovrascrivibili prima dell'avvio |
| evento `wi:save-bar:change` | sul form, `detail.dirty`, solo al passaggio pulito/sporco |

`absorb(el)` serve solo per scritture non fatte dall'utente, come una
precompilazione AJAX: sul contenitore più stretto che contiene i campi scritti,
mai sul form intero e mai dopo una modifica dell'utente.

## Come funziona

- `setUpPage()` avvia la barra con `setUpSaveBar()`. Una pagina senza form
  tracciati non crea niente.
- La lib fotografa ogni form tracciato con `new FormData(form)`, cioè quello
  che il salvataggio spedirebbe. Il form è sporco se ha `-dirty` o se la foto
  di adesso è diversa da quella di partenza: annullare a mano una modifica lo
  riporta pulito.
- Finché l'utente non tocca la pagina (tasto, clic, tocco, incolla), ogni
  differenza diventa il nuovo punto di partenza: le riscritture dei widget
  all'avvio non contano.
- I campi dentro `.modal` e `[data-wi-save-bar-ignore]` non contano.
- Si copiano gli elementi del form con `type="submit"` o classe `.wi-submit`,
  compresi quelli collegati con `form="id"`, tranne quelli dentro `.modal`,
  `.offcanvas` e `[data-wi-save-bar-ignore]`. La copia chiama `click()`
  sull'originale, che parte con il suo `name`, il suo `onclick` e gli handler
  delegati.
- Un'isola per pagina, legata all'ultimo form tracciato usato. L'etichetta
  segue quel form; l'avviso di uscita scatta se un qualsiasi form tracciato è
  sporco.
- In fondo alla pagina la lib riserva lo spazio `--wi-save-bar-reserve`, che
  `header.php` del backend sottrae dall'altezza minima del contenuto.

La descrizione completa, con stati, fascia e token CSS, è in
`wonder-image/lib`, `docs/javascript/save-bar.md`.

## Vincoli

- **Copie disabilitate come l'originale.** `check()` disabilita Salva finché
  manca un campo obbligatorio, e la copia lo segue.
- **Più form in una pagina.** Inviare un form che lascia la pagina mentre un
  altro form tracciato è sporco chiede conferma (`labels.confirmOther`). Vale
  anche per i form non tracciati, come la password dell'account. Con un
  `Button::post` che ha la sua `confirm()` le domande sono due: è accettato.
- **EditorJS** segnala le modifiche dopo circa 400ms: uscire prima non dà
  l'avviso.
- **Password e segreti.** Una password in un form tracciato che non è la
  credenziale di login dell'utente dichiara `->autocomplete('new-password')`,
  come i segreti di `SecurityResource`; altrimenti il browser ci inserisce la
  password salvata e il form risulta modificato. `data-wi-save-bar-ignore`
  serve solo alla conferma della propria password, nell'account.
  `new-password` non è il default di `password()`: va dichiarato dove serve.
- **Un Submit `upload` nel layout sostituisce il Salva di default.** Se
  `formLayoutSchema()` contiene già un `Submit` con name `upload`, il footer
  non aggiunge il suo. Lo decide
  `ResourceFormLayoutRenderer::hasSubmit($form, $name = 'upload')`: entra in
  Container, Card e Accordion aperti (`expanded(true)`), non in Modal e
  QuickCreate, e salta i componenti con `visibleWhen()` o `hiddenWhen()`. Un
  Submit in una Card condizionale o in un Accordion chiuso non conta, e il
  Salva di default resta.
- **Niente `autocomplete="off"`.** Firefox, ricaricando la pagina, rimette i
  valori non salvati e l'isola li prende come punto di partenza. È un limite
  accettato.
- **Override di `header.php`.** Un sito che sovrascrive tutto
  `header.php` non sottrae la riserva e ha al massimo 96px di scroll in più.
  Per allinearlo si aggiunge `- var(--wi-save-bar-reserve, 0px)` al
  `min-height` del contenitore della pagina.
- **Override delle viste dei moduli.** Un override identico al pacchetto si
  cancella. Un override personalizzato riceve a mano i due attributi, oppure
  si ripubblica solo quel file, per esempio
  `php forge publish:module immobili pages/backend/immobili/form.php --force`.
  Mai `--force` sull'albero intero: sovrascrive le viste personalizzate.
- **`.offcanvas-{bp}`** non si esclude da solo: sui suoi bottoni serve
  `data-wi-save-bar-ignore`.
- **Aggiornare un sito** alla lib 2.1.2-alpha.17:
  - se `package.json` ha già `wonder-image` in `^2.1.2-alpha.*`, basta
    `composer update`, perché `forge config` esegue `npm install`;
  - se è fuori range (`^2.1.1-alpha.*`, `^2.0.x`, `^2.1.0`), si esegue
    `npm install wonder-image@^2.1.2-alpha.17` e si fa il commit di
    `package.json` e `package-lock.json`, perché la CI usa `npm ci`.

## Dove si trova nel codice

| Elemento | File |
|---|---|
| Isola e stile | `wonder-image/lib`: `src/build/backend/js/form/saveBar.js`, `src/build/backend/css/save-bar.css` |
| Serializzatore degli attributi | `class/App/Support/AttributeString.php` (`render()`) |
| Opzione `attributes` e `hasSubmit()` | `class/Backend/Support/ResourceFormLayoutRenderer.php` |
| Form delle Resource | `app/view/pages/backend/resource/form.php` |
| Scheduler | `app/view/pages/backend/scheduler/form.php` |
| Account | `app/http/backend/account/index.php`, `app/view/pages/backend/account/index.php` |
| Gestione utenti | `class/Backend/Support/UserManagementPageController.php`, `app/view/pages/backend/user/manage.php` |
| File di configurazione | `app/view/pages/backend/config/configuration-file.php` |
| Campo `written` di `user()` | `app/function/user/user.php` |
| Riserva in fondo alla pagina | `app/view/components/backend/layout/header.php` |
