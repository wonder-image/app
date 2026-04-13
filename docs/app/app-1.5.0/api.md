---
icon: circle-nodes
---

# Api

Questa è la struttura reale delle API oggi.

Le API route stanno in:

- `1.5.0/config/routes/route.api.php`
- `custom/config/routes/route.api.php`

Gli handler API stanno in:

- `1.5.0/http/api/...`
- `custom/http/api/...`

Il boilerplate corretto non è più quello vecchio con `try/catch` scritto a mano in ogni file.
Adesso il pattern da usare è questo.

## Endpoint base

```php
<?php

use Wonder\Api\{ Endpoint, Handler, Response };

Handler::run('/api/app/ping/', 'POST', 'api_internal_user', function (Endpoint $CALL) {
    return Response::json([
        'success' => true,
        'status' => 200,
        'response' => 'pong',
    ]);
});
```

## Esempio reale update

File:

- `1.5.0/http/api/app/update.php`

Schema reale:

```php
<?php

use Wonder\Api\{ Endpoint, Handler, Response };

Handler::run('/api/app/update/', 'POST', [ 'api_internal_user', 'api_public_access' ], function (Endpoint $CALL) {

    $PARAMETERS = is_array($CALL->parameters) ? $CALL->parameters : [];
    $source = trim((string) ($PARAMETERS['source'] ?? 'github'));

    if (!in_array($source, [ 'github', 'backend' ], true)) {
        $source = 'github';
    }

    $RUNNER = new Wonder\App\UpdateRunner();
    $RESULT = $RUNNER->execute([
        'release_id' => trim((string) ($PARAMETERS['release_id'] ?? '')),
        'trigger_type' => 'api',
        'source' => $source,
    ]);

    return Response::raw(
        $RUNNER->jsonPayload($RESULT),
        $RESULT->success ? 200 : 500
    );
});
```

## Classi da conoscere

- `class/Api/Endpoint.php`
- `class/Api/EndpointException.php`
- `class/Api/Handler.php`
- `class/Api/Response.php`

## Risposte

### JSON standard

```php
return Response::json([
    'success' => true,
    'status' => 200,
    'response' => 'ok',
]);
```

### Raw

Serve quando hai già il payload pronto.

```php
return Response::raw($jsonString, 200);
```

## Errori

`Handler::run()` uniforma automaticamente gli errori in questo formato:

```json
{
  "success": false,
  "status": 400,
  "response": "Messaggio errore"
}
```

Quindi nel file endpoint devi scrivere solo la logica utile.

## Chiamata con JS

Chiamate classiche

```javascript
API_CLIENT.post("/")
.then(data => console.log(data))
.catch(error => console.error(error));
```

Chiamata alle api di wonder-image/app:

```javascript
API_CLIENT_APP.post("/")
.then(data => console.log(data))
.catch(error => console.error(error));
```

## Errori comuni

### 1. Endpoint non trovato

Controlla:

- route in `route.api.php`
- path dichiarato in `Handler::run(...)`
- handler file esistente

### 2. `Bearer token mancante`

Stai chiamando un endpoint protetto senza header `Authorization`.

### 3. `404` HTML invece di JSON

Succede quando il path non matcha nessuna route API.

Prima registra la route, poi testa l'endpoint.

### 4. Usi ancora il vecchio `try/catch` in ogni file

Evitalo.

Se l'endpoint è nel modello nuovo, usa:

- `Endpoint`
- `Handler`
- `Response`

## Best practice

- Una route API deve puntare a un handler in `http/api/...`
- Il file handler deve contenere solo autenticazione e logica
- Usa `Handler::run()` per evitare boilerplate duplicato
- Usa `Response::json()` come default
- Usa `Response::raw()` solo quando hai davvero già il payload serializzato
- Tieni i path coerenti con il gruppo route
