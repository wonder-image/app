# Notifiche

Nel backend ogni conferma arriva come **toast**: il riquadro che compare in
alto a destra. Lo mostra `alertToast()` di `wonder-image/lib`, che chiede
l'HTML dell'avviso a `/api/backend/alert/`.

Due modi per far comparire un toast.

## 1. Codice della notifica

I testi stanno in `resources/lang/<lingua>/notifications.json`, uno per
codice:

```json
"650": { "type": "success", "title": "Modificato", "text": "Modifiche effettuate con successo." }
```

Il codice si passa in tre modi:

| Come | Quando |
|---|---|
| `$ALERT = '905';` | la pagina si ridisegna subito (es. un form con errori) |
| `?alert=650` nell'URL | redirect verso una pagina che non conosce l'esito |
| `FlashAlert::code(650)` | redirect senza sporcare l'URL |

Nello script finisce solo un numero: `?alert=` arriva dall'esterno e non deve
poter diventare codice.

## 2. Messaggio scritto al momento

Quando il testo si compone durante il salvataggio ("Sbloccate: Ordini,
Coupon.") non c'è un codice da usare:

```php
use Wonder\Backend\Support\FlashAlert;

FlashAlert::saved('Sbloccate: Ordini, Coupon.');       // titolo del 650
FlashAlert::custom('Attenzione', 'Sede senza orari.', 'warning');
```

L'avviso aspetta in sessione e viene consumato dalla prima pagina che lo
stampa. Vale **solo nel backend**: il JavaScript del frontend accetta
soltanto il codice, quindi lì un messaggio scritto a mano viene ignorato.

Il testo entra nell'avviso come HTML, esattamente come le traduzioni (che
contengono `<br>`): scrivilo nel codice, non passarci quello che ha digitato
un utente.

## Errori dei form

{% hint style="danger" %}
**Nel backend l'esito dell'invio di un form/modulo si comunica con un alert.
Sempre.** Che sia un salvataggio riuscito, un'eccezione intercettata, un CSRF
non valido o un errore di validazione globale, il messaggio esce come toast /
notifica — `FlashAlert::custom($titolo, $testo, 'error')`, oppure un codice di
`notifications.json` via `FlashAlert::code(650)` / `$ALERT` — **prima** del
redirect.

Mai stampare l'errore come testo grezzo nella pagina (una variabile
`$MESSAGE` sputata nel markup) né chiuderlo con `exit('...')`: sono i due
anti-pattern che questa regola vieta.
{% endhint %}

È il comportamento che `ResourcePageController` ha già di serie (vedi
`submitFormPage()`). Gli handler scritti a mano sotto `app/http/backend/*` e
le pagine `CustomPageSchema` devono adeguarsi allo stesso schema.

Gli errori di **singolo campo** restano nel wiring di `FormField`: qui si
parla del messaggio d'**esito** dell'invio, non dei suggerimenti campo per
campo.

## Messaggi frontend dopo un redirect

Nel frontend usa `FlashMessage` per comunicare l'esito di una richiesta che
termina con un redirect:

```php
use Wonder\Frontend\Support\FlashMessage;

FlashMessage::success('Quantità aggiornata.', 'Carrello aggiornato');
FlashMessage::error("Indirizzo incompleto.\nMetodo di pagamento mancante.", 'Controlla i dati');
FlashMessage::warning('La sessione sta per scadere.', 'Attenzione');
FlashMessage::info('La richiesta è in elaborazione.', 'Informazione');

header('Location: /destinazione/');
exit;
```

Il messaggio aspetta in sessione ed è consumato una sola volta dal componente
`frontend.layout.flash-message`, incluso in `frontend.base`. La resa usa
`Wonder\Elements\Components\Alert`: testo e titolo vengono escapati dal
renderer, mentre livello e contenuto restano dati e non markup preparato dal
controller.

`FlashMessage` contiene un solo esito per redirect: una seconda chiamata prima
del rendering sostituisce la precedente. Gli errori correlati vanno quindi
uniti in un unico testo separato da `\n`. Gli errori di singolo campo restano
nel wiring di `FormField` e non diventano messaggi flash.

Per aggiornamenti AJAX non usare la sessione: restituisci l'esito nella risposta
JSON e mostra l'avviso soltanto dopo la risposta positiva o negativa.

## Cosa fa già il core

Le Resource notificano da sole: dopo un salvataggio o un'eliminazione andati
a buon fine, `ResourcePageController` mette in coda il codice 650 e il toast
compare sulla pagina dove arriva il redirect. Non serve scrivere niente.

Una pagina-form (`isFormPage()`) mostra il messaggio restituito da
`submitFormPage()` come toast: in pagina non resta niente.
