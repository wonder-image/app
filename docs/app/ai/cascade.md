# Cascade file-level — risoluzione di un agente

`Wonder\AI\AgentResolver` risolve un agente cercando **ogni file singolo**
nei layer disponibili, partendo dalla priority più alta.

## Algoritmo

Per ogni slug agente:

1. Costruisci la lista dei layer ordinati per priority desc:
   - 30 → `<consumer>/ai/agents/`
   - 20 → ogni `<modulo enabled>/ai/agents/`
   - 10 → `<framework>/ai/agents/`

2. Per ciascuno dei file canonici (`agent.yml`, `prompt.md`,
   `tools.yml`, `schema.yml`), itera i layer in ordine e prendi il
   **primo trovato**.

3. Parse `agent.yml`, applica `defaults` di `config.yml`, applica
   eventuale `overrides.yml[slug]`.

4. Risolvi il path del `prompt.md` (può essere override custom via
   campo `prompt:` in agent.yml).

5. Costruisci un `Agent` con `$sources` che mappa filename → layer
   d'origine (`'app'`, `'module:rsvp'`, `'consumer'`).

## Esempi

### Solo framework

```
wonder-image/app/ai/agents/seo-meta-writer/
├── agent.yml
└── prompt.md
```

Risoluzione: tutto da `app`. `$sources = { agent.yml: 'app', prompt.md: 'app' }`.

### Consumer overrida solo prompt

```
wonder-image/app/ai/agents/seo-meta-writer/
├── agent.yml
└── prompt.md

new-site/ai/agents/seo-meta-writer/
└── prompt.md         ← versione italiana custom
```

Risoluzione:
- `agent.yml` da `app` (priority 10, l'unico che lo possiede)
- `prompt.md` da `consumer` (priority 30 > 10)

`$sources = { agent.yml: 'app', prompt.md: 'consumer' }`. Il sito usa
i settings del framework e il prompt italiano.

### Modulo + consumer

```
vendor/wonder-image/rsvp/ai/agents/rsvp-reminder-composer/
├── agent.yml
└── prompt.md

new-site/ai/agents/rsvp-reminder-composer/
└── prompt.md         ← tono custom per questo cliente
```

Risoluzione:
- `agent.yml` da `module:rsvp` (priority 20)
- `prompt.md` da `consumer` (priority 30)

### Tutti e 3 i layer

```
wonder-image/app/ai/agents/seo-meta-writer/
├── agent.yml
└── prompt.md

vendor/wonder-image/blog/ai/agents/seo-meta-writer/   # ⚠️ collision
├── agent.yml
└── prompt.md

new-site/ai/agents/seo-meta-writer/
└── prompt.md
```

Risoluzione:
- `agent.yml`: cerca priority desc → trova in `module:blog` (20) prima
  di `app` (10) → vince `module:blog`
- `prompt.md`: trova in `consumer` (30) → vince `consumer`

`$sources = { agent.yml: 'module:blog', prompt.md: 'consumer' }`.

> ⚠️ **Naming collision tra layer**: lo stesso slug a layer diversi è
> consentito (è il meccanismo di override). Tra moduli diversi alla
> stessa priority è un errore di setup — meglio rinominare uno dei due.

## Override settings senza forking

`<consumer>/ai/overrides.yml`:

```yaml
seo-meta-writer:
  model: claude-haiku-4
  temperature: 0.3
  max_tokens: 256
```

Applicato dopo la risoluzione file-level via
`AgentConfig::mergedWith($overrides)`. Solo i settings di esecuzione
sono modificabili; `name`, `description`, `prompt` restano dall'agent.yml
risolto.

`$sources` aggiunge `'__overrides' => 'consumer:overrides.yml'` per
tracciare che il tuning è stato applicato.

## Custom prompt filename

Un agente può dichiarare un prompt con nome non standard:

```yaml
# agent.yml
prompt: prompt-it.md
```

In quel caso il resolver cerca `<slug>/prompt-it.md` nei layer (stessa
cascade), non `prompt.md`. Tutti gli altri file canonici restano coi
loro nomi standard.

## Validazioni di sicurezza

- I path AI dichiarati in `module.json` (`ai.agents`, `ai.prompts`,
  `ai.tools`) devono essere INTERNI al root del modulo. Path traversal
  (`../../`) viene rifiutato da `ManifestValidator::isPathInsideRoot()`.
- Lo slug agente deve essere kebab-case lowercase
  (`^[a-z0-9]+(?:-[a-z0-9]+)*$`).
- Il campo `prompt:` in agent.yml non può contenere `..` né path
  assoluti (validato in `AgentValidator`).

## Errori comuni

| Errore | Causa | Fix |
|---|---|---|
| `Agente "xxx" non trovato: nessun agent.yml` | Nessun layer ha `agent.yml` per quello slug | Crea `agent.yml` in almeno un layer |
| `Prompt file non trovato` | `prompt:` punta a file inesistente | Verifica il path o crea il file |
| `YAML invalido in /path/agent.yml: ...` | Sintassi YAML errata | Fix sintassi (es. virgolette, indentazione) |
| `Campo obbligatorio mancante: name` | `agent.yml` senza `name` | Aggiungi `name:` |

Per debug: `php forge status:agents` mostra tutti gli agenti con i loro
errori. `php forge validate:agent <slug>` valida uno specifico.
