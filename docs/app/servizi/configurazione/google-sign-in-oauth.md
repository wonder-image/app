# Google Auth Platform

Questa guida descrive come creare le credenziali OAuth 2.0 per l'accesso con
Google e salvarle in **Backend > Dev > API e servizi > Credenziali**, nella
sezione **Google Cloud Platform > Google Auth Platform**:

- **Google Client ID** (`google_oauth_client_id`);
- **Google Client Secret** (`google_oauth_client_secret`).

Google Auth Platform appartiene a un progetto Google Cloud. Crea un progetto
separato per ogni cliente o brand e separa almeno test e produzione. È
preferibile che il progetto di produzione appartenga al cliente e che Wonder
Image riceva l'accesso necessario tramite IAM.

## 1. Creare o selezionare il progetto

1. Apri la [Google Cloud Console](https://console.cloud.google.com/).
2. Dal selettore in alto crea o scegli il progetto del cliente e dell'ambiente.
3. Apri **Google Auth Platform**.

Per la produzione non riutilizzare un progetto di sviluppo: branding, audience,
origini autorizzate e gestione delle credenziali devono restare isolati.

## 2. Configurare Branding e Audience

In **Google Auth Platform > Branding** configura almeno:

- nome dell'applicazione;
- email di assistenza;
- dominio applicazione;
- home page, Privacy Policy e Termini e condizioni pubblici.

I domini devono appartenere al cliente o essere autorizzati e, quando Google lo
richiede, verificati tramite Search Console.

In **Audience** scegli il tipo adatto:

- **Internal** solo per utenti della stessa organizzazione Google Workspace;
- **External** per clienti e utenti esterni.

Durante lo sviluppo mantieni lo stato **Testing** e aggiungi gli account
necessari tra i test user, quando richiesto. Pubblicare l'app non corregge errori
di origine o redirect non validi.

## 3. Creare il client Web

1. Apri [Google Auth Platform > Clients](https://console.cloud.google.com/auth/clients).
2. Premi **Create client**.
3. Seleziona **Web application**.
4. Assegna un nome riconoscibile, per esempio `Negozio produzione`.
5. In **Authorized JavaScript origins** aggiungi l'origine completa del sito.

Un'origine contiene esclusivamente protocollo, host ed eventuale porta:

```text
https://www.example.com
https://staging.example.com
http://localhost:8080
```

Non inserire path, query string, slash finale o wildcard. Protocollo, dominio e
porta devono coincidere esattamente con la pagina che mostra il pulsante Google.
Google accetta `localhost` per lo sviluppo locale, ma non domini riservati come
`example.test`: in quel caso usa `localhost` oppure uno staging HTTPS pubblico.

Il flusso Google Identity Services attuale riceve l'ID token tramite callback
JavaScript, quindi non richiede una **Authorized redirect URI**. Configurala
soltanto quando un progetto introduce esplicitamente un flusso server-side con
authorization code; la URI dovrà allora coincidere esattamente con l'endpoint.

6. Premi **Create**.

## 4. Copiare Client ID e Client Secret

Al termine della creazione Google mostra:

- **Client ID**, con formato simile a
  `1234567890-abc123.apps.googleusercontent.com`;
- **Client Secret**, da trattare come una password.

Dal 2025 Google mostra e permette di scaricare il Client Secret soltanto al
momento della creazione. Copialo subito e conservalo in un password manager o
secret manager. Se viene perso, crea o ruota il secret dal dettaglio del client:
non inserirlo nel repository e non inviarlo al browser.

Nel backend salva entrambi i valori nella sezione **Google Auth Platform**. In
alternativa possono essere forniti nell'ambiente del sito:

```dotenv
GOOGLE_OAUTH_CLIENT_ID="...apps.googleusercontent.com"
GOOGLE_OAUTH_CLIENT_SECRET="..."
```

Le variabili d'ambiente hanno precedenza sui valori salvati nel database.

Il login tramite Google Identity Services usa attualmente soltanto il Client
ID: il backend verifica firma, issuer, audience, scadenza e nonce dell'ID token.
Il Client Secret resta disponibile per futuri flussi OAuth server-side e non
deve mai essere esposto nel markup o in JavaScript.

## 5. Verifica

1. Apri la pagina di login dal dominio registrato.
2. Premi il pulsante Google; il flusso federato non dipende da reCAPTCHA.
3. Controlla accesso con account esistente e registrazione con nuovo account.
4. Ripeti la verifica per ogni origine e ambiente configurato.

Errori frequenti:

- `invalid_request`: origine, protocollo o porta non coincidono con il client;
- `redirect_uri_mismatch`: la redirect URI inviata non coincide con quella
  registrata;
- accesso negato in Testing: account non incluso nell'audience di test;
- pulsante assente: Client ID non configurato nel backend o nel `.env`.

## Documentazione ufficiale

- [Configurare Google Identity Services](https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid)
- [Gestire i client OAuth](https://support.google.com/cloud/answer/15549257)
- [Policy OAuth 2.0](https://developers.google.com/identity/protocols/oauth2/policies)
- [Best practice Sign in with Google](https://developers.google.com/identity/siwg/best-practices)
