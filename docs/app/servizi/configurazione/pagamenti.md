# Credenziali dei pagamenti

Le credenziali Stripe, PayPal e Nexi si configurano nella pagina backend
**Dev → API e servizi → Credenziali**. Il core le espone tramite
`Wonder\App\Credentials::api()` con la cascata `.env` → riga `security` →
default vuoto.

## Stripe

«Collega webhook» (`POST /api/service/stripe/connect/`) rifà l'endpoint
Connect dell'ambiente scelto con `Wonder\Plugin\Stripe\Connect`. Gli eventi
registrati sono `Connect::events()`: i quattro di `Connect::EVENTS`
(`payment_intent.succeeded`, `payment_intent.payment_failed`,
`payment_intent.canceled`, `charge.refunded`) più quelli aggiunti dai moduli.
Il core non conosce gli eventi dei moduli: un modulo che ne gestisce altri li
registra al suo bootstrap, prima che il webhook venga collegato.

```php
use Wonder\Plugin\Stripe\Connect;

Connect::addEvents(['payment_intent.processing']);
```

L'elenco è senza doppioni; stringhe vuote o non stringhe lanciano
`InvalidArgumentException`. `webhook($url, $events)` accetta anche un elenco
esplicito, che sostituisce quello composto. Dopo aver aggiunto eventi il
webhook va ricollegato: l'endpoint già registrato su Stripe non cambia da
solo. La creazione dell'endpoint non ha idempotency key: un doppio invio
crea due endpoint per lo stesso url, e il collegamento successivo li
cancella entrambi prima di crearne uno nuovo.

## PayPal

| Proprietà | Variabile `.env` | Colonna `security` |
|---|---|---|
| `paypal_live` | `PAYPAL_LIVE` | `paypal_live` |
| `paypal_client_id` | `PAYPAL_CLIENT_ID` | `paypal_client_id` |
| `paypal_client_secret` | `PAYPAL_CLIENT_SECRET` | `paypal_client_secret` |

`paypal_live=false` usa l'ambiente Sandbox; `true` usa la produzione.

## Nexi

| Proprietà | Variabile `.env` | Colonna `security` |
|---|---|---|
| `nexi_prod` | `NEXI_PROD` | `nexi_prod` |
| `nexi_api_key` | `NEXI_API_KEY` | `nexi_api_key` |
| `nexi_alias` | `NEXI_ALIAS` | `nexi_alias` |
| `nexi_mac_key` | `NEXI_MAC_KEY` | `nexi_mac_key` |

L'API Key serve a XPay Web/Global. Alias e chiave MAC sono le credenziali
separate di XPay classico. `Wonder\Plugin\Nexi\Nexi` accetta entrambi i set:

```php
$api = new Nexi($credentials->nexi_api_key, $credentials->nexi_prod);

$classic = new Nexi(
    '',
    $credentials->nexi_prod,
    $credentials->nexi_alias,
    $credentials->nexi_mac_key,
);
```

Per XPay classico, `classicPaymentMac()` firma l'avvio del pagamento e
`verifyClassicResultMac()` verifica la firma della notifica o dell'esito.
