# Moduli

Questa sezione documenta il sistema moduli del framework Wonder.

La linea corrente e' questa:

- il modulo canonico e' un package Composer autonomo;
- il package consigliato e' `wonder-image/<slug>`;
- il namespace base standard e' `Wonder\Plugin\<StudlySlug>\`;
- il modulo espone un `module.json` e un entrypoint PHP che implementa `Wonder\App\Module\Contracts\ModuleInterface`;
- in compatibilita' temporanea, un package Composer `wonder-image/*` con `extra.wonder.module=true` puo' essere caricato anche senza `module.json`, ma questo fallback legacy non e' il formato target;
- il consumer abilita i moduli da `custom/config/modules.php`.

Stato attuale del core:

- discovery moduli Composer e locali
- validazione manifest
- registry moduli abilitati
- merge permissions modulo
- registrazione route frontend/backend/api
- discovery `Model` e `Resource` dai moduli
- registrazione traduzioni modulo
- repository config modulo runtime
- comandi `php forge validate:module <slug>` e `php forge status:modules`

Continua con:

- [Sistema Moduli](module-system.md)
- [Contratto Modulo](module-contract.md)
- [Manifest Modulo](module-manifest.md)
