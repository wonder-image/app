---
icon: user-gear
---

# Gestione utenti (backend / API)

## Cos'è

La gestione utenti è **centralizzata** in una risorsa astratta condivisa,
`UserManagementResource` (`class/App/Resources/Support/UserManagementResource.php`),
specializzata da due risorse concrete:

- `BackendUserResource` — utenti del backend
  (`class/App/Resources/User/BackendUserResource.php`);
- `ApiUserResource` — utenti delle API
  (`class/App/Resources/User/ApiUserResource.php`).

## A cosa serve

Creare/modificare utenti e assegnare loro un'**authority**, senza costruire
pagine custom. Form, tabella, route custom, filtro per authority e flusso
create/edit vivono nella base condivisa.

## Come funziona la specializzazione

Le due risorse concrete implementano solo i due metodi astratti della base:

```php
abstract public static function managedArea(): string;       // 'backend' | 'api'
abstract protected static function permissionsFunction(): string; // funzione che risolve le authority
```

`BackendUserResource::managedArea()` ritorna `'backend'`,
`ApiUserResource::managedArea()` ritorna `'api'`. Da lì la base deriva:

- la **query** filtrata per area (`querySchema()`:
  `` `area` LIKE '%backend%' ``);
- il **form** (in area `api` cambia: vedi il ramo `if (managedArea() === 'api')`);
- la **tabella** con la colonna `authority` resa come badge;
- le **route custom** (`registerBackendRoutes()`), `customBackendPages()`;
- le **authority assegnabili** (`availableAuthorities()`), che dipendono da
  `permissionsFunction()` e dall'authority dell'utente corrente.

## Regole di sicurezza già integrate

- In area `backend`, un utente che **non** è `admin` vede una query ristretta
  (`querySchema()` aggiunge un filtro extra se l'utente corrente non ha `admin`).
- `canCreateFromExisting()` e `existingUserOptions()` gestiscono l'aggancio a
  utenti già esistenti.
- `sendsWelcomeMail()` è `true` solo per l'area `backend`.
- Le authority assegnabili sono limitate da quanto definito con `->creator([...])`
  nel builder dei permessi (vedi [Builder permessi](permessi.md)).

## Creare un utente backend (flusso)

1. Backend → sezione Utenti (voce di menu da `BackendUserResource::navigationSchema()`).
2. "Aggiungi": compili i dati e scegli l'**authority** tra quelle disponibili per
   il tuo ruolo.
3. Salvataggio: l'utente viene creato nell'area `backend`; se previsto, parte la
   welcome mail.

## Creare un utente API

Stesso flusso su `ApiUserResource` (area `api`). Il form è leggermente diverso
(token, domini ammessi). Gli utenti API di sistema (`@system`,
`api_internal_user`) sono creati dal bootstrap (`app/build/row/user.php`): vedi
[Installazione e Deploy](../../piattaforma/installazione-e-deploy.md).

## Collegamenti con il resto

- Le authority assegnabili provengono dal [builder permessi](permessi.md).
- Le route delle risorse utente sono gated come ogni altra Resource: vedi
  [Route e API](../risorse/route-e-api.md).

## Regola operativa

{% hint style="warning" %}
**Non costruire pagine di gestione utenti parallele.** Backend e API user
management sono centralizzati nella risorsa condivisa. Per un nuovo ruolo,
registra prima l'authority nel builder (`app/config/app/permission.php` o
`custom/config/permissions.php`), non con array ad-hoc altrove.
{% endhint %}

## Errori comuni

- **L'authority nuova non compare tra quelle assegnabili** → manca dal builder o
  il suo `->creator([...])` non include il tuo ruolo.
- **Un utente backend vede troppi/pochi utenti** → è il filtro `querySchema()`
  basato su `admin`: comportamento voluto.
- **Pagina utenti duplicata** → si è creata una risorsa parallela invece di
  estendere `UserManagementResource`.

## Checklist

- [ ] authority esistente nel builder, con `creator` corretto
- [ ] uso di `BackendUserResource` / `ApiUserResource` (non risorse parallele)
- [ ] navigazione visibile solo ai ruoli giusti
- [ ] welcome mail / token verificati per l'area corretta
