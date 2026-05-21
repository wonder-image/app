# AI — agenti shippati dal framework

Questa cartella contiene gli **agenti AI universali** che il framework
`wonder-image/app` mette a disposizione di tutti i consumer.

## Cosa c'è qui

- `agents/` — un agente per cartella, con `agent.yml` + `prompt.md`.
- `prompts/` — frammenti di prompt riusabili (es. tono, formato output).
- `tools/` — tool generici che gli agenti possono invocare (placeholder
  per ora — l'esecuzione `Agent::run()` è stub in questo PR).

## Agenti pubblicati

| Slug | Descrizione |
|---|---|
| `seo-meta-writer` | Genera title + meta description SEO da contenuto + keyword. |

## Come usarli dal consumer

Niente da fare: gli agenti del framework sono scoperti automaticamente da
`Wonder\AI\AgentRegistry` con priority 10. Da un consumer (es. new-site):

```php
use Wonder\AI\AgentRegistry;

$agent = AgentRegistry::get('seo-meta-writer');
echo $agent->prompt();                      // contenuto del prompt
$agent->config->model;                      // 'claude-sonnet-4'
$agent->run(['url' => '/contatti', ...]);   // STUB in questo PR
```

## Come overridarli dal consumer

Vedi la doc completa in `docs/app/ai/`.

In sintesi: la **cascade file-level** ti permette di overridare solo
quello che serve (priority 30 nel consumer, vince sul framework):

```
<consumer>/ai/agents/seo-meta-writer/
├── prompt.md       # solo questo → vince sul prompt.md del framework
                    # agent.yml resta ereditato
```

Oppure usa `<consumer>/ai/overrides.yml` per tweak run-time di
model/temperature/max_tokens senza forking di `agent.yml`.

## Aggiungere un agente shippato dal framework

1. `mkdir wonder-image/app/ai/agents/<nome-agente>`
2. Crea `agent.yml` (vedi `seo-meta-writer` come template).
3. Crea `prompt.md`.
4. `php forge validate:agent <nome-agente>` per verificare.
5. Aggiorna questa README + la GitBook `docs/app/ai/`.

Criterio di inclusione: un agente sta nel framework SOLO se è utile a
qualsiasi sito Wonder Image. Altrimenti va in un modulo (`wonder-image/<modulo>/ai/agents/`)
o nel consumer (`<root>/ai/agents/`).
