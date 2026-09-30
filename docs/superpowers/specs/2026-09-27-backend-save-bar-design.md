# Barra di salvataggio del backend — design

**Data:** 2026-09-27
**Repo:** lib + app, più i satelliti toccati: skills, immobili, new-site, immobili-site, rsvp-site, agliati
**Tipo:** architetturale

## Contesto

La richiesta dell'utente:

> Vorrei che i bottoni salva o salva aggiungi o modifica nel backend se non si vedono nella schermata perchè sono troppo sotto, vorrei mettere una sorta di Dynamic Island in basso, con quei bottoni e quando si modifica qualcosa si scrive 'Operazione non salvata'

Oggi il "Salva" sta in fondo al form e il backend non si accorge mai delle modifiche. Né l'app né la lib hanno uno stato "modificato", un `beforeunload` o una scritta "non salvato". Il modello è la barra di salvataggio contestuale dell'admin di Shopify o delle impostazioni di GitHub. La metafora della Dynamic Island calza: è un'isola che cambia forma secondo lo stato.

Il lavoro è architetturale: tocca app e lib, cambia il funzionamento dei bottoni di invio e aggiunge un componente alla lib.

**Risposte dell'utente alle cinque domande iniziali:**
1. **Forma.** L'isola si trasforma:
   - è nascosta quando i bottoni sono visibili e il form è pulito;
   - mostra i bottoni quando sono fuori schermo;
   - con modifiche non salvate mostra "Operazione non salvata", più i bottoni se sono fuori schermo.
2. **Dove.** È attiva di default sui form delle Resource. Altrove si attiva su richiesta, con un attributo sul `<form>`. Login, filtri ed eliminazioni restano esclusi.
3. **Bottoni.** L'isola ripete solo i bottoni che il form ha già. Non ne crea di nuovi.
4. **Uscita.** Si usa l'avviso nativo del browser tramite `preventPageExit` della lib, che si spegne al salvataggio.
5. **Approccio.** "A — Lib + attributo".

Nel resto del documento i codici Q1–Q20 non indicano queste cinque risposte. Indicano i casi limite decisi durante la stesura dei test, riassunti nella tabella finale.

**Vincoli scoperti nel codice che guidano il design:**
- La `check()` della lib (`src/build/backend/js/form/input.js:3`) abilita solo i `form button[type=submit], form button.wi-submit` dentro il form, e i bottoni nascono `disabled`. I bottoni quindi non si possono spostare né clonare fuori dal form come submit veri.
- Il server sceglie l'azione dal `name` del bottone (`upload`, `upload-add`, `modify`).
- Nelle Resource "Salva e aggiungi" non esiste: `ResourcePagePresenter.php:152` fa comunque redirect. `submitAdd()` è legacy (user/manage, blog/gallery/menu/getrix).
- `preventPageExit` esiste già nella lib (`src/build/global/js/utility.js:115`) e oggi nessuno lo usa.
- Capire "è stato modificato" non è banale:
  - Select2 e i datepicker mandano `change` solo tramite jQuery;
  - Quill, EditorJS, repeater e jstree scrivono valori senza eventi;
  - AutoNumeric, FilePond e il campo telefono riscrivono i valori già al caricamento.
- Dopo un errore di validazione il form si ridisegna sulla stessa POST con dati non salvati. Deve quindi nascere "modificato".

## Obiettivo

- Nel backend, quando i bottoni di invio di un form sono sotto la piega, un'isola fissa in basso ne mostra delle copie funzionanti. Le copie inviano il form con il `name` del bottone originale.
- Quando il form è diverso da com'era all'apertura, l'isola scrive "Operazione non salvata". Se l'utente annulla a mano la modifica, l'isola torna pulita.
- Uscire dalla pagina con modifiche non salvate chiede conferma con il dialogo nativo del browser.
- Tutti i form delle Resource non bloccati (`$locked`) hanno l'isola senza codice nei siti. Le altre pagine la attivano con un attributo.
- Lib e app si rilasciano separatamente, e ogni combinazione di versioni funziona.

## Non obiettivi (YAGNI)

- Nessun bottone "Annulla modifiche".
- Nessun bottone nuovo nell'isola. Si copiano solo quelli esistenti.
- Nessuna traduzione dei testi: restano in italiano, sovrascrivibili (sez. 4, Testi).
- Nessun salvataggio automatico e nessuna bozza locale.
- Nessuna isola sul frontend. Non si attiva sui form di login, recupero e impostazione password, filtri, eliminazioni e `Button::post`.
- Nessun `autocomplete="off"` sui form tracciati (Q8, vedi Limiti noti).
- Nessuna modifica a `send.js` né agli helper AJAX della lib.
- Nessuna 2.1.2 stabile della lib per questa funzione.
- Fuori da questo lavoro:
  - l'anello di focus di `btn-dark` in tema scuro (1,26:1): task separato già chiuso su main della lib (3dee8d3; 33ecf8f per il tema chiaro). Nessun lavoro qui.
- Già risolti su main prima di questo lavoro, e coperti qui solo come regressione:
  - l'errore 970 spurio di `apiUser()`, corretto dal commit dell'app ca40ba49 (I8);
  - il CheckTree che perdeva le spunte chiudendo un nodo, corretto da lib 4ab9bcd e app fa70d1b5 (I12).

## Principi di codice

La regola dell'utente:

> crea sempre componenti o funzioni che potremmo riutilizzare anche per altri scopi! Sii minimal nel codice con commenti sempre brevi e in italiano. Non fare funzioni per piccole operazioni ma solo per cose ripetitive

Ecco come si applica a questo lavoro. I contratti pubblici approvati (`window.wiSaveBar`, attributi, evento, firme PHP) non cambiano: i pezzi generici stanno sotto, come dettaglio di implementazione.

### 1. Riutilizzabile

**Lib: un solo file JS, con i pezzi generici in funzioni separate.**
- Il layout approvato resta di due file nella lib: `src/build/backend/js/form/saveBar.js` e `src/build/backend/css/save-bar.css` (sez. 1). Non si crea un file JS a parte per lo stato del form.
- Dentro `saveBar.js` la logica generica sullo stato di un form sta in funzioni separate, senza markup, pronte per altri usi (autosave, modal con modifiche, form del frontend):
  - `formSnapshot(form, excludeSelector)`: l'istantanea ordinata e normalizzata (sez. 2.1), comprese le zone escluse e la regola Q1;
  - `sameSnapshot(a, b)`: il confronto, con le checkbox dello stesso nome trattate come insieme;
  - `absorbSnapshot(base, current, container)`: il nuovo punto di partenza, che prende da `current` solo le voci dei campi dentro `container`;
  - `frameScheduler(fn)`: restituisce una funzione che programma `fn` al massimo una volta per frame, con `requestAnimationFrame`. Serve a ricalcolo, visibilità e rilettura della fascia.
- Sempre in `saveBar.js`, le funzioni proprie dell'isola:
  - `proxyButton(original)` e `syncProxy(original, copy)`: creano la copia ripulita di un bottone e la riallineano all'originale (sez. 3 C);
  - la rilettura della fascia (sez. 4, Fascia), chiamata all'avvio e sul `resize`;
  - la tabella degli stati (sez. 3 E).
- Il resto del file: `window.wiSaveBar`, `setUpSaveBar()`, gli osservatori, l'invio e l'avviso di uscita.
- Se un secondo consumatore avrà bisogno delle funzioni generiche, si spostano in un file proprio (per esempio `src/build/global/js/form/`) senza cambiare le firme.
- Queste funzioni non entrano nel contratto e non vanno nel MANIFEST come API. Le leggono solo i test della lib.

**Lib: si riusa quello che c'è.**
- L'avviso di uscita è `preventPageExit` (`src/build/global/js/utility.js:115`), aggiunto e tolto con `addEventListener('beforeunload', ...)`.
- Lo spinner si comanda con `loadingSpinner()` (`src/build/backend/js/utility.js:34`), che oggi alterna soltanto. Si estende in modo compatibile, sul modello di quello del frontend che accetta già un'azione:
  ```js
  function loadingSpinner(show) {
      document.getElementById('loading-spinner').classList.toggle('d-none', show === undefined ? undefined : !show);
  }
  ```
  Senza argomento continua ad alternare, quindi `onsubmit="loadingSpinner()"` e gli altri chiamanti non cambiano. L'isola usa `loadingSpinner(false)` per spegnerlo (sez. 3 F, 3 J) e non conosce né l'id `#loading-spinner` né la classe `d-none`.
- L'avvio è un passo di `setUpPage()` (`pageSetUp.js`), sul modello di `setUpBootstrap()` e `setUpJquery()`.
- Gli assorbimenti tardivi stanno dentro le funzioni di `set.js` che già riempiono i campi (`setDynamicSearch`, jstree) e in `initGoogleMapsPlace`.
- Il nome dell'evento segue lo schema `wi:<area>:<evento>` di `DeferredContent` (`wi:deferred:ready`).

**App.**
- `Wonder\App\Support\AttributeString::render()` è un serializzatore generico di attributi HTML, utile a qualsiasi componente o vista (sez. 5.1).
- `ResourceFormLayoutRenderer::hasSubmit(Form $form, string $name = 'upload'): bool` è pubblica e statica. Risponde a una domanda generica ("questo layout ha già un Submit con questo nome?"), non legata alla barra (sez. 5.2).
- L'opzione `attributes` di `ResourceFormLayoutRenderer::render()` è generica: il renderer non sa nulla della barra.

**Test.**
- Il DOM finto per `node:vm` si costruisce dentro `test/backend-save-bar.test.cjs` (6.2). I finti di Quill ed Editor.js si riscrivono dentro `test/backend-set-input.test.cjs` (6.2). Nessun helper condiviso nuovo sotto `test/`.

**Nessuna funzione nuova per il prodotto.** L'estrazione dei pezzi generici non aggiunge API pubbliche né comportamenti oltre a quelli approvati.

### 2. Minimal

- Nessuna dipendenza nuova: niente jsdom, niente PHPUnit, Playwright fuori da `devDependencies`.
- Nessuna classe o livello di astrazione in più: funzioni globali, come il resto del backend della lib.
- Nessun codice difensivo per casi non previsti dal design. Eccezioni:
  - il `try/catch` dell'avvio e il controllo delle API (Q16);
  - il `try/catch` sulla creazione dell'IntersectionObserver, che riprova con 96 (sez. 4, Fascia);
  - il ripiego a 96 quando `--wi-save-bar-band` non si legge come lunghezza in px (sez. 4, Fascia).
- CSS con i token di Bootstrap già presenti. Nessun valore duplicato.

### 3. Commenti

- I commenti sono brevi (una riga, al massimo due) e in italiano. Il precedente è `src/build/backend/js/form/iconPicker.js:23` della lib.
- Si segue la regola ASCII degli `AGENTS.md` di app e lib: accenti solo nei file che già usano caratteri non ASCII (per esempio `set.js`). I file nuovi (`saveBar.js`, `save-bar.css`) restano ASCII anche nei commenti. Identificatori, nomi di file e stringhe tecniche restano sempre ASCII.
- Un commento spiega il perché, non il cosa. Esempio: `// threshold 1 si blocca a 0,99 con DPR frazionari`.

### 4. Funzioni solo per operazioni ripetute

- **Diventano funzioni** solo le operazioni chiamate da più punti:
  - istantanea e confronto;
  - ricalcolo programmato;
  - copia e riallineamento dei bottoni;
  - rilettura della fascia (all'avvio e sul `resize`);
  - tabella degli stati.
- **Restano inline** le operazioni fatte in un punto solo:
  - costruzione del DOM dell'isola e della regione `role="status"`;
  - il confronto con la fascia nella misura del rAF (sez. 3 D) e il parse in px dentro la rilettura della fascia;
  - il filtro delle classi dentro `proxyButton`;
  - singoli cambi di classe o di attributo (`wi-save-bar-on`, `data-wi-state`);
  - testo della regione di stato.
- In PHP vale lo stesso. `AttributeString::render()` esiste perché serve in due rami di `resource/form.php` e nel renderer. La riga `$saveBarAttributes` resta inline nella vista. Nei `<form>` scritti a mano gli attributi si scrivono direttamente nel tag, senza `AttributeString` (sez. 5.4).

## Sezione 1 — Architettura e contratto

### Glossario

- **Isola:** la barra fissa in basso; il nome tecnico è `save-bar`.
- **Form tracciato:** un `<form>` con `data-wi-save-bar`.
- **Sporco / pulito:** con o senza modifiche non salvate (sez. 2.4).
- **Originale / copia:** il bottone di invio vero nel form e il suo doppione nell'isola (sez. 3 C).
- **Fascia:** la striscia in fondo alla finestra, alta `--wi-save-bar-band`, dove sta l'isola (sez. 4, Fascia).
- **IO:** `IntersectionObserver`. **rAF:** `requestAnimationFrame`.

### Dove sta il codice

- **Nella lib:**
  - `src/build/backend/js/form/saveBar.js`: l'isola, come funzioni globali secondo lo stile del backend, con i pezzi generici in funzioni separate (vedi Principi di codice);
  - `src/build/backend/css/save-bar.css`: lo stile dell'isola.
  - Tutti e due si collegano in `src/export/backend/head.js`.
- **Nell'app** non c'è né JS né CSS. Ci sono solo gli attributi sui form, il serializzatore di attributi e le condizioni "nasce sporco".
- Il nome tecnico è `save-bar`. A voce la chiamiamo "isola".

### Contratto pubblico

**Attributi**

| Attributo | Dove | Significato |
|---|---|---|
| `data-wi-save-bar` | `<form>` | Attiva l'isola per il form. |
| `data-wi-save-bar-dirty` | `<form>` | Il form nasce "non salvato", per esempio dopo un POST fallito. Conta la presenza, non il valore. |
| `data-wi-save-bar-ignore` | bottone, campo o contenitore | Il bottone non si copia; i campi dentro non contano nell'istantanea. |
| `data-wi-save-bar-hide-when-open` | qualsiasi elemento nel `body` | Finché l'elemento esiste, l'isola si nasconde. Serve ai popup di widget terzi (sez. 4, Stati). |

**JS**

| Membro | Firma | Comportamento |
|---|---|---|
| `wiSaveBar.changed` | `(elOrForm?)` | "Ho scritto un valore senza evento": programma un ricalcolo di tutti i form tracciati. L'argomento è facoltativo e non cambia il comportamento. Non forza lo stato. |
| `wiSaveBar.reset` | `(form)` | L'istantanea attuale diventa il punto di partenza. Azzera lo sporco nato da `-dirty` e spegne l'avviso di uscita per quel form. |
| `wiSaveBar.isDirty` | `(form) → boolean` | Stato attuale del form. |
| `wiSaveBar.absorb` | `(el)` | I valori dei campi dentro `el` diventano il nuovo punto di partenza; il resto no. |
| `wiSaveBar.labels` | `{ group, unsaved, confirmOther }` | Testi, sovrascrivibili prima dell'avvio (sez. 4, Testi). |
| `setUpSaveBar` | `()` | Passo di avvio globale e idempotente, chiamato da `setUpPage()` (Q6). |
| evento `wi:save-bar:change` | sul form, `detail { dirty }` | Parte solo al passaggio pulito↔sporco, anche per `reset()` (Q2). |

- Le quattro funzioni chiamate su un form non tracciato o su `null` non danno errori, restituiscono `false` e non emettono eventi.
- `changed()` chiede solo di ricontrollare: se l'utente ha annullato la modifica, lo stato torna pulito. La usano Quill, EditorJS e il codice dei siti che scrive valori senza eventi.
- L'attributo `data-wi-save-bar-dirty` si legge solo all'avvio. `reset()` e un invio non annullato azzerano il flag interno senza togliere l'attributo dal DOM.

**CSS nel contratto**
- `--wi-save-bar-reserve` è l'unica variabile nel contratto, in sola lettura. È lo spazio riservato in fondo alla pagina e l'`header.php` dell'app la sottrae (sez. 4, Fascia). Con la lib vecchia non esiste e vale 0.
- I token `--wi-save-bar-zindex`, `--wi-save-bar-band`, `--wi-save-bar-offset` e `--wi-save-bar-border-color` si documentano in `css-variables.md` come personalizzabili su `:root`. Non sono contratto stabile.

**Fuori contratto (interni della lib):**
- le classi `.wi-save-bar*` e `html.wi-save-bar-on`;
- `data-wi-state` e `data-wi-save-bar-keyboard`;
- le funzioni interne di `saveBar.js` (`formSnapshot`, `sameSnapshot`, `absorbSnapshot`, `frameScheduler`, `proxyButton`, `syncProxy` e le altre).

**Regole per chi integra:**
- Chi invia un form tracciato da script chiama `wiSaveBar?.reset(form)` prima di `form.submit()`.
- Chi salva via AJAX chiama `wiSaveBar?.reset(form)` nella callback di successo.
- `absorb(el)` serve solo per scritture non fatte dall'utente, sul contenitore più stretto, mai sul form intero (sez. 5.7).
- I listener `formdata` non devono avere effetti collaterali (sez. 2.1).

**Rimossi durante il design, e quindi non esistono:** `wiSaveBar.disarm()`, l'override di `HTMLFormElement.prototype.submit`, `data-wi-save-bar-submit` e un `ignore` a livello di form.

### Bottoni copiati

- Si copiano gli elementi di `form.elements` con la proprietà `type === 'submit'` oppure con la classe `.wi-submit`, in ordine di pagina (Q12). La proprietà, non l'attributo, quindi entrano:
  - `<button>` senza `type`;
  - `<input type="submit">`;
  - i submit fuori dal form collegati con `form="id"`.
- Restano fuori:
  - i bottoni dentro `.modal`, `.offcanvas` o `[data-wi-save-bar-ignore]`, cercati con `closest()`. Per esempio il "Salva" del quick-create;
  - i bottoni non visualizzati (sez. 3 B).
- Le varianti responsive `.offcanvas-{bp}` non si escludono da sole: si usa `data-wi-save-bar-ignore`.

### Ciclo di vita

- L'avvio è un passo di `setUpPage()`, subito prima di `window.dispatchEvent(new Event('loaded'))`. Il passo è la funzione globale `setUpSaveBar()` (Q6).
- A quel punto `createCard()` ha ricostruito le card e i widget hanno formattato i valori. Prima, i riferimenti ai nodi andrebbero persi.
- Punto di partenza, elenco dei bottoni e primi osservatori si preparano nel frame successivo (sez. 3 A).

### Un'isola per pagina

- Una sola isola, messa in fondo al `body`.
- È collegata al form attivo, cioè l'ultimo form tracciato in cui l'utente ha interagito.
- All'avvio il form attivo è il primo con `data-wi-save-bar-dirty`, altrimenti il primo `form[data-wi-save-bar]` in ordine DOM.
- L'etichetta segue il form attivo. L'avviso di uscita scatta invece se almeno un form tracciato è sporco.
- Le pagine delle Resource hanno un solo form. La regola serve alle pagine con più form, come l'account.

### Rilasci indipendenti

- Lib nuova e app vecchia: non succede niente, perché manca l'attributo.
- App nuova e lib vecchia: l'attributo resta inerte, e `--wi-save-bar-reserve` vale 0.
- Senza JS, o senza le API necessarie (Q16), restano i bottoni originali.

### Alternative scartate

- **B, tutto nell'app** con `<style>`/`<script>` inline in un componente PHP. Va contro la regola "l'app non emette CSS per il backend", i moduli non potrebbero riusarlo, non si aggancia ai widget interni della lib e richiederebbe polling.
- **C, isola disegnata dal server** dentro il `<form>`, con submit veri duplicati in `position: fixed`. Avrebbe bottoni doppi nel DOM (accessibilità, ordine di tabulazione, `id` ripetuti), funzionerebbe solo con gli Element e richiederebbe comunque JS nella lib.
- **Copie che chiamano `form.requestSubmit(originale)`.** Sostituito da `original.click()`, che fa partire anche gli `onclick` e gli handler delegati dell'originale (sez. 3 C).

## Sezione 2 — Come l'isola capisce che c'è qualcosa da salvare

**Principio:** il form è "non salvato" quando quello che invierebbe adesso è diverso da quello che inviava all'apertura. Se l'utente annulla a mano una modifica, l'isola torna pulita. Si confronta l'istantanea con quella iniziale, non si contano gli eventi.

### 1. Istantanea del form

- Si prende con `new FormData(form)`, cioè esattamente quello che il salvataggio spedirebbe (`formSnapshot`). Restano fuori da soli:
  - i campi disabilitati, compresa la riga del repeater eliminata con "annulla" disponibile;
  - le checkbox non spuntate;
  - i bottoni.
- `new FormData` fa girare i listener `formdata`. Lì FilePond, con `data-wi-file-references` (sempre attivo nel tema Bootstrap), scrive il manifest `__wi_files`: i file salvati più quelli nuovi. Il manifest non cambia mentre i file salvati si scaricano. Un widget futuro che si serializza su `formdata` sarà coperto senza codice in più.
  - **Regola per lib e siti:** i listener `formdata` non devono avere effetti collaterali.
- L'istantanea è una sequenza **ordinata** di coppie nome/valore, come la riceve PHP. Spostare una riga del repeater cambia la posizione salvata, quindi conta come modifica.
- Normalizzazioni:
  - **AutoNumeric:** si confronta il valore numerico, non il testo. Il mouse su un prezzo vuoto mostra "€" e un submit annullato lascia i campi non formattati: nessuno dei due casi conta.
  - **File:** si confrontano nome, dimensione e tipo. Le voci file vuote non contano.
  - **Nomi fatti solo di checkbox:** i valori si confrontano come insieme, perché la ricerca delle DynamicCheck rimette in fondo le voci spuntate.
- **Esclusi:** i campi dentro `.modal` e dentro `[data-wi-save-bar-ignore]`, e i campi che hanno l'attributo. L'attributo vale quindi per bottoni, campi e contenitori.
- **Esclusione per nome (Q1).** `FormData` non dice da quale elemento viene una voce.
  - Un nome presente solo in zone escluse si toglie dall'istantanea.
  - Un nome presente sia dentro sia fuori da una zona esclusa si tiene intero, con un avviso in console.
  - I modal dell'app sono già fuori dal form (`data-wi-modal-detach`, e il quick-create li sposta nel `body`), quindi il caso riguarda i modal legacy dentro il form e `[data-wi-save-bar-ignore]`.

### 2. Quando si ricalcola

- Al massimo una volta per frame, mai dentro l'evento stesso, perché Quill aggiorna la textarea un attimo dopo. Il ricalcolo passa da `frameScheduler`.
- Ogni segnale ricalcola **tutti** i form tracciati. Il motivo è che la conferma del repeater sta in un modal sul `body`, fuori dal form.
- I segnali sono:
  - `input` e `change` nativi, più gli stessi tramite jQuery delegato. Select2, i datepicker e il quick-create usano `.trigger()`, che `addEventListener` non vede;
  - `click` e `keyup` come rete di sicurezza, ascoltati su `document` in cattura. Coprono le scritture silenziose dentro un `onclick` (albero, frecce e svuotamento del repeater, generatore di codici, righe legacy) e gli handler che chiamano `stopPropagation()`;
  - un solo MutationObserver sul form (Q11), con `childList`, `subtree` e gli attributi `disabled` e `data-wi-save-bar-ignore`:
    - programma sempre un ricalcolo, quindi coglie righe aggiunte, tolte o spostate, opzioni ricostruite e `disabled` messo da script;
    - rifà l'elenco dei bottoni solo se la mutazione tocca un bottone o l'attributo ignore (sez. 3 B);
  - gli eventi `FilePond:addfile`, `FilePond:removefile`, `FilePond:reorderfiles`, `FilePond:processfile`, `FilePond:processfilerevert` e `FilePond:updatefiles` (elenco chiuso), `wi-repeater-row-delete`, `wi-repeater-row-restore`, `wi:quick-create:created` e `autoNumeric:rawValueModified`;
  - `wiSaveBar.changed()`.

### 3. Il punto di partenza

Si fotografa al primo frame dopo l'avvio (sez. 3 A). Diverse cose però arrivano dopo, da sole:
- le DynamicCheck riempite via AJAX;
- il `ready` di jstree;
- EditorJS quando un'immagine finisce di caricare;
- Google Places che abilita il campo.

Le regole sono tre.
- **Prima dell'interazione.** Finché l'utente non ha fatto niente, ogni differenza diventa il nuovo punto di partenza.
  - "Fare qualcosa" vuol dire un tasto, un clic o un tocco, incolla, trascina, o un `input` reale. Tutti con `isTrusted` true.
  - Non chiudono questa fase: `el.click()`, `.trigger()` di jQuery e `dispatchEvent` (`isTrusted` false); il `focus` fidato che nasce da `el.focus()`; lo scroll e il passaggio del mouse.
  - Nessuna modifica dell'utente può nascere prima, quindi non si perde nulla. Sono coperti anche gli script dei siti che non conosciamo.
- **Dopo l'interazione** il confronto è rigido. Con il paese IT→FR→IT e la provincia azzerata nel frattempo, il form resta "non salvato", come deve.
- **Riempimenti tardivi noti:** `wiSaveBar.absorb(el)` (`absorbSnapshot`). I valori dei campi dentro `el` diventano il nuovo punto di partenza, il resto no. Il risultato è corretto anche se l'utente ha già cliccato altrove. La lib la chiama:
  - nel successo AJAX del caricamento iniziale di `setDynamicSearch`, su `#container-{id}` (sez. 5.7);
  - sul `ready.jstree`, sul contenitore `[data-wi-tree-values]` dell'albero (Q9);
  - in `initGoogleMapsPlace`, subito dopo `input.disabled = false` (Q17).
- `absorb(el)` con `el` fuori da ogni form tracciato non ha effetti.

### 4. Lo stato

"Non salvato" vale in due casi:
- il form ha `data-wi-save-bar-dirty`, per esempio dopo un errore di validazione. Resta finché non c'è un invio non annullato o un `reset(form)`;
- l'istantanea è diversa dal punto di partenza (`sameSnapshot` falso).

### 5. Correzioni nella lib

**Quill** (`set.js`):
- l'editor vuoto vale `''`, non `<p><br></p>`;
- il valore si scrive solo su `text-change`. Vanno via le scritture su `keydown` e sul deprecato `DOMNodeInserted`, che mettevano nella textarea `<p><br></p>` e il cursore;
- a ogni `text-change`, qualunque sia la sorgente (`user` o `api`), chiama `wiSaveBar?.changed()` (Q10). Non forza lo stato, quindi non costa nulla;
- effetto voluto: un Quill obbligatorio in cui si scrive e poi si cancella torna vuoto, e `check()` disabilita Salva;
- scrivere direttamente nel DOM di `.ql-editor` non è più supportato. Va nel CHANGELOG.

Senza queste correzioni "scrivo una lettera e la cancello" resterebbe "non salvato".

**EditorJS** (`set.js`):
- dopo `isReady`, un `save()` produce la versione normalizzata del contenuto iniziale, senza `id`, `time` e `version`;
- a ogni `onChange` la textarea si riscrive solo se il contenuto è davvero cambiato. Se torna uguale all'iniziale, si rimette il JSON originale;
- poi chiama `wiSaveBar?.changed()`;
- così un'immagine che finisce di caricare o il mouse su una tabella non contano come modifiche.
- EditorJS segnala con `onChange` in debounce e `save()` è asincrono: lo sporco arriva circa 400ms dopo la modifica. Uscire dalla pagina prima non dà l'avviso (Limiti noti, I12).

### Costo

- Una `FormData` per frame, e solo dopo un segnale.
- Soglia (Q4), misurata su un Mac di sviluppo:
  - la mediana di 5 ripetizioni per scenario non aggiunge task oltre 50ms rispetto alla stessa pagina senza isola;
  - una `FormData` del form da circa 5000 campi resta sotto un frame (16ms);
  - la CPU rallentata ×4 è solo informativa.
- Gli scenari sono in B20.

### Alternative scartate

- Contare gli eventi invece di confrontare istantanee: non torna pulito dopo un annullamento a mano.
- Fotografare subito all'avvio, senza la fase "prima dell'interazione": ogni riscrittura di init (AutoNumeric, FilePond, telefono) segnerebbe il form sporco.
- Due osservatori sul form, uno per i valori e uno per i bottoni: con uno solo il costo è lo stesso e un `disabled` messo da script non si perde.

## Sezione 3 — Come si comporta l'isola

### A. Avvio e form attivo

- `setUpSaveBar()` parte da `setUpPage()`, subito prima di `loaded`, dopo `createCard`, `setInput`, `checkInput`, `setUpBootstrap` e `setUpJquery`.
- Ha una guardia contro il doppio avvio: una seconda chiamata non crea una seconda isola né nuovi listener.
- **Avvio protetto (Q16):**
  - il passo è avvolto in `try/catch`. Se lancia un errore, l'errore finisce in console, `loaded` parte comunque e non c'è isola;
  - parte solo se esistono `IntersectionObserver`, `ResizeObserver`, `MutationObserver` e il supporto a `inert`. Altrimenti restano i bottoni originali, come senza JS.
- Una pagina senza form tracciati non crea l'isola, gli osservatori, la classe su `<html>`, i listener su `document` né lavoro in rAF.
- Punto di partenza, elenco dei bottoni e primi bersagli di IntersectionObserver si preparano nel `requestAnimationFrame` successivo. Questo copre i microtask di Quill e i listener su `loaded` come `Address.initForm`. Le scritture asincrone successive si coprono con la fase "prima dell'interazione" o con `absorb()` (sez. 2.3).
- L'isola resta nello stato `hidden` finché non arrivano sia la prima callback di IntersectionObserver sia la prima istantanea.
- **Form attivo:** l'ultimo form tracciato che ha ricevuto `focusin`, `pointerdown` o `input`. All'avvio è il primo con `data-wi-save-bar-dirty`, altrimenti il primo in ordine DOM.
- **Form bloccati** (`$locked` in `resource/form.php`, `READONLY` in `scheduler/form.php`) non ricevono `data-wi-save-bar`. Non esiste un `ignore` a livello di form.
- Ogni ricalcolo dei bottoni smette di osservare gli originali spariti (`unobserve`) e osserva quelli nuovi.

### B. Quali bottoni copiare e come seguirli

- **Candidati:** vedi sez. 1, Bottoni copiati (Q12).
- **Esclusi:** `closest('.modal, .offcanvas, [data-wi-save-bar-ignore]')` e i bottoni non visualizzati, controllati con `checkVisibility()` senza opzioni; se il metodo manca, con `getClientRects().length > 0`.
- **(a) Un MutationObserver per ogni originale:**
  - attributi `disabled`, `aria-disabled`, `class`, `type`, `hidden` e `data-wi-save-bar-ignore`;
  - `characterData` e `childList` su `subtree`;
  - aggiorna la copia in O(1) con `syncProxy`;
  - se cambiano `type`, `hidden` o `data-wi-save-bar-ignore`, programma anche il ricalcolo dell'elenco dei bottoni.
- **(b) Il MutationObserver sul form** è quello unico della sez. 2.2 (Q11). Rifà l'elenco dei bottoni solo quando la mutazione tocca un bottone o l'attributo ignore. L'aggiornamento è raggruppato nel rAF.
- **(c) Un ResizeObserver per candidato:** dice se il bottone è visualizzato o no, anche fuori dalla fascia, per esempio quando un antenato passa a `display:none`. `checkVisibility()` si chiama solo lì.
- **(d) IntersectionObserver:** solo per sapere se l'originale è "dentro la fascia" (D).

### C. Le copie nell'isola

- Una `Map` originale→copia. La copia è un nodo stabile: si crea solo per gli originali nuovi, si toglie solo per quelli spariti e non si ricrea mai. Sostituire la copia tra `mousedown` e `mouseup` fa perdere il click, come verificato su Chrome 152.
- **Copia ripulita** (`proxyButton`):
  - via `id`, `name`, `value`, tutti i `form*` (`form`, `formaction`, `formmethod`, `formtarget`, `formnovalidate`) e tutti gli `on*`;
  - `type="button"`;
  - nessun `data-*`, né sul bottone né sui figli (Q13), così un handler delegato su `document` non parte due volte;
  - i figli perdono l'`id`;
  - classi: solo le `btn*`, più `disabled` e `wi-save-bar-btn`, senza `btn-check` e senza `wi-submit`.
- L'etichetta si riscrive solo se cambia l'innerHTML ripulito. In CSS i figli della copia hanno `pointer-events: none` (sez. 4, Varie).
- **Disabilitata** se `original.disabled`, oppure se l'originale ha la classe `disabled`, oppure `aria-disabled="true"`. `check()` non tocca la copia: la copia segue l'originale.
- **Click:** se il form è in `submitting` non fa nulla, altrimenti chiama `original.click()`. L'invio parte quindi dal bottone vero, con il suo `name`, il suo `onclick` e gli handler delegati.

### D. Quando il bottone originale conta come visibile

- IntersectionObserver con `threshold: [0]` e `rootMargin` = `-TOPpx 0px -BANDpx 0px`:
  - TOP è `#topbar.offsetHeight` (50px oggi), letto a ogni rilettura della fascia; 50 se `#topbar` manca;
  - BAND è la fascia, letta dal token `--wi-save-bar-band` (sez. 4, Fascia). Non esiste una costante in JS.
- Finché almeno un originale tocca la fascia, `scroll` passivo e `resize` si raggruppano nel rAF. Si misura con `getBoundingClientRect()`, con il confronto scritto inline: un originale è visibile se `top >= TOP - 1` e `bottom <= innerHeight - BAND + 1`.
- Tutti gli originali visibili: nessuna copia serve. Zero bottoni candidati: `dirty-label` se il form è sporco, altrimenti `hidden`.
- **Perché non `threshold: 1`:** con zoom o DPR frazionari il rapporto si blocca a 0,99 (Chromium 40656485 e 40788495, W3C IntersectionObserver #477).
- **Perché serve la riserva in fondo:** sulle pagine lunghe, allo scroll massimo, Salva sta a 70px dal fondo, sempre dentro la fascia. Senza riserva l'isola non sparirebbe mai (misurato).

### E. Stati

Lo stato sta in `data-wi-state` su `.wi-save-bar` ed è calcolato da una funzione pura (tabella degli stati).

| Stato | Quando | Cosa si vede |
|---|---|---|
| `hidden` | form attivo pulito e originali tutti visibili, oppure pulito senza bottoni, oppure prima del primo IO e della prima istantanea | niente |
| `buttons` | form attivo pulito, almeno un originale nella fascia | solo le copie |
| `dirty-buttons` | form attivo sporco, almeno un originale nella fascia | etichetta e copie |
| `dirty-label` | form attivo sporco, originali tutti visibili oppure zero bottoni | solo l'etichetta |
| `submitting` | invio non annullato di un form tracciato | niente, isola `inert` |

- L'etichetta segue solo il form attivo. Con il form attivo pulito e un altro sporco non c'è etichetta (`buttons` o `hidden`). L'avviso di uscita (G) considera invece tutti i form.

### F. Invio

- **Listener che gira per ultimo.** Un listener `submit` in cattura su `window`, a ogni invio, toglie e rimette un listener `submit` in bolla su `window`. Quello in bolla gira quindi dopo tutti gli altri e vede `defaultPrevented` definitivo. È il modello di `FormSubmitObserver` di Turbo.
- **Invio non annullato di un form tracciato:** passa a `submitting` nello stesso task. L'isola si nasconde e diventa `inert`, e l'avviso di uscita si aggiorna nello stesso handler.
- **Invio che naviga la finestra con un altro form sporco:**
  - vale per qualsiasi form, tracciato o no, che naviga la finestra, cioè senza `target=_blank` e senza `method=dialog`. Si usano `formtarget` e `formmethod` del bottone che invia quando ci sono, come fa il browser (Q3);
  - se un ALTRO form tracciato è sporco e non in `submitting`, parte subito un `confirm(labels.confirmOther)`;
  - **Annulla:** `preventDefault()` e `loadingSpinner(false)`, perché l'`onsubmit="loadingSpinner()"` del form l'ha già acceso. Niente `submitting`;
  - **OK:** il form inviato, tracciato o no, si segna come in invio; l'isola passa a `submitting` e `preventPageExit` si toglie subito, senza un secondo avviso. Al ritorno dalla bfcache vale 3 J;
  - il verso opposto non chiede nulla: un form non tracciato compilato non blocca l'invio del form tracciato;
  - un form inviato in AJAX con `preventDefault()` non chiede nulla.
- Uno script che chiama `new FormData(form)`, come un'anteprima AJAX, non cambia lo stato.
- **Invio da script:** chi usa `form.submit()` chiama prima `wiSaveBar?.reset(form)`. `form.submit()` non genera `submit` e la lib non lo intercetta.

### G. Avviso di uscita

- `preventPageExit` resta registrato su `beforeunload` solo se almeno un form tracciato è sporco e non in `submitting`.
- Si aggiorna nello stesso handler durante l'invio, altrimenti nel rAF del ricalcolo.
- Link e chiusura della scheda usano il dialogo nativo.
- Dopo un POST fallito l'avviso è armato dal caricamento. Se l'utente non ha ancora interagito con la pagina (nessuna sticky activation), il browser può saltarlo. È accettato.

### H. Salvataggi AJAX

- `send.js` non cambia. `formUpload` non ha chiamanti e `postData` risolve `undefined` anche in caso di errore, quindi la lib non può sapere da sola se il salvataggio è riuscito.
- Il chiamante chiama `wiSaveBar?.reset(form)` nella callback di successo.
- Limite: le modifiche fatte durante la richiesta risultano salvate.

### I. Submit con `onclick`

- Viene copiato come gli altri: la copia chiama `original.click()`, quindi l'`onclick` parte.
- La sua callback chiama `reset(form)`, dopo il successo AJAX o prima di `form.submit()`.

### J. Tasto Indietro (pagina ripristinata dalla cache)

- Su `pageshow` con `persisted: true`:
  - se un form era in `submitting`: lo spinner si spegne con `loadingSpinner(false)`, poi `location.replace(location.href.split('#')[0])`. È sempre una GET, quindi niente re-POST e niente doppio inserimento da un form "nuovo";
  - altrimenti si ricalcola lo stato.
- Contesto: dal 2025 Chrome mette in bfcache anche le pagine `no-store`. Le pagine nate da POST non vanno mai in bfcache in Chrome, Firefox e WebKit su https.

### K. Sovrapposizioni (z-index)

- z-index 7, dal token `--wi-save-bar-zindex` in cima a `save-bar.css`.
- **Sopra:** il contenuto (massimo 5).
- **Sotto:** `#sidebar` (8), `#topbar` (9), datepicker (10), dropdown (1000), Select2 (1051), icon picker (1090), `#loading-spinner` (1100) e i modal.
- La sidebar offcanvas su mobile vive al livello 8, con il backdrop dentro `#sidebar`. Il menu mobile aperto copre quindi l'isola senza JS: niente codice per `#sidebar.show`.
- L'isola sta nella colonna di `#content`: parte a 80px da sinistra sopra i 768px.
- I popover di EditorJS e delle tabelle sono gestiti in CSS (sez. 4, Stati). Resta un limite per i popup non elencati in contesti di sovrapposizione bassi.

### L. Focus e accessibilità

- **Scroll-padding** (sez. 4, Fascia): il campo a fuoco resta sopra la fascia e sotto la topbar, anche nello scroll della validazione nativa (WCAG 2.4.11).
- **Tastiera virtuale.** L'isola si nasconde, con `data-wi-save-bar-keyboard` su `.wi-save-bar`, quando:
  - un campo di testo ha il focus: `textarea`, `contenteditable`, oppure `input` che non sia checkbox, radio, button, submit, reset, file, range o color;
  - e in più vale `matchMedia('(pointer: coarse)')` oppure `visualViewport.height < 0,75 × innerHeight` (Q15).
  - Si ascoltano `focusin` e `focusout` su `document`, così è coperta anche la ricerca di Select2 fuori dal form. La decisione si prende nel rAF dopo il `focusout`, quindi passando tra due campi l'isola non ricompare.
- **`inert`:**
  - tutta l'isola è `inert` in `hidden` e in `submitting`;
  - in `dirty-label` è `inert` solo il gruppo delle copie;
  - se l'elemento attivo sta in una zona che diventa `inert` (fuori da `submitting`), il focus passa all'originale con `focus({ preventScroll: true })`;
  - niente `aria-hidden`.
- **Regione `role="status"`:** creata all'avvio, `visually-hidden`, fuori da ogni zona `inert`. Riceve `labels.unsaved` al passaggio pulito→sporco e si svuota al passaggio sporco→pulito.
- **Doppio invio.** Il doppio click del mouse è coperto dallo spinner (1100) che si mette sopra l'isola. L'invio ripetuto da tastiera è bloccato dalla guardia `submitting`.

### M. POST fallito

- Con `data-wi-save-bar-dirty` l'etichetta compare dalla prima callback di IntersectionObserver.
- L'avviso di uscita resta armato fino a un invio non annullato o a `reset()`.

### Alternative scartate

- `data-wi-save-bar-submit` per scegliere i bottoni: la regola di sez. 1 basta.
- `ignore` a livello di form: un form bloccato non riceve l'attributo.
- `formdata` come segnale di invio: parte anche con `new FormData(form)`.
- `queueMicrotask` per far girare il listener per ultimo: non basta contro i listener registrati dopo.
- Ricaricare senza forzare la GET al ritorno dalla bfcache: rischia un re-POST.
- `requestSubmit(originale)` dalla copia: salterebbe `onclick` e handler delegati.

## Sezione 4 — Aspetto e layout dell'isola

### Markup

Il JS lo crea in fondo a `<body>`:

```html
<div class="wi-save-bar" data-wi-state="hidden" role="group" aria-label="{labels.group}" inert>
  <div class="wi-save-bar-island">
    <span class="wi-save-bar-label"><i class="bi bi-circle-fill" aria-hidden="true"></i> {labels.unsaved}</span>
    <div class="wi-save-bar-actions"><!-- copie .wi-save-bar-btn --></div>
  </div>
</div>
<div class="visually-hidden" role="status"></div>
```

- La regione `role="status"` è sorella dell'isola e non è mai `inert`.
- `data-wi-save-bar-keyboard` va su `.wi-save-bar`.
- `.wi-save-bar-island` non ha `aria-hidden`.

### Posizione

- **`.wi-save-bar`:**
  - `position: fixed`, `left: 80px` (0 a ≤768px), `right: 0`;
  - `bottom: calc(env(safe-area-inset-bottom, 0px) + var(--wi-save-bar-offset))`;
  - `padding-inline: 20px` a tutte le larghezze, allineata alle card;
  - flex centrato;
  - `pointer-events: none`;
  - `z-index: var(--wi-save-bar-zindex)`.
- **`--wi-save-bar-offset`:** 16px, 12px a ≤768px. `env()` resta sempre nel calcolo, per le barre dei gesti di Chrome Android 135+ edge-to-edge.
- **`.wi-save-bar-island`:** `pointer-events: auto`; flex; `align-items: center`; `gap: .5rem`; `flex-wrap: wrap`; `justify-content: center`; `max-width: 100%`; `padding: .5rem`.
- **`.wi-save-bar-label`:** `padding-inline: .75rem`, uguale al padding orizzontale dei bottoni.
- **`.wi-save-bar-actions`:** flex; `flex-wrap: wrap`; `justify-content: center`; `gap: .5rem`; `min-width: 0`; `max-width: 100%`.
- **`.wi-save-bar-btn`:** `max-width: 100%`, senza `nowrap` né ellissi. Le etichette vanno a capo come negli originali.
- Con le etichette "Salva" e "Salva e aggiungi":
  - a 1280px l'isola sta nella colonna del contenuto (80px + 20px) e dista 16px dal fondo;
  - a 768px sta a 0 + 20px e dista 12px dal fondo;
  - a 320px i bottoni vanno su due righe al massimo, senza scroll orizzontale.
- Limite: un'isola su tre righe, con etichette lunghissime, può uscire dalla fascia.

### Aspetto

- Sfondo `var(--bs-body-bg)` in entrambi i temi, così le copie appaiono come gli originali.
- Testo nel colore del body.
- Bordo `var(--bs-border-width) solid var(--wi-save-bar-border-color)`.
- `box-shadow: var(--bs-box-shadow-lg)` e `border-radius: var(--bs-border-radius-xxl)`.
- `--wi-save-bar-border-color` vale `var(--bs-border-color)` in tema chiaro e `rgba(var(--bs-emphasis-color-rgb), .3)` in tema scuro (contrasto 2,68:1).
- **Etichetta:** un pallino `bi-circle-fill` in `var(--bs-warning)` con `aria-hidden`, poi il testo con `font-weight: 500`. Nessun separatore.
- **Correzione globale in `header.css` della lib, tema scuro:**
  - `.btn-dark:disabled`: sfondo e bordo tornano a `var(--bs-btn-bg)` e `var(--bs-btn-border-color)`;
  - `.btn-outline-dark:disabled`: sfondo `transparent`, bordo `var(--bs-btn-border-color)`.

### Stati

| `data-wi-state` | Regole |
|---|---|
| `hidden`, `submitting`, oppure `[data-wi-save-bar-keyboard]` | `visibility: hidden`, `opacity: 0`, `transform: translateY(12px)` |
| `buttons` | `.wi-save-bar-label` con `display: none` |
| `dirty-buttons` | etichetta e azioni |
| `dirty-label` | `.wi-save-bar-actions` con `display: none` |

- Lo stato iniziale `hidden` usa `visibility`, non `display: none`, così l'isola entra con la transizione.
- **Popup degli editor aperti:** `body:has(.ce-popover--opened, .tc-popover--opened, [data-wi-save-bar-hide-when-open]) .wi-save-bar` riceve `visibility: hidden`, `opacity: 0` e `transition: none`, e `.wi-save-bar-island` riceve `pointer-events: none`.
  - `data-wi-save-bar-hide-when-open` è il gancio pubblico per i widget terzi.
  - Quill è escluso: la toolbar della lib non ha picker.
- **Transizioni:**
  - `opacity` e `transform` in 200ms;
  - `visibility` con ritardo di 200ms in uscita e di 0 in entrata;
  - i passaggi tra stati visibili (per esempio `buttons` → `dirty-buttons`) sono istantanei;
  - con `prefers-reduced-motion: reduce` si anima solo l'opacità.
- Accettato: `#content` ha `transition: all`, quindi la riserva in fondo si anima in 300ms al caricamento, sotto la piega.

### Fascia e spazio in fondo

- **Token della fascia:**
  ```css
  @property --wi-save-bar-band { syntax: '<length>'; inherits: true; initial-value: 96px; }
  :root { --wi-save-bar-band: 96px; }
  ```
  Un solo valore, senza media query. Un valore non valido torna a 96px grazie a `@property`.
- **Rilettura in JS** (funzione della fascia, sez. 3 D), all'avvio e sul `resize`:
  - si fa il parse con `/^(\d+(?:\.\d+)?)px$/`, con ripiego a 96;
  - il `rootMargin` si costruisce dal numero;
  - `new IntersectionObserver` sta in un `try/catch` che riprova con 96;
  - si rilegge sul `resize` raggruppato nel rAF.
- Il JS mette solo la classe `html.wi-save-bar-on`, quando esiste almeno un form tracciato. Nessuna variabile scritta da JS.
- **Riserva:**
  ```css
  @media screen {
    html.wi-save-bar-on {
      --wi-save-bar-reserve: calc(var(--wi-save-bar-band) + env(safe-area-max-inset-bottom, 0px));
      scroll-padding-bottom: var(--wi-save-bar-reserve);
    }
  }
  #content { padding-bottom: calc(20px + var(--wi-save-bar-reserve, 0px)); }
  ```
- **App, `app/view/components/backend/layout/header.php:229`:** il `min-height` diventa `calc(100vh - (50px + 22.5px + 1rem + 20px) - var(--wi-save-bar-reserve, 0px))`, così le pagine corte non hanno scroll vuoto, in qualsiasi ordine di rilascio.
- **`header.css` della lib:** `html { scroll-padding-top: 50px }` globale, accanto a `#topbar`. `save-bar.css` usa solo la proprietà del lato inferiore.
- In stampa la riserva non esiste, perché sta dentro `@media screen`.

### Testi

- Restano in italiano. Il runtime JS di traduzione nel backend esiste (`head.php:37-43`), ma nessuna parte dell'interfaccia backend è tradotta, e le copie mostrano comunque "Salva".
- `window.wiSaveBar.labels = { group, unsaved, confirmOther }`, sovrascrivibile dal sito come `Billing.modalText`. Si legge in modo pigro, alla costruzione dell'isola, quindi un sito può cambiarla dopo il caricamento della lib e prima dell'avvio.
  - `group`: il nome accessibile dell'isola, di default "Barra di salvataggio";
  - `unsaved`: "Operazione non salvata";
  - `confirmOther`: il testo del `confirm()` per le altre modifiche non salvate (sez. 3 F), di default "Ci sono modifiche non salvate in un altro modulo della pagina. Continuare e perderle?".
- **Percorso futuro**, se il backend verrà tradotto: `getNestedValue(translations) ?? defaultTranslations ?? italiano`. Mai `__t()`, che su una chiave mancante scrive `console.error` e mostra la chiave grezza.

### Varie

- I token `--wi-save-bar-zindex`, `--wi-save-bar-band`, `--wi-save-bar-offset` e `--wi-save-bar-border-color` stanno su `:root` in cima a `save-bar.css`, non in `tokens.css`, che è riservato ai bordi.
- Il MANIFEST della lib riceve la voce `components.save_bar` (sez. 5, Da sapere prima; Documentazione).
- `@media print { .wi-save-bar { display: none } }`.
- `.wi-save-bar-btn > * { pointer-events: none }`.
- Il passaggio del focus resta quello della sez. 3 L.
- L'anello di focus di `btn-dark` in tema scuro (1,26:1) è già chiuso su main della lib (3dee8d3, 33ecf8f). Nessun lavoro qui.

### Alternative scartate

- Fascia scritta come costante in JS: non si può adattare dal CSS del sito.
- Media query sulla fascia: basta un valore, e la riserva segue da sola.
- Separatore tra etichetta e bottoni: rumore visivo.
- Voce per l'isola in `tokens.css`: è riservato ai bordi.

## Sezione 5 — Integrazione lato app

### Da sapere prima

1. **Route POST dell'account: prerequisito già soddisfatto.** Il gruppo `/backend/account/` aveva solo la route GET, quindi "Modifica dati" e "Modifica password" rispondevano 404. La route è già su main: commit 559d6d02, `app/config/routes/route.backend.php:82`. Il piano della barra controlla solo che ci sia.
2. **Cambio di comportamento: sparisce il doppio Salva.** Se un `formLayoutSchema()` contiene già un `Submit` chiamato `upload`, il footer non aggiunge più il suo Salva (5.2). Oggi ResidenzaResource (immobili) e TeamMemberResource (agliati) mostrano due Salva.
3. **La lib registra la barra nel MANIFEST** con la voce `components.save_bar`. La scelta iniziale di non registrarla si basava su un'idea sbagliata: il MANIFEST elenca anche componenti propri, come `components.deferred`.

### 5.1 Attributi sul `<form>`

- **Nuovo metodo** `Wonder\App\Support\AttributeString::render(array $attributes, array $reserved = []): string`. La classe si dichiara già "parser/serializer", ma oggi ha solo `parse()` e `has()`.
- **Regole:**
  - `true` stampa l'attributo senza valore;
  - `false` e `null` lo omettono. `null` va omesso per forza: la lib guarda la presenza, quindi `data-wi-save-bar-dirty=""` segnerebbe il form sporco;
  - un array diventa i valori uniti da uno spazio: ogni valore si ripulisce dagli spazi ai bordi e si salta se resta `''`. `"0"` resta, a differenza di `View\Component::renderAttributes()`, che usa `array_filter` e lo toglie; anche questa differenza va nel PHPDoc;
  - i valori che non sono scalari né Stringable si saltano, anche dentro un array (Q20). La differenza da `View\Component::renderAttributes()` si scrive nel PHPDoc;
  - escape con `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`;
  - le chiavi si ripuliscono dagli spazi; le chiavi vuote si saltano;
  - le chiavi riservate si confrontano in minuscolo;
  - il risultato non ha spazio iniziale, come `View\Component`: lo aggiunge chi lo stampa (Q5). Un array vuoto dà `''`.
- **`ResourceFormLayoutRenderer::render()`** riceve l'opzione `'attributes' => []`, vuota di default. Si stampa sul `<form>` dopo gli attributi fissi, con `AttributeString::render($attrs, ['id', 'method', 'enctype', 'action', 'onsubmit', 'class'])`. Le chiavi riservate vengono ignorate, per non duplicarle.
- Il renderer non sa nulla della barra.
- L'helper privato `attributes()` usato da Card e `wrapField` non cambia (con `null` stampa ancora `key=""`), quindi l'HTML dei componenti resta identico. Unificarlo cambierebbe l'output dei `null`: fuori da questo lavoro.
- **Scartato:** leggere `$form->getAttr()` sul `<form>`.

### 5.2 Form delle Resource (attiva per tutte)

- In `app/view/pages/backend/resource/form.php`, subito dopo `$locked` (L9), una riga calcolata una volta:
  ```php
  $saveBarAttributes = ['data-wi-save-bar' => !$locked, 'data-wi-save-bar-dirty' => !$locked && !empty($FORM_ERRORS)];
  ```
- **Ramo con layout:** `'attributes' => $saveBarAttributes` nelle opzioni del renderer.
- **Ramo legacy** (L47-50): stampa con `AttributeString::render()`, niente markup a mano. È ancora vivo: circa 7 Resource del core non hanno `formLayoutSchema()`.
- **La condizione è `!$locked`, non `!$readonly`:** un form in sola lettura parziale ha campi modificabili e un Salva. Contano solo i campi modificabili, perché quelli in sola lettura sono `disabled` e non entrano nell'istantanea.
- Un salvataggio riuscito fa sempre redirect (PRG). Un salvataggio fallito ridisegna il form con `FORM_ERRORS`.
- L'id `resource-layout-form` non cambia. Lo script della griglia varianti di ProductModelResource in gestionale usa `document.querySelector('#resource-layout-form [name="sku"]')`.
- **Doppio Salva.** Se il layout contiene già un `Submit` con name `upload`, il ramo layout non aggiunge il Salva di default nel footer. L'avviso di sola lettura resta.
  - Lo decide `ResourceFormLayoutRenderer::hasSubmit(Form $form, string $name = 'upload'): bool`, pubblica e statica.
  - Visita Container, Card e Accordion, anche annidati. Non entra in Modal e QuickCreate.
  - Con un nome diverso, per esempio `upload-add`, cerca solo quello.
  - Non conta un Submit dentro una Card con `visibleWhen` né dentro un Accordion che non sia `expanded(true)` (Q14). Così il footer resta e il form non perde il Salva raggiungibile.
  - Sistema ResidenzaResource (immobili) e TeamMemberResource (agliati) senza toccarle.

### 5.3 Viste del core

- **Scheduler** (`scheduler/form.php:4`):
  - l'attributo si mette con la stessa espressione che la vista già usa, `(empty($READONLY) || !empty($READONLY_EDITABLE))`, che equivale a `!$locked`;
  - `data-wi-save-bar-dirty` si calcola come in `resource/form.php`: la stessa condizione e `!empty($FORM_ERRORS)` (Q18);
  - la condizione dell'`onsubmit="return false"` della vista si allinea alla stessa espressione (Q18). Senza, con `READONLY` più `READONLY_EDITABLE` il salvataggio sarebbe sempre annullato e l'isola resterebbe su "non salvato". Oggi il caso non si presenta.
- **Caricamento massivo dei media** (`media/upload-massive.php`): no.

### 5.4 Pagine che non sono Resource

**Quando una pagina nasce "modificata".** Quando la scrittura non è avvenuta, non quando c'è un ALERT.
- `user()` restituisce un nuovo campo: `'written' => false` nel `$RETURN` (`user.php:247-257`).
- `$RETURN->written = true` subito dopo `sqlInsert('user')` (L398) e `sqlModify('user')` (L573).
- Il codice ALERT non basta. `user()` a volte scrive e poi imposta un avviso (hook, consensi 900, mail 908), e a volte fallisce senza un codice riconoscibile (upload 920/923, `functionValidate`). Una lista di codici "puliti" sbaglierebbe in una delle due direzioni.

**Account, form profilo** (`app/view/pages/backend/account/index.php:26`):
- riceve `data-wi-save-bar`;
- riceve `data-wi-save-bar-dirty` se `PROFILE_DIRTY = isset($_POST['modify']) && isset($UPLOAD) && empty($UPLOAD->written)`;
- con la password di conferma errata (905), il ramo `else` (`app/http/backend/account/index.php:41-43`, dentro l'`if` di L30-44) imposta solo `$ALERT` e lascia `$UPLOAD` non impostato. La pagina mostra i valori del DB, quindi il form è pulito;
- la password di conferma ha `->attribute('data-wi-save-bar-ignore')` (`AccountPageSchema.php:35`). Chrome la compila dopo il primo gesto e non deve contare.

**Account, form password** (`app/view/pages/backend/account/index.php:69`):
- non è tracciato;
- se il profilo è sporco, inviarlo fa partire `confirmOther` (3 F vale per qualsiasi form che naviga). "OK" scarta le modifiche al profilo;
- il verso opposto non chiede nulla.

**Gestione utenti** (`user/manage.php:19`):
- `UserManagementPageController::render()` (privato, L73) riceve l'ultimo parametro `bool $dirty = false`;
- `submit()` (privato, L43) passa `empty($upload->written)` nella chiamata di L70;
- si copiano "Salva e aggiungi" (`upload-add`) e "Salva" (`upload`).

**File di configurazione** (`config/configuration-file.php:15`): sporco se `!empty($ERRORS)`.

**Esclusi** (nessun attributo):
- login, recupero, ripristino e impostazione password;
- "Esegui update" in home;
- filtri GET;
- dashboard dello scheduler ed eliminazione riga di ScheduleResource;
- form di `RendersButtonPostForm` (`Button::post`);
- sql-download;
- demo frontend in `app/build/src/docs/*`.

**Modal:** nessuna modifica. `.modal` è escluso dalla lib.

**Come si scrivono gli attributi.** Nei `<form>` scritti a mano gli attributi fissi sono letterali (`data-wi-save-bar`) e quelli condizionali sono inline (`<?= $dirty ? ' data-wi-save-bar-dirty' : '' ?>`), senza `AttributeString`.

### 5.5 Segreti e autocompletamento

- In `SecurityResource::formSchema()` si aggiunge `->autocomplete('new-password')` a:
  - `klaviyo_api_key`, `brevo_api_key` e `mail_password`, che oggi si vedono;
  - `google_oauth_client_secret` e `apple_oauth_private_key`, oggi commentati nel layout.
- Senza, Chrome può inserirci la password di login: il form risulterebbe modificato e si rischierebbe di salvare la password sbagliata.
- Si usa `->autocomplete()`, non `->attribute('autocomplete', ...)`.
- Niente `ignore`: sono valori veri e le loro modifiche contano.
- `new-password` non diventa il default di `InputPassword`: login e recupero hanno bisogno di `current-password`.
- **Regola per i docs:**
  - una password in un form tracciato che non è la credenziale di login dell'utente dichiara `->autocomplete('new-password')`;
  - `data-wi-save-bar-ignore` si usa solo per la conferma della propria password (Account).

### 5.6 Layout

- `header.php:229` sottrae `--wi-save-bar-reserve` dal `min-height` (sez. 4, Fascia). Con la lib vecchia la variabile vale 0 e il layout resta com'è.
- Nessun'altra modifica al layout dell'app.
- Un sito che sovrascrive tutto `header.php` non sottrae la riserva e ha al massimo 96px di scroll in più. Va documentato.

### 5.7 Script, AJAX e due aggiunte alla lib

- **Invio da script:** `wiSaveBar?.reset(form)` prima di `form.submit()`. **AJAX:** nella callback di successo. Oggi nel backend non ci sono chiamanti.
- **`absorb(el)`:**
  - solo per scritture non causate dall'utente (riempimenti di init, precompilazioni AJAX);
  - sul contenitore più stretto che contiene i campi scritti, mai sul form, perché dopo la prima interazione assorbirebbe modifiche vere;
  - mai dopo una modifica dell'utente.
- **Aggiunte alla lib:**
  - `setDynamicSearch` chiama `wiSaveBar?.absorb()` su `#container-{id}` solo al successo del caricamento iniziale, dopo `createCheckbox` (`set.js:71-74`). La ricerca digitata (`inputSearch`) non la chiama;
  - jstree, sul `ready.jstree`, chiama `absorb()` sul contenitore `[data-wi-tree-values]` (Q9);
  - `initGoogleMapsPlace` chiama `wiSaveBar?.absorb(input)` subito dopo `input.disabled = false` (Q17). Se l'utente clicca prima che Places sia pronto, il campo abilitato non conta come modifica.
- Quill ed EditorJS sono gestiti dentro la lib (sez. 2.5).
- **Chiarimento del contratto:** `click` e `keyup` restano rete di sicurezza, con listener su `document` in cattura. Ogni segnale ricalcola tutti i form tracciati (sez. 2.2).
- **Repeater dell'app** (`Themes/Bootstrap/Form/Components/Repeater.php:752-760`), svuotamento dell'ultima riga:
  - checkbox e radio: `checked = false` e poi un `change` con `bubbles: true`;
  - tutti gli altri campi passano da `window.wiRepeaterSetFieldValue(input, '')`, che emette già `input` e `change` con `bubbles: true` (L1108-1109) e gestisce AutoNumeric;
  - il ramo AutoNumeric di `wiRepeaterSetFieldValue` (L1100) oggi chiama `clear()` o `set()` senza eventi: dopo, emette anche lui un `change` con `bubbles: true`.
- Questo sistema anche `check()` sui campi obbligatori. Aggiunta, rimozione e spostamento delle righe restano coperti dal MutationObserver, senza eventi nuovi.

### 5.8 Button::post dentro un form Resource

- Il parser HTML scarta un form annidato, quindi un `Button::post` dentro un form Resource è già rotto oggi. Si aggiunge una nota nei docs accanto a `Button::post` (`docs/app/concetti/componenti/README.md`), sul modello della nota sui Modal. Nessun avviso nel renderer.
- Un form di `Button::post` fuori dal form Resource ha la sua conferma. Se il form Resource è sporco, dopo quella arriva anche `confirmOther`. La doppia domanda è accettata e documentata (Q19).

### 5.9 Moduli e siti

- **immobili** (`view/pages/backend/immobili/form.php:27`). Gli attributi si scrivono letterali nell'opzione `attributes`, senza dipendere da `$saveBarAttributes` dell'app. Un'app vecchia ignora l'opzione: nessun attributo e nessun errore (I20).
  ```php
  $saveBar = empty($READONLY) || !empty($READONLY_EDITABLE);
  'attributes' => ['data-wi-save-bar' => $saveBar, 'data-wi-save-bar-dirty' => $saveBar && !empty($FORM_ERRORS)]
  ```
  Oggi Immobile non ha `syncSchema`, quindi non è mai in sola lettura: la prima condizione serve solo per il futuro. `FORM_ERRORS` arriva già.
- **agliati.** `git rm custom/modules/immobili/view/pages/backend/immobili/form.php`: è identico a quello del pacchetto e non è mai stato modificato. Non si usa `publish:module`, che salta i file esistenti. Un `--force` sull'albero intero cancellerebbe circa 15 viste personalizzate.
- **Regola per i docs sugli override:**
  - override identico al pacchetto → si cancella;
  - override personalizzato → si aggiungono a mano i due attributi, oppure si ripubblica solo quel file con `php forge publish:module immobili pages/backend/immobili/form.php --force`;
  - mai `--force` su tutto l'albero.
- **gestionale, rsvp e le altre Resource di moduli e siti:** la barra arriva da sola, anche con un Submit nel layout, grazie a 5.2.
- **Moduli copia-incolla e siti legacy:** nessuna patch. Una nota nei docs spiega come attivarla a mano.
- Il pattern `preventFormSubmit` del sito cco è superato. Si documenta.

### 5.10 Rilascio

L'ordine e i due casi per i siti esistenti sono in "Rilascio e compatibilità".

### 5.11 Docs

L'elenco completo, per app, `AGENTS.md`, skill, lib, MANIFEST e CHANGELOG, è in "Documentazione".

### 5.12 Test lato app

Sostituito dalla sezione 6.4 (test PHP) e dai casi I della 6.5. Il test del serializzatore sta in `tests/App/Support/AttributeStringTest.php`, non in `tests/Support/`.

### Alternative scartate

- ALERT come segnale di "sporco" per l'account: sostituito da `PROFILE_DIRTY` con `user()->written`.
- Correggere la route dell'account dentro questo lavoro: è già su main.
- Avviso nel renderer per `Button::post` dentro un form: basta la nota nei docs.
- `new-password` di default su `InputPassword`: rompe il login.
- `publish:module --force` per agliati: cancella viste personalizzate.

## Sezione 6 — Test

Ogni caso è scritto così: cosa si fa → cosa ci si aspetta (decisione che verifica). I codici servono al piano TDD per ricavare i task:
- **U:** unit test della lib;
- **B:** test browser della lib;
- **P:** test PHP dell'app;
- **I:** integrazione;
- **M:** prove manuali.

### 6.1 Principi

- **Prima il test, poi il codice.** Ogni caso si scrive, si vede fallire sul codice di oggi e poi si implementa. I casi di regressione P9, P13, I1 e I15 (tranne l'ultimo punto, che vale solo dopo) fanno eccezione: devono passare prima e dopo la modifica.
- **Cinque livelli, ognuno per quello che sa fare meglio:**
  1. **U**, unit test della lib in `node:vm`, dentro `npm test`: la logica che non dipende dal layout (ricalcolo, punto di partenza, stati, API) e le correzioni di `set.js`;
  2. **B**, test browser della lib con Playwright e Chrome, fuori da `npm test`, come `test/deferred-content.browser.test.cjs`: `FormData` vera, copie, geometria, CSS, focus, invio, dialoghi;
  3. **P**, test PHP dell'app con `tests/harness.php`: serializzazione degli attributi, renderer, JS del repeater, attributi delle pagine non Resource;
  4. **I**, integrazione su tre siti Herd: new.test (new-site), ecommerce.test (ecommerce-site, che ospita gestionale) e immobili.test (immobili-site). rsvp-site e agliati si provano durante il rilascio;
  5. **M**, prove manuali fatte dall'utente, perché gli strumenti a disposizione non le riproducono.
- **Niente dipendenze nuove.** Playwright non entra in `devDependencies`: si installa una volta in `$HOME/.cache/wonder-tooling/playwright` e si passa con `PLAYWRIGHT_MODULE`, come nel test browser che c'è già. Niente PHPUnit, niente jsdom.
- **Gli interni si guardano solo nella lib.** `data-wi-state`, `wi-save-bar-on`, le variabili CSS interne e le funzioni interne di `saveBar.js` li leggono solo i test della lib. I test dell'app e dei siti guardano gli attributi pubblici e il comportamento visibile.
- **Nessun caso I passa con errori nuovi in console.** Ogni pagina si confronta con la console della stessa pagina prima della modifica.
- **Un caso che non riproduce la sua condizione non è "passato".** Si segna "non riprodotto" con il motivo. Vale per B4, B35, B39 e I16. I13 si segna "non applicabile in Chrome" se la pagina non entra nella bfcache.
- **Limiti accettati, senza test** (vedi Limiti noti):
  - modifiche fatte durante un salvataggio AJAX;
  - popup non elencati sotto l'isola;
  - override integrale di `header.php` (I14 misura solo il caso dell'header copiato);
  - pagina senza JS;
  - avviso di uscita saltato senza interazione;
  - zoom del browser, coperto solo tramite la densità dello schermo (B4);
  - isola su tre righe, documentata da B43.
- **Un limite con un test che lo documenta:** uscire entro circa 400ms dalla prima modifica in EditorJS non dà l'avviso (2.5). I12 prova che dopo 500ms l'avviso c'è.
- **Stile:** quello dei test esistenti, con `node:assert/strict`, commenti brevi in italiano e un file per area.

### 6.2 Lib: unit test in node:vm

Il nuovo file `test/backend-save-bar.test.cjs` entra in coda alla catena di `npm test`. Carica `src/build/backend/js/form/saveBar.js` in un contesto `vm`, con un DOM finto costruito dentro questo test (vedi Principi di codice).

**Cosa offre il DOM finto:**
- nodi con `addEventListener`, `closest`, `matches`, `classList`, `getBoundingClientRect`, `focus` e `inert`. Il `Nodo` di `test/backend-tree-values.test.cjs` non basta;
- uno stub di `$`/`jQuery` che registra le chiamate `.on()` delegate. Senza, il caricamento dà ReferenceError;
- eventi come oggetti semplici con `isTrusted` impostabile, perché l'`Event` di Node ha `isTrusted` sempre false;
- `CustomEvent` nel contesto;
- una `FormData` finta che restituisce le voci preparate dal test e conta quante volte viene costruita;
- `requestAnimationFrame` con una coda che il test svuota a mano;
- stub di `IntersectionObserver`, `ResizeObserver` e `MutationObserver` che registrano opzioni e chiamate;
- un `getComputedStyle` configurabile.

La logica pura (tabella degli stati, confronto delle istantanee) sta in funzioni separate, raggiungibili dal contesto vm. La fascia si verifica con U25 e U26, il filtro delle classi con B22. Tutto ciò che clona nodi o legge attributi reali delle copie si prova nel browser (6.3).

**Contratto e markup**
- **U1** Dopo il caricamento esistono `wiSaveBar.changed/reset/isDirty/absorb` e `labels {group, unsaved, confirmOther}`. `disarm` non esiste e `HTMLFormElement.prototype.submit` è lo stesso di prima del caricamento. (sez. 1 Contratto, 2.3, Storico delle decisioni)
- **U2** Le quattro funzioni chiamate su un form senza `data-wi-save-bar`, o su `null` → nessun errore, `isDirty` false, nessun evento. (sez. 1 Rilasci)
- **U3** Pagina senza form tracciati → niente `.wi-save-bar`, nessun osservatore, nessuna classe su `<html>`, nessun listener su `document`, nessun lavoro in rAF. (sez. 1 Rilasci, 2 Costo)
- **U4** Il sito cambia `wiSaveBar.labels.unsaved` dopo il caricamento della lib ma prima dell'avvio → l'isola usa il testo nuovo. (sez. 4 Testi)
- **U5** Struttura dopo l'avvio (sez. 4 Markup, 3 L):
  - `.wi-save-bar` con `data-wi-state="hidden"`, `role="group"`, `aria-label` uguale a `labels.group` e `inert`;
  - dentro, `.wi-save-bar-island` senza `aria-hidden`;
  - il pallino dell'etichetta con `aria-hidden="true"`;
  - la regione `role="status"` sorella dell'isola, in fondo al `body`, mai `inert`.
- **U6** Il passo di avvio chiamato due volte → una sola isola, e un segnale produce un solo ricalcolo. (sez. 3 A)

**Avvio in `setUpPage`**
- **U7** Si carica `pageSetUp.js`, si assegnano al contesto gli stub di `createCard`, `setInput`, `checkInput`, `setUpBootstrap` e `setUpJquery`, poi `await context.setUpPage()`. Ordine registrato: `createCard`, `setInput`, `checkInput`, `setUpBootstrap`, `setUpJquery`, `setUpSaveBar`, evento `loaded`. (sez. 3 A, Q6)
- **U8** Il passo dell'isola lancia un errore → `loaded` parte comunque, l'errore finisce in console e non c'è isola. (sez. 3 A, Q16)
- **U9** Mancano `ResizeObserver`, `IntersectionObserver`, `MutationObserver` o il supporto a `inert` → nessuna isola, nessun errore, `loaded` parte. (sez. 1 Rilasci, Q16)

**Tempi del ricalcolo**
- **U10** Un evento `input` → nessuna `FormData` costruita dentro l'evento; una sola dopo lo svuotamento della coda rAF. (sez. 2.2)
- **U11** Dieci segnali misti nello stesso frame (`input`, `change`, `click`, `keyup`, `.trigger('change')` di jQuery ricevuto dal listener delegato, `changed()`) → una sola `FormData` per form. (sez. 2.2)
- **U12** Un `click` su `document` fuori da ogni form, come la conferma del repeater nel modal → si ricalcolano tutti i form tracciati. (sez. 5.7)
- **U13** Si emettono `FilePond:addfile`, `FilePond:removefile`, `FilePond:reorderfiles`, `FilePond:processfile`, `FilePond:processfilerevert`, `FilePond:updatefiles`, `wi-repeater-row-delete`, `wi-repeater-row-restore`, `wi:quick-create:created` e `autoNumeric:rawValueModified` → ognuno programma un ricalcolo. (sez. 2.2)
- **U14** Dopo l'interazione, uno script aggiunge, toglie o sposta una riga e mette `disabled` su un campo, senza eventi → un solo ricalcolo al frame successivo, e il form è sporco. (sez. 2.2, Q11)
- **U15** Mutazioni senza bottoni dentro il form (un `div` aggiunto, `class` o `style` cambiati) → nessuna nuova ricerca dei bottoni e nessun `observe` in più. Un submit aggiunto → una sola nuova ricerca. (sez. 3 B)

**Punto di partenza e stato**
- **U16** Una scrittura nello stesso tick dell'avvio (Quill, un listener su `loaded`) → finisce nel punto di partenza, il form è pulito. (sez. 3 A)
- **U17** Tabella dell'interazione: per ogni evento si controlla se una scrittura da script fatta dopo sporca il form. (sez. 2.3)
  - chiudono la fase iniziale (poi → sporco): `keydown`, pressione o clic, tocco, `paste`, `drop` e `input`, tutti con `isTrusted` true;
  - non la chiudono (poi → nuovo punto di partenza, pulito): `el.click()`, `.trigger()` di jQuery e `dispatchEvent`, tutti con `isTrusted` false; `focus` fidato (quello di `el.focus()` in Chrome), `scroll` e passaggio del mouse fidati.
- **U18** Dopo l'interazione: paese IT→FR→IT, con la provincia azzerata nel frattempo → il form resta sporco. (sez. 2.3)
- **U19** L'utente cambia il campo A, poi `absorb(contenitoreB)` dopo il riempimento di B → A resta sporco, B no. `absorb(el)` con `el` fuori da ogni form tracciato → nessun effetto. (sez. 2.3, 5.7)
- **U20** Si modifica un valore e poi lo si riporta a mano a quello iniziale → il form torna pulito. (sez. 2, Principio)
- **U21** Form con `data-wi-save-bar-dirty` e istantanea invariata → `isDirty` true fino a `reset(form)`; dopo il reset è false e l'istantanea attuale è il nuovo punto di partenza. (sez. 2.4, 3 M)
- **U22** `changed(el)` dopo che l'utente ha annullato la modifica → il form è pulito, perché `changed()` non forza lo stato. (sez. 1 Contratto)
- **U23** Il form passa da pulito a sporco e ritorno → `wi:save-bar:change` sul form con `detail.dirty` true, poi false. Due ricalcoli sporchi di fila → nessun evento in più. (sez. 1 Contratto, Q2)
- **U24** Due form, il secondo con `data-wi-save-bar-dirty` → all'avvio è attivo il secondo. Poi `pointerdown` sul primo → il primo; `focusin` sul secondo (Tab) → il secondo; `input` sul primo → il primo. Senza form sporchi all'avvio è attivo il primo. (sez. 1, 3 A)

**Fascia, visibilità e stati** (stub di IO e `getBoundingClientRect` finti)
- **U25** `--wi-save-bar-band` letto come `96px`, `120.5px`, `6rem`, `''`, `abc`, `-5px` → `rootMargin` `-50px 0px -96px 0px` (topbar da 50px), `-120.5px` nel secondo caso e 96 in tutti gli altri. Se il costruttore di IO lancia un errore, si riprova con 96. (sez. 4 Fascia)
- **U26** Originale con `top = 49` e `bottom = innerHeight - 96 + 1` → visibile; con `bottom` 2px più in basso → non visibile. (sez. 3 D)
- **U27** Tabella degli stati → `data-wi-state` atteso (sez. 3 D-E, 4 Stati):
  - pulito, tutti gli originali visibili → `hidden`;
  - pulito, un originale nella fascia → `buttons`;
  - sporco, un originale nella fascia → `dirty-buttons`;
  - sporco, tutti visibili oppure zero bottoni → `dirty-label`;
  - pulito, zero bottoni → `hidden`;
  - form attivo pulito e un altro form sporco → nessuna etichetta (`buttons` o `hidden`);
  - invio non annullato → `submitting`.
- **U28** L'isola resta `hidden` finché non arrivano sia la prima callback di IO sia la prima istantanea. (sez. 3 A)

**Correzioni in `set.js`** (si estende `test/backend-set-input.test.cjs`)

I finti di oggi non bastano e si riscrivono:
- Quill finto: salva l'handler di `text-change` e lo emette con sorgente `user` o `api`, registra gli `addEventListener` dell'editor e parte con innerHTML `<p><br></p>`;
- EditorJS finto: chiama `onChange(api)`, con `api.saver.save()` che restituisce una Promise con `{time, version, blocks[id]}`;
- stub di `document.getElementById` e di `wiSaveBar`, e `setTextarea` vera invece del noop;
- il test diventa una IIFE async che aspetta i microtask.

Casi:
- **U29** Quill: nessun listener `keydown` o `DOMNodeInserted` sull'editor; la textarea cambia solo su `text-change`. (sez. 2.5)
- **U30** Quill vuoto → textarea `''`, non `<p><br></p>`. (sez. 2.5)
- **U31** Quill, `text-change` dell'utente → `wiSaveBar.changed()` chiamato; se `wiSaveBar` manca, nessun errore. (sez. 2.5)
- **U32** Quill, `setContents` e `clipboard.dangerouslyPasteHTML` con sorgente `api` → la textarea si aggiorna e `changed()` viene chiamato. (sez. 2.5, Q10)
- **U33** EditorJS: `onChange` con contenuto uguale all'iniziale (`id`, `time` e `version` diversi) → la textarea non cambia. (sez. 2.5)
- **U34** EditorJS: una modifica vera → la textarea si riscrive e `changed()` viene chiamato. (sez. 2.5)
- **U35** EditorJS: modifica e poi ritorno al contenuto iniziale → la textarea torna identica al JSON originale. (sez. 2.5)
- **U36** `setDynamicSearch`, con stub di `$.ajax` (chiama `success` subito), `createCheckbox` e `checkInput`: al successo del caricamento iniziale `absorb(#container-{id})` viene chiamato una volta, dopo `createCheckbox`. Si carica anche `src/build/backend/js/form/input.js`: la ricerca digitata (`inputSearch`) non chiama `absorb`. (sez. 2.3, 5.7)
- **U37** jstree: `ready.jstree` → `absorb` del contenitore `[data-wi-tree-values]`. Il caso va in `test/backend-tree-values.test.cjs` ed estende il finto di jstree che c'è già. (sez. 2.3, Q9)

**Catena di `npm test`**
- **U38** `test/backend/file-references.test.cjs` oggi è ignorato da git e fuori dalla catena. Si sposta in `test/backend-file-references.test.cjs`, che la regola `!test/*.test.cjs` traccia (in alternativa `git add -f`). Il percorso di `set.js` diventa `path.join(__dirname, '../src/build/backend/js/form/set.js')` e `node test/backend-file-references.test.cjs` si aggiunge allo script `test` di `package.json`. Protegge il manifest `__wi_files` che l'isola legge. (sez. 2.1)
- **U39** Test del MANIFEST: `JSON.parse` di `MANIFEST.json`; `components.save_bar` ha le chiavi `js`, `css`, `global` (`"window.wiSaveBar"`), `contract`, `docs` e `internal_classes` (`[".wi-save-bar*", "html.wi-save-bar-on"]`); i percorsi di `js`, `css` e `docs` esistono. `global` e `internal_classes` sono chiavi nuove per il MANIFEST; `MANIFEST.schema.json`, citato da `$schema`, non esiste e non si valida. (sez. 5, Da sapere prima; Documentazione)

### 6.3 Lib: test browser (Playwright e Chrome)

Il file è `test/backend-save-bar.browser.test.cjs`. Segue lo schema del test esistente: `require(process.env.PLAYWRIGHT_MODULE || 'playwright')`, Chrome installato scelto con `CHROME_CHANNEL`, modalità headless.

**Installazione e lancio.** Si scrivono anche nei docs della lib e nel suo `AGENTS.md`, accanto al test esistente.
- Una volta sola, con la rete: `PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm i --prefix "$HOME/.cache/wonder-tooling/playwright" playwright-core`. Non scarica browser: `channel: 'chrome'` usa Chrome.app.
- Verifica: `node -e "require(process.env.PLAYWRIGHT_MODULE).chromium"`.
- Lancio: `PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" node test/backend-save-bar.browser.test.cjs`.

**Fixture.** È scritta dentro il `.cjs`, come in deferred-content, perché `test/*` è ignorato da git tranne i `*.test.cjs`. I file di `node_modules` si risolvono dal checkout principale della lib, oppure si fa `npm ci` nel worktree. La pagina sta su `https://save-bar.test/`, servita con `page.route`.
- **CSS:**
  - Bootstrap e bootstrap-icons (con i font sotto `fonts/`; altrimenti si accetta che le icone manchino);
  - Select2 con `src/build/backend/css/lib/select2.css` e bootstrap-datepicker;
  - `src/build/backend/css/input.css` (icon picker);
  - poi `tokens.css`, `header.css` e `save-bar.css` da `src`.
- **JS:**
  - jQuery e `bootstrap.bundle.min.js` (modal e dropdown);
  - Select2 e bootstrap-datepicker vero, che calcola da solo il suo z-index;
  - AutoNumeric con `src/build/global/js/form/autonumeric.js` (opzioni del backend, compreso "€");
  - `check()` da `src/build/backend/js/form/input.js`;
  - `loadingSpinner` e `preventPageExit` veri da `utility.js`;
  - FilePond e jstree;
  - solo `saveBar.js` da `src`.
- **Pagina:** `#topbar` da 50px, `#sidebar` con menu mobile, `#content` con card e un form lungo con i submit "Salva" (`upload`) e "Salva e aggiungi" (`upload-add`). Ogni form ha `onsubmit="loadingSpinner()"`, come quello di `ResourceFormLayoutRenderer`. L'avvio chiama `setUpSaveBar()` come fa `setUpPage` (Q6). Il repeater dell'app non c'è: B13 simula solo le operazioni sul DOM.

**Regole comuni**
- **POST:** la route si trattiene, cioè non si chiama `fulfill` finché le asserzioni non sono finite. Il bottone che ha inviato si legge dal corpo con `request.postDataBuffer()`. Lo stato nello stesso task si legge con un listener nella pagina.
- **Dialoghi:**
  - prima di una chiusura serve un gesto fidato (`click` o `keyboard.type`);
  - si usa `page.waitForEvent('dialog', { timeout })` e si controlla `dialog.type()`. "Nessun dialogo" vuol dire timeout scaduto senza dialog;
  - per `confirm()` si usa `page.once('dialog', d => d.accept())` oppure `d.dismiss()`, controllando `d.message()`.
- **Geometria:** si misura dopo `await Promise.all(document.getAnimations().map(a => a.finished))`, perché `#content` ha `transition: all 0.3s`. Solo nei casi di geometria si può iniettare `*{transition:none!important}`, mai in B2 e B46.
- **Tema scuro:** `html[data-bs-theme="dark"]`, non `prefers-color-scheme`.

**Avvio e visibilità**
- **B1** Carico la pagina con Salva in vista → in nessun frame l'isola risulta visibile (si campionano stato e opacità a ogni frame). (sez. 3 A, 4 Stati)
- **B2** Scorro finché Salva finisce sotto la fascia → `buttons`; torno su → `hidden`. Non si misura il tempo. Si controlla (sez. 3 D, 4 Stati):
  - `transitionDuration` di `0.2s` e un'animazione attiva, in entrambi i versi;
  - durante l'uscita `visibility` resta `visible`; all'entrata è `visible` subito;
  - il passaggio `buttons` → `dirty-buttons` avviene senza animazione.
- **B3** Pagina lunga scorsa fino in fondo → Salva sta sopra la fascia e l'isola è nascosta. Dopo le animazioni: `html.wi-save-bar-on`; padding-bottom di `#content` = 20px + 96px; `scroll-padding-bottom` 96px; `scroll-padding-top` 50px. (sez. 3 D, 4 Fascia)
- **B4** Con `deviceScaleFactor` 1.25, un IntersectionObserver di controllo con `threshold: 1` sul bottone deve dare un rapporto sotto 1: è la prova che il bug di Chromium c'è. Poi si ripetono B2 e B3 → stessi risultati. Se il rapporto è 1, il caso è "non riprodotto". (sez. 3 D)
- **B5** Salva fuori fascia sta in una card nascosta con `display:none` → la copia sparisce; quando la card riappare, la copia torna. (sez. 3 B)
- **B6** Attributi osservati sull'originale (sez. 3 B-C):
  - `hidden` o `type="button"` sull'originale → copia tolta;
  - `data-wi-save-bar-ignore` sull'originale o su un suo contenitore → copia tolta;
  - attributo rimosso → copia di nuovo presente;
  - un originale nuovo → copia creata e osservata;
  - le altre copie restano lo stesso nodo.
- **B7** Fascia (sez. 4 Fascia):
  - `--wi-save-bar-band` a `6rem` e ad `abc` → `getComputedStyle` restituisce 96px (conversione di `@property`; il ripiego in JS è coperto da U25);
  - a `8rem` → fascia di 128px: la copia compare quando Salva entra negli ultimi 128px;
  - token cambiato a pagina aperta, poi un resize → vale la fascia nuova.

**Rilevamento con widget veri**
- **B8** Scrivo in un campo e poi cancello → sporco, poi pulito. La regione `role=status` riceve "Operazione non salvata" e poi si svuota. (sez. 2 Principio, 3 L)
- **B9** Select2 e datepicker veri: cambio il valore → sporco; lo riporto a quello iniziale → pulito. (sez. 2.2)
- **B10** Scrittura silenziosa dentro un `onclick`, senza eventi → sporco, grazie al click. Un handler sul campo chiama `stopPropagation()` e scrive un valore senza eventi → sporco, perché l'ascolto su `document` è in cattura. (sez. 2.2, 5.7)
- **B11** Scrittura da script dopo l'interazione, seguita da `wiSaveBar.changed(el)` → sporco. (sez. 1 Contratto)
- **B12** Passo il mouse su un campo, scorro la pagina e chiamo `el.focus()`, poi uno script scrive un valore → pulito. Dopo un clic vero, una scrittura da script → sporco. (sez. 2.3)
- **B13** Repeater, solo operazioni sul DOM (sez. 2.1-2.2):
  - aggiungo una riga → sporco; la tolgo → pulito;
  - scambio due righe → sporco; le rimetto in ordine → pulito;
  - elimino una riga con "annulla" disponibile, cioè con i campi `disabled` → sporco; la ripristino → pulito;
  - le stesse operazioni fatte da script, senza click → stessi esiti, grazie al MutationObserver (Q11).
- **B14** AutoNumeric vero con le opzioni del backend, su un prezzo vuoto: il mouse sopra mostra "€" → pulito; un submit annullato lascia i valori non formattati → pulito; cambio il valore numerico → sporco. (sez. 2.1)
- **B15** Scelgo un file con `setInputFiles` → sporco; lo tolgo → pulito. Un input file vuoto presente all'avvio non conta. (sez. 2.1)
- **B16** Un listener `formdata` scrive un manifest, come `__wi_files` di FilePond (sez. 2.1):
  - il manifest cambia → sporco;
  - i file salvati finiscono di scaricarsi e il manifest resta uguale → pulito;
  - riordino i file già salvati → sporco; ripristino l'ordine → pulito.
- **B17** Checkbox con lo stesso nome riordinate nel DOM, come fa la ricerca delle DynamicCheck → pulito; una spunta in più → sporco. (sez. 2.1)
- **B18** Esclusioni, con un modal legacy scritto a mano dentro il form (i modal dell'app sono già fuori):
  - modifico campi dentro quel modal, dentro `[data-wi-save-bar-ignore]` e un campo con l'attributo stesso → pulito;
  - un nome presente solo nel contenitore ignorato → tolto dall'istantanea;
  - lo stesso nome dentro e fuori da un contenitore ignorato → tenuto intero, con un avviso in console.
  (sez. 2.1, Q1)
- **B19** Uno script del sito chiama `new FormData(form)`, come un'anteprima AJAX → nessun `submitting`, stato invariato, avviso di uscita attivo. (sez. 3 F)
- **B20** Prestazioni, sempre come confronto A/B: la stessa fixture con e senza `data-wi-save-bar`. Scenari:
  - digito 20 caratteri in un form da circa 5000 campi (albero grande più repeater);
  - 20 clic fuori dai campi;
  - espansione completa di un albero da 2000 nodi;
  - aggiunta di 50 righe di repeater;
  - un caricamento FilePond con animazione;
  - tempo da `DOMContentLoaded` a `loaded`.

  Con `addInitScript`, `FormData` viene avvolta in una sottoclasse che misura il costruttore. I task lunghi si misurano con `PerformanceObserver('longtask')`. Ogni scenario si ripete 5 volte e si stampano mediana e massimo. Il rallentamento della CPU ×4 (CDP `Emulation.setCPUThrottlingRate`) è facoltativo e solo informativo. Soglia: Q4. (sez. 2 Costo, 3 B)

**Copie e click**
- **B21** Elenco delle copie in ordine di pagina. Restano fuori i submit in un `.modal` legacy, in `.offcanvas`, con `[data-wi-save-bar-ignore]` e quelli non visualizzati. Entrano `<button>` senza `type`, `<input type="submit">` e un submit fuori dal form collegato con `form="id"`. (sez. 1 Bottoni copiati, 3 B, Q12)
- **B22** Pulizia della copia. L'originale ha `id`, `name`, `value`, `formaction`, `formmethod`, `formtarget`, `formnovalidate`, `form`, `onclick`, `data-bs-toggle="modal"`, `data-wi-qc-endpoint`, `class="btn btn-primary wi-submit btn-check disabled"` e un figlio con `id`. Atteso (sez. 3 C, 4 Aspetto, Storico delle decisioni, Q13):
  - la copia ha `type=button`, nessuno di quegli attributi, e i figli senza `id`;
  - le classi sono `btn btn-primary disabled wi-save-bar-btn`;
  - nessun `data-*` sulla copia né sui figli;
  - un handler jQuery delegato su `.wi-submit` parte una sola volta al clic sulla copia;
  - `check()` non abilita né disabilita la copia per conto suo: la copia segue l'originale.
- **B23** L'originale cambia etichetta o stato disabilitato, come fa `check()` sui campi obbligatori → la copia si aggiorna entro il frame successivo e resta lo stesso nodo. (sez. 3 B-C)
- **B24** Click sulla copia di "Salva e aggiungi" → un solo POST, con `upload-add` nel corpo. Click sull'icona `<i>` dentro la copia → POST con lo stesso nome. (sez. 3 C)
- **B25** Premo il mouse sulla copia, cambio l'etichetta dell'originale e rilascio → un solo POST. (sez. 3 C)
- **B26** Originale disabilitato in tre modi (attributo `disabled`, classe `disabled`, `aria-disabled="true"`) → per ognuno, click, Invio e Spazio sulla copia → nessun POST. Con `aria-disabled="false"` la copia è attiva. (sez. 3 C)
- **B27** Doppio invio (sez. 3 C, K, L):
  - Invio due volte sulla copia mentre la richiesta è trattenuta → un solo POST;
  - doppio click del mouse sulla copia → un solo POST;
  - con `#loading-spinner` visibile, `elementFromPoint` al centro dell'isola → vince lo spinner.
- **B28** Submit con `onclick` (sez. 3 I):
  - l'`onclick` dell'originale incrementa un contatore e chiama `reset(form)` → dopo un click sulla copia il contatore vale 1, il form è pulito e non c'è `beforeunload`;
  - variante: l'`onclick` annulla l'invio → nessun POST.

**Invio e uscita**
- **B29** Un altro listener `submit`, registrato dopo l'isola, chiama `preventDefault()` → il form non va in `submitting`, l'isola resta e l'avviso di uscita resta attivo. (sez. 3 F)
- **B30** Invio non annullato → `submitting` già nello stesso task, isola nascosta e `inert`. Stesso esito con Invio in un campo di testo (POST con il nome del bottone di default) e con `requestSubmit(original)`. (sez. 3 F)
- **B31** Avviso di uscita, sempre dopo un gesto fidato (sez. 3 E, G):
  - form sporco e navigazione → dialogo `beforeunload`;
  - form pulito, o dopo un invio non annullato → nessun dialogo;
  - form 1 sporco, poi click nel form 2 pulito → nessuna etichetta, ma alla navigazione il dialogo compare;
  - controllo anche con un evento sintetico annullabile (`defaultPrevented`), valido perché `preventPageExit` chiama `preventDefault`.
- **B32** Conferma con più form (sez. 3 F, 5.4, 5.8):
  - due form tracciati, il primo sporco, invio il secondo → `confirm()` con il testo di `labels.confirmOther`, e lo spinner è acceso in quel momento;
  - "Annulla" → nessun POST e `#loading-spinner` con `d-none`, tramite `loadingSpinner(false)`;
  - "OK" → POST e nessun `beforeunload`;
  - secondo form non tracciato (come il form password o un filtro GET) → `confirm()`;
  - verso opposto: form non tracciato compilato e invio del form tracciato pulito → nessuna domanda;
  - `target=_blank` o `method=dialog` → nessuna domanda; `formtarget="_blank"` o `formmethod="dialog"` sul bottone che invia → nessuna domanda, anche se il form naviga (Q3);
  - form inviato in AJAX con `preventDefault` → nessuna domanda;
  - ricerca GET nella topbar con il form sporco → domanda; "Annulla" → nessuna navigazione e spinner spento;
  - form di `Button::post` con la sua conferma → prima la sua conferma, poi `confirmOther` (Q19).
- **B33** Uno script chiama `wiSaveBar.reset(form)` e poi `form.submit()` → nessun avviso. (sez. 3 F)
- **B34** Ritorno dalla cache simulato con `pageshow` e `persisted: true`: se il form era in `submitting` → spinner spento e richiesta GET all'URL senza `#`; altrimenti nessuna navigazione e stato ricalcolato. (sez. 3 J)
- **B35** Ritorno dalla cache vero (sez. 3 J):
  - Chromium si lancia con `ignoreDefaultArgs: ['--disable-back-forward-cache']`;
  - la fixture è servita da un piccolo server `node:http` su 127.0.0.1, senza no-store e senza `page.route`, che registra i corpi dei POST;
  - precondizione: `pageshow.persisted === true`, altrimenti "non riprodotto";
  - invio, poi Indietro → richiesta GET e un solo POST registrato.

**Focus e accessibilità**
- **B36** Tab con l'isola `hidden` o `dirty-label` → il focus non entra nelle copie. La regione `role=status` non sta mai in una zona `inert`. (sez. 3 L)
- **B37** Focus su una copia, poi scorro finché l'originale torna visibile → il focus passa all'originale e `scrollY` non cambia. (sez. 3 L)
- **B38** Focus su un campo in fondo, poi `reportValidity()` su un obbligatorio vuoto → il campo resta sopra la fascia e sotto la topbar. (sez. 3 L, 4 Fascia)
- **B39** Touch, con `hasTouch` e `isMobile` (sez. 3 L, Q15):
  - precondizione: `matchMedia('(pointer: coarse)').matches === true`, altrimenti "non riprodotto";
  - con `locator.tap()` su input di testo, `textarea`, `contenteditable` (Quill, EditorJS) e ricerca di Select2 fuori dal form → isola nascosta;
  - checkbox, file, range, color e submit → isola visibile;
  - blur → l'isola torna;
  - passaggio tra due campi di testo → in nessun frame l'isola ricompare;
  - senza `pointer: coarse`, `visualViewport.height` sotto 0,75 × `innerHeight` con un campo di testo a fuoco → isola nascosta; sopra la soglia → visibile.

**Aspetto e livelli**
- **B40** `elementFromPoint` al centro dell'isola (sez. 3 K):
  - con modal, sidebar, dropdown, Select2, datepicker, icon picker o spinner aperti → vince l'altro elemento;
  - a 375px con il menu mobile aperto → vince il menu;
  - senza nessuno di questi → l'isola.
- **B41** `.ce-popover--opened`, `.tc-popover--opened` o `data-wi-save-bar-hide-when-open` nel body → isola nascosta subito, senza transizione, e non cliccabile. (sez. 4 Stati)
- **B42** Posizione, misurata dopo le animazioni, con le etichette "Salva" e "Salva e aggiungi" (sez. 4 Posizione):
  - a 1280px l'isola sta nella colonna del contenuto (80px + 20px) e dista 16px dal fondo;
  - a 768px sta a 0 + 20px e dista 12px dal fondo;
  - a 320px i bottoni vanno su due righe al massimo, l'isola resta nella fascia e non c'è scroll orizzontale.
- **B43** Un'etichetta molto lunga → nessuno scroll orizzontale; l'uscita dalla fascia è accettata come limite documentato. (sez. 4 Posizione)
- **B44** Tema scuro: sfondo `--bs-body-bg` e bordo più chiaro. `btn-dark` e `btn-outline-dark` disabilitati hanno un colore diverso dallo sfondo. (sez. 4 Aspetto)
- **B45** `emulateMedia({ media: 'print' })` → isola `display:none` e nessuna riserva in fondo. (sez. 4 Fascia, Varie)
- **B46** `reducedMotion: 'reduce'` → solo opacità, nessuno slittamento. (sez. 4 Stati)
- **B47** Pagina senza form tracciati → `scroll-padding-top` 50px presente, nessuna `.wi-save-bar`, nessuna riserva. (sez. 4 Fascia, 1 Rilasci)

### 6.4 App: test PHP

I file nuovi si aggiungono con `git add -f`, perché `tests/` è in `.gitignore`.

**`tests/App/Support/AttributeStringTest.php`** (sez. 5.1)
- **P1** `['data-wi-save-bar' => true]` → l'attributo senza valore.
- **P2** `false` e `null` → attributo assente. In particolare `data-wi-save-bar-dirty` a `null` non stampa `=""`.
- **P3** `['a', 'b']` → `"a b"`; `['a', '', ' b ']` → `"a b"`.
- **P4** Oggetto Stringable → stampato. Array annidato e oggetto non Stringable dentro un array → saltati, senza avvisi (Q20).
- **P5** `<`, `"`, `'`, `&` e UTF-8 non valido → escape con `ENT_QUOTES|ENT_SUBSTITUTE`.
- **P6** Le sei chiavi riservate `id`, `method`, `enctype`, `action`, `onsubmit` e `class`, anche con maiuscole o spazi (` ID `, `Class`) → ignorate. Una chiave non riservata con spazi → ripulita; una chiave vuota → saltata.
- **P7** Array vuoto → `''`. Nessuno spazio iniziale in nessun caso (Q5).
- **P8** Parità con `View\Component::renderAttributes()`, letta con Reflection, sulla stessa tabella di input scalari e Stringable → stesso risultato (anche lì nessuno spazio iniziale, Q5).

**`tests/Backend/Support/ResourceFormLayoutRendererTest.php`** (sez. 5.1-5.2)
- **P9** Lo stesso Form reso senza opzione, con `attributes => []` e con tutti i valori a `false` → `<form ...>` identico carattere per carattere a quello di oggi. Il test lo scrive come letterale prima della modifica.
- **P10** `attributes` con i due attributi della barra → stampati nello stesso tag, dopo `class`, preceduti da uno spazio aggiunto dal renderer.
- **P11** `attributes` con tutte e sei le chiavi riservate → ignorate; restano i valori fissi.
- **P12** `hasSubmit()` (sez. 5.2, Q14):
  - trova un `Submit` `upload` dentro Card, Accordion e Container annidati;
  - non lo trova dentro Modal e QuickCreate;
  - con il nome `upload-add` cerca solo quello;
  - un Submit in una Card con `visibleWhen` o in un Accordion non `expanded(true)` → non conta, e il footer resta.
- **P13** Regressione: una Card con `visibleWhen` e i layout dei test Card esistenti → output identico prima e dopo, perché l'helper privato `attributes()` non cambia.

**Repeater** (sez. 5.7). Un test nuovo in `tests/Themes/`, sul modello di `RepeaterAutonumericTest.php`. Estrae `wiRepeaterRemoveRow` dal render e la esegue in node con un DOM finto; senza node controlla il testo. È la prima copertura di questa funzione: nessun test di oggi la tocca.
- **P14** Svuoto l'ultima riga → ogni campo riceve un `change` con `bubbles: true`; i campi che non sono checkbox o radio passano da `wiRepeaterSetFieldValue(input, '')`, AutoNumeric compreso. Tolgo una riga che non è l'ultima → nessun `change` in più.

**Gestione utenti e autocompletamento** (sez. 5.4-5.5)
- **P15** Con Reflection (anche su metodo privato): la firma di `UserManagementPageController::render()` resta compatibile. I parametri di oggi non cambiano e `bool $dirty = false` è l'ultimo, facoltativo. In più, se la pagina si rende senza DB, si controllano gli attributi del form: `data-wi-save-bar` sempre, `data-wi-save-bar-dirty` solo con `$dirty`; altrimenti questo controllo passa a I8. Il campo `written` di `user()` richiede il DB e si verifica in I7, I8 e I18.
- **P16** Autocompletamento (sez. 5.5):
  - un `InputPassword` senza opzioni → render identico a oggi, senza `new-password`;
  - in SecurityResource i tre campi resi hanno `autocomplete` uguale a `new-password`, letto dallo schema se si costruisce senza DB (altrimenti in I10);
  - i due campi commentati (`google_oauth_client_secret`, `apple_oauth_private_key`) dichiarano `->autocomplete('new-password')`: si controlla sul testo del file.

**Lint e regressione**
- **P17** Controlli finali:
  - `php -l` su ogni file PHP toccato;
  - tutti i test esistenti con `for f in $(git ls-files tests | grep -v -e harness.php -e scheduler-integration.php); do php "$f" >/dev/null || echo "FALLITO $f"; done`;
  - il risultato di base si registra su main prima di iniziare. `scheduler-integration.php` resta fuori, perché crea un sito usa e getta e usa un DB;
  - nel worktree serve `vendor/`: `composer install` oppure un symlink al `vendor/` principale;
  - le suite di gestionale (74 file) e immobili (2 file) girano contro l'app modificata, con il prepend dell'autoloader verso `class/` del worktree. Per immobili `vendor/wonder-image/` è vuota: prima `composer install`, altrimenti "non eseguibile" con il motivo.

Le viste (`resource/form.php`, `scheduler/form.php`, account, gestione utenti, configuration-file) e `user()->written` richiedono il runtime o il DB. Non si crea un helper nuovo per testarle: le coprono lo snapshot I1 e i casi I2–I9 (Q7).

### 6.5 Integrazione

**Preparazione.** Tutto è reversibile e nei siti non si fa nessun commit. Prima di iniziare si annotano `git status --porcelain` e `readlink` di ogni cartella che verrà sostituita.
- **Base di confronto:** `git worktree add <scratch>/app-base main`. Niente `git stash`: il checkout dell'app contiene worktree annidati e cartelle non tracciate.
- **Lib:** si costruisce fuori dal repo con `npx webpack --output-path <scratch>/lib-dist`, perché `dist/` è tracciata. Si fa un backup di `assets/lib/wonder-image/dist` del sito e si copia con `cp -R` senza `-p`, così `Asset::version()` (filemtime) cambia `?v=`.
- **App, sito per sito**, con backup della copia prima di sostituirla:
  - new.test: `vendor/wonder-image/app` → symlink al worktree;
  - ecommerce.test: `vendor/wonder-image/app` → symlink al worktree; gestionale è già in symlink verso `packages/gestionale`;
  - immobili.test: `vendor/wonder-image/app` → symlink al worktree, e `vendor/wonder-image/immobili` → symlink al checkout del modulo, perché 5.9 tocca la sua vista;
  - `herd restart` dopo la sostituzione e dopo il ripristino.
- **Render da CLI:** lo script imposta `$GLOBALS['ROOT']` al sito e gira dalla sua root. Il prepend dell'autoloader va dopo il `require` del `vendor/autoload.php` del sito e si verifica con `ReflectionClass::getFileName`.
- **DB:** il DB di ogni sito deve essere attivo, perché `formLayoutSchema()` può leggere opzioni.
- **Utente di prova:** un admin locale per ognuno dei tre siti. Lo crea l'utente una volta (Gestione utenti) o un seed nel DB locale, con le credenziali in un file locale gitignorato. In alternativa l'utente fa il login a mano nel pannello Browser. La sessione dura 4 ore.
- **Account:** la route POST è già su main (commit 559d6d02). Se in un sito manca, i casi dell'account si segnano bloccati, non falliti.

**Regole per ogni caso I**
- **Console:** errori o avvisi nuovi rispetto alla stessa pagina "prima" → caso fallito.
- **`confirm()`:** prima dell'azione si sostituisce `window.confirm` con javascript_tool, in modo che registri il testo e restituisca true o false. Il dialogo vero è coperto da B32.
- **Avviso di uscita:** un clic sulla pagina, poi `navigate` senza `force`; il pannello Browser segnala "Leave site?".
- **Tema scuro:** `localStorage.setItem('theme','dark')` e ricarica; alla fine si rimette `'light'`.
- **Touch:** si controlla `matchMedia('(pointer: coarse)')`. Se è falso, il caso passa alle prove manuali.

**Regressione del markup**
- **I1** Snapshot dell'HTML completo, in sessione autenticata, con l'app di base e poi con quella modificata (sez. 5.1-5.6):
  - si normalizzano id casuali (`/_[a-z]{10}\b/`), token CSRF, orari e `?v=`;
  - pagine:
    - una Resource con layout, in creazione e in modifica;
    - 2-3 Resource legacy;
    - scheduler modificabile e `READONLY`;
    - account, gestione utenti e configuration-file;
    - una pagina `$locked` e una pagina lista;
    - ProductModelResource su ecommerce.test;
    - ResidenzaResource e ImmobileResource su immobili.test;
  - differenze attese, in lista chiusa:
    - `data-wi-save-bar` e `data-wi-save-bar-dirty` sul form;
    - il footer senza il Salva di default solo dove `hasSubmit()` è vero;
    - `autocomplete="new-password"` sui tre campi di SecurityResource;
    - il `min-height` di `header.php`;
  - qualsiasi altra differenza fa fallire il caso.

**Pagine nel browser** (pannello Browser, su desktop salvo dove detto)
- **I2** Resource con layout → `<form>` con `data-wi-save-bar` e senza `-dirty`; l'isola si comporta come in B2, B8 e B24. (sez. 5.2)
- **I3** Resource legacy del core senza layout → stessi attributi e stesso comportamento. (sez. 5.2)
- **I4** Form bloccato e sola lettura (sez. 5.2, 3 A, Q14):
  - `$locked` → nessun attributo e nessuna isola;
  - sola lettura parziale → attributo e isola;
  - sola lettura parziale con un Submit nel layout → avviso di sola lettura presente e un solo Salva;
  - Resource di prova temporanea, non committata, con il Submit in una Card nascosta da `visibleWhen` → il footer tiene il suo Salva, quindi c'è almeno un Salva cliccabile, nella pagina o nell'isola.
- **I5** POST fallito (sez. 5.2, 3 M, 3 G):
  - si provoca con un vincolo che esiste solo sul server, per esempio un valore duplicato su un campo unico, oppure togliendo `required` con javascript_tool, perché `check()` disabilita Salva sui campi obbligatori vuoti;
  - atteso: `data-wi-save-bar-dirty` ed etichetta subito;
  - dopo un clic sulla pagina l'avviso di uscita è attivo; senza quel gesto non è garantito (3 G);
  - POST riuscito → redirect e pagina pulita.
- **I6** Scheduler (sez. 5.3, Q18):
  - modificabile → isola;
  - `READONLY` → nessuna isola;
  - POST fallito → `data-wi-save-bar-dirty` ed etichetta;
  - `READONLY` con `READONLY_EDITABLE` → isola, e il salvataggio parte davvero perché l'`onsubmit` segue la stessa condizione. Oggi il caso non si presenta: si prova con una Resource temporanea, se serve.
- **I7** Account (sez. 5.4):
  - password di conferma errata (905) → pulito, valori del DB, nessun avviso di uscita;
  - password corretta ma `user()` fallisce, per esempio sull'upload (920) → sporco;
  - modifica riuscita → pulito;
  - profilo sporco e invio del form password → `confirm()` con il testo di `labels.confirmOther`;
  - verso opposto, form password compilato e invio del profilo pulito → nessuna domanda;
  - il form password non ha l'attributo;
  - l'autocompilazione di Chrome si prova in M1.
- **I8** Gestione utenti (sez. 5.4):
  - nuovo utente con errore → sporco; salvato → pulito;
  - salvato con l'avviso 908 (mail) → pulito;
  - upload fallito (920/923) o validazione fallita → sporco. Se serve, con un hook temporaneo su new-site, da non committare;
  - due copie, "Salva e aggiungi" e "Salva", in ordine di pagina; il click sulla copia di "Salva e aggiungi" porta al form nuovo, vuoto e pulito;
  - modifica di un utente API → pulito (970 corretto da ca40ba49: non deve più comparire).
- **I9** File di configurazione: errore → sporco; salvataggio riuscito → pulito. (sez. 5.4)
- **I10** SecurityResource: i tre campi segreti hanno esattamente `autocomplete="new-password"` e il form si apre pulito. Il comportamento di Chrome si prova in M1. (sez. 5.5)
- **I11** Repeater: svuoto l'ultima riga, che contiene Select2, un campo AutoNumeric, un campo con `visibleWhen` e FilePond (sez. 5.7):
  - campi vuoti, compreso AutoNumeric;
  - campi dipendenti coerenti;
  - isola sporca, e `check()` aggiorna Salva secondo i campi obbligatori;
  - console pulita.
- **I12** Widget veri. Per ognuno: apertura pulita, una modifica, console pulita, esito scritto (sez. 2, 2.3, 2.5, 4 Stati):
  - Select2 o datepicker cambiati → sporco; riportati al valore iniziale → pulito;
  - Quill: scrivo una lettera e la cancello → pulito;
  - Quill obbligatorio: scrivo e cancello → Salva disabilitato (cambio voluto, nel CHANGELOG);
  - record che nel DB ha `<p><br></p>` → si apre pulito;
  - EditorJS:
    - un'immagine che finisce di caricare o il mouse su una tabella → pulito;
    - una modifica → sporco;
    - una modifica e l'uscita dopo 500ms → avviso presente;
    - menu aperti su mobile → isola nascosta;
  - FilePond con immagini già salvate → pulito all'apertura;
  - jstree e DynamicCheck: all'apertura pulito; la ricerca che riordina → pulito;
  - CheckTree (regressione): chiudo e riapro un nodo con figli spuntati → pulito; spunto in un ordine e tolgo in un altro → pulito. I valori dell'albero sono checkbox nascoste che non si spostano (`set.js:505-557`);
  - quick-create: il modal sta nel `body`, quindi il suo Salva non viene copiato; una creazione → sporco;
  - campo telefono all'apertura → pulito;
  - Google Places: click prima che Places abiliti il campo → pulito (Q17); scelta di un indirizzo → sporco;
  - generatore di codici → sporco.
- **I13** Tasto Indietro dopo un salvataggio (sez. 3 J):
  - è una verifica di idoneità: dopo "Indietro" si leggono `performance.getEntriesByType('navigation')[0].type` e `notRestoredReasons`, oppure si usa DevTools › Application › Back/forward cache;
  - il backend risponde `no-store`, quindi ci si aspetta una pagina non ripristinata: il caso si segna "non applicabile in Chrome" e la copertura resta B34-B35;
  - se la pagina viene ripristinata → si ricarica in GET, senza un secondo inserimento.
- **I14** Layout (sez. 4 Fascia, 5.6, 1 Rilasci):
  - pagina corta con form → nessuno scroll vuoto, perché `header.php` sottrae la riserva;
  - pagina lista corta senza form → nessuna riserva, e altezza di `#content` identica a prima;
  - header sovrascritto da un sito (copia di quello attuale) → isola funzionante, al massimo 96px di spazio in più in fondo, nessuna sovrapposizione con il Salva;
  - app nuova e dist vecchia della lib → layout come oggi e console pulita.
- **I15** Lib nuova e app vecchia, cioè vendor originale (sez. 1 Rilasci, 2.5, 4 Aspetto, 4 Fascia):
  - nessuna isola e nessun errore;
  - tema chiaro invariato: snapshot visivo di una pagina lista e di un form;
  - bottoni attivi invariati in tema scuro;
  - scroll corretto al primo errore di validazione;
  - Quill obbligatorio scritto e cancellato → Salva disabilitato, come cambio voluto.
- **I16** Pagine vere a 375px e in tema scuro, solo per quello che la fixture non ha (sez. 3 K, 3 L, 4):
  - il menu mobile vero copre l'isola;
  - tema scuro con il CSS del sito;
  - precondizione del touch; se manca, il caso passa a M2.
- **I17** Pagine escluse → nessun `data-wi-save-bar` nell'HTML (sez. 5.3, 5.4):
  - login, recupero e impostazione password;
  - "Esegui update" in home;
  - filtri;
  - dashboard dello scheduler ed eliminazione riga di ScheduleResource;
  - form dei `Button::post`;
  - upload-massive e sql-download.
- **I18** Registrazione frontend su new-site → utente creato, nessun errore. Controlla che `written` non rompa chi usa `user()` fuori dal backend. (sez. 5.4)

**Moduli e siti**
- **I19** ecommerce.test, gestionale (sez. 5.2, 5.9, 2.3):
  - la griglia varianti di ProductModelResource trova ancora `#resource-layout-form [name="sku"]`;
  - apro un prodotto esistente, aspetto 3 secondi, apro e chiudo i pannelli varianti e sede senza modificare → pulito;
  - se risulta sporco, la correzione è un `absorb()` documentato nel modulo, non un cambio dell'isola.
- **I20** immobili.test (sez. 5.2, 5.7, 5.9):
  - app vecchia → nessun attributo, nessuna isola, nessun errore, perché l'app vecchia ignora l'opzione;
  - app nuova → `data-wi-save-bar` su `#immobile-resource-form`;
  - ResidenzaResource mostra un solo Salva, nella pagina e nell'isola;
  - POST con `FORM_ERRORS` → sporco;
  - record da feed (`lockFeedFields`, `noValidate`) → si apre pulito;
  - riga media svuotata → sporco, FilePond ricreato, console pulita.
- **I21** rsvp-site, al passo 3 del rilascio: una Resource con l'isola e un solo Salva. (sez. 5.9; Rilascio)
- **I22** agliati, al passo 5 del rilascio: dopo il `git rm` dell'override, TeamMemberResource mostra un solo Salva e l'isola. (sez. 5.9; Rilascio)

**Prove manuali (le fa l'utente)**

Gli strumenti di Claude non le riproducono: il pannello Browser non ha password salvate e manda i click come mouse, il simulatore iOS richiede Xcode, e un telefono non raggiunge `*.test`. Esito ammesso: "non verificato, con motivo".
- **M1** Chrome dell'utente, con la password salvata dell'utente di prova (sez. 5.4, 5.5):
  - account: Chrome compila la password di conferma → pulito;
  - SecurityResource: Chrome non inserisce la password di login nei campi segreti.
- **M2** Telefono vero tramite `herd share`, che apre un tunnel pubblico e quindi lo decide l'utente. Safari iOS e Chrome Android 135+ con barra dei gesti (sez. 4 Posizione, 3 L):
  - isola sopra l'area sicura;
  - tastiera aperta su un campo di testo → isola nascosta.
- **M3** Simulatore iOS, solo se Xcode viene installato, con la CA di Herd (`~/Library/Application Support/Herd/config/valet/CA/LaravelValetCASelfSigned.pem`) installata dall'utente: stessi controlli di M2.

**Dopo il rilascio** (checklist)
- new-site, immobili-site e rsvp-site: range `^2.1.2-alpha.*` e lock rigenerato.
- `npm ci` verde in CI.
- La dist del sito contiene `wiSaveBar`.
- La release di immobili è installata.

### 6.6 Criteri di completamento

Il lavoro è pronto per il rilascio quando:
1. `npm test` nella lib è verde. La catena comprende tutti i `test/**/*.test.cjs` non browser: U1–U39, compreso il test dei file di FilePond spostato con U38.
2. Il test browser B1–B47 è verde con Chrome in locale. B4, B35 e B39 possono risultare "non riprodotti", con il motivo. In B20 la mediana di ogni scenario rispetta Q4 con un margine.
3. La build fuori dal repo passa: `head.js` contiene `wiSaveBar` e `head.css` le regole `.wi-save-bar`. U39 è verde e `dist/` della lib non è stata toccata sul ramo.
4. P1–P17 sono verdi: `php -l` pulito, `composer dumpautoload` senza avvisi, stesso risultato di main sui test esistenti, suite di gestionale e immobili verdi contro l'app modificata.
5. La checklist I1–I20 è completata su new.test, ecommerce.test e immobili.test, con le eccezioni scritte. Inoltre:
   - M1–M3 sono fatte, oppure segnate "non verificato, con motivo";
   - l'esito di `rg "ql-editor"` su siti e moduli è scritto;
   - I21 e I22 si fanno ai passi 3 e 5 del rilascio, seguiti dalla checklist "Dopo il rilascio".
6. Docs e skill sono aggiornati e controllati file per file, secondo l'elenco di "Documentazione".
7. Il ripristino è verificato su new-site, ecommerce-site e immobili-site:
   - `git status --porcelain` uguale a quello annotato prima;
   - `readlink` di `vendor/wonder-image/*` uguale ai valori annotati;
   - `diff -r` tra il backup e la `dist` ripristinata;
   - `herd restart` fatto;
   - tema del pannello Browser rimesso a `light`;
   - hook e Resource temporanei tolti, worktree `app-base` rimosso.

## Rilascio e compatibilità

**Ordine di rilascio**
1. **Release della lib:** 2.1.2-alpha.17, sul canale alpha.
2. **Merge dell'app.** La route POST dell'account è già su main (commit 559d6d02) e non serve un commit a parte.
3. **Siti boilerplate:** new-site, immobili-site e rsvp-site portano `wonder-image` al range `^2.1.2-alpha.*` e rigenerano il lock. Oggi hanno `^2.1.1-alpha.*`, che non include la nuova alpha. Al termine si fa I21.
4. **Release di immobili**, con gli attributi letterali nella sua vista (5.9).
5. **agliati:** `git rm custom/modules/immobili/view/pages/backend/immobili/form.php` (5.9). Al termine si fa I22.

**Siti esistenti: due casi, da scrivere nei docs**
- **Range già `^2.1.2-alpha.*`:** basta `composer update`, perché `forge config` fa `npm install`.
- **Fuori range** (`^2.1.1-alpha.*`, `^2.0.x`, `^2.1.0`): `npm install wonder-image@^2.1.2-alpha.17`, poi il commit di `package.json` e del lock, perché la CI usa `npm ci`.

**Nessuna 2.1.2 stabile per questa funzione.** Porterebbe tutta la 2.1.x ai siti fermi alla 1.5.x. È una decisione separata.

**Compatibilità tra versioni**

| Lib | App | Risultato |
|---|---|---|
| nuova | nuova | isola attiva sui form tracciati |
| nuova | vecchia | nessun attributo, nessuna isola, nessun errore; restano le correzioni globali (Quill vuoto `''`, `btn-dark` disabilitato in tema scuro, `scroll-padding-top`) |
| vecchia | nuova | attributo inerte; `--wi-save-bar-reserve` vale 0 e `header.php` resta com'era; il doppio Salva sparisce comunque (5.2) |
| qualsiasi | vecchia, con modulo immobili nuovo | nessun attributo (l'app vecchia ignora l'opzione `attributes`), nessuna isola, nessun errore (I20) |
| senza JS o senza API (Q16) | qualsiasi | bottoni originali |

**Cambi di comportamento da comunicare:**
- sparisce il Salva di default quando il layout ha già un Submit `upload` (5.2);
- Quill vuoto vale `''`, e un Quill obbligatorio scritto e cancellato disabilita Salva (2.5);
- le scritture dirette nel DOM di `.ql-editor` non sono più supportate (Q10);
- `user()` restituisce il campo nuovo `written` (5.4).

## Documentazione

Il lavoro è architetturale: non è completo finché docs, `AGENTS.md` e skill non sono allineati nello stesso cambio.

**App**
- Nuova pagina `docs/app/concetti/form/save-bar.md`, con le sezioni:
  - Cos'è;
  - Come si dichiara;
  - Stato iniziale sporco;
  - Submit da script e AJAX;
  - Come funziona;
  - Vincoli;
  - Dove si trova nel codice.
- **Vincoli** comprende:
  - le copie disabilitate come l'originale (`check()` dei required);
  - la conferma `confirmOther` con più form e la doppia domanda con `Button::post` (Q19);
  - il limite di EditorJS (circa 400ms);
  - la regola su `autocomplete="new-password"` e `data-wi-save-bar-ignore` (5.5);
  - un Submit `upload` nel layout sostituisce il Salva di default, con l'eccezione di Card con `visibleWhen` e Accordion chiusi (Q14);
  - nessun `autocomplete="off"`: Firefox che ricarica la pagina rimette i valori non salvati, e l'isola li prende come punto di partenza (Q8);
  - l'override integrale di `header.php` dà al massimo 96px di scroll in più (5.6);
  - gli override dei moduli e `publish:module` per singolo file (5.9);
  - i due casi di aggiornamento dei siti (Rilascio e compatibilità).
- Link alla pagina nuova da `docs/app/SUMMARY.md`, `docs/app/concetti/form/README.md`, `docs/app/concetti/componenti/README.md` e `docs/app/piattaforma/layout.md`.
- Nota in `docs/app/concetti/componenti/README.md` accanto a `Button::post`, sul modello della nota sui Modal (5.8).
- **`AGENTS.md` dell'app:**
  - un punto nuovo dopo quello sul CheckTree, che finisce con "See docs/app/concetti/form/save-bar.md";
  - correzione del link a `AGENTS.md:437`: `docs/app/elementi/form-system.md` non esiste e diventa `docs/app/concetti/form/theme-system.md`.

**Skill** (sorgenti in `packages/skills`, poi risincronizzate con `npx skills`):
- `wi-app/references/model-and-resource.md`;
- `wi-site/references/workflows.md`.

**Lib**
- Aggiornati:
  - `AGENTS.md`, con i comandi di installazione, verifica e lancio del test browser;
  - `data-attributes.md`, `js-reference.md` (anche `loadingSpinner(show)` con l'argomento facoltativo), `events.md`, `forms.md` e `css-variables.md`;
  - la nuova `javascript/save-bar.md`, con il link in `SUMMARY.md`.
- **MANIFEST:** voce `components.save_bar` sul modello di `components.deferred`:
  - `js`: `"src/build/backend/js/form/saveBar.js"`, e `css` con il percorso di `save-bar.css`;
  - `global`: `"window.wiSaveBar"` (chiave nuova);
  - `contract`: una stringa con attributi, evento e variabile `--wi-save-bar-reserve`, e la nota che le classi interne non sono stabili;
  - `docs`: `"docs/javascript/save-bar.md"`;
  - `internal_classes`: `[".wi-save-bar*", "html.wi-save-bar-on"]` (chiave nuova).
- **CHANGELOG, sezione Unreleased:**
  - Quill vuoto diventa `''`;
  - un Quill obbligatorio scritto e cancellato disabilita Salva;
  - le scritture dirette nel DOM di `.ql-editor` non sono più supportate;
  - `loadingSpinner(show)` accetta un argomento facoltativo; senza argomento alterna come prima.

## Limiti noti

- **Salvataggi AJAX:** le modifiche fatte durante la richiesta risultano salvate dopo `reset()` (3 H).
- **Popup non elencati** in contesti di sovrapposizione bassi possono finire sotto l'isola. Si risolve con `data-wi-save-bar-hide-when-open` (3 K, 4 Stati).
- **Override integrale di `header.php`:** senza la sottrazione della riserva, al massimo 96px di scroll in più (5.6).
- **Senza JS o senza le API necessarie** restano solo i bottoni originali (sez. 1, Q16).
- **Avviso di uscita saltato:** se l'utente non ha ancora interagito con la pagina, per esempio subito dopo un POST fallito, il browser può non mostrare il dialogo (3 G).
- **Zoom del browser** coperto solo tramite la densità dello schermo nei test (B4).
- **Isola su tre righe:** con etichette lunghissime può uscire dalla fascia (4 Posizione, B43).
- **EditorJS:** uscire entro circa 400ms dalla prima modifica non dà l'avviso (2.5, I12).
- **Firefox e ricaricamento:** senza `autocomplete="off"`, Firefox rimette i valori non salvati e l'isola li prende come punto di partenza (Q8).
- **`.offcanvas-{bp}`** non si esclude da solo: serve `data-wi-save-bar-ignore` (3 B).
- **Invio da script** senza `reset(form)` prima di `form.submit()` lascia partire l'avviso di uscita (3 F).
- **Doppia domanda** con `Button::post` e un form Resource sporco (Q19).
- **CheckTree:** nessun limite. I valori sono checkbox nascoste nel contenitore `[data-wi-tree-values]`, che non si spostano chiudendo un nodo (`set.js:505-557`); I12 lo verifica come regressione.
- **Nome in zone escluse e non escluse:** si tiene intero, con un avviso in console (Q1).

## Casi limite decisi (Q1–Q20)

| Q | Decisione | Sezione |
|---|---|---|
| Q1 | Un nome presente solo in zone escluse si toglie dall'istantanea; presente dentro e fuori si tiene intero, con avviso in console. | 2.1 |
| Q2 | `wi:save-bar:change` parte solo ai passaggi pulito↔sporco, compreso quello di `reset()`. | 1 Contratto pubblico |
| Q3 | Per "invio che naviga la finestra" valgono `formtarget` e `formmethod` del bottone che invia, se presenti. | 3 F |
| Q4 | Mediana di 5 ripetizioni: nessun task in più oltre 50ms rispetto alla pagina senza isola; `FormData` del form da 5000 campi sotto 16ms; CPU ×4 solo informativa. | 2 Costo, 6.3 B20 |
| Q5 | `AttributeString::render()` non mette lo spazio iniziale; lo aggiunge il renderer. | 5.1 |
| Q6 | Passo di avvio: funzione globale `setUpSaveBar()`, idempotente, chiamata da `setUpPage()`. | 1 Ciclo di vita, 3 A |
| Q7 | Nessun helper nuovo per testare le viste; le coprono I1 e I2–I9. | 6.4 |
| Q8 | Nessun `autocomplete="off"`; il comportamento di Firefox è un limite documentato. | Non obiettivi, Limiti noti, Documentazione |
| Q9 | `absorb()` di jstree sul contenitore `[data-wi-tree-values]`. | 2.3, 5.7 |
| Q10 | Quill chiama `changed()` a ogni `text-change`, qualunque sia la sorgente; scrivere nel DOM di `.ql-editor` non è più supportato. | 2.5 |
| Q11 | Un solo MutationObserver sul form (`childList`, `subtree`, `disabled`, `data-wi-save-bar-ignore`): ricalcola sempre, rifà i bottoni solo se servono. | 2.2, 3 B |
| Q12 | Si copiano gli elementi di `form.elements` con proprietà `type === 'submit'` o classe `.wi-submit`, compresi i collegati con `form="id"`, in ordine di pagina. | 1 Bottoni copiati, 3 B |
| Q13 | La copia non tiene nessun `data-*`, né sul bottone né sui figli. | 3 C |
| Q14 | `hasSubmit()` non conta i Submit in una Card con `visibleWhen` né in un Accordion non `expanded(true)`. | 5.2 |
| Q15 | Tastiera aperta se `visualViewport.height < 0,75 × innerHeight` con un campo di testo a fuoco, in alternativa a `pointer: coarse`. | 3 L |
| Q16 | Avvio in `try/catch` e solo con `IntersectionObserver`, `ResizeObserver`, `MutationObserver` e `inert` disponibili. | 3 A |
| Q17 | `wiSaveBar?.absorb(input)` in `initGoogleMapsPlace`, subito dopo `input.disabled = false`. | 2.3, 5.7 |
| Q18 | Scheduler: `onsubmit` allineato alla condizione dell'attributo; `-dirty` come in `resource/form.php`. | 5.3 |
| Q19 | La doppia domanda con `Button::post` è accettata e documentata. | 5.8 |
| Q20 | Dentro un array si saltano gli elementi che non sono scalari né Stringable; la differenza da `View\Component` va nel PHPDoc. | 5.1 |

## Storico delle decisioni (non normativo)

Registra come il contratto è cambiato tra una revisione e l'altra. Le regole valide sono quelle delle sezioni 1–6; qui non c'è nulla di nuovo.

### Sezione 3: modifiche al contratto rispetto alle sezioni 1–2

- **Rimossi:** `disarm()` e l'override di `form.submit()`.
- **Aggiunti:** l'esclusione di `.offcanvas`, la regola "`reset(form)` prima di `form.submit()`" e il passo `setUpSaveBar()` in `setUpPage()`.
- **Interni della lib, fuori contratto:** `wi-save-bar-on`, le variabili CSS tranne `--wi-save-bar-reserve`, `data-wi-state` e `data-wi-save-bar-keyboard`.

### Sezione 4: cosa cambia rispetto alla sezione 3

- La fascia viene dal token CSS `--wi-save-bar-band`: non c'è più la costante `BAND` in JS né `--wi-save-bar-h`.
- Lo stato iniziale nascosto usa `visibility`, non `display: none`.
- `data-state` diventa `data-wi-state`.
- `data-wi-save-bar-keyboard` va su `.wi-save-bar`.
- Il token dello z-index si sposta da `tokens.css` a `save-bar.css`.
- Le copie perdono anche `btn-check`.
- I popup di EditorJS non sono più un limite: li gestisce la regola `:has()`.
