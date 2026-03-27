# Sign in with Apple

Questa pagina descrive come ottenere le chiavi per Sign in with Apple da inserire in:
- Backend > Config > Credentials
- campi: `apple_oauth_client_id`, `apple_oauth_team_id`, `apple_oauth_key_id`, `apple_oauth_private_key`, `apple_oauth_redirect_uri`

## Prerequisiti
- Apple Developer Program attivo
- Identifier/Service ID configurato
- Dominio e redirect validati

## Passi
1. Apri [Apple Developer](https://developer.apple.com/account/).
2. Vai su **Certificates, IDs & Profiles**.
3. Configura un **Service ID** con Sign in with Apple abilitato.
4. Configura dominio e Redirect URL.
5. Crea una **Key** abilitando Sign in with Apple.
6. Salva `Team ID`, `Key ID`, `Service ID (Client ID)`.
7. Scarica il file `.p8` e incolla il contenuto nel campo `apple_oauth_private_key`.

## Link ufficiali
- [Sign in with Apple JS](https://developer.apple.com/documentation/sign_in_with_apple/sign_in_with_apple_js)
- [Configure your webpage for Sign in with Apple](https://developer.apple.com/documentation/sign_in_with_apple/configuring_your_webpage_for_sign_in_with_apple)

## Note operative
- La `redirect_uri` deve essere identica a quella configurata nel Service ID.
- Tratta la private key `.p8` come segreto.
