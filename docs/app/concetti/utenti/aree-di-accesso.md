---
icon: lock
---

# Aree di accesso (`Access`)

## Cos'è

Una mappa **area del backend → ruoli**. Un'area è un gruppo di pagine con gli
stessi permessi: `log`, `avvisi`, `gestionale:catalogo`, `gestionale:impostazioni_spedizioni`.

Non va confusa con `Area` del [builder permessi](permessi.md)
(`backend` / `frontend` / `api`), che è l'ambito di login.

## A cosa serve

- Cambiare chi vede o modifica una parte del backend **senza toccare le Resource**:
  si cambia una riga di config.
- Tenere `administrator` (il commerciante) fuori dalle parti tecniche finché
  Wonder Image non lo autorizza.
- Dare ai ruoli operativi dei moduli (es. `sales`, `warehouse`) solo le aree che
  servono.

## Dove si trova nel codice

| Elemento | File |
|---|---|
| Registro | `class/App/Permission/Access.php` |
| Aree del core | `Access::CORE` |
| Aree di un modulo | chiave `permissions` di `config/module.php` del modulo |
| Aree e override del sito | `custom/config/access.php` (restituisce `area => regola`) |
| Override del sito (modulo) | chiave `permissions` di `custom/config/modules/<slug>.php` |
| Uso nelle Resource | `PermissionSchema::access()`, `NavigationSchema::access()` |

## Regole

Una regola è una lista di ruoli, valida per tutte le azioni, oppure una mappa
per azione. Le azioni sono `view`, `create`, `edit`, `delete`; `*` vale per
quelle non indicate.

```php
[]                                                // solo admin
['administrator']                                 // admin e administrator, tutto
['view' => ['administrator']]                     // administrator vede, non modifica
['*' => ['administrator', 'sales'], 'delete' => ['administrator']]
```

- `admin` (Wonder Image) è **sempre** incluso: un override non può toglierlo.
- Un override **sostituisce la regola intera** dell'area, non si unisce alla
  lista di default. `['view' => ['administrator']]` toglie a `administrator`
  le azioni di modifica anche se il default le dava.
- Le aree dei moduli si leggono con il prefisso dello slug:
  `gestionale:catalogo`. Nel config del modulo la chiave è senza prefisso e
  senza punti (`impostazioni_spedizioni`).
- I permessi si decidono solo nei file: lo stato del modulo salvato in DB non li
  cambia.

## Aree del core

| Area | Pagine | Default |
|---|---|---|
| `log` | email inviate, accessi, consensi | solo admin |
| `diagnostica` | scheduler (Dev), errori, errori SQL | solo admin |
| `avvisi` | annunci e popup (anche le API) | solo admin |

Le altre pagine del core (Media, Set Up, utenti) restano dichiarate nella
Resource, solo admin.

## Come si usa in una Resource

```php
public static function permissionSchema(): PermissionSchema
{
    // Solo le azioni che la pagina ha: un registro si legge e basta.
    return PermissionSchema::for(static::class)->access('log', ['list', 'view']);
}

public static function navigationSchema(): NavigationSchema
{
    return NavigationSchema::for(static::class)->title('Email')->access('log');
}
```

`access()` sul permesso espande `view` → `list`/`view`, `create` →
`create`/`store`, `edit` → `edit`/`update`, `delete` → `delete`. Il secondo
argomento limita le azioni backend abilitate: senza, la Resource riceve tutte
quelle dell'area (anche create e delete). Le azioni API
restano esplicite (`->api(...)`, `->apiCrud(Access::roles('avvisi'))`).
`access()` sulla navigazione mostra la voce ai ruoli che hanno `view`; una
sezione del menu senza voci visibili sparisce da sola.

Per una route scritta a mano: `->permit(Access::roles('diagnostica'))`.

## Come autorizzare il commerciante

Aree del core, in `custom/config/access.php`:

```php
return [
    'log' => ['view' => ['administrator']],
    'avvisi' => ['administrator'],
];
```

Aree di un modulo, in `custom/config/modules/gestionale.php`:

```php
return [
    'permissions' => [
        'impostazioni_spedizioni' => ['administrator'],
    ],
];
```

Nello stesso file un sito definisce le sue aree (`'richieste' => []`) e può
cambiare quelle dei moduli con il nome completo (`'gestionale:magazzino' => ...`).

Le regole si leggono alla prima richiesta di un'area, non al bootstrap: le
route delle Resource vengono registrate prima di `custom/config/permissions.php`,
quindi un'area definita lì con `Access::define()` arriva troppo tardi. Usare
`custom/config/access.php`.

## Errori comuni

- **`Area permessi 'x' non definita`** — la Resource usa un'area che nessuno
  definisce, o il modulo che la definisce è disattivato.
- **`Azione 'x' non valida`** — nella mappa si usano solo `view`, `create`,
  `edit`, `delete`, `*`.
- **Il ruolo tolto resta** — l'override va nella chiave `permissions`; un
  override altrove (es. `impersonation.actor_authorities` di ecommerce) si
  unisce ancora per indice.
- **Pulsanti visibili ma 403** — i pulsanti di lista non leggono ancora i
  permessi per azione: con `['view' => [...]]` il ruolo vede "Modifica" ma la
  route risponde 403.
