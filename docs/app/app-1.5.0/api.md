---
icon: circle-nodes
---

# Api

#### Endpoint

```php
<?php

    $FRONTEND = true;

    require_once "config.php";

    use Wonder\Api\{ Endpoint, EndpointException };
    use Wonder\App\Table;
    
    try {

        # Preparo l'endopoint
            $CALL = (new Endpoint("/api/", "POST", "api_internal_user"))
                        ->checkParameters();
        # Risposta
            $RESPONSE = $CALL->response("Endpoint funzionante!");

    } catch (EndpointException $e) {

        http_response_code($e->getCode() ?: 400);

        $RESPONSE = $e->getResponse();
        
    } catch (Exception $e) {

        http_response_code($e->getCode(),);

        $RESPONSE = [
            "success"  => false,
            "status"   => $e->getCode() ?: 500,
            "response" => $e->getMessage()
        ];

    }

    echo json_encode($RESPONSE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
```

#### Chiamata con JS

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

#### Chiamata con PHP
