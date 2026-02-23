# Roadmap PHP 8.4 + Symfony 8

Obiettivo: migrare la piattaforma a PHP 8.4 e integrare Symfony 8 a componenti, mantenendo il framework Wonder come core applicativo.

## Principi

- Migrazione incrementale, senza riscrivere tutto in una volta.
- Compatibilità controllata con adapter e wrapper temporanei.
- Ogni fase deve chiudersi con test e rollback plan.

## Fase 0 - Baseline e sicurezza

### Attività

- Congelare una baseline stabile (tag/release interna).
- Definire ambienti: locale, staging, produzione.
- Preparare check automatici minimi: lint, smoke test, test DB base.
- Mappare classi legacy `DEPRECATO` da rimuovere progressivamente.

### Deliverable

- Documento stato iniziale (dipendenze, versioni PHP, rischi aperti).
- Checklist release/rollback.

### Punti critici

- Assenza di test automatici estesi.
- Dipendenze legacy usate indirettamente (soprattutto in `1.5.0`).

## Fase 1 - Upgrade runtime a PHP 8.4

### Attività

- Aggiornare ambienti (dev/staging/prod) a PHP 8.4.
- Aggiornare `composer.json` (`"php": "^8.4"`).
- Eseguire `composer update` controllato.
- Verificare compatibilità librerie core (Stripe, PhpSpreadsheet, ecc.).

### Deliverable

- Build funzionante in staging con PHP 8.4.
- Report incompatibilità e fix applicati.

### Punti critici

- Breaking changes su librerie non allineate a PHP 8.4.
- Possibili warning/deprecazioni in codice custom con typing debole.

## Fase 2 - Introduzione Symfony 8 (componenti minimi)

### Pacchetti consigliati

- `symfony/http-foundation`
- `symfony/routing`
- `symfony/dependency-injection`
- `symfony/config`
- `symfony/event-dispatcher`
- `symfony/cache` (opzionale ma consigliato)

### Attività

- Aggiungere dipendenze Symfony 8 in `composer.json`.
- Creare bootstrap container servizi (`Wonder\App` + servizi core).
- Introdurre adapter Request/Response senza rompere API esistenti.
- Definire router centralizzato con bridge verso controller/resource attuali.

### Deliverable

- Primo flusso end-to-end su route Symfony + risposta HTTP Foundation.
- Container DI attivo su almeno un modulo reale.

### Punti critici

- Mescolanza temporanea tra vecchio bootstrap e nuovo lifecycle.
- Service wiring ambiguo senza naming convention chiara.

## Fase 3 - Integrazione con Model/Resource

### Attività

- Formalizzare contratti per `Model` e `Resource` (metodi, responsabilità).
- Separare chiaramente:
  - schema dati/validazione (`Wonder\Data`)
  - presentazione (`Wonder\Support\Prettify`, `Themes`)
  - orchestration HTTP (Symfony components)
- Uniformare la gestione input in `Model::validate(...)` con contesto request.

### Deliverable

- Flusso CRUD standardizzato con Resource + route Symfony.
- Convenzioni documentate per nuove risorse.

### Punti critici

- Duplicazione logica tra layer (validator, formatter, prettify).
- Incoerenza naming tra moduli legacy e nuovi namespace.

## Fase 4 - Qualità, performance, osservabilità

### Attività

- Aggiungere test automatizzati su:
  - validators (`TinValidator`, `VatValidator`)
  - prettify (`Date`, `Phone`, `Address`)
  - flussi `Model/Resource`.
- Introdurre cache strategica (config, routing, metadata).
- Misurare tempi endpoint critici (prima/dopo migrazione).

### Deliverable

- Test suite minima stabile in CI.
- KPI performance baseline vs nuova architettura.

### Punti critici

- Regressioni silenziose su form/legacy functions.
- Performance peggiorata in caso di container bootstrap non ottimizzato.

## Fase 5 - Decommissioning legacy

### Attività

- Rimuovere classi marcate `DEPRECATO` quando non più referenziate.
- Eliminare namespace obsoleti (`Plugin/Custom` non più necessari).
- Consolidare docs finali (struttura, regole naming, esempi).

### Deliverable

- Codebase pulita senza bridge legacy inutili.
- Versione stabile “post-migrazione”.

### Punti critici

- Rimozione prematura di wrapper ancora usati in percorsi non coperti da test.
- Documentazione non aggiornata rispetto al codice reale.

## Checklist tecnica sintetica

- [ ] PHP 8.4 operativo in tutti gli ambienti.
- [ ] Composer aggiornato senza conflitti bloccanti.
- [ ] Symfony 8 componenti installati e bootstrap attivo.
- [ ] Un modulo completo migrato (route -> resource -> model -> response).
- [ ] Test minimi automatici in CI.
- [ ] Rimozione progressiva classi `DEPRECATO`.

## Rischi principali da monitorare

- Compatibilità librerie terze parti con PHP 8.4/Symfony 8.
- Doppio stack runtime durante la transizione.
- Debito tecnico su codice storico `1.5.0`.
- Mancanza copertura test sulle aree business più sensibili.

