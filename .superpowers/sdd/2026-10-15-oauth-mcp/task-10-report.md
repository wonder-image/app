# Task 10 report: pagina backend "Connessioni MCP"

## Implementato
- `class/App/Resources/OAuth/ConnectionsResource.php`: rows/seeAll/canRevoke/handleSubmit come da brief, piu' `servers(): ?array` (null se `Config::issuer()` lancia OAuthException).
- `app/view/pages/backend/oauth/connections.php`: URL MCP per server (da ResourceRegistry + issuer), tabella grant, form di revoca con `Csrf::field()->render()`; avviso (`oauth.connections.issuer_error`) se l'issuer non e' valido, niente 500. Tutto l'output con htmlspecialchars; `json_encode` con JSON_HEX_TAG|JSON_HEX_AMP nello script.
- Rotte GET/POST `backend.oauth.connections` in route.backend.php, solo se `OAuth::enabled()`.
- Lang: aggiunta `oauth.connections.issuer_error` in it ed en (le altre chiavi esistevano gia').
- Test `tests/OAuth/ConnectionsTest.php` (8 check, inclusi issuer non valido e risoluzione nome risorsa).

## Adattamenti al codice reale
- `NavigationSchema` e' in `Wonder\App\ResourceSchema`, non `Resources\Support`; `FlashAlert` in `Wonder\Backend\Support`; utente via `LegacyGlobals::get('USER')`.
- Classe non `final` (come DashboardResource); `path()` = `app/oauth/connections` (come `app/scheduler`), slug `app-oauth-connections`; icona `bi-plug` (stile `bi-clock`).
- Vista con `View::layout('backend.show', ...)` come la dashboard scheduler; `rows()` usa `servers() ?? []` per non esplodere con issuer non valido.
- `handleSubmit` termina con `exit()` (come Dashboard).

## TDD
RED (classe rimossa temporaneamente): `8 test, 8 falliti`. Nota: l'implementazione era stata scritta prima del test, il RED e' stato verificato a posteriori. GREEN: `8 test, 0 falliti`.
Suite: `for f in tests/OAuth/*Test.php; do php $f | tail -1; done` -> tutti 0 falliti. `php -l` OK su tutti i file toccati.

## Concern
- La voce di menu sta nella sezione `dev`, la cui visibilita' e' limitata ad admin/administrator: gli utenti non admin hanno la rotta ma non la voce di menu. Per renderla visibile a tutti serve una sezione propria.
- La rotta/handleSubmit non e' stata provata in un browser reale (nessun sito con l'app puntata a questo branch); coperti solo logica e lint.
- `__r('backend.oauth.connections')` e il rendering reale della vista non sono testati.
