# PIANO_COMPONENTI — audit del markup ripetuto nei repository Wonder Image

Data: 2026-10-05 · Repository analizzati: `wonder-image/app` (working tree su `main`), `wonder-image/lib` (`ad0bcf3`, 2.1.2-alpha.22), `wonder-image/gestionale` (`e624c45`), `wonder-image/new-site` (`e1ab653`).

Questo documento è solo un piano: nessun file di codice è stato modificato, nessuna PR aperta.

**Revisione 2** — incorpora le decisioni prese dopo la prima lettura (elenco completo in §7). Versioni di destinazione: **app 2.4** e **lib 2.1.2**.

**Come leggere i numeri.** Le occorrenze sono contate con ricerche testuali (`rg`) escludendo `vendor/`, `docs/`, `tests/`, `node_modules/`, `dist/`, `*.md`, `*.json`. Sono ordini di grandezza affidabili, non conteggi al singolo carattere: una riga con due bottoni conta uno, un commento che cita `confirm()` è stato scartato a mano. I percorsi `file:riga` sono stati aperti e verificati, salvo dove indicato.

---

## 1. Sintesi in 10 righe

1. **Modal con form (app + gestionale)** — il gestionale scrive a mano 6 finestre Bootstrap complete di form, bottoni e campi; in app convivono tre modal non imparentate. Estendere `Wonder\Elements\Components\Modal` con `form()`, `submit()`, `cancel()`.
2. **Conferma delle azioni distruttive (lib + app)** — tre meccanismi diversi (`modal('…', url)` JS, `window.confirm`, `Button::confirm()`). Un solo contratto dichiarativo `data-wi-confirm` nella lib, emesso da `Button`/`Dropdown`.
3. **Menu azioni e dropdown (app)** — `Dropdown` esiste ma menu riga, header-actions e filtri costruiscono `dropdown-item` a mano in 13 file. Farli passare tutti dall'Element.
4. **Client HTTP e funzioni JS duplicate (lib)** — tre client (`$.ajax` ×10, `postData`, `ApiClient`) e 9 funzioni con lo stesso nome ma firme diverse tra `frontend/` e `backend/`. Un solo `wi.http` e un solo `wi.toast`/`wi.spinner` in `global/`.
5. **Schede dettaglio, liste-widget e badge (app → gestionale → new-site)** — righe «Etichetta: **valore**» a mano in 4 pagine show e nel boilerplate, 4 widget con lo stesso `list-group`, 10 badge di stato a mano. `DataItem`/`DataList`, nuovo `ActionList`, `Badge`. `<wi-card>` esce di scena: `Card` rende sempre `<div class="card">` e il tag non si aggiunge più.

Due prerequisiti comuni a tutti. Primo: oggi **non esiste un modo uniforme di sovrascrivere markup e classi di un Element** (§3.0); senza quello ogni componente nuovo nasce chiuso e i siti tornano a copiare l'HTML. Secondo: **non esiste una protezione CSRF centrale** (§3.16); va introdotta insieme a `Modal::form()`, `Button::post()` e alle voci POST di `Dropdown`, che sono i punti da cui passeranno tutti i form.

Dove si lavora di più: `app` (componenti e renderer), poi `lib` (JS/CSS), poi il gestionale come primo consumatore, infine `new-site`.

---

## 2. Tabella riassuntiva

| # | Pattern | Occorrenze (file/righe) | Destinazione | Priorità |
|---|---------|------------------------|--------------|----------|
| 0 | Override uniforme di markup e classi degli Element | trasversale | app | **Alta** (prerequisito) |
| 1 | Modal con form scritto a mano | gestionale 5/6 · app 3 modal distinte · lib 2 `modal()` omonime | app (markup) + lib (JS) | **Alta** |
| 2 | Conferma azione distruttiva | app 7/9 · gestionale 2 · lib 1 | lib (JS) + app (attributi) | **Alta** |
| 3 | Menu azioni / dropdown a mano | app 13/27 · gestionale 1/8 | app | **Alta** |
| 4 | Client HTTP, toast e funzioni JS duplicate | lib: `$.ajax` 9/10, 3 client, 9 funzioni doppie | lib | **Alta** |
| 5 | Bottoni a mano e stato «loading» | app 28/64 · gestionale 12/27 · `onsubmit="loadingSpinner()"` ×14 | app + lib | Media |
| 6 | Card, dismissione di `<wi-card>` e liste-widget | `<wi-card>`: app 19 file/47 · gestionale 4/6 · ecommerce 1 · `<div class="card">` a mano: app 12/19 · list-group gestionale 3/11 | app (+ lib per la rimozione) | Media |
| 7 | Scheda dettaglio «etichetta: valore» | app 3 pagine · gestionale 2 Support · new-site 1 | app | Media |
| 8 | Badge di stato a mano | app 8/20 · gestionale 7/10 | app (+ gestionale Support) | Media |
| 9 | Form e campi scritti a mano | app 52/90 `<input` · gestionale 8/48 | app (regola già esistente) | Media |
| 10 | Filtri lista duplicati (legacy ↔ classi) | `filter.php` 1207 righe vs `FilterDate`+`FilterCustom` 491 | app | Media |
| 11 | Script inline di inizializzazione | app 34/44 · gestionale 3/10 · new-site 1 | lib + asset del modulo | Media |
| 12 | Tabelle statiche a mano | gestionale 3 backend · app 3 · servirà a tutti i moduli | app (renderer per `Elements\Table`) | Media |
| 13 | Script Swiper/Gallery duplicati tra temi | 2 coppie di renderer | app (concern) + lib | Bassa |
| 14 | Blocchi ricopiati del boilerplate | new-site: form contatto, 3 pagine legali, header/footer | app (view component) | Media |
| 15 | Paginazione, icone, escape | app 7/35 · 41/104 · 43/153 | app | Bassa |
| 16 | Protezione CSRF centrale (assente) | 4 token indipendenti in app · 0 nel gestionale | app (+ lib per le richieste JS) | **Alta** (prerequisito) |

---

## 3. Pattern

### 3.0 Prerequisito — override uniforme di markup e classi

**1. Descrizione.** Ogni Element espone `class()`, `addClass()`, `attr()`, `style()` (`class/Elements/Component.php:10`), ma il modo in cui il renderer li applica cambia da componente a componente: il concern `RendersComponentAttributes` è usato solo da 4 renderer. Le classi delle parti interne (header della card, footer della modal, voce del dropdown) sono cablate nel renderer. `Themes\Resolver` mappa per namespace e non offre un punto in cui un sito possa sostituire il renderer di un singolo Element (`class/Themes/Registry.php:35` registra solo temi interi).

**2. Occorrenze.** Tutti i renderer sotto `class/Themes/{Wonder,Bootstrap}/Components/*`.

**3. Varianti.** Renderer che applicano `class` alla radice, renderer che lo ignorano, renderer che lo concatenano dopo le classi del tema (attributo duplicato: il browser tiene il primo, come già annotato in AGENTS.md per i Field).

**4. Destinazione.** `app`: è il contratto tra Element e tema.

**5. API proposta.** Tre livelli, dal più leggero al più invasivo:

```php
// a) radice: già esiste, va solo resa uniforme in tutti i renderer
Card::make()->addClass('shadow-sm')->attr('data-x', '1');

// b) parti interne: un metodo dedicato per ogni parte, dichiarato dall'Element
Modal::make('Titolo')->footerClass('justify-content-between')
                     ->bodyClass('p-0')
                     ->bodyAttributes(['data-wi-scroll' => 'true']);

// c) sostituzione del renderer per un Element, per tema, dal sito o da un modulo
Wonder\Themes\Registry::override(
    Wonder\Elements\Components\Modal::class,   // Element
    App\Themes\Bootstrap\Modal::class,         // renderer che estende quello di default
    'bootstrap'
);
```

- **Metodi dedicati, non una chiave stringa** (decisione 1): ogni Element espone `<parte>Class()` e, dove serve, `<parte>Attributes()` solo per le parti che possiede davvero — `Modal`: `dialogClass`, `headerClass`, `titleClass`, `bodyClass`, `footerClass`; `Card`: `headerClass`, `bodyClass`, `footerClass`; `Dropdown`: `toggleClass`, `menuClass`, `itemClass`. L'IDE li suggerisce e una parte inesistente è un errore a tempo di scrittura, non a runtime.
- Le classi passate **si aggiungono** a quelle del tema (stessa semantica di `addClass()`); per toglierle o cambiare struttura si usa il livello c).
- Per non riscrivere la stessa logica in ogni Element: concern interno `Elements\Concerns\HasPartAttributes` con i soli metodi `protected` (`setPartClass()`, `setPartAttributes()`, `partSchema()`); i metodi pubblici dedicati sono deleghe di una riga.
- Concern renderer `Themes\Concerns\RendersPartAttributes` con `partAttributes(string $part, string $themeClasses): string`, fratello di `fieldClass()`/`fieldAttributes()` di `AbstractFieldRenderer`: un attributo scritto una volta sola.
- `Registry::override()` è un punto di estensione **pubblico** (decisione 8), documentato per siti e moduli; è consultato da `Resolver` **prima** della catena per namespace e solo dentro il tema richiesto (nessun fallback tra temi, regola invariata). Il renderer registrato deve estendere quello di default dello stesso tema, altrimenti `InvalidArgumentException`.
- I renderer espongono metodi `protected renderHeader()`, `renderBody()`, `renderFooter()`: chi fa override ridefinisce un pezzo, non tutto.

**6. Esistente.** `RendersComponentAttributes`, `MergesClassAttribute`, `App\Support\AttributeString`, `AbstractFieldRenderer::fieldClass()`.

**7. Impatto.** Tocca tutti i renderer dei Components (circa 20 per tema), ma in modo additivo. Rischio: un renderer che oggi ignora `class()` inizierà ad applicarlo; va verificato che nessun chiamante passi classi «morte». Retrocompatibile.

**8. Priorità.** **Alta**: è la condizione perché i punti seguenti rispettino il requisito «personalizzabile senza riscrivere l'HTML».

---

### 3.1 Modal con form scritto a mano

**1. Descrizione.** Finestra Bootstrap completa (`modal fade` → `modal-dialog` → `modal-content` → header con `btn-close` → body con campi → footer con Annulla/Conferma), racchiusa in un `<form method="post">`, costruita per concatenazione di stringhe.

**2. Occorrenze.**
- gestionale, 6 finestre in 5 file: `src/Resources/Sales/OrderPaymentResource.php:112`, `src/Resources/Sales/OrderNoteResource.php:125`, `src/Resources/Sales/OrderResource.php:279`, `src/Resources/Catalog/ProductResource.php:710`, `src/Resources/Contacts/ContactAddressResource.php:288` e `:318`.
- app, tre implementazioni senza base comune: Element `class/Elements/Components/Modal.php` (renderer Bootstrap 141 righe; renderer Wonder 35 righe, in lavorazione), `class/Backend/Support/QuickCreateModal.php` (452 righe, markup + `FormData` + `fetch` propri), modal globale `#modal` di `app/function/backend/modal.php:3`; più il view component `app/view/components/frontend/overlay/modal.php`.
- lib: due funzioni globali `modal()` con significato diverso — `src/build/backend/js/modal.js:1` (conferma con `ajaxRequest`) e `src/build/frontend/js/modal.js:22` (apre un `wi-modal`).

**3. Varianti.** Footer «Indietro/Salva», «Indietro/Registra» (success), «Indietro/Elimina» (danger), «Annulla/Salva la rettifica»; con o senza icona di aiuto nel titolo (`OrderPaymentResource.php:116`); campi nascosti `order_id`/`back`; trigger scritto a mano con `data-bs-toggle="modal"` (`ContactAddressResource.php:243,256`); in tre punti del gestionale il titolo viene poi riscritto via JS (`ProductModelResource.php:856,7369,8732`, `[data-wi-modal-title]`).

**4. Destinazione.** Markup in `app` (serve a gestionale, ecommerce, pannello account frontend). Apertura/chiusura, focus e riscrittura del titolo in `lib`. Nel gestionale resta solo la definizione dei campi.

**5. API proposta** (estensione di `Modal`, non una classe nuova):

```php
use Wonder\Elements\Components\{Modal, Button};

$modal = Modal::make('Registra pagamento: ordine '.$numero)
    ->id('pay-'.$orderId)
    ->help($aiuto)                                   // icona info nel titolo
    ->form(action: static::submitUrl(), method: 'post', hidden: [
        'order_id' => $orderId, 'back' => $back,
    ])                                               // avvolge body+footer in <form>, aggiunge il token CSRF (§3.16)
    ->components([                                   // FormField o Element qualsiasi
        FormField::key('amount')->price()->required()->columnSpan(6),
        FormField::key('paid_at')->date()->value(date('Y-m-d'))->columnSpan(6),
        FormField::key('payment_method_id')->select($metodi)->columnSpan(6),
        FormField::key('reference')->text()->columnSpan(6),
    ])
    ->cancel('Indietro')                             // default: __t('components.buttons.back')
    ->submit('Registra', variant: 'success');        // default variant: 'primary'

echo Button::make('Registra pagamento')->opensModal($modal->getId());
echo $modal;
```

Default: `size('md')`, centrata, `cancel` e `submit` tradotti, nessun form se `form()` non è chiamato (comportamento attuale).
Override: `dialogClass()`, `headerClass()`, `titleClass()`, `bodyClass()`, `footerClass()` (§3.0); `->footer([...])` continua a sostituire del tutto il footer; `Registry::override()` per cambiare struttura. Il titolo dinamico diventa `data-wi-modal-title` emesso dal renderer.

Lib (decisione 4): nessuna delle due `modal()` attuali tiene il nome. L'API nuova è `wi.modal.open(id, { title })` / `wi.modal.close(id)` per aprire una finestra e `wi.confirm()` per le conferme (§3.2); `modal()` del backend e `modal()` del frontend restano come alias con la firma di oggi.

**6. Esistente.** `Modal` (`make`, `size`, `scrollable`, `components`, `footer`, `frontend`), `Button::opensModal()` (`class/Elements/Components/Button.php:97`) con `Themes\Concerns\RendersButtonModal`, `Auth\Frontend\AccountAddressModal`. Tutto già nella direzione giusta: manca solo il caso «modal = form».

**7. Impatto.** Gestionale: 5 file, circa 200 righe di stringhe sostituite. Rischio medio: i campi oggi hanno `data-wi-check` e id calcolati a mano, da riprodurre tramite `FormField`. `QuickCreateModal` può adottare `Modal` come involucro in un secondo momento; la `#modal` globale resta finché esiste l'alias `modal()` JS (vedi §3.2). Le due `modal()` della lib non vengono rinominate né rimosse ora: diventano alias.

**8. Priorità.** **Alta** — molte righe, alto rischio di incoerenza (etichette dei bottoni già diverse tra finestre), e nessuna delle 6 finestre del gestionale include un token CSRF nel form (in tutto il repo `csrf` non compare mai). È confermato che non esiste una protezione centrale: `Modal::form()` è uno dei tre punti in cui viene introdotta (§3.16), quindi questo passo non è più solo pulizia del markup.

---

### 3.2 Conferma delle azioni distruttive

**1. Descrizione.** Chiedere conferma prima di eliminare/eseguire un'azione POST.

**2. Occorrenze.**
- JS `modal('Sei sicuro…', url)` scritto in un `onclick`: `class/Backend/Table/Field.php:177`, `:205`; `app/function/backend/plugin.php:99`, `:113`.
- `window.confirm`: `class/Themes/Concerns/RendersButtonPostForm.php:26`, `class/App/Resources/Scheduler/ScheduleResource.php:95`, `class/Themes/Bootstrap/Form/Components/Repeater.php:703`; gestionale `src/Resources/Catalog/ProductModelResource.php:4772`, `:6457`.
- Modal di conferma dedicata scritta a mano: gestionale `src/Resources/Contacts/ContactAddressResource.php:318`.
- API dichiarativa già presente: `Button::confirm()` (`class/Elements/Components/Button.php:83`).

**3. Varianti.** Conferma nativa del browser contro modal Bootstrap; azione via `ajaxRequest(link)` con ricarica pagina contro submit di un form POST; testi liberi e non tradotti.

**4. Destinazione.** Comportamento in `lib` (JS puro, un listener delegato). In `app` solo l'emissione degli attributi.

**5. API proposta.**

```html
<!-- contratto lib: funziona su <a>, <button>, <form> -->
<button data-wi-confirm="Eliminare l'indirizzo?"
        data-wi-confirm-title="Attenzione"
        data-wi-confirm-ok="Elimina" data-wi-confirm-variant="danger"
        data-wi-confirm-cancel="Indietro">…</button>
```

```js
wi.confirm({ text, title, ok, cancel, variant }) // → Promise<boolean>
wi.confirm.setRenderer(fn)                        // override del markup della finestra
```

```php
Button::post($url, 'Elimina')->confirm('Eliminare l\'indirizzo?', title: 'Attenzione', ok: 'Elimina', variant: 'danger');
Dropdown::make('Azioni')->item('Elimina', $url, ['method' => 'post', 'confirm' => '…']);
```

Default: titolo, «Conferma» e «Annulla» dal `TranslationProvider` della lib; `variant` `danger` se il metodo è POST/DELETE. Backend → modal Bootstrap, frontend → `wi-modal`; dove nessuna delle due è disponibile, ripiego su `window.confirm`. Override: classi tramite `data-wi-confirm-class`, markup tramite `setRenderer`.

**6. Esistente.** `Button::confirm()`, `RendersButtonPostForm`, `modal()` backend della lib: quest'ultima diventa un wrapper deprecato attorno a `wi.confirm` + `wi.http`.

**7. Impatto.** lib: un file nuovo più l'adattatore. app: 5 punti. Gestionale: 3 punti. Rischio basso se `Button::confirm()` mantiene la firma attuale (i nuovi parametri sono opzionali e nominati). `modal(text, link, …)` va mantenuta per i siti esistenti.

**8. Priorità.** **Alta** — riguarda azioni irreversibili; oggi l'utente vede tre finestre diverse a seconda della pagina.

---

### 3.3 Menu azioni e dropdown a mano

**1. Descrizione.** `<ul class="dropdown-menu">` con `<li><a class="dropdown-item">` costruiti per stringa, nonostante esista `Dropdown::make()->item()`.

**2. Occorrenze.** app, 13 file: `class/Backend/Table/Field.php:261-264`, `:314-315`, `:366`; `class/Backend/Table/Table.php:670`, `:673`; `class/Backend/Table/BooleanBadge.php:196`; `class/App/Resources/Scheduler/ScheduleResource.php:90-97`; `app/view/layout/backend/partials/header-actions.php:37` (con 15 `htmlspecialchars` e attributi composti a mano); `app/function/backend/plugin.php:38`; `app/function/backend/filter.php:151`, `:181`; `class/Backend/Filter/FilterDate.php:104`. Gestionale: `src/Resources/Catalog/ProductModelResource.php:4478-4495`, `:6246-6269`.

**3. Varianti.** Voce link, voce bottone con `data-*`, voce form POST con conferma, divisore, testo informativo (`dropdown-item-text`), voce attiva/disabilitata, trigger a tre puntini o bottone tratteggiato a larghezza piena.

**4. Destinazione.** `app`. Nessun JS nuovo: Bootstrap gestisce già l'apertura.

**5. API proposta** (estensione di `Dropdown`):

```php
Dropdown::make('Azioni')
    ->icon('bi-three-dots')->align('end')          // default: 'start'
    ->item('Modifica', $editUrl, ['icon' => 'bi-pencil'])
    ->item('Esegui ora', $runUrl, ['method' => 'post', 'confirm' => 'Eseguire?'])   // nuovo: voce POST con token CSRF (§3.16)
    ->action('Aggiungi', ['data-wi-option-add' => $id])                              // nuovo: voce <button> senza href
    ->text('Una caratteristica nuova si prepara in …')                               // nuovo: dropdown-item-text
    ->divider()
    ->item('Elimina', $delUrl, ['method' => 'post', 'confirm' => '…', 'variant' => 'danger']);
```

Override: `menuClass()`, `itemClass()`, `toggleClass()`; `->toggle(Button $button)` per sostituire del tutto il trigger.

**6. Esistente.** `Dropdown` (`item`, `button`, `divider`, `header`), `TableColumn::actions()`, `TableLayoutSchema::buttonCustom(Button|Dropdown)`, `PageActionNormalizer`.

**7. Impatto.** `Field.php` e `header-actions.php` sono sul percorso di ogni lista backend: rischio medio, da coprire con un confronto dell'HTML prima/dopo. Nessun cambio di API pubblica (`actions()` continua ad accettare array).

**8. Priorità.** **Alta** — componente esistente ma aggirato proprio dal codice del framework; chi legge `Field.php` impara il pattern sbagliato.

---

### 3.4 Client HTTP, toast e funzioni JS duplicate (lib)

**1. Descrizione.** Stessa funzione implementata due volte, in `frontend/` e in `backend/`, con firme diverse; tre modi di fare una richiesta.

**2. Occorrenze.**
- Client: `$.ajax` in 9 file (`src/build/backend/js/ajax.js:5`, `backend/js/list.js:75`, `backend/js/form/input.js:214`, `backend/js/form/file.js:14`, `backend/js/form/set.js:64`, `backend/js/form/place.js:28`, `frontend/js/form/list.js:112`, `frontend/js/form/place.js:200`, `frontend/js/form/send.js:80,113`), ognuno con lo stesso blocco `error: function (XMLHttpRequest) { ajaxRequestError(XMLHttpRequest); }`; `postData()` in `src/build/global/js/fetch.js:1`; `src/class/ApiClient.js`.
- Funzioni omonime: `alertToast` (`backend/js/alert.js:24` con 4 parametri, `frontend/js/alert.js:27` con 1), `modal` (§3.1), `loadingSpinner` (`backend/js/utility.js:35` `(show)`, `frontend/js/utility.js:1` `(action='toggle')`), `check`, `searchStates`, `formUpload`, `setUpPage`, `setInput`, `checkInput`.
- Markup generato da JS: 79 `innerHTML`/template in 20 file.

**3. Varianti.** Backend su jQuery + Bootstrap (Toast, Modal), frontend su componenti `wi-*`; endpoint `/backend/alert/` contro `/frontend/alert/`; contenitori `toastContainer` contro `alertContainer`.

**4. Destinazione.** `lib`, cartella `global/` più adattatori per tema.

**5. API proposta.**

```js
wi.http.get(url, params)
wi.http.post(url, body, { spinner: true, onError: wi.http.defaultError })  // FormData o oggetto
wi.toast({ type: 'success', title, text, timeout: 5000 })
wi.toast.fromCode(649)                    // ex alertToast(alert): chiede il testo al server
wi.spinner.show() / .hide() / .toggle()
wi.ui.use('bootstrap' | 'wonder')         // sceglie l'adattatore; il tema è già noto alla pagina
wi.toast.setRenderer(fn)                  // override del markup
```

`wi` è un oggetto globale (decisione 2), coerente con il resto della lib che espone solo funzioni globali; nessun modulo ES da importare. `ajaxRequest`, `postData`, `alertToast`, `loadingSpinner` restano come alias sottili con la firma attuale, marcati `@deprecated` nel JSDoc: saranno rimossi a tempo debito, senza una data fissata ora (decisione 7), quindi nessun avviso in console e nessuna scadenza nei changelog finché non viene decisa.

`wi.http` aggiunge da solo il token CSRF a ogni richiesta non-GET verso lo stesso dominio (§3.16), e lo fanno anche gli alias, perché ci passano sopra.

**6. Esistente.** `ApiClient` è la base naturale di `wi.http` (aggiungere gestione `FormData`, codici 800/802/803/911 oggi in `postData`, e `ajaxRequestError`).

**7. Impatto.** Circa 15 file della lib. Rischio medio-alto se fatto in un colpo: procedere funzione per funzione, tenendo gli alias; ogni sito consuma la lib compilata, quindi un errore si propaga ovunque al primo `npm update`. Nessuna modifica richiesta ai siti finché gli alias restano.

**8. Priorità.** **Alta** — è la base su cui poggiano conferme, toast e form; ogni nuova funzionalità oggi va scritta due volte.

---

### 3.5 Bottoni a mano e stato «loading»

**1. Descrizione.** `<button class="btn …">`/`<a class="btn …">` scritti per stringa; form che mostrano lo spinner con `onsubmit="loadingSpinner()"`.

**2. Occorrenze.** app 28 file/64 righe, tra cui `app/function/backend/filter.php:149,179,194,209,318,733,1078,1113`, `app/function/backend/plugin.php:51`, `app/view/pages/backend/resource/form.php:20`, `app/view/pages/backend/media/upload-massive.php:11`, `app/function/backend/modal.php:16-17`. Gestionale 12 file/27 righe: `src/Backend/Widgets/SetupWidget.php:62`, `AttentionWidget.php:94,128`, `ContactsWidget.php:88`, `LowStockWidget.php:125`, `src/Resources/Stock/StockMovementResource.php:215`, `src/Resources/Catalog/ProductModelResource.php:1615`, `src/Resources/Sales/OrderReturnTableResource.php:130`. `onsubmit="loadingSpinner()"`: circa 14 form (`resource/form.php:52`, `scheduler/form.php:9`, `class/Backend/Support/ResourceFormLayoutRenderer.php:32`, `class/Backend/Filter/FilterCustom.php:53`, `FilterDate.php:154`, pagine `account/*`).

**3. Varianti.** Link, submit, bottone dentro mini-form POST (`ContactAddressResource.php:270`, `OrderReturnTableResource.php:130`), icona + testo, `btn-sm`, `text-nowrap`.

**4. Destinazione.** Markup: `app` (`Button` esiste). Stato loading: `lib`.

**5. API proposta.**

```php
Button::to($url, 'Apri')->variant('secondary')->size('sm')->nowrap();
Button::post($url, 'Annulla reso')->size('sm')->variant('outline-danger');
Button::make('Salva')->type('submit')->icon('bi-check')         // nuovo: icon()
      ->loading();                                              // nuovo: data-wi-loading
Form::make()->loading();                                        // nuovo: sostituisce onsubmit="loadingSpinner()"
```

Lib: `data-wi-loading` su un bottone lo disabilita e mostra lo spinner al click/submit; su un form mostra lo spinner globale. `data-wi-loading="button|page"` (default `button` sui bottoni, `page` sui form). Override: `data-wi-loading-text`, `Button::spinnerClass()`.

**6. Esistente.** `Button` con `variant/outline/size/disabled/block/nowrap/arrow`, `Button::post()`, `Form`.

**7. Impatto.** Sostituzione meccanica, basso rischio, fattibile file per file. Non toccare `filter.php` (vedi §3.10).

**8. Priorità.** Media — tante occorrenze ma cambiano di rado e l'incoerenza è soprattutto estetica.

---

### 3.6 Card, dismissione di `<wi-card>` e liste-widget

**Decisione presa.** `<wi-card>` non si usa più: da ora **non se ne aggiungono di nuovi**, e `Card` rende sempre e solo `<div class="card">`, senza opzione per cambiare tag.

**1. Descrizione.** Contenitori scritti a mano in tre forme: `<div class="card">`; il tag `<wi-card class="col-…">`; e, nei widget della home, un elenco `list-group` con testo a sinistra e bottone a destra. `<wi-card>` non è un vero custom element: la lib lo nasconde via CSS (`src/build/backend/css/header.css:227`, `wi-card { display: none; }`) e a pagina caricata `createCard()` (`src/build/backend/js/pageSetUp.js:1-21`) ne sposta il contenuto dentro `<div class="card border"><div class="card-body row g-3">` e rimette visibile il tag. Quindi la card esiste solo dopo l'esecuzione del JS, il contenuto viene riscritto con `innerHTML` (i listener già agganciati si perdono) e il markup reale non è leggibile nel sorgente PHP.

**2. Occorrenze.**
- `<wi-card>`, app: 19 file, 47 tag di apertura. View: `app/view/pages/backend/resource/form.php` (5), `user/manage.php` (5), `log/email/show.php` (5), `log/consent/show.php` (5), `config/sql-error/show.php` (4), `home.php` (3), `account/index.php` (3), `log/auth-users/show.php` (3), `resource/list.php`, `media/upload-massive.php`, `account/login.php`, `account/password-recovery.php`, `account/password-restore.php`, `account/password-set.php` (1 ciascuna); layout `app/view/layout/backend/form.php` (2), `show.php` (1). Classi: `class/Backend/Support/ResourcePagePresenter.php`, `class/Backend/Table/Table.php` (1 ciascuna). Helper legacy `app/function/backend/components/card.php` (3: `wiCard()`, `wiCardLink()`).
- `<wi-card>`, gestionale: `src/Backend/Widgets/SetupWidget.php:57`, `AttentionWidget.php:58,123`, `ContactsWidget.php:69`, `LowStockWidget.php:77,103`.
- `<wi-card>`, ecommerce: `view/pages/backend/impersonation.php` (1; il repo non è stato analizzato per il resto).
- `<div class="card">` a mano, app: `app/view/pages/backend/scheduler/dashboard.php:38,64`; `config/configuration-file.php:18,32,47,63,79`; `config/sql-download.php:19,32`.
- `list-group` a mano, gestionale: `SetupWidget.php:57-72`, `AttentionWidget.php:88-102`, `LowStockWidget.php:103-127`.
- Siti già in produzione: numero di `<wi-card>` non noto (ogni pagina backend personalizzata può contenerne).

**3. Varianti.** Card con sola griglia di campi (il caso di `<wi-card>`); card con titolo+icona+sottotitolo; card che contiene una lista; riga lista con titolo, descrizione e bottone; riga «vuota» informativa.

**4. Destinazione.** `app` per il componente: `HomeWidgets` è del framework e i widget di tutti i moduli devono poter comporre gli stessi pezzi. `lib` solo per la rimozione finale di `createCard()` e della regola CSS.

**5. API proposta.**

```php
Card::make()->title('Primi passi')->icon('bi-flag')
    ->description('Manca ancora qualcosa prima di partire.')     // nuovi: title/icon/description
    ->columnSpan(12)
    ->components([
        ActionList::make()->flush()                               // nuovo Element (list-group)
            ->item('Aliquote IVA', 'Nessuna aliquota configurata',
                   Button::to($url, 'Vai')->variant('info')->size('sm'))
            ->empty('Niente da segnalare.'),
    ]);

// sostituto diretto di <wi-card class="col-12">…campi…</wi-card>
Card::make()->columnSpan(12)->grid()->components([...]);          // nuovo: grid() → body "row g-3"
```

Markup: sempre `<div class="card">` + `card-body` (decisione 5). Per sostituire `<wi-card>` senza differenze visive servono due cose che oggi aggiunge il JS: il bordo (`border`) e il corpo a griglia (`row g-3`). Proposta: `Card::grid()` per il corpo a griglia e bordo deciso una volta nel renderer Bootstrap; **da verificare sul renderer attuale** prima di scegliere i default, per non alterare le `Card` già in uso.
Override: `headerClass()`, `bodyClass()`, `footerClass()` su `Card`; `itemClass()`, `itemTitleClass()`, `itemTextClass()` su `ActionList`; `Registry::override()` per la struttura.

Nomi confermati (decisione 3): `ActionList` e `DataList`.

**6. Esistente.** `Card`, `InfoCard`, `MetricCard`, `SectionTitle`, `Container`; `wiCard()`/`wiCardLink()` legacy (`app/function/backend/components/card.php:3,16`) da ricondurre a `Card` come già fatto per `wiCardStats()` → `MetricCard`.

**7. Impatto e migrazione di `<wi-card>`.** In quattro passi, perché i siti esistenti hanno pagine proprie che lo usano:
1. **Subito (regola):** nessun nuovo `<wi-card>` in app, moduli e siti; la regola entra nelle skill (§6) e in AGENTS.md.
2. **app 2.4:** `Card` copre il caso «griglia di campi»; le 19 occorrenze di app passano a `Card`, comprese `ResourcePagePresenter`, `Table` e i layout `form.php`/`show.php`, che sono sul percorso di ogni pagina backend. `wiCard()`/`wiCardLink()` restano come funzioni ma producono `Card`.
3. **Moduli:** gestionale (4 widget, circa 150 righe di heredoc) ed ecommerce (1 view).
4. **A tempo debito:** `createCard()` e la regola CSS escono dalla lib, insieme agli altri alias (decisione 7). Fino ad allora restano, altrimenti le pagine dei siti non ancora migrati mostrerebbero card vuote: con `display: none` senza JS il contenuto sparisce.

Rischio medio, non basso come stimato nella prima versione: i punti 2 toccano tutte le pagine form/lista/show del backend. Attenzione a (a) doppio wrapper `col-*` già segnalato in AGENTS.md per `ResourceFormLayoutRenderer`; (b) selettori CSS/JS che puntano a `wi-card` nei siti (`wi-card .x`, `closest('wi-card')`): da cercare prima di togliere il tag; (c) la save bar e gli script che oggi partono dopo `createCard()` e ne danno per scontato l'ordine.
Vantaggio collaterale: la card è nel markup servito, quindi niente contenuto nascosto fino al JS e niente listener persi.

**8. Priorità.** Media — sale ad Alta per il solo passo 1 (la regola), che non costa nulla e ferma la crescita.

---

### 3.7 Scheda dettaglio «etichetta: valore»

**1. Descrizione.** Pagine di sola lettura che stampano `Etichetta: <b>valore</b><br>` una riga alla volta.

**2. Occorrenze.** app: `app/view/pages/backend/log/email/show.php:31-37`, `log/consent/show.php` (11 `htmlspecialchars`), `log/auth-users/show.php:19-43`. new-site: `custom/view/pages/backend/request/show.php:28-31`, `:42-46`, `:56-61` — copiato in ogni sito. Gestionale: `src/Support/Contacts/CustomerSheet.php:61-135`, `src/Support/Orders/OrderSheet.php:62-99`.

**3. Varianti.** Valore testo, link, badge, valore multilinea; etichetta presa da `Resource::getLabel()`.

**4. Destinazione.** `app`.

**5. API proposta.**

```php
DataList::make()->columns(2)                       // nuovo contenitore di DataItem
    ->item(RequestResource::getLabel('email'), $row['email'])
    ->item('Pagina', Link::to($url, $url)->blank())
    ->fromResource(RequestResource::class, $row, ['full_name', 'email', 'phone']);  // etichette dal labelSchema
```

I valori stringa sono sempre sottoposti a `e()`; per HTML fidato resta `DataItem::html()`. Override: `labelClass()`, `valueClass()`, `->layout('inline'|'stacked'|'table')` (default `inline`).

**6. Esistente.** `DataItem::make($label, $value)` con `html()` — solo tema Bootstrap, usato una sola volta nel gestionale.

**7. Impatto.** 3 view in app, 1 nel boilerplate, 2 classi Support nel gestionale. Rischio basso. Nota su `new-site/custom/view/pages/backend/request/show.php:30-31,38`: email, telefono e messaggio del visitatore entrano in un `RichText` senza escape esplicito, ma `safeFindById()` restituisce valori già sottoposti a escape (decisione 12), quindi **non è un problema di sicurezza**: è solo stile da uniformare. Conseguenza per l'API: `DataList::fromResource()` deve sapere che i valori di una riga «safe» sono già codificati e non applicare `e()` una seconda volta (altrimenti `&amp;amp;`); `item()` con valori grezzi continua a codificare.

**8. Priorità.** Media — poche occorrenze nel framework, ma è il file che ogni nuovo sito copia come modello.

---

### 3.8 Badge di stato

**1. Descrizione.** `<span class="badge text-bg-…">` scritto a mano con il colore scelto da una mappa locale.

**2. Occorrenze.** Gestionale: `src/Support/Orders/OrderSheet.php:168`, `src/Support/Orders/StatusLabels.php:70`, `src/Support/Contacts/CustomerSheet.php:79-80`, `:129`, `src/Resources/Stock/StockLevelResource.php:402`, `src/Resources/Catalog/ProductModelResource.php:1113`, `src/Resources/Sales/OrderItemTableResource.php:137`, `src/Resources/Locations/LocationResource.php:131-132`. app: `class/Backend/Table/BooleanBadge.php:149,161,173`, `app/function/backend/plugin.php:198,201`, `app/function/backend/utility.php:33-36`.

**3. Varianti.** Solo testo, icona + testo, con tooltip, cliccabile (toggle booleano).

**4. Destinazione.** Markup: `app` (`Badge`). Le mappe stato → etichetta/colore degli ordini restano nel gestionale, in `Support\Orders\StatusLabels`, che deve restituire un `Badge` invece di una stringa.

**5. API proposta.**

```php
Badge::make('Attiva')->variant('success')->icon('bi-check')->tooltip('…');
Badge::status($value, [                       // nuovo: mappa valore → [label, variant, icon]
    'paid'    => ['Pagato', 'success'],
    'pending' => ['In attesa', 'warning'],
], default: ['Sconosciuto', 'secondary']);
```

**6. Esistente.** `Badge`, `TableColumn::badge()/status()/*Badge()`, `BooleanBadge`, `ColumnFormatterRegistry`; `returnBadge()` e simili già `@deprecated`.

**7. Impatto.** 7 file nel gestionale, 3 in app. Rischio basso.

**8. Priorità.** Media.

---

### 3.9 Form e campi scritti a mano

**1. Descrizione.** `<input>`, `<select>`, `<textarea>`, `<form>` in stringa, contro la regola «gli input passano sempre da `FormField`».

**2. Occorrenze.** Gestionale, 48 righe in 8 file: `ContactAddressResource.php` (11), `ProductResource.php` (8), `OrderReturnResource.php` (7), `OrderPaymentResource.php:124-134` (7), `OrderReturnTableResource.php` (5), `OrderResource.php` (4), `OrderNoteResource.php` (4), `ProductModelResource.php` (2). app, 90 righe in 52 file: gran parte è legittima (renderer di tema, campi nascosti); i casi reali sono `class/Plugin/Custom/Address/Address.php:254-367` e `app/function/backend/filter.php`.

**3. Varianti.** Campi dentro modal (la maggioranza: spariscono con §3.1), mini-form con solo campi nascosti e un bottone (spariscono con `Button::post()`), righe di tabella con quantità modificabile (`OrderReturnResource.php`).

**4. Destinazione.** Nessun componente nuovo: `FormField`/`Form` in `app` coprono già i casi. Serve solo che `Modal::form()` e `Button::post()->formAttributes()` accettino campi nascosti.

**5. API proposta.** Vedi §3.1 e §3.5. Per le righe con input in tabella: `RepeaterColumn` in sola modifica (`Repeater::fixedRows()`, nuovo flag che toglie aggiungi/rimuovi).

**6. Esistente.** `FormField`, `Form`, `Repeater`, `RepeaterColumn`, `Button::post()`, `Button::formAttributes()` (`Button.php:136`).

**7. Impatto.** Dipende da §3.1: fatto quello, restano `OrderReturnResource` e `OrderReturnTableResource`. Rischio medio sui resi (logica di quantità).

**8. Priorità.** Media (Alta la parte che coincide con §3.1).

---

### 3.10 Filtri lista duplicati

**1. Descrizione.** Due implementazioni dei filtri delle liste backend: funzioni globali in `app/function/backend/filter.php` (1207 righe) e classi `class/Backend/Filter/FilterDate.php` (260) e `FilterCustom.php` (231). Entrambe generano form, dropdown, bottoni e script propri.

**2. Occorrenze.** Form con `onsubmit="loadingSpinner()"`: `filter.php:726`, `:1107`; `FilterCustom.php:53`; `FilterDate.php:154`. Script inline: `filter.php:321`, `:519`, `:1121`. Bottoni: vedi §3.5.

**3. Varianti.** Ricerca, limite, select, radio, data singola/intervallo, filtro con query personalizzata.

**4. Destinazione.** `app`. **Non astrarre il legacy**: `filterDate()` è già `@deprecated` e AGENTS.md chiede solo di tenerlo allineato. Il lavoro è completare le classi e far delegare le funzioni globali.

**5. API proposta.** Una base `Backend\Filter\AbstractFilter` (`name()`, `label()`, `options()`, `parse(array $get)`, `condition()`, `render()`) da cui derivano `FilterDate`, `FilterCustom` e i nuovi `FilterSearch`, `FilterLimit`, `FilterRadio`; il markup dei controlli passa da `Dropdown`, `Button`, `FormField`. `TableLayoutSchema::filter(AbstractFilter $f)` come punto di estensione per moduli; `filterSearch()/filterCustom()/…` restano scorciatoie. Override: `formClass()`, `controlClass()`, `->view('custom.component')`.

**6. Esistente.** `FilterDate`, `FilterCustom`, `TableLayoutSchema::filter*()`, `filterCustomOptions()` come unica sorgente delle opzioni (da preservare).

**7. Impatto.** Alto come superficie (ogni lista backend), quindi a passi: prima la base comune, poi una funzione legacy alla volta ridotta a delega. Le regole di sicurezza di AGENTS.md (whitelist dei valori, escape degli identificatori) devono restare identiche.

**8. Priorità.** Media — duplicazione grande ma stabile; diventa Alta se si prevede di aggiungere nuovi tipi di filtro.

---

### 3.11 Script inline di inizializzazione

**1. Descrizione.** Blocchi `<script>` emessi da PHP per inizializzare un widget, spesso con valori PHP interpolati.

**2. Occorrenze.** app 34 file/44 blocchi: `app/function/backend/filter.php:321,519,1121`; `app/function/backend/plugin/header.php:55`; `app/view/pages/backend/config/configuration-file.php:92`; `config/sql-download.php:52`; `class/Backend/Support/QuickCreateModal.php:151`; `class/Themes/Bootstrap/Components/Modal.php:120`; `class/Themes/Bootstrap/Form/Components/Repeater.php:96,574`; `class/Plugin/Custom/Address/Address.php:329`; `class/Themes/Wonder/Form/Components/File.php:90`; `app/view/components/frontend/overlay/popup.php:72`, `annuncement.php:26`. Gestionale 10 blocchi in 3 file: `src/Resources/Catalog/ProductModelResource.php:729,4502,4901,5057,6046,6275,7044,8268` (file da 9400 righe, in buona parte JavaScript dentro heredoc), `ProductResource.php:750`, `ContactAddressResource.php:342`. new-site: `custom/view/components/frontend/sections/contact-form.php:78-121`.

**3. Varianti.** Init di libreria (Swiper, Fancybox, FilePond, select2); logica applicativa vera (editor delle varianti prodotto); callback di risposta di un form.

**4. Destinazione.** Init generici → `lib`, con il contratto `data-wi-*` già adottato per i date picker. Logica specifica del gestionale → file JS veri sotto `gestionale/resources/assets/` (la cartella esiste ed è vuota), serviti come asset del modulo: **non** è un componente riutilizzabile, è solo codice nel posto sbagliato.

**5. API proposta.**
- Regola: il renderer emette solo `data-wi-<componente>` e, se servono dati, un `<script type="application/json" data-wi-config>` codificato con le flag sicure già usate da `RendersGoogleMap`.
- Lib: `wi.init(root = document)` che inizializza ogni `[data-wi-*]` noto; richiamabile dopo un inserimento AJAX. `wi.register('nome', (el, config) => {…})` per moduli e siti.
- app: `Module\Contracts\ModuleInterface` espone gli asset JS/CSS del modulo (`assets(): array`), caricati solo nelle pagine del modulo.

**6. Esistente.** Contratto `data-wi-*` dei date picker, `setInput()`, `DeferredContent`, `data-wi-save-bar`, `data-wi-tree`, `Dependencies`.

**7. Impatto.** Grande ma frazionabile. Il solo spostamento degli script di `ProductModelResource` in file `.js` non cambia il comportamento e rende il codice analizzabile da linter e CSP. Rischio: ordine di caricamento (script deferiti) e valori PHP oggi interpolati.

**8. Priorità.** Media — Alta per `ProductModelResource` come manutenibilità, ma è una ristrutturazione, non un componente.

---

### 3.12 Tabelle statiche a mano

**1. Descrizione.** `<table class="table table-sm">` costruita per stringa per riepiloghi non paginati.

**2. Occorrenze.** Gestionale: `src/Support/Orders/OrderSheet.php:160`, `:213`; `src/Resources/Sales/OrderReturnResource.php:213`. app: 3 file. (Le tabelle di `view/emails/*.php` sono escluse: vedi §4.)

**3. Varianti.** Totali a due colonne, riepilogo IVA, righe con input.

**4. Destinazione.** `app`: `class/Elements/Table/*` esiste come sola configurazione, senza renderer di tema.

**5. API proposta.** Renderer Bootstrap (e Wonder) per `Elements\Table`: `Table::make()->columns([Column::make('Aliquota'), Column::make('Imposta')->align('end')->numeric()])->rows($rows)->size('sm')->responsive()->footer([...])`. Override: `tableClass()`, `headClass()`, `rowClass()`, `cellClass()`, `Column::format(callable)`.

**6. Esistente.** `Elements\Table\*`, `Backend\Table\Table` (DataTables, altro scopo).

**7. Impatto.** 3 punti nel gestionale. Rischio basso.

**8. Priorità.** Media — le occorrenze di oggi sono poche, ma è confermato che `Elements\Table` e `ActionList` serviranno a tutti i moduli (decisione 13): conviene averli prima che ecommerce e gli altri moduli scrivano la loro copia. Passa dalla fase 5 alla fase 2.

---

### 3.13 Script Swiper e Gallery duplicati tra temi

**1. Descrizione.** I renderer Wonder e Bootstrap dello stesso media contengono lo stesso script di init.

**2. Occorrenze.** `class/Themes/Wonder/Media/Swiper.php` (160 righe) e `class/Themes/Bootstrap/Media/Swiper.php` (165): 16 righe di differenza. `Gallery.php` 108 contro 109, con `Fancybox.bind` inline in entrambi (`Wonder:78`, `Bootstrap:68`).

**3. Varianti.** Solo le classi del wrapper.

**4. Destinazione.** Concern condiviso in `app` (`Themes\Concerns\RendersSwiper`, `RendersGallery`), sul modello di `RendersGoogleMap` e `RendersButtonLightbox`; init in `lib` tramite `data-wi-swiper`/`data-wi-gallery` (§3.11).

**5. API proposta.** Nessuna API pubblica nuova: `Swiper` e `Gallery` restano identici per chi li usa.

**6. Esistente.** `RendersGoogleMap`, `RendersButtonLightbox`.

**7. Impatto.** 4 file. Rischio medio. AGENTS.md impone che il render di default dei Media resti identico byte per byte; spostare lo script nella lib lo cambia. **Deciso (6): è accettabile in una minor.** Va quindi fatto in app 2.4 insieme alla lib 2.1.2, con tre accortezze: riscrivere quella frase di AGENTS.md e di `docs/app/elementi/responsive-media.md` (la garanzia resta sul wrapper di colonna, non sullo script); voce esplicita nel changelog sotto Changed; `wi.init()` richiamabile per i contenuti inseriti via AJAX, che oggi si inizializzano da soli grazie allo script inline.

**8. Priorità.** Bassa.

---

### 3.14 Blocchi ricopiati del boilerplate (`new-site`)

**1. Descrizione.** File che ogni nuovo sito riceve in copia e che quindi divergono subito.

**2. Occorrenze** (1 copia × numero di siti):
- `custom/view/components/frontend/sections/contact-form.php` (121 righe): form + 40 righe di JS per interpretare la risposta (`:78-121`).
- `custom/view/pages/frontend/legal/privacy-policy.php`, `terms-conditions.php` (struttura identica, cambia la chiave) e `cookie-policy.php` (stessa pagina, sorgente dati diversa).
- `custom/view/components/frontend/layout/header.php` (hamburger a 5 barre, nav desktop e mobile), `footer.php`, `minimal/footer.php`, `ui/nav-item.php`, `ui/nav-link.php`.
- `custom/view/pages/backend/request/show.php` (vedi §3.7).

**3. Varianti.** Header e footer sono per natura diversi in ogni sito; form contatto e pagine legali no.

**4. Destinazione.** `app`, come view component sovrascrivibili (`View::component()` cerca già prima in `custom/view/components`). JS della risposta del form in `lib`.

**5. API proposta.**

```php
// pagine legali: una sola view nel framework
View::component('frontend.sections.legal-document', ['key' => 'privacy_policy']);

// form contatto: il sito sceglie campi e layout, non riscrive JS
Form::make()->resource(RequestResource::class, ['name','surname','phone','email','request','accept_privacy_policy','recaptcha'])
    ->submitTo('api.resource.requests.store')     // nuovo: data-wi-form-endpoint
    ->successMessage(__t('notifications.649.text'));
```

Lib: `loadingResponse()` (`frontend/js/form/send.js:163`) impara a leggere `response.errors` e `response.message`, così `contactFormSubmitResponse`/`contactFormErrorMessage` spariscono dal boilerplate. `nav-item`/`nav-link` passano in `app/view/components/frontend/ui/` (accanto a `button.php`); header e footer restano nel sito.

Override: essendo view component, il sito li copia in `custom/` solo se deve cambiarli; le classi arrivano come argomenti (`cssClass` esiste già in `nav-item`).

**6. Esistente.** `View::component()`, `Resource::getInput()`, `formSubmit()` e `loadingResponse()` nella lib, `Submit`.

**7. Impatto.** I siti già creati non cambiano (hanno la loro copia). I nuovi partono con meno file. Rischio basso.

**8. Priorità.** Media — poche righe, ma moltiplicate per ogni progetto.

---

### 3.15 Paginazione, icone, escape

Tre temi minori, raccolti per completezza.

- **Paginazione.** Esiste `pagination()`/`paginationButton()` (`app/function/frontend/plugin/pagination.php:20,3`) con un array `$style` per le classi: è già personalizzabile, ma è una funzione globale fuori dal sistema Element. Proposta: Element `Pagination::make($total, $perPage)->current($n)->url(fn ($p) => …)` con renderer nei due temi, e `pagination()` come delega. Occorrenze contate (7 file/35 righe) ma **non verificate una per una**. Priorità Bassa.
- **Icone.** `<i class="bi bi-…">` compare 104 volte in app e 29 nel gestionale. Un helper `Icon::make('flag')` avrebbe senso solo come parte di `Button::icon()`, `Card::icon()`, `Badge::icon()` (già proposti sopra); un Element a sé per un tag di una riga è più verboso dell'HTML. Priorità Bassa.
- **Escape.** Il gestionale ha tre helper equivalenti (`GestionaleResource::escape()` `src/Resources/GestionaleResource.php:116`, `OrderSheet::esc()` `src/Support/Orders/OrderSheet.php:36`, `StockAdjustmentResource::escape()` `:351`) più `htmlspecialchars` diretto nei widget; app ha `e()` e 153 `htmlspecialchars`. Non serve un componente: serve una regola («usa `e()`») e scompare da sola man mano che il markup passa dagli Element.

---

### 3.16 Protezione CSRF centrale

Non è un pattern di markup ripetuto, ma è emerso dall'audit ed è stato deciso di introdurlo (decisione 11): entra nel piano perché i componenti dei punti 3.1–3.5 sono il posto in cui deve vivere.

**1. Descrizione.** Non esiste una protezione CSRF del framework. Ogni funzione che ne ha avuto bisogno si è costruita il proprio token; tutto il resto fa POST senza.

**2. Occorrenze.** Quattro implementazioni indipendenti in app: `class/Auth/Frontend/AuthSession.php:23` (`csrfToken()`/`verify()`, chiave di sessione `wonder_auth_csrf`), riusata da `class/App/Resources/Contacts/ContactResource.php:44,136` con il campo `_contact_csrf`; `class/Auth/Impersonation.php:154-168` (token per scopo); `class/App/Resources/Scheduler/ScheduleResource.php:28,94,158-162` (`scheduler_csrf`). Gestionale: nessuna (0 occorrenze di `csrf`), comprese le 6 modal di §3.1 e i mini-form POST di §3.5. Form generati da `Resource::formSchema()` e richieste `ajaxRequest()`/`postData()` della lib: nessun token.

**3. Varianti.** Token unico di sessione contro token per scopo; nome del campo diverso in ogni punto; verifica dentro la Resource contro verifica nel controller.

**4. Destinazione.** `app` per generazione, emissione e verifica; `lib` solo per allegare il token alle richieste JS.

**5. API proposta.**

```php
use Wonder\Http\Csrf;

Csrf::token();                    // token di sessione, creato al primo uso
Csrf::field();                    // FormField hidden già pronto (nome unico: _csrf)
Csrf::verify($request);           // hash_equals su campo _csrf o header X-WI-CSRF
```

- **Emissione automatica**, senza che chi scrive la pagina ci pensi: renderer di `Form` (entrambi i temi) quando il metodo non è GET, `Modal::form()`, `Button::post()` (`RendersButtonPostForm`), voci POST di `Dropdown`, `resource/form.php`. Nel layout backend e frontend un `<meta name="wi-csrf">` da cui `wi.http` legge il token e lo invia nell'header `X-WI-CSRF`.
- **Verifica centrale** in `RouteDispatcher`, prima di includere l'handler, per ogni richiesta non-GET delle aree backend e frontend con sessione. Escluse le rotte API autenticate con Bearer (non usano i cookie) e i webhook; esclusione esplicita per rotta, nome da definire. Fallimento → eccezione HTTP dedicata tradotta dal solo `RouteDispatcher`, come già avviene per `ForbiddenHttpException`.
- **Override:** `Csrf::field()` accetta nome e scopo per i casi che vogliono un token dedicato (`Impersonation` resta com'è: è più restrittiva, non meno).

**6. Esistente.** `AuthSession::csrfToken()/verify()` è la base naturale: `Csrf` ne generalizza la logica e `AuthSession` diventa una delega. `scheduler_csrf` e `_contact_csrf` spariscono a favore del campo unico. `ForbiddenHttpException` e `RouteDispatcher` per la traduzione dell'errore.

**7. Impatto.** È il passo più delicato del piano, perché accendere la verifica rompe ogni form che non emette il token: form scritti a mano nei siti, pagine dei moduli, richieste JS personalizzate. Per questo in tre tempi:
1. **Emissione** ovunque (app 2.4 + lib 2.1.2): nessun effetto visibile.
2. **Sola osservazione:** la verifica gira ma registra soltanto le richieste senza token (log), così ogni sito scopre i propri form scoperti.
3. **Blocco:** attivato da configurazione per sito, poi default nei siti nuovi.

Da verificare prima di progettare: pagine in cache o servite senza sessione (il token non può finire in HTML condiviso), sessioni scadute a form aperto (messaggio chiaro e non perdita dei dati: si lega alla save bar), login e form pubblici come il contatto di `new-site` (oggi protetto da reCAPTCHA, passa dall'API).

**8. Priorità.** **Alta** — è una lacuna di sicurezza, non di ordine; e conviene farla ora perché §3.1, §3.3 e §3.5 stanno per riscrivere proprio i punti che emettono i form.

---

## 4. Casi esclusi (astrarre sarebbe peggio)

| Caso | Motivo |
|------|--------|
| `app/view/…/under_maintenance.php` e `under_construction.php` (147 righe, diverse per una parola alla riga 83) | Due sole occorrenze: non un componente. Basta una view con un parametro; correzione da pochi minuti, fuori da questo piano. |
| Tabelle di `gestionale/view/emails/{order,order-merchant,low-stock}.php` | Markup email: tabelle e stili inline sono obbligati dai client di posta e non condividono nulla con il backend. Eventuale layout email è un tema separato. |
| Editor di varianti, opzioni e schede tecniche in `ProductModelResource.php` | Interfacce uniche, molto diverse tra loro. Vanno spostate in file JS (§3.11), non generalizzate. |
| `class/Plugin/Custom/Address/Address.php` (385 righe a mano) | Occorrenza singola; valutare se sostituirlo con `AddressExtension` + `AccountAddressForm`, già esistenti. |
| `app/function/backend/filter.php` come sorgente di componenti | Codice destinato a sparire: si migra verso le classi (§3.10), non si astrae. |
| `SortableInput`, `returnBadge()`, `wiCardStats()`, `filterDate()` | Già deprecati. |
| `createCard()` e la regola CSS di `<wi-card>` nella lib | Non si astraggono e non si toccano ora: restano finché i siti non hanno migrato (§3.6), poi si rimuovono. |
| `app/build/src/docs/button/index.php` | Pagina di documentazione: l'HTML a mano lì è il contenuto. |
| Offcanvas (`sidebarOffcanvas()`, 2 file), accordion (2 file), tooltip (8 file, quasi tutti dentro `BooleanBadge`), tabs (0) | Meno di 3 usi indipendenti, o già coperti da un Element. |
| `app/view/error/http.php` (322 righe) | Pagina autonoma che deve funzionare anche a framework non avviato. |
| `QuickCreateModal` — logica `fetch`/`FormData` | Una sola implementazione; adotterà `wi.http` e `Modal` quando esistono, senza un componente dedicato. |
| Header e footer dei siti | Per definizione diversi in ogni progetto. |

---

## 5. Ordine di implementazione

Destinazione: **app 2.4** e **lib 2.1.2** (decisione 10). Le fasi 1 e 2 escono insieme nelle due versioni; app è oggi a 2.4.0-beta.1 e lib a 2.1.2-alpha.22, quindi il lavoro entra nei cicli beta/alpha già aperti.

**Fase 0 — regole e vincoli (nessun componente nuovo)**
1. Regola «nessun nuovo `<wi-card>`» in AGENTS.md e nelle skill (§3.6, passo 1).
2. Versione minima della lib dichiarata da app e controllata da `forge update` (decisione 9): app scrive il vincolo in un solo punto (proposta: `extra.wonder.lib` nel proprio `composer.json`, es. `^2.1.2`) e `UpdateRunner` lo confronta con `node_modules/wonder-image/package.json`, fermandosi con un messaggio che dice quale comando lanciare. Senza questo, un sito con app 2.4 e lib vecchia riceverebbe `data-wi-confirm`, `data-wi-loading` e l'header CSRF senza il JS che li gestisce.

**Fase 1 — fondamenta (lib 2.1.2 + app 2.4)**
3. app: override uniforme (§3.0) — metodi `<parte>Class()`, `RendersPartAttributes`, `Registry::override()`, `RendersComponentAttributes` su tutti i renderer.
4. app: `Csrf` e sola emissione del token (§3.16, tempo 1).
5. lib: `wi.http` (con header CSRF), `wi.toast`, `wi.spinner`, con alias per le funzioni attuali (§3.4).
6. lib: `wi.modal.open()`, `data-wi-confirm` e `wi.confirm()` (§3.1, §3.2); `data-wi-loading` (§3.5).
7. app: `Button::confirm()` esteso, `Button::icon()/loading()`, `Dropdown` con voci POST/azione/testo (§3.2, §3.3, §3.5).
8. app: `Modal::form()/submit()/cancel()/help()` (§3.1).

**Fase 2 — componenti di contenuto (app 2.4)**
9. `Card::title()/icon()/description()/grid()`, `ActionList`, `DataList`, `Badge::status()` (§3.6–3.8).
10. Renderer per `Elements\Table` (§3.12): serve a tutti i moduli, quindi prima dei moduli.
11. app usa i propri componenti: `<wi-card>` → `Card` nelle 19 occorrenze, `Field.php`, `header-actions.php`, pagine `log/*/show.php`, `config/*`, `scheduler/*`.
12. Filtri: base comune e deleghe (§3.10). Contratto `wi.init`/`wi.register` e asset dei moduli (§3.11). Concern Swiper/Gallery con init nella lib (§3.13), ora sbloccato.
13. CSRF, tempo 2: verifica in sola osservazione (§3.16).

**Fase 3 — gestionale (e gli altri moduli)**
14. Modal e form (`OrderPayment`, `OrderNote`, `Order`, `Product`, `ContactAddress`).
15. Widget della home senza `<wi-card>`, badge, `OrderSheet`/`CustomerSheet`, tabelle statiche.
16. Script di `ProductModelResource` in `resources/assets/`.
17. ecommerce: `view/pages/backend/impersonation.php` e una ricognizione con gli stessi criteri di questo audit (non ancora fatta).

**Fase 4 — new-site**
18. Pagine legali e form contatto come view component del framework; `request/show.php` con `DataList`; `nav-item`/`nav-link` nel framework.

**Fase 5 — chiusura**
19. CSRF, tempo 3: blocco attivo, prima per configurazione poi di default.
20. `Pagination` come Element (facoltativo).
21. A tempo debito, senza data: rimozione di `createCard()`/CSS `<wi-card>` e degli alias JS dalla lib.

Ogni passo delle fasi 0–2 è un cambio architetturale secondo AGENTS.md: va chiuso insieme a `docs/app/*`, `AGENTS.md` e alla skill in `wonder-image/skills`.

---

## 6. Bozza delle regole per le skill

### wi-app

- **Modal.** Per le finestre modali usa `Wonder\Elements\Components\Modal` in app (`Modal::make()->form()->components()->submit()`), aperta con `Button::opensModal()`. Non scrivere l'HTML a mano. Se il componente non copre un caso, estendi la classe invece di duplicare il markup.
- **Conferme.** Per chiedere conferma usa `Button::confirm()` o l'opzione `confirm` di `Dropdown::item()` in app, che emettono `data-wi-confirm` gestito dalla lib. Non scrivere `onclick="modal(…)"` né `window.confirm`. Se il componente non copre un caso, estendi `wi.confirm` nella lib invece di duplicare il markup.
- **Bottoni.** Per bottoni e link-bottone usa `Wonder\Elements\Components\Button` in app (`make`, `to`, `post`, `icon`, `loading`). Non scrivere `<a class="btn">` a mano né `onsubmit="loadingSpinner()"`. Se il componente non copre un caso, estendi la classe invece di duplicare il markup.
- **Dropdown e menu azioni.** Usa `Wonder\Elements\Components\Dropdown` in app; nelle liste `TableColumn::actions()` e `TableLayoutSchema::buttonCustom()`. Non scrivere `dropdown-menu`/`dropdown-item` a mano. Se il componente non copre un caso, estendi la classe invece di duplicare il markup.
- **Card e widget.** Usa `Card`, `InfoCard`, `MetricCard` e `ActionList` in app. Non scrivere `<div class="card">` o `list-group` a mano. Se il componente non copre un caso, estendi la classe invece di duplicare il markup.
- **`<wi-card>` è dismesso.** Non aggiungere nuovi `<wi-card>` e non chiamare `wiCard()`/`wiCardLink()` in codice nuovo: usa `Card` in app, che rende `<div class="card">`. Quando modifichi una pagina che lo contiene, sostituiscilo. Non aggiungere a `Card` un'opzione per cambiare tag.
- **CSRF.** Per ogni form non-GET usa `Form`, `Modal::form()`, `Button::post()` o le voci POST di `Dropdown` in app, che emettono il token da soli; nelle richieste JS usa `wi.http`. Non creare token o campi nascosti propri (`*_csrf`) e non scrivere `<form method="post">` a mano. Se serve un token dedicato, estendi `Wonder\Http\Csrf` invece di duplicare la logica.
- **Schede dettaglio.** Per coppie etichetta/valore usa `DataList`/`DataItem` in app. Non scrivere `Etichetta: <b>valore</b><br>` a mano. Se il componente non copre un caso, estendi la classe invece di duplicare il markup.
- **Badge.** Usa `Wonder\Elements\Components\Badge` in app (`Badge::status()` per le mappe di stato; `TableColumn::badge()` nelle liste). Non scrivere `<span class="badge">` a mano. Se il componente non copre un caso, estendi la classe invece di duplicare il markup.
- **Form e campi.** Usa `FormField`/`RepeaterColumn` e `Form` in app (regola già in vigore). Non scrivere `<input>`, `<select>`, `<textarea>` a mano, nemmeno dentro una modal. Se manca un tipo, aggiungilo sotto `ResourceSchema/Inputs` con i renderer dei due temi.
- **Filtri lista.** Usa `TableLayoutSchema::filter*()` e le classi `Wonder\Backend\Filter\*` in app. Non aggiungere funzioni a `app/function/backend/filter.php`. Se il filtro non copre un caso, estendi `AbstractFilter`.
- **Tabelle statiche.** Usa `Wonder\Elements\Table` in app. Non scrivere `<table>` a mano fuori dalle email. Se il componente non copre un caso, estendi la classe invece di duplicare il markup.
- **JavaScript.** Per richieste, toast e spinner usa `wi.http`, `wi.toast`, `wi.spinner` nella lib. Non scrivere `$.ajax`/`fetch` nei renderer e non emettere `<script>` con valori PHP interpolati: il renderer scrive `data-wi-*`, l'inizializzazione sta nella lib (`wi.register`). Il JS specifico di un modulo vive nei suoi asset, non in heredoc PHP.
- **Personalizzazione.** Per cambiare classi usa `addClass()` sulla radice e i metodi dedicati delle parti (`headerClass()`, `bodyClass()`, `footerClass()`, …); per cambiare struttura registra un renderer con `Themes\Registry::override()`. Non copiare il renderer né l'HTML generato. Se a un Element manca il metodo per una sua parte, aggiungilo all'Element.

### wi-site

- **Modal, bottoni, badge, card.** Usa gli Element di `wonder-image/app` (`Modal`, `Button`, `Badge`, `Card`) con `render()` senza forzare il tema. Non scrivere l'HTML a mano. Se il componente non copre un caso, estendilo in `app/` o chiedi l'estensione nel framework invece di duplicare il markup in `custom/`.
- **`<wi-card>` è dismesso.** Non scrivere `<wi-card>` nelle pagine backend del sito: usa `Card` di `wonder-image/app`. Le pagine esistenti che lo usano continuano a funzionare finché la lib non lo rimuove; sostituiscilo quando le tocchi.
- **Form.** Usa `Resource::getInput()`/`FormField` e `Form::submitTo()`. Non scrivere `<input>` né `<form method="post">` a mano e non aggiungere `<script>` di callback: token CSRF e risposta sono gestiti da framework e lib.
- **Pagine legali.** Usa `View::component('frontend.sections.legal-document', ['key' => …])`. Non copiare la pagina per ogni documento.
- **Navigazione.** Usa `frontend.ui.nav-item`/`nav-link` del framework alimentati da `custom/config/navigation.php`. Sovrascrivi il componente in `custom/view/components` solo se cambia la struttura.
- **Pagine show del backend.** Usa `DataList` con le etichette della Resource. Non concatenare valori dentro `RichText`.
- **JavaScript.** Usa `wi.http`, `wi.toast`, `wi.confirm` della lib. Non scrivere `fetch`/`$.ajax` nelle view; per un comportamento nuovo registra `wi.register('nome', …)` negli asset del sito.
- **Override.** Per cambiare l'aspetto usa classi della lib tramite `addClass()` e i metodi dedicati delle parti (`footerClass()`, `bodyClass()`, …). Se serve un markup diverso, sovrascrivi il view component in `custom/view/components` o registra un renderer: non incollare l'HTML generato.

---

## 7. Decisioni prese

Le domande della prima versione, con la risposta ricevuta e il punto del piano in cui è stata applicata.

| # | Domanda | Decisione | Dove |
|---|---------|-----------|------|
| — | `<wi-card>` | Non si usa più e non se ne aggiungono; `Card` rende `<div class="card">` | §3.6, §6 |
| 1 | Classi delle parti | Metodi dedicati: `footerClass()`, `bodyClass()`, … | §3.0 e tutte le voci «Override» |
| 2 | Namespace JS | Oggetto globale `wi.http` / `wi.toast` | §3.4 |
| 3 | Nomi degli Element | `ActionList`, `DataList` | §3.6, §3.7 |
| 4 | Le due `modal()` | `wi.modal.open()` e `wi.confirm()`; le vecchie restano alias | §3.1, §3.2 |
| 5 | Markup di default di `Card` | `<div class="card">` | §3.6 |
| 6 | Init Swiper/Gallery nella lib (addio «byte per byte») | Sì, in una minor | §3.13 |
| 7 | Durata degli alias JS | Rimossi a tempo debito, nessuna data ora | §3.4, §5 fase 5 |
| 8 | `Registry::override()` pubblico | Sì | §3.0 |
| 9 | Versione minima della lib | Dichiarata da app, controllata in `forge update` | §5 fase 0 |
| 10 | Versioni | app 2.4 e lib 2.1.2 | §5 |
| 11 | CSRF centrale | Non esiste: va introdotta | §3.16, §3.1 |
| 12 | `safeFindById()` | Restituisce valori già sottoposti a escape: nessun rischio in `request/show.php` | §3.7 |
| 13 | `ActionList` e `Elements\Table` | Serviranno a tutti i moduli | §3.12 (ora Media), §5 fase 2 |

### Punti rimasti da verificare durante l'implementazione

Non sono domande per te: sono controlli da fare sul codice prima di scrivere il componente relativo.

1. **Default di `Card`** — che cosa emette oggi il renderer Bootstrap (bordo, classi del body) rispetto a `card border` + `card-body row g-3` prodotti da `createCard()`: da qui dipende se `grid()` basta o serve anche un default diverso (§3.6).
2. **Selettori su `wi-card`** in CSS/JS di lib, moduli e siti, prima di sostituire il tag (§3.6).
3. **CSRF** — pagine senza sessione o in cache, rotte da escludere, comportamento a sessione scaduta (§3.16).
4. **Doppia codifica** — `DataList::fromResource()` con righe già «safe» (§3.7).
5. **Dove scrivere il vincolo di versione della lib** — `extra.wonder.lib` in `composer.json` è una proposta; va controllato che sopravviva ai deploy che rimuovono il `composer.json` dei pacchetti (AGENTS.md lo segnala per i moduli), altrimenti una costante in una classe (§5 fase 0).
6. **ecommerce** — contiene almeno un `<wi-card>`; il resto del repo non è stato analizzato.
