---
icon: masks-theater
---

# Token monouso e impersonificazione

## Token monouso

`Wonder\Auth\OneTimeToken` gestisce token bearer nel formato
`selector.validator`. In `auth_one_time_tokens` viene salvato soltanto l'hash
del validator. Ogni token ha purpose, soggetto, scadenza e stato consumato o
revocato.

- `issue()` crea il token e, per default, revoca gli altri token aperti dello
  stesso purpose e utente.
- `inspect()` serve soltanto a mostrare una pagina GET senza consumare il token.
- `consume()` esegue il consumo atomico; l'azione protetta deve usare questo
  metodo.
- `Wonder\Auth\PasswordReset` applica lo stesso contratto al cambio password.

Gli URL contenuti nei token devono essere normalizzati dal consumer e limitati
allo stesso host o a percorsi relativi.

## Impersonificazione

`Wonder\Auth\Impersonation` è un servizio opt-in. Il consumer passa sempre la
lista esplicita delle authority backend autorizzate. Il servizio:

- accetta soltanto attori backend attivi e soggetti frontend attivi;
- vieta auto-impersonificazione e soggetti che possiedono anche accesso backend;
- trasferisce l'identità con token monouso di breve durata;
- rigenera l'ID sessione all'ingresso e all'uscita;
- conserva attore, soggetto, inizio e URL di ritorno nella sessione;
- registra emissione, avvio, rifiuto e arresto in
  `auth_impersonation_audits`;
- richiede CSRF sia per l'emissione sia per l'arresto.

Il componente frontend `frontend.overlay.impersonation` rende una barra sempre
visibile quando la modalità è attiva. Il modulo che espone le route deve fornire
URL di stop e testi di interfaccia.
