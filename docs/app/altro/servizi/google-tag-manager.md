# Google Tag Manager

{% hint style="info" %}
Link di accesso  \[ [https://tagmanager.google.com](https://tagmanager.google.com/) ]
{% endhint %}

## Guida per l'Inserimento del Codice di Analisi

1. **Accedi al Pannello di Controllo:** Effettua l'accesso per creare un nuovo account.
2. **Copia il Codice del Contenitore:** Assicurati di copiare correttamente il codice fornito.
3. **Accedi al Backend del Sito Web:**
   * Vai alla sezione `Set Up > Analitica`.
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



