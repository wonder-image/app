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

## Cosa fa già il core

Le Resource notificano da sole: dopo un salvataggio o un'eliminazione andati
a buon fine, `ResourcePageController` mette in coda il codice 650 e il toast
compare sulla pagina dove arriva il redirect. Non serve scrivere niente.

Una pagina-form (`isFormPage()`) mostra il messaggio restituito da
`submitFormPage()` come toast: in pagina non resta niente.
