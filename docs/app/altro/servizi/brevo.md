# Brevo

{% hint style="info" %}
Link di accesso: [https://app.brevo.com/](https://app.brevo.com/)
{% endhint %}

## Configurazione rapida

1. Accedi a Brevo e crea una API Key da `SMTP & API` -> `API Keys`.
2. Nel backend del sito apri la sezione credenziali email.
3. Imposta il servizio su `Brevo`.
4. Incolla la chiave nel campo `Brevo API Key`.

## Plugin disponibile

Il framework include il plugin:

- `Wonder\Plugin\Brevo\TransactionalEmail`
- `Wonder\Plugin\Brevo\Contact`
- `Wonder\Plugin\Brevo\Account`

La chiave API viene letta automaticamente da `Credentials::mail()->brevo_api_key`.

## Esempi di utilizzo

### 1) Invio email transazionale

```php
use Wonder\Plugin\Brevo\TransactionalEmail;

$response = (new TransactionalEmail())
    ->sender('no-reply@dominio.it', 'My Site')
    ->to('utente@example.com', 'Mario Rossi')
    ->subject('Conferma registrazione')
    ->html('<p>Registrazione completata con successo.</p>')
    ->send();
```

### 2) Creazione o aggiornamento contatto

```php
use Wonder\Plugin\Brevo\Contact;

$response = (new Contact())
    ->email('utente@example.com')
    ->firstName('Mario')
    ->lastName('Rossi')
    ->phone('+393331234567')
    ->listId(12)
    ->updateEnabled(true)
    ->create();
```

### 3) Lettura dati account Brevo

```php
use Wonder\Plugin\Brevo\Account;

$account = (new Account())->get();
```

## Link utili

- Quickstart Brevo: [https://developers.brevo.com/docs/quickstart.md](https://developers.brevo.com/docs/quickstart.md)

