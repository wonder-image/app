---
icon: house
---

# Cos'è wonder-image/app

`wonder-image/app` è il **core del framework** Wonder: una libreria PHP
distribuita come pacchetto Composer (`wonder-image/app`). Non è un sito: è il
motore che un **sito** installa sotto `vendor/wonder-image/app` e usa per
costruire frontend, backend, API, CRUD, permessi e gestione utenti.

{% hint style="info" %}
**Per chi è questa documentazione.** Per chi sviluppa o estende il framework e
per chi costruisce un sito sopra di esso. Se stai lavorando su un progetto
scaffoldato da [`wonder-image/new-site`](https://github.com/wonder-image/new-site),
quasi tutto ciò che ti serve è in **Concetti fondamentali**.

**Percorso di lettura consigliato:** Introduzione → Concetti fondamentali (in
ordine) → Piattaforma come reference quando serve.
{% endhint %}

## Le due parti del sistema

| Termine | Cos'è | Dove vive |
|---|---|---|
| **framework** (`wonder-image/app`) | Il core: Model, Resource, Form, Tabelle, Permessi, Moduli | repo `wonder-image/app`; in un sito sta in `vendor/wonder-image/app/` |
| **site** (es. `new-site`) | Il progetto reale: pagine, contenuti, configurazioni | repo del progetto; usa il framework sotto `vendor/` |
| **lib** (`wonder-image/lib`) | Il design system JS/CSS (classi `.wi-*`) | pacchetto npm `wonder-image` |
| **module** (`wonder-image/<slug>`) | Pacchetto opzionale che aggiunge Model/Resource/route | scoperto via Composer, abilitato dal sito |

## Le 7 aree fondamentali

Il framework si capisce seguendo un'unica catena:
**Modulo → Risorsa → Form → Tabella → Database → Permessi → Componenti**.

| Area | Cosa fa | Pagina |
|---|---|---|
| **Moduli** | Pacchetti che estendono il framework | [Moduli](concetti/moduli/README.md) |
| **Risorse e Model** | CRUD su una tabella: form, lista, API | [Risorse e Model](concetti/risorse/README.md) |
| **Creazione Form** | Dichiarare input con `FormField` | [Form](concetti/form/README.md) |
| **Render tabelle** | Liste backend con `TableColumn` | [Render delle tabelle](concetti/tabelle/README.md) |
| **Database** | `dataSchema()` + `tableSchema()`, migrazioni | [Model e Database](concetti/risorse/database.md) |
| **Utenti e permessi** | Authority, ruoli, gestione utenti | [Utenti e Permessi](concetti/utenti/README.md) |
| **Componenti** | Card, Container, Alert, Button, Badge, Dropdown | [Componenti UI](concetti/componenti/README.md) |

Per vedere come si incastrano (e i 4 flussi tipici: creazione, modifica,
visualizzazione lista, accesso negato) parti dalla
[Mappa end-to-end](concetti/mappa-end-to-end.md).

## Avvio rapido

```bash
composer create-project wonder-image/new-site:dev-main nome-progetto
cd nome-progetto
php forge config
php forge update --local
php forge db:init --admin-host=127.0.0.1 --admin-port=3306 --admin-username=root --admin-password=secret
php forge start
```

Dettagli in [Avvio rapido](introduzione/avvio-rapido.md) e
[Installazione e Deploy](piattaforma/installazione-e-deploy.md).

## Bootstrap (entrypoint)

Il punto di ingresso del pacchetto è
[`wonder-image.php`](https://github.com/wonder-image/app): risolve `ROOT`,
carica l'autoloader del sito e poi `function`, `config`, `service`,
`middleware` e infine `bootstrap/backend.php` o `bootstrap/frontend.php`.
Spiegato in [Architettura in 5 minuti](introduzione/architettura.md).

{% hint style="warning" %}
**Regola di manutenzione.** Quando una modifica cambia architettura,
bootstrap/runtime, layout strutturale, convenzioni per sviluppatori o punti di
estensione, il lavoro non è completo finché non vengono aggiornati **insieme**:
la documentazione pertinente sotto `docs/app/*`, `AGENTS.md` e la skill AI
rilevante. Non modificare `.agents/` a mano: aggiorna la skill alla sorgente e
poi risincronizza con `npx skills`.
{% endhint %}
