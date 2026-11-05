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
