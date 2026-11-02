# Credenziali email

Le email del sito (`sendMail()`, notifiche dello scheduler, moduli) partono con le
credenziali salvate nel backend in **Dev → Credenziali**, sezione *Server mail*.

## Configurazione

1. Scegli il **Servizio**: `PHPMailer (SMTP)` oppure `Brevo API`.
2. Con SMTP compila `Host`, `Porta`, `Username` e `Password`. La connessione usa
   sempre TLS implicito (`ENCRYPTION_SMTPS`, di norma porta 465).
3. Con Brevo incolla la chiave nel campo `Brevo API Key` (vedi [Brevo](brevo.md)).
4. Salva.

Le variabili d'ambiente `MAIL_SERVICE`, `MAIL_HOST`, `MAIL_PORT`,
`MAIL_USERNAME`, `MAIL_PASSWORD` e `BREVO_API_KEY` hanno la precedenza sui
valori salvati: `Credentials::mail()` restituisce già il risultato della
fusione.

## Email di prova

Il bottone **Email di prova** nell'intestazione della pagina Credenziali invia
un'email all'indirizzo dell'utente backend che la preme, con le credenziali
**salvate** (quelle restituite da `Credentials::mail()`): le modifiche non
ancora salvate nel form non vengono provate.

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
- **Corpo.** Il messaggio originale (mittente, destinatari, oggetto, corpo,
  allegati, template) sta nella colonna `payload` finché l'email non è inviata
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
- La pagina aggiunge il bottone con `SecurityResource::pageSchema()->actions('edit', ...)`
  e registra la rotta in `registerBackendRoutes()`: un sito o un modulo che
  estende la risorsa può sostituire `editActions()` per cambiare o togliere
  il bottone.
- `Wonder\App\Support\Mail\Outbox` contiene le regole della coda
  (`queue()`, `process()`, `retryAt()`, `permanent()`, `stuck()` e le
  costanti `MAX_ATTEMPTS`, `RETRY_DELAYS`, `CLAIM_TIMEOUT`, `BATCH`) e lavora
  su un `OutboxStore`: `DatabaseOutboxStore` è quello su `mail_log`, i test
  usano uno store in memoria. `process()` riceve la funzione che spedisce
  (`mailDeliver()`, lo stesso invio di `sendMail()` senza la riga di log),
  così un modulo può sostituire trasporto o archivio senza toccare le regole.
- `mail_log` ha le colonne `attempts`, `next_attempt_at` (UTC) e `payload`
  (JSON grezzo, scritto con prepared statement); `forge update` le aggiunge.
