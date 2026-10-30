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
  tentare l'invio;
- poi invia l'email con `sendMail()`, lo stesso percorso delle email reali:
  l'esito finisce nel log delle email (`mail_log`) come ogni altro invio;
- il risultato compare come notifica nella pagina: *Email di prova inviata*
  con host, porta e utente usati, oppure il motivo dell'errore (credenziali
  incomplete, connessione rifiutata, invio non riuscito, Brevo senza chiave).

La rotta è `POST backend.resource.app-config-credentials.mail-test`, protetta
da CSRF e dagli stessi permessi di modifica della risorsa.

## Per chi sviluppa

- `Wonder\App\Support\Mail\SmtpTransport::configure($mail, $credentials)` è
  l'unico punto che imposta PHPMailer per SMTP; `sendMail()` lo usa.
  `SmtpTransport::check($credentials)` restituisce `''` se la connessione
  riesce, altrimenti il messaggio di errore.
- `Wonder\App\Support\Mail\CredentialsTest::run($recipient)` esegue l'intera
  prova e restituisce un `CredentialsTestResult` (`ok`, `title`, `message`).
  Accetta chiusure al posto del check e dell'invio, così i test non toccano
  la rete.
- `sendMailError()` restituisce l'ultimo errore di `sendMail()` (stringa vuota
  quando l'invio è riuscito): utile per mostrare all'utente perché un invio
  è fallito senza leggere `mail_log`.
- La pagina aggiunge il bottone con `SecurityResource::pageSchema()->actions('edit', ...)`
  e registra la rotta in `registerBackendRoutes()`: un sito o un modulo che
  estende la risorsa può sostituire `editActions()` per cambiare o togliere
  il bottone.
