# Brevo

{% hint style="info" %}
Link di accesso: [https://app.brevo.com/](https://app.brevo.com/)
{% endhint %}

## Configurazione rapida

1. Accedi a Brevo e crea una API Key da `SMTP & API` -> `API Keys`.
2. Nel backend del sito apri la sezione credenziali email.
3. Imposta il servizio su `Brevo`.
4. Incolla la chiave nel campo `Brevo API Key`.

## Classi disponibili

Il framework include queste classi:

- `Wonder\Plugin\Brevo\TransactionalEmail`
- `Wonder\Plugin\Brevo\Contact`
- `Wonder\Plugin\Brevo\Account`

Di default la chiave API viene letta automaticamente da `Credentials::mail()->brevo_api_key`.

## Pattern di utilizzo

Tutte le classi Brevo usano lo stesso approccio:

- crei l'istanza
- imposti i parametri con metodi fluent
- esegui la chiamata finale

Esempio base:

```php
use Wonder\Plugin\Brevo\TransactionalEmail;

$response = (new TransactionalEmail())
    ->subject('Oggetto')
    ->text('Testo')
    ->send();
```

Se vuoi forzare una chiave API specifica:

```php
use Wonder\Plugin\Brevo\TransactionalEmail;

$mail = TransactionalEmail::connect('xkeysib-...');
```

In alternativa puoi usare `TransactionalEmail::apiKey('xkeysib-...')`.

## TransactionalEmail

La classe `TransactionalEmail` serve per inviare email transazionali e leggere log/report.

### Invio email HTML

```php
use Wonder\Plugin\Brevo\TransactionalEmail;

$response = (new TransactionalEmail())
    ->sender('no-reply@dominio.it', 'My Site')
    ->to('utente@example.com', 'Mario Rossi')
    ->replyTo('info@dominio.it', 'Supporto')
    ->subject('Conferma registrazione')
    ->html('<p>Registrazione completata con successo.</p>')
    ->text('Registrazione completata con successo.')
    ->send();
```

### Invio con template Brevo

```php
use Wonder\Plugin\Brevo\TransactionalEmail;

$response = (new TransactionalEmail())
    ->sender('no-reply@dominio.it', 'My Site')
    ->to('utente@example.com', 'Mario Rossi')
    ->templateId(12)
    ->param('NOME', 'Mario')
    ->param('LINK', 'https://example.com/conferma')
    ->send();
```

### Allegati

```php
use Wonder\Plugin\Brevo\TransactionalEmail;

$response = (new TransactionalEmail())
    ->sender('no-reply@dominio.it', 'My Site')
    ->to('utente@example.com')
    ->subject('Documento')
    ->html('<p>In allegato trovi il file richiesto.</p>')
    ->attachmentUrl('https://example.com/documento.pdf', 'documento.pdf')
    ->send();
```

Se hai gia il contenuto del file in base64 puoi usare `attachmentContent($name, $content)`.

### Log ed eventi

Lista email inviate:

```php
use Wonder\Plugin\Brevo\TransactionalEmail;

$emails = (new TransactionalEmail())
    ->email('utente@example.com')
    ->limit(20)
    ->all();
```

Report eventi:

```php
use Wonder\Plugin\Brevo\TransactionalEmail;

$events = (new TransactionalEmail())
    ->email('utente@example.com')
    ->days(7)
    ->events();
```

Metodi piu usati:

- `sender()`
- `senderId()`
- `to()`
- `cc()`
- `bcc()`
- `replyTo()`
- `subject()`
- `html()`
- `text()`
- `templateId()`
- `param()`
- `tag()`
- `scheduledAt()`
- `attachmentUrl()`
- `attachmentContent()`
- `send()`
- `all()`
- `events()`
- `report()`
- `get()`
- `scheduled()`
- `delete()`

## Contact

La classe `Contact` serve per creare, leggere, aggiornare ed eliminare contatti Brevo.

### Creazione o aggiornamento contatto

```php
use Wonder\Plugin\Brevo\Contact;

$response = (new Contact())
    ->email('utente@example.com')
    ->firstName('Mario')
    ->lastName('Rossi')
    ->phone('+39 333 1234567')
    ->listId(12)
    ->updateEnabled(true)
    ->create();
```

Nota:

- `firstName()` salva l'attributo `NOME`
- `lastName()` salva l'attributo `COGNOME`
- `phone()` salva l'attributo `SMS` e rimuove gli spazi

### Lettura contatto

```php
use Wonder\Plugin\Brevo\Contact;

$contact = (new Contact())
    ->identifierType('email_id')
    ->get('utente@example.com');
```

### Aggiornamento contatto esistente

```php
use Wonder\Plugin\Brevo\Contact;

(new Contact())
    ->identifierType('email_id')
    ->firstName('Mario')
    ->lastName('Bianchi')
    ->update('utente@example.com');
```

### Elenco contatti

```php
use Wonder\Plugin\Brevo\Contact;

$contacts = (new Contact())
    ->limit(50)
    ->offset(0)
    ->all();
```

Metodi piu usati:

- `email()`
- `extId()`
- `identifierType()`
- `attribute()`
- `firstName()`
- `lastName()`
- `phone()`
- `whatsapp()`
- `listId()`
- `listIds()`
- `unlinkListId()`
- `emailBlacklisted()`
- `smsBlacklisted()`
- `updateEnabled()`
- `create()`
- `get()`
- `update()`
- `delete()`
- `all()`

## Account

La classe `Account` serve per leggere i dati dell'account Brevo e le attivita.

### Lettura account

```php
use Wonder\Plugin\Brevo\Account;

$account = (new Account())->get();
```

### Lettura attivita account

```php
use Wonder\Plugin\Brevo\Account;

$activity = (new Account())
    ->startDate('2026-03-01')
    ->endDate('2026-03-17')
    ->limit(50)
    ->activity();
```

## Note pratiche

- Se usi `sendMail()` con servizio `brevo`, il framework usa internamente `TransactionalEmail`.
- Per le email HTML puoi passare direttamente tag come `<br>`, `<b>` e `<a>`.
- Per i contatti, gli attributi personalizzati devono esistere gia su Brevo.

## Link utili

- Quickstart Brevo: [https://developers.brevo.com/docs/quickstart.md](https://developers.brevo.com/docs/quickstart.md)
