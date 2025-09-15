---
description: Passaggio da versioni <1.4.5
---

# Versione <1.4.5

Necessario creare il seguente file forge

{% @github-files/github-code-block url="https://github.com/wonder-image/new-site/blob/main/forge" %}

Necessario modificare il file composer.json

{% @github-files/github-code-block url="https://github.com/wonder-image/new-site/blob/main/composer.json" %}

{% hint style="warning" %}
Questo permette di scaricare direttamente tutte le librerie npm sul server quindi sarà poi necessario caricare le cartelle `node_modules` e `vendor` consigliamo tramite FileZilla.     &#x20;
{% endhint %}

{% hint style="warning" %}
Tutte le dipendenze verranno gestite dalla classe `Wonder\App\Dependencies` che permette l'importazione delle librerie.
{% endhint %}

