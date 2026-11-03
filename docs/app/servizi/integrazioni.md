# Integrazioni

La pagina **Dev → Credenziali** (`SecurityResource`) mostra i servizi esterni
come una griglia di card, divise per categoria: Wonder Image, Google, Accesso,
Pagamenti, Email, Marketing, Fatturazione (e «Altro» per categorie non note).

Ogni card riporta:

- lo stato: **Da connettere**, **Parziale** (qualche campo compilato, non
  tutti quelli richiesti) o **Connessa**;
- il badge **Test** quando il servizio è in modalità di prova;
- il badge **`.env`** quando almeno un campo arriva dal file `.env`;
- una nota breve, per esempio **In uso per l'invio** sul servizio email attivo;
- l'avviso **Richiede Google Cloud Platform** quando dipende da
  un'integrazione non ancora connessa (la card resta al massimo Parziale).

Lo stato si calcola solo dai valori salvati e dal `.env`: la pagina non chiama
mai i servizi.

Un clic sulla card apre il suo modal con la guida, i campi, i contenuti propri
dell'integrazione (per Stripe le righe Produzione e Test con **Collega** e
**Ricollega webhook**) e il link alla guida del servizio. Il modal salva solo i
campi della sua integrazione:

- i campi che arrivano dal `.env` sono in sola lettura e non vengono salvati;
  una riga in cima al modal lo spiega. Se tutti i campi vengono dal `.env`, il
  modal ha solo **Chiudi**;
- le password restano vuote: un campo lasciato vuoto non modifica il valore
  salvato;
- un campo `integration` sconosciuto nel POST fa fallire il salvataggio.

SMTP e Brevo sono due card distinte. `mail_service` è un campo di modalità:
compare in entrambe ma non conta per lo stato. **Invia email di prova** è nei
loro modal: vedi [Credenziali email](configurazione/email.md#email-di-prova).

## Aggiungere, sostituire o togliere un'integrazione

Un'integrazione estende `Wonder\App\Integrations\Integration`:

```php
use Wonder\App\Integrations\Integration;

final class Mailchimp extends Integration
{
    public function key(): string { return 'mailchimp'; }
    public function name(): string { return 'Mailchimp'; }
    public function category(): string { return 'marketing'; }
    public function icon(): string { return 'bi-envelope-paper'; }
    public function description(): string { return 'Liste e campagne email.'; }

    // colonna di `security` => larghezza su 12
    public function fields(): array
    {
        return ['mailchimp_api_key' => 12];
    }
}
```

Le colonne devono già esistere nella tabella `security` e avere un campo nel
`formSchema()` della risorsa. Gli altri metodi, tutti facoltativi:

| Metodo | Uso |
| --- | --- |
| `modes()` | campi di modalità (ambiente, servizio d'invio), esclusi dallo stato |
| `required($values)` | campi che servono per dirla connessa (default: tutti tranne le modalità) |
| `testMode($values)` | accende il badge Test |
| `note($values)` | badge con una nota breve |
| `requires()` | chiave dell'integrazione da cui dipende |
| `readonly()` | modal senza salvataggio |
| `guide()`, `docsUrl()` | testo in cima al modal e link alla guida |
| `panel($values, $status)` | Element o HTML fidato in fondo al modal |
| `status($values)` | stato calcolato a mano, se le regole sopra non bastano |
| `order()` | ordine dentro la categoria |

Il registro è `Wonder\App\Integrations\Integrations`, sullo stesso schema di
`HomeStatus`:

- il core registra le sue in `Integrations::CORE`;
- un modulo abilitato aggiunge, sostituisce o toglie (`false`) voci con la
  chiave `backend.integrations` della propria configurazione:

  ```php
  return [
      'backend' => [
          'integrations' => [
              'mailchimp' => Mailchimp::class,
              'klaviyo' => false,
          ],
      ],
  ];
  ```

- il sito ha l'ultima parola con `Integrations::set('mailchimp', Mailchimp::class)`
  o `Integrations::set('nexi', false)`.

Un'integrazione che solleva un'eccezione viene registrata nel log
(`backend/integrations`) e saltata: il resto della pagina si apre comunque.

La card è l'Element `IntegrationCard` (scheda nel catalogo dei componenti).
