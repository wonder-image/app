---
icon: users
---

# Utenti e Permessi

## Cos'è

Il sistema di accesso del framework ha due lati:

- **Permessi** — definizione di **aree** (`frontend`, `backend`, `api`) e di
  **authority** (ruoli/chiavi come `admin`, `administrator`, `client`,
  `api_internal_user`) tramite un builder.
- **Gestione utenti** — risorse condivise che creano/modificano gli utenti
  backend e API e assegnano loro le authority.

## A cosa serve

- Decidere **chi** può fare **cosa** su ogni route (CRUD backend e API).
- Creare utenti e assegnare ruoli senza scrivere pagine custom.

## Dove si trova nel codice

| Elemento | File |
|---|---|
| Builder permessi | `class/App/Permission/{Permissions,Area,Permission}.php` |
| Registro base | `app/config/app/permission.php` |
| Estensione del sito | `custom/config/permissions.php` |
| Gestione utenti (base) | `class/App/Resources/Support/UserManagementResource.php` |
| Utenti backend | `class/App/Resources/User/BackendUserResource.php` |
| Utenti API | `class/App/Resources/User/ApiUserResource.php` |

## Come si collega al resto

- Ogni `Resource::permissionSchema()` dichiara quali authority possono fare
  quali azioni; `ResourceRouteRegistrar` applica il gate (`Route::permit(...)`).
  Vedi [Route e API](../risorse/route-e-api.md).
- Le authority disponibili devono **esistere** nel builder dei permessi prima di
  poter essere referenziate.

## Le pagine di questa sezione

- [Builder permessi (Area / Permission)](permessi.md) — definire aree e
  authority.
- [Gestione utenti (backend / API)](gestione-utenti.md) — creare utenti e
  assegnare ruoli.
- [Permessi client e verifica email](permessi-client.md) — permessi lato client
  e hook di verifica.
- [Verifica email](verifica-email.md)
- [Registrazione consensi](consensi.md)
- [Auth federata Google / Apple](auth-federata.md)

## Flusso "accesso negato" in breve

```
Route generata con ->permit(['admin','administrator'])
  → utente con authority 'client' chiama la route
  → gate fallisce → 403
```

Per concedere l'accesso: aggiungi l'authority dell'utente all'elenco in
`permissionSchema()`, oppure assegna all'utente un'authority già ammessa.
