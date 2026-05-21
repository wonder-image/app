# Sezione `ai` in `module.json`

Un modulo composer pubblica agenti AI dichiarando una sezione `ai` nel
suo `module.json`. La sezione è **opzionale**: un modulo senza la
sezione non partecipa alla discovery AI (è un modulo "solo Resource"
o "solo Route").

## Schema

```json
{
  "name": "Wonder RSVP",
  "slug": "rsvp",
  "version": "0.1.0",
  "...": "altri campi standard",

  "ai": {
    "agents": "ai/agents",
    "prompts": "ai/prompts",
    "tools": "ai/tools"
  }
}
```

Tutti i sotto-campi sono **opzionali**. Se presenti, devono essere path
relativi al root del modulo. Default in caso di campo assente:

| Campo | Default |
|---|---|
| `ai.agents` | `ai/agents` |
| `ai.prompts` | `ai/prompts` |
| `ai.tools` | `ai/tools` |

Il modulo che vuole solo agenti (senza prompt shared né tool) può
dichiarare solo:

```json
"ai": {}
```

e la directory `ai/agents` verrà scoperta automaticamente (se esiste
fisicamente).

## Validazione

`Wonder\App\Module\ManifestValidator` verifica:

- Ogni path AI dichiarato è **dentro al root del modulo** (no
  `../../`, no path assoluti)
- Esistenza fisica delle directory **non è obbligatoria**: un modulo
  può dichiarare `ai.agents` anche se la cartella sarà popolata
  dopo. La discovery la ignora se vuota.

## Struttura attesa dentro `ai/agents/`

```
vendor/wonder-image/rsvp/
├── module.json                    (con sezione `ai`)
└── ai/
    ├── agents/
    │   ├── guest-list-summarizer/
    │   │   ├── agent.yml
    │   │   └── prompt.md
    │   └── rsvp-reminder-composer/
    │       ├── agent.yml
    │       └── prompt.md
    ├── prompts/
    │   └── shared/
    │       └── tone-friendly.md
    └── tools/
        └── fetch_guest_count.php
```

## Discovery API

Il framework espone questi metodi su `Wonder\App\Module\Registry`
(usati internamente da `AgentRegistry`):

```php
ModuleRegistry::aiAgentDirectories();   // array di {path, priority: 20, source: 'module:<slug>'}
ModuleRegistry::aiPromptDirectories();
ModuleRegistry::aiToolDirectories();
```

Solo i moduli **enabled** (`StateRepository::isEnabled($slug)`) partecipano.

## Best practice per i moduli

1. **Dichiara la sezione `ai` solo se hai davvero agenti**. Lasciarla
   vuota non crea problemi (la discovery ignora le directory inesistenti),
   ma può confondere chi legge il manifest.

2. **Naming degli agenti del modulo**: prefisso o no? Nessuna regola
   forzata, ma per evitare collision tra moduli (es. `rsvp-` e
   `blog-`) usa nomi specifici al dominio. Esempio:
   - ✅ `guest-list-summarizer` (modulo rsvp)
   - ✅ `article-outline-writer` (modulo blog)
   - ❌ `summarizer` (troppo generico, può fare collision)

3. **README per modulo**: nel `ai/README.md` del modulo elenca gli
   agenti che pubblichi + descrizione 1-riga. Aiuta i consumer a
   sapere cosa è disponibile.

4. **Documenta gli override consigliati**: se un agente accetta
   parametri (via overrides.yml), spiegalo nel README. Esempio:

   > L'agente `rsvp-reminder-composer` di default usa
   > `temperature: 0.3` per un tono naturale. Per email transazionali
   > più formali, in `overrides.yml`:
   > ```yaml
   > rsvp-reminder-composer:
   >   temperature: 0
   > ```

## Esempio completo

`vendor/wonder-image/rsvp/module.json`:

```json
{
  "name": "Wonder Image RSVP",
  "slug": "rsvp",
  "version": "0.2.0",
  "description": "Gestione eventi e iscrizioni RSVP",
  "namespace": "Wonder\\Plugin\\Rsvp\\",
  "entrypoint": "Wonder\\Plugin\\Rsvp\\Module",
  "frameworkCompatibility": {
    "wonder-app": "^2.1",
    "php": "^8.2"
  },

  "database": {
    "models": "src/Models"
  },
  "resources": {
    "classes": "src/Resources"
  },
  "routes": {
    "frontend": "routes/route.frontend.php",
    "backend": "routes/route.backend.php"
  },

  "ai": {
    "agents": "ai/agents"
  }
}
```

Nota che `ai.prompts` e `ai.tools` sono omessi: il modulo pubblica
solo agenti. Le sotto-directory inesistenti sono ignorate.
