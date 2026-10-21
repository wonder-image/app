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
solo. La creazione dell'endpoint riceve da CurlClient una chiave
d'idempotenza casuale per chiamata: protegge i retry, non un doppio invio.
Due collegamenti di fila creano due endpoint per lo stesso url, e il
collegamento successivo li cancella entrambi prima di crearne uno nuovo.

### Client e rete

Ogni `StripeClient` del plugin (`Stripe` e sottoclassi, `PaymentIntent`,
`Connect`) nasce da `Stripe::clientConfig($apiKey)`:

```php
[
    'api_key' => $apiKey,
    'stripe_version' => Stripe::API_VERSION, // '2025-08-27.basil'
    'max_network_retries' => 2,
]
```

`clientConfig()` chiama `Stripe::httpClient()`, che una volta per processo
porta il `CurlClient` condiviso dell'SDK a 20 s di timeout e 5 s di
connessione (invece di 80 e 30) e alza `\Stripe\Stripe::setMaxNetworkRetries(2)`.
Il valore globale serve: CurlClient aggiunge l'`Idempotency-Key` alle POST
v1 senza chiave solo se i retry globali sono più di zero, e senza chiave un
retry dopo un timeout può duplicare la creazione. Un client HTTP già
installato che non è `CurlClient` (il fake dei test) non viene toccato. Un
modulo che crea un suo `StripeClient` usa `new StripeClient(Stripe::clientConfig($key))`.

Un `StripeClient` creato senza `clientConfig()` dipende dall'ordine di
creazione: legge `max_network_retries` globale quando nasce, quindi ha 2
retry solo se `Stripe::httpClient()` è già stato chiamato nel processo
(altrimenti 0), mentre il `CurlClient` condiviso con 20/5 s lo usa in ogni
caso. Per un comportamento stabile va creato con `Stripe::clientConfig()`.

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
