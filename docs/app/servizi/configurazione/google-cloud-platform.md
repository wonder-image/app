# Google Cloud Platform

### Creazione Progetto

Per trovare l'ID Progetto crea o seleziona un progetto da [Google Cloud](https://console.cloud.google.com) in alto a sinistra.

### Creazione API Key

Per crearle accedi a [Google Cloud](https://console.cloud.google.com/apis/credentials) e premi su `+ Crea credenziali` ⇒ Chiave API,  dovrai creare 2 chiave la prima pubblica la seconda privata.&#x20;

#### Chiave pubblica

* Nome: **Chiave Pubblica**
* Restrizioni delle applicazioni: **Siti Web**
* Limitazioni degli indirizzi IP: **\*.dominio.tld/\***
* Restrizioni delle API: **Non limitare la chiave**

#### Chiave privata

* Nome: **Chiave Privata**
* Restrizioni delle applicazioni: **Indirizzi IP**
* Limitazioni degli indirizzi IP: **Indirizzo IP del server**
* Restrizioni delle API: **Non limitare la chiave**

Se la chiave pubblica è vuota, `Credentials::api()->gcp_client_api_key` ricade sulla chiave privata, che così finisce nella pagina (`GOOGLE_API_KEY`): compila sempre entrambe. Per sapere cosa manca davvero usa `Credentials::storedApi()`, che restituisce i valori salvati (`.env`, poi riga di security) senza questo ripiego.

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
