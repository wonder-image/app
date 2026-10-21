# Google Tag Manager

{% hint style="info" %}
Link di accesso  \[ [https://tagmanager.google.com](https://tagmanager.google.com/) ]
{% endhint %}

## Guida per l'Inserimento del Codice di Analisi

1. **Accedi al Pannello di Controllo:** Effettua l'accesso per creare un nuovo account.
2. **Copia il Codice del Contenitore:** Assicurati di copiare correttamente il codice fornito.
3. **Accedi al Backend del Sito Web:**
   * Vai alla sezione `Dev > API e servizi > Analitica`.
   * Incolla il codice nell'input appropriato.
4. **Attivazione:** Decidi se attivare immediatamente il codice incollato.

Segui attentamente questi passaggi per garantire una configurazione corretta.

***

## Preconfigurazione

Accedi al progetto, clicca su **Amministrazione** in alto a sinistra, poi vai nella sezione del contenitore e clicca su **Importa Contenitore**. Scarica il seguente file.

{% file src="../../.gitbook/assets/GTM-Default.json" %}

Carica il file selezionato nell'input appropriato, imposta come area di lavoro **Default Workspace** e come opzione di importazione **Sostituisci**, successivamente premi su **Aggiungi all'area di lavoro**.

***

## Configura

Vai ai tag e successivamente imposta:

1. **iubenda:** Segui la guida [iubenda.md](iubenda.md "mention") e inserisci in **CS configuration** il codice JSON (Current) esportato dal sito di iubenda
2. **GA4:** Segui la guida [google-analytics.md](google-analytics.md "mention") e inserisci in **ID tag** il codice G-XXXXX esportato da Google Analytics

### Nota architetturale

Per i progetti Wonder il setup standard desiderato e':

- codice Google Tag Manager nel sito
- tag Google Analytics 4 configurato dentro GTM

Nel tempo questo sostituira' progressivamente il vecchio blocco statistiche proprietario del framework.


### Consenso e dataLayer

Il layout frontend (`app/view/components/frontend/layout/head.php`) scrive,
prima del loader GTM, lo script di `Wonder\Frontend\Support\DataLayer`:

- `gtag('consent', 'default', …)` con `ad_storage`, `analytics_storage`,
  `ad_user_data` e `ad_personalization` a `denied` (Consent Mode) e
  `wait_for_update: 500`. Il core non ha un banner: il tag Iubenda in GTM
  aggiorna il consenso con `gtag('consent', 'update', …)`, e i tag Google
  aspettano quell'aggiornamento fino a 500 ms prima di partire con il default
  (serve perché Iubenda si carica dentro GTM, dopo il default);
- `{ user: { id } }`, dove `id` è un HMAC dell'id utente con `APP_KEY` (32
  caratteri esadecimali, stabile per utente), `null` senza utente o senza
  `APP_KEY`. Il core non manda più l'id numerico.

{% hint style="warning" %}
Con il modulo ecommerce serve la versione con il fix `fix/2026-10-2d-datalayer`:
le versioni precedenti fanno un secondo push `{ user: { id: 7 } }` con l'id in
chiaro e, nel merge di GTM, l'ultimo valore vince. Dal fix ecommerce non manda
più `user` e resta l'hash del core.
{% endhint %}

Il Meta Pixel (`Dev > API e servizi > Analitica`, `pixel_facebook`) è ancora
emesso senza attendere il consenso.
