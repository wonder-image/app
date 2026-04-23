# Manuale Model e Resource

Questo e' il manuale d'uso semplice del sistema `Model + Resource`.

Se vuoi partire subito:

1. leggi [Quick Start](quick-start.md)
2. poi guarda [Componenti](componenti.md)
3. se hai una pagina speciale usa [Resource e CustomPageSchema](custom-page-schema.md)
4. se hai un modulo a record singolo usa [Resource Singleton](singleton.md)
5. infine usa [Route e API](route-e-api.md)

Per i dettagli architetturali completi:

- [Specifica tecnica CRUD dinamico](../resource-crud-dinamico.md)

## Cosa ottieni

Con un `Model` e una `Resource` ottieni automaticamente:

- pagina lista backend
- pagina create backend
- pagina edit backend
- pagina dettaglio backend
- endpoint API CRUD
- voce menu backend

## File principali

- `class/App/Models/*`
- `class/App/Resources/*`
- `class/App/PageSchema/*`
- `class/App/Resource.php`
- `class/App/Model.php`

## Flusso semplice

1. crei il `Model`
2. crei la `Resource`
3. compili `tableSchema()` e `dataSchema()` nel model
4. compili `formSchema()` e `tableSchema()` nella resource
5. se vuoi, impagini il backend con `formLayoutSchema()` e `tableLayoutSchema()`
6. apri il backend con il path definito in `Model::$folder`

Se invece la pagina non e' un modulo CRUD vero, usa una `CustomPageSchema`.

## Distinzione corretta

Questa e' la distinzione da ricordare:

- `Model::tableSchema()` = definisce la struttura SQL della tabella
- `Model::dataSchema()` = definisce come trattare i dati prima del salvataggio
- `Resource::formSchema()` = definisce gli input del backend

Quindi:

- `tableSchema()` = come e' fatta la tabella
- `dataSchema()` = come si preparano i dati
- `formSchema()` = come si inseriscono i dati nel backend
