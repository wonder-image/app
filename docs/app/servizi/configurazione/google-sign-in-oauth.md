# Google Sign-In OAuth

Questa pagina descrive come ottenere le chiavi per il login federato Google da inserire in:
- Backend > Config > Credentials
- campi: `google_oauth_client_id`, `google_oauth_client_secret`, `google_oauth_redirect_uri`

## Prerequisiti
- Progetto Google Cloud attivo
- OAuth consent screen configurata
- Dominio autorizzato

## Passi
1. Apri [Google Cloud Console](https://console.cloud.google.com/).
2. Seleziona il progetto.
3. Vai su **APIs & Services > OAuth consent screen** e completa la configurazione.
4. Vai su **APIs & Services > Credentials**.
5. Crea **OAuth client ID** (tipo Web application).
6. Inserisci la Redirect URI esatta usata dal progetto.
7. Copia Client ID e Client Secret nel backend.

## Link ufficiali
- [Google Identity - OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect)
- [Create OAuth client credentials](https://support.google.com/googleapi/answer/6158849)

## Note operative
- La `redirect_uri` deve combaciare al 100% con quella registrata su Google.
- Se cambi dominio/ambiente, aggiorna la configurazione OAuth.
