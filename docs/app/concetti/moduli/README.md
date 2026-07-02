---
icon: plug
---

# Moduli

## Cos'è

Un **modulo** è un pacchetto `wonder-image/<slug>` che estende il framework
aggiungendo Model, Resource, route, traduzioni e permessi, senza modificare né
il core né il sito. Viene **scoperto** automaticamente e **abilitato** dal sito.

## A cosa serve

Distribuire funzionalità riutilizzabili (es. un blog, un catalogo, un'integrazione)
come pacchetto installabile, con un contratto chiaro verso il framework.

## Dove si trova nel codice

| Elemento | File |
|---|---|
| Contratto | `class/App/Module/Contracts/ModuleInterface.php` |
| Scoperta | `class/App/Module/Discovery.php` |
| Manifest | `class/App/Module/Manifest.php` |
| Validazione | `class/App/Module/ManifestValidator.php` |
| Registro | `class/App/Module/Registry.php` |
| Comandi forge | `class/Console/Commands/StatusModules.php`, `ValidateModule.php`, `PublishModule.php` |

## Come si collega al resto

1. **Discovery** trova i pacchetti candidati da più sorgenti.
2. **ManifestValidator** valida il `module.json`.
3. **Registry** tiene i moduli abilitati ed espone i loro percorsi
   (model, resource, route, lang) e fa il **merge dei permessi**.
4. Model/Resource del modulo entrano nei registry con la loro precedenza; le
   route vengono caricate; i permessi confluiscono in `$PERMITS`.

## Comandi utili

```bash
php forge status:modules     # stato dei moduli scoperti/abilitati
php forge validate:module <slug>   # valida il manifest di un modulo
php forge publish:module <slug>    # pubblica tutte le view overrideabili del modulo
```

(I comandi esistono in `class/Console/Commands/StatusModules.php` e
`ValidateModule.php`, `PublishModule.php`.)

## Abilitare un modulo (lato sito)

Il sito abilita i moduli in `custom/config/modules.php`. Solo i moduli
**abilitati** vengono caricati da `Registry::enabled()`.

## Le pagine di questa sezione

- [Sistema moduli](sistema.md) — scoperta, sorgenti, precedenza, registro.
- [Contratto modulo (ModuleInterface)](contratto.md) — l'interfaccia minima.
- [Manifest module.json](manifest.md) — campi e validazioni reali.

{% hint style="info" %}
Esiste anche un documento di **progetto** più ampio sul sistema moduli (lifecycle,
event bus, ecc.): è una **proposta non ancora implementata**, tenuta separata in
[Proposte → Sistema moduli](../../proposte/module-system-project.md). Questa
sezione documenta solo ciò che è realmente nel codice.
{% endhint %}
