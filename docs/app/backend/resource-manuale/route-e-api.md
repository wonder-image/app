# Route e API

Questa pagina raccoglie i path backend e gli endpoint API generati automaticamente.

## Path backend

Il path backend viene dal model:

```php
public static string $folder = 'app/contact';
```

Quindi la risorsa backend sara':

```text
/backend/app/contact/
```

Pagine standard:

- `GET /backend/app/contact/`
- `GET /backend/app/contact/create/`
- `POST /backend/app/contact/create/`
- `GET /backend/app/contact/{id}/`
- `GET /backend/app/contact/{id}/edit/`
- `POST /backend/app/contact/{id}/edit/`
- `POST /backend/app/contact/{id}/delete/`

## Route names backend

Lo slug interno di `app/contact` diventa `app-contact`.

Route names:

- `backend.resource.app-contact.list`
- `backend.resource.app-contact.create`
- `backend.resource.app-contact.store`
- `backend.resource.app-contact.view`
- `backend.resource.app-contact.edit`
- `backend.resource.app-contact.update`
- `backend.resource.app-contact.delete`

## Path API

Le API usano lo slug interno:

```text
/api/resource/app-contact/
```

Endpoint standard:

- `GET /api/resource/app-contact/`
- `POST /api/resource/app-contact/`
- `GET /api/resource/app-contact/{id}/`
- `PUT /api/resource/app-contact/{id}/`
- `PATCH /api/resource/app-contact/{id}/`
- `DELETE /api/resource/app-contact/{id}/`

## Route names API

- `api.resource.app-contact.index`
- `api.resource.app-contact.store`
- `api.resource.app-contact.show`
- `api.resource.app-contact.update`
- `api.resource.app-contact.update.patch`
- `api.resource.app-contact.destroy`

## Esempi `curl`

### Lista

```bash
curl -X GET http://127.0.0.1:8088/api/resource/app-contact/
```

### Crea

```bash
curl -X POST http://127.0.0.1:8088/api/resource/app-contact/ \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Mario",
    "surname": "Rossi",
    "email": "mario@example.com"
  }'
```

### Dettaglio

```bash
curl -X GET http://127.0.0.1:8088/api/resource/app-contact/1/
```

### Update completo

```bash
curl -X PUT http://127.0.0.1:8088/api/resource/app-contact/1/ \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Mario",
    "surname": "Bianchi",
    "email": "mario@example.com"
  }'
```

### Update parziale

```bash
curl -X PATCH http://127.0.0.1:8088/api/resource/app-contact/1/ \
  -H "Content-Type: application/json" \
  -d '{
    "surname": "Bianchi"
  }'
```

### Delete

```bash
curl -X DELETE http://127.0.0.1:8088/api/resource/app-contact/1/
```

## Cosa decide se una route esiste

### Backend

`pageSchema()`

Esempio:

```php
public static function pageSchema(): PageSchema
{
    return PageSchema::for(static::class)
        ->enable(['list', 'create', 'store', 'view', 'edit', 'update', 'delete']);
}
```

### API

`apiSchema()`

Esempio:

```php
public static function apiSchema(): ApiSchema
{
    return ApiSchema::for(static::class)
        ->route('index')
        ->route('store')
        ->route('show')
        ->route('update')
        ->route('destroy');
}
```

## Permessi

I permessi si configurano in `permissionSchema()`.

Esempio:

```php
public static function permissionSchema(): PermissionSchema
{
    return PermissionSchema::for(static::class)
        ->backendCrud(['admin'])
        ->apiCrud(['admin']);
}
```

## Menu backend

La voce menu viene costruita automaticamente dal `ResourceRegistry`.

Di solito basta:

```php
public static function navigationSchema(): NavigationSchema
{
    return NavigationSchema::for(static::class);
}
```

Continua con:

- [Quick Start](quick-start.md)
- [Componenti](componenti.md)
- [Specifica tecnica CRUD dinamico](../resource-crud-dinamico.md)
