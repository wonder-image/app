# Google Cloud Platform

### Creazione Progetto

Per trovare l'ID Progetto crea o seleziona un progetto da [Google Cloud](https://console.cloud.google.com) in alto a sinistra.

### API da abilitare

L'elenco delle API da abilitare è nella pagina **Credenziali** del backend,
nella scheda Google Cloud Platform → `API da abilitare in Google Cloud`: ogni
nome apre la pagina della console del progetto, con il tipo di chiave che la
usa e se è obbligatoria. L'elenco viene da `Wonder\App\Support\GoogleCloud\GoogleApis`
e lo completano i moduli e il sito (vedi [Registro delle API](#registro-delle-api)).

API del core:

| API (nome in console) | Servizio | Chiave | Per cosa |
|---|---|---|---|
| reCAPTCHA Enterprise API | `recaptchaenterprise.googleapis.com` | server | verifica reCAPTCHA v2/v3 dei form (obbligatoria) |
| Maps JavaScript API | `maps-backend.googleapis.com` | client | mappe e libreria Maps nel browser |
| Places API (New) | `places.googleapis.com` | client | autocompletamento indirizzi |
| Places API (New) | `places.googleapis.com` | server | sync notturno di orari e recensioni dal Place ID |
| Maps Embed API | `maps-embed-backend.googleapis.com` | client | mappe in iframe |
| Directions API | `directions-backend.googleapis.com` | client | percorsi sulla mappa, solo se configurati |
| Google Auth Platform | — | client OAuth | Accedi con Google (Client ID) |

### Creazione API Key

Per crearle accedi a [Google Cloud](https://console.cloud.google.com/apis/credentials) e premi su `+ Crea credenziali` ⇒ Chiave API. Servono 2 chiavi, una pubblica e una privata.

#### Chiave pubblica (`gcp_client_api_key`)

* Nome: **Chiave Pubblica**
* Restrizioni delle applicazioni: **Siti Web**
* Referrer: **\*.dominio.tld/\***
* Restrizioni delle API: **limita la chiave** alle API con chiave client dell'elenco (Maps JavaScript API, Places API (New), e Maps Embed / Directions se usate)

#### Chiave privata (`gcp_api_key`)

* Nome: **Chiave Privata**
* Restrizioni delle applicazioni: **Indirizzi IP** con l'IP del server, solo se il server esce sempre dallo stesso IP; altrimenti **Nessuna**
* Restrizioni delle API: **limita la chiave** alle API con chiave server dell'elenco (reCAPTCHA Enterprise API, e Places API (New) se usi il sync della sede)

La chiave privata non deve mai finire nel browser: compila sempre anche la
chiave pubblica, perché se `gcp_client_api_key` è vuota `Credentials::api()`
usa `gcp_api_key` al suo posto e la stampa in `GOOGLE_API_KEY` nella pagina.

La chiave pubblica è l'unica che arriva nella pagina (`GOOGLE_API_KEY`): se è vuota mappe e completamento degli indirizzi restano spenti, la chiave privata non la sostituisce. Per sapere cosa manca usa `Credentials::storedApi()`, i valori salvati (`.env`, poi riga di security) senza ripieghi.

### reCAPTCHA

Per creare la chiave del sito [clicca qui](https://www.google.com/recaptcha/admin/create), successivamente compila i campi come segue:

* Etichetta: **example.com**
* Tipo di reCAPTCHA: **Verfica (v2)** ⇒ **Casella di controllo "Non sono un robot"**
* Dominio: **example.com**
* Piattaforma Google Cloud: **Seleziona il progetto creato in precedenza**

Successivamente è possibile cambiare il livello di [clicca qui](https://console.cloud.google.com/security/recaptcha) poi cerca la chiave e premi su `Dettagli chiave`, successivamente sulla destra c'è **Impostazione di verifica** e premi su `Configura` e seleziona la difficoltà della verifica, se viene utilizzata solo per form di contatto/registrazione va bene quella facile/bilanciata.

### Google Place

Per trovare il Place Id [clicca qui](https://developers.google.com/maps/documentation/geocoding/overview#how-the-geocoding-api-works), vai fino alla mappa e ricerca il luogo, cerca e poi copia il Place Id.

### Google Auth Platform

Google Auth Platform usa lo stesso progetto Google Cloud per creare il client
OAuth 2.0 dell'accesso con Google. Segui la guida dedicata per configurare
branding, audience, origini JavaScript e recuperare Client ID e Client Secret:
[Google Auth Platform](google-sign-in-oauth.md).

### Registro delle API

`GoogleApis::all()` unisce, in ordine, le API del core, quelle dei moduli
abilitati che implementano `Wonder\App\Module\Contracts\ModuleGoogleApis` e
quelle del sito in `custom/config/google-apis.php`. Ogni voce è una
`GoogleApi`; la stessa chiave dichiarata più volte viene unita: è obbligatoria
se lo è per una sorgente, e campi e utilizzatori si sommano.

```php
// Entrypoint di un modulo
final class Module implements ModuleInterface, ModuleGoogleApis
{
    public static function googleApis(): iterable
    {
        yield GoogleApi::make('places')->required()->usedBy('ecommerce');
        yield GoogleApi::make('routes')
            ->name('Routes API')
            ->service('routes.googleapis.com')
            ->keyType(GoogleApi::KEY_SERVER)
            ->fields(['gcp_api_key'])
            ->purpose('ecommerce.google_apis.routes')
            ->usedBy('ecommerce');
    }
}
```

`custom/config/google-apis.php` ritorna un iterable di `GoogleApi` con la
stessa forma. Una nuova API deve avere la sua descrizione nei file lang: per
default la chiave è `google.apis.{key}.purpose`, con `purpose()` se ne indica
un'altra (quella del modulo o del sito).

`GoogleApis::missing($valori)` ritorna le API obbligatorie con un campo
vuoto. Passa i valori salvati (riga di security o `.env`), non
`Credentials::api()`, che riempie la chiave client con quella server.

### Futuro: automazione

La configurazione del progetto Google Cloud resta manuale. In futuro un
comando `forge` potrà usare `gcloud` per creare il progetto, abilitare i
servizi del registro (`gcloud services enable` con il campo `service` di ogni
`GoogleApi`) e creare le due chiavi con le restrizioni qui sopra.
