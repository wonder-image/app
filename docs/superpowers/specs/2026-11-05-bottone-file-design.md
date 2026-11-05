# Bottone «File» per export e import — design

**Data:** 2026-11-05
**Repo:** app (framework). Cambia anche il gestionale (catalogo prodotti). Customer-account ed ecommerce restano invariati, ma ereditano il bottone.
**Tipo:** UI backend, comune a tutte le liste Resource.

## Perché

Oggi la lista di una Resource mostra un menu «Esporta» con una voce per ogni coppia tracciato × formato. Con due tracciati e due formati sono già sette voci, con l'XML diventerebbero dieci, e crescono a ogni tracciato nuovo. Accanto c'è un bottone «Importa» separato, anche se lavora sugli stessi tracciati e sugli stessi file. Sul catalogo il gestionale lo aggiunge pure a mano con `buttonCustom()`.

Tracciato e formato sono due scelte indipendenti: il menu le moltiplica. Il commerciante usa questa funzione soprattutto per **export veloci**.

## Obiettivi

- Un solo bottone **«File»**, con icona `bi-arrow-down-up`, al posto di «Esporta» e «Importa» in tutte le liste Resource.
- Il bottone apre una finestra (`Modal`) con due schede, **Esporta** e **Importa**. Si apre sempre su Esporta.
- Scheda **Esporta**: si sceglie un tracciato e un formato, poi si preme «Scarica». Due scelte e un clic, qualunque sia il numero di tracciati.
- La finestra propone l'ultimo tracciato e l'ultimo formato scaricati da quella lista: per l'export abituale basta aprire e premere «Scarica».
- Scheda **Importa**: si sceglie un tracciato, si carica il file e si preme «Continua». Si arriva all'abbinamento delle colonne e all'anteprima, che esistono già. C'è anche il link «Scarica il modello».

## Non obiettivi

- **Formato XML**, in export e in import. È un passo successivo. Con la finestra diventa solo un'opzione in più tra i formati, senza cambiare la UI.
- **«Solo le righe filtrate».** I filtri della lista vivono nella tabella JS (`query_filter` firmato, filtri custom e data). Riportarli lato server nell'export è un lavoro a sé. Oggi l'export usa `querySchema()` e `RowVisibility`, e resta così: tutte le righe che l'utente può vedere.
- Rifare la pagina di import (abbinamento, anteprima, annullamento) o la pagina «Tracciati file».
- Nuovo JavaScript nella lib. La finestra funziona con un form HTML normale. *Scelta del designer:* niente JS, quindi nessun rilascio della lib.

## Anti-obiettivi

- La finestra non deve rendere l'export abituale più lento di oggi: oggi sono due clic (menu, voce), domani devono essere al massimo tre (File, Scarica, con la scelta già pronta).
- Nessuna voce o scheda che l'utente non può usare. Niente scheda Importa disabilitata: se non può importare, la scheda non c'è.

## Comportamento

### Quando c'è il bottone

| Export (`download()` con formati) | Import (`importLayout()`, `Layout::permits()`, Resource non readonly) | Risultato |
|---|---|---|
| sì | sì | «File», finestra con due schede |
| sì | no | «File», finestra con la sola scheda Esporta (senza la barra delle schede) |
| no | sì | «File», finestra con la sola scheda Importa |
| no | no | nessun bottone |

L'etichetta è sempre «File», anche con una sola scheda, per coerenza tra le liste. *Scelta del designer.* `download(label: ...)` oggi rinomina «Esporta». La label resta valida e diventa il titolo della scheda Esporta.

### Scheda Esporta

- **Tracciato** (select):
  - la prima voce è «Tutte le colonne», con valore vuoto: è l'export di oggi, senza `?layout`;
  - poi i tracciati salvati della fonte (`Profile::options($source)`), nell'ordine di oggi;
  - il select non compare se non ci sono tracciati salvati o se nessun formato li accetta.
- **Formato** (radio o select): le voci di `download()`. Sul catalogo sono Excel, CSV e Catalogo Meta.
- **Tracciato con un formato che non lo accetta** (es. Catalogo Meta): il tracciato viene ignorato, come fa già `ResourceDownloadController::handle()`. Sotto il campo c'è un testo d'aiuto: «I tracciati valgono per Excel e CSV». Il testo elenca i formati di `downloadLayouts()`. Sul catalogo «Catalogo Meta» resta quindi un formato e non diventa un tracciato: è il modello dati di oggi. *Scelta del designer:* non serve JS per disattivare le opzioni.
- **«Scarica»**: invia un form GET all'endpoint che esiste già, `backend.resource.{slug}.export` con `{format}` e `?layout=<id>`.
  - Il formato è una parte del percorso, non un parametro. Il form punta quindi a una piccola rotta di smistamento oppure costruisce l'indirizzo lato server. La scelta resta al builder, a patto che gli indirizzi che esistono oggi continuino a funzionare: link salvati e test.
  - Il file si scarica e la finestra resta aperta. Il builder può chiuderla con `data-bs-dismiss` solo se non serve JS nuovo.
- **Ultima scelta**: il controller salva in sessione `[$slug => ['layout' => ?int, 'format' => string]]` dopo aver validato la richiesta e prima di scrivere il file o di chiamare il gestore (`downloadUsing()` può chiudere la risposta da sé). Salva il tracciato *chiesto*, anche se per quel formato viene ignorato: è la scelta dell'utente. La finestra poi la verifica. *Scelta del designer.* La finestra preseleziona quei valori se sono ancora validi (formato concesso, tracciato esistente), altrimenti usa il primo formato e «Tutte le colonne».
  - *Scelta del designer:* la sessione e non `localStorage`, così non serve JS.
  - Vale per utente e per sessione. Dopo un nuovo login si riparte dai valori di base.

### Scheda Importa

- **Tracciato** (select): «Nessuno, abbino le colonne io», poi i tracciati salvati del Layout di import (`import_layout_id` che `ImportResource::upload()` legge già).
- **File** (`FormField` file, un solo file, `.xlsx` e `.csv`, cioè `Reader::EXTENSIONS`).
- **«Scarica il modello»**: link a `ImportResource::sampleUrl($layout)`.
- **«Continua»**: form POST multipart con CSRF verso l'handler che esiste già (`ImportResource::handleSubmit`, `action=upload`, `layout=<chiave>`). Dopo il caricamento si finisce su `?layout=…&job=…`, come oggi. Gli errori, come «nessun file», tornano alla pagina di import con il flash di oggi.

### Dove si rende

- Il bottone va dove oggi c'è il gruppo «Esporta e importa»: `ResourceTableRenderer::applyButtonData()` → `Table::buttonDataHtml()`. Lo apre `Button::opensModal($id)`.
- La finestra è un `Modal` senza `form()`. Il renderer Bootstrap la marca `data-wi-modal-detach` e lo script della pagina la porta fuori da un eventuale form attorno. La lista non ne ha, ma un form annidato il browser lo butterebbe via.
- Ogni scheda ha il suo form: GET per Esporta, POST multipart con CSRF per Importa. Oggi nessun componente lo permette. `Themes/Bootstrap/Form/Form.php` scrive sempre `<form action="" method="post" enctype="multipart/form-data">` e `Modal::form()` dà un solo form per finestra. Servono due aggiunte nell'app, entrambe pubbliche e documentate. *Scelte del designer.*
  1. **`Form::action(string $url, string $method = 'post')`**: con `get` niente `enctype` e niente CSRF; con `post` resta multipart e stampa `Csrf::fieldFor()`. Senza chiamata, il comportamento di oggi (`action=""`, POST) non cambia.
  2. **Un componente `Tabs`** in `class/Elements/Components/`: schede Bootstrap (`nav-tabs` più `tab-content`, `data-bs-toggle="tab"`), un pannello per scheda con i suoi componenti, e una sola scheda senza barra. Bootstrap è già nel backend, quindi non serve JS nuovo né un rilascio della lib. Il componente è di serie solo per Bootstrap. La card va in `docs/components/...` e deve passare `php tests/Docs/CatalogRenderTest.php`.
- Tutti gli input passano da `FormField`.

## Gestionale

- In `ProductModelResource::tableLayoutSchema()` il `buttonCustom(... 'Importa' ...)` si sostituisce con `->importLayout(CatalogLayout::KEY)`, a patto che `Gestionale::feature('import_catalog')` sia acceso. Il permesso `update` lo controlla già `Layout::permits()`: il builder verifica che `CatalogLayout::permits()` copra il controllo `allows(..., 'update')` che si toglie, oppure lo mantiene in `permits()`.
- Il commento «Dev → File → Tracciati file» resta valido.

## Documentazione e test

- **Docs dev dell'app:** la pagina delle liste o tabelle Resource e quella dell'import (`docs/app/concetti/importazione.md`) descrivono il bottone «File», le schede, l'ultima scelta in sessione e il fatto che `download(label:)` dà il titolo della scheda.
- **Guida utente del gestionale** (`docs/user/catalogo-prodotti.md`): «Esporta» e «Importa» diventano «File», con le istruzioni per l'export veloce.
- **CHANGELOG:** app («Cambiato») e gestionale.
- **Test dell'app:** il renderer produce il bottone giusto per le quattro righe della tabella sopra; la preselezione dall'ultima scelta funziona e un tracciato non più valido ricade sul valore di base; gli indirizzi di export esistenti non cambiano.
- Catalogo dei componenti: la card nuova di `Tabs` e l'aggiornamento della card di `Form` (`action()`), con `php tests/Docs/CatalogRenderTest.php` che passa.

## Lasciato al builder

- Radio o select per il formato. *Preferenza del designer:* radio se i formati sono al massimo 4, perché si vedono tutti senza aprire niente.
- Come si costruisce l'indirizzo con il formato nel percorso (vedi Scheda Esporta).
- Il nome della chiave di sessione.
- I testi esatti delle etichette, in italiano e nello stile del backend.
