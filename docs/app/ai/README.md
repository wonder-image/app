# Agenti AI: architettura 3-layer

Il framework `wonder-image/app` espone un sistema di **discovery e
composizione** di agenti AI distribuito su 3 layer, analogo al sistema
moduli per Models / Resources / Routes.

L'obiettivo è: prompt e config sparsi nel codice → struttura predicibile
che permette ai consumer (siti) di overridare framework e moduli senza
forking.

## I 3 layer

```
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 3 — Consumer (sito specifico)        priority 30         │
│  <root>/ai/agents/<slug>/                                       │
│  Override del prompt, fork completo, agenti solo-questo-sito   │
└─────────────────────────────────────────────────────────────────┘
                              ▲
                              │  vince su
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 2 — Moduli composer                  priority 20         │
│  vendor/wonder-image/<modulo>/ai/agents/<slug>/                 │
│  Agenti del dominio: blog, rsvp, ecommerce, …                  │
└─────────────────────────────────────────────────────────────────┘
                              ▲
                              │  vince su
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 1 — Framework wonder-image/app       priority 10         │
│  ai/agents/<slug>/                                              │
│  Agenti universali utili a qualsiasi sito                       │
└─────────────────────────────────────────────────────────────────┘
```

## Struttura di un agente

Ogni agente vive in una sotto-cartella `<slug>/` (slug = kebab-case,
nome cartella). I file canonici sono:

| File | Obbligatorio? | Cosa contiene |
|---|---|---|
| `agent.yml` | ✅ | config: name, description, model, temperature, tools, ... |
| `prompt.md` | ✅ | system prompt dell'agente |
| `tools.yml` | – | (placeholder per definizione tool — non usato in questa versione) |
| `schema.yml` | – | (placeholder per input/output schema — non usato in questa versione) |

Ogni file viene risolto **indipendentemente** dagli altri (cascade
file-level): se il consumer mette solo `prompt.md`, il sito userà
l'`agent.yml` ereditato dal framework e il `prompt.md` del consumer.

Vedi [cascade.md](cascade.md) per i dettagli.

## Esempio `agent.yml`

```yaml
name: SEO Meta Writer
description: Genera title + meta description da contenuto pagina + keyword.
model: claude-sonnet-4
temperature: 0
max_tokens: 512
prompt: prompt.md
tools: []
provider: anthropic
timeout_seconds: 30
max_retries: 2
```

Campi:
- `name`, `description` — **obbligatori**, identificano l'agente
- `model`, `provider` — quale LLM. Fallback su `defaults.model` di `config.yml`
- `temperature`, `max_tokens`, `timeout_seconds`, `max_retries` — settings di esecuzione
- `prompt` — path al file del prompt, relativo alla cartella agente. Default `prompt.md`
- `tools` — lista di slug di tool (placeholder, non eseguiti in questa versione)

## Override dal consumer

Il consumer ha **3 modi** di customizzare un agente shippato altrove:

### A) Solo prompt
```
new-site/ai/agents/seo-meta-writer/
└── prompt.md      ← solo questo file → vince. agent.yml resta ereditato.
```

### B) Tweak settings (senza forking)
```yaml
# new-site/ai/overrides.yml
seo-meta-writer:
  model: claude-haiku-4
  temperature: 0.3
```

### C) Fork completo
```
new-site/ai/agents/seo-meta-writer/
├── agent.yml      ← override completo
└── prompt.md
```

**Limite di B**: `overrides.yml` può cambiare solo i settings di esecuzione.
Per cambiare il prompt usa A. Per cambiare nome/description usa C.

## Configurazione globale (`config.yml`)

`<consumer>/ai/config.yml` definisce i default per tutti gli agenti:

```yaml
defaults:
  model: claude-sonnet-4
  temperature: 0
  timeout_seconds: 60
providers:
  anthropic:
    api_key_env: ANTHROPIC_API_KEY
```

Il framework può shippare un `ai/config.yml` proprio (con defaults
"di partenza"); il consumer fa merge profondo e vince per ogni chiave
dichiarata.

## Manifest dei moduli

I moduli composer attivano la pubblicazione di agenti aggiungendo una
sezione `ai` al loro `module.json`:

```json
{
  "slug": "rsvp",
  "ai": {
    "agents": "ai/agents",
    "prompts": "ai/prompts",
    "tools": "ai/tools"
  }
}
```

Tutti i path sono **opzionali** (default = `ai/agents`, `ai/prompts`,
`ai/tools`). Vedi [manifest.md](manifest.md).

## API PHP

```php
use Wonder\AI\AgentRegistry;

// Discovery + lista
foreach (AgentRegistry::all() as $slug => $agent) {
    echo $slug.' → '.$agent->config->model."\n";
}

// Recupera + usa
$agent = AgentRegistry::get('seo-meta-writer');
$agent->config->model;        // 'claude-sonnet-4' (o override)
$agent->prompt();             // contenuto risolto
$agent->sources;              // {agent.yml: 'app', prompt.md: 'consumer'}
$agent->run(['url' => ...]);  // STUB: throwa in questa versione
```

## Console commands

```bash
php forge status:agents              # lista tutti gli agenti, source, validità
php forge validate:agent <slug>      # valida shape di un singolo agente
```

## Stato di questa versione

✅ Discovery 3-layer + cascade file-level + overrides
✅ Console commands `status:agents` / `validate:agent`
✅ Validazione `agent.yml`
✅ API stabile di `Agent::run()` (firma definitiva)

🚧 `Agent::run()` è uno **STUB**: throwa `RuntimeException`. L'integrazione
con il provider LLM (chiamate Anthropic / OpenAI) sarà aggiunta in un
PR successivo. La discovery + override sono però completi: si può già
iniziare a definire agenti e organizzare prompt.

## Cosa NON fare

- Non organizzare per provider (`agents/anthropic/`, `agents/openai/`)
  → si organizza per **funzione**.
- Non hardcodare prompt nel codice PHP. Vivono in `prompt.md`.
- Non duplicare agenti tra modulo e framework: se serve a più di un
  sito, alza al framework.
- Non committare API key. Vivono in `.env`, referenziate via
  `providers.<name>.api_key_env`.
