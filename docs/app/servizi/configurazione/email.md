# Credenziali email

Le email del sito (`sendMail()`, notifiche dello scheduler, moduli) partono con le
credenziali salvate nel backend in **Dev → Credenziali**, card *Server SMTP* o
*Brevo* (categoria Email, vedi [Integrazioni](../integrazioni.md)).

## Configurazione

1. Apri la card del servizio e scegli il **Servizio** d'invio:
   `PHPMailer (SMTP)` oppure `Brevo API`. La card del servizio scelto mostra
   *In uso per l'invio*.
2. Con SMTP compila `Host`, `Porta`, `Username` e `Password`. La connessione usa
   sempre TLS implicito (`ENCRYPTION_SMTPS`, di norma porta 465).
3. Con Brevo incolla la chiave nel campo `Brevo API Key` (vedi [Brevo](brevo.md)).
4. Salva.

Le variabili d'ambiente `MAIL_SERVICE`, `MAIL_HOST`, `MAIL_PORT`,
`MAIL_USERNAME`, `MAIL_PASSWORD` e `BREVO_API_KEY` hanno la precedenza sui
valori salvati: `Credentials::mail()` restituisce già il risultato della
fusione.

## Email di prova

Il bottone **Invia email di prova** nel modal *Server SMTP* o *Brevo* invia
un'email all'indirizzo dell'utente backend che lo preme, con i valori
**scritti nel modal**, senza salvarli. Il servizio provato è quello del modal
(SMTP o Brevo), qualunque sia il servizio scelto. Una password lasciata vuota
usa quella salvata; un valore presente nel `.env` vince sempre su quello del
modal, come nell'invio vero (`CredentialsTest::fromForm()`).

Cosa succede alla conferma:

- con SMTP il framework apre prima una connessione al server (`SmtpTransport::check()`,
  timeout di 15 secondi) e, se fallisce, mostra l'errore di PHPMailer senza
  tentare l'invio; il check fallito viene comunque registrato come un invio
  fallito, con una riga `failed` in `mail_log` (oggetto *Email di prova*,
  corpo vuoto) e una voce `[credentials-test]` nel log degli errori;
- poi invia l'email con `sendMail()`, lo stesso percorso delle email reali:
  l'esito finisce nel log delle email (`mail_log`) come ogni altro invio;
- il risultato compare come notifica nella pagina: *Email di prova inviata*
  con host, porta e utente usati, oppure il motivo dell'errore (credenziali
  incomplete, connessione rifiutata, invio non riuscito, Brevo senza chiave).

La rotta è `POST backend.resource.app-config-credentials.mail-test`, protetta
da CSRF e dagli stessi permessi di modifica della risorsa.

## Aspetto delle email

Ogni email passa dal template `basic` (`emailTemplate($body, 'basic')`, che
delega a `Wonder\App\Support\Mail\MailLayout::render()`):

- impaginazione a tabelle larga 600 px, il formato che i client di posta
  (Gmail, Apple Mail, Outlook) rendono in modo più uniforme;
- in alto il logo principale del sito (`$PATH->logo`, con la versione
  bianca `logoWhite` nel tema scuro) sotto una riga nel colore primario.
  Il logo si carica in Media → Logo (solo PNG). Senza logo compare il nome
  della società preceduto da un quadratino nel colore primario; un logo SVG
  non viene usato, perché Gmail e Outlook non lo mostrano;
- il colore primario viene da `css_color` (`var = primary`): se è troppo
  chiaro per uno sfondo bianco diventa un grigio scuro, e il testo dei
  pulsanti prende il colore di contrasto leggibile;
- tema scuro per i client che lo supportano (`prefers-color-scheme`), solo per i corpi del builder: un corpo scritto a mano può avere colori inline scuri e resta in tema chiaro;
- piè di pagina tradotto con ragione sociale, indirizzo, P.IVA / C.F.,
  email e telefono della società (le voci vuote non compaiono);
- una versione solo testo (`AltBody`) ricavata dall'HTML.

Un sito o un test può fissare i colori con
`MailLayout::useBrand('#c2410c', '#ffffff')`; `useBrand(null)` torna alla
lettura da `css_color`.

### Scrivere una email con i blocchi

`Wonder\App\Support\Mail\MailMessage` compone il corpo senza scrivere HTML.
Tutti i testi passano da `htmlspecialchars()`; gli a capo diventano `<br>`.

```php
use Wonder\App\Support\Mail\MailMessage;

$corpo = MailMessage::make()
    ->preheader('Arriverà entro 2-3 giorni lavorativi.')
    ->heading('Il tuo ordine è stato spedito')
    ->text("Ciao {$nome},\nil tuo ordine #{$numero} è partito oggi.")
    ->button('Traccia la spedizione', $urlTracking)
    ->details([
        'Ordine' => '#'.$numero,
        'Corriere' => $corriere,
        'Totale' => $totale,
    ], 'Riepilogo')
    ->notice('Il pacco richiede una firma alla consegna.', 'warning')
    ->divider()
    ->small('Se non hai effettuato tu questo ordine, rispondi a questa mail.');

sendMail($from, $cliente, 'Ordine spedito', $corpo);
```

| Metodo | Cosa produce |
| --- | --- |
| `preheader($testo)` | Anteprima mostrata dal client accanto all'oggetto, nascosta nel corpo. |
| `heading($testo)` | Titolo. |
| `text($testo)` | Paragrafo. |
| `html($html)` | HTML già pronto, **non** sanificato: solo markup scritto dal codice. |
| `button($etichetta, $url, $fallback = true)` | Pulsante nel colore primario e, sotto, il link da copiare. Un URL che non sia `http(s)://`, `mailto:`, `tel:` o un path `/...` stampa solo l'etichetta. |
| `details($righe, $titolo)` | Elenco etichetta / valore (riepiloghi, dati di un form). |
| `items($righe, $totali, $titolo)` | Righe di un ordine: anteprima, nome, descrizione, quantità, prezzo (barrato se c'è `original_price`) e totale di riga; sotto i totali, l'ultimo in evidenza. |
| `table($colonne, $righe, $titolo, $aDestra)` | Tabella con intestazione; `$aDestra` elenca gli indici delle colonne da allineare a destra (importi). |
| `notice($testo, $livello)` | Riquadro `info`, `success`, `warning` o `danger`. |
| `divider()` | Linea di separazione. |
| `small($testo)` | Nota in piccolo. |

`sendMail()`, `queueMail()` e `mailDeliver()` accettano il `MailMessage`
così com'è (lo convertono in stringa), quindi la firma non cambia e la coda
salva il corpo già composto.

Ogni riga di `items()` è un array con `name` (obbligatorio), e se servono
`description`, `image` (URL `http(s)` o path `/...`, mostrata a 64 px),
`url` (link sul nome), `quantity`, `price`, `original_price` e `total`. I
prezzi arrivano già formattati: il blocco non fa conti.

```php
->items([
    ['name' => 'Tazza', 'description' => 'Blu, 350 ml', 'image' => $urlFoto,
     'quantity' => 2, 'price' => '€ 10,00', 'original_price' => '€ 12,00',
     'total' => '€ 20,00'],
], ['Subtotale' => '€ 20,00', 'Spedizione' => 'Gratis', 'Totale' => '€ 20,00'], 'Il tuo ordine')
```

Nella versione testo il pulsante diventa `Etichetta: URL`, le righe di
`details()` e i totali di `items()` diventano `Etichetta: valore`, il prezzo
pieno barrato non compare e preheader e link di riserva non
compaiono.

### Email scritte prima del builder

Un corpo HTML scritto a mano continua a funzionare come prima: passa ancora
da `sanitizeEcho()` e finisce nella stessa cornice (logo, colori, piè di
pagina). Non serve migrarlo; conviene farlo quando lo si tocca, per avere
pulsanti e riepiloghi coerenti.

Le email spedite dal pacchetto (account backend e frontend, benvenuto dello
staff, verifica email, token API, segnalazione errori, email di prova) sono
già scritte con `MailMessage`. I loro testi tradotti sono divisi per blocco:
`auth.email.reset_text` / `reset_button` / `reset_ignore`,
`federated_text` / `federated_button`, `existing_text` / `existing_button` /
`existing_recovery` / `existing_recovery_button` / `existing_ignore` e
`account.email.mail_text` / `mail_button` / `mail_ignore`; l'URL lo mette il
pulsante, non il testo.

Il builder segna il proprio output con il commento `<!--wi-mail:v1-->`
(`MailMessage::MARKER`): solo quel corpo salta `sanitizeEcho()`, perché è già
stato sanificato blocco per blocco. Un template diverso da `basic` restituisce
una stringa vuota, come prima.

## Copia (CC) e copia nascosta (CCN)

`sendMail()`, `queueMail()` e `mailDeliver()` accettano in fondo
`array $options = []` con le chiavi `cc` e `bcc`. Passalo per nome, così non
servono gli argomenti intermedi:

```php
sendMail($from, $cliente, $oggetto, $corpo, options: [
    'cc' => [$emailAzienda, ...$contattiCheRicevonoDocumenti],
    'bcc' => 'archivio@example.it',
]);

queueMail($from, $cliente, $oggetto, $corpo, $allegati, options: ['cc' => $emailAzienda]);
```

- Ogni chiave accetta una stringa (anche più indirizzi separati da `,` o `;`)
  o una lista.
- Gli indirizzi non validi vengono scartati senza errore; i doppioni (anche
  con maiuscole diverse) restano una volta sola.
- Il destinatario `$to` esce dai CC; `$to` e i CC escono dai CCN: nessuno
  riceve la stessa email due volte.
- Una chiave diversa da `cc` e `bcc` lancia `InvalidArgumentException`: è un
  errore di chi chiama, non un invio fallito.
- PHPMailer usa `addCC()` / `addBCC()`, Brevo `TransactionalEmail::cc()` /
  `bcc()`.
- Il log delle email salva gli indirizzi in `cc_email` e `bcc_email`, li
  mostra nel dettaglio (**Copia (CC)**, **Copia nascosta (CCN)**) e li usa
  nella ricerca della lista.

## Coda di invio

`sendMail()` spedisce subito, dentro la richiesta: se il server SMTP è lento o
non risponde, la pagina aspetta e l'email fallita resta fallita. `queueMail()`
ha la stessa firma e lo stesso ritorno (`true` / `false`), ma salva l'email in
`mail_log` con stato **In coda** (`queued`) e la lascia spedire al cron
[Coda email](../../piattaforma/cron-job.md#preconfigurati-e-integrazioni-future)
(`wonder.mail-outbox`, ogni minuto).

```php
queueMail($from, $to, $oggetto, $corpo, $allegati, 'basic');
```

La coda è opt-in: `sendMail()` resta sincrona e nessuna chiamata esistente
cambia comportamento. Un sito o un modulo che vuole mettere in coda tutte le
sue email cambia il punto in cui le spedisce (per esempio il gestionale
imposta `Mailer::$transport = 'queueMail'`).

- **Ripiego sincrono.** Se lo Scheduler non gira (ultimo contatto assente o
  più vecchio di 5 minuti) o il salvataggio in coda fallisce, `queueMail()`
  chiama `sendMail()`: l'email parte comunque, come prima.
- **Tentativi.** Un errore temporaneo (connessione, autenticazione, timeout)
  riprova dopo 1, 5, 15 e 60 minuti, al massimo 5 tentativi. Un errore
  permanente (indirizzo non valido o rifiutato, codici 550-554, allegato non
  trovato) passa subito a **Fallita**. Il motivo resta nella riga di `mail_log`.
- **Almeno una volta.** Una riga rimasta **In invio** (`sending`) per più di
  15 minuti, perché il processo è stato ucciso a metà, torna in coda: in casi
  rari il destinatario può ricevere l'email due volte.
- **Allegati.** In coda si salvano i percorsi, non i file: devono esistere
  ancora quando il cron spedisce. Non mettere in coda allegati temporanei che
  la richiesta cancella subito dopo.
- **Corpo.** Il messaggio originale (mittente, destinatari, CC e CCN, oggetto,
  corpo, allegati, template) sta nella colonna `payload` finché l'email non è inviata
  o fallita, poi viene svuotato; il log mostra il corpo spedito come per
  `sendMail()`.
- **Backend.** Il log delle email filtra anche per **In coda** e **In invio**.
  Se la coda è ferma (l'email più vecchia da spedire aspetta da oltre 15
  minuti) la home del backend mostra la card **Coda email**, a chi può vedere
  il log, con il link alla lista.

## Per chi sviluppa

- `Wonder\App\Support\Mail\SmtpTransport::configure($mail, $credentials)` è
  l'unico punto che imposta PHPMailer per SMTP; `sendMail()` lo usa.
  `SmtpTransport::check($credentials)` restituisce `''` se la connessione
  riesce, altrimenti il messaggio di errore.
- `Wonder\App\Support\Mail\CredentialsTest::run($recipient)` esegue l'intera
  prova e restituisce un `CredentialsTestResult` (`ok`, `title`, `message`).
  Accetta chiusure al posto del check, dell'invio e del log, così i test non
  toccano rete né database. `CredentialsTest::logCheckFailure()` è il log del
  check fallito (riga `mail_log` + `__log()`), non lancia mai.
- `sendMailError()` restituisce l'ultimo errore di `sendMail()` (stringa vuota
  quando l'invio è riuscito): utile per mostrare all'utente perché un invio
  è fallito senza leggere `mail_log`.
- `CredentialsTest::fromForm($service, $posted)` costruisce le credenziali
  della prova partendo da `Credentials::mail()`: i campi compilati nel POST
  sostituiscono quelli salvati, tranne quelli presenti nel `.env`.
- Il bottone è un `submit` con `formaction` nel footer del modal (vista
  `app/view/pages/backend/config/security/integrations.php`); la rotta
  `mail-test` è registrata in `SecurityResource::registerBackendRoutes()`.
- `Wonder\App\Support\Mail\Outbox` contiene le regole della coda
  (`queue()`, `process()`, `retryAt()`, `permanent()`, `stuck()` e le
  costanti `MAX_ATTEMPTS`, `RETRY_DELAYS`, `CLAIM_TIMEOUT`, `BATCH`) e lavora
  su un `OutboxStore`: `DatabaseOutboxStore` è quello su `mail_log`, i test
  usano uno store in memoria. `process()` riceve la funzione che spedisce
  (`mailDeliver()`, lo stesso invio di `sendMail()` senza la riga di log),
  così un modulo può sostituire trasporto o archivio senza toccare le regole.
- `Wonder\App\Support\Mail\Recipients` normalizza CC e CCN
  (`normalize()`, `options($options, $to)`, `format()`), senza rete né
  database. Un'opzione nuova di `sendMail()` si aggiunge a
  `Recipients::OPTIONS` e passa da `$options`, senza allungare la firma.
  `mailDeliver()` restituisce anche `cc` e `bcc` già normalizzati.
- `Outbox::message()` salva `options` nel `payload`; `Outbox::arguments()`
  rende gli argomenti posizionali di `sendMail()` / `mailDeliver()`
  (`$credentials` null, poi `$options`). Le righe accodate prima di questa
  versione, senza `options`, partono con CC e CCN vuoti.
- `mail_log` ha le colonne `cc_email`, `bcc_email` (`TEXT`, indirizzi separati
  da `, `), `attempts`, `next_attempt_at` (UTC) e `payload`
  (JSON grezzo, scritto con prepared statement); `forge update` le aggiunge.
