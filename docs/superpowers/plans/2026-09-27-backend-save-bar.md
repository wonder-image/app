# Barra di salvataggio del backend — piano di implementazione

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Obiettivo:** quando i bottoni di salvataggio di un form del backend escono dallo schermo, compaiono in un'isola fissa in basso; se il form ha modifiche non salvate, l'isola mostra "Operazione non salvata" e l'uscita dalla pagina chiede conferma.

**Architettura:**
- La lib (`wonder-image`) contiene tutto il comportamento: `saveBar.js` e `save-bar.css`, attivati da `setUpPage()` su ogni `form[data-wi-save-bar]`.
- L'isola copia i bottoni esistenti del form, senza crearne di nuovi.
- Lo stato "non salvato" nasce dal confronto tra lo snapshot attuale del form e una baseline presa lato client.
- L'app (`wonder-image/app`) si limita a dichiarare gli attributi sul `<form>`: di default su tutte le Resource, in modo esplicito sulle altre pagine.
- Le due parti si rilasciano indipendentemente e degradano in modo sicuro in entrambe le direzioni.

**Tech stack:**
- JavaScript globale caricato con `script-loader` (webpack), CSS con token Bootstrap 5;
- test unitari in `node:vm` e test browser Playwright su Chrome;
- PHP 8.2+ con test in `tests/` eseguiti con `php`.

**Spec:** `docs/superpowers/specs/2026-09-27-backend-save-bar-design.md` (branch `backend-save-bar` dell'app). Chi esegue il piano legge sia la spec sia il piano.

## Global Constraints

Valgono per ogni task, anche quando il task non li ripete.

- Testi predefiniti di `window.wiSaveBar.labels`: `group` "Barra di salvataggio", `unsaved` "Operazione non salvata", `confirmOther` "Ci sono modifiche non salvate in un altro modulo della pagina. Continuare e perderle?".
- API pubblica: `window.wiSaveBar = { changed(elOrForm?), reset(form), isDirty(form) → boolean, absorb(el), labels }`, più `setUpSaveBar()` chiamato da `setUpPage()` e l'evento `wi:save-bar:change` sul form con `detail { dirty }`, emesso solo ai passaggi pulito↔sporco. Su un form non tracciato o su `null` le quattro funzioni non danno errori, restituiscono `false` e non emettono eventi.
- Attributi: `data-wi-save-bar` (sul `<form>`), `data-wi-save-bar-dirty` (sul `<form>`, conta la presenza), `data-wi-save-bar-ignore` (bottone, campo o contenitore), `data-wi-save-bar-hide-when-open` (qualsiasi elemento nel `body`).
- Livelli: isola a z-index 7 dal token `--wi-save-bar-zindex`; sopra il contenuto (massimo 5), sotto `#sidebar` (8), `#topbar` (9), datepicker (10), dropdown (1000), Select2 (1051), icon picker (1090), `#loading-spinner` (1100) e i modal.
- Fascia: `--wi-save-bar-band` 96px, registrato con `@property` (un valore non valido torna a 96px). Distanza dal fondo `--wi-save-bar-offset`: 16px, 12px a ≤768px, con `env()` sempre nel calcolo.
- Nell'app non c'è JS né CSS della barra: solo attributi sui form, `AttributeString::render()` e le condizioni "nasce sporco". PHP `^8.2` (piattaforma Composer 8.2.30).
- Commenti brevi e in italiano; nei file nuovi (`saveBar.js`, `save-bar.css`, test nuovi) solo ASCII. Funzioni solo per operazioni ripetute; componenti e helper riutilizzabili.
- Push, PR, merge, tag, `npm run release` e `composer release` si fanno solo su richiesta esplicita dell'utente, come indicato nei task con "(solo su richiesta esplicita dell'utente)".
- Nei siti di prova nessun commit: ogni modifica è reversibile e si rimette com'era nello stesso task.
- Nessuna mail vera: indirizzi `@example.com` e `_skip_api_token_mail=1`; se un caso sta per inviare una mail, il caso si ferma.
- Nessuna password vera scritta dall'agente: i casi di password sbagliata usano `sbagliata-save-bar`, quella giusta la scrive l'utente.
- Nessun record cancellato dall'agente: ogni record creato va in `record-di-prova.txt` e l'elenco passa all'utente nel Task R8.
- Mai stampare il contenuto di `.env`: si leggono solo `APP_URL`, `APP_ENV` e `APP_DOMAIN` con `grep`, mai `GOOGLE_API_KEY`.
- I test nuovi dell'app vanno aggiunti con `git add -f`: `tests/` è in `.gitignore` ma i test sono tracciati.

## Review Focus

- L'utente cambia un campo e poi lo riporta a mano al valore iniziale → isola pulita e nessun avviso di uscita -> Task C2, `U20 un valore riportato a mano a quello iniziale torna pulito`; Task B2, `B8`.
- Una pagina si apre con widget che si riempiono dopo l'avvio (caselle della DynamicCheck via AJAX, jstree, Places) e l'utente non tocca nulla → form pulito, nessun avviso -> Task I3, blocchi `U36` e `U37`; Task R5, caso I12.
- Doppio click su Salva o sulla sua copia, anche con la pagina lenta → un solo POST -> Task B3, `B27`.
- Un POST fallito ridisegna il form con gli errori → il form nasce sporco e resta tale fino a un invio o a `reset(form)` -> Task C2, `U21 data-wi-save-bar-dirty: sporco fino a reset(), che prende il nuovo punto di partenza`; Task R6, caso I20 Step 4.
- Un secondo form tracciato nella stessa pagina ha modifiche non salvate quando si salva il primo → `confirm()` con `labels.confirmOther` prima di perderle -> Task B3, `B32`.

---

## Parte S — Preparazione

Il Task S1 prepara worktree, dipendenze e risultati di base per tutte le parti successive.

Regole valide per tutta la parte:
- lo stato della shell non persiste tra un comando e l'altro: ogni blocco ridefinisce le variabili che usa e lavora con percorsi assoluti;
- `/Users/andreamarinoni/Developer/packages` e `/Users/andreamarinoni/Desktop/PROGETTI/template` sono la stessa cartella: se l'hook rifiuta Edit/Write su un worktree col percorso `Developer/packages`, si usa lo stesso percorso sotto `Desktop/PROGETTI/template`;
- push, PR, merge, release npm, tag e pubblicazioni si fanno **solo su richiesta esplicita dell'utente**; ogni comando di questo tipo è marcato così;
- non si stampa mai il contenuto di un `.env`: si leggono solo le chiavi non segrete con `grep -n '^APP_URL=\|^APP_ENV=\|^APP_DOMAIN='`;
- la cartella di lavoro fuori dai repo è `$HOME/.cache/wonder-tooling/save-bar` (accanto alla cache di Playwright); i backup dei siti stanno in `/Users/andreamarinoni/Developer/boilerplates/.save-bar-backup/<sito>/` (`boilerplates/` non è un repo git).

### Task S1: worktree, dipendenze e risultati di base

Repo: **lib**, **app**, **immobili**, **skills** (nessun commit).

Skill: `superpowers:using-git-worktrees`. Si usa il fallback git (Step 1b) e non uno strumento nativo, perché servono worktree su quattro repo e il branch dell'app esiste già. La cartella è `.claude/worktrees/`, già usata dall'app (`.claude/worktrees/safehtml-parse-recover`) e già ignorata in tutti e quattro i repo:
- lib `.gitignore:2 .claude/`;
- app `.gitignore:3 .claude/`;
- immobili `.gitignore:5 *.claude`;
- skills `.gitignore:2 .claude`.

**Files:**
- Create: `/Users/andreamarinoni/Developer/packages/{lib,app,immobili,skills}/.claude/worktrees/backend-save-bar/` (worktree, ignorati da git)
- Create: `$HOME/.cache/wonder-tooling/save-bar/app-baseline.txt`, `$HOME/.cache/wonder-tooling/save-bar/gestionale-baseline.txt`
- Test: nessun file nuovo; si registrano i risultati di base

**Interfaces:**
- Consumes: branch `backend-save-bar` dell'app (commit della spec e del piano), `main` di lib, immobili e skills
- Produces:
  - `LIB_WT=/Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar`, sul branch `backend-save-bar` nuovo, con `node_modules/`;
  - `APP_WT=/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar`, sul branch `backend-save-bar` esistente, con `vendor/` installato da `composer install`;
  - `IMM_WT=/Users/andreamarinoni/Developer/packages/immobili/.claude/worktrees/backend-save-bar` e `SKL_WT=/Users/andreamarinoni/Developer/packages/skills/.claude/worktrees/backend-save-bar`, sui branch `backend-save-bar` nuovi;
  - `PLAYWRIGHT_MODULE=$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core` e `CHROME_CHANNEL=chrome` per i test browser (Parte B);
  - i risultati di base: lib `npm test` verde, test browser verde, 124 test dell'app, 71 test unitari del gestionale e 2 test di immobili, tutti verdi.

- [ ] **Step 1: Controlla da dove parti (Step 0 della skill)**

```bash
cd /Users/andreamarinoni/Developer/packages/app
git rev-parse --git-dir --git-common-dir
git rev-parse --show-superproject-working-tree
mkdir -p "$HOME/.cache/wonder-tooling/save-bar"
for r in lib app immobili skills; do
  echo "== $r"
  git -C "/Users/andreamarinoni/Developer/packages/$r" fetch origin main --quiet
  git -C "/Users/andreamarinoni/Developer/packages/$r" status --short --branch | head -3
  git -C "/Users/andreamarinoni/Developer/packages/$r" rev-list --left-right --count main...origin/main
  git -C "/Users/andreamarinoni/Developer/packages/$r" check-ignore -q .claude/worktrees/backend-save-bar && echo "cartella ignorata"
done
```

Expected:
- le prime due righe sono uguali (`.git` e `.git`): sei nella checkout principale, non in un worktree;
- la riga del superproject è vuota;
- per ogni repo compaiono `## main...origin/main` e `cartella ignorata`;
- il conteggio `rev-list` vale `0	0` per lib, immobili e skills.

Per l'app può valere `0	N` o `N	0`: il branch dell'app parte da un commit già fissato, quindi non conta. Se lib, immobili o skills hanno `main` diverso da `origin/main`, fermati e chiedi all'utente come allinearlo. Non fare `pull` e non fare `reset` di tua iniziativa.

- [ ] **Step 2: Worktree della lib su un branch nuovo da `main`**

```bash
cd /Users/andreamarinoni/Developer/packages/lib
git worktree add .claude/worktrees/backend-save-bar -b backend-save-bar main
cd .claude/worktrees/backend-save-bar
npm ci
git status --short --branch
```

Expected: `Preparing worktree (new branch 'backend-save-bar')`, `npm ci` senza errori, poi `## backend-save-bar` senza file modificati.

- [ ] **Step 3: Worktree dell'app sul branch `backend-save-bar` esistente**

Il branch contiene la spec e il piano, e può essere ancora aperto nel worktree della sessione che li ha scritti. Un branch non può stare in due worktree.

```bash
cd /Users/andreamarinoni/Developer/packages/app
git worktree prune
git worktree list | grep '\[backend-save-bar\]'
```

- Se la riga non compare, passa al blocco successivo.
- Se compare con un percorso diverso da `.claude/worktrees/backend-save-bar`, esegui `git -C <percorso> status --porcelain`:
  - se non stampa niente, esegui `git worktree remove <percorso>`: il branch e i commit restano;
  - se stampa file, fermati e chiedi all'utente cosa fare di quelle modifiche.

```bash
cd /Users/andreamarinoni/Developer/packages/app
git worktree add .claude/worktrees/backend-save-bar backend-save-bar
cd .claude/worktrees/backend-save-bar
git log --oneline -3
ls docs/superpowers/specs/2026-09-27-backend-save-bar-design.md
composer install --no-interaction
git status --short --branch
```

Expected:
- il log mostra i commit della spec e del piano sopra `559d6d02` o sopra un `main` più recente;
- il file della spec esiste;
- `composer install` termina con `Generating autoload files`;
- lo stato è `## backend-save-bar` senza file modificati (`vendor/` è ignorato).

Non usare un symlink al `vendor/` principale al posto di `composer install`. PHP risolve i symlink in `__DIR__`, quindi l'autoload di Composer caricherebbe `class/` dalla checkout principale e non dal worktree: i test passerebbero sul codice sbagliato. È stato verificato con un file sotto un symlink, e `__DIR__` restituisce il percorso reale.

- [ ] **Step 4: Worktree di immobili e skills su branch nuovi da `main`**

```bash
cd /Users/andreamarinoni/Developer/packages/immobili
git worktree add .claude/worktrees/backend-save-bar -b backend-save-bar main
git -C .claude/worktrees/backend-save-bar status --short --branch
cd /Users/andreamarinoni/Developer/packages/skills
git worktree add .claude/worktrees/backend-save-bar -b backend-save-bar main
git -C .claude/worktrees/backend-save-bar status --short --branch
```

Expected: due `Preparing worktree (new branch 'backend-save-bar')` e due `## backend-save-bar` puliti.

Il worktree di immobili resta senza `vendor/`:
- il Task R1 cambia solo una vista e la verifica con un controllo che non usa l'autoload;
- `composer install` nel worktree fallirebbe comunque, perché il repository path `../app` di `composer.json` punterebbe a `.claude/worktrees/app`, che non esiste.

I 2 test di immobili girano dalla checkout principale (Step 6), che ha gli stessi `src/`.

- [ ] **Step 5: Playwright nella cache**

```bash
test -f "$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core/package.json" \
  || npm install --prefix "$HOME/.cache/wonder-tooling/playwright" playwright-core@^1.63.0
node -p "require(process.env.HOME + '/.cache/wonder-tooling/playwright/node_modules/playwright-core/package.json').version"
ls -d "/Applications/Google Chrome.app"
```

Expected: una versione `1.63.x` o successiva e il percorso di Chrome. Playwright usa Chrome installato (`channel: 'chrome'`), non scarica browser. Se Chrome manca, fermati e chiedi all'utente di installarlo.

- [ ] **Step 6: Risultati di base (Step 3 della skill)**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
npm test > "$HOME/.cache/wonder-tooling/save-bar/lib-test.log" 2>&1; echo "exit=$?"
tail -2 "$HOME/.cache/wonder-tooling/save-bar/lib-test.log"
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" CHROME_CHANNEL=chrome \
  node test/deferred-content.browser.test.cjs
```

Expected: `exit=0`, poi `flag-icons assets tests passed` e `source map paths tests passed`, poi `DeferredContent browser checks passed`.

```bash
cd /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
git ls-files tests | grep -v -e harness.php -e scheduler-integration.php | wc -l
for f in $(git ls-files tests | grep -v -e harness.php -e scheduler-integration.php); do php "$f" >/dev/null || echo "FALLITO $f"; done \
  | tee "$HOME/.cache/wonder-tooling/save-bar/app-baseline.txt"
wc -l < "$HOME/.cache/wonder-tooling/save-bar/app-baseline.txt"
```

Expected:
- `124`, cioè i file eseguiti;
- nessuna riga `FALLITO`, quindi `0`.

`scheduler-integration.php` resta fuori perché crea un sito usa e getta e usa un DB. I test scrivono solo in `storage/logs/`, che è ignorato (`.gitignore:8 /storage/`).

```bash
cd /Users/andreamarinoni/Developer/packages/immobili
php tests/listing-routes.php
php tests/property-media.php
cd /Users/andreamarinoni/Developer/packages/gestionale
ls tests/*Test.php | wc -l
for f in tests/*Test.php; do php "$f" >/dev/null || echo "FALLITO $f"; done \
  | tee "$HOME/.cache/wonder-tooling/save-bar/gestionale-baseline.txt"
wc -l < "$HOME/.cache/wonder-tooling/save-bar/gestionale-baseline.txt"
git status --porcelain | wc -l
```

Expected:
- `Listing route checks passed` e `Property media checks passed`;
- `71` file del gestionale e `0` righe `FALLITO`;
- lo stesso numero di file modificati di prima: la checkout del gestionale ha già modifiche dell'utente (CHANGELOG e docs), e i test non ne aggiungono.

I test del gestionale in `tests/integrazione/` restano fuori perché usano il database di `ecommerce-site`. `tests/run.php` non si usa, perché li includerebbe.

Se un risultato di base è rosso, fermati: riporta i fallimenti all'utente e chiedi se procedere. I fallimenti di base non sono regressioni della barra, ma vanno annotati prima di iniziare.

- [ ] **Step 7: Resoconto**

Riporta all'utente, senza commit:
- i quattro percorsi dei worktree e i rispettivi branch;
- i conteggi di base (`npm test` verde, test browser verde, app 124/0, gestionale 71/0, immobili 2/2);
- la versione di Playwright.

## Parte C — saveBar.js: tracciamento, API, isola, bottoni, invio

Copre il file `src/build/backend/js/form/saveBar.js` della lib: istantanea e ricalcolo (sez. 2.1-2.4), API e avvio (sez. 1 e 3 A), isola e stati (sez. 3 E, 3 L e il markup della sez. 4), bottoni copiati e fascia (sez. 3 B-D), invio, avviso di uscita e bfcache (sez. 3 F, G, J), più `loadingSpinner(show)` e i test U1-U28.

Regole valide per tutta la parte:
- ogni blocco di comandi parte da `cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar`: è il worktree `LIB_WT` del Task S1, sul branch `backend-save-bar`, con `node_modules/` già installati da `npm ci`;
- `dist/` non si tocca mai sul branch: la build si verifica fuori dal repo con `npx webpack --output-path "$(mktemp -d)"`, e `dist/` viene ricostruita e committata da `npm run release` al rilascio (Parte R). Nessun passo di questa parte lancia `npm run build`;
- la parte non dipende da I1-I4 e va prima di I5-I7. Lo stile dell'isola (`save-bar.css`) è di I4: qui stati e classi si controllano nel DOM finto, l'aspetto no;
- i numeri di riga sono quelli del `main` di partenza, dopo i task precedenti di questa parte. Se la Parte I è già entrata e ha spostato righe (`package.json:11`, `src/export/backend/head.js`), vale il testo da sostituire, che nel file compare una volta sola;
- `saveBar.js` e `test/backend-save-bar.test.cjs` sono file nuovi: solo ASCII, commenti brevi in italiano senza accenti (`e'`). Anche `utility.js` e `pageSetUp.js` del backend sono ASCII e restano tali;
- il file di test cresce task dopo task: il DOM finto si scrive tutto in C1, e ogni task aggiunge i suoi casi subito prima del commento `// Esecuzione in ordine, anche dei test asincroni.`, che resta l'ultima parte del file. Il DOM finto non interpreta `innerHTML` e non fa layout: la pulizia delle etichette, la geometria, il CSS, il focus e i dialoghi veri si provano nei test B (Parte B);
- `/Users/andreamarinoni/Developer/packages` e `/Users/andreamarinoni/Desktop/PROGETTI/template` sono la stessa cartella: se l'hook rifiuta Edit/Write sul worktree col percorso `Developer/packages`, si usa lo stesso percorso sotto `Desktop/PROGETTI/template`;
- push, PR, merge, release npm e tag si fanno **solo su richiesta esplicita dell'utente**; in questa parte non ce ne sono.

### Task C1: istantanea, confronto, assorbimento e tabella degli stati

Repo: **lib**.

Spec Principi di codice (1, 3, 4), sez. 1 Contratto, 2.1, 2.3, 3 E; test U1 e le funzioni pure che U10-U28 usano. Qui nascono il file dell'isola, con la `window.wiSaveBar` di default e le funzioni generiche senza markup, e il file di test con il DOM finto che servirà a tutta la parte.

**Files:**
- Create: `src/build/backend/js/form/saveBar.js`
- Create: `test/backend-save-bar.test.cjs`
- Modify: `package.json:11` (script `test`)

**Interfaces:**
- Consumes: `AutoNumeric.getAutoNumericElement(el)`, globale in ogni pagina del backend (la usa già `setAutonumeric()`, chiamata da `checkInput()` prima dell'isola); `FormData`, `File`, `requestAnimationFrame`.
- Produces:
  - `window.wiSaveBar = { changed, reset, isDirty, absorb, labels: { group, unsaved, confirmOther } }`, con le quattro funzioni uguali a `saveBarOff()` (restituisce `false`) finché `setUpSaveBar()` (C2) non le sostituisce;
  - `formSnapshot(form, excludeSelector) → { keys, names, sets }`: `keys[i]` è `JSON.stringify([nome, valore])` (per i file `[nome, name, size, type]`), `names[i]` il nome, `sets[nome]` è `true` per i nomi fatti solo di checkbox; `formSnapshot.warned` tiene i nomi già segnalati da Q1;
  - `sameSnapshot(a, b) → boolean`, `absorbSnapshot(base, current, container) → istantanea`, `frameScheduler(fn) → function`, `saveBarState(ready, submitting, dirty, outside) → 'hidden' | 'buttons' | 'dirty-buttons' | 'dirty-label' | 'submitting'`;
  - nel test: `newPage(build, options)` (DOM finto, stub del browser, script caricati in `vm`), con `t.start()`, `t.tick()`, `t.fire()`, `t.mutate()`, `t.intersect()`, `t.resize()`, `t.bar()`, `t.state()`, `t.copies()`, `t.events()`; `snap(pairs, sets)`; `test(name, fn)` e il commento di ancoraggio `// Esecuzione in ordine, anche dei test asincroni.`.

- [ ] **Step 1: Scrivi il DOM finto e i test che falliscono**

Il DOM finto ha nodi con attributi riflessi (`id`, `name`, `class`, `hidden`, `disabled`, `inert`, `type`), `form`/`elements` come nel browser (anche con `form="id"`), selettori semplici con virgole e `:disabled` (che vede i fieldset disabilitati), eventi con `isTrusted` impostabile e le fasi di cattura e bolla fino a `window`. `click()` fa come il browser: il click da script non è fidato, l'invio che ne nasce sì. `FormData` finta segue le regole di quella vera (niente disabilitati, bottoni o checkbox spente; il file input vuoto dà un `File` senza nome) e aggiunge le voci di `form.extra`, come un listener `formdata`. Gli osservatori registrano bersagli e opzioni, e il test li fa scattare a mano.

Crea `test/backend-save-bar.test.cjs` con questo contenuto:

```js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const read = (file) => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');

const EXCLUDE = '.modal, [data-wi-save-bar-ignore]';
const SKIP = '.modal, .offcanvas, [data-wi-save-bar-ignore]';

// Evento finto: isTrusted si imposta, l'Event di Node lo tiene sempre false.
class FakeEvent {
    constructor(type, init = {}) {
        Object.assign(this, { bubbles: false, cancelable: false, isTrusted: false, detail: null }, init);
        Object.assign(this, { type, defaultPrevented: false, stopped: false });
    }
    preventDefault() { if (this.cancelable) this.defaultPrevented = true; }
    stopPropagation() { this.stopped = true; }
}

const captureOf = (options) => options === true || !!(options && options.capture);

// Cattura dalla finestra al bersaglio, poi il bersaglio, poi la bolla.
class Target {
    constructor() { this.listeners = []; }
    addEventListener(type, fn, options) {
        const capture = captureOf(options);
        if (!this.listeners.some((l) => l.type === type && l.fn === fn && l.capture === capture)) this.listeners.push({ type, fn, capture });
    }
    removeEventListener(type, fn, options) {
        const capture = captureOf(options);
        this.listeners = this.listeners.filter((l) => !(l.type === type && l.fn === fn && l.capture === capture));
    }
    has(type, fn) { return this.listeners.some((l) => l.type === type && (!fn || l.fn === fn)); }
    dispatchEvent(event) {
        const chain = [];
        for (let node = this; node; node = node.eventParent()) chain.push(node);
        event.target = this;
        const run = (node, capture) => {
            if (event.stopped) return;
            event.currentTarget = node;
            node.listeners.slice().forEach((l) => {
                if (l.type === event.type && l.capture === capture && node.listeners.includes(l)) l.fn.call(node, event);
            });
        };
        for (let i = chain.length - 1; i > 0; i--) run(chain[i], true);
        run(this, true);
        run(this, false);
        if (event.bubbles) for (let i = 1; i < chain.length; i++) run(chain[i], false);
        return !event.defaultPrevented;
    }
}

class FakeFile {
    constructor(name, size, type) { Object.assign(this, { name, size, type }); }
}

const LISTED = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'FIELDSET', 'OUTPUT'];
const PROPS = ['value', 'checked', 'files', 'autoNumeric', 'rect', 'shown', 'offsetHeight', 'isContentEditable', 'extra'];

// Un DOM quanto basta: selettori semplici (tag, .classe, #id, [attr], [attr=v], :disabled) separati da virgole.
function newDom() {
    const doc = new Target();
    const win = new Target();

    const walk = (root) => root.children.flatMap((child) => [child, ...walk(child)]);
    const ancestors = (el) => {
        const list = [];
        for (let node = el; node && node.nodeType === 1; node = node.parentNode) list.push(node);
        return list;
    };
    const disabled = (el) => LISTED.includes(el.tagName)
        && ancestors(el).some((node) => (node === el || node.tagName === 'FIELDSET') && node.hasAttribute('disabled'));
    const matchOne = (el, compound) => compound.match(/[.#]?[\w-]+|\*|\[[^\]]+\]|:[\w-]+/g).every((token) => {
        if (token === '*') return true;
        if (token[0] === '.') return el.classList.contains(token.slice(1));
        if (token[0] === '#') return el.id === token.slice(1);
        if (token[0] === ':') return token === ':disabled' && disabled(el);
        if (token[0] === '[') {
            const [, name, value] = token.match(/^\[([\w-]+)(?:=["']?([^"'\]]*)["']?)?\]$/);
            return value === undefined ? el.hasAttribute(name) : el.getAttribute(name) === value;
        }
        return el.tagName === token.toUpperCase();
    });
    const escape = (text) => String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    class El extends Target {
        constructor(tag) {
            super();
            Object.assign(this, { tagName: tag.toUpperCase(), nodeType: 1, attrs: new Map(), nodes: [], parentNode: null, innerHTML: '', value: '', checked: false, style: {}, focusCalls: [] });
            if (this.tagName === 'TEMPLATE') {
                this.content = {
                    append: (...texts) => { this.innerHTML += texts.map(escape).join(''); },
                    querySelectorAll: () => [],
                };
            }
        }
        eventParent() { return this.parentNode; }
        get children() { return this.nodes.filter((node) => node.nodeType === 1); }
        get textContent() { return this.nodes.map((node) => node.textContent).join(''); }
        set textContent(text) { this.nodes = [{ nodeType: 3, textContent: String(text), parentNode: this }]; }
        getAttribute(name) { return this.attrs.has(name) ? this.attrs.get(name) : null; }
        setAttribute(name, value) { this.attrs.set(name, String(value)); }
        hasAttribute(name) { return this.attrs.has(name); }
        removeAttribute(name) { this.attrs.delete(name); }
        toggleAttribute(name, force) {
            const on = force === undefined ? !this.hasAttribute(name) : !!force;
            if (on) this.setAttribute(name, ''); else this.removeAttribute(name);
            return on;
        }
        get id() { return this.getAttribute('id') || ''; }
        set id(value) { this.setAttribute('id', value); }
        get name() { return this.getAttribute('name') || ''; }
        set name(value) { this.setAttribute('name', value); }
        get className() { return this.getAttribute('class') || ''; }
        set className(value) { this.setAttribute('class', value); }
        get hidden() { return this.hasAttribute('hidden'); }
        set hidden(value) { this.toggleAttribute('hidden', !!value); }
        get disabled() { return this.hasAttribute('disabled'); }
        set disabled(value) { this.toggleAttribute('disabled', !!value); }
        get inert() { return this.hasAttribute('inert'); }
        set inert(value) { this.toggleAttribute('inert', !!value); }
        get type() {
            const type = (this.getAttribute('type') || '').toLowerCase();
            if (this.tagName === 'BUTTON') return ['button', 'reset'].includes(type) ? type : 'submit';
            if (this.tagName === 'SELECT') return 'select-one';
            return this.tagName === 'INPUT' ? type || 'text' : type || undefined;
        }
        set type(value) { this.setAttribute('type', value); }
        get classList() {
            const list = () => this.className.split(/\s+/).filter(Boolean);
            const write = (names) => { this.className = names.join(' '); };
            const classList = {
                contains: (name) => list().includes(name),
                add: (...names) => write([...new Set([...list(), ...names])]),
                remove: (...names) => write(list().filter((name) => !names.includes(name))),
                toggle: (name, force) => {
                    const on = force === undefined ? !list().includes(name) : !!force;
                    if (on) classList.add(name); else classList.remove(name);
                    return on;
                },
                [Symbol.iterator]: () => list()[Symbol.iterator](),
            };
            return classList;
        }
        get isConnected() { return ancestors(this).includes(doc.documentElement); }
        get form() {
            if (!LISTED.includes(this.tagName)) return undefined;
            if (this.hasAttribute('form')) return doc.getElementById(this.getAttribute('form'));
            return ancestors(this).find((node) => node.tagName === 'FORM') || null;
        }
        get elements() {
            return walk(this.isConnected ? doc.documentElement : this).filter((el) => el.form === this);
        }
        matches(selector) { return selector.split(',').some((part) => matchOne(this, part.trim())); }
        closest(selector) {
            doc.closestCalls.push(selector);
            return ancestors(this).find((node) => node.matches(selector)) || null;
        }
        querySelectorAll(selector) { return walk(this).filter((el) => el.matches(selector)); }
        querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
        contains(node) { return !!node && node.nodeType === 1 && ancestors(node).includes(this); }
        insertBefore(node, ref) {
            if (typeof node === 'string') node = { nodeType: 3, textContent: node };
            if (node.parentNode) node.parentNode.nodes = node.parentNode.nodes.filter((child) => child !== node);
            node.parentNode = this;
            const at = this.nodes.indexOf(ref);
            this.nodes.splice(at < 0 ? this.nodes.length : at, 0, node);
            return node;
        }
        append(...nodes) { nodes.forEach((node) => this.insertBefore(node, null)); }
        appendChild(node) { return this.insertBefore(node, null); }
        remove() {
            if (this.parentNode) this.parentNode.nodes = this.parentNode.nodes.filter((child) => child !== this);
            this.parentNode = null;
        }
        getBoundingClientRect() { return this.rect || { top: 0, bottom: 0, left: 0, right: 0, width: 0, height: 0 }; }
        checkVisibility() { return this.shown !== false; }
        getClientRects() { return this.shown === false ? [] : [{}]; }
        focus(options) { doc.activeElement = this; this.focusCalls.push(options); }
        blur() { if (doc.activeElement === this) doc.activeElement = doc.body; }
        // Come il browser: il click da script non e' fidato, l'invio che ne nasce si'.
        click() {
            if (this.disabled || !this.dispatchEvent(new FakeEvent('click', { bubbles: true, cancelable: true }))) return;
            if (this.type === 'submit' && ['BUTTON', 'INPUT'].includes(this.tagName) && this.form) {
                const event = new FakeEvent('submit', { bubbles: true, cancelable: true, isTrusted: true, submitter: this });
                doc.submits.push({ form: this.form, submitter: this, sent: this.form.dispatchEvent(event) });
            }
        }
    }

    Object.assign(doc, { nodeType: 9, closestCalls: [], submits: [] });
    doc.eventParent = () => win;
    win.eventParent = () => null;
    doc.createElement = (tag) => new El(tag);
    doc.documentElement = new El('html');
    doc.documentElement.parentNode = doc;
    doc.body = new El('body');
    doc.documentElement.append(doc.body);
    doc.activeElement = doc.body;
    doc.getElementById = (id) => walk(doc.documentElement).find((el) => el.id === id) || null;
    doc.querySelectorAll = (selector) => doc.documentElement.querySelectorAll(selector);
    doc.querySelector = (selector) => doc.documentElement.querySelector(selector);

    return { doc, win };
}

// Una pagina: DOM finto, stub del browser e gli script caricati in un contesto vm.
function newPage(build = () => [], options = {}) {
    const { doc, win } = newDom();
    const env = { formData: 0, band: '96px', coarse: false, ioThrows: null, jqThrows: false, confirmAnswer: true, confirms: [], replaced: [], spinner: [] };
    const frames = [];
    const observers = { io: [], ro: [], mo: [] };
    const jq = [];
    const logs = { warn: [], error: [] };

    class FakeIO {
        constructor(callback, init, list = observers.io) {
            if (list === observers.io && env.ioThrows && env.ioThrows(init)) throw new SyntaxError('rootMargin non valido');
            Object.assign(this, { callback, options: init, targets: [], observed: [], disconnected: false });
            list.push(this);
        }
        observe(el) { this.targets.push(el); this.observed.push(el); }
        unobserve(el) { this.targets = this.targets.filter((target) => target !== el); }
        disconnect() { this.disconnected = true; this.targets = []; }
    }
    class FakeRO extends FakeIO { constructor(callback) { super(callback, undefined, observers.ro); } }
    class FakeMO {
        constructor(callback) {
            Object.assign(this, { callback, targets: [], disconnected: false });
            observers.mo.push(this);
        }
        observe(target, init) { this.targets.push({ target, options: init }); }
        disconnect() { this.disconnected = true; this.targets = []; }
    }
    class FakeFormData {
        constructor(form) {
            env.formData++;
            this.entries = [];
            for (const el of form.elements) {
                if (!el.name || el.matches(':disabled') || ['BUTTON', 'FIELDSET'].includes(el.tagName) || /^(submit|button|reset|image)$/.test(el.type)) continue;
                if (/^(checkbox|radio)$/.test(el.type)) {
                    if (el.checked) this.entries.push([el.name, el.value || 'on']);
                } else if (el.type === 'file') {
                    (el.files && el.files.length ? el.files : [new FakeFile('', 0, 'application/octet-stream')]).forEach((file) => this.entries.push([el.name, file]));
                } else {
                    [].concat(el.value).forEach((value) => this.entries.push([el.name, String(value)]));
                }
            }
            // Le voci aggiunte dai listener formdata, come il manifest di FilePond.
            (form.extra || []).forEach((pair) => this.entries.push(pair));
        }
        forEach(fn) { this.entries.forEach(([name, value]) => fn(value, name, this)); }
    }

    const visualViewport = new Target();
    visualViewport.eventParent = () => null;
    visualViewport.height = 800;

    const context = {
        document: doc,
        console: { log: console.log, warn: (...args) => logs.warn.push(args), error: (...args) => logs.error.push(args) },
        Event: FakeEvent,
        CustomEvent: FakeEvent,
        FormData: FakeFormData,
        File: FakeFile,
        IntersectionObserver: FakeIO,
        ResizeObserver: FakeRO,
        MutationObserver: FakeMO,
        HTMLElement: function HTMLElement() {},
        HTMLFormElement: function HTMLFormElement() {},
        AutoNumeric: { getAutoNumericElement: (el) => el.autoNumeric || null },
        requestAnimationFrame: (fn) => frames.push(fn),
        innerHeight: 800,
        visualViewport,
        getComputedStyle: () => ({ getPropertyValue: (name) => (name === '--wi-save-bar-band' ? env.band : '') }),
        matchMedia: (query) => ({ media: query, get matches() { return query === '(pointer: coarse)' && env.coarse; } }),
        location: { href: 'https://new.test/backend/prodotti/?id=1#dati', replace: (url) => env.replaced.push(url) },
        confirm: (text) => { env.confirms.push(text); return env.confirmAnswer; },
        loadingSpinner: (show) => env.spinner.push(show),
        preventPageExit: function preventPageExit() {},
        addEventListener: win.addEventListener.bind(win),
        removeEventListener: win.removeEventListener.bind(win),
        dispatchEvent: win.dispatchEvent.bind(win),
    };
    context.window = context;
    context.$ = context.jQuery = (target) => ({
        on(events, handler) {
            if (env.jqThrows) throw new Error('jQuery rotto');
            jq.push({ target, events, handler });
            return this;
        },
    });
    context.HTMLElement.prototype.inert = false;
    context.HTMLFormElement.prototype.submit = function submit() {};
    const formSubmit = context.HTMLFormElement.prototype.submit;

    const h = (tag, props = {}, ...children) => {
        const el = doc.createElement(tag);
        Object.entries(props).forEach(([key, value]) => {
            if (PROPS.includes(key)) el[key] = value;
            else if (value !== false) el.setAttribute(key, value === true ? '' : value);
        });
        el.append(...children);
        return el;
    };
    doc.body.append(...build(h));

    if (options.before) options.before(context);
    vm.createContext(context);
    (options.scripts || ['src/build/backend/js/form/saveBar.js']).forEach((file) => vm.runInContext(read(file), context));

    const t = {
        context, doc, win, env, frames, observers, jq, logs, formSubmit, h,
        api: context.wiSaveBar,
        form: (id) => doc.getElementById(id),
        start: () => context.setUpSaveBar(),
        // Svuota la coda di requestAnimationFrame, come un frame del browser.
        tick: () => { const run = frames.splice(0); run.forEach((fn) => fn(0)); return run.length; },
        fire: (target, type, init = {}) => target.dispatchEvent(new FakeEvent(type, { bubbles: true, cancelable: true, isTrusted: true, ...init })),
        mutate: (target, records) => observers.mo.filter((mo) => mo.targets.some((item) => item.target === target)).forEach((mo) => mo.callback(records, mo)),
        intersect: (el, isIntersecting) => observers.io.filter((io) => io.targets.includes(el)).forEach((io) => io.callback([{ target: el, isIntersecting }], io)),
        resize: (el) => observers.ro.filter((ro) => ro.targets.includes(el)).forEach((ro) => ro.callback([{ target: el }], ro)),
        bar: () => doc.querySelector('.wi-save-bar'),
        state: () => t.bar().getAttribute('data-wi-state'),
        copies: () => t.bar().querySelectorAll('.wi-save-bar-btn'),
        events: (form) => {
            const list = [];
            form.addEventListener('wi:save-bar:change', (event) => list.push(event.detail.dirty));
            return list;
        },
    };
    return t;
}

// Istantanea di comodo per i confronti.
const snap = (pairs, sets = {}) => ({
    keys: pairs.map((pair) => JSON.stringify(pair)),
    names: pairs.map((pair) => pair[0]),
    sets,
});

const tests = [];
const test = (name, fn) => tests.push({ name, fn });

test('U1 contratto dopo il caricamento', () => {
    const t = newPage((h) => [h('form', { id: 'f', 'data-wi-save-bar': true }, h('input', { name: 'title', value: 'Casa' }))]);
    ['changed', 'reset', 'isDirty', 'absorb'].forEach((name) => assert.equal(typeof t.api[name], 'function', name));
    assert.deepEqual({ ...t.api.labels }, {
        group: 'Barra di salvataggio',
        unsaved: 'Operazione non salvata',
        confirmOther: 'Ci sono modifiche non salvate in un altro modulo della pagina. Continuare e perderle?',
    });
    assert.equal('disarm' in t.api, false, 'disarm non esiste');
    assert.equal(t.context.HTMLFormElement.prototype.submit, t.formSubmit, 'submit dei form intatto');
    ['changed', 'reset', 'isDirty', 'absorb'].forEach((name) => assert.equal(t.api[name](t.form('f')), false, name + ' prima dell\'avvio'));
    assert.equal(t.frames.length, 0, 'nessun lavoro prima dell\'avvio');
});

test('formSnapshot: le voci in ordine, come le spedirebbe il form', () => {
    const t = newPage((h) => [h('form', { id: 'f' },
        h('input', { name: 'title', value: 'Casa' }),
        h('input', { name: 'tags[]', type: 'checkbox', value: 'a', checked: true }),
        h('input', { name: 'tags[]', type: 'checkbox', value: 'b' }),
        h('input', { name: 'off', value: 'x', disabled: true }),
        h('button', { name: 'upload', value: '1' }, 'Salva'),
    )]);
    const form = t.form('f');
    form.extra = [['gallery__wi_files', '["a.jpg"]']];
    const result = t.context.formSnapshot(form, EXCLUDE);
    assert.deepEqual([...result.keys], ['["title","Casa"]', '["tags[]","a"]', '["gallery__wi_files","[\\"a.jpg\\"]"]']);
    assert.deepEqual([...result.names], ['title', 'tags[]', 'gallery__wi_files']);
    assert.equal(result.sets['tags[]'], true, 'nome fatto solo di checkbox');
    assert.equal(result.sets.title, false);
});

test('formSnapshot: AutoNumeric vale il numero, anche dopo una riga eliminata', () => {
    const numeric = (value) => ({ getNumericString: () => value });
    const t = newPage((h) => [h('form', { id: 'f' },
        // La riga eliminata del repeater: fieldset disabilitato, i campi dentro no.
        h('fieldset', { disabled: true }, h('input', { name: 'price[]', value: '\u20ac 10,00', autoNumeric: numeric('10') })),
        h('fieldset', {}, h('input', { name: 'price[]', value: '\u20ac 20,00', autoNumeric: numeric('20') })),
        h('input', { name: 'discount', value: '\u20ac', autoNumeric: numeric('') }),
        h('input', { name: 'empty', value: 'testo', autoNumeric: numeric(null) }),
    )]);
    const result = t.context.formSnapshot(t.form('f'), EXCLUDE);
    assert.deepEqual([...result.keys], ['["price[]","20"]', '["discount",""]', '["empty","testo"]']);
});

test('formSnapshot: i file valgono nome, dimensione e tipo; la voce vuota non conta', () => {
    const t = newPage((h) => [h('form', { id: 'f' },
        h('input', { name: 'cover', type: 'file', files: [new FakeFile('casa.jpg', 1200, 'image/jpeg')] }),
        h('input', { name: 'pdf', type: 'file' }),
    )]);
    assert.deepEqual([...t.context.formSnapshot(t.form('f'), EXCLUDE).keys], ['["cover","casa.jpg",1200,"image/jpeg"]']);
});

test('formSnapshot: zone escluse per nome (Q1), con un solo avviso per nome', () => {
    const t = newPage((h) => [h('form', { id: 'f' },
        h('input', { name: 'title', value: 'Casa' }),
        h('div', { class: 'modal' }, h('input', { name: 'modal_only', value: 'x' }), h('input', { name: 'both', value: 'dentro' })),
        h('input', { name: 'both', value: 'fuori' }),
        h('input', { name: 'search', value: 'q', 'data-wi-save-bar-ignore': true }),
        h('div', { 'data-wi-save-bar-ignore': true }, h('input', { name: 'filter', value: 'f' })),
    )]);
    const result = t.context.formSnapshot(t.form('f'), EXCLUDE);
    assert.deepEqual([...result.keys], ['["title","Casa"]', '["both","dentro"]', '["both","fuori"]']);
    t.context.formSnapshot(t.form('f'), EXCLUDE);
    assert.equal(t.logs.warn.length, 1, 'un avviso solo, anche alla seconda istantanea');
    assert.match(t.logs.warn[0][0], /"both"/);
});

test('sameSnapshot: conta l\'ordine, tranne per i nomi fatti solo di checkbox', () => {
    const t = newPage();
    const same = t.context.sameSnapshot;
    const rows = snap([['rows[]', 'a'], ['rows[]', 'b']], { 'rows[]': false });
    assert.equal(same(rows, snap([['rows[]', 'a'], ['rows[]', 'b']], { 'rows[]': false })), true);
    assert.equal(same(rows, snap([['rows[]', 'b'], ['rows[]', 'a']], { 'rows[]': false })), false, 'riga spostata');
    const tags = snap([['title', 'x'], ['tags[]', '1'], ['tags[]', '2']], { title: false, 'tags[]': true });
    assert.equal(same(tags, snap([['tags[]', '2'], ['title', 'x'], ['tags[]', '1']], { title: false, 'tags[]': true })), true, 'checkbox come insieme');
    assert.equal(same(tags, snap([['title', 'x'], ['tags[]', '1']], { title: false, 'tags[]': true })), false, 'checkbox tolta');
    assert.equal(same(tags, snap([['title', 'y'], ['tags[]', '1'], ['tags[]', '2']], { title: false, 'tags[]': true })), false);
});

test('absorbSnapshot: prende da current solo i campi dentro il contenitore', () => {
    const t = newPage((h) => [h('form', { id: 'f' },
        h('input', { name: 'title' }),
        h('div', { id: 'box' }, h('input', { name: 'city[]' })),
    )]);
    const { absorbSnapshot, sameSnapshot } = t.context;
    const box = t.form('box');
    const base = snap([['title', 'Casa'], ['city[]', 'Roma']]);
    // L'utente ha cambiato title, un riempimento tardivo ha scritto due citta'.
    const current = snap([['title', 'Villa'], ['city[]', 'Roma'], ['city[]', 'Milano']]);
    const result = absorbSnapshot(base, current, box);
    assert.deepEqual([...result.keys], ['["title","Casa"]', '["city[]","Roma"]', '["city[]","Milano"]']);
    assert.equal(sameSnapshot(result, current), false, 'title resta sporco');
    assert.equal(sameSnapshot(result, snap([['title', 'Casa'], ['city[]', 'Roma'], ['city[]', 'Milano']])), true);
    // Una voce fuori dal contenitore sparita resta nel punto di partenza.
    const fewer = absorbSnapshot(snap([['title', 'Casa'], ['note', 'n'], ['city[]', 'Roma']]), snap([['title', 'Casa'], ['city[]', 'Roma']]), box);
    assert.deepEqual([...fewer.keys], ['["title","Casa"]', '["city[]","Roma"]', '["note","n"]']);
    // Il contenitore puo' essere il campo stesso.
    const input = box.querySelector('[name]');
    assert.deepEqual([...absorbSnapshot(base, snap([['title', 'Casa'], ['city[]', 'Napoli']]), input).keys], ['["title","Casa"]', '["city[]","Napoli"]']);
});

test('frameScheduler: al massimo una volta per frame', () => {
    const t = newPage();
    let runs = 0;
    const soon = t.context.frameScheduler(() => {
        runs++;
        if (runs === 1) soon();
    });
    soon();
    soon();
    soon();
    assert.equal(t.frames.length, 1);
    assert.equal(runs, 0, 'mai dentro la chiamata');
    t.tick();
    assert.equal(runs, 1);
    assert.equal(t.frames.length, 1, 'una chiamata dentro fn va al frame dopo');
    t.tick();
    assert.equal(runs, 2);
    assert.equal(t.tick(), 0);
});

test('saveBarState: la tabella degli stati', () => {
    const t = newPage();
    const state = t.context.saveBarState;
    assert.equal(state(false, false, true, true), 'hidden', 'prima del primo IO e della prima istantanea');
    assert.equal(state(true, false, false, false), 'hidden');
    assert.equal(state(true, false, false, true), 'buttons');
    assert.equal(state(true, false, true, true), 'dirty-buttons');
    assert.equal(state(true, false, true, false), 'dirty-label');
    assert.equal(state(true, true, true, true), 'submitting');
    assert.equal(state(false, true, false, false), 'submitting');
});

// Esecuzione in ordine, anche dei test asincroni.
(async () => {
    for (const { name, fn } of tests) {
        try {
            await fn();
        } catch (error) {
            console.error('FAIL ' + name);
            throw error;
        }
    }
    console.log('backend-save-bar: ' + tests.length + ' tests passed');
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
```

- [ ] **Step 2: Lancia il test e verifica che fallisca**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-save-bar.test.cjs
```

Expected: FAIL con `FAIL U1 contratto dopo il caricamento` e `Error: ENOENT: no such file or directory, open '.../src/build/backend/js/form/saveBar.js'`: il file non esiste ancora.

- [ ] **Step 3: Crea `saveBar.js` con le funzioni pure e aggiungi il test alla catena**

`formSnapshot` scorre prima `form.elements` per sapere quali nomi stanno in zone escluse, quali sono solo checkbox e quali campi hanno AutoNumeric, poi legge `new FormData(form)`. La voce n-esima di un nome si abbina al campo n-esimo non disabilitato con quel nome: per questo serve `el.matches(':disabled')`, che vede anche il fieldset della riga eliminata del repeater. `absorbSnapshot` prende da `current` le voci dei nomi dentro il contenitore (il contenitore stesso compreso) e, nei posti degli altri nomi, rimette in ordine quelle di `base`.

Crea `src/build/backend/js/form/saveBar.js` con questo contenuto:

```js
/*
 * L'isola di salvataggio del backend, per i `form[data-wi-save-bar]`.
 *
 * Quando i bottoni di invio escono dallo schermo, le loro copie compaiono in
 * un'isola fissa in basso; se il form ha modifiche non salvate l'isola mostra
 * "Operazione non salvata" e l'uscita dalla pagina chiede conferma. Sporco
 * vuol dire che la FormData di adesso e' diversa da quella di partenza.
 *
 * Il contratto sono gli attributi `data-wi-save-bar*`, `window.wiSaveBar` e
 * l'evento `wi:save-bar:change`; classi e `data-wi-state` sono interni.
 * L'avvio e' `setUpSaveBar()`, chiamato da `setUpPage()`.
 */
function saveBarOff() {

    return false;

}

// Prima dell'avvio, e senza form tracciati, le funzioni non fanno nulla.
window.wiSaveBar = {
    changed: saveBarOff,
    reset: saveBarOff,
    isDirty: saveBarOff,
    absorb: saveBarOff,
    labels: {
        group: 'Barra di salvataggio',
        unsaved: 'Operazione non salvata',
        confirmOther: 'Ci sono modifiche non salvate in un altro modulo della pagina. Continuare e perderle?'
    }
};

// Le voci che il form spedirebbe adesso, in ordine e normalizzate.
function formSnapshot(form, excludeSelector) {

    var inside = {}, outside = {}, sets = {}, numeric = {}, seen = {}, keys = [], names = [];

    // :disabled copre anche i campi di un fieldset disabilitato, come la riga eliminata del repeater.
    for (var el of form.elements) {
        if (!el.name || el.matches(':disabled')) continue;
        (el.closest(excludeSelector) ? inside : outside)[el.name] = true;
        sets[el.name] = sets[el.name] !== false && el.type === 'checkbox';
        (numeric[el.name] = numeric[el.name] || []).push(AutoNumeric.getAutoNumericElement(el));
    }

    new FormData(form).forEach((value, name) => {
        var index = seen[name] = (seen[name] || 0) + 1;
        if (inside[name] && !outside[name]) return;
        if (inside[name] && !formSnapshot.warned[name]) {
            formSnapshot.warned[name] = true;
            console.warn('wiSaveBar: il nome "' + name + '" sta dentro e fuori da una zona esclusa, quindi conta intero');
        }
        if (value instanceof File) {
            if (!value.name) return;
            value = [value.name, value.size, value.type];
        } else {
            var auto = numeric[name] && numeric[name][index - 1];
            if (auto && auto.getNumericString() != null) value = auto.getNumericString();
        }
        keys.push(JSON.stringify([name].concat(value)));
        names.push(name);
    });

    return { keys: keys, names: names, sets: sets };

}

formSnapshot.warned = {};

// Conta l'ordine, tranne per i nomi fatti solo di checkbox, che valgono come insieme.
function sameSnapshot(a, b) {

    var text = s => {
        var ordered = [], unordered = [];
        s.keys.forEach((key, i) => (s.sets[s.names[i]] ? unordered : ordered).push(key));
        return ordered.join('\n') + '\n\n' + unordered.sort().join('\n');
    };

    return text(a) === text(b);

}

// Il nuovo punto di partenza: da current solo i campi dentro container, il resto resta com'era.
function absorbSnapshot(base, current, container) {

    var inside = {}, keys = [], names = [], rest = [];

    container.querySelectorAll('[name]').forEach(el => inside[el.name] = true);
    if (container.name) inside[container.name] = true;
    base.names.forEach((name, i) => { if (!inside[name]) rest.push(i); });
    current.names.forEach((name, i) => {
        if (inside[name]) { keys.push(current.keys[i]); names.push(name); }
        else if (rest.length) { var j = rest.shift(); keys.push(base.keys[j]); names.push(base.names[j]); }
    });
    rest.forEach(j => { keys.push(base.keys[j]); names.push(base.names[j]); });

    return { keys: keys, names: names, sets: current.sets };

}

// fn al massimo una volta per frame, mai dentro la chiamata.
function frameScheduler(fn) {

    var queued = false;

    return () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            fn();
        });
    };

}

// La tabella degli stati dell'isola.
function saveBarState(ready, submitting, dirty, outside) {

    if (submitting) return 'submitting';
    if (!ready) return 'hidden';
    if (outside) return dirty ? 'dirty-buttons' : 'buttons';
    return dirty ? 'dirty-label' : 'hidden';

}
```

In `package.json`, riga 11, sostituisci:

```json
node test/source-map-paths.test.cjs",
```

con:

```json
node test/source-map-paths.test.cjs && node test/backend-save-bar.test.cjs",
```

- [ ] **Step 4: Rilancia il test e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-save-bar.test.cjs
npm test
```

Expected: PASS con `backend-save-bar: 9 tests passed`; `npm test` finisce con la stessa riga, senza errori. Da qui in avanti, in questa parte e dopo, la catena finisce con `backend-save-bar: N tests passed` e non più con `source map paths tests passed`.

- [ ] **Step 5: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add src/build/backend/js/form/saveBar.js test/backend-save-bar.test.cjs package.json
git commit -m "feat(backend): istantanea e stati dell'isola di salvataggio" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `3 files changed`.

### Task C2: avvio, segnali, ricalcolo e API pubblica

Repo: **lib**.

Spec sez. 1 (Contratto, API, evento, `data-wi-save-bar-dirty`), 2.2 (segnali), 2.3 (fase prima dell'interazione), 2.4 (`absorb`), Costo, 3 A (avvio), Q2 (evento solo ai passaggi, U23) e Q16 (avvio protetto, U8-U9); test U2, U3, U6-U14, U16-U23. Qui l'isola non si vede ancora: `setUpSaveBar()` tiene lo stato di ogni form e sostituisce le funzioni di `window.wiSaveBar`. Il markup arriva in C3.

**Files:**
- Modify: `src/build/backend/js/form/saveBar.js` (in fondo, dopo la riga 124)
- Modify: `test/backend-save-bar.test.cjs` (prima del commento di ancoraggio, riga 479)
- Modify: `src/build/backend/js/pageSetUp.js:118` (in `setUpPage()`)
- Modify: `src/export/backend/head.js:26`

**Interfaces:**
- Consumes:
  - da C1: `saveBarOff()`, `window.wiSaveBar`, `formSnapshot()`, `sameSnapshot()`, `absorbSnapshot()`, `frameScheduler()` e, nel test, `newPage()` con `t.start()`, `t.tick()`, `t.fire()`, `t.mutate()`, `t.env`, `t.context`, `t.win`, `t.doc`;
  - dalla lib: `$` (jQuery, globale prima di `setUpPage()`), `setUpPage()` di `pageSetUp.js`, `CustomEvent`, `MutationObserver`;
  - eventi già emessi da altri: `FilePond:*` (FilePond sull'elemento radice), `wi-repeater-row-delete`/`wi-repeater-row-restore` (Repeater del tema Bootstrap in `wonder-image/app`), `wi:quick-create:created` (`quickCreate.js`), `autoNumeric:rawValueModified` (AutoNumeric).
- Produces:
  - `setUpSaveBar()`: avvio idempotente (guardia `setUpSaveBar.started`), chiamato da `setUpPage()` tra `setUpJquery()` e `loaded`; senza `IntersectionObserver`, `ResizeObserver`, `MutationObserver` o `inert`, o senza `form[data-wi-save-bar]`, non fa nulla; un errore finisce in `console.error` e non ferma `loaded`;
  - `window.wiSaveBar.changed(el) → boolean` (programma sempre un ricalcolo; `true` se `el` sta in un form tracciato), `reset(form) → boolean` (sincrono: nuovo punto di partenza, via il flag `-dirty`), `isDirty(form) → boolean` (l'ultimo stato calcolato), `absorb(el) → boolean` (al prossimo ricalcolo, che programma da solo, i campi dentro `el` diventano punto di partenza); su form non tracciati, `null` o `undefined` restituiscono `false`;
  - l'evento `wi:save-bar:change` sul form, `bubbles: true`, `detail: { dirty }`, solo ai passaggi (anche da `reset()`), mai all'avvio;
  - dentro la chiusura di `setUpSaveBar()`: `forms` (`{ form, base, flag, dirty, absorb }` per form), `EXCLUDE`, `recalc()`, `recalcSoon`, `stateOf(el)`, `setDirty(state, dirty)`, che C3-C5 estendono;
  - nel test: `PAGE_SCRIPTS`, `oneForm(h)`, `twoForms(h)`, `started(build, options)`, `userTypes(t, el, value)`, `runSetUpPage(t)`.

- [ ] **Step 1: Scrivi i test che falliscono**

`runSetUpPage()` sostituisce i passi di `setUpPage()` prima dell'isola con funzioni finte che si segnano in ordine, così U7 e U8 leggono l'ordine vero delle chiamate. U8 rompe `$(document).on()`: l'errore deve finire in console e `loaded` deve partire lo stesso. U12 mette il bottone di conferma in un `.modal` con un listener che ferma la propagazione: i listener dell'isola stanno in cattura su `document`, quindi il click conta lo stesso. U19 prova anche la pagina "in ritardo", dove il riempimento è già stato contato da un segnale prima di `absorb()`.

In `test/backend-save-bar.test.cjs`, subito prima della riga `// Esecuzione in ordine, anche dei test asincroni.` (riga 479), inserisci questo blocco, seguito da una riga vuota:

```js
const PAGE_SCRIPTS = ['src/build/backend/js/form/saveBar.js', 'src/build/backend/js/pageSetUp.js'];

const oneForm = (h) => [h('form', { id: 'f', 'data-wi-save-bar': true },
    h('input', { id: 'title', name: 'title', value: 'Casa' }),
    h('button', { id: 'save' }, 'Salva'),
)];

const twoForms = (h) => ['a', 'b'].map((id) => h('form', { id, 'data-wi-save-bar': true },
    h('input', { id: id + '_title', name: 'title', value: 'Casa ' + id }),
    h('button', { id: id + '_save' }, 'Salva'),
));

// Avvio e primo frame: il punto di partenza e' preso e il contatore delle FormData riparte da zero.
const started = (build, options) => {
    const t = newPage(build, options);
    t.start();
    t.tick();
    t.env.formData = 0;
    return t;
};

// L'utente scrive: tasto, nuovo valore, input.
const userTypes = (t, el, value) => {
    t.fire(el, 'keydown');
    el.value = value;
    t.fire(el, 'input');
};

// setUpPage con i passi prima dell'isola finti; restituisce l'ordine delle chiamate.
async function runSetUpPage(t) {
    const order = [];
    ['createCard', 'setInput', 'checkInput', 'setUpBootstrap', 'setUpJquery'].forEach((name) => {
        t.context[name] = () => order.push(name);
    });
    const setUpSaveBar = t.context.setUpSaveBar;
    t.context.setUpSaveBar = () => {
        order.push('setUpSaveBar');
        setUpSaveBar();
    };
    t.win.addEventListener('loaded', () => order.push('loaded'));
    await t.context.setUpPage();
    return order;
}

test('U2 le funzioni su un form non tracciato o su null', () => {
    const t = started((h) => [...oneForm(h), h('form', { id: 'g' }, h('input', { id: 'q', name: 'q', value: 'x' }))]);
    const events = [];
    t.doc.addEventListener('wi:save-bar:change', (event) => events.push(event.target.id));
    [t.form('g'), t.form('q'), null, undefined].forEach((target) => {
        ['changed', 'reset', 'isDirty', 'absorb'].forEach((name) => assert.equal(t.api[name](target), false, name));
    });
    t.tick();
    assert.deepEqual(events, []);
    assert.equal(t.api.isDirty(t.form('f')), false);
});

test('U3 pagina senza form tracciati: nessun lavoro', () => {
    const t = newPage((h) => [h('form', { id: 'g' }, h('input', { name: 'q' }), h('button', {}, 'Cerca'))]);
    t.start();
    assert.equal(t.bar(), null);
    assert.deepEqual([t.observers.io.length, t.observers.ro.length, t.observers.mo.length], [0, 0, 0]);
    assert.equal(t.doc.documentElement.className, '');
    assert.deepEqual([t.doc.listeners.length, t.win.listeners.length, t.jq.length, t.frames.length, t.env.formData], [0, 0, 0, 0, 0]);
    assert.equal(t.api.changed, t.context.saveBarOff);
});

test('U6 avvio doppio: un solo ricalcolo per segnale', () => {
    const t = newPage(oneForm);
    t.start();
    t.start();
    assert.deepEqual([t.jq.length, t.observers.mo.length], [1, 1]);
    t.tick();
    t.env.formData = 0;
    t.fire(t.form('title'), 'input');
    assert.equal(t.tick(), 1);
    assert.equal(t.env.formData, 1);
});

test('U7 setUpPage avvia l\'isola subito prima di loaded', async () => {
    const t = newPage(oneForm, { scripts: PAGE_SCRIPTS });
    assert.deepEqual(await runSetUpPage(t), ['createCard', 'setInput', 'checkInput', 'setUpBootstrap', 'setUpJquery', 'setUpSaveBar', 'loaded']);
    assert.equal(t.jq.length, 1, 'isola avviata');
});

test('U8 un errore nell\'avvio finisce in console e loaded parte', async () => {
    const t = newPage(oneForm, { scripts: PAGE_SCRIPTS });
    t.env.jqThrows = true;
    assert.deepEqual((await runSetUpPage(t)).slice(-2), ['setUpSaveBar', 'loaded']);
    assert.equal(t.logs.error.length, 1);
    assert.match(String(t.logs.error[0][0]), /jQuery rotto/);
    assert.equal(t.bar(), null);
    assert.deepEqual([t.doc.listeners.length, t.observers.mo.length, t.frames.length], [0, 0, 0]);
    assert.equal(t.api.changed, t.context.saveBarOff);
});

test('U9 senza le API necessarie niente isola e niente errori', async () => {
    const missing = {
        IntersectionObserver: (context) => delete context.IntersectionObserver,
        ResizeObserver: (context) => delete context.ResizeObserver,
        MutationObserver: (context) => delete context.MutationObserver,
        inert: (context) => delete context.HTMLElement.prototype.inert,
    };
    for (const [name, remove] of Object.entries(missing)) {
        const t = newPage(oneForm, { scripts: PAGE_SCRIPTS, before: remove });
        assert.deepEqual((await runSetUpPage(t)).slice(-1), ['loaded'], name);
        assert.deepEqual([t.logs.error.length, t.doc.listeners.length, t.jq.length, t.frames.length], [0, 0, 0, 0], name);
        assert.equal(t.bar(), null, name);
    }
});

test('U10 nessuna FormData dentro l\'evento, una sola al frame dopo', () => {
    const t = started(oneForm);
    t.fire(t.form('title'), 'input');
    assert.equal(t.env.formData, 0);
    t.tick();
    assert.equal(t.env.formData, 1);
});

test('U11 dieci segnali nello stesso frame: una FormData per form', () => {
    const t = started(twoForms);
    const [a, b] = [t.form('a_title'), t.form('b_title')];
    assert.equal(t.jq[0].target, t.doc);
    assert.equal(t.jq[0].events, 'input change');
    ['input', 'change', 'click', 'keyup'].forEach((type) => t.fire(a, type));
    ['input', 'change', 'keyup'].forEach((type) => t.fire(b, type));
    t.jq[0].handler({ type: 'change' });
    t.api.changed(a);
    t.api.changed();
    assert.equal(t.env.formData, 0);
    assert.equal(t.tick(), 1);
    assert.equal(t.env.formData, 2);
});

test('U12 un click fuori dai form ricalcola tutti i form tracciati', () => {
    const t = started((h) => [...twoForms(h), h('div', { class: 'modal' }, h('button', { id: 'confirm', type: 'button' }, 'Elimina'))]);
    // Come la conferma del repeater: scrive senza eventi e ferma la propagazione.
    t.form('confirm').addEventListener('click', (event) => {
        event.stopPropagation();
        t.form('a_title').value = 'A';
        t.form('b_title').value = 'B';
    });
    t.fire(t.form('confirm'), 'click');
    t.tick();
    assert.equal(t.env.formData, 2);
    assert.deepEqual([t.api.isDirty(t.form('a')), t.api.isDirty(t.form('b'))], [true, true]);
});

test('U13 gli eventi dei widget programmano un ricalcolo', () => {
    const t = started(oneForm);
    const types = ['FilePond:addfile', 'FilePond:removefile', 'FilePond:reorderfiles', 'FilePond:processfile', 'FilePond:processfilerevert', 'FilePond:updatefiles', 'wi-repeater-row-delete', 'wi-repeater-row-restore', 'wi:quick-create:created', 'autoNumeric:rawValueModified'];
    types.forEach((type) => {
        t.fire(type === 'wi:quick-create:created' ? t.doc : t.form('title'), type, { isTrusted: false });
        assert.equal(t.tick(), 1, type);
    });
    assert.equal(t.env.formData, types.length);
});

test('U14 righe e disabled cambiati da script: un ricalcolo, form sporco', () => {
    const rows = (h) => [h('form', { id: 'f', 'data-wi-save-bar': true }, h('div', { id: 'rows' },
        h('input', { id: 'r1', name: 'row[]', value: 'a' }),
        h('input', { id: 'r2', name: 'row[]', value: 'b' }),
    ))];
    const changes = {
        aggiunta: (t) => t.form('rows').append(t.h('input', { name: 'row[]', value: 'c' })),
        tolta: (t) => t.form('r2').remove(),
        spostata: (t) => t.form('rows').insertBefore(t.form('r2'), t.form('r1')),
        disabilitata: (t) => { t.form('r1').disabled = true; },
    };
    Object.entries(changes).forEach(([name, change]) => {
        const t = started(rows);
        const [{ target, options }] = t.observers.mo[0].targets;
        assert.equal(target, t.form('f'));
        assert.deepEqual({ ...options, attributeFilter: [...options.attributeFilter] }, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'type', 'data-wi-save-bar-ignore'] });
        t.fire(t.doc.body, 'pointerdown');
        t.env.formData = 0;
        change(t);
        t.mutate(t.form('f'), [{ type: 'childList', target: t.form('rows'), addedNodes: [], removedNodes: [] }]);
        t.mutate(t.form('f'), [{ type: 'attributes', target: t.form('r1'), attributeName: 'disabled' }]);
        assert.equal(t.tick(), 1, name);
        assert.equal(t.env.formData, 1, name);
        assert.equal(t.api.isDirty(t.form('f')), true, name);
    });
});

test('U16 una scrittura nello stesso tick dell\'avvio finisce nel punto di partenza', () => {
    const t = newPage(oneForm);
    const title = t.form('title');
    t.start();
    title.value = 'Scritto da Quill';
    assert.equal(t.env.formData, 0, 'il punto di partenza si prende al frame dopo');
    t.tick();
    assert.equal(t.api.isDirty(t.form('f')), false);
    userTypes(t, title, 'Casa');
    t.tick();
    assert.equal(t.api.isDirty(t.form('f')), true, 'il punto di partenza ha il valore scritto all\'avvio');
});

test('U17 cosa chiude la fase prima dell\'interazione', () => {
    const cases = [
        ['keydown', true, (t, el) => t.fire(el, 'keydown')],
        ['pointerdown', true, (t, el) => t.fire(el, 'pointerdown')],
        ['mousedown', true, (t, el) => t.fire(el, 'mousedown')],
        ['touchstart', true, (t, el) => t.fire(el, 'touchstart')],
        ['paste', true, (t, el) => t.fire(el, 'paste')],
        ['drop', true, (t, el) => t.fire(el, 'drop')],
        ['input', true, (t, el) => t.fire(el, 'input')],
        ['click', true, (t, el) => t.fire(el, 'click')],
        ['el.click()', false, (t, el) => el.click()],
        ['trigger di jQuery', false, (t) => t.jq[0].handler({ type: 'change' })],
        ['dispatchEvent', false, (t, el) => t.fire(el, 'input', { isTrusted: false })],
        ['focus', false, (t, el) => { el.focus(); t.fire(el, 'focus', { bubbles: false }); t.fire(el, 'focusin'); }],
        ['scroll', false, (t) => t.fire(t.doc, 'scroll', { bubbles: false })],
        ['passaggio del mouse', false, (t, el) => ['pointerover', 'mouseover', 'mousemove'].forEach((type) => t.fire(el, type))],
    ];
    cases.forEach(([name, closes, act]) => {
        const t = started(oneForm);
        act(t, t.form('title'));
        t.form('title').value = 'Scritto da script';
        t.api.changed();
        t.tick();
        assert.equal(t.api.isDirty(t.form('f')), closes, name);
    });
    // Una scrittura senza eventi prima del primo tasto resta nel punto di partenza.
    const t = started(oneForm);
    t.form('title').value = 'Scritto senza eventi';
    t.fire(t.form('title'), 'keydown');
    t.api.changed();
    t.tick();
    assert.equal(t.api.isDirty(t.form('f')), false);
});

test('U18 dopo l\'interazione il confronto e\' rigido: IT, FR, IT', () => {
    const t = started((h) => [h('form', { id: 'f', 'data-wi-save-bar': true },
        h('select', { id: 'country', name: 'country', value: 'IT' }),
        h('input', { id: 'province', name: 'province', value: 'MI' }),
    )]);
    const country = t.form('country');
    t.fire(country, 'pointerdown');
    country.value = 'FR';
    t.fire(country, 'change');
    t.form('province').value = '';
    t.tick();
    country.value = 'IT';
    t.fire(country, 'change');
    t.tick();
    assert.equal(t.api.isDirty(t.form('f')), true);
});

test('U19 absorb: il contenitore diventa punto di partenza, il resto no', () => {
    const build = (h) => [
        h('form', { id: 'f', 'data-wi-save-bar': true },
            h('input', { id: 'a', name: 'a', value: '1' }),
            h('div', { id: 'b' }, h('input', { id: 'city', name: 'city', value: '' })),
        ),
        h('input', { id: 'linked', form: 'f', name: 'note' }),
        h('div', { id: 'outside' }, h('input', { name: 'x' })),
    ];
    const t = started(build);
    const city = t.form('city');
    userTypes(t, t.form('a'), '2');
    city.value = 'Roma';
    assert.equal(t.api.absorb(t.form('b')), true);
    t.tick();
    assert.equal(t.api.isDirty(t.form('f')), true, 'A resta sporco');
    userTypes(t, t.form('a'), '1');
    t.tick();
    assert.equal(t.api.isDirty(t.form('f')), false, 'B e\' nel punto di partenza');
    userTypes(t, city, 'Milano');
    t.tick();
    assert.equal(t.api.isDirty(t.form('f')), true, 'dopo l\'assorbimento B torna a contare');
    assert.equal(t.api.absorb(t.form('linked')), true, 'campo collegato con form="f"');
    assert.equal(t.api.absorb(t.form('outside')), false);
    // Il riempimento e' gia' stato contato da un segnale: absorb() ricalcola da solo.
    const late = started(build);
    late.fire(late.doc.body, 'pointerdown');
    late.form('city').value = 'Roma';
    late.mutate(late.form('f'), [{ type: 'childList', target: late.form('b'), addedNodes: [], removedNodes: [] }]);
    late.tick();
    assert.equal(late.api.isDirty(late.form('f')), true);
    late.api.absorb(late.form('b'));
    late.tick();
    assert.equal(late.api.isDirty(late.form('f')), false);
    late.api.absorb(late.form('outside'));
    assert.equal(late.frames.length, 0, 'fuori dai form tracciati nessun ricalcolo');
});

test('U20 un valore riportato a mano a quello iniziale torna pulito', () => {
    const t = started(oneForm);
    userTypes(t, t.form('title'), 'Villa');
    t.tick();
    assert.equal(t.api.isDirty(t.form('f')), true);
    userTypes(t, t.form('title'), 'Casa');
    t.tick();
    assert.equal(t.api.isDirty(t.form('f')), false);
});

test('U21 data-wi-save-bar-dirty: sporco fino a reset(), che prende il nuovo punto di partenza', () => {
    const t = newPage((h) => [h('form', { id: 'f', 'data-wi-save-bar': true, 'data-wi-save-bar-dirty': 'false' },
        h('input', { id: 'title', name: 'title', value: 'Casa' }),
    )]);
    const form = t.form('f');
    t.start();
    assert.equal(t.api.isDirty(form), true, 'conta la presenza, non il valore');
    t.tick();
    t.api.changed();
    t.tick();
    assert.equal(t.api.isDirty(form), true, 'istantanea invariata');
    userTypes(t, t.form('title'), 'Villa');
    t.env.formData = 0;
    assert.equal(t.api.reset(form), true);
    assert.equal(t.env.formData, 1, 'reset prende subito l\'istantanea');
    assert.equal(t.api.isDirty(form), false);
    t.tick();
    assert.equal(t.api.isDirty(form), false, 'Villa e\' il nuovo punto di partenza');
    assert.equal(form.hasAttribute('data-wi-save-bar-dirty'), true, 'l\'attributo resta nel DOM');
    userTypes(t, t.form('title'), 'Casa');
    t.tick();
    assert.equal(t.api.isDirty(form), true);
});

test('U22 changed() non forza lo stato', () => {
    const t = started(oneForm);
    const title = t.form('title');
    userTypes(t, title, 'Villa');
    t.tick();
    assert.equal(t.api.isDirty(t.form('f')), true);
    title.value = 'Casa';
    assert.equal(t.api.changed(title), true);
    t.tick();
    assert.equal(t.api.isDirty(t.form('f')), false);
});

test('U23 wi:save-bar:change solo ai passaggi pulito-sporco', () => {
    const t = newPage(twoForms);
    const [a, b] = [t.form('a'), t.form('b')];
    b.setAttribute('data-wi-save-bar-dirty', '');
    const [eventsA, eventsB, bubbled] = [t.events(a), t.events(b), []];
    t.doc.addEventListener('wi:save-bar:change', (event) => bubbled.push(event.target.id));
    t.start();
    t.tick();
    assert.deepEqual([eventsA, eventsB], [[], []], 'nessun evento all\'avvio, neanche per -dirty');
    userTypes(t, t.form('a_title'), 'Villa');
    t.tick();
    userTypes(t, t.form('a_title'), 'Villona');
    t.tick();
    assert.deepEqual(eventsA, [true], 'due ricalcoli sporchi, un evento');
    userTypes(t, t.form('a_title'), 'Casa a');
    t.tick();
    assert.deepEqual(eventsA, [true, false]);
    t.api.reset(b);
    t.api.reset(b);
    assert.deepEqual(eventsB, [false], 'anche reset(), una volta');
    assert.deepEqual(bubbled, ['a', 'a', 'b'], 'l\'evento sale fino al document');
});
```

- [ ] **Step 2: Lancia il test e verifica che fallisca**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-save-bar.test.cjs
```

Expected: FAIL con `FAIL U2 le funzioni su un form non tracciato o su null` e `TypeError: context.setUpSaveBar is not a function`: l'avvio non esiste ancora.

- [ ] **Step 3: Aggiungi `setUpSaveBar()` a `saveBar.js`**

Il ricalcolo prende l'istantanea di ogni form tracciato. Prima della prima interazione fidata ogni istantanea diventa il punto di partenza; dopo, il punto di partenza cambia solo con `absorb()` e `reset()`. `keydown`, `pointerdown` e gli altri eventi che arrivano prima del cambio di valore chiudono la fase e prendono subito il punto di partenza; `input` e `click` arrivano a valore già cambiato, quindi tengono quello dell'ultimo frame. I listener stanno in cattura su `document`, per non perdere gli eventi fermati da altri; `.trigger()` di jQuery passa dal delegato `$(document).on('input change')`. `stateOf()` usa `el.form` e `contains()` (che vale anche per il form stesso), così i campi con `form="id"` fuori dal form contano.

In fondo a `src/build/backend/js/form/saveBar.js`, dopo la riga 124 (la `}` che chiude `saveBarState`), aggiungi una riga vuota e poi:

```js
// L'avvio, una volta sola: chiamato da setUpPage() subito prima di loaded.
function setUpSaveBar() {

    if (setUpSaveBar.started) return;
    setUpSaveBar.started = true;

    var EXCLUDE = '.modal, [data-wi-save-bar-ignore]';
    var SIGNALS = ['input', 'change', 'click', 'keyup', 'FilePond:addfile', 'FilePond:removefile', 'FilePond:reorderfiles', 'FilePond:processfile', 'FilePond:processfilerevert', 'FilePond:updatefiles', 'wi-repeater-row-delete', 'wi-repeater-row-restore', 'wi:quick-create:created', 'autoNumeric:rawValueModified'];
    var INTERACTIONS = ['keydown', 'pointerdown', 'mousedown', 'touchstart', 'paste', 'drop', 'input', 'click'];
    var forms, interacted = false, recalcSoon = frameScheduler(recalc);

    var stateOf = el => el && forms.find(state => state.form === el.form || state.form.contains(el));

    function setDirty(state, dirty) {

        if (state.dirty === dirty) return;
        state.dirty = dirty;
        state.form.dispatchEvent(new CustomEvent('wi:save-bar:change', { bubbles: true, detail: { dirty: dirty } }));

    }

    function recalc() {

        forms.forEach(state => {
            var current = formSnapshot(state.form, EXCLUDE);
            // Prima dell'interazione ogni differenza diventa il punto di partenza; dopo, solo absorb().
            state.base = !state.base || !interacted ? current : state.absorb.reduce((base, el) => absorbSnapshot(base, current, el), state.base);
            state.absorb = [];
            setDirty(state, state.flag || !sameSnapshot(state.base, current));
        });

    }

    function onInteraction(event) {

        if (!event.isTrusted || interacted) return;
        interacted = true;
        // input e click arrivano a valore gia' cambiato: il punto di partenza resta quello dell'ultimo frame.
        if (event.type !== 'input' && event.type !== 'click') forms.forEach(state => state.base = formSnapshot(state.form, EXCLUDE));

    }

    try {

        if (!(window.IntersectionObserver && window.ResizeObserver && window.MutationObserver && 'inert' in HTMLElement.prototype)) return;
        forms = [...document.querySelectorAll('form[data-wi-save-bar]')].map(form => {
            var flag = form.hasAttribute('data-wi-save-bar-dirty');
            return { form: form, base: null, flag: flag, dirty: flag, absorb: [] };
        });
        if (!forms.length) return;

        // .trigger() di jQuery non arriva ad addEventListener.
        $(document).on('input change', recalcSoon);
        var observer = new MutationObserver(recalcSoon);
        forms.forEach(state => observer.observe(state.form, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'type', 'data-wi-save-bar-ignore'] }));
        SIGNALS.forEach(type => document.addEventListener(type, recalcSoon, true));
        INTERACTIONS.forEach(type => document.addEventListener(type, onInteraction, true));

        Object.assign(window.wiSaveBar, {
            changed: el => {
                recalcSoon();
                return !!stateOf(el);
            },
            reset: form => {
                var state = stateOf(form);
                if (!state) return false;
                state.flag = false;
                state.base = formSnapshot(state.form, EXCLUDE);
                state.absorb = [];
                setDirty(state, false);
                return true;
            },
            isDirty: form => {
                var state = stateOf(form);
                return !!(state && state.dirty);
            },
            absorb: el => {
                var state = stateOf(el);
                if (!state) return false;
                state.absorb.push(el);
                recalcSoon();
                return true;
            }
        });
        recalcSoon();

    } catch (error) {
        console.error(error);
    }

}
```

- [ ] **Step 4: Rilancia il test e verifica che fallisca solo l'ordine di `setUpPage()`**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-save-bar.test.cjs
```

Expected: FAIL con `FAIL U7 setUpPage avvia l'isola subito prima di loaded` e un `AssertionError` in cui all'ordine vero manca `'setUpSaveBar'` tra `'setUpJquery'` e `'loaded'`: `setUpPage()` non chiama ancora l'avvio.

- [ ] **Step 5: Chiama l'avvio da `setUpPage()` e carica il file nel bundle `head.js`**

In `src/build/backend/js/pageSetUp.js`, dentro `setUpPage()` (righe 118-120), sostituisci:

```js
    setUpJquery();

    window.dispatchEvent(new Event('loaded'));
```

con:

```js
    setUpJquery();
    setUpSaveBar();

    window.dispatchEvent(new Event('loaded'));
```

In `src/export/backend/head.js`, riga 26, sostituisci:

```js
import 'script-loader!/src/build/backend/js/form/set.js';
```

con:

```js
import 'script-loader!/src/build/backend/js/form/set.js';
import 'script-loader!/src/build/backend/js/form/saveBar.js';
```

`saveBar.js` sta in `head.js` come gli altri file del form, quindi `window.wiSaveBar` esiste prima degli script della pagina; `setUpPage()` sta in `body-end.js`.

- [ ] **Step 6: Rilancia test, catena e bundle**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-save-bar.test.cjs
npm test
O="$(mktemp -d)"
npx webpack --output-path "$O" 2>&1 | tail -1
git status --porcelain dist
node -e "
const assert = require('node:assert/strict');
const read = (file) => require('node:fs').readFileSync(process.argv[1] + '/backend/' + file, 'utf8');
assert.ok(read('head.js').includes('function setUpSaveBar()'), 'head.js senza saveBar.js');
assert.ok(read('body-end.js').includes('setUpSaveBar();'), 'body-end.js senza la chiamata');
console.log('bundle ok');
" "$O"
```

Expected: PASS con `backend-save-bar: 28 tests passed`; `npm test` finisce con la stessa riga; webpack stampa una riga con `compiled` (gli avvisi già presenti su `main` restano), `git status --porcelain dist` non stampa nulla e l'ultimo comando stampa `bundle ok`.

- [ ] **Step 7: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add src/build/backend/js/form/saveBar.js test/backend-save-bar.test.cjs src/build/backend/js/pageSetUp.js src/export/backend/head.js
git commit -m "feat(backend): avvio, ricalcolo e API dell'isola di salvataggio" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `4 files changed`.

### Task C3: isola, stati e form attivo

Repo: **lib**.

Spec sez. 3 A (form attivo), 3 E (stati), 3 L (`inert` e regione `role="status"`), 4 (Markup, Testi); test U4, U5, U24 e l'isola unica di U6. Qui nasce l'isola nel DOM: `render()` scrive `data-wi-state`, `inert` e la regione di stato secondo la tabella di C1, con i soli dati che esistono già (istantanea e sporco del form attivo). Bottoni e fascia arrivano in C4, l'invio in C5; fino ad allora `render()` passa `false` al posto di `submitting` e di "originale fuori dalla fascia".

**Files:**
- Modify: `src/build/backend/js/form/saveBar.js` (righe 126-127, 135, 154-157, 164-166, 175-179, 182, 194-196)
- Modify: `test/backend-save-bar.test.cjs` (righe 547-549 in U6; prima del commento di ancoraggio, riga 834)

**Interfaces:**
- Consumes:
  - da C1: `saveBarState(ready, submitting, dirty, outside)`, `window.wiSaveBar.labels`;
  - da C2: dentro `setUpSaveBar()` `forms`, `stateOf(el)`, `recalc()`, `recalcSoon`, `setDirty()`, `reset` di `window.wiSaveBar`; nel test `started()`, `newPage()` con `t.bar()`, `t.state()`, `t.fire()`, `t.tick()`.
- Produces:
  - `saveBarNode(tag, attrs, children) → Element`: globale, crea un elemento con gli attributi (`setAttribute`) e i figli (`append`, anche stringhe); C4 la usa per le copie;
  - il markup della sez. 4 in fondo a `<body>` (`.wi-save-bar` con `data-wi-state="hidden"`, `role="group"`, `aria-label`, `inert`; `.wi-save-bar-island`; `.wi-save-bar-label` col pallino `aria-hidden`; `.wi-save-bar-actions`; la sorella `.visually-hidden[role="status"]`) e `html.wi-save-bar-on`;
  - dentro la chiusura: `active` (lo stato del form attivo), `labels`, `bar`, `actions`, `status`, `render()` (chiamata alla fine di `recalc()` e da `reset()`), `onActivate(event)`; C4 e C5 cambiano la riga di `saveBarState(...)` dentro `render()`;
  - nel test: `plainForm(h, id, attrs)`, un form tracciato senza bottoni.

- [ ] **Step 1: Scrivi i test che falliscono**

U24 usa form senza bottoni, così lo stato dipende solo dal form attivo: `dirty-label` se è sporco, `hidden` se è pulito. Gli eventi da script (`isTrusted` false) non cambiano il form attivo. U24 controlla anche che la regione di stato non si riscriva a ogni ricalcolo: il setter di `textContent` del DOM finto sostituisce l'array `nodes`, quindi lo stesso array vuol dire nessuna scrittura (una riscrittura farebbe ripetere l'annuncio al lettore di schermo).

In `test/backend-save-bar.test.cjs`, subito prima della riga `// Esecuzione in ordine, anche dei test asincroni.` (riga 834), inserisci questo blocco, seguito da una riga vuota:

```js
// Un form tracciato senza bottoni.
const plainForm = (h, id, attrs = {}) => h('form', { id, 'data-wi-save-bar': true, ...attrs },
    h('input', { id: id + '_title', name: 'title', value: 'Casa ' + id }),
);

test('U4 i testi cambiati dal sito prima dell\'avvio', () => {
    const t = newPage((h) => [plainForm(h, 'f', { 'data-wi-save-bar-dirty': true })]);
    t.api.labels.group = 'Salvataggio';
    t.api.labels.unsaved = 'Modifiche da salvare';
    t.start();
    t.tick();
    assert.equal(t.bar().getAttribute('aria-label'), 'Salvataggio');
    assert.equal(t.bar().querySelector('.wi-save-bar-label').textContent, ' Modifiche da salvare');
    assert.equal(t.doc.querySelector('[role=status]').textContent, 'Modifiche da salvare');
});

test('U5 la struttura dell\'isola dopo l\'avvio', () => {
    const t = newPage((h) => [plainForm(h, 'f')]);
    t.start();
    const bar = t.bar();
    const [island] = bar.children;
    const [label, actions] = island.children;
    const status = t.doc.querySelector('[role=status]');
    assert.deepEqual(t.doc.body.children.slice(-2), [bar, status], 'in fondo al body, sorelle');
    assert.deepEqual([bar.className, bar.getAttribute('data-wi-state'), bar.getAttribute('role'), bar.getAttribute('aria-label'), bar.inert], ['wi-save-bar', 'hidden', 'group', 'Barra di salvataggio', true]);
    assert.deepEqual([island.className, island.hasAttribute('aria-hidden')], ['wi-save-bar-island', false]);
    assert.deepEqual([label.className, label.children[0].className, label.children[0].getAttribute('aria-hidden'), label.textContent], ['wi-save-bar-label', 'bi bi-circle-fill', 'true', ' Operazione non salvata']);
    assert.deepEqual([actions.className, actions.children.length], ['wi-save-bar-actions', 0]);
    assert.deepEqual([status.className, status.textContent, status.inert], ['visually-hidden', '', false]);
    assert.equal(t.doc.documentElement.className, 'wi-save-bar-on');
});

test('U24 il form attivo: il primo con -dirty all\'avvio, poi l\'ultimo toccato', () => {
    const t = newPage((h) => [plainForm(h, 'a'), plainForm(h, 'b', { 'data-wi-save-bar-dirty': true })]);
    const [a, b] = [t.form('a_title'), t.form('b_title')];
    const status = () => t.doc.querySelector('[role=status]');
    const view = () => [t.state(), t.bar().inert, t.bar().querySelector('.wi-save-bar-actions').inert, status().textContent];
    t.start();
    t.tick();
    assert.deepEqual(view(), ['dirty-label', false, true, 'Operazione non salvata'], 'all\'avvio b, con -dirty');
    const nodes = status().nodes;
    t.api.changed();
    t.tick();
    assert.equal(status().nodes, nodes, 'stesso testo, la regione non si riscrive');
    t.fire(a, 'pointerdown');
    t.tick();
    assert.deepEqual(view(), ['hidden', true, false, ''], 'pointerdown su a');
    t.fire(b, 'focusin');
    t.tick();
    assert.equal(t.state(), 'dirty-label', 'focusin su b');
    t.fire(a, 'input');
    t.tick();
    assert.equal(t.state(), 'hidden', 'input su a');
    ['focusin', 'pointerdown'].forEach((type) => t.fire(b, type, { isTrusted: false }));
    assert.equal(t.tick(), 0, 'gli eventi da script non cambiano il form attivo');
    t.fire(b, 'focusin');
    t.tick();
    assert.equal(t.api.reset(t.form('b')), true);
    assert.deepEqual(view(), ['hidden', true, false, ''], 'reset() aggiorna subito l\'isola');
    assert.equal(status().inert, false);
    // Senza form sporchi all'avvio e' attivo il primo.
    const u = started((h) => [plainForm(h, 'a'), plainForm(h, 'b')]);
    u.fire(u.doc.body, 'keydown');
    u.form('b_title').value = 'Villa';
    u.api.changed();
    u.tick();
    assert.deepEqual([u.api.isDirty(u.form('b')), u.state()], [true, 'hidden'], 'b sporco ma non attivo: niente etichetta');
    u.form('a_title').value = 'Villa';
    u.api.changed();
    u.tick();
    assert.equal(u.state(), 'dirty-label');
});
```

Poi, dentro U6 (righe 547-549), sostituisci:

```js
    t.start();
    t.start();
    assert.deepEqual([t.jq.length, t.observers.mo.length], [1, 1]);
```

con:

```js
    t.start();
    t.start();
    assert.deepEqual([t.jq.length, t.observers.mo.length], [1, 1]);
    assert.deepEqual([t.doc.querySelectorAll('.wi-save-bar').length, t.doc.querySelectorAll('[role=status]').length], [1, 1], 'una sola isola');
```

- [ ] **Step 2: Lancia il test e verifica che fallisca**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-save-bar.test.cjs
```

Expected: FAIL con `FAIL U6 avvio doppio: un solo ricalcolo per segnale` e `AssertionError [ERR_ASSERTION]: una sola isola` (trovate `[0, 0]` invece di `[1, 1]`): l'isola non esiste ancora.

- [ ] **Step 3: Aggiungi form attivo, `render()` e isola a `setUpSaveBar()`**

`render()` scrive `data-wi-state` e `inert` solo quando lo stato cambia: tutta l'isola è `inert` in `hidden` e `submitting`, solo le azioni in `dirty-label`. La regione di stato segue l'etichetta visibile, quindi il form attivo: `labels.unsaved` negli stati `dirty-*`, vuota negli altri, riscritta solo se il testo cambia. `onActivate()` accetta solo eventi fidati e programma il ricalcolo, che finisce con `render()`: un cambio di form attivo non costa una FormData in più nello stesso frame (U11 resta a un solo frame). `reset()` chiama `render()` subito, così l'isola è già aggiornata quando il chiamante prosegue con `form.submit()`. L'isola si crea dopo `$(document).on()`: se jQuery lancia un errore non resta un'isola orfana (U8).

Le sostituzioni vanno dal basso verso l'alto, così i numeri di riga restano quelli del file alla fine di C2. In `src/build/backend/js/form/saveBar.js`, dentro `reset` (righe 194-196), sostituisci:

```js
                state.absorb = [];
                setDirty(state, false);
                return true;
```

con:

```js
                state.absorb = [];
                setDirty(state, false);
                render();
                return true;
```

Riga 182, sostituisci:

```js
        INTERACTIONS.forEach(type => document.addEventListener(type, onInteraction, true));
```

con:

```js
        INTERACTIONS.forEach(type => document.addEventListener(type, onInteraction, true));
        ['focusin', 'pointerdown', 'input'].forEach(type => document.addEventListener(type, onActivate, true));
```

Nel `try` (righe 175-179), sostituisci:

```js
        if (!forms.length) return;

        // .trigger() di jQuery non arriva ad addEventListener.
        $(document).on('input change', recalcSoon);
        var observer = new MutationObserver(recalcSoon);
```

con:

```js
        if (!forms.length) return;
        active = forms.find(state => state.flag) || forms[0];

        // .trigger() di jQuery non arriva ad addEventListener.
        $(document).on('input change', recalcSoon);

        // I testi si leggono adesso: il sito puo' averli cambiati dopo il caricamento.
        labels = window.wiSaveBar.labels;
        actions = saveBarNode('div', { class: 'wi-save-bar-actions' });
        bar = saveBarNode('div', { class: 'wi-save-bar', 'data-wi-state': 'hidden', role: 'group', 'aria-label': labels.group, inert: '' }, [
            saveBarNode('div', { class: 'wi-save-bar-island' }, [
                saveBarNode('span', { class: 'wi-save-bar-label' }, [saveBarNode('i', { class: 'bi bi-circle-fill', 'aria-hidden': 'true' }), ' ' + labels.unsaved]),
                actions
            ])
        ]);
        status = saveBarNode('div', { class: 'visually-hidden', role: 'status' });
        document.body.append(bar, status);
        document.documentElement.classList.add('wi-save-bar-on');

        var observer = new MutationObserver(recalcSoon);
```

In fondo a `onInteraction()` (righe 164-166), sostituisci:

```js
        if (event.type !== 'input' && event.type !== 'click') forms.forEach(state => state.base = formSnapshot(state.form, EXCLUDE));

    }
```

con:

```js
        if (event.type !== 'input' && event.type !== 'click') forms.forEach(state => state.base = formSnapshot(state.form, EXCLUDE));

    }

    // Stato, inert e regione status seguono il form attivo.
    function render() {

        var state = saveBarState(!!active.base, false, active.dirty, false);
        var text = state.startsWith('dirty') ? labels.unsaved : '';

        if (bar.getAttribute('data-wi-state') !== state) {
            bar.setAttribute('data-wi-state', state);
            bar.inert = state === 'hidden' || state === 'submitting';
            actions.inert = state === 'dirty-label';
        }
        if (status.textContent !== text) status.textContent = text;

    }

    // Il form attivo e' l'ultimo toccato dall'utente.
    function onActivate(event) {

        var state = event.isTrusted && stateOf(event.target);

        if (!state || state === active) return;
        active = state;
        recalcSoon();

    }
```

In fondo a `recalc()` (righe 154-157), sostituisci:

```js
            setDirty(state, state.flag || !sameSnapshot(state.base, current));
        });

    }
```

con:

```js
            setDirty(state, state.flag || !sameSnapshot(state.base, current));
        });
        render();

    }
```

Riga 135, sostituisci:

```js
    var forms, interacted = false, recalcSoon = frameScheduler(recalc);
```

con:

```js
    var forms, active, labels, bar, actions, status, interacted = false, recalcSoon = frameScheduler(recalc);
```

- [ ] **Step 4: Aggiungi `saveBarNode()` prima dell'avvio**

Nello stesso file, righe 126-127, sostituisci:

```js
// L'avvio, una volta sola: chiamato da setUpPage() subito prima di loaded.
function setUpSaveBar() {
```

con:

```js
// Un elemento con attributi e figli.
function saveBarNode(tag, attrs, children) {

    var el = document.createElement(tag);

    Object.keys(attrs).forEach(name => el.setAttribute(name, attrs[name]));
    el.append(...(children || []));

    return el;

}

// L'avvio, una volta sola: chiamato da setUpPage() subito prima di loaded.
function setUpSaveBar() {
```

- [ ] **Step 5: Rilancia il test e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-save-bar.test.cjs
npm test
```

Expected: PASS con `backend-save-bar: 31 tests passed`; `npm test` finisce con la stessa riga.

- [ ] **Step 6: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add src/build/backend/js/form/saveBar.js test/backend-save-bar.test.cjs
git commit -m "feat(backend): isola di salvataggio, stati e form attivo" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `2 files changed`.

### Task C4: copie dei bottoni, fascia e visibilità degli originali

Repo: **lib**.

Spec sez. 3 B (quali bottoni), 3 C (la copia), 3 D (fascia e visibilità), la riga "ready" di 3 A e le righe senza invio della tabella di 3 E; test U15, U25, U26, U27 (tranne la riga `submitting`, che è di C5) e U28. Qui l'isola riceve le copie dei bottoni del form attivo e `render()` sa se un originale è fuori dalla zona utile: le due informazioni che in C3 erano `false`, tranne `submitting`.

**Files:**
- Modify: `src/build/backend/js/form/saveBar.js` (righe 138-139, 144, 147, 166-168, 184, 207-209, 233)
- Modify: `test/backend-save-bar.test.cjs` (riga 321 in `newPage()`; prima del commento di ancoraggio, riga 908)

**Interfaces:**
- Consumes:
  - da C1: `saveBarState(ready, submitting, dirty, outside)`, `frameScheduler(fn)`;
  - da C3: `saveBarNode(tag, attrs, children)`; dentro `setUpSaveBar()` `active`, `actions`, `recalc()`, `recalcSoon`, `render()`, l'osservatore del form e il blocco dell'avvio; nel test `newPage()`, `started()`, `oneForm`, `twoForms`, `plainForm()`, `userTypes()`, `SKIP`, `t.observers`, `t.intersect()`, `t.resize()`, `t.copies()`, `t.env.band`, `t.env.ioThrows`, `t.doc.closestCalls`, `t.doc.submits`.
- Produces:
  - `proxyButton(original) → HTMLButtonElement`: globale, una copia `<button type="button">` nuova, senza attributi dell'originale;
  - `syncProxy(original, copy)`: globale, riallinea etichetta (riscritta solo se cambia), classi `btn*` (tranne `btn-check`) più `disabled` e `wi-save-bar-btn`, e `disabled` (`disabled`, classe `disabled` o `aria-disabled="true"`);
  - dentro la chiusura: `copies` (Map originale → `{ copy, observer }`), `hits`, `listed`, `io`, `ro`, `topbarHeight`, `band`, `seen`, `renderSoon`, `onIntersect()`, `readBand()`, le costanti `SKIP`, `BUTTONS`, `SCROLL`; il clic della copia `() => original.click()`, che C5 protegge durante l'invio; in `render()` la riga `saveBarState(!!active.base && (seen || !copies.size), false, active.dirty, outside)`, dove C5 sostituisce il secondo argomento;
  - nel test: `box(top, bottom)`, `withLabels`, `ids(list)`; `t.mutate()` completa i record con `attributeName: null`, `addedNodes: []`, `removedNodes: []`, come un `MutationRecord` vero.

- [ ] **Step 1: Scrivi i test che falliscono**

I test coprono la copia da sola (`proxyButton`/`syncProxy`), l'elenco del form attivo (ordine di pagina, esclusi modal, offcanvas e ignore, compreso il submit esterno con `form="f"`), le copie che restano gli stessi nodi quando l'elenco si rifà, U15 (solo le mutazioni che toccano un bottone o gli attributi type e ignore rifanno l'elenco: si contano le `closest(SKIP)` e le `observe()`), la fascia letta dal token (U25), la tolleranza di 1px (U26), gli stati sulla pagina (U27) e l'attesa della prima callback di IO (U28). Il DOM finto non interpreta `innerHTML`: la pulizia di `id` e `data-*` nei figli dell'etichetta la prova B22.

In `test/backend-save-bar.test.cjs`, subito prima della riga `// Esecuzione in ordine, anche dei test asincroni.` (riga 908), inserisci questo blocco, seguito da una riga vuota:

```js
// Un rettangolo con i bordi verticali dati.
const box = (top, bottom) => ({ top, bottom, left: 0, right: 100, width: 100, height: bottom - top });

// Nel DOM finto innerHTML non segue i figli: i bottoni lo prendono dal testo.
const withLabels = { before: (context) => context.document.querySelectorAll('button').forEach((el) => { el.innerHTML = el.textContent; }) };

// Gli osservati, senza contare l'ordine delle chiamate a observe().
const ids = (list) => list.map((el) => el.id).sort();

test('proxyButton e syncProxy: una copia pulita che segue l\'originale', () => {
    const t = newPage((h) => [h('form', { id: 'f' },
        h('button', { id: 'save', name: 'upload', value: '1', form: 'f', formaction: '/x', formmethod: 'post', formtarget: '_self', formnovalidate: true, onclick: 'count()', 'data-bs-toggle': 'modal', 'data-wi-qc-endpoint': '/qc', class: 'btn btn-primary wi-submit btn-check disabled' }),
        h('input', { id: 'add', type: 'submit', value: 'Salva & <aggiungi>', class: 'btn btn-outline-dark' }),
    )]);
    const { proxyButton, syncProxy } = t.context;
    const save = t.form('save');
    save.innerHTML = '<i class="bi bi-check"></i> Salva';
    const copy = proxyButton(save);
    assert.deepEqual([copy.tagName, [...copy.attrs.keys()].sort()], ['BUTTON', ['class', 'disabled', 'type']], 'nessun attributo dell\'originale');
    assert.deepEqual([copy.type, copy.className, copy.disabled, copy.innerHTML], ['button', 'btn btn-primary disabled wi-save-bar-btn', true, '<i class="bi bi-check"></i> Salva']);
    // Il setter conta le scritture: l'etichetta si riscrive solo se cambia.
    let writes = 0;
    let html = copy.innerHTML;
    Object.defineProperty(copy, 'innerHTML', { get: () => html, set: (value) => { writes++; html = value; } });
    syncProxy(save, copy);
    assert.equal(writes, 0, 'stessa etichetta, nessuna riscrittura');
    save.innerHTML = 'Salvataggio';
    save.className = 'btn btn-success';
    syncProxy(save, copy);
    assert.deepEqual([writes, copy.innerHTML, copy.className, copy.disabled], [1, 'Salvataggio', 'btn btn-success wi-save-bar-btn', false]);
    [[{}, false], [{ disabled: true }, true], [{ class: 'btn disabled' }, true], [{ 'aria-disabled': 'true' }, true], [{ 'aria-disabled': 'false' }, false]].forEach(([attrs, disabled]) => {
        assert.equal(proxyButton(t.h('button', { class: 'btn', ...attrs })).disabled, disabled, JSON.stringify(attrs));
    });
    const add = proxyButton(t.form('add'));
    assert.deepEqual([add.innerHTML, add.className], ['Salva &amp; &lt;aggiungi&gt;', 'btn btn-outline-dark wi-save-bar-btn'], 'il value dell\'input come testo');
});

test('le copie: i submit del form attivo in ordine di pagina, sempre gli stessi nodi', () => {
    const t = started((h) => [
        h('form', { id: 'f', 'data-wi-save-bar': true },
            h('input', { id: 'title', name: 'title', value: 'Casa' }),
            h('div', { class: 'modal' }, h('button', { id: 'qc' }, 'Salva nel modal')),
            h('div', { class: 'offcanvas' }, h('button', { id: 'oc' }, 'Salva nel menu')),
            h('button', { id: 'ign', 'data-wi-save-bar-ignore': true }, 'Ignorato'),
            h('button', { id: 'save', name: 'upload', class: 'btn btn-primary' }, 'Salva'),
            h('button', { id: 'undo', type: 'reset' }, 'Annulla'),
            h('button', { id: 'ajax', type: 'button', class: 'btn wi-submit' }, 'Salva AJAX'),
            h('input', { id: 'add', type: 'submit', name: 'upload-add', value: 'Salva e aggiungi' }),
        ),
        h('button', { id: 'outer', form: 'f' }, 'Fuori dal form'),
    ], withLabels);
    const [io] = t.observers.io;
    const [ro] = t.observers.ro;
    const labels = () => t.copies().map((copy) => copy.innerHTML);
    const [save, add] = [t.form('save'), t.form('add')];
    assert.deepEqual(labels(), ['Salva', 'Salva AJAX', 'Salva e aggiungi', 'Fuori dal form']);
    assert.deepEqual([ids(io.targets), ids(ro.targets)], [['add', 'ajax', 'outer', 'save'], ['add', 'ajax', 'outer', 'save']]);
    const saveObserver = t.observers.mo.find((mo) => mo.targets.some((item) => item.target === save));
    const watch = saveObserver.targets[0].options;
    assert.deepEqual({ ...watch, attributeFilter: [...watch.attributeFilter] }, { attributes: true, attributeFilter: ['disabled', 'aria-disabled', 'class', 'type', 'hidden', 'data-wi-save-bar-ignore'], characterData: true, childList: true, subtree: true });
    // Click sulla copia: l'invio parte dal bottone vero.
    t.copies()[2].click();
    assert.deepEqual(t.doc.submits.map((submit) => [submit.form.id, submit.submitter.id]), [['f', 'add']]);
    // L'originale cambia: la copia si aggiorna subito e resta lo stesso nodo.
    const first = t.copies();
    save.setAttribute('aria-disabled', 'true');
    save.innerHTML = 'Salva ora';
    t.mutate(save, [{ type: 'attributes', target: save, attributeName: 'aria-disabled' }]);
    assert.deepEqual([first[0].disabled, first[0].innerHTML], [true, 'Salva ora']);
    // Un originale non visualizzato: copia nascosta, nessuna nuova ricerca.
    t.form('outer').shown = false;
    t.resize(t.form('outer'));
    assert.deepEqual(t.copies().map((copy) => copy.hidden), [false, false, false, true]);
    // type sull'originale rifa' l'elenco: via la copia di save, le altre restano.
    save.type = 'button';
    t.mutate(save, [{ type: 'attributes', target: save, attributeName: 'type' }]);
    t.tick();
    assert.deepEqual(t.copies(), first.slice(1));
    assert.deepEqual([ids(io.targets), ids(ro.targets), saveObserver.disconnected], [['add', 'ajax', 'outer'], ['add', 'ajax', 'outer'], true]);
    // Il submit collegato con form="f" sta fuori dal form: l'ignore lo vede solo il suo osservatore.
    const outer = t.form('outer');
    outer.setAttribute('data-wi-save-bar-ignore', '');
    t.mutate(outer, [{ type: 'attributes', target: outer, attributeName: 'data-wi-save-bar-ignore' }]);
    t.tick();
    assert.deepEqual(t.copies(), [first[1], first[2]]);
    // Dentro il form l'ignore lo vede l'osservatore del form, anche quando si toglie.
    add.setAttribute('data-wi-save-bar-ignore', '');
    t.mutate(t.form('f'), [{ type: 'attributes', target: add, attributeName: 'data-wi-save-bar-ignore' }]);
    t.tick();
    assert.deepEqual(t.copies(), [first[1]]);
    add.removeAttribute('data-wi-save-bar-ignore');
    t.mutate(t.form('f'), [{ type: 'attributes', target: add, attributeName: 'data-wi-save-bar-ignore' }]);
    t.tick();
    assert.deepEqual(labels(), ['Salva AJAX', 'Salva e aggiungi'], 'attributo tolto: la copia torna, al suo posto');
    assert.equal(t.copies()[0], first[1]);
    // type rimesso a submit: save non ha piu' il suo osservatore, lo vede quello del form.
    const before = t.copies();
    save.type = 'submit';
    t.mutate(t.form('f'), [{ type: 'attributes', target: save, attributeName: 'type' }]);
    t.tick();
    assert.deepEqual(labels(), ['Salva ora', 'Salva AJAX', 'Salva e aggiungi'], 'type rimesso: la copia torna, al suo posto');
    assert.deepEqual(t.copies().slice(1), before);
});

test('le copie seguono il form attivo', () => {
    const t = started(twoForms, withLabels);
    const [io] = t.observers.io;
    assert.deepEqual(io.targets, [t.form('a_save')]);
    t.fire(t.form('b_title'), 'pointerdown');
    t.tick();
    assert.deepEqual([t.copies().length, io.targets], [1, [t.form('b_save')]]);
    t.copies()[0].click();
    assert.deepEqual(t.doc.submits.map((submit) => submit.submitter.id), ['b_save']);
});

test('U15 solo le mutazioni che toccano i bottoni rifanno l\'elenco', () => {
    const t = started(oneForm);
    const form = t.form('f');
    const save = t.form('save');
    const searches = () => t.doc.closestCalls.filter((selector) => selector === SKIP).length;
    const observed = () => [t.observers.io[0].observed.length, t.observers.ro[0].observed.length];
    const before = [searches(), ...observed()];
    const row = t.h('div', { class: 'row' }, t.h('input', { name: 'note' }));
    form.append(row);
    t.mutate(form, [{ type: 'childList', target: form, addedNodes: [row], removedNodes: [] }]);
    t.mutate(form, [{ type: 'attributes', target: t.form('title'), attributeName: 'disabled' }]);
    row.remove();
    t.mutate(form, [{ type: 'childList', target: form, addedNodes: [], removedNodes: [row] }]);
    save.className = 'btn btn-primary';
    t.mutate(save, [{ type: 'attributes', target: save, attributeName: 'class' }]);
    t.mutate(save, [{ type: 'characterData', target: save }]);
    assert.equal(t.tick(), 1, 'un solo ricalcolo');
    assert.deepEqual([searches(), ...observed()], before, 'nessuna nuova ricerca e nessun observe');
    assert.equal(t.copies()[0].className, 'btn btn-primary wi-save-bar-btn', 'la copia segue la classe');
    // Un submit dentro un contenitore aggiunto: una ricerca, una closest per candidato.
    const copy = t.copies()[0];
    const wrap = t.h('div', {}, t.h('input', { id: 'more', type: 'submit', value: 'Altro' }));
    form.append(wrap);
    t.mutate(form, [{ type: 'childList', target: form, addedNodes: [wrap] }]);
    t.tick();
    const grown = () => [searches() - before[0], observed()[0] - before[1], observed()[1] - before[2]];
    assert.deepEqual(grown(), [2, 1, 1]);
    // Un bottone e un campo aggiunti nello stesso frame: una sola ricerca.
    const add = t.h('button', { id: 'add', name: 'upload-add' }, 'Salva e aggiungi');
    const code = t.h('input', { name: 'code' });
    form.append(add, code);
    t.mutate(form, [{ type: 'childList', target: form, addedNodes: [add] }]);
    t.mutate(form, [{ type: 'childList', target: form, addedNodes: [code] }]);
    assert.equal(t.tick(), 1);
    assert.deepEqual(grown(), [5, 2, 2]);
    assert.equal(t.copies()[0], copy, 'la copia di save resta lo stesso nodo');
    // hidden sull'originale rifa' l'elenco; la copia la nasconde poi il ResizeObserver.
    save.hidden = true;
    t.mutate(save, [{ type: 'attributes', target: save, attributeName: 'hidden' }]);
    t.tick();
    assert.deepEqual(grown(), [8, 2, 2]);
    // L'attributo ignore sul form rifa' l'elenco anche senza bottoni nella mutazione.
    wrap.setAttribute('data-wi-save-bar-ignore', '');
    t.mutate(form, [{ type: 'attributes', target: wrap, attributeName: 'data-wi-save-bar-ignore' }]);
    t.tick();
    assert.equal(t.copies().length, 2);
});

test('U25 la fascia dal token CSS, con ripiego a 96', () => {
    const cases = [['96px', '96'], ['120.5px', '120.5'], ['6rem', '96'], ['', '96'], ['abc', '96'], ['-5px', '96'], [' 100px ', '100']];
    cases.forEach(([band, size]) => {
        const t = newPage((h) => [h('div', { id: 'topbar', offsetHeight: 50 }), ...oneForm(h)]);
        t.env.band = band;
        t.start();
        const [{ options }] = t.observers.io;
        assert.deepEqual([[...options.threshold], options.rootMargin], [[0], '-50px 0px -' + size + 'px 0px'], JSON.stringify(band));
    });
    const tall = newPage((h) => [h('div', { id: 'topbar', offsetHeight: 64 }), ...oneForm(h)]);
    tall.start();
    assert.equal(tall.observers.io[0].options.rootMargin, '-64px 0px -96px 0px', 'topbar piu\' alta');
    // Il costruttore lancia: si riprova con 96, e 96 vale anche per la misura.
    const t = newPage(oneForm);
    const save = t.form('save');
    t.env.band = '120.5px';
    t.env.ioThrows = (init) => init.rootMargin.includes('120.5');
    t.start();
    t.tick();
    assert.deepEqual(t.observers.io.map((io) => io.options.rootMargin), ['-50px 0px -96px 0px'], 'senza topbar vale 50');
    save.rect = box(100, 700);
    t.intersect(save, true);
    t.tick();
    assert.equal(t.state(), 'hidden', 'bottom 700 sta sopra la fascia da 96');
    // Il resize rilegge la fascia: nuovo IO solo se cambia.
    t.env.ioThrows = null;
    t.env.band = '120px';
    t.fire(t.win, 'resize');
    t.fire(t.win, 'resize');
    assert.equal(t.tick(), 1, 'resize raggruppati nel rAF');
    const [old, io] = t.observers.io;
    assert.deepEqual([old.disconnected, io.options.rootMargin, io.targets], [true, '-50px 0px -120px 0px', [save]]);
    t.intersect(save, true);
    t.tick();
    assert.equal(t.state(), 'buttons', 'con la fascia da 120 bottom 700 ci finisce dentro');
    t.fire(t.win, 'resize');
    t.tick();
    assert.equal(t.observers.io.length, 2, 'fascia uguale, stesso IO');
});

test('U26 visibile: tutto tra la topbar e la fascia, con 1px di tolleranza', () => {
    const t = started(oneForm);
    const save = t.form('save');
    const at = (top, bottom) => {
        save.rect = box(top, bottom);
        t.intersect(save, true);
        t.tick();
        return t.state();
    };
    assert.equal(at(49, 800 - 96 + 1), 'hidden');
    assert.equal(at(49, 800 - 96 + 3), 'buttons', 'bottom 2px piu\' in basso');
    assert.equal(at(47, 100), 'buttons', 'sotto la topbar');
    // Un originale nuovo, prima della sua callback di IO, si misura subito.
    assert.equal(at(100, 140), 'hidden');
    const add = t.h('button', { id: 'add', rect: box(200, 240) }, 'Salva e aggiungi');
    t.form('f').append(add);
    t.mutate(t.form('f'), [{ type: 'childList', target: t.form('f'), addedNodes: [add] }]);
    t.tick();
    assert.deepEqual([t.copies().length, t.state()], [2, 'hidden']);
    add.rect = box(750, 790);
    t.fire(t.win, 'resize');
    t.tick();
    assert.equal(t.state(), 'buttons');
});

test('U27 la tabella degli stati sulla pagina', () => {
    const t = started(oneForm);
    const save = t.form('save');
    const view = () => [t.state(), t.bar().inert, t.bar().querySelector('.wi-save-bar-actions').inert];
    const scroll = (rect) => {
        save.rect = rect;
        t.fire(t.win, 'scroll', { bubbles: false });
        t.tick();
        return view();
    };
    save.rect = box(100, 140);
    t.intersect(save, true);
    t.tick();
    assert.deepEqual(view(), ['hidden', true, false], 'pulito, originale visibile');
    assert.equal(t.win.has('scroll'), true, 'originale nella zona: si misura a ogni scroll');
    assert.deepEqual(scroll(box(700, 740)), ['buttons', false, false], 'pulito, originale nella fascia');
    userTypes(t, t.form('title'), 'Villa');
    t.tick();
    assert.deepEqual(view(), ['dirty-buttons', false, false], 'sporco, originale nella fascia');
    assert.deepEqual(scroll(box(100, 140)), ['dirty-label', false, true], 'sporco, originale visibile');
    save.rect = box(900, 940);
    t.intersect(save, false);
    t.tick();
    assert.deepEqual([...view(), t.win.has('scroll')], ['dirty-buttons', false, false, false], 'sporco, originale fuori dallo schermo: niente scroll');
    save.shown = false;
    t.resize(save);
    t.tick();
    assert.equal(t.state(), 'dirty-label', 'sporco, zero bottoni visualizzati');
    t.api.reset(t.form('f'));
    assert.equal(t.state(), 'hidden', 'pulito, zero bottoni visualizzati');
    // Form attivo pulito e un altro sporco: nessuna etichetta.
    const u = started(twoForms);
    const aSave = u.form('a_save');
    u.fire(u.doc.body, 'keydown');
    u.form('b_title').value = 'Villa';
    u.api.changed();
    aSave.rect = box(700, 740);
    u.intersect(aSave, true);
    u.tick();
    assert.deepEqual([u.api.isDirty(u.form('b')), u.state()], [true, 'buttons']);
    aSave.rect = box(100, 140);
    u.fire(u.win, 'scroll', { bubbles: false });
    u.tick();
    assert.equal(u.state(), 'hidden');
});

test('U28 hidden finche\' non arrivano la prima istantanea e la prima callback di IO', () => {
    const t = newPage((h) => [h('form', { id: 'f', 'data-wi-save-bar': true, 'data-wi-save-bar-dirty': true },
        h('input', { name: 'title', value: 'Casa' }),
        h('button', { id: 'save' }, 'Salva'),
    )]);
    t.start();
    assert.equal(t.state(), 'hidden', 'all\'avvio');
    t.tick();
    assert.deepEqual([t.state(), t.doc.querySelector('[role=status]').textContent], ['hidden', ''], 'istantanea senza IO');
    t.intersect(t.form('save'), false);
    t.tick();
    assert.deepEqual([t.state(), t.doc.querySelector('[role=status]').textContent], ['dirty-buttons', 'Operazione non salvata']);
    // Senza bottoni basta l'istantanea.
    const u = newPage((h) => [plainForm(h, 'p', { 'data-wi-save-bar-dirty': true })]);
    u.start();
    u.tick();
    assert.equal(u.state(), 'dirty-label');
});
```

Poi, dentro `newPage()` (riga 321), sostituisci:

```js
        mutate: (target, records) => observers.mo.filter((mo) => mo.targets.some((item) => item.target === target)).forEach((mo) => mo.callback(records, mo)),
```

con:

```js
        mutate: (target, records) => observers.mo.filter((mo) => mo.targets.some((item) => item.target === target)).forEach((mo) => mo.callback(records.map((record) => ({ attributeName: null, addedNodes: [], removedNodes: [], ...record })), mo)),
```

- [ ] **Step 2: Lancia il test e verifica che fallisca**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-save-bar.test.cjs
```

Expected: FAIL con `FAIL proxyButton e syncProxy: una copia pulita che segue l'originale` e `TypeError: proxyButton is not a function`.

- [ ] **Step 3: Aggiungi elenco dei bottoni, fascia e misura a `setUpSaveBar()`**

L'elenco si rifà dentro `recalc()` solo quando `listed` non è il form attivo: lo azzerano il cambio di form attivo, l'osservatore del form (nodi aggiunti o tolti che sono o contengono un bottone, attributi `type` e ignore) e l'osservatore di ogni originale (`type`, `hidden`, ignore). Le altre mutazioni dell'originale chiamano solo `syncProxy()`. Le copie esistenti non si ricreano e non si spostano: si scorre l'elenco al contrario e le nuove entrano con `insertBefore()` davanti alla copia che segue. Una copia di un originale non visualizzato (ResizeObserver: `checkVisibility()`, altrimenti `getClientRects()`) prende `hidden` e non conta per la fascia.

IO usa `threshold: [0]` e dice solo quali originali toccano la zona tra la topbar e la fascia; finché almeno uno la tocca, un listener `scroll` (capture e passive) ricalcola lo stato a ogni frame di scroll. `render()` misura gli originali che toccano la zona e quelli che IO non ha ancora riportato; lo stato esce da `hidden` solo dopo la prima callback di IO, o subito se il form non ha bottoni. La fascia viene da `--wi-save-bar-band` (solo `Npx`, altrimenti 96) e la topbar da `#topbar.offsetHeight` (50 se manca); al resize si rileggono e IO si ricrea solo se cambiano.

Le sostituzioni vanno dal basso verso l'alto, così i numeri di riga restano quelli del file alla fine di C3. In `src/build/backend/js/form/saveBar.js`, nell'avvio (riga 233), sostituisci:

```js
        var observer = new MutationObserver(recalcSoon);
```

con:

```js
        ro = new ResizeObserver(entries => {
            entries.forEach(entry => {
                var item = copies.get(entry.target);
                if (item) item.copy.hidden = !(entry.target.checkVisibility ? entry.target.checkVisibility() : entry.target.getClientRects().length > 0);
            });
            renderSoon();
        });
        readBand();
        window.addEventListener('resize', frameScheduler(() => {
            readBand();
            render();
        }));

        // Sempre un ricalcolo; l'elenco dei bottoni solo se la mutazione tocca un bottone o gli attributi type e ignore.
        var observer = new MutationObserver(records => {
            if (records.some(record => ['type', 'data-wi-save-bar-ignore'].includes(record.attributeName) || [...record.addedNodes, ...record.removedNodes].some(node => node.nodeType === 1 && (node.matches(BUTTONS) || node.querySelector(BUTTONS))))) listed = null;
            recalcSoon();
        });
```

Prima del `try` (righe 207-209), sostituisci:

```js
    try {

        if (!(window.IntersectionObserver && window.ResizeObserver && window.MutationObserver && 'inert' in HTMLElement.prototype)) return;
```

con:

```js
    // IO dice solo chi tocca la zona tra topbar e fascia: finche' qualcuno la tocca si misura a ogni scroll.
    function onIntersect(entries) {

        entries.forEach(entry => {
            if (copies.has(entry.target)) hits.set(entry.target, entry.isIntersecting);
        });
        seen = true;
        if ([...hits.values()].includes(true)) window.addEventListener('scroll', renderSoon, SCROLL);
        else window.removeEventListener('scroll', renderSoon, SCROLL);
        renderSoon();

    }

    // La fascia dal token CSS e l'altezza della topbar: IO nuovo solo se cambiano.
    function readBand() {

        var match = getComputedStyle(document.documentElement).getPropertyValue('--wi-save-bar-band').trim().match(/^(\d+(?:\.\d+)?)px$/);
        var topbar = document.getElementById('topbar');
        var top = topbar ? topbar.offsetHeight : 50;
        var size = match ? +match[1] : 96;
        // threshold 1 si blocca a 0,99 con DPR frazionari
        var create = () => new IntersectionObserver(onIntersect, { threshold: [0], rootMargin: '-' + top + 'px 0px -' + band + 'px 0px' });

        if (io && top === topbarHeight && size === band) return;
        if (io) io.disconnect();
        topbarHeight = top;
        band = size;
        try {
            io = create();
        } catch (error) {
            band = 96;
            io = create();
        }
        copies.forEach((item, original) => io.observe(original));

    }

    try {

        if (!(window.IntersectionObserver && window.ResizeObserver && window.MutationObserver && 'inert' in HTMLElement.prototype)) return;
```

In `render()` (riga 184), sostituisci:

```js
        var state = saveBarState(!!active.base, false, active.dirty, false);
```

con:

```js
        // Un originale non ancora visto da IO si misura subito.
        var outside = [...copies].some(([original, item]) => {
            if (item.copy.hidden) return false;
            var rect = hits.get(original) !== false && original.getBoundingClientRect();
            return !(rect && rect.top >= topbarHeight - 1 && rect.bottom <= window.innerHeight - band + 1);
        });
        var state = saveBarState(!!active.base && (seen || !copies.size), false, active.dirty, outside);
```

In fondo a `recalc()` (righe 166-168), sostituisci:

```js
            setDirty(state, state.flag || !sameSnapshot(state.base, current));
        });
        render();
```

con:

```js
            setDirty(state, state.flag || !sameSnapshot(state.base, current));
        });
        // I bottoni del form attivo: le copie esistenti non si ricreano e non si spostano.
        if (listed !== active) {
            var list = [...active.form.elements].filter(el => (el.type === 'submit' || el.classList.contains('wi-submit')) && !el.closest(SKIP));
            var next = null;
            copies.forEach((item, original) => {
                if (list.includes(original)) return;
                item.copy.remove();
                item.observer.disconnect();
                io.unobserve(original);
                ro.unobserve(original);
                hits.delete(original);
                copies.delete(original);
            });
            list.reverse().forEach(original => {
                var item = copies.get(original);
                if (!item) {
                    var copy = proxyButton(original);
                    item = { copy: copy, observer: new MutationObserver(records => {
                        syncProxy(original, copy);
                        if (records.some(record => ['type', 'hidden', 'data-wi-save-bar-ignore'].includes(record.attributeName))) {
                            listed = null;
                            recalcSoon();
                        }
                    }) };
                    copy.addEventListener('click', () => original.click());
                    item.observer.observe(original, { attributes: true, attributeFilter: ['disabled', 'aria-disabled', 'class', 'type', 'hidden', 'data-wi-save-bar-ignore'], characterData: true, childList: true, subtree: true });
                    actions.insertBefore(copy, next);
                    copies.set(original, item);
                    io.observe(original);
                    ro.observe(original);
                }
                next = item.copy;
            });
            listed = active;
        }
        render();
```

Riga 147, sostituisci:

```js
    var forms, active, labels, bar, actions, status, interacted = false, recalcSoon = frameScheduler(recalc);
```

con:

```js
    var SCROLL = { capture: true, passive: true };
    var forms, active, labels, bar, actions, status, listed, io, ro, topbarHeight, band, interacted = false, seen = false;
    var copies = new Map(), hits = new Map(), recalcSoon = frameScheduler(recalc), renderSoon = frameScheduler(render);
```

Riga 144, sostituisci:

```js
    var EXCLUDE = '.modal, [data-wi-save-bar-ignore]';
```

con:

```js
    var EXCLUDE = '.modal, [data-wi-save-bar-ignore]';
    var SKIP = '.modal, .offcanvas, [data-wi-save-bar-ignore]';
    var BUTTONS = 'button, [type=submit], .wi-submit';
```

- [ ] **Step 4: Aggiungi `proxyButton()` e `syncProxy()` prima dell'avvio**

La copia è un `<button type="button">` nuovo: dell'originale non porta `name`, `value`, `form*`, `on*`, `data-bs-*` né `data-wi-*`, quindi non invia il form, non apre modal e non viene riagganciata da altri script. Il clic passa a `original.click()`. Nello stesso file, righe 138-139, sostituisci:

```js
// L'avvio, una volta sola: chiamato da setUpPage() subito prima di loaded.
function setUpSaveBar() {
```

con:

```js
// Una copia pulita del bottone: dell'originale solo etichetta, classi btn* e disabled.
function proxyButton(original) {

    var copy = saveBarNode('button', { type: 'button' });

    syncProxy(original, copy);

    return copy;

}

// La copia segue l'originale; l'etichetta si riscrive solo se cambia.
function syncProxy(original, copy) {

    var template = document.createElement('template');

    if (original.tagName === 'INPUT') template.content.append(original.value);
    else template.innerHTML = original.innerHTML;
    template.content.querySelectorAll('*').forEach(el => [...el.attributes].forEach(attr => {
        if (attr.name === 'id' || attr.name.startsWith('data-')) el.removeAttribute(attr.name);
    }));
    if (copy.innerHTML !== template.innerHTML) copy.innerHTML = template.innerHTML;
    copy.className = [...original.classList].filter(name => name.startsWith('btn') && name !== 'btn-check' || name === 'disabled').concat('wi-save-bar-btn').join(' ');
    copy.disabled = original.disabled || original.classList.contains('disabled') || original.getAttribute('aria-disabled') === 'true';

}

// L'avvio, una volta sola: chiamato da setUpPage() subito prima di loaded.
function setUpSaveBar() {
```

- [ ] **Step 5: Rilancia il test e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-save-bar.test.cjs
npm test
```

Expected: PASS con `backend-save-bar: 39 tests passed`; `npm test` finisce con la stessa riga.

- [ ] **Step 6: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add src/build/backend/js/form/saveBar.js test/backend-save-bar.test.cjs
git commit -m "feat(backend): copie dei bottoni e fascia di visibilità" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `2 files changed`.

### Task C5: invio, avviso di uscita, ritorno dalla cache, tastiera e focus

Repo: **lib**.

Spec sez. 3 F (invio), 3 G (avviso di uscita), 3 J (ritorno dalla cache), 3 L (tastiera virtuale e `inert`), la riga `submitting` di U27 e `loadingSpinner(show)` dei Principi di codice ("Lib: si riusa quello che c'è"). I test in `node:vm` provano anche le situazioni di B29-B32, B34, B37 e B39, che la Parte B ripete nel browser vero. Qui entra l'ultimo argomento di `saveBarState()` che in C3 e C4 era `false`: `submitting`.

**Files:**
- Modify: `src/build/backend/js/form/saveBar.js` (righe 177, 223, 247, 256-264, 273-277, 363)
- Modify: `src/build/backend/js/utility.js` (righe 34-36)
- Modify: `test/backend-save-bar.test.cjs` (riga 889 in U24; prima del commento di ancoraggio, riga 1201)

**Interfaces:**
- Consumes:
  - da C1: `frameScheduler(fn)`, `saveBarState(ready, submitting, dirty, outside)`, `labels.confirmOther`; nel test `newPage()` con l'opzione `scripts`, `t.env.confirms`, `t.env.confirmAnswer`, `t.env.spinner`, `t.env.coarse`, `t.env.formData`, `t.context.visualViewport`, `t.context.location`, `t.win.has()`, `el.focusCalls`, `t.doc.activeElement`, `t.doc.submits` (`sent`, `submitter`);
  - da C2: `forms` con `entry.dirty` e `entry.flag`, `stateOf(el)`, `wiSaveBar.reset()`, `changed()` e `isDirty()`;
  - da C3: `active`, `bar`, `actions`, `status`, `render()`, `recalcSoon`, `onActivate()` e la riga dei suoi listener nell'avvio; nel test `started()`, `oneForm`, `twoForms`, `plainForm()`, `userTypes()`;
  - da C4: `copies`, il clic della copia, `seen` e `outside` in `render()`; nel test `box()`, `t.intersect()`, `t.copies()`;
  - dalla lib: `preventPageExit` (`src/build/global/js/utility.js:115`) e `loadingSpinner()` (`src/build/backend/js/utility.js:34`), globali in ogni pagina del backend.
- Produces:
  - dentro `setUpSaveBar()`: `submitting` (true dall'invio non annullato al cambio di pagina), `onSubmit(event)`, il listener `submit` in cattura che rimette `onSubmit` in coda, il listener `pageshow`, `coarse`, `keyboardSoon`;
  - in `render()`: `saveBarState(..., submitting, ...)`, il passaggio del focus da una copia al suo originale prima di `inert`, e `preventPageExit` su `beforeunload` solo con `!submitting` e almeno un form tracciato sporco;
  - sull'isola l'attributo `data-wi-save-bar-keyboard`, che lo stile della Parte I usa per nasconderla (sez. 3 L);
  - `loadingSpinner(show)`: senza argomento alterna come prima, `true` accende, `false` spegne.

- [ ] **Step 1: Scrivi i test che falliscono**

I test coprono l'invio non annullato, anche da tastiera (`submitting` nello stesso task, la copia che non invia due volte, il flag `-dirty` azzerato senza togliere l'attributo), il listener registrato dopo l'isola che annulla l'invio, `confirmOther` (solo un invio che naviga la finestra con un altro form tracciato sporco: niente per `_blank`, `dialog`, AJAX e per il verso opposto; Annulla spegne lo spinner, OK va in invio anche da un form non tracciato), l'avviso di uscita su tutti i form tracciati, `pageshow` dalla cache, la tastiera virtuale, il focus che passa all'originale e `loadingSpinner(show)` caricato dal vero `utility.js`.

In `test/backend-save-bar.test.cjs`, subito prima della riga `// Esecuzione in ordine, anche dei test asincroni.` (riga 1201), inserisci questo blocco, seguito da una riga vuota:

```js
const exitArmed = (t) => t.win.has('beforeunload', t.context.preventPageExit);

test('U27 invio non annullato: submitting nello stesso task, anche da tastiera', () => {
    const t = started(oneForm);
    const save = t.form('save');
    save.rect = box(700, 740);
    t.intersect(save, true);
    userTypes(t, t.form('title'), 'Villa');
    t.tick();
    assert.deepEqual([t.state(), exitArmed(t)], ['dirty-buttons', true]);
    t.copies()[0].click();
    assert.deepEqual([t.doc.submits.length, t.doc.submits[0].submitter, t.doc.submits[0].sent], [1, save, true]);
    assert.deepEqual([t.state(), t.bar().inert, exitArmed(t)], ['submitting', true, false], 'senza aspettare il frame');
    t.copies()[0].click();
    assert.equal(t.doc.submits.length, 1, 'la copia non invia due volte');
    t.fire(t.form('title'), 'input');
    t.tick();
    assert.deepEqual([t.state(), exitArmed(t)], ['submitting', false], 'resta in invio');
    // Invio da tastiera o requestSubmit(): lo stesso evento submit, qui con il flag -dirty.
    const u = newPage((h) => [plainForm(h, 'p', { 'data-wi-save-bar-dirty': true })]);
    u.start();
    u.tick();
    assert.deepEqual([u.state(), exitArmed(u)], ['dirty-label', true]);
    u.fire(u.form('p'), 'submit');
    assert.deepEqual([u.state(), u.bar().inert, exitArmed(u)], ['submitting', true, false]);
    u.api.changed();
    u.tick();
    assert.deepEqual([u.api.isDirty(u.form('p')), u.form('p').hasAttribute('data-wi-save-bar-dirty'), u.state()], [false, true, 'submitting'], 'flag azzerato, attributo intatto');
});

test('un listener registrato dopo l\'isola annulla l\'invio: niente submitting', () => {
    const t = started(oneForm);
    const save = t.form('save');
    save.rect = box(100, 140);
    t.intersect(save, true);
    userTypes(t, t.form('title'), 'Villa');
    t.tick();
    const cancel = (event) => event.preventDefault();
    t.win.addEventListener('submit', cancel);
    save.click();
    assert.deepEqual([t.doc.submits[0].sent, t.state(), exitArmed(t), t.env.confirms], [false, 'dirty-label', true, []]);
    t.win.removeEventListener('submit', cancel);
    save.click();
    assert.deepEqual([t.doc.submits[1].sent, t.state(), exitArmed(t)], [true, 'submitting', false]);
});

test('confirmOther: chiede solo un invio che naviga con un altro form sporco', () => {
    const page = (h) => [...twoForms(h),
        h('form', { id: 'g', method: 'get' },
            h('input', { id: 'q', name: 'q', value: '' }),
            h('button', { id: 'g_go' }, 'Cerca'),
            h('button', { id: 'g_blank', formtarget: '_blank' }, 'Nuova scheda'),
            h('button', { id: 'g_dialog', formmethod: 'DIALOG' }, 'Chiudi'),
        ),
        h('form', { id: 'blank', target: '_BLANK' }, h('button', { id: 'blank_go' }, 'Stampa')),
        h('form', { id: 'dialog', method: 'dialog' }, h('button', { id: 'dialog_go' }, 'Chiudi')),
    ];
    const t = started(page);
    const sent = () => t.doc.submits.map((submit) => submit.sent);
    // Il form non tracciato compilato non conta come sporco: il verso opposto non chiede.
    userTypes(t, t.form('q'), 'villa');
    userTypes(t, t.form('a_title'), 'Villa');
    t.tick();
    ['g_blank', 'g_dialog', 'blank_go', 'dialog_go'].forEach((id) => t.form(id).click());
    assert.deepEqual([t.env.confirms, sent(), exitArmed(t)], [[], [true, true, true, true], true], 'target _blank e method dialog non navigano la finestra (Q3)');
    const ajax = (event) => event.preventDefault();
    t.form('g').addEventListener('submit', ajax);
    t.form('g_go').click();
    assert.deepEqual(t.env.confirms, [], 'invio AJAX con preventDefault');
    t.form('g').removeEventListener('submit', ajax);
    t.env.confirmAnswer = false;
    t.form('b_save').click();
    t.form('g_go').click();
    assert.deepEqual(t.env.confirms, [t.api.labels.confirmOther, t.api.labels.confirmOther]);
    assert.deepEqual([sent().slice(-2), t.env.spinner, t.state(), exitArmed(t)], [[false, false], [false, false], 'hidden', true], 'Annulla');
    t.form('a_save').click();
    assert.equal(t.env.confirms.length, 2, 'il form sporco stesso non chiede, anche con q compilato');
    assert.deepEqual([sent().pop(), t.state(), exitArmed(t)], [true, 'submitting', false]);
    // OK su un form non tracciato: la pagina va in invio e l'avviso si toglie subito.
    const u = started(page);
    userTypes(u, u.form('a_title'), 'Villa');
    u.tick();
    u.form('g_go').click();
    assert.deepEqual([u.env.confirms.length, u.doc.submits[0].sent, u.state(), exitArmed(u)], [1, true, 'submitting', false]);
    u.form('b_save').click();
    assert.equal(u.env.confirms.length, 1, 'in invio non chiede piu\'');
});

test('l\'avviso di uscita segue tutti i form tracciati', () => {
    const t = started(twoForms);
    assert.equal(exitArmed(t), false);
    userTypes(t, t.form('a_title'), 'Villa');
    assert.equal(exitArmed(t), false, 'si aggiorna nel frame');
    t.tick();
    assert.equal(exitArmed(t), true);
    t.fire(t.form('b_title'), 'pointerdown');
    t.tick();
    assert.deepEqual([t.doc.querySelector('[role=status]').textContent, exitArmed(t)], ['', true], 'form attivo pulito, un altro sporco');
    t.form('a_title').value = 'Casa a';
    t.fire(t.form('a_title'), 'input');
    t.tick();
    assert.equal(exitArmed(t), false);
    // Dopo un POST fallito: armato dal primo frame fino a reset().
    const u = newPage((h) => [plainForm(h, 'p', { 'data-wi-save-bar-dirty': true })]);
    u.start();
    u.tick();
    assert.equal(exitArmed(u), true);
    u.api.reset(u.form('p'));
    assert.equal(exitArmed(u), false, 'reset() lo spegne subito, prima di form.submit()');
});

test('pageshow dalla cache: GET dopo un invio, altrimenti un ricalcolo', () => {
    const t = started(oneForm);
    const order = [];
    t.context.loadingSpinner = (show) => order.push('spinner ' + show);
    t.context.location.replace = (url) => order.push('replace ' + url);
    t.fire(t.win, 'pageshow', { bubbles: false, persisted: false });
    assert.deepEqual([t.frames.length, order], [0, []], 'pagina nuova');
    t.fire(t.win, 'pageshow', { bubbles: false, persisted: true });
    assert.equal(t.tick(), 1);
    assert.deepEqual([t.env.formData, order], [1, []], 'dalla cache senza invio: si ricalcola');
    t.form('save').click();
    t.fire(t.win, 'pageshow', { bubbles: false, persisted: true });
    assert.deepEqual(order, ['spinner false', 'replace https://new.test/backend/prodotti/?id=1']);
});

test('tastiera virtuale: nascosta con un campo di testo a fuoco', () => {
    const t = started((h) => [h('form', { id: 'f', 'data-wi-save-bar': true },
        h('input', { id: 'title', name: 'title' }),
        h('input', { id: 'email', name: 'email', type: 'email' }),
        h('textarea', { id: 'note', name: 'note' }),
        ...['checkbox', 'radio', 'button', 'submit', 'reset', 'file', 'range', 'color'].map((type) => h('input', { id: type, name: type, type })),
        h('button', { id: 'save' }, 'Salva'),
    ), h('div', { id: 'editor', isContentEditable: true })]);
    const keyboard = (id) => {
        const el = t.form(id);
        el.focus();
        t.fire(el, 'focusin');
        t.tick();
        return t.bar().hasAttribute('data-wi-save-bar-keyboard');
    };
    assert.equal(keyboard('title'), false, 'puntatore fine e viewport intero');
    t.env.coarse = true;
    assert.deepEqual(['title', 'email', 'note', 'editor'].map(keyboard), [true, true, true, true]);
    assert.deepEqual(['checkbox', 'radio', 'button', 'submit', 'reset', 'file', 'range', 'color', 'save'].map(keyboard), Array(9).fill(false));
    keyboard('title');
    t.form('title').blur();
    t.fire(t.form('title'), 'focusout');
    t.form('note').focus();
    t.fire(t.form('note'), 'focusin');
    assert.equal(t.bar().hasAttribute('data-wi-save-bar-keyboard'), true, 'tra due campi non ricompare');
    assert.equal(t.tick(), 1);
    assert.equal(t.bar().hasAttribute('data-wi-save-bar-keyboard'), true);
    t.form('note').blur();
    t.fire(t.form('note'), 'focusout');
    t.tick();
    assert.equal(t.bar().hasAttribute('data-wi-save-bar-keyboard'), false, 'blur');
    // Senza pointer: coarse decide l'altezza del viewport visibile (Q15).
    t.env.coarse = false;
    keyboard('title');
    t.context.visualViewport.height = 590;
    t.fire(t.context.visualViewport, 'resize', { bubbles: false });
    t.tick();
    assert.equal(t.bar().hasAttribute('data-wi-save-bar-keyboard'), true, 'sotto 0,75 x innerHeight');
    t.context.visualViewport.height = 610;
    t.fire(t.context.visualViewport, 'resize', { bubbles: false });
    t.tick();
    assert.equal(t.bar().hasAttribute('data-wi-save-bar-keyboard'), false, 'sopra la soglia');
});

test('il focus su una copia passa all\'originale prima di inert', () => {
    const t = started(oneForm);
    const save = t.form('save');
    const scroll = (rect) => {
        save.rect = rect;
        t.fire(t.win, 'scroll', { bubbles: false });
        t.tick();
        return t.state();
    };
    save.rect = box(700, 740);
    t.intersect(save, true);
    t.tick();
    t.form('title').focus();
    assert.deepEqual([scroll(box(100, 140)), scroll(box(700, 740)), save.focusCalls], ['hidden', 'buttons', []], 'focus altrove: resta dov\'e\'');
    const copy = t.copies()[0];
    copy.focus();
    // Le opzioni nascono nel contesto vm: si copiano per il confronto.
    assert.deepEqual([scroll(box(100, 140)), t.doc.activeElement, save.focusCalls.map((options) => ({ ...options }))], ['hidden', save, [{ preventScroll: true }]]);
    userTypes(t, t.form('title'), 'Villa');
    scroll(box(700, 740));
    copy.focus();
    assert.deepEqual([scroll(box(100, 140)), t.doc.activeElement, save.focusCalls.length], ['dirty-label', save, 2], 'solo le copie diventano inert');
    scroll(box(700, 740));
    copy.focus();
    copy.click();
    assert.deepEqual([t.state(), t.doc.activeElement, save.focusCalls.length], ['submitting', copy, 2], 'in invio il focus non si sposta');
});

test('loadingSpinner(show): false spegne, true accende, senza argomento alterna', () => {
    const t = newPage((h) => [h('div', { id: 'loading-spinner', class: 'd-none' })], { scripts: ['src/build/backend/js/utility.js'] });
    const spinner = t.form('loading-spinner');
    const shown = (...args) => {
        t.context.loadingSpinner(...args);
        return !spinner.classList.contains('d-none');
    };
    assert.deepEqual([shown(), shown(true), shown(false), shown(false), shown(), shown()], [true, true, false, false, true, false]);
});
```

Poi, in U24 (riga 889), il `focusin` da script fa partire il frame della tastiera, che però non cambia il form attivo. Sostituisci:

```js
    assert.equal(t.tick(), 0, 'gli eventi da script non cambiano il form attivo');
```

con:

```js
    assert.deepEqual([t.tick(), t.state()], [1, 'hidden'], 'gli eventi da script non cambiano il form attivo: solo il frame della tastiera');
```

- [ ] **Step 2: Lancia il test e verifica che fallisca**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-save-bar.test.cjs
```

Expected: FAIL con `FAIL U24 il form attivo: il primo con -dirty all'avvio, poi l'ultimo toccato` e `AssertionError [ERR_ASSERTION]: gli eventi da script non cambiano il form attivo: solo il frame della tastiera` (`0` invece di `1`: il frame della tastiera non esiste ancora).

- [ ] **Step 3: Aggiungi invio, avviso di uscita, bfcache, tastiera e focus a `setUpSaveBar()`**

Un listener `submit` in cattura su `window`, a ogni invio, toglie e rimette `onSubmit` in bolla: così `onSubmit` gira dopo tutti gli altri listener e vede `defaultPrevented` definitivo. Un invio non annullato di un form tracciato azzera il suo flag `-dirty` (l'attributo resta) e porta `submitting` a true nello stesso task: `render()` nasconde l'isola, la rende `inert` e toglie `preventPageExit`. Un invio che naviga la finestra (`target` e `method` letti prima da `formtarget`/`formmethod` del bottone, Q3) con un altro form tracciato sporco chiede `confirm(labels.confirmOther)`: Annulla fa `preventDefault()` e `loadingSpinner(false)`, OK va in `submitting` anche da un form non tracciato. Durante l'invio la copia non richiama più l'originale.

`render()` tiene `preventPageExit` su `beforeunload` solo se nessun invio è partito e almeno un form tracciato è sporco: così si aggiorna nel frame del ricalcolo, subito in `reset()` e subito nell'handler dell'invio. Prima di rendere `inert` l'isola (`hidden`) o il gruppo delle copie (`dirty-label`), il focus su una copia passa al suo originale con `preventScroll`; in `submitting` non si sposta. Da `pageshow` con `persisted` si ricalcola, o dopo un invio si spegne lo spinner e si ricarica l'URL senza `#` con `location.replace()`, quindi con una GET. La tastiera si decide in un frame dopo `focusin`/`focusout` (su `document`, in cattura) e dopo il `resize` di `visualViewport`.

Le sostituzioni vanno dal basso verso l'alto, così i numeri di riga restano quelli del file alla fine di C4. In `src/build/backend/js/form/saveBar.js`, nell'avvio (riga 363), sostituisci:

```js
        ['focusin', 'pointerdown', 'input'].forEach(type => document.addEventListener(type, onActivate, true));
```

con:

```js
        ['focusin', 'pointerdown', 'input'].forEach(type => document.addEventListener(type, onActivate, true));
        // Il listener in bolla torna in coda a ogni invio, come FormSubmitObserver di Turbo.
        window.addEventListener('submit', () => {
            window.removeEventListener('submit', onSubmit);
            window.addEventListener('submit', onSubmit);
        }, true);
        // Dalla bfcache dopo un invio si ricarica con una GET, mai con un nuovo POST.
        window.addEventListener('pageshow', event => {
            if (!event.persisted) return;
            if (!submitting) return recalcSoon();
            loadingSpinner(false);
            location.replace(location.href.split('#')[0]);
        });
        // Tastiera virtuale: si decide nel frame dopo focusout, cosi' tra due campi l'isola non ricompare.
        var coarse = matchMedia('(pointer: coarse)');
        var keyboardSoon = frameScheduler(() => {
            var el = document.activeElement;
            var typing = el.tagName === 'TEXTAREA' || el.isContentEditable || el.tagName === 'INPUT' && !/^(checkbox|radio|button|submit|reset|file|range|color)$/.test(el.type);
            bar.toggleAttribute('data-wi-save-bar-keyboard', typing && (coarse.matches || visualViewport.height < 0.75 * window.innerHeight));
        });
        ['focusin', 'focusout'].forEach(type => document.addEventListener(type, keyboardSoon, true));
        visualViewport.addEventListener('resize', keyboardSoon);
```

In fondo a `onActivate()` (righe 273-277), sostituisci:

```js
        if (!state || state === active) return;
        active = state;
        recalcSoon();

    }
```

con:

```js
        if (!state || state === active) return;
        active = state;
        recalcSoon();

    }

    // Gira per ultimo: defaultPrevented e' gia' definitivo.
    function onSubmit(event) {

        var form = event.target, submitter = event.submitter, state = stateOf(form);
        var read = name => ((submitter && submitter.getAttribute('form' + name)) || form.getAttribute(name) || '').toLowerCase();

        if (event.defaultPrevented) return;
        // Un invio che naviga la finestra con un altro form sporco chiede conferma (Q3).
        if (read('target') !== '_blank' && read('method') !== 'dialog' && !submitting && forms.some(entry => entry !== state && entry.dirty)) {
            if (!confirm(labels.confirmOther)) {
                event.preventDefault();
                loadingSpinner(false);
                return;
            }
            submitting = true;
        }
        if (state) {
            state.flag = false;
            submitting = true;
        }
        render();

    }
```

In `render()` (righe 256-264), sostituisci:

```js
        var state = saveBarState(!!active.base && (seen || !copies.size), false, active.dirty, outside);
        var text = state.startsWith('dirty') ? labels.unsaved : '';

        if (bar.getAttribute('data-wi-state') !== state) {
            bar.setAttribute('data-wi-state', state);
            bar.inert = state === 'hidden' || state === 'submitting';
            actions.inert = state === 'dirty-label';
        }
        if (status.textContent !== text) status.textContent = text;
```

con:

```js
        var state = saveBarState(!!active.base && (seen || !copies.size), submitting, active.dirty, outside);
        var text = state.startsWith('dirty') ? labels.unsaved : '';

        if (bar.getAttribute('data-wi-state') !== state) {
            // Prima di inert: il focus su una copia passa al suo originale.
            if (state === 'hidden' || state === 'dirty-label') copies.forEach((item, original) => {
                if (item.copy === document.activeElement) original.focus({ preventScroll: true });
            });
            bar.setAttribute('data-wi-state', state);
            bar.inert = state === 'hidden' || state === 'submitting';
            actions.inert = state === 'dirty-label';
        }
        if (status.textContent !== text) status.textContent = text;
        if (!submitting && forms.some(entry => entry.dirty)) window.addEventListener('beforeunload', preventPageExit);
        else window.removeEventListener('beforeunload', preventPageExit);
```

Riga 247, sostituisci:

```js
    // Stato, inert e regione status seguono il form attivo.
```

con:

```js
    // Stato, inert e regione status seguono il form attivo; l'avviso di uscita tutti i form.
```

Nel clic della copia (riga 223), sostituisci:

```js
                    copy.addEventListener('click', () => original.click());
```

con:

```js
                    copy.addEventListener('click', () => {
                        if (!submitting) original.click();
                    });
```

Riga 177, sostituisci:

```js
    var forms, active, labels, bar, actions, status, listed, io, ro, topbarHeight, band, interacted = false, seen = false;
```

con:

```js
    var forms, active, labels, bar, actions, status, listed, io, ro, topbarHeight, band, interacted = false, seen = false, submitting = false;
```

- [ ] **Step 4: Estendi `loadingSpinner()` in modo compatibile**

È il codice della spec (Principi di codice), con la riga vuota dopo l'apertura che il file ha già. Senza argomento alterna come oggi, quindi `onsubmit="loadingSpinner()"` e gli altri chiamanti non cambiano. In `src/build/backend/js/utility.js` (righe 34-36), sostituisci:

```js
function loadingSpinner() {

    document.getElementById('loading-spinner').classList.toggle('d-none');
```

con:

```js
// Senza argomento alterna, come prima; true accende e false spegne.
function loadingSpinner(show) {

    document.getElementById('loading-spinner').classList.toggle('d-none', show === undefined ? undefined : !show);
```

- [ ] **Step 5: Rilancia il test e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-save-bar.test.cjs
npm test
```

Expected: PASS con `backend-save-bar: 47 tests passed`; `npm test` finisce con la stessa riga.

- [ ] **Step 6: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add src/build/backend/js/form/saveBar.js src/build/backend/js/utility.js test/backend-save-bar.test.cjs
git commit -m "feat(backend): invio, avviso di uscita, bfcache, tastiera e focus della barra" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `3 files changed`.

## Parte I — Integrazione nella lib: CSS, correzioni in set.js, MANIFEST, test spostato, documentazione

Copre le correzioni di Quill ed EditorJS (sez. 2.5), le chiamate ad `absorb()` dei riempimenti tardivi (sez. 2.3 e 5.7), lo stile dell'isola, la riserva in fondo e i bottoni scuri disabilitati (sez. 4), i test U29-U39 e, per la lib, la parte "Documentazione": pagine in `docs/`, `MANIFEST.json`, `AGENTS.md` e CHANGELOG.

Regole valide per tutta la parte:
- ogni blocco di comandi parte da `cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar`: è il worktree `LIB_WT` del Task S1, sul branch `backend-save-bar`, con `node_modules/` già installati da `npm ci`;
- `dist/` non si tocca mai sul branch: la build si verifica fuori dal repo con `npx webpack --output-path "$(mktemp -d)"`, e `dist/` viene ricostruita e committata da `npm run release` al rilascio (Parte R). Nessun passo di questa parte lancia `npm run build`;
- I1-I4 non dipendono dalla Parte C. I5-I7 vanno dopo la Parte C: la documentazione descrive `saveBar.js` e `loadingSpinner(show)`, e il test del MANIFEST controlla che `src/build/backend/js/form/saveBar.js` esista;
- i numeri di riga sono quelli del `main` di partenza, dopo i task precedenti di questa parte. Nell'elenco **Files** sono quelli prima del task; nei passi, ogni sostituzione tiene conto di quelle precedenti dello stesso task. Se la Parte C è già entrata e ha spostato righe (per esempio in `src/export/backend/head.js`), vale il testo da sostituire, che nel file compare una volta sola;
- `set.js` e `maps.js` contengono già caratteri non ASCII, quindi i commenti nuovi restano in italiano con gli accenti; i file nuovi sono solo ASCII, e la documentazione della lib resta in inglese come il resto di `docs/` (tranne `docs/integrations/quill.md`, che è già in italiano);
- `/Users/andreamarinoni/Developer/packages` e `/Users/andreamarinoni/Desktop/PROGETTI/template` sono la stessa cartella: se l'hook rifiuta Edit/Write sul worktree col percorso `Developer/packages`, si usa lo stesso percorso sotto `Desktop/PROGETTI/template`;
- push, PR, merge, release npm e tag si fanno **solo su richiesta esplicita dell'utente**; in questa parte non ce ne sono.

### Task I1: Quill scrive la textarea solo su `text-change` e vuoto vale `''`

Repo: **lib**.

Spec sez. 2.5 e Q10, test U29-U32. Oggi il ramo Quill di `setTextarea` scrive la textarea anche su `keydown` e sul deprecato `DOMNodeInserted`, che mettono nella textarea `<p><br></p>` e il cursore: "scrivo una lettera e la cancello" resterebbe "non salvato".

**Files:**
- Modify: `src/build/backend/js/form/set.js:273-279, 296-299` (ramo Quill di `setTextarea`)
- Test: `test/backend-set-input.test.cjs` (riscritto: U29-U32)

**Interfaces:**
- Consumes: `setTextarea(CONTAINER)` e `wiSanitizeHtml()` di `set.js`; `window.wiSaveBar?.changed()` della Parte C, che qui è facoltativo (senza `wiSaveBar` nessun errore).
- Produces: Quill che scrive la textarea solo su `text-change` (sorgente `user` o `api`), con `''` per l'editor vuoto (`quill.getLength() > 1`), e poi chiama `window.wiSaveBar?.changed(textarea)`.

- [ ] **Step 1: Scrivi i test U29-U32 che falliscono**

Il finto di Quill parte vuoto come Quill (`<p><br></p>`), tiene l'handler di `text-change` e, come il vero, `on()` restituisce l'emitter e non l'editor; `getLength()` vale 1 a editor vuoto. `saveBar(context)` registra le chiamate a `wiSaveBar`. Il corpo sta in una funzione `async`, che I2 usa per aspettare le Promise di Editor.js.

Sostituisci tutto il contenuto di `test/backend-set-input.test.cjs` con:

```js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const read = (file) => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');

// Il core scrive `data-wi-value` con base64_encode() del testo UTF-8.
const base64 = (text) => Buffer.from(text, 'utf8').toString('base64');

const noop = () => {};
const element = () => ({ id: '', classList: { add: noop }, style: {}, addEventListener: noop });

function newContext() {
    const context = {
        document: {
            addEventListener: noop,
            createElement: element,
            createTextNode: (text) => ({ text }),
        },
        addEventListener: noop,
        atob,
        TextDecoder,
        DOMPurify: { sanitize: (html) => html },
    };
    context.window = context;
    vm.createContext(context);
    vm.runInContext(read('src/build/backend/js/form/set.js'), context);
    return context;
}

// wiSaveBar finto: registra le chiamate.
function saveBar(context) {
    const calls = [];
    context.wiSaveBar = {
        changed: (el) => calls.push(['changed', el]),
        absorb: (el) => calls.push(['absorb', el]),
    };
    return calls;
}

// Un editor Quill (base, plus, pro) con il valore del core. Il finto parte
// vuoto come Quill (<p><br></p>) e tiene l'handler di text-change; come il
// vero, on() restituisce l'emitter e non l'editor.
function quillTextarea(value, withSaveBar = true) {
    const context = newContext();
    const calls = withSaveBar ? saveBar(context) : [];
    const listeners = [];
    const qlEditor = { innerHTML: '<p><br></p>', addEventListener: (name) => listeners.push(name) };
    let quill = null;
    context.Quill = class {
        constructor() { quill = this; this.handlers = {}; }
        on(name, handler) { this.handlers[name] = handler; return {}; }
        // Come Quill: 1 a editor vuoto, per il \n finale.
        getLength() { return qlEditor.innerHTML.replace(/<[^>]*>/g, '').length + 1; }
    };
    context.document.querySelector = (selector) => (selector === '#description_editor .ql-editor' ? qlEditor : null);

    const col = { querySelector: () => element() };
    const textarea = {
        id: 'description',
        value: 'dal server',
        dataset: { wiTextarea: 'plus', wiValue: value },
        parentElement: { parentElement: col, after: noop },
    };
    context.setTextarea({ querySelectorAll: () => [textarea] });

    // text-change con l'HTML che l'editor ha dopo la modifica.
    const emit = (html, source) => {
        qlEditor.innerHTML = html;
        quill.handlers['text-change']({}, {}, source);
    };

    return { qlEditor, textarea, listeners, calls, emit };
}

(async () => {

    // setInput avvia anche AutoNumeric, nel contenitore che riceve
    {
        const context = newContext();
        const calls = [];
        ['setGoogleMapsApi', 'setSearchInput', 'setTextarea', 'setUploader', 'setJsTree',
            'setCheckBoolean', 'setSelect2', 'setIconPicker', 'setConditional'].forEach((name) => { context[name] = noop; });
        context.setAutonumeric = (container) => calls.push(container);

        const row = { id: 'riga-nuova' };
        context.setInput(row);
        assert.equal(calls.length, 1, 'una riga aggiunta avvia AutoNumeric');
        assert.equal(calls[0], row, 'AutoNumeric parte nella riga aggiunta');

        context.setInput();
        assert.equal(calls[1], context.document, 'senza contenitore vale tutta la pagina');
    }

    assert.equal(quillTextarea(base64('<p>perché è così</p>')).qlEditor.innerHTML, '<p>perché è così</p>', 'le lettere accentate tornano intatte in Quill');
    assert.equal(quillTextarea(base64('<p>€ 12 — “citazione”</p>')).qlEditor.innerHTML, '<p>€ 12 — “citazione”</p>', 'anche i caratteri fuori dal Latin-1');
    assert.equal(quillTextarea('').qlEditor.innerHTML, '', 'un campo vuoto apre un editor vuoto');

    // U29: la textarea cambia solo su text-change.
    {
        const quill = quillTextarea(base64('<p>ciao</p>'));
        assert.deepEqual(quill.listeners, [], 'nessun listener keydown o DOMNodeInserted sull\'editor');
        assert.equal(quill.textarea.value, 'dal server', 'senza text-change la textarea non cambia');

        quill.emit('<p>ciao!</p>', 'user');
        assert.equal(quill.textarea.value, '<p>ciao!</p>');
    }

    // U30: Quill vuoto vale '', non <p><br></p>.
    {
        const quill = quillTextarea(base64('<p>a</p>'));
        quill.emit('<p><br></p>', 'user');
        assert.equal(quill.textarea.value, '', 'un Quill svuotato torna vuoto');
    }

    // U31: text-change dell'utente -> changed(); senza wiSaveBar nessun errore.
    {
        const quill = quillTextarea('');
        quill.emit('<p>a</p>', 'user');
        assert.deepEqual(quill.calls, [['changed', quill.textarea]]);

        const senza = quillTextarea('', false);
        assert.doesNotThrow(() => senza.emit('<p>a</p>', 'user'), 'senza wiSaveBar nessun errore');
        assert.equal(senza.textarea.value, '<p>a</p>');
    }

    // U32: setContents e clipboard.dangerouslyPasteHTML emettono con sorgente api.
    {
        const quill = quillTextarea('');
        quill.emit('<p>da setContents</p>', 'api');
        quill.emit('<p><strong>incollato</strong></p>', 'api');
        assert.equal(quill.textarea.value, '<p><strong>incollato</strong></p>');
        assert.equal(quill.calls.length, 2, 'changed() anche con sorgente api');
    }

    // Un editor Editor.js (table, blog) con il valore del core.
    {
        const context = newContext();
        let editorOptions = null;
        const upload = () => ({ config: { additionalRequestHeaders: {} } });
        context.EDITORJS_TOOLS_BLOG = { image: upload(), gallery: upload(), attaches: upload() };
        context.EDITORJS_TOOLS_TABLE = {};
        context.EDITORJS_i18n_IT = {};
        context.EditorJS = class { constructor(options) { editorOptions = options; } };

        const blocks = JSON.stringify([{ type: 'paragraph', data: { text: 'perché è' } }]);
        const textarea = {
            id: 'content',
            dataset: { wiTextarea: 'blog', wiValue: base64(blocks), wiFolder: 'blog' },
            parentElement: { after: noop },
        };
        context.setTextarea({ querySelectorAll: () => [textarea] });
        assert.equal(editorOptions.data.blocks[0].data.text, 'perché è', 'le lettere accentate tornano intatte in Editor.js');
    }

    console.log('backend set input tests passed');

})().catch((error) => { console.error(error); process.exitCode = 1; });
```

- [ ] **Step 2: Lancia il test e verifica che fallisca**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-set-input.test.cjs
```

Expected: FAIL con `AssertionError [ERR_ASSERTION]: nessun listener keydown o DOMNodeInserted sull'editor`: oggi `setTextarea` registra `keydown` e `DOMNodeInserted` sull'editor.

- [ ] **Step 3: Scrivi la textarea solo su `text-change`**

Nel ramo Quill di `setTextarea` l'istanza va in una variabile: `on()` restituisce l'emitter, quindi non si concatena al costruttore.

In `src/build/backend/js/form/set.js`, righe 273-279, sostituisci:

```js
            new Quill('#'+editorId, {
                theme: 'snow',
                modules: {
                    toolbar: option
                }
            }).on('text-change', (delta, oldDelta, source) => {
                textarea.value = wiSanitizeHtml(editor.innerHTML);
```

con:

```js
            var quill = new Quill('#'+editorId, {
                theme: 'snow',
                modules: {
                    toolbar: option
                }
            });

            // Solo text-change, con qualsiasi sorgente; vuoto vale '' e non <p><br></p>
            quill.on('text-change', () => {
                textarea.value = quill.getLength() > 1 ? wiSanitizeHtml(editor.innerHTML) : '';
                window.wiSaveBar?.changed(textarea);
```

Poi togli le due scritture sugli eventi del DOM:

In `src/build/backend/js/form/set.js`, righe 300-303, sostituisci:

```js
            editor.addEventListener('keydown', () => { textarea.value = wiSanitizeHtml(editor.innerHTML); });
            editor.addEventListener('DOMNodeInserted', () => { textarea.value = wiSanitizeHtml(editor.innerHTML); });

        } else if (type == 'table' || type == 'blog') {
```

con:

```js
        } else if (type == 'table' || type == 'blog') {
```

La riga `editor.innerHTML = wiSanitizeHtml(...)` resta: Quill la vede con il suo MutationObserver e lancia `text-change` con sorgente `user`, e la barra la tratta come ogni altra differenza prima della prima interazione (sez. 2.3).

- [ ] **Step 4: Rilancia il test e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-set-input.test.cjs
npm test
```

Expected: PASS con `backend set input tests passed`; `npm test` finisce senza errori con `source map paths tests passed`, o con `backend-save-bar: N tests passed` se la Parte C è già entrata.

- [ ] **Step 5: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add src/build/backend/js/form/set.js test/backend-set-input.test.cjs
git commit -m "fix(backend): Quill scrive la textarea solo su text-change e vuoto vale ''" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `2 files changed`.

### Task I2: EditorJS riscrive la textarea solo se il contenuto cambia davvero

Repo: **lib**.

Spec sez. 2.5, test U33-U35. Editor.js chiama `onChange` anche quando il contenuto non cambia, e ogni `save()` restituisce `id` dei blocchi, `time` e `version` nuovi: il JSON nella textarea cambierebbe a ogni focus e la barra direbbe "non salvato" senza modifiche.

**Files:**
- Modify: `src/build/backend/js/form/set.js:339, 347-348, 354-357` (ramo `table`/`blog` di `setTextarea`)
- Test: `test/backend-set-input.test.cjs:14, 77, 137-154` (U33-U35)

**Interfaces:**
- Consumes: `setTextarea(CONTAINER)` di `set.js`, dopo I1; `window.wiSaveBar?.changed()` della Parte C, facoltativo.
- Produces: Editor.js che confronta i blocchi senza `id` con quelli letti al `isReady`: se sono uguali rimette il JSON originale del server, altrimenti scrive `JSON.stringify(outputData.blocks)`; dopo ogni scrittura chiama `window.wiSaveBar?.changed(textarea)`.

- [ ] **Step 1: Scrivi i test U33-U35 che falliscono**

Aggiungi `tick()`, che lascia finire le Promise del finto Editor.js:

In `test/backend-set-input.test.cjs`, riga 14, sostituisci:

```js
function newContext() {
```

con:

```js
// Lascia finire le Promise di Editor.js.
const tick = () => new Promise((resolve) => setImmediate(resolve));

function newContext() {
```

Aggiungi `editorTextarea(blocks)`: il finto `EditorJS` tiene le opzioni, ha `isReady` risolto e a ogni `save()` restituisce `id`, `time` e `version` nuovi; `change(list)` chiama `onChange` come Editor.js e aspetta un `tick()`.

In `test/backend-set-input.test.cjs`, riga 80, sostituisci:

```js
(async () => {
```

con:

```js
// Un editor Editor.js (blog) con il valore del core. Il finto chiama
// onChange(api) e ogni save() restituisce id, time e version nuovi.
function editorTextarea(blocks) {
    const context = newContext();
    const calls = saveBar(context);
    let options = null;
    let saves = 0;
    const output = (list) => {
        saves++;
        return { time: 1000 + saves, version: '2.29.' + saves, blocks: list.map((block) => ({ id: 'id' + saves, ...block })) };
    };

    const upload = () => ({ config: { additionalRequestHeaders: {} } });
    context.EDITORJS_TOOLS_BLOG = { image: upload(), gallery: upload(), attaches: upload() };
    context.EDITORJS_TOOLS_TABLE = {};
    context.EDITORJS_i18n_IT = {};
    context.EditorJS = class {
        constructor(editorOptions) { options = editorOptions; this.isReady = Promise.resolve(); }
        save() { return Promise.resolve(output(blocks)); }
    };

    const json = JSON.stringify(blocks.map((block, i) => ({ id: 'server' + i, ...block })));
    const textarea = {
        id: 'content',
        value: json,
        dataset: { wiTextarea: 'blog', wiValue: base64(json), wiFolder: 'blog' },
        parentElement: { after: noop },
    };
    context.document.getElementById = (id) => (id === 'content' ? textarea : null);
    context.setTextarea({ querySelectorAll: () => [textarea] });

    const change = async (list) => {
        options.onChange({ saver: { save: () => Promise.resolve(output(list)) } });
        await tick();
    };

    return { textarea, json, calls, change, options: () => options };
}

(async () => {
```

Sostituisci il vecchio blocco Editor.js del corpo con U33-U35 (il controllo delle lettere accentate resta):

In `test/backend-set-input.test.cjs`, righe 179-196, sostituisci:

```js
    // Un editor Editor.js (table, blog) con il valore del core.
    {
        const context = newContext();
        let editorOptions = null;
        const upload = () => ({ config: { additionalRequestHeaders: {} } });
        context.EDITORJS_TOOLS_BLOG = { image: upload(), gallery: upload(), attaches: upload() };
        context.EDITORJS_TOOLS_TABLE = {};
        context.EDITORJS_i18n_IT = {};
        context.EditorJS = class { constructor(options) { editorOptions = options; } };

        const blocks = JSON.stringify([{ type: 'paragraph', data: { text: 'perché è' } }]);
        const textarea = {
            id: 'content',
            dataset: { wiTextarea: 'blog', wiValue: base64(blocks), wiFolder: 'blog' },
            parentElement: { after: noop },
        };
        context.setTextarea({ querySelectorAll: () => [textarea] });
        assert.equal(editorOptions.data.blocks[0].data.text, 'perché è', 'le lettere accentate tornano intatte in Editor.js');
```

con:

```js
    // Editor.js: U33, U34, U35.
    {
        const paragraph = (text) => ({ type: 'paragraph', data: { text } });
        const editor = editorTextarea([paragraph('perché è')]);
        assert.equal(editor.options().data.blocks[0].data.text, 'perché è', 'le lettere accentate tornano intatte in Editor.js');
        await tick();

        await editor.change([paragraph('perché è')]);
        assert.equal(editor.textarea.value, editor.json, 'U33: stesso contenuto con id, time e version nuovi, textarea invariata');

        await editor.change([paragraph('perché no')]);
        assert.deepEqual(JSON.parse(editor.textarea.value).map((block) => block.data.text), ['perché no'], 'U34: una modifica vera riscrive la textarea');
        assert.deepEqual(editor.calls.at(-1), ['changed', editor.textarea], 'U34: changed() dopo la scrittura');

        await editor.change([paragraph('perché è')]);
        assert.equal(editor.textarea.value, editor.json, 'U35: tornando all\'inizio la textarea riprende il JSON originale');
```

- [ ] **Step 2: Lancia il test e verifica che fallisca**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-set-input.test.cjs
```

Expected: FAIL con `AssertionError [ERR_ASSERTION]: U33: stesso contenuto con id, time e version nuovi, textarea invariata`: oggi ogni `onChange` riscrive la textarea con `id` nuovi.

- [ ] **Step 3: Confronta con il contenuto letto all'avvio**

Prima del costruttore tieni il valore del server e una funzione che toglie gli `id` dei blocchi; l'istanza va in `editorJs`:

In `src/build/backend/js/form/set.js`, riga 339, sostituisci:

```js
            new EditorJS({
```

con:

```js
            // Contenuto uguale all'avvio, a meno di id, time e version: torna il JSON originale
            var original = textarea.value;
            var initial = null;
            var normalize = (outputData) => JSON.stringify(outputData.blocks.map(({ id, ...block }) => block));

            var editorJs = new EditorJS({
```

In `onChange` riscrivi la textarea solo se il contenuto è diverso da quello iniziale, poi avvisa la barra:

In `src/build/backend/js/form/set.js`, righe 352-353, sostituisci:

```js
                        var editorValue = JSON.stringify(outputData.blocks);
                        document.getElementById(textareaId).value = editorValue;
```

con:

```js
                        var editorValue = normalize(outputData) === initial ? original : JSON.stringify(outputData.blocks);
                        document.getElementById(textareaId).value = editorValue;
                        window.wiSaveBar?.changed(textarea);
```

Dopo il costruttore leggi il contenuto iniziale quando l'editor è pronto:

In `src/build/backend/js/form/set.js`, righe 360-363, sostituisci:

```js
                i18n: EDITORJS_i18n_IT
            });

        }
```

con:

```js
                i18n: EDITORJS_i18n_IT
            });

            editorJs.isReady.then(() => editorJs.save()).then((outputData) => {
                initial = normalize(outputData);
            }).catch((error) => {
                console.log('Saving failed: ', error)
            });

        }
```

- [ ] **Step 4: Rilancia il test e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-set-input.test.cjs
npm test
```

Expected: PASS con `backend set input tests passed`; `npm test` finisce senza errori con `source map paths tests passed`, o con `backend-save-bar: N tests passed` se la Parte C è già entrata.

- [ ] **Step 5: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add src/build/backend/js/form/set.js test/backend-set-input.test.cjs
git commit -m "fix(backend): EditorJS riscrive la textarea solo se il contenuto cambia davvero" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `2 files changed`.

### Task I3: I riempimenti tardivi di DynamicCheck, jstree e Places chiamano `wiSaveBar.absorb`

Repo: **lib**.

Spec sez. 2.3 e 5.7, Q9 e Q17, test U36-U37. Tre campi si riempiono o si abilitano dopo `setUpSaveBar()`: le caselle della DynamicCheck arrivano via AJAX, jstree allinea le caselle al `ready`, Places abilita il campo quando Google risponde. Senza `absorb()` la barra le prenderebbe per modifiche dell'utente. La ricerca digitata nella DynamicCheck e le spunte dopo il `ready` restano modifiche.

**Files:**
- Modify: `src/build/backend/js/form/set.js:73, 584-586` (`setDynamicSearch` e fine di `setJsTreeValues`)
- Modify: `src/build/global/js/form/maps.js:29` (`initGoogleMapsPlace`)
- Test: `test/backend-set-input.test.cjs:17, 31, 197` (U36 e Q17)
- Test: `test/backend-tree-values.test.cjs:81, 149, 249` (U37)

**Interfaces:**
- Consumes: `window.wiSaveBar?.absorb(el)` della Parte C, facoltativo: senza `wiSaveBar` (frontend, o Parte C non ancora entrata) nessun errore. `setDynamicSearch()` e `inputSearch()` (`input.js`), `setJsTreeValues()` con `allinea()` e il contenitore `valori`, `initGoogleMapsPlace()`.
- Produces: `absorb` sul contenitore `[id^="container-"]` dopo il primo `createCheckbox` della DynamicCheck, sul contenitore `[data-wi-tree-values]` al `ready.jstree` dopo `allinea()`, e sull'input Places subito dopo `input.disabled = false`.

- [ ] **Step 1: Scrivi i test U36, Q17 e U37 che falliscono**

In `test/backend-set-input.test.cjs`, `newContext()` accetta l'elenco dei file da caricare, che per default resta `set.js`:

In `test/backend-set-input.test.cjs`, riga 17, sostituisci:

```js
function newContext() {
```

con:

```js
function newContext(files = ['src/build/backend/js/form/set.js']) {
```

In `test/backend-set-input.test.cjs`, riga 31, sostituisci:

```js
    vm.runInContext(read('src/build/backend/js/form/set.js'), context);
```

con:

```js
    files.forEach((file) => vm.runInContext(read(file), context));
```

Prima del messaggio finale aggiungi U36 (DynamicCheck: `$.ajax` finto che risponde subito) e Q17 (Places, con e senza `wiSaveBar`):

In `test/backend-set-input.test.cjs`, riga 197, sostituisci:

```js
    console.log('backend set input tests passed');
```

con:

```js
    // U36: il caricamento iniziale della DynamicCheck si assorbe, la ricerca digitata no.
    {
        const context = newContext(['src/build/backend/js/form/set.js', 'src/build/backend/js/form/input.js']);
        const calls = saveBar(context);
        context.$ = { ajax: (options) => options.success('[]') };
        context.createCheckbox = () => calls.push(['createCheckbox']);
        context.checkInput = () => calls.push(['checkInput']);

        const outer = { id: 'container-tags' };
        const search = {
            value: '',
            dataset: { wiValue: '3', wiSearchUrl: '/api/tags' },
            parentElement: { querySelector: () => ({ innerHTML: '' }) },
            closest: (selector) => (selector === '[id^="container-"]' ? outer : null),
        };

        context.setDynamicSearch(search);
        assert.deepEqual(calls, [['createCheckbox'], ['checkInput'], ['absorb', outer]], 'absorb una volta, dopo createCheckbox');

        context.inputSearch({ target: search });
        assert.deepEqual(calls.slice(3), [['createCheckbox'], ['checkInput']], 'la ricerca digitata non assorbe');
    }

    // Q17: Places che abilita il campo non è una modifica dell'utente.
    {
        const maps = ['src/build/global/js/form/maps.js'];
        const places = (context) => {
            const input = { disabled: true, dataset: { wiCallback: 'placeCallback' }, addEventListener: noop };
            context.document.querySelectorAll = () => [input];
            context.google = { maps: { places: { Autocomplete: class { addListener() {} } } } };
            return input;
        };

        const context = newContext(maps);
        const calls = [];
        context.wiSaveBar = { absorb: (el) => calls.push([el, el.disabled]) };
        const input = places(context);
        context.initGoogleMapsPlace();
        assert.deepEqual(calls, [[input, false]], 'absorb subito dopo input.disabled = false');

        const frontend = newContext(maps);
        places(frontend);
        assert.doesNotThrow(() => frontend.initGoogleMapsPlace(), 'senza wiSaveBar (frontend) nessun errore');
    }

    console.log('backend set input tests passed');
```

In `test/backend-tree-values.test.cjs`, `newTree()` accetta un `saveBar` e lo mette nel contesto come `wiSaveBar`:

In `test/backend-tree-values.test.cjs`, riga 81, sostituisci:

```js
function newTree({ type = 'checkbox', name = 'categories[]', ids = ['1', '2', '3', '4', '5'], checked = [], legacy = false, values = true } = {}) {
```

con:

```js
function newTree({ type = 'checkbox', name = 'categories[]', ids = ['1', '2', '3', '4', '5'], checked = [], legacy = false, values = true, saveBar } = {}) {
```

In `test/backend-tree-values.test.cjs`, riga 149, sostituisci:

```js
        check: () => { checks.count++; },
```

con:

```js
        check: () => { checks.count++; },
        wiSaveBar: saveBar,
```

Prima del messaggio finale aggiungi U37:

In `test/backend-tree-values.test.cjs`, riga 250, sostituisci:

```js
console.log('backend-tree-values: ok');
```

con:

```js
// U37: al ready le caselle allineate diventano il punto di partenza; le spunte dopo no.
{
    const assorbiti = [];
    const saveBar = { absorb: (el) => assorbiti.push([el, el.querySelectorAll('input').filter((c) => c.checked).map((c) => c.value)]) };
    const tree = newTree({ type: 'radio', name: 'parent_id', checked: ['3', '5'], saveBar });

    tree.fire('ready.jstree');
    assert.equal(assorbiti.length, 1, 'un solo absorb al ready');
    assert.equal(assorbiti[0][0], tree.contenitore(), 'absorb sul contenitore [data-wi-tree-values]');
    assert.deepEqual(assorbiti[0][1], ['5'], 'absorb dopo l\'allineamento delle caselle');

    tree.check('2');
    assert.equal(assorbiti.length, 1, 'una spunta dell\'utente non assorbe');
}

console.log('backend-tree-values: ok');
```

- [ ] **Step 2: Lancia i due test e verifica che falliscano**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-set-input.test.cjs
node test/backend-tree-values.test.cjs
```

Expected: FAIL in tutti e due, con `AssertionError [ERR_ASSERTION]: absorb una volta, dopo createCheckbox` e con `AssertionError [ERR_ASSERTION]: un solo absorb al ready`: oggi nessuno chiama `absorb`.

- [ ] **Step 3: Chiama `absorb` dopo i tre riempimenti**

DynamicCheck: dopo il primo caricamento delle caselle, nel `success` di `setDynamicSearch`. La ricerca digitata passa da `inputSearch()` in `input.js`, che non cambia:

In `src/build/backend/js/form/set.js`, riga 73, sostituisci:

```js
                checkInput();
```

con:

```js
                checkInput();
                // Il caricamento iniziale non è una modifica dell'utente
                window.wiSaveBar?.absorb(element.closest('[id^="container-"]'));
```

jstree: alla fine di `setJsTreeValues`, dopo il gestore che chiama `allinea()`, così al `ready` le caselle sono già allineate:

In `src/build/backend/js/form/set.js`, righe 586-588, sostituisci:

```js
}

function setJsTree(CONTAINER) {
```

con:

```js
    // Dopo allinea(): le spunte del server non sono una modifica dell'utente
    $(element).on('ready.jstree', function () { window.wiSaveBar?.absorb(valori); });

}

function setJsTree(CONTAINER) {
```

Places: in `initGoogleMapsPlace` di `maps.js`, che gira anche nel frontend senza `wiSaveBar`:

In `src/build/global/js/form/maps.js`, riga 29, sostituisci:

```js
        input.disabled = false;
```

con:

```js
        input.disabled = false;
        // Il campo abilitato da Places non è una modifica dell'utente
        window.wiSaveBar?.absorb(input);
```

- [ ] **Step 4: Rilancia i test e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-set-input.test.cjs
node test/backend-tree-values.test.cjs
npm test
```

Expected: PASS con `backend set input tests passed` e `backend-tree-values: ok`; `npm test` finisce senza errori con `source map paths tests passed`, o con `backend-save-bar: N tests passed` se la Parte C è già entrata.

- [ ] **Step 5: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add src/build/backend/js/form/set.js src/build/global/js/form/maps.js test/backend-set-input.test.cjs test/backend-tree-values.test.cjs
git commit -m "feat(backend): i riempimenti tardivi di DynamicCheck, jstree e Places chiamano wiSaveBar.absorb" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `4 files changed`.

### Task I4: Stile dell'isola, riserva in fondo e bottoni scuri disabilitati leggibili

Repo: **lib**.

Spec sez. 4 (isola, stati, fascia in fondo, `--wi-save-bar-reserve`, `@property`, `body:has(...)` per i popup, stampa, `prefers-reduced-motion`) e Q15. Il foglio nuovo entra nel bundle `backend/head.css` dopo gli altri stili del backend. In `header.css` i `.btn-dark` e `.btn-outline-dark` disabilitati del tema scuro oggi hanno sfondo e bordo `#212529` sul fondo scuro, e sono illeggibili; `scroll-padding-top: 50px` tiene i campi fuori da sotto `#topbar`. I casi nel browser sono nei test della Parte B.

**Files:**
- Create: `src/build/backend/css/save-bar.css`
- Modify: `src/build/backend/css/header.css:25-29, 46-51, 224` (bottoni scuri disabilitati nel tema scuro, `scroll-padding-top`)
- Modify: `src/export/backend/head.js:51` (import del foglio nuovo)
- Test: controllo della build fuori dal repo, nello Step 1 (non si committa)

**Interfaces:**
- Consumes: Le classi e gli attributi che `saveBar.js` (Parte C) mette sull'isola: `.wi-save-bar` con `data-wi-state` (`hidden`, `buttons`, `dirty-buttons`, `dirty-label`, `submitting`) e `data-wi-save-bar-keyboard`, `.wi-save-bar-island`, `.wi-save-bar-label` con `.bi-circle-fill`, `.wi-save-bar-actions`, `.wi-save-bar-btn`, e `html.wi-save-bar-on`. Le variabili Bootstrap `--bs-body-bg`, `--bs-border-color`, `--bs-box-shadow-lg`, `--bs-border-radius-xxl`, `--bs-warning`.
- Produces: `src/build/backend/css/save-bar.css` nel bundle `backend/head.css`; `--wi-save-bar-reserve` (sola lettura, unica variabile del contratto) su `html.wi-save-bar-on` solo a schermo, e `padding-bottom` di `#content` che la usa; i token non contrattuali `--wi-save-bar-zindex`, `--wi-save-bar-band`, `--wi-save-bar-offset`, `--wi-save-bar-border-color`; `html { scroll-padding-top: 50px; }`.

- [ ] **Step 1: Scrivi il controllo della build**

Il test compila la lib in una cartella temporanea, fuori dal repo, e cerca in `backend/head.css` (minificato) le regole nuove. `git status --porcelain dist` non deve stampare nulla: `dist/` non si tocca. Il controllo non si committa; si lancia allo Step 2 e allo Step 4:

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
O="$(mktemp -d)"
npx webpack --output-path "$O" 2>&1 | tail -1
git status --porcelain dist
node -e "
const assert = require('node:assert/strict');
const css = require('node:fs').readFileSync(process.argv[1] + '/backend/head.css', 'utf8');
for (const rule of ['.wi-save-bar{', '@property --wi-save-bar-band', 'html.wi-save-bar-on{', 'body:has(', 'html{scroll-padding-top:50px}', '--bs-btn-disabled-bg:var(--bs-btn-bg)']) assert.ok(css.includes(rule), 'head.css senza ' + rule);
console.log('head.css ok');
" "$O"
```

- [ ] **Step 2: Lancia il controllo e verifica che fallisca**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
O="$(mktemp -d)"
npx webpack --output-path "$O" 2>&1 | tail -1
git status --porcelain dist
node -e "
const assert = require('node:assert/strict');
const css = require('node:fs').readFileSync(process.argv[1] + '/backend/head.css', 'utf8');
for (const rule of ['.wi-save-bar{', '@property --wi-save-bar-band', 'html.wi-save-bar-on{', 'body:has(', 'html{scroll-padding-top:50px}', '--bs-btn-disabled-bg:var(--bs-btn-bg)']) assert.ok(css.includes(rule), 'head.css senza ' + rule);
console.log('head.css ok');
" "$O"
```

Expected: webpack stampa `compiled` (anche con i warning che ci sono già), `git status --porcelain dist` non stampa nulla, poi FAIL con `AssertionError [ERR_ASSERTION]: head.css senza .wi-save-bar{`.

- [ ] **Step 3: Aggiungi il foglio dell'isola e correggi `header.css`**

Il foglio nuovo, solo ASCII, con le proprietà senza rientro come gli altri CSS del backend:

Crea `src/build/backend/css/save-bar.css` con questo contenuto:

```css
/* Barra di salvataggio (isola) del backend: vedi docs/javascript/save-bar.md */

/* Un valore non valido della fascia torna a 96px */
@property --wi-save-bar-band {
syntax: '<length>';
inherits: true;
initial-value: 96px;
}

:root {
--wi-save-bar-zindex: 7;
--wi-save-bar-band: 96px;
--wi-save-bar-offset: 16px;
--wi-save-bar-border-color: var(--bs-border-color);
}

html[data-bs-theme="dark"] {
--wi-save-bar-border-color: rgba(var(--bs-emphasis-color-rgb), .3);
}

.wi-save-bar {
position: fixed;
left: 80px;
right: 0;
bottom: calc(env(safe-area-inset-bottom, 0px) + var(--wi-save-bar-offset));
z-index: var(--wi-save-bar-zindex);
display: flex;
justify-content: center;
padding-inline: 20px;
pointer-events: none;
transition: opacity .2s, transform .2s;
}

/* In uscita visibility aspetta la fine della dissolvenza */
.wi-save-bar[data-wi-state="hidden"],
.wi-save-bar[data-wi-state="submitting"],
.wi-save-bar[data-wi-save-bar-keyboard] {
visibility: hidden;
opacity: 0;
transform: translateY(12px);
transition: opacity .2s, transform .2s, visibility 0s .2s;
}

.wi-save-bar-island {
display: flex;
flex-wrap: wrap;
align-items: center;
justify-content: center;
gap: .5rem;
max-width: 100%;
padding: .5rem;
color: var(--bs-body-color);
background-color: var(--bs-body-bg);
border: var(--bs-border-width) solid var(--wi-save-bar-border-color);
border-radius: var(--bs-border-radius-xxl);
box-shadow: var(--bs-box-shadow-lg);
pointer-events: auto;
}

.wi-save-bar-label {
padding-inline: .75rem;
font-weight: 500;
}

.wi-save-bar-label .bi-circle-fill {
color: var(--bs-warning);
}

.wi-save-bar-actions {
display: flex;
flex-wrap: wrap;
justify-content: center;
gap: .5rem;
min-width: 0;
max-width: 100%;
}

.wi-save-bar-btn {
max-width: 100%;
}

.wi-save-bar-btn > * {
pointer-events: none;
}

.wi-save-bar[data-wi-state="buttons"] .wi-save-bar-label,
.wi-save-bar[data-wi-state="dirty-label"] .wi-save-bar-actions {
display: none;
}

/* Popup degli editor e gancio per i widget terzi: via subito */
body:has(.ce-popover--opened, .tc-popover--opened, [data-wi-save-bar-hide-when-open]) .wi-save-bar {
visibility: hidden;
opacity: 0;
transition: none;
}

body:has(.ce-popover--opened, .tc-popover--opened, [data-wi-save-bar-hide-when-open]) .wi-save-bar-island {
pointer-events: none;
}

@media (prefers-reduced-motion: reduce) {
.wi-save-bar[data-wi-state] {
transform: none;
}
}

@media (max-width: 768px) {
:root {
--wi-save-bar-offset: 12px;
}
.wi-save-bar {
left: 0;
}
}

/* Riserva in fondo: unica variabile del contratto, in sola lettura */
@media screen {
html.wi-save-bar-on {
--wi-save-bar-reserve: calc(var(--wi-save-bar-band) + env(safe-area-max-inset-bottom, 0px));
scroll-padding-bottom: var(--wi-save-bar-reserve);
}
}

#content {
padding-bottom: calc(20px + var(--wi-save-bar-reserve, 0px));
}

@media print {
.wi-save-bar {
display: none;
}
}
```

Importalo in `head.js` dopo gli stili del backend. Se la Parte C ha già aggiunto righe qui, il testo da sostituire resta la riga di `order.scss`:

In `src/export/backend/head.js`, riga 51, sostituisci:

```js
import '/src/build/backend/css/order.scss';
```

con:

```js
import '/src/build/backend/css/order.scss';
import '/src/build/backend/css/save-bar.css';
```

In `header.css`, `.btn-outline-dark` disabilitato nel tema scuro: sfondo trasparente e bordo del bottone:

In `src/build/backend/css/header.css`, righe 25-29, sostituisci:

```css
--bs-btn-disabled-bg: #212529;
--bs-btn-disabled-border-color: #212529;
}

html[data-bs-theme="light"] .btn-dark {
```

con:

```css
--bs-btn-disabled-bg: transparent;
--bs-btn-disabled-border-color: var(--bs-btn-border-color);
}

html[data-bs-theme="light"] .btn-dark {
```

`.btn-dark` disabilitato nel tema scuro: sfondo e bordo del bottone normale, che `opacity` di Bootstrap già attenua:

In `src/build/backend/css/header.css`, righe 46-51, sostituisci:

```css
--bs-btn-disabled-bg: #212529;
--bs-btn-disabled-border-color: #212529;
}


html {
```

con:

```css
--bs-btn-disabled-bg: var(--bs-btn-bg);
--bs-btn-disabled-border-color: var(--bs-btn-border-color);
}


html {
```

Lo scroll verso un'ancora o un campo non lo lascia sotto `#topbar`:

In `src/build/backend/css/header.css`, riga 224, sostituisci:

```css
wi-card { display: none; }
```

con:

```css
/* Lo scroll verso un campo lascia libera la topbar */
html { scroll-padding-top: 50px; }

wi-card { display: none; }
```

- [ ] **Step 4: Rilancia il controllo**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
O="$(mktemp -d)"
npx webpack --output-path "$O" 2>&1 | tail -1
git status --porcelain dist
node -e "
const assert = require('node:assert/strict');
const css = require('node:fs').readFileSync(process.argv[1] + '/backend/head.css', 'utf8');
for (const rule of ['.wi-save-bar{', '@property --wi-save-bar-band', 'html.wi-save-bar-on{', 'body:has(', 'html{scroll-padding-top:50px}', '--bs-btn-disabled-bg:var(--bs-btn-bg)']) assert.ok(css.includes(rule), 'head.css senza ' + rule);
console.log('head.css ok');
" "$O"
```

Expected: PASS con `head.css ok`; `git status --porcelain dist` non stampa nulla.

- [ ] **Step 5: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add src/build/backend/css/save-bar.css src/build/backend/css/header.css src/export/backend/head.js
git commit -m "feat(backend): stile dell'isola di salvataggio, riserva in fondo e bottoni scuri disabilitati leggibili" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `3 files changed` e nessun file di `dist/`.

### Task I5: Documentazione della barra, di `loadingSpinner(show)` e di Quill su `text-change`

Repo: **lib**.

Spec sez. "Documentazione" (lib). Va dopo la Parte C: descrive `saveBar.js`, `setUpSaveBar()` e `loadingSpinner(show)`. La pagina nuova e le modifiche sono in inglese e ASCII, tranne il `—` delle colonne vuote in `docs/reference/js-reference.md`, come nelle righe che ci sono già, e `docs/integrations/quill.md`, che è in italiano.

**Files:**
- Create: `docs/javascript/save-bar.md`
- Modify: `docs/SUMMARY.md:37`
- Modify: `docs/reference/data-attributes.md:96`
- Modify: `docs/reference/js-reference.md:15, 174`
- Modify: `docs/javascript/events.md:81, 89, 99`
- Modify: `docs/javascript/forms.md:146, 150`
- Modify: `docs/styles/css-variables.md:82, 93`
- Modify: `docs/integrations/quill.md:40-45, 51`
- Test: controllo dei documenti nello Step 1 (non si committa)

**Interfaces:**
- Consumes: Da I1-I4: Quill su `text-change` con `''` a vuoto, Editor.js che riscrive solo se cambia, `absorb()` nei riempimenti tardivi, `save-bar.css` con i token e `--wi-save-bar-reserve`. Dalla Parte C: `setUpSaveBar()` chiamata da `setUpPage()` prima di `loaded`, `window.wiSaveBar` (`changed`, `reset`, `isDirty`, `absorb`, `labels` con `group`, `unsaved`, `confirmOther`), l'evento `wi:save-bar:change`, gli attributi `data-wi-save-bar*`, `loadingSpinner(show)` in `backend/js/utility.js`, il test `test/backend-save-bar.test.cjs`. Dalla Parte B: il test `test/backend-save-bar.browser.test.cjs` e il `PLAYWRIGHT_MODULE`.
- Produces: `docs/javascript/save-bar.md` (contratto, API, regole per chi integra, limiti, aggiornamento dei siti con `wonder-image@^2.1.2-alpha.17`, test) e i rimandi da `SUMMARY.md`, `data-attributes.md`, `js-reference.md`, `events.md`, `forms.md`, `css-variables.md` e `quill.md`, che I6 (MANIFEST `docs`) e I7 (`AGENTS.md`) citano.

- [ ] **Step 1: Scrivi il controllo dei documenti**

Il controllo cerca nelle pagine le voci del contratto e verifica che le pagine inglesi toccate restino ASCII. Non si committa; si lancia allo Step 2 e allo Step 4:

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node <<'JS'
const assert = require('node:assert/strict');
const fs = require('node:fs');
const read = (file) => fs.readFileSync(file, 'utf8');
const has = (file, words) => {
    const text = read(file);
    for (const word of words) assert.ok(text.includes(word), file + ' senza ' + word);
    return text;
};
const ascii = (file) => assert.ok(/^[\x00-\x7F]*$/.test(read(file)), file + ' non ASCII');

has('docs/javascript/save-bar.md', ['data-wi-save-bar-dirty', 'data-wi-save-bar-ignore', 'data-wi-save-bar-hide-when-open', 'wi:save-bar:change', '--wi-save-bar-reserve', 'wiSaveBar?.reset(form)', 'absorb(el)', 'setUpSaveBar()', 'confirmOther', 'wonder-image@^2.1.2-alpha.17', 'backend-save-bar.browser.test.cjs']);
has('docs/SUMMARY.md', ['* [Save bar](javascript/save-bar.md)']);
has('docs/reference/data-attributes.md', ['## Save bar (backend)', '`data-wi-save-bar-hide-when-open`']);
has('docs/reference/js-reference.md', ['`loadingSpinner(show)`', '`setUpSaveBar()`', '`window.wiSaveBar.absorb(el)`']);
has('docs/javascript/events.md', ['`wi:save-bar:change`', 'save-bar.md']);
has('docs/javascript/forms.md', ['wiSaveBar?.reset(form)', 'save-bar.md']);
has('docs/styles/css-variables.md', ['--wi-save-bar-band', '--wi-save-bar-reserve']);
has('docs/integrations/quill.md', ['text-change', 'wiSaveBar', '.ql-editor']);
['docs/javascript/save-bar.md', 'docs/SUMMARY.md', 'docs/javascript/events.md'].forEach(ascii);
console.log('docs ok');
JS
```

- [ ] **Step 2: Lancia il controllo e verifica che fallisca**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node <<'JS'
const assert = require('node:assert/strict');
const fs = require('node:fs');
const read = (file) => fs.readFileSync(file, 'utf8');
const has = (file, words) => {
    const text = read(file);
    for (const word of words) assert.ok(text.includes(word), file + ' senza ' + word);
    return text;
};
const ascii = (file) => assert.ok(/^[\x00-\x7F]*$/.test(read(file)), file + ' non ASCII');

has('docs/javascript/save-bar.md', ['data-wi-save-bar-dirty', 'data-wi-save-bar-ignore', 'data-wi-save-bar-hide-when-open', 'wi:save-bar:change', '--wi-save-bar-reserve', 'wiSaveBar?.reset(form)', 'absorb(el)', 'setUpSaveBar()', 'confirmOther', 'wonder-image@^2.1.2-alpha.17', 'backend-save-bar.browser.test.cjs']);
has('docs/SUMMARY.md', ['* [Save bar](javascript/save-bar.md)']);
has('docs/reference/data-attributes.md', ['## Save bar (backend)', '`data-wi-save-bar-hide-when-open`']);
has('docs/reference/js-reference.md', ['`loadingSpinner(show)`', '`setUpSaveBar()`', '`window.wiSaveBar.absorb(el)`']);
has('docs/javascript/events.md', ['`wi:save-bar:change`', 'save-bar.md']);
has('docs/javascript/forms.md', ['wiSaveBar?.reset(form)', 'save-bar.md']);
has('docs/styles/css-variables.md', ['--wi-save-bar-band', '--wi-save-bar-reserve']);
has('docs/integrations/quill.md', ['text-change', 'wiSaveBar', '.ql-editor']);
['docs/javascript/save-bar.md', 'docs/SUMMARY.md', 'docs/javascript/events.md'].forEach(ascii);
console.log('docs ok');
JS
```

Expected: FAIL con `Error: ENOENT: no such file or directory, open 'docs/javascript/save-bar.md'`: la pagina non esiste ancora.

- [ ] **Step 3: Scrivi la pagina e i rimandi**

La pagina nuova:

Crea `docs/javascript/save-bar.md` con questo contenuto:

````markdown
# Save bar

Backend only. When the submit buttons of a tracked form leave the screen, a fixed island at the bottom shows copies of them. When the form has unsaved changes, the island shows "Operazione non salvata" and leaving the page asks for confirmation.

The code ships in `dist/backend/head.js` and `dist/backend/head.css`. `setUpPage()` calls `setUpSaveBar()` right before the `loaded` event. A page without tracked forms creates no island, no observers and no listeners.

## Markup contract

| Attribute | On | Meaning |
|---|---|---|
| `data-wi-save-bar` | `<form>` | Opt-in: the form is tracked. |
| `data-wi-save-bar-dirty` | `<form>` | The form starts unsaved, for example after a failed POST. Presence counts, not the value. Read only at start. |
| `data-wi-save-bar-ignore` | button, field or container | The button is not copied; the fields inside do not count as changes. |
| `data-wi-save-bar-hide-when-open` | any element in `body` | While the element exists the island hides. Use it on third-party popups. |

```html
<form method="post" data-wi-save-bar onsubmit="loadingSpinner()">
  <input type="text" name="name" value="Andrea">
  <button type="submit" name="upload" class="btn btn-dark wi-submit">Salva</button>
</form>
```

Copied buttons are the elements of `form.elements` whose `type` property is `submit` or that have the `.wi-submit` class, in page order, including buttons linked with `form="id"`. Buttons inside `.modal`, `.offcanvas` or `[data-wi-save-bar-ignore]`, and buttons that are not rendered, are skipped. Responsive `.offcanvas-{bp}` containers are not skipped automatically: add `data-wi-save-bar-ignore`.

A copy is a `type="button"` clone without `id`, `name`, `value`, `form*`, `on*` and `data-*` attributes. Clicking it calls `original.click()`, so the original `name`, `onclick` and delegated handlers run. A copy is disabled while its original is disabled.

## JavaScript API

| Member | Signature | Behavior |
|---|---|---|
| `wiSaveBar.changed` | `(elOrForm?)` | "I wrote a value without an event": schedules a new comparison of every tracked form. It never forces the dirty state. |
| `wiSaveBar.reset` | `(form)` | The current snapshot becomes the baseline. Clears the dirty flag from `data-wi-save-bar-dirty` and the leave warning for that form. |
| `wiSaveBar.isDirty` | `(form)` returns boolean | Current state of the form. |
| `wiSaveBar.absorb` | `(el)` | The values of the fields inside `el` become the new baseline; the rest does not. |
| `wiSaveBar.labels` | `{ group, unsaved, confirmOther }` | Texts, read when the island is built. |
| `setUpSaveBar` | `()` | Start step, idempotent, called by `setUpPage()`. |
| event `wi:save-bar:change` | on the form, `detail: { dirty }` | Fires only on clean/dirty transitions, `reset()` included. |

Called on an untracked form or on `null`, the four functions throw nothing, return `false` and fire no event.

```js
form.addEventListener('wi:save-bar:change', (event) => {
  console.log(event.detail.dirty);
});
```

## Rules for integrators

- Submitting a tracked form from a script: call `wiSaveBar?.reset(form)` before `form.submit()`. `form.submit()` fires no `submit` event, so without `reset` the leave warning appears.
- Saving through AJAX: call `wiSaveBar?.reset(form)` in the success callback.
- Use `absorb(el)` only for values not written by the user, on the narrowest container, never on the whole form.
- `formdata` listeners must have no side effects: the save bar builds `new FormData(form)` to compare.
- A widget that writes a value without firing `input` or `change` calls `wiSaveBar?.changed(el)`.
- Code that can also run without the save bar, such as the frontend or a site still on an older lib, writes `window.wiSaveBar?.`: a bare `wiSaveBar` throws a `ReferenceError` where the global does not exist.

## How changes are detected

- A snapshot is `new FormData(form)`: exactly what the save would send, in order. Disabled fields, unchecked checkboxes and buttons are not part of it.
- AutoNumeric fields compare the numeric value, files compare name, size and type, and names made only of checkboxes compare as sets.
- Fields inside `.modal` or `[data-wi-save-bar-ignore]` do not count. A name present both inside and outside an excluded zone is kept whole, with a console warning.
- The baseline is taken on the first frame after start. Until the first trusted user interaction, every difference becomes the new baseline. Known late fills call `absorb()`: DynamicCheck options loaded via AJAX, jstree `ready` and Google Places enabling its field.
- Quill writes the textarea only on `text-change` and calls `wiSaveBar?.changed()`. Editor.js rewrites the textarea only when the content really changes.

## Submit and leave

- `preventPageExit` stays registered on `beforeunload` while at least one tracked form is dirty and not being submitted.
- Submitting a form that navigates the window while another tracked form is dirty asks `confirm(labels.confirmOther)`. Cancel keeps the page and hides the spinner with `loadingSpinner(false)`.
- A page restored from the back/forward cache after a submit reloads with a GET, never a new POST.
- The island hides while a text field has the focus and a virtual keyboard is likely open (`pointer: coarse`, or a visual viewport below 75% of the window).

## Texts

```js
window.wiSaveBar.labels.unsaved = 'Unsaved changes';
```

| Key | Default |
|---|---|
| `group` | `Barra di salvataggio` (accessible name of the island) |
| `unsaved` | `Operazione non salvata` |
| `confirmOther` | `Ci sono modifiche non salvate in un altro modulo della pagina. Continuare e perderle?` |

Change them after the head bundle and before `setUpPage()` runs.

## CSS

- `--wi-save-bar-reserve` is the only CSS variable in the contract. It is read-only: the space reserved at the bottom of the page while a tracked form exists. Otherwise it is not defined, so read it as `var(--wi-save-bar-reserve, 0px)`.
- `--wi-save-bar-zindex`, `--wi-save-bar-band`, `--wi-save-bar-offset` and `--wi-save-bar-border-color` can be customized on `:root` but are not a stable contract. See [CSS Variables](../styles/css-variables.md).
- The `.wi-save-bar*` classes, `html.wi-save-bar-on`, `data-wi-state` and `data-wi-save-bar-keyboard` are internal and may change.

## Known limits

- AJAX saves: changes made during the request count as saved after `reset()`.
- Popups not listed in the CSS can end up under the island: add `data-wi-save-bar-hide-when-open`.
- Editor.js reports changes with a delay: leaving within about 400ms of the first change gives no warning.
- Firefox restores unsaved values on reload unless the form has `autocomplete="off"`, and the island takes them as the baseline.
- Without user activation, for example right after a failed POST, the browser may skip the leave dialog.
- An island on three rows, with very long labels, can overflow the reserved band.
- Without JavaScript, or without `IntersectionObserver`, `ResizeObserver`, `MutationObserver` and `inert`, only the original buttons remain.

## Updating a site

- `wonder-image` range already `^2.1.2-alpha.*`: `composer update` is enough, because `forge config` runs `npm install`.
- Other ranges (`^2.1.1-alpha.*`, `^2.0.x`, `^2.1.0`): run `npm install wonder-image@^2.1.2-alpha.17`, then commit `package.json` and `package-lock.json`, because CI uses `npm ci`.

## Tests

```sh
node test/backend-save-bar.test.cjs
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" node test/backend-save-bar.browser.test.cjs
```

The unit test runs in `npm test`. The browser test needs Playwright and Chrome; see `AGENTS.md` for the one-time install.

## Common mistakes

- Calling `form.submit()` without `wiSaveBar?.reset(form)`: the leave warning appears.
- Calling `absorb(form)` on the whole form: real user changes become the baseline.
- Writing into the `.ql-editor` DOM directly: use the Quill API, which fires `text-change`.
- Styling `.wi-save-bar*` from a site: the classes are internal.

## Source

- `src/build/backend/js/form/saveBar.js`
- `src/build/backend/css/save-bar.css`
- `src/build/backend/js/pageSetUp.js`
````

Le modifiche di uno stesso file vanno fatte nell'ordine scritto: i numeri di riga qui sotto contano le righe già aggiunte sopra, mentre la lista **Files** dà quelli originali (per esempio `events.md` 81, 89, 99 diventano 81, 97, 109).

Voce nell'indice, dopo Forms:

In `docs/SUMMARY.md`, riga 37, sostituisci:

```markdown
* [Forms](javascript/forms.md)
```

con:

```markdown
* [Forms](javascript/forms.md)
* [Save bar](javascript/save-bar.md)
```

I quattro attributi, prima della copertura ancora da fare:

In `docs/reference/data-attributes.md`, riga 96, sostituisci:

```markdown
## Pending source coverage
```

con:

```markdown
## Save bar (backend)

| Attribute | Values | Read by | Source |
|---|---|---|---|
| `data-wi-save-bar` | presence, on `<form>` | `setUpSaveBar` | `backend/js/form/saveBar.js` |
| `data-wi-save-bar-dirty` | presence, on `<form>`; read only at start | same | same |
| `data-wi-save-bar-ignore` | presence, on a button, field or container | same | same |
| `data-wi-save-bar-hide-when-open` | presence, on any element in `body` | CSS `body:has(...)` | `backend/css/save-bar.css` |

See [Save bar](../javascript/save-bar.md).

## Pending source coverage
```

La firma nuova di `loadingSpinner` del backend, sotto quella del frontend, e l'API prima del ponte globale:

In `docs/reference/js-reference.md`, riga 15, sostituisci:

```markdown
| `loadingSpinner(action='toggle')` | string \| boolean | — | `frontend/js/utility.js` |
```

con:

```markdown
| `loadingSpinner(action='toggle')` | string \| boolean | — | `frontend/js/utility.js` |
| `loadingSpinner(show)` | boolean, optional: `true` shows, `false` hides, none toggles | — | `backend/js/utility.js` |
```

In `docs/reference/js-reference.md`, riga 175, sostituisci:

```markdown
## Global bridge
```

con:

```markdown
## Save bar (backend)

| Function | Parameters | Returns | Source |
|---|---|---|---|
| `setUpSaveBar()` | — | — | `backend/js/form/saveBar.js` |
| `window.wiSaveBar.changed(elOrForm?)` | Element, optional | — | same |
| `window.wiSaveBar.reset(form)` | HTMLFormElement | — | same |
| `window.wiSaveBar.isDirty(form)` | HTMLFormElement | boolean | same |
| `window.wiSaveBar.absorb(el)` | Element | — | same |
| `window.wiSaveBar.labels` | `{ group, unsaved, confirmOther }` | — | same |

On an untracked form or on `null` the four functions return `false`. See [Save bar](../javascript/save-bar.md).

## Global bridge
```

L'evento, la nota su `preventPageExit` e il sorgente:

In `docs/javascript/events.md`, riga 81, sostituisci:

```markdown
## Page exit guard
```

con:

```markdown
## Save bar (backend)

| Event | Target | `detail` | When |
|---|---|---|---|
| `wi:save-bar:change` | tracked `<form>` | `{ dirty }` | only on clean/dirty transitions, `reset()` included |

See [Save bar](save-bar.md).

## Page exit guard
```

In `docs/javascript/events.md`, riga 97, sostituisci:

```markdown
It calls `event.preventDefault()` + sets `event.returnValue = true` to trigger the browser's "leave page?" dialog.
```

con:

```markdown
It calls `event.preventDefault()` + sets `event.returnValue = true` to trigger the browser's "leave page?" dialog.

In the backend the save bar adds and removes this handler itself while a `form[data-wi-save-bar]` has unsaved changes: do not register it again for tracked forms.
```

In `docs/javascript/events.md`, riga 109, sostituisci:

```markdown
- `src/build/global/js/utility.js`
```

con:

```markdown
- `src/build/global/js/utility.js`
- `src/build/backend/js/form/saveBar.js`
```

I form tracciati e il nuovo errore comune:

In `docs/javascript/forms.md`, riga 146, sostituisci:

```markdown
## Common mistakes
```

con:

````markdown
## Tracked backend forms

In the backend a `form[data-wi-save-bar]` is tracked by the [save bar](save-bar.md). Code that submits or saves such a form from a script marks its values as saved:

```js
window.wiSaveBar?.reset(form);
form.submit();
```

After an AJAX save, call `window.wiSaveBar?.reset(form)` in the success callback. The frontend bundle has no save bar and `window.wiSaveBar` is undefined there: keep the `window.` prefix, because a bare `wiSaveBar?.` throws a `ReferenceError` when the global does not exist.

## Common mistakes
````

In `docs/javascript/forms.md`, riga 161, sostituisci:

```markdown
- Forgetting the hidden `g-recaptcha-token` / `g-recaptcha-action` inputs. `setRecaptchaInput` writes into them; if they don't exist, you get `Cannot set property 'value' of null`.
```

con:

```markdown
- Forgetting the hidden `g-recaptcha-token` / `g-recaptcha-action` inputs. `setRecaptchaInput` writes into them; if they don't exist, you get `Cannot set property 'value' of null`.
- Calling `form.submit()` on a tracked backend form without `wiSaveBar?.reset(form)` first: the leave warning appears.
```

I token del backend, la variabile del contratto e il sorgente:

In `docs/styles/css-variables.md`, riga 82, sostituisci:

```markdown
## Common mistakes
```

con:

```markdown
## Backend save bar tokens

`src/build/backend/css/save-bar.css` declares these on `:root`. A site can override them, but they are not a stable contract:

| Variable | Default | Used for |
|---|---|---|
| `--wi-save-bar-zindex` | `7` | stacking of the island: above the content, below `#sidebar` (8) and `#topbar` (9) |
| `--wi-save-bar-band` | `96px` | height of the bottom band where the island sits; an invalid length falls back to `96px` |
| `--wi-save-bar-offset` | `16px`, `12px` at <=768px | distance of the island from the bottom edge |
| `--wi-save-bar-border-color` | `var(--bs-border-color)`, `rgba(var(--bs-emphasis-color-rgb), .3)` in the dark theme | island border |

`--wi-save-bar-reserve` is set by the lib and is read-only: the space reserved at the bottom of the page while a tracked form exists. It is the only save bar variable in the contract; read it as `var(--wi-save-bar-reserve, 0px)`. See [Save bar](../javascript/save-bar.md).

## Common mistakes
```

In `docs/styles/css-variables.md`, riga 106, sostituisci:

```markdown
- `src/build/frontend/css/components/*.css`
```

con:

```markdown
- `src/build/frontend/css/components/*.css`
- `src/build/backend/css/save-bar.css`
```

In italiano come il resto della pagina: Quill solo su `text-change`, vuoto `''`, Editor.js solo se cambia, e il nuovo errore comune:

In `docs/integrations/quill.md`, righe 40-45, sostituisci:

```markdown
- **Quill** scrive nella textarea l'`innerHTML` dell'editor passato da
  `wiSanitizeHtml()`: DOMPurify con i tag `p br span strong b em i u s strike del
  ol ul li a blockquote` e gli attributi `href target rel class`. Senza DOMPurify
  toglie tutto il markup. Il server deve comunque ripulire l'HTML: il browser non
  è una garanzia.
- **Editor.js** scrive il JSON dei blocchi (`outputData.blocks`).
```

con:

```markdown
- **Quill** scrive nella textarea solo su `text-change`, qualunque sia la
  sorgente (`user` o `api`), e poi chiama `wiSaveBar?.changed()` per la
  [barra di salvataggio](../javascript/save-bar.md). Il valore è l'`innerHTML`
  dell'editor passato da `wiSanitizeHtml()`: DOMPurify con i tag `p br span
  strong b em i u s strike del ol ul li a blockquote` e gli attributi
  `href target rel class`. Senza DOMPurify toglie tutto il markup. Il server
  deve comunque ripulire l'HTML: il browser non è una garanzia.
- Un Quill vuoto vale `''`, non `<p><br></p>`: un campo obbligatorio in cui si
  scrive e poi si cancella torna vuoto, e `check()` disabilita Salva.
- **Editor.js** scrive il JSON dei blocchi (`outputData.blocks`) solo se il
  contenuto cambia davvero: se torna uguale a quello di partenza, a meno di
  `id`, `time` e `version`, rimette il JSON originale. La modifica arriva circa
  400ms dopo, perché Editor.js la segnala in ritardo.
```

In `docs/integrations/quill.md`, riga 58, sostituisci:

```markdown
- Leggere `data-wi-value` con il solo `atob()`: le lettere accentate si rovinano.
```

con:

```markdown
- Leggere `data-wi-value` con il solo `atob()`: le lettere accentate si rovinano.
- Scrivere direttamente nel DOM di `.ql-editor`: la textarea non si aggiorna più.
  Si usa l'API di Quill, che lancia `text-change`.
```

- [ ] **Step 4: Rilancia il controllo**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node <<'JS'
const assert = require('node:assert/strict');
const fs = require('node:fs');
const read = (file) => fs.readFileSync(file, 'utf8');
const has = (file, words) => {
    const text = read(file);
    for (const word of words) assert.ok(text.includes(word), file + ' senza ' + word);
    return text;
};
const ascii = (file) => assert.ok(/^[\x00-\x7F]*$/.test(read(file)), file + ' non ASCII');

has('docs/javascript/save-bar.md', ['data-wi-save-bar-dirty', 'data-wi-save-bar-ignore', 'data-wi-save-bar-hide-when-open', 'wi:save-bar:change', '--wi-save-bar-reserve', 'wiSaveBar?.reset(form)', 'absorb(el)', 'setUpSaveBar()', 'confirmOther', 'wonder-image@^2.1.2-alpha.17', 'backend-save-bar.browser.test.cjs']);
has('docs/SUMMARY.md', ['* [Save bar](javascript/save-bar.md)']);
has('docs/reference/data-attributes.md', ['## Save bar (backend)', '`data-wi-save-bar-hide-when-open`']);
has('docs/reference/js-reference.md', ['`loadingSpinner(show)`', '`setUpSaveBar()`', '`window.wiSaveBar.absorb(el)`']);
has('docs/javascript/events.md', ['`wi:save-bar:change`', 'save-bar.md']);
has('docs/javascript/forms.md', ['wiSaveBar?.reset(form)', 'save-bar.md']);
has('docs/styles/css-variables.md', ['--wi-save-bar-band', '--wi-save-bar-reserve']);
has('docs/integrations/quill.md', ['text-change', 'wiSaveBar', '.ql-editor']);
['docs/javascript/save-bar.md', 'docs/SUMMARY.md', 'docs/javascript/events.md'].forEach(ascii);
console.log('docs ok');
JS
```

Expected: PASS con `docs ok`.

- [ ] **Step 5: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add docs/javascript/save-bar.md docs/SUMMARY.md docs/reference/data-attributes.md docs/reference/js-reference.md docs/javascript/events.md docs/javascript/forms.md docs/styles/css-variables.md docs/integrations/quill.md
git commit -m "docs: barra di salvataggio del backend, loadingSpinner(show) e Quill su text-change" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `8 files changed`.

### Task I6: Voce `save_bar` nel MANIFEST e test dei riferimenti FilePond in `npm test`

Repo: **lib**.

Spec sez. "Documentazione" (MANIFEST) e sez. 6, test U38-U39. Va dopo la Parte C e dopo I5: il test del MANIFEST controlla che esistano `saveBar.js`, `save-bar.css` e `docs/javascript/save-bar.md`. `test/backend/file-references.test.cjs` sta in una cartella ignorata da `.gitignore` (`test/*`, con eccezione solo per `test/*.test.cjs`) e non gira in `npm test`: qui entra nel repo come `test/backend-file-references.test.cjs`, uguale tranne il percorso di `set.js`.

**Files:**
- Create: `test/backend-file-references.test.cjs` (U38, il test spostato)
- Create: `test/manifest.test.cjs` (U39)
- Modify: `MANIFEST.json:122-123` (dopo `components.deferred`)
- Modify: `package.json:11` (script `test`)

**Interfaces:**
- Consumes: `setUploaderFileReferences()` di `set.js`; `components.deferred` del MANIFEST come modello; `src/build/backend/js/form/saveBar.js` della Parte C, `src/build/backend/css/save-bar.css` di I4 e `docs/javascript/save-bar.md` di I5; la catena `npm test` con `node test/backend-set-input.test.cjs && ` e `node test/source-map-paths.test.cjs` come nel `main` (la Parte C aggiunge `node test/backend-save-bar.test.cjs` senza toccare questi due pezzi).
- Produces: `components.save_bar` in `MANIFEST.json` con `js`, `css`, `global`, `contract`, `docs`, `internal_classes`; `npm test` che lancia anche `test/backend-file-references.test.cjs` (dopo `backend-set-input`) e `test/manifest.test.cjs` (prima di `source-map-paths`).

- [ ] **Step 1: Scrivi i due test**

Il test dei riferimenti FilePond, spostato: rispetto a `test/backend/file-references.test.cjs` cambia solo la riga 9, che ora punta a `../src/build/backend/js/form/set.js`. Se nel worktree c'è ancora la copia ignorata in `test/backend/`, non si tocca: nel checkout principale si toglie solo su richiesta esplicita dell'utente.

Crea `test/backend-file-references.test.cjs` con questo contenuto:

```js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { File } = require('node:buffer');

const scope = {};
vm.createContext(scope);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../src/build/backend/js/form/set.js'), 'utf8'), scope);

async function main() {
    const listeners = new Map();
    const events = new Map();
    const form = {
        addEventListener: (name, callback) => listeners.set(name, callback),
        removeEventListener: name => listeners.delete(name),
    };
    let items = [{ id: 'old', source: '/old.png' }];
    const pond = {
        name: 'rows[7][photos][]', disabled: false,
        element: { isConnected: true, closest: selector => selector === 'form' ? form : null },
        on: (name, callback) => events.set(name, callback),
        getFiles: () => items,
    };
    scope.setUploaderFileReferences(pond, [{source:'/old.png'}], ['old.png']);
    const serialize = () => {
        const formData = new FormData();
        formData.append(pond.name, new File(['old bytes'], 'old.png'));
        listeners.get('formdata')({formData});
        return formData;
    };

    let data = serialize();
    assert.equal(data.getAll(pond.name).length, 0);
    assert.equal(data.get('rows[7][photos__wi_files]'), '["old.png"]');

    const original = new File(['original'], 'new.png');
    const transformed = new File(['cropped output'], 'new.png');
    items = [{id:'new', source:original, file:original}, ...items];
    events.get('preparefile')(items[0], transformed);
    data = serialize();
    assert.equal(data.get('rows[7][photos__wi_files]'), '[0,"old.png"]');
    assert.equal(await data.get(pond.name).text(), 'cropped output');

    items = [];
    data = serialize();
    assert.equal(data.get('rows[7][photos__wi_files]'), '[]');
    assert.equal(data.getAll(pond.name).length, 0);

    pond.disabled = true;
    assert.equal(serialize().has('rows[7][photos__wi_files]'), false);
    pond.disabled = false;
    pond.element.isConnected = false;
    assert.equal(serialize().has('rows[7][photos__wi_files]'), false);
    events.get('destroy')();
    assert.equal(listeners.size, 0);
    console.log('PASS: existing references, nested names, order, prepared output, removal, disabled/detached widgets, cleanup');
}

main().catch(error => { console.error(error); process.exitCode = 1; });
```

Il test del MANIFEST:

Crea `test/manifest.test.cjs` con questo contenuto:

```js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'MANIFEST.json'), 'utf8'));

// U39: la voce della barra di salvataggio, sul modello di components.deferred.
const saveBar = manifest.components.save_bar;
assert.ok(saveBar, 'MANIFEST senza components.save_bar');
assert.deepEqual(Object.keys(saveBar).sort(), ['contract', 'css', 'docs', 'global', 'internal_classes', 'js']);
assert.equal(saveBar.js, 'src/build/backend/js/form/saveBar.js');
assert.equal(saveBar.css, 'src/build/backend/css/save-bar.css');
assert.equal(saveBar.global, 'window.wiSaveBar');
assert.equal(saveBar.docs, 'docs/javascript/save-bar.md');
assert.deepEqual(saveBar.internal_classes, ['.wi-save-bar*', 'html.wi-save-bar-on']);
for (const word of ['data-wi-save-bar', 'wi:save-bar:change', '--wi-save-bar-reserve']) {
    assert.ok(saveBar.contract.includes(word), 'contract senza ' + word);
}
for (const key of ['js', 'css', 'docs']) {
    assert.ok(fs.existsSync(path.join(root, saveBar[key])), key + ': ' + saveBar[key] + ' non esiste');
}

console.log('manifest: ok');
```

- [ ] **Step 2: Lancia i test e il controllo della catena, e verifica che falliscano**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/backend-file-references.test.cjs
node test/manifest.test.cjs
node -e "const t = require('./package.json').scripts.test; for (const f of ['test/backend-file-references.test.cjs', 'test/manifest.test.cjs']) require('node:assert/strict').ok(t.includes('node ' + f + ' && '), 'npm test senza ' + f); console.log('npm test ok');"
```

Expected: `test/backend-file-references.test.cjs` passa già (`PASS: existing references, ...`), perché il codice c'è; `test/manifest.test.cjs` FAIL con `AssertionError [ERR_ASSERTION]: MANIFEST senza components.save_bar`; il controllo della catena FAIL con `AssertionError [ERR_ASSERTION]: npm test senza test/backend-file-references.test.cjs`.

- [ ] **Step 3: Aggiungi la voce nel MANIFEST e i due test alla catena**

La voce dopo `components.deferred`, sul suo modello, con le chiavi nuove `global` e `internal_classes`:

In `MANIFEST.json`, righe 122-123, sostituisci:

```json
      "docs": "docs/deferred-content.md"
    },
```

con:

```json
      "docs": "docs/deferred-content.md"
    },
    "save_bar": {
      "js": "src/build/backend/js/form/saveBar.js",
      "css": "src/build/backend/css/save-bar.css",
      "global": "window.wiSaveBar",
      "contract": "Backend only: form[data-wi-save-bar] (opt-in), data-wi-save-bar-dirty, data-wi-save-bar-ignore, data-wi-save-bar-hide-when-open; window.wiSaveBar.changed/reset/isDirty/absorb/labels; event wi:save-bar:change {dirty}; CSS var --wi-save-bar-reserve (read-only); internal classes are not stable",
      "docs": "docs/javascript/save-bar.md",
      "internal_classes": [".wi-save-bar*", "html.wi-save-bar-on"]
    },
```

In `package.json` la riga 11 (`"test"`) cambia anche nella Parte C, quindi si sostituiscono due pezzi che vi compaiono una volta sola: `node test/backend-set-input.test.cjs && ` diventa `node test/backend-set-input.test.cjs && node test/backend-file-references.test.cjs && `, e `node test/source-map-paths.test.cjs` diventa `node test/manifest.test.cjs && node test/source-map-paths.test.cjs`. Il comando fallisce senza scrivere se un pezzo non è unico, e `grep -c` stampa `1`:

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node -e "
const fs = require('node:fs');
let text = fs.readFileSync('package.json', 'utf8');
for (const [from, to] of [
    ['node test/backend-set-input.test.cjs && ', 'node test/backend-set-input.test.cjs && node test/backend-file-references.test.cjs && '],
    ['node test/source-map-paths.test.cjs', 'node test/manifest.test.cjs && node test/source-map-paths.test.cjs'],
]) {
    if (text.split(from).length !== 2) throw new Error('package.json: testo non unico: ' + from);
    text = text.replace(from, to);
}
fs.writeFileSync('package.json', text);
"
grep -c 'backend-file-references.test.cjs && .*manifest.test.cjs && node test/source-map-paths' package.json
```

Expected: `1`.

- [ ] **Step 4: Rilancia i test e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node test/manifest.test.cjs
node -e "const t = require('./package.json').scripts.test; for (const f of ['test/backend-file-references.test.cjs', 'test/manifest.test.cjs']) require('node:assert/strict').ok(t.includes('node ' + f + ' && '), 'npm test senza ' + f); console.log('npm test ok');"
npm test
```

Expected: PASS con `manifest: ok` e `npm test ok`; `npm test` stampa anche `PASS: existing references, ...` e `manifest: ok`, e finisce con `backend-save-bar: N tests passed` della Parte C, senza errori.

- [ ] **Step 5: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add test/backend-file-references.test.cjs test/manifest.test.cjs MANIFEST.json package.json
git commit -m "feat(backend): voce save_bar nel MANIFEST e test dei riferimenti FilePond in npm test" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `4 files changed`.

### Task I7: `AGENTS.md` e CHANGELOG per la barra di salvataggio

Repo: **lib**.

Spec sez. "Documentazione" (lib: `AGENTS.md` e CHANGELOG). Va dopo I5 e I6. La lib non ha un CHANGELOG: `CHANGELOG.md` nasce qui con una sola sezione `Unreleased`, in inglese e ASCII. Il punto in `AGENTS.md` va dopo quello di DeferredContent, con i comandi per i test e per la build.

**Files:**
- Modify: `AGENTS.md:63` (dopo il punto di DeferredContent)
- Create: `CHANGELOG.md`
- Test: controllo di `AGENTS.md` e `CHANGELOG.md` nello Step 1 (non si committa)

**Interfaces:**
- Consumes: `docs/javascript/save-bar.md` di I5; `test/backend-save-bar.test.cjs` della Parte C; `test/backend-save-bar.browser.test.cjs` e il `PLAYWRIGHT_MODULE` della Parte B; `components.save_bar` e i test di I6.
- Produces: Il punto sulla barra in `AGENTS.md` (contratto, regole per chi integra, test unitario e nel browser con l'installazione di Playwright una tantum, build fuori da `dist/`) e `CHANGELOG.md` con Added, Changed e Fixed della barra.

- [ ] **Step 1: Scrivi il controllo di `AGENTS.md` e `CHANGELOG.md`**

Il controllo cerca in `AGENTS.md` i comandi e il contratto, e in `CHANGELOG.md` le voci della spec (`''` di Quill vuoto, `.ql-editor`, `loadingSpinner(show)`, Salva, `scroll-padding-top`). Non si committa; si lancia allo Step 2 e allo Step 4:

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node <<'JS'
const assert = require('node:assert/strict');
const fs = require('node:fs');
const agents = fs.readFileSync('AGENTS.md', 'utf8');
for (const s of ['setUpSaveBar()', 'wiSaveBar?.reset(form)', '--wi-save-bar-reserve', 'node test/backend-save-bar.test.cjs', 'PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm i --prefix "$HOME/.cache/wonder-tooling/playwright" playwright-core', 'node -e "require(process.env.PLAYWRIGHT_MODULE).chromium"', 'node test/backend-save-bar.browser.test.cjs', 'npx webpack --output-path "$(mktemp -d)"', 'docs/javascript/save-bar.md']) {
    assert.ok(agents.includes(s), 'AGENTS.md senza ' + s);
}
const log = fs.readFileSync('CHANGELOG.md', 'utf8');
assert.ok(/^[\x00-\x7F]*$/.test(log), 'CHANGELOG.md non ASCII');
for (const s of ['## Unreleased', '### Added', '### Changed', '### Fixed', "`''`", '.ql-editor', 'loadingSpinner(show)', 'Salva', 'scroll-padding-top']) {
    assert.ok(log.includes(s), 'CHANGELOG.md senza ' + s);
}
console.log('agents e changelog ok');
JS
```

- [ ] **Step 2: Lancia il controllo e verifica che fallisca**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node <<'JS'
const assert = require('node:assert/strict');
const fs = require('node:fs');
const agents = fs.readFileSync('AGENTS.md', 'utf8');
for (const s of ['setUpSaveBar()', 'wiSaveBar?.reset(form)', '--wi-save-bar-reserve', 'node test/backend-save-bar.test.cjs', 'PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm i --prefix "$HOME/.cache/wonder-tooling/playwright" playwright-core', 'node -e "require(process.env.PLAYWRIGHT_MODULE).chromium"', 'node test/backend-save-bar.browser.test.cjs', 'npx webpack --output-path "$(mktemp -d)"', 'docs/javascript/save-bar.md']) {
    assert.ok(agents.includes(s), 'AGENTS.md senza ' + s);
}
const log = fs.readFileSync('CHANGELOG.md', 'utf8');
assert.ok(/^[\x00-\x7F]*$/.test(log), 'CHANGELOG.md non ASCII');
for (const s of ['## Unreleased', '### Added', '### Changed', '### Fixed', "`''`", '.ql-editor', 'loadingSpinner(show)', 'Salva', 'scroll-padding-top']) {
    assert.ok(log.includes(s), 'CHANGELOG.md senza ' + s);
}
console.log('agents e changelog ok');
JS
```

Expected: FAIL con `AssertionError [ERR_ASSERTION]: AGENTS.md senza setUpSaveBar()`.

- [ ] **Step 3: Aggiorna `AGENTS.md` e crea il CHANGELOG**

In `AGENTS.md`, dopo il punto di DeferredContent:

In `AGENTS.md`, riga 63, sostituisci la fine della riga:

```markdown
Run `test/deferred-content.browser.test.cjs` with Playwright and Chrome; see `docs/deferred-content.md`.
```

con:

```markdown
Run `test/deferred-content.browser.test.cjs` with Playwright and Chrome; see `docs/deferred-content.md`.

- The backend save bar (`src/build/backend/js/form/saveBar.js`,
  `src/build/backend/css/save-bar.css`) ships in the backend head bundle and
  `setUpPage()` starts it with `setUpSaveBar()` right before `loaded`. The
  contract is the `data-wi-save-bar*` attributes, `window.wiSaveBar`, the
  `wi:save-bar:change` event and the read-only `--wi-save-bar-reserve`, the
  only CSS variable in it; `.wi-save-bar*` and `html.wi-save-bar-on` are
  internal. Scripts call `window.wiSaveBar?.reset(form)` before
  `form.submit()` and after an AJAX save (the `window.` prefix keeps code that
  also runs without the save bar from throwing); `absorb()` is only for values
  the user did not write; `formdata` listeners must have no side effects. Run
  `node test/backend-save-bar.test.cjs`. Browser test: install once with
  `PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm i --prefix "$HOME/.cache/wonder-tooling/playwright" playwright-core`,
  set `PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core"`,
  check it with `node -e "require(process.env.PLAYWRIGHT_MODULE).chromium"`
  and run `node test/backend-save-bar.browser.test.cjs`. Check the build with
  `npx webpack --output-path "$(mktemp -d)"`: `dist/` is rebuilt and
  committed by `npm run release`. See `docs/javascript/save-bar.md`.
```

Il CHANGELOG, solo ASCII:

Crea `CHANGELOG.md` con questo contenuto:

```markdown
# Changelog

## Unreleased

### Added
- Backend save bar: when the submit buttons of a `form[data-wi-save-bar]`
  leave the screen, a fixed island at the bottom shows copies of them; while
  the form has unsaved changes it shows "Operazione non salvata" and leaving
  the page asks for confirmation. API `window.wiSaveBar` (`changed`, `reset`,
  `isDirty`, `absorb`, `labels`), event `wi:save-bar:change`, read-only CSS
  variable `--wi-save-bar-reserve`. See `docs/javascript/save-bar.md`.
- `components.save_bar` in `MANIFEST.json`, with the new keys `global` and
  `internal_classes`.

### Changed
- An empty Quill editor writes `''` to its textarea instead of `<p><br></p>`:
  a required Quill field typed in and then cleared disables Salva again.
- Quill writes its textarea only on `text-change`. Writing directly into the
  `.ql-editor` DOM is no longer supported: use the Quill API.
- Editor.js rewrites its textarea only when the content really changes.
- Backend `loadingSpinner(show)` accepts an optional argument: `true` shows,
  `false` hides; without an argument it toggles as before.
- `test/backend/file-references.test.cjs` moved to
  `test/backend-file-references.test.cjs` and runs in `npm test`.

### Fixed
- Disabled `.btn-dark` and `.btn-outline-dark` are readable in the backend
  dark theme.
- The backend sets `scroll-padding-top: 50px` on `html`, so scrolling to an
  anchor or a field no longer hides it under `#topbar`.
```

- [ ] **Step 4: Rilancia il controllo**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
node <<'JS'
const assert = require('node:assert/strict');
const fs = require('node:fs');
const agents = fs.readFileSync('AGENTS.md', 'utf8');
for (const s of ['setUpSaveBar()', 'wiSaveBar?.reset(form)', '--wi-save-bar-reserve', 'node test/backend-save-bar.test.cjs', 'PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm i --prefix "$HOME/.cache/wonder-tooling/playwright" playwright-core', 'node -e "require(process.env.PLAYWRIGHT_MODULE).chromium"', 'node test/backend-save-bar.browser.test.cjs', 'npx webpack --output-path "$(mktemp -d)"', 'docs/javascript/save-bar.md']) {
    assert.ok(agents.includes(s), 'AGENTS.md senza ' + s);
}
const log = fs.readFileSync('CHANGELOG.md', 'utf8');
assert.ok(/^[\x00-\x7F]*$/.test(log), 'CHANGELOG.md non ASCII');
for (const s of ['## Unreleased', '### Added', '### Changed', '### Fixed', "`''`", '.ql-editor', 'loadingSpinner(show)', 'Salva', 'scroll-padding-top']) {
    assert.ok(log.includes(s), 'CHANGELOG.md senza ' + s);
}
console.log('agents e changelog ok');
JS
```

Expected: PASS con `agents e changelog ok`.

- [ ] **Step 5: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add AGENTS.md CHANGELOG.md
git commit -m "docs: AGENTS.md e CHANGELOG per la barra di salvataggio" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `2 files changed`.

## Parte B — Test browser della barra (Playwright + Chrome)

Copre il file nuovo `test/backend-save-bar.browser.test.cjs` della lib: la fixture della sez. 6.3 della spec, gli helper condivisi, il runner e i casi B1-B47. I test B sono fuori da `npm test` (sez. 6.1): si lanciano a mano con `playwright-core` e il Chrome installato, come `test/deferred-content.browser.test.cjs`, e `npm test` non cambia.

Regole valide per tutta la parte:
- ogni blocco di comandi parte da `/Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar`: è il worktree `LIB_WT` del Task S1, sul branch `backend-save-bar`. Se il worktree non ha `node_modules/`, Node risale a quelli del checkout principale della lib, e la fixture li trova lo stesso;
- la parte va dopo le Parti C e I: la fixture carica `saveBar.js` (C) e `save-bar.css` con i token (I4), e i comandi di installazione e lancio sono già nei docs e in `AGENTS.md` della lib (I5, I7);
- il file è nuovo: solo ASCII, commenti brevi in italiano senza accenti (`e'`);
- il file cresce task dopo task: ogni task inserisce il suo blocco di casi, che comincia con il commento `// Task Bn: ...`, subito prima dell'ultima riga del file, `run().catch(error => { console.error(error); process.exitCode = 1; });`, che resta l'ultima. I casi usano gli helper del Task B1: un helper che serve a più casi di un solo task sta in testa al blocco di quel task, e uno che serve a un solo caso resta dentro quel caso;
- un caso che non riesce a creare la sua condizione chiama `notReproduced(motivo)`: il runner stampa `non riprodotto Bn: motivo` e non lo conta né tra i passati né tra i falliti (sez. 6.1: vale per B4, B35 e B39);
- `SAVE_BAR_ONLY=B8,B9` lancia solo i casi indicati; un id che non esiste fa fallire il lancio con `casi inesistenti: ...`;
- se un caso fallisce per un errore di `saveBar.js` o dei CSS, la correzione va nel task della Parte C o I che ha scritto quel codice, non nel test;
- Claude non digita mai password vere: nei casi con una password sbagliata scrive `sbagliata-save-bar`, quella giusta la digita l'utente;
- push, PR, merge, release npm e tag si fanno **solo su richiesta esplicita dell'utente**; in questa parte non ce ne sono.

### Task B1: fixture, helper, runner e casi B1-B7 (avvio e visibilità)

Repo: **lib**.

Spec sez. 6.3 (installazione, fixture, regole comuni) e casi B1-B7 (sez. 3 A-D, 4 Stati e Fascia). Qui nasce il file dei test browser con tutto quello che serve ai casi B8-B47:
- la pagina del backend, servita su `https://save-bar.test/` con `page.route`: `#topbar`, `#sidebar` con il menu mobile, `#content` con due card, un form lungo con `onsubmit="loadingSpinner()"` e i submit `upload-add` "Salva e aggiungi" e `upload` "Salva" come quelli di `ResourceFormLayoutRenderer`, disabilitati finché `checkInput()` non li abilita;
- le opzioni della fixture per i task successivi: campi in più (`fields`), altezza del riempitivo (`filler`), bottoni (`buttons`), attributi del form (`form`), HTML dopo il form, per esempio un secondo form (`html`), stili in `<head>` (`head`), codice eseguito al `load` prima dell'avvio (`setup`), tema Bootstrap (`theme`) e uno script iniettato prima di ogni altro (`init`). Tutte le altre opzioni vanno al contesto di Playwright: `viewport`, `deviceScaleFactor`, `colorScheme`, `hasTouch`, `isMobile`, `reducedMotion`;
- i POST trattenuti: la route non risponde, e il caso legge i nomi dei campi inviati, compreso il bottone che ha inviato;
- il runner con il registro dei casi, il filtro e i casi "non riprodotti".

Scelte di questo task:
- l'avvio fa `checkInput(); setUpSaveBar();` e poi l'evento `loaded`, cioè la parte di `setUpPage()` che riguarda i form (Q6): `createCard`, `setInput`, `setUpBootstrap` e `setUpJquery` servono alle pagine vere, non all'isola. Per `checkInput()` si carica `pageSetUp.js`, oltre ai file della sez. 6.3;
- il backend ha `html { font-size: 14px }` (`header.css`), quindi `6rem` vale 84px e `8rem` 112px, non 96 e 128 come nella spec, che conta 16px. B7 converte con il `rem` letto dalla pagina; `abc` dà sempre 96px, il valore iniziale di `@property`;
- le misure si prendono dopo `settle()`, che aspetta i font, tre frame e la fine delle animazioni finite: `#content` ha `transition: all .3s` e all'avvio il suo padding cresce della riserva. Nessun caso di questo task toglie le transizioni;
- B2 porta il fondo di Salva a 40px dal fondo della finestra, dentro la fascia (`buttons`), e poi in fondo alla pagina, dove Salva sta sopra la fascia (`hidden`);
- B4 ripete i controlli di B2 e B3 con `deviceScaleFactor` 1.25 anche quando il bug non si riproduce, e solo dopo si segna "non riprodotto": con il Chrome di oggi l'IntersectionObserver di controllo con `threshold: 1` dà rapporto 1 (provati anche 1.1, 1.5 e 1.75);
- B6 toglie e rimette `type="button"` su un originale. Il MutationObserver del form guarda anche l'attributo `type` e rifà l'elenco quando cambia (correzione di C2 e C4, fuori da Q11): senza, un bottone tornato `submit` non è più un originale osservato e la sua copia non torna.

**Files:**
- Create: `test/backend-save-bar.browser.test.cjs`

**Interfaces:**
- Consumes:
  - dalla Parte C: `setUpSaveBar()`, `window.wiSaveBar`, `.wi-save-bar` con `data-wi-state` (`hidden`, `buttons`, `dirty-buttons`, `dirty-label`, `submitting`), `.wi-save-bar-island`, le copie `.wi-save-bar-btn` (visibili con `:not([hidden])`, senza `title` né `aria-label`), `data-wi-save-bar` e `data-wi-save-bar-ignore`; il MutationObserver del form con `attributeFilter: ['disabled', 'type', 'data-wi-save-bar-ignore']`; `loadingSpinner()` in `backend/js/utility.js`;
  - dalla Parte I: `save-bar.css` con `@property --wi-save-bar-band` (96px), `html.wi-save-bar-on`, `--wi-save-bar-reserve` sul padding di `#content` e su `scroll-padding-bottom`, `scroll-padding-top: 50px` in `header.css`; i comandi di installazione e lancio in `AGENTS.md` e `docs/javascript/save-bar.md` (I5, I7);
  - `checkInput()` da `backend/js/pageSetUp.js`; jQuery prima di `saveBar.js`.
- Produces (per i Task B2-B4):
  - costanti: `ROOT`, `MODULES`, `PAGE = 'https://save-bar.test/'`, `SALVA = '[name=upload]'`, `BUTTONS` (markup dei due submit), `CSS` e `JS` (percorsi caricati dalla fixture);
  - letture da passare a `page.evaluate` o `until`: `STATE() → string | null` (lo stato dell'isola), `COPIES() → string[]` (le etichette delle copie visibili);
  - `test(id, fn)` e il registro `cases`; `run()` con `SAVE_BAR_ONLY`, `CHROME_CHANNEL` (default `chrome`) e la riga finale `backend-save-bar browser: N passed`;
  - `fixture({ fields, filler, buttons, form, html, head, setup, theme }) → string` (l'HTML della pagina) e `asset(pathname) → string | null` (il file locale di `/src/...` e `/node_modules/...`), riusabili da un server `node:http` (B35);
  - `open({ ...fixture, init, ...contesto }) → Promise<Page>`, con `page.requests` = `[{ method, url, names, route }]` (le richieste che non sono file; i POST restano trattenuti e `route` permette di chiuderli);
  - `until(page, read, expected)`, `settle(page)`, `place(page, gap, selector = SALVA)`, `toBottom(page)`, `rect(page, selector)`, `atIsland(page, selector = '.wi-save-bar-island')`, `dialog(page, timeout = 1000) → Dialog | null`, `posts(page, count = 0) → POST[]`;
  - `sampler()`, da `init` o `page.evaluate`: `window.samples` = `[{ at: 'frame' | 'change', state, opacity, visibility, duration, animations }]`;
  - `notReproduced(reason)`, `checkScroll(page)` (i controlli di B2) e `checkBottom(page)` (quelli di B3).

- [ ] **Step 1: Installa `playwright-core` e controlla che si carichi**

Una volta sola per macchina, con la rete. Non scarica browser: il test usa Chrome.app con `channel: 'chrome'`.

```bash
PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm i --prefix "$HOME/.cache/wonder-tooling/playwright" playwright-core
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" node -e "require(process.env.PLAYWRIGHT_MODULE).chromium"
```

Expected: npm finisce senza errori (`added 1 package` la prima volta, `up to date` le volte dopo); il comando `node -e` non stampa nulla ed esce con 0. Se `$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core` c'è già, basta il secondo comando.

- [ ] **Step 2: Scrivi fixture, helper, runner e casi B1-B7**

In cima al file: `require`, risoluzione dei file, la fixture servita con `page.route`, gli helper condivisi e il registro. Poi i casi del task, sotto il commento `// Task B1: ...`. In fondo, una sola riga che lancia `run()`.

Crea `test/backend-save-bar.browser.test.cjs` con questo contenuto:

```js
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const ROOT = path.join(__dirname, '..');
// In un worktree dentro la lib, senza npm ci, Node risale ai node_modules del checkout principale.
const MODULES = path.dirname(path.dirname(require.resolve('bootstrap/package.json', { paths: [ROOT] })));
const PAGE = 'https://save-bar.test/';
const SALVA = '[name=upload]';
const BUTTONS = `<div class="container" style="max-width: 100%;"><div class="row row-cols-auto gap-2 justify-content-end">
<button type="submit" id="" name="upload-add" class="float-end btn btn-dark wi-submit" disabled>Salva e aggiungi</button>
<button type="submit" id="" name="upload" class="float-end btn btn-dark wi-submit" disabled>Salva</button></div></div>`;
const CSS = [
    'node_modules/bootstrap/dist/css/bootstrap.min.css',
    'node_modules/bootstrap-icons/font/bootstrap-icons.min.css',
    'node_modules/select2/dist/css/select2.min.css',
    'node_modules/@ttskch/select2-bootstrap4-theme/dist/select2-bootstrap4.css',
    'node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker3.min.css',
    'node_modules/filepond/dist/filepond.min.css',
    'node_modules/jstree/dist/themes/default/style.min.css',
    'src/build/backend/css/tokens.css',
    'src/build/backend/css/header.css',
    'src/build/backend/css/input.css',
    'src/build/backend/css/save-bar.css',
    'src/build/backend/css/lib/filepond.css',
    'src/build/backend/css/lib/jstree.css',
    'src/build/backend/css/lib/select2.css'
];
const JS = [
    'node_modules/jquery/dist/jquery.min.js',
    'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js',
    'node_modules/select2/dist/js/select2.min.js',
    'node_modules/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js',
    'node_modules/bootstrap-datepicker/dist/locales/bootstrap-datepicker.it.min.js',
    'node_modules/autonumeric/dist/autoNumeric.min.js',
    'node_modules/filepond/dist/filepond.min.js',
    'node_modules/jstree/dist/jstree.min.js',
    'src/build/global/js/form/autonumeric.js',
    'src/build/global/js/utility.js',
    'src/build/backend/js/utility.js',
    'src/build/backend/js/form/input.js',
    'src/build/backend/js/pageSetUp.js',
    'src/build/backend/js/form/saveBar.js'
];

// Letture da eseguire nella pagina: stato dell'isola ed etichette delle copie visibili.
const STATE = () => document.querySelector('.wi-save-bar')?.dataset.wiState ?? null;
const COPIES = () => [...document.querySelectorAll('.wi-save-bar-btn:not([hidden])')].map(el => el.textContent.trim());

let browser;
const contexts = [];
const cases = [];

function test(id, fn) {
    cases.push({ id, fn });
}

// L'HTML della pagina del backend: html va in #content dopo il form (per esempio un secondo form).
function fixture({ fields = '', filler = 1500, buttons = BUTTONS, form = 'data-wi-save-bar', html = '', head = '', setup = '', theme = 'light' } = {}) {
    return `<!doctype html><html lang="it" data-bs-theme="${theme}"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
${CSS.map(href => `<link rel="stylesheet" href="/${href}">`).join('\n')}
${head}</head><body>
<div id="loading-spinner" class="position-fixed container-fluid bg-dark bg-opacity-50 h-100 d-none top-0 start-0" style="z-index: 1100"><div class="position-absolute top-50 start-50 translate-middle text-center"><div class="spinner-border" role="status"></div><br><br><span>Caricamento</span></div></div>
<nav id="sidebar">
<div class="sidebar-navbar bg-body-secondary border-end d-flex flex-column flex-shrink-0"><ul class="list-unstyled components mb-auto sidebar-navbar-nav">
<li class="active"><a type="button" data-bs-toggle="offcanvas" data-bs-target="#menu-risorse" class="text-body-emphasis"><i class="bi bi-box"></i><span>Risorse</span></a></li>
</ul></div>
<div class="sidebar-offcanvas"><div class="offcanvas offcanvas-start border-end" tabindex="-1" id="menu-risorse" data-bs-scroll="false" data-bs-backdrop="true">
<div class="offcanvas-header"><h5 class="offcanvas-title">Risorse</h5></div>
<div class="offcanvas-body pt-2 px-2"><ul class="list-group list-group-flush mt-0 w-100"><li class="list-group-item border-0 m-0 p-0 w-100 float-none"><a href="/altra" class="be-nav-link d-block w-100 m-0 py-1 pe-2 float-none ps-2 text-secondary text-decoration-none">Elenco</a></li></ul></div>
</div></div>
</nav>
<nav id="topbar" class="bg-body border-bottom">
<button id="menu" class="btn btn-outline-dark btn-sm pc-none ms-2 float-end" type="button" onclick="menu();"><i class="open-menu bi bi-list"></i><i class="close-menu bi bi-x-lg d-none"></i></button>
<div class="dropdown float-end"><button id="bs-theme" class="btn btn-outline-dark btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-sun-fill"></i></button>
<ul class="dropdown-menu"><li><button type="button" class="dropdown-item">Light</button></li><li><button type="button" class="dropdown-item">Dark</button></li></ul></div>
</nav>
<div id="content">
<form id="resource-layout-form" method="POST" enctype="multipart/form-data" action="/salva" onsubmit="loadingSpinner()" ${form}>
<div class="row g-3">
<div class="col-12"><div class="card border"><div class="card-body row g-3">
${fields}
<div style="height: ${filler}px"></div>
<div class="col-12"><label for="nome" class="form-label">Nome</label><input type="text" class="form-control" id="nome" name="nome" value="Mario"></div>
</div></div></div>
<div class="col-12"><div class="card border"><div class="card-body">${buttons}</div></div></div>
</div>
</form>
${html}
</div>
${JS.map(src => `<script src="/${src}"></script>`).join('\n')}
<script>window.addEventListener('load', () => { ${setup}; checkInput(); setUpSaveBar(); window.dispatchEvent(new Event('loaded')); });</script>
</body></html>`;
}

// Il file locale di un percorso /src/ o /node_modules/, altrimenti null.
function asset(pathname) {
    if (pathname.startsWith('/src/')) return path.join(ROOT, pathname);
    if (pathname.startsWith('/node_modules/')) return path.join(MODULES, pathname.slice(14));
    return null;
}

// Apre la fixture su PAGE; le opzioni che non sono della fixture vanno al contesto (viewport, deviceScaleFactor, colorScheme, hasTouch, isMobile, reducedMotion...).
async function open({ fields, filler, buttons, form, html, head, setup, theme, init, ...options } = {}) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, ...options });
    contexts.push(context);
    context.setDefaultTimeout(5000);
    const page = await context.newPage();
    const body = fixture({ fields, filler, buttons, form, html, head, setup, theme });
    page.requests = [];
    await page.route(PAGE + '**', route => {
        const request = route.request();
        const { pathname } = new URL(request.url());
        const file = asset(pathname);
        if (file) return route.fulfill({ path: file });
        const data = String(request.postDataBuffer() || '');
        const names = /multipart/.test(request.headers()['content-type']) ? [...data.matchAll(/; name="([^"]*)"/g)].map(match => match[1]) : [...new URLSearchParams(data).keys()];
        page.requests.push({ method: request.method(), url: request.url(), names, route });
        // Il POST resta trattenuto: lo chiude il caso, se serve.
        if (request.method() !== 'POST') return route.fulfill({ contentType: 'text/html', body: pathname === '/' ? body : '<p>Altra pagina</p>' });
    });
    if (init) await page.addInitScript(init);
    await page.goto(PAGE);
    await settle(page);
    return page;
}

// Aspetta che read, eseguita nella pagina, valga expected; allo scadere l'assert mostra il valore reale.
async function until(page, read, expected) {
    await page.waitForFunction(`JSON.stringify((${read})()) === ${JSON.stringify(JSON.stringify(expected))}`).catch(() => {});
    assert.deepEqual(await page.evaluate(read), expected);
}

// Font, un paio di frame per IO e rAF, poi la fine delle transizioni: #content ha transition: all .3s
// e all'avvio il suo padding-bottom cresce della riserva.
function settle(page) {
    return page.evaluate(async () => {
        await document.fonts.ready;
        for (let i = 0; i < 3; i++) await new Promise(requestAnimationFrame);
        await Promise.all(document.getAnimations().filter(animation => animation.effect.getComputedTiming().endTime < Infinity).map(animation => animation.finished.catch(() => {})));
    });
}

// Scorre finche' il fondo di selector sta a gap px dal fondo della finestra.
async function place(page, gap, selector = SALVA) {
    const actual = await page.evaluate(([selector, gap]) => {
        window.scrollBy({ top: document.querySelector(selector).getBoundingClientRect().bottom - innerHeight + gap, behavior: 'instant' });
        return innerHeight - document.querySelector(selector).getBoundingClientRect().bottom;
    }, [selector, gap]);
    assert.ok(Math.abs(actual - gap) < 1, `${selector} a ${actual}px dal fondo invece di ${gap}px`);
}

function toBottom(page) {
    return page.evaluate(() => window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'instant' }));
}

function rect(page, selector) {
    return page.locator(selector).evaluate(el => el.getBoundingClientRect().toJSON());
}

// Vero se nel punto centrale dell'isola l'elemento in cima sta dentro selector.
function atIsland(page, selector = '.wi-save-bar-island') {
    return page.evaluate(selector => {
        const box = document.querySelector('.wi-save-bar-island').getBoundingClientRect();
        return !!document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2)?.closest(selector);
    }, selector);
}

// Il dialogo arrivato entro timeout ms, oppure null; chi lo riceve deve chiuderlo.
function dialog(page, timeout = 1000) {
    return page.waitForEvent('dialog', { timeout }).catch(error => {
        if (error.name !== 'TimeoutError') throw error;
        return null;
    });
}

// I POST trattenuti, con i nomi dei campi letti dal corpo (quindi anche il bottone che ha inviato).
// Con count aspetta che ne siano arrivati almeno count: lo stato submitting precede la richiesta.
// Il clic che invia vuole { noWaitAfter: true }, altrimenti aspetta la navigazione trattenuta.
async function posts(page, count = 0) {
    const list = () => page.requests.filter(request => request.method === 'POST');
    for (let i = 0; i < 100 && list().length < count; i++) await new Promise(resolve => setTimeout(resolve, 50));
    return list();
}

// Da addInitScript o evaluate: in window.samples come appare l'isola a ogni frame e a ogni cambio di stato.
function sampler() {
    window.samples = [];
    const sample = at => {
        const bar = document.querySelector('.wi-save-bar');
        if (!bar) return;
        const style = getComputedStyle(bar);
        window.samples.push({ at, state: bar.dataset.wiState, opacity: +style.opacity, visibility: style.visibility, duration: style.transitionDuration, animations: bar.getAnimations().map(animation => animation.transitionProperty) });
    };
    const frame = () => {
        sample('frame');
        requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
    new MutationObserver(() => sample('change')).observe(document, { subtree: true, attributeFilter: ['data-wi-state'] });
}

function notReproduced(reason) {
    throw Object.assign(new Error(reason), { notReproduced: true });
}

// B2 e B4: la copia entra ed esce con la dissolvenza; da buttons a dirty-buttons senza animazione.
async function checkScroll(page) {
    const phase = async (action, expected) => {
        await page.evaluate(() => window.samples = []);
        await action();
        await until(page, STATE, expected);
        await settle(page);
        const samples = await page.evaluate(() => window.samples);
        return { change: samples.find(sample => sample.at === 'change'), frames: samples.filter(sample => sample.at === 'frame' && sample.state === expected) };
    };
    await toBottom(page);
    await until(page, STATE, 'hidden');
    await settle(page);
    await page.evaluate(sampler);
    const enter = await phase(() => place(page, 40), 'buttons');
    const exit = await phase(() => toBottom(page), 'hidden');
    for (const { change } of [enter, exit]) {
        assert.equal(change.duration.split(', ')[0], '0.2s');
        assert.ok(change.animations.includes('opacity'), 'nessuna dissolvenza');
    }
    assert.equal(enter.change.visibility, 'visible');
    const fading = exit.frames.filter(sample => sample.opacity > 0);
    assert.ok(fading.length > 0, 'nessun frame della dissolvenza in uscita');
    assert.deepEqual(fading.filter(sample => sample.visibility !== 'visible'), []);
    await phase(() => place(page, 40), 'buttons');
    const dirty = await phase(() => page.locator('#nome').pressSequentially('x'), 'dirty-buttons');
    assert.deepEqual(dirty.change.animations, []);
}

// B3 e B4: in fondo alla pagina Salva sta tra topbar e fascia, e la riserva e' applicata.
async function checkBottom(page) {
    await toBottom(page);
    await until(page, STATE, 'hidden');
    await settle(page);
    const salva = await rect(page, SALVA);
    const view = await page.evaluate(() => ({
        height: innerHeight,
        on: document.documentElement.classList.contains('wi-save-bar-on'),
        padding: getComputedStyle(document.getElementById('content')).paddingBottom,
        bottom: getComputedStyle(document.documentElement).scrollPaddingBottom,
        top: getComputedStyle(document.documentElement).scrollPaddingTop
    }));
    assert.ok(salva.top >= 50 && salva.bottom <= view.height - 96, `Salva da ${salva.top} a ${salva.bottom}px`);
    assert.deepEqual({ on: view.on, padding: view.padding, bottom: view.bottom, top: view.top }, { on: true, padding: '116px', bottom: '96px', top: '50px' });
}

async function run() {
    const only = process.env.SAVE_BAR_ONLY ? process.env.SAVE_BAR_ONLY.split(',') : null;
    const unknown = (only || []).filter(id => !cases.some(item => item.id === id));
    if (unknown.length) throw new Error('casi inesistenti: ' + unknown.join(', '));
    const skipped = [], failed = [];
    let passed = 0;
    browser = await chromium.launch({ channel: process.env.CHROME_CHANNEL || 'chrome', headless: true });
    try {
        for (const { id, fn } of cases) {
            if (only && !only.includes(id)) continue;
            try {
                await fn();
                passed++;
            } catch (error) {
                if (!error.notReproduced) {
                    failed.push(id);
                    console.error(id, error);
                } else skipped.push(id + ': ' + error.message);
            } finally {
                await Promise.all(contexts.splice(0).map(context => context.close()));
            }
        }
    } finally {
        await browser.close();
    }
    console.log(`backend-save-bar browser: ${passed} passed`);
    skipped.forEach(line => console.log('non riprodotto ' + line));
    if (failed.length) throw new Error('falliti: ' + failed.join(', '));
}

// Task B1: avvio, scorrimento, fascia e copie (B1-B7)

test('B1', async () => {
    const page = await open({ filler: 0, init: sampler });
    await until(page, COPIES, ['Salva e aggiungi', 'Salva']);
    await page.waitForFunction(() => window.samples.filter(sample => sample.at === 'frame').length >= 60);
    const samples = await page.evaluate(() => window.samples);
    assert.deepEqual(samples.filter(sample => sample.state !== 'hidden' || sample.opacity !== 0 || sample.visibility !== 'hidden'), []);
});

test('B2', async () => checkScroll(await open()));

test('B3', async () => checkBottom(await open()));

test('B4', async () => {
    const page = await open({ deviceScaleFactor: 1.25 });
    // IO di controllo con threshold 1 su Salva tutto in vista: sotto 1 il bug di Chromium c'e'.
    const ratio = await page.evaluate(selector => new Promise(resolve => {
        const el = document.querySelector(selector);
        el.scrollIntoView({ block: 'center', behavior: 'instant' });
        new IntersectionObserver(([entry], observer) => {
            observer.disconnect();
            resolve(entry.intersectionRatio);
        }, { threshold: 1 }).observe(el);
    }), SALVA);
    await checkBottom(page);
    await checkScroll(page);
    if (ratio >= 1) notReproduced(`con deviceScaleFactor 1.25 il rapporto di Salva in vista e' ${ratio}`);
});

test('B5', async () => {
    const page = await open();
    const both = ['Salva e aggiungi', 'Salva'];
    await until(page, COPIES, both);
    await until(page, STATE, 'buttons');
    await page.evaluate(() => window.kept = [...document.querySelectorAll('.wi-save-bar-btn')]);
    await page.evaluate(selector => document.querySelector(selector).closest('.card').style.display = 'none', SALVA);
    await until(page, COPIES, []);
    await until(page, STATE, 'hidden');
    await page.evaluate(selector => document.querySelector(selector).closest('.card').style.display = '', SALVA);
    await until(page, COPIES, both);
    await until(page, STATE, 'buttons');
    assert.ok(await page.evaluate(() => window.kept.every((el, i) => el === document.querySelectorAll('.wi-save-bar-btn')[i])), 'copie ricreate');
});

test('B6', async () => {
    const page = await open({ buttons: BUTTONS + '<div class="mt-2" id="altri"><button type="submit" name="bozza" class="btn btn-outline-dark">Bozza</button></div>' });
    const all = ['Salva e aggiungi', 'Salva', 'Bozza'];
    const without = all.slice(0, 2);
    await until(page, COPIES, all);
    await page.evaluate(() => window.kept = [...document.querySelectorAll('.wi-save-bar-btn')].slice(0, 2));
    // Dopo ogni modifica le copie attese, e le prime due sono ancora gli stessi nodi.
    const step = async (change, expected) => {
        await page.evaluate(change);
        await until(page, COPIES, expected);
        assert.ok(await page.evaluate(() => window.kept.every((el, i) => el === document.querySelectorAll('.wi-save-bar-btn')[i])), 'copie ricreate');
    };
    await step(() => document.querySelector('[name=bozza]').hidden = true, without);
    await step(() => document.querySelector('[name=bozza]').hidden = false, all);
    await step(() => document.querySelector('[name=bozza]').type = 'button', without);
    await step(() => document.querySelector('[name=bozza]').type = 'submit', all);
    await step(() => document.querySelector('[name=bozza]').setAttribute('data-wi-save-bar-ignore', ''), without);
    await step(() => document.querySelector('[name=bozza]').removeAttribute('data-wi-save-bar-ignore'), all);
    await step(() => document.getElementById('altri').setAttribute('data-wi-save-bar-ignore', ''), without);
    await step(() => document.getElementById('altri').removeAttribute('data-wi-save-bar-ignore'), all);
    await step(() => document.getElementById('altri').insertAdjacentHTML('beforeend', '<button type="submit" name="duplica" class="btn btn-outline-dark">Duplica</button>'), [...all, 'Duplica']);
    await step(() => document.querySelector('[name=duplica]').textContent = 'Duplica record', [...all, 'Duplica record']);
});

test('B7', async () => {
    // Con la fascia a 8rem la copia compare solo quando Salva entra negli ultimi 8rem.
    const band = async (page, rem) => {
        await place(page, 8 * rem + 8);
        await until(page, STATE, 'hidden');
        await place(page, 8 * rem - 8);
        await until(page, STATE, 'buttons');
    };
    const page = await open();
    // Il backend ha html { font-size: 14px }: i rem si convertono con quello.
    const rem = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize));
    const values = await page.evaluate(() => ['6rem', '8rem', 'abc'].map(value => {
        document.documentElement.style.setProperty('--wi-save-bar-band', value);
        return getComputedStyle(document.documentElement).getPropertyValue('--wi-save-bar-band').trim();
    }));
    assert.deepEqual(values, [6 * rem + 'px', 8 * rem + 'px', '96px']);
    await band(await open({ head: '<style>:root { --wi-save-bar-band: 8rem; }</style>' }), rem);
    // Token cambiato a pagina aperta: vale dopo un resize.
    await page.evaluate(() => document.documentElement.style.setProperty('--wi-save-bar-band', '8rem'));
    await page.setViewportSize({ width: 1200, height: 720 });
    await band(page, rem);
});

run().catch(error => { console.error(error); process.exitCode = 1; });
```

- [ ] **Step 3: Lancia solo i casi B1-B7**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" SAVE_BAR_ONLY=B1,B2,B3,B4,B5,B6,B7 node test/backend-save-bar.browser.test.cjs
```

Expected: PASS in una decina di secondi, con uscita 0 e queste due righe:

```text
backend-save-bar browser: 6 passed
non riprodotto B4: con deviceScaleFactor 1.25 il rapporto di Salva in vista e' 1
```

Con un Chrome che ha ancora il bug di arrotondamento: `backend-save-bar browser: 7 passed` e nessuna riga `non riprodotto`. Se B6 fallisce perché `Bozza` non torna dopo `type = 'submit'`, manca la correzione del MutationObserver del form nei Task C2 e C4 (`'type'` in `attributeFilter` e nella condizione che rifà l'elenco).

- [ ] **Step 4: Lancia tutto il file e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" node test/backend-save-bar.browser.test.cjs
npm test
```

Expected: il test browser stampa le stesse due righe dello Step 3 ed esce con 0; `npm test` finisce ancora con `backend-save-bar: 47 tests passed`, perché i test B non sono nella catena.

- [ ] **Step 5: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add test/backend-save-bar.browser.test.cjs
git commit -m "test(backend): test browser dell'isola di salvataggio, avvio, fascia e copie" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `1 file changed`. Il file non è ignorato: `.gitignore` esclude `test/*` ma riammette `!test/*.test.cjs`.

### Task B2: rilevamento con widget veri, esclusioni, FormData di terzi e prestazioni (B8-B20)

Repo: **lib**.

Spec sez. 6.3, casi B8-B20 (sez. 1 Contratto, 2 Principio e Costo, 2.1-2.3, 3 F e L, 5.7, Q1, Q4, Q11). Il task aggiunge al file del Task B1 il blocco `// Task B2: ...`, subito prima dell'ultima riga, e non cambia gli helper del Task B1:
- in testa al blocco, tre helper usati da più casi di questo task: le letture `DIRTY` (lo sporco del form della fixture, con `wiSaveBar.isDirty`) e `STATUS` (il testo di `body > [role=status]`, `null` senza isola) e `clean(page)`, che aspetta il ricalcolo del frame e controlla che il form sia ancora pulito;
- B8-B12: scrittura e cancellazione, Select2 e datepicker veri, scritture silenziose, `changed()` e interazione vera;
- B13-B17: repeater, AutoNumeric, input file, manifest di FilePond e checkbox riordinate;
- B18-B19: esclusioni e `new FormData(form)` chiamata da uno script del sito;
- B20: il confronto A/B delle prestazioni con le soglie di Q4.

Scelte di questo task:
- B9 avvia Select2 in `setup` con le opzioni di `setSelect2()` che non chiedono altri file (`theme: 'bootstrap4'`, `width: 'style'`, `dropdownParent` su `#content`). Il datepicker è quello vero che `checkInput()` crea su `data-wi-date`: non ha `autoclose`, quindi resta aperto e il caso sceglie il 12 e poi di nuovo il 10 nello stesso calendario. Select2 riscrive il testo mostrato dentro il form, quindi il ricalcolo arriva anche dal clic in cattura e dal MutationObserver: B9 non isola l'ascolto jQuery `input change` su `document` (tolto quello, il caso passa lo stesso);
- B10: la scrittura silenziosa è nell'`onclick` di `#genera`. Poi `wiSaveBar.reset(form)` e un listener `click` su `#colore` che chiama `stopPropagation()` e scrive senza eventi. Con i segnali in bolla invece che in cattura, la seconda parte fallisce;
- B11: il ricalcolo del clic avviene nel frame dopo, quindi il caso aspetta `settle()` prima di scrivere da script. Senza l'attesa, quel ricalcolo vedrebbe già il valore nuovo, e `changed()` non servirebbe a niente. Prima di `changed()` il caso controlla che il form sia ancora pulito;
- B12: le scritture da script lanciano un `input` sintetico, che non è `isTrusted`: così il ricalcolo c'è davvero e, senza interazione vera, assorbe il valore nella base (pulito). Hover, rotella e `focus()` non sono interazioni. Dopo un clic vero, la stessa scrittura rende sporco;
- B13 simula il repeater con righe `.wi-repeater-row` e quattro bottoni: aggiungi, togli, scambia (sposta la prima riga in fondo) ed elimina, che alterna `disabled` sul `fieldset` della riga come fa "annulla". Il primo giro usa clic veri, il secondo chiama le stesse funzioni da script: lì fa ricalcolare solo il MutationObserver;
- B14: `prezzo` vuoto e `costo` a `12.5`, con `data-wi-price` e le opzioni di `checkInput()`. Il `mouseenter` non è un segnale dell'isola: dopo il passaggio del mouse, che mostra `€` (`emptyInputBehavior: 'focus'`), il caso forza il ricalcolo con `wiSaveBar.changed()` e controlla che il form resti pulito. Il submit annullato viene da un listener in `setup`, che chiama `preventDefault()` e spegne lo spinner. AutoNumeric toglie la formattazione anche così (`_onFormSubmit`): il caso controlla i valori `''` e `12.5` e che il form resti pulito;
- B15: gli eventi di `setInputFiles` di Playwright non sono `isTrusted`, quindi non contano come interazione, e il file scelto finirebbe nella base. Il caso usa il file chooser: il clic su `#allegato` è l'interazione vera, poi `setFiles` sceglie il file. Il file tolto con `setInputFiles([])` riporta a pulito. Per "un input file vuoto non conta" il caso toglie l'input dal DOM: se il campo vuoto fosse nell'istantanea, toglierlo la cambierebbe;
- B16 simula FilePond senza la libreria. Un listener `formdata` in `setup` aggiunge `foto__wi_files` con il JSON dei file salvati, e il caso lancia gli eventi `FilePond:*` su `#foto`. Il download dei file salvati aggiunge immagini al DOM e lancia `addfile` e `updatefiles` senza cambiare il manifest (pulito); `reorderfiles` inverte l'ordine (sporco) e poi lo ripristina (pulito); un file nuovo cambia il manifest (sporco);
- B17 sposta in fondo la prima checkbox `categoria[]`, come fa la ricerca delle DynamicCheck, poi spunta la seconda;
- B18: dopo le scritture vere in `#filtro` e `#cerca`, il campo del modal legacy, che è nascosto, si scrive da script con un `input` sintetico, e il form resta pulito. "Un nome presente solo nel contenitore ignorato viene tolto dall'istantanea" si prova togliendo `#filtro` dal DOM: il form resta pulito. `tag[]` sta dentro e fuori da `[data-wi-save-bar-ignore]`: modificarlo dentro rende sporco, e `init` raccoglie gli avvisi di `console.warn`, che devono essere esattamente uno;
- B19: dopo la `FormData` di terzi il caso controlla lo stato `dirty-buttons` e lo sporco, poi prova a uscire con `location.href`. La `evaluate` che naviga resta bloccata finché il dialogo `beforeunload` è aperto, quindi il caso non la aspetta subito: aspetta il dialogo, lo chiude con `dismiss()` e controlla che la pagina sia ancora `PAGE`;
- B20, fixture: la stessa per A e B (`form` a `data-wi-save-bar` o vuoto, `filler: 0`), con circa 5600 elementi nel form e 4002 voci nella `FormData`. Contiene un albero jstree da 2000 nodi (20 radici, 9 figli ciascuna, 10 foglie per figlio, plugin `checkbox` con `three_state: false`) con le 2000 checkbox `albero[]` nel contenitore `[data-wi-tree-values]`, metà spuntate; 600 righe di repeater da 5 campi; un FilePond con `server.process` finto, che avanza del 10% ogni 50ms; `#aggiungi`, che clona l'ultima riga;
- B20, misura: prima di misurare, il caso controlla che la regione status ci sia solo con l'isola. Se `setUpSaveBar()` non partisse, A e B sarebbero la stessa pagina e il confronto passerebbe a vuoto. `init` avvolge `FormData` in una sottoclasse che misura il costruttore, osserva i `longtask` e registra `DOMContentLoaded` e l'evento `loaded` della fixture. Gli scenari girano in ordine sulla stessa pagina; A e B si alternano per 5 ripetizioni, con una pagina nuova ogni volta. Per ogni scenario si contano i task lunghi iniziati durante lo scenario e si prende la `FormData` più lenta; per il caricamento si conta da `DOMContentLoaded` a quando l'albero è pronto. I 20 clic fuori dai campi vanno su `#topbar`, nel punto (640, 25);
- B20, soglie: per ogni scenario, la mediana dei task lunghi con l'isola non supera quella senza, e la mediana della `FormData` più lenta resta sotto 16ms. Il tempo da `DOMContentLoaded` a `loaded` si stampa soltanto, perché Q4 non gli dà una soglia. Il rallentamento della CPU ×4 è facoltativo e solo informativo, e non c'è.

**Files:**
- Modify: `test/backend-save-bar.browser.test.cjs` (blocco nuovo subito prima dell'ultima riga)

**Interfaces:**
- Consumes:
  - dal Task B1: `test`, `open` (con `fields`, `filler`, `form`, `setup` e `init`), `until`, `settle`, `dialog`, `STATE`, `SALVA`, `PAGE`, `assert`;
  - dalla Parte C: `setUpSaveBar()`; `window.wiSaveBar` con `isDirty(form)`, `changed(el)` e `reset(form)`; la regione `body > [role=status]` con "Operazione non salvata"; i segnali in cattura su `document`, compresi `FilePond:addfile`, `FilePond:updatefiles` e `FilePond:reorderfiles`; il MutationObserver del form; l'interazione solo con eventi `isTrusted`; `formSnapshot`, che salta i campi `:disabled` e gli input file vuoti, riduce i file a nome, dimensione e tipo, legge AutoNumeric con `getNumericString()` e confronta come insieme le checkbox con lo stesso nome; `.modal, [data-wi-save-bar-ignore]` come zone escluse e l'avviso `wiSaveBar: il nome "..." sta dentro e fuori da una zona esclusa, quindi conta intero`; il `beforeunload` di `preventPageExit`;
  - `checkInput()` da `backend/js/pageSetUp.js` (datepicker su `data-wi-date`, AutoNumeric su `data-wi-price`); Select2, bootstrap-datepicker, AutoNumeric, FilePond e jstree caricati dalla fixture; `loadingSpinner()`.
- Produces: le letture `DIRTY() → boolean` e `STATUS() → string | null` e l'helper `clean(page)`, in testa al blocco. Li usano solo i casi B8-B20.

- [ ] **Step 1: Aggiungi i casi B8-B20**

In `test/backend-save-bar.browser.test.cjs` sostituisci l'ultima riga del file:

```js
run().catch(error => { console.error(error); process.exitCode = 1; });
```

con il blocco del task seguito dalla stessa riga, che resta l'ultima:

```js
// Task B2: rilevamento con widget veri, esclusioni, FormData di terzi e prestazioni (B8-B20)

// Letture per i casi di rilevamento: lo sporco del form della fixture e il testo della regione status.
const DIRTY = () => wiSaveBar.isDirty(document.forms[0]);
const STATUS = () => document.querySelector('body > [role=status]')?.textContent ?? null;

// Dopo il ricalcolo del frame il form e' ancora pulito.
async function clean(page) {
    await settle(page);
    assert.equal(await page.evaluate(DIRTY), false);
}

test('B8', async () => {
    const page = await open();
    await until(page, STATUS, '');
    await page.locator('#nome').pressSequentially('x');
    await until(page, DIRTY, true);
    await until(page, STATUS, 'Operazione non salvata');
    await page.locator('#nome').press('Backspace');
    await until(page, DIRTY, false);
    await until(page, STATUS, '');
});

test('B9', async () => {
    const page = await open({
        fields: `<div class="col-6"><select class="form-select" id="citta" name="citta"><option value="mi">Milano</option><option value="to">Torino</option></select></div>
<div class="col-6"><input type="text" class="form-control" id="data" name="data" value="10/03/2026" data-wi-date="true"></div>`,
        // Le opzioni di setSelect2() del backend che non chiedono file in piu'.
        setup: "$('#citta').select2({ theme: 'bootstrap4', width: 'style', dropdownParent: document.getElementById('content') })"
    });
    const city = async name => {
        await page.click('#citta + .select2 .select2-selection');
        await page.locator('.select2-results__option', { hasText: name }).click();
    };
    const day = text => page.locator('.datepicker-days td.day:not(.old):not(.new)', { hasText: new RegExp(`^${text}$`) }).click();
    await city('Torino');
    await until(page, DIRTY, true);
    await city('Milano');
    await until(page, DIRTY, false);
    await page.click('#data');
    await day(12);
    await until(page, DIRTY, true);
    await day(10);
    await until(page, DIRTY, false);
});

test('B10', async () => {
    const page = await open({
        fields: `<div class="col-6"><div class="input-group"><input type="text" class="form-control" id="codice" name="codice" value="A1"><button type="button" class="btn btn-outline-dark" id="genera" onclick="document.getElementById('codice').value = 'B2'">Genera</button></div></div>
<div class="col-6"><input type="text" class="form-control" id="colore" name="colore" value="rosso"></div>`,
        setup: "document.getElementById('colore').addEventListener('click', event => { event.stopPropagation(); event.target.value = 'blu'; })"
    });
    await page.click('#genera');
    await until(page, DIRTY, true);
    await page.evaluate(() => wiSaveBar.reset(document.forms[0]));
    await clean(page);
    await page.click('#colore');
    await until(page, DIRTY, true);
});

test('B11', async () => {
    const page = await open();
    await page.click('#nome');
    // Il ricalcolo del clic e' nel frame dopo: la scrittura deve venire dopo di lui.
    await settle(page);
    await page.evaluate(() => document.getElementById('nome').value = 'Luigi');
    await clean(page);
    await page.evaluate(() => wiSaveBar.changed(document.getElementById('nome')));
    await until(page, DIRTY, true);
});

test('B12', async () => {
    const page = await open();
    const write = value => page.evaluate(value => {
        const el = document.getElementById('nome');
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }, value);
    await page.hover('#nome');
    await page.mouse.wheel(0, 300);
    await page.evaluate(() => document.getElementById('nome').focus());
    await write('Luigi');
    await clean(page);
    await page.click('#nome');
    await write('Anna');
    await until(page, DIRTY, true);
});

test('B13', async () => {
    const row = (key, value) => `<div class="wi-repeater-row" id="riga-${key}"><fieldset><input type="text" class="form-control" name="righe[${key}][nome]" value="${value}"></fieldset></div>`;
    const page = await open({
        fields: `<div class="col-12" id="righe">${row('a', 'Uno')}${row('b', 'Due')}</div>
<div class="col-12">${['aggiungi', 'togli', 'scambia', 'elimina'].map(id => `<button type="button" class="btn btn-outline-dark" id="${id}" onclick="ops[this.id]()">${id}</button>`).join(' ')}</div>`,
        setup: `window.ops = {
    aggiungi: () => document.getElementById('righe').insertAdjacentHTML('beforeend', '${row('c', '')}'),
    togli: () => document.getElementById('riga-c').remove(),
    scambia: () => document.getElementById('righe').append(document.getElementById('righe').firstElementChild),
    elimina: () => document.querySelector('#riga-a fieldset').toggleAttribute('disabled')
}`
    });
    // Prima i clic, che sono anche l'interazione; poi le stesse operazioni da script, viste solo dal MutationObserver.
    for (const run of [id => page.click('#' + id), id => page.evaluate(id => ops[id](), id)]) {
        for (const [id, dirty] of [['aggiungi', true], ['togli', false], ['scambia', true], ['scambia', false], ['elimina', true], ['elimina', false]]) {
            await run(id);
            await until(page, DIRTY, dirty);
        }
    }
});

test('B14', async () => {
    const page = await open({
        fields: `<div class="col-6"><input type="text" class="form-control" id="prezzo" name="prezzo" value="" data-wi-price="true"></div>
<div class="col-6"><input type="text" class="form-control" id="costo" name="costo" value="12.5" data-wi-price="true"></div>`,
        setup: "document.forms[0].addEventListener('submit', event => { event.preventDefault(); loadingSpinner(false); })"
    });
    await page.click('#nome');
    await page.hover('#prezzo');
    assert.equal(await page.inputValue('#prezzo'), '\u20ac');
    await page.evaluate(() => wiSaveBar.changed(document.getElementById('prezzo')));
    await clean(page);
    await page.click(SALVA);
    assert.deepEqual([await page.inputValue('#prezzo'), await page.inputValue('#costo')], ['', '12.5']);
    await clean(page);
    await page.locator('#prezzo').pressSequentially('5');
    await until(page, DIRTY, true);
});

test('B15', async () => {
    const page = await open({ fields: '<div class="col-12"><input type="file" class="form-control" id="allegato" name="allegato"></div>' });
    // Il clic sull'input e' l'interazione: gli eventi di setFiles non sono isTrusted.
    const [chooser] = await Promise.all([page.waitForEvent('filechooser'), page.click('#allegato')]);
    await chooser.setFiles({ name: 'a.txt', mimeType: 'text/plain', buffer: Buffer.from('ciao') });
    await until(page, DIRTY, true);
    await page.setInputFiles('#allegato', []);
    await until(page, DIRTY, false);
    // Se l'input vuoto contasse, toglierlo cambierebbe l'istantanea.
    await page.evaluate(() => document.getElementById('allegato').remove());
    await clean(page);
});

test('B16', async () => {
    const page = await open({
        fields: '<div class="col-12" id="foto"></div>',
        setup: `window.saved = ['a.jpg', 'b.jpg'];
window.fire = type => document.getElementById('foto').dispatchEvent(new CustomEvent('FilePond:' + type, { bubbles: true }));
document.forms[0].addEventListener('formdata', event => event.formData.append('foto__wi_files', JSON.stringify(saved)))`
    });
    await page.click('#nome');
    // I file salvati finiscono di scaricarsi: cambia il DOM di FilePond, non il manifest.
    await page.evaluate(() => {
        document.getElementById('foto').append(...saved.map(alt => Object.assign(new Image(), { alt })));
        fire('addfile');
        fire('updatefiles');
    });
    await clean(page);
    await page.evaluate(() => { saved.reverse(); fire('reorderfiles'); });
    await until(page, DIRTY, true);
    await page.evaluate(() => { saved.reverse(); fire('reorderfiles'); });
    await until(page, DIRTY, false);
    await page.evaluate(() => { saved.push('c.jpg'); fire('addfile'); });
    await until(page, DIRTY, true);
});

test('B17', async () => {
    const page = await open({
        fields: `<div class="col-12" id="categorie">${[1, 2, 3].map(n => `<div class="form-check"><input class="form-check-input" type="checkbox" name="categoria[]" value="${n}" id="c${n}"${n === 2 ? '' : ' checked'}><label class="form-check-label" for="c${n}">Categoria ${n}</label></div>`).join('')}</div>`
    });
    await page.click('#nome');
    await page.evaluate(() => document.getElementById('categorie').append(document.getElementById('c1').parentNode));
    await clean(page);
    await page.check('#c2');
    await until(page, DIRTY, true);
});

test('B18', async () => {
    const page = await open({
        fields: `<div class="modal" id="legacy" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><input type="text" class="form-control" id="nota" name="nota"></div></div></div>
<div class="col-6" data-wi-save-bar-ignore><input type="text" class="form-control" id="filtro" name="filtro"><input type="text" class="form-control mt-2" id="tag-dentro" name="tag[]" value="uno"></div>
<div class="col-6"><input type="text" class="form-control" id="cerca" name="cerca" data-wi-save-bar-ignore><input type="text" class="form-control mt-2" id="tag-fuori" name="tag[]" value="due"></div>`,
        init: () => {
            window.warnings = [];
            const warn = console.warn;
            console.warn = (...args) => {
                window.warnings.push(args.join(' '));
                warn.apply(console, args);
            };
        }
    });
    await page.fill('#filtro', 'rossi');
    await page.fill('#cerca', 'verdi');
    // Il modal e' nascosto: si scrive da script.
    await page.evaluate(() => {
        const el = document.getElementById('nota');
        el.value = 'riservata';
        el.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await clean(page);
    await page.evaluate(() => document.getElementById('filtro').remove());
    await clean(page);
    await page.fill('#tag-dentro', 'tre');
    await until(page, DIRTY, true);
    assert.deepEqual(await page.evaluate(() => window.warnings), ['wiSaveBar: il nome "tag[]" sta dentro e fuori da una zona esclusa, quindi conta intero']);
});

test('B19', async () => {
    const page = await open();
    await page.locator('#nome').pressSequentially('x');
    await until(page, STATE, 'dirty-buttons');
    await page.evaluate(() => { new FormData(document.forms[0]); });
    await settle(page);
    assert.deepEqual(await page.evaluate(() => [document.querySelector('.wi-save-bar').dataset.wiState, wiSaveBar.isDirty(document.forms[0])]), ['dirty-buttons', true]);
    // Il dialogo blocca la pagina: la evaluate finisce solo dopo dismiss().
    const shown = dialog(page);
    const leaving = page.evaluate(() => { location.href = '/altra'; });
    const exit = await shown;
    assert.equal(exit?.type(), 'beforeunload');
    await exit.dismiss();
    await leaving;
    assert.equal(page.url(), PAGE);
});

test('B20', async () => {
    // Circa 5000 campi: 2000 caselle di un albero da 2000 nodi, 600 righe da 5 campi e FilePond.
    const fields = `<div class="col-12"><div id="albero"></div><div class="d-none" data-wi-tree-values="albero[]">${Array.from({ length: 2000 }, (_, i) => `<input type="checkbox" class="d-none" name="albero[]" value="n${i}"${i % 2 ? '' : ' checked'}>`).join('')}</div></div>
<div class="col-12"><input type="file" id="foto" name="foto"></div>
<div class="col-12" id="righe">${Array.from({ length: 600 }, (_, r) => `<div class="wi-repeater-row"><fieldset class="row g-2">${['a', 'b', 'c', 'd', 'e'].map(c => `<div class="col"><input type="text" class="form-control" name="righe[${r}][${c}]" value="${c}${r}"></div>`).join('')}</fieldset></div>`).join('')}</div>
<div class="col-12"><button type="button" class="btn btn-outline-dark" id="aggiungi" onclick="const rows = document.getElementById('righe'); rows.append(rows.lastElementChild.cloneNode(true))">Aggiungi riga</button></div>`;
    // 20 radici da 9 figli con 10 foglie ciascuno; il caricamento FilePond avanza del 10% ogni 50ms.
    const setup = `let n = 0;
const node = depth => ({ id: 'n' + n++, text: 'Voce', children: depth < 2 ? Array.from({ length: depth ? 10 : 9 }, () => node(depth + 1)) : [] });
$('#albero').on('ready.jstree', () => window.treeReady = true).jstree({ core: { data: Array.from({ length: 20 }, () => node(0)) }, checkbox: { keep_selected_style: false, three_state: false }, plugins: ['checkbox'] });
FilePond.create(document.getElementById('foto'), { server: { process: (field, file, metadata, load, error, progress) => {
    let done = 0;
    const timer = setInterval(() => {
        progress(true, done += 10, 100);
        if (done === 100) { clearInterval(timer); load('f1'); }
    }, 50);
    return { abort: () => clearInterval(timer) };
} } })`;
    const init = () => {
        window.perf = { tasks: [], formData: [] };
        perf.observer = new PerformanceObserver(list => perf.tasks.push(...list.getEntries()));
        perf.observer.observe({ type: 'longtask' });
        const Native = FormData;
        window.FormData = class extends Native {
            constructor(...args) {
                const start = performance.now();
                super(...args);
                perf.formData.push(performance.now() - start);
            }
        };
        addEventListener('DOMContentLoaded', () => perf.dcl = performance.now());
        addEventListener('loaded', () => perf.loaded = performance.now());
    };
    const scenarios = {
        digitazione: page => page.locator('#nome').pressSequentially('abcdefghijklmnopqrst'),
        clic: async page => { for (let i = 0; i < 20; i++) await page.mouse.click(640, 25); },
        albero: page => page.evaluate(() => new Promise(resolve => $('#albero').one('open_all.jstree', resolve).jstree('open_all'))),
        righe: async page => { for (let i = 0; i < 50; i++) await page.click('#aggiungi'); },
        filepond: async page => {
            const done = page.evaluate(() => new Promise(resolve => document.addEventListener('FilePond:processfile', resolve, { once: true })));
            await page.setInputFiles('.filepond--browser', { name: 'foto.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc(1024) });
            await done;
            await page.waitForTimeout(1000);
        }
    };
    // Task lunghi e FormData piu' lenta durante action; per il caricamento si conta da DOMContentLoaded.
    const measure = async (page, action, load = false) => {
        await page.evaluate(load => {
            perf.from = load ? perf.dcl : performance.now();
            if (!load) perf.formData = [];
        }, load);
        await action();
        await settle(page);
        return page.evaluate(() => {
            perf.tasks.push(...perf.observer.takeRecords());
            return { tasks: perf.tasks.filter(task => task.startTime >= perf.from).length, formData: Math.max(0, ...perf.formData), time: perf.loaded - perf.dcl };
        });
    };
    const runs = { con: [], senza: [] };
    for (let i = 0; i < 5; i++) {
        for (const [key, form] of [['con', 'data-wi-save-bar'], ['senza', '']]) {
            const page = await open({ fields, filler: 0, form, setup, init });
            // Senza l'isola la regione status non c'e': il confronto e' davvero A/B.
            assert.equal(await page.evaluate(STATUS) !== null, key === 'con');
            const run = { caricamento: await measure(page, () => page.waitForFunction(() => window.treeReady), true) };
            for (const [name, action] of Object.entries(scenarios)) run[name] = await measure(page, () => action(page));
            runs[key].push(run);
            await page.context().close();
        }
    }
    const median = list => [...list].sort((a, b) => a - b)[Math.floor(list.length / 2)];
    const fixed = value => Math.round(value * 10) / 10;
    for (const name of ['caricamento', ...Object.keys(scenarios)]) {
        const pick = (key, field) => runs[key].map(run => run[name][field]);
        const line = (key, field) => fixed(median(pick(key, field))) + '/' + fixed(Math.max(...pick(key, field)));
        const load = name === 'caricamento' ? `, DOMContentLoaded-loaded ${line('con', 'time')} contro ${line('senza', 'time')} ms` : '';
        console.log(`B20 ${name} (mediana/massimo): task lunghi ${line('con', 'tasks')} con l'isola, ${line('senza', 'tasks')} senza; FormData ${line('con', 'formData')} ms${load}`);
        assert.ok(median(pick('con', 'tasks')) <= median(pick('senza', 'tasks')), `${name}: task lunghi in piu' con l'isola`);
        assert.ok(median(pick('con', 'formData')) < 16, `${name}: FormData oltre 16ms`);
    }
});

run().catch(error => { console.error(error); process.exitCode = 1; });
```

- [ ] **Step 2: Lancia solo i casi B8-B20**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" SAVE_BAR_ONLY=B8,B9,B10,B11,B12,B13,B14,B15,B16,B17,B18,B19,B20 node test/backend-save-bar.browser.test.cjs
```

Expected: PASS con uscita 0, in circa tre minuti, quasi tutti di B20. B20 stampa sei righe di misure e alla fine c'è la riga del totale. Su un Mac di sviluppo:

```text
B20 caricamento (mediana/massimo): task lunghi 0/0 con l'isola, 0/0 senza; FormData 0.4/0.6 ms, DOMContentLoaded-loaded 43.4/43.6 contro 23.2/36.9 ms
B20 digitazione (mediana/massimo): task lunghi 0/0 con l'isola, 0/0 senza; FormData 0.6/0.7 ms
B20 clic (mediana/massimo): task lunghi 0/0 con l'isola, 0/0 senza; FormData 0.5/1.5 ms
B20 albero (mediana/massimo): task lunghi 1/1 con l'isola, 1/1 senza; FormData 0.5/0.9 ms
B20 righe (mediana/massimo): task lunghi 0/0 con l'isola, 0/0 senza; FormData 0.6/3 ms
B20 filepond (mediana/massimo): task lunghi 0/0 con l'isola, 0/0 senza; FormData 0.3/0.4 ms
backend-save-bar browser: 13 passed
```

I numeri delle misure cambiano da un lancio all'altro. Contano l'uscita 0 e la riga del totale; il massimo della `FormData` può avere un picco isolato (fino a 44ms visti in `righe`) senza toccare la mediana. Se B20 fallisce con `<scenario>: task lunghi in piu' con l'isola` o `<scenario>: FormData oltre 16ms`, rilancialo a macchina scarica: se il fallimento si ripete, il costo è in `saveBar.js` e la correzione va nel task della Parte C che l'ha scritto, non nel test. Lo stesso vale per un errore di `saveBar.js` negli altri casi.

- [ ] **Step 3: Lancia tutto il file e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" node test/backend-save-bar.browser.test.cjs
npm test
```

Expected: il test browser esce con 0, stampa le sei righe di B20 e alla fine queste due righe:

```text
backend-save-bar browser: 19 passed
non riprodotto B4: con deviceScaleFactor 1.25 il rapporto di Salva in vista e' 1
```

Con un Chrome che ha ancora il bug di arrotondamento: `backend-save-bar browser: 20 passed` e nessuna riga `non riprodotto`. `npm test` finisce ancora con `backend-save-bar: 47 tests passed`, perché i test B non sono nella catena.

- [ ] **Step 4: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add test/backend-save-bar.browser.test.cjs
git commit -m "test(backend): test browser di rilevamento, esclusioni e prestazioni dell'isola" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `1 file changed`.

### Task B3: copie e click, invio e uscita, conferma con più form, ritorno dalla cache (B21-B35)

Repo: **lib**.

Spec sez. 6.3, casi B21-B35 (sez. 1 Bottoni copiati, 3 B-C, E-G, I-L, 5.4, 5.8, Q3, Q12, Q13, Q19). Il task aggiunge al file del Task B1 il blocco `// Task B3: ...`, subito prima dell'ultima riga, e non cambia gli helper del Task B1:
- in testa al blocco, tre frammenti di markup usati da più casi di questo task: `TITOLO`, un campo in cima al form (scriverci non porta Salva in vista), `ALTRA`, un link per uscire dalla pagina, e `SECONDO`, un secondo form tracciato con `onsubmit="loadingSpinner()"` come i form del backend;
- B21-B28: quali bottoni diventano copie, come la copia viene ripulita e segue l'originale, click, tastiera e doppio invio;
- B29-B33: lo stato `submitting`, l'avviso di uscita e la conferma quando un altro form ha modifiche;
- B34-B35: il ritorno dalla cache, simulato con `pageshow` e vero con la bfcache di Chrome.

Scelte di questo task:
- con un POST trattenuto dalla route, Chrome non risponde a `page.evaluate` (e nemmeno a `Runtime.evaluate` via CDP) finché la richiesta resta aperta, e la lettura resta appesa (`evaluate` non ha timeout). B25, B28, B30 e B34 leggono i POST con `posts()`, poi li chiudono con `route.abort('aborted')` e solo dopo leggono la pagina: `ERR_ABORTED` non apre una pagina d'errore, quindi il documento resta, ancora in `submitting`;
- B22 mette l'originale `#copiato` dentro il form, tra i bottoni della fixture (`buttons`), così `check()` agisce davvero su di lui. La classe `disabled` tiene spenta la sua copia anche quando `check()` abilita gli altri: la copia segue l'originale e non `check()`;
- B21 mostra il `.modal` legacy con uno stile in linea: i submit di modal, offcanvas e `[data-wi-save-bar-ignore]` restano fuori per il contenitore, non perché non si vedono (il caso controlla `checkVisibility()`);
- B26: quando la copia col focus si disabilita, Chrome sposta il focus sul `body`, quindi Invio e Spazio arrivano lì, e Spazio scorre la pagina (l'isola può tornare `hidden`). Il caso controlla che non partano POST e che lo stato non sia `submitting`, e prima di ogni modo riporta la pagina in cima e aspetta `buttons` con la copia attiva;
- B27: il secondo Invio e il secondo click non arrivano alla guardia `!submitting` del click della copia. Al primo invio `render()` rende l'isola `inert`, il focus lascia la copia e il secondo Invio va al `body`; intanto `#loading-spinner` copre l'isola, che ha `--wi-save-bar-zindex: 7`, e il secondo click colpisce lo spinner. Il caso protegge questa catena (spinner sopra l'isola e `inert` in `submitting`); la guardia resta come difesa in profondità, ma con input fidato non si osserva;
- B30 legge lo stato nel listener `formdata` del form: Chrome lo lancia nello stesso task del `submit`, quindi il caso prova che `submitting` e `inert` ci sono già in quel task. Il listener considera solo i FormData con `upload` o `upload-add`, perché anche `new FormData(form)` di `formSnapshot` lancia `formdata`, ma senza il bottone che invia;
- oltre ai dialoghi veri, B31 controlla l'evento sintetico annullabile (`defaultPrevented`, sez. 3 G), e B32 avvolge `window.confirm` in `setup` per sapere se lo spinner era acceso quando la domanda è comparsa;
- B32: i form con `target=_blank` o `formtarget="_blank"` hanno `action` o `formaction` `about:blank`. La route è della pagina, non del contesto: un invio sbagliato in una finestra nuova andrebbe in rete verso `save-bar.test`. Così il caso fallisce solo per la domanda in più. La ricerca GET della topbar si aggiunge in `setup`, perché la fixture non ce l'ha; nel form `#accesso` il campo password resta vuoto;
- B33 lancia `reset(form); form.submit()` senza aspettare l'`evaluate`: se comparisse un `beforeunload`, il dialogo aperto lo bloccherebbe. Il caso chiude l'eventuale dialogo e poi controlla che non ci sia stato;
- B34 mette `#sezione` nell'indirizzo prima dell'invio: con il `#`, `location.replace` sarebbe solo un salto di ancora, senza richiesta. La GET dopo il POST prova che l'indirizzo è stato ripulito. Nel ramo "non in invio" il campo torna vuoto senza eventi, quindi lo stato resta `dirty-buttons` finché `pageshow` non fa ricalcolare;
- B35: il browser del runner ha `--disable-back-forward-cache` tra gli argomenti di default di Playwright. Per questo B35 lancia un suo Chrome con `ignoreDefaultArgs` e lo chiude nel `finally` insieme al server `node:http`. Anche la pagina "Salvato" scrive il suo `pageshow`: contano solo quelli dopo Indietro. Con Chrome e `playwright-core` 1.63.0 il caso si riproduce (dopo Indietro arriva `pageshow true`); se la pagina non entra nella bfcache, il caso finisce in "non riprodotto".

**Files:**
- Modify: `test/backend-save-bar.browser.test.cjs` (blocco nuovo subito prima dell'ultima riga)

**Interfaces:**
- Consumes:
  - dal Task B1: `test`, `open` (con `fields`, `buttons`, `html`, `setup`) e `page.requests` con `route`, `until`, `settle`, `atIsland`, `dialog`, `posts`, `notReproduced`, `fixture()` e `asset()` (per il server di B35), `STATE`, `COPIES`, `BUTTONS`, `PAGE`, `chromium`, `path`, `assert`;
  - dalla Parte C: `window.wiSaveBar` con `reset(form)`, `isDirty(form)` e `labels.confirmOther`; le copie `.wi-save-bar-btn` in ordine di pagina, con `type=button`, classi `btn*` senza `btn-check`, `disabled` e nessun `id` o `data-*`; `inert` dell'isola in `hidden` e `submitting`; il `beforeunload` di `preventPageExit`; la conferma `confirmOther` e il `pageshow` con `persisted`; `loadingSpinner()` in `backend/js/utility.js`;
  - dalla Parte I: `visibility: hidden` dell'isola in `submitting` (`save-bar.css`);
  - `check()` da `backend/js/pageSetUp.js`, caricato dalla fixture.
- Produces: le costanti di markup `TITOLO`, `ALTRA` e `SECONDO`, in testa al blocco. Le usano solo i casi B21-B35.

- [ ] **Step 1: Aggiungi i casi B21-B35**

In `test/backend-save-bar.browser.test.cjs` sostituisci l'ultima riga del file:

```js
run().catch(error => { console.error(error); process.exitCode = 1; });
```

con il blocco del task seguito dalla stessa riga, che resta l'ultima:

```js
// Task B3: copie e click, invio e uscita, conferma con piu form, ritorno dalla cache (B21-B35)

// Un campo in cima al form: scriverci non porta Salva in vista.
const TITOLO = '<div class="col-12"><label for="titolo" class="form-label">Titolo</label><input type="text" class="form-control" id="titolo" name="titolo"></div>';
// Un link per uscire dalla pagina e un secondo form tracciato, con uno spinner come quello del backend.
const ALTRA = '<div class="col-12"><a id="altra" href="/altra">Altra pagina</a></div>';
const SECONDO = '<form id="secondo" method="POST" action="/secondo" onsubmit="loadingSpinner()" data-wi-save-bar><input type="text" class="form-control" id="altro" name="altro"><button type="submit" class="btn btn-dark" name="invia">Invia</button></form>';

test('B21', async () => {
    const page = await open({
        fields: `<div class="col-12"><button class="btn btn-dark" name="primo">Primo</button></div>
<div class="col-12"><input type="submit" class="btn btn-dark" name="secondo" value="Secondo"></div>
<div class="modal" style="display: block; position: static; height: auto"><button type="submit" name="modale">Modale</button></div>
<div class="offcanvas offcanvas-end"><button type="submit" name="laterale">Laterale</button></div>
<div class="col-12" data-wi-save-bar-ignore><button type="submit" name="ignorato">Ignorato</button></div>
<div class="col-12"><button type="submit" name="nascosto" style="display: none">Nascosto</button></div>`,
        html: '<button type="submit" form="resource-layout-form" name="esterno" class="btn btn-dark">Esterno</button>'
    });
    await until(page, COPIES, ['Primo', 'Secondo', 'Salva e aggiungi', 'Salva', 'Esterno']);
    // Modal, offcanvas e ignorato restano fuori per il contenitore, non perche' non si vedono.
    assert.deepEqual(await page.evaluate(() => ['modale', 'laterale', 'ignorato'].map(name => document.querySelector(`[name=${name}]`).checkVisibility())), [true, true, true]);
});

test('B22', async () => {
    const page = await open({
        fields: TITOLO.replace('<input', '<input required'),
        buttons: BUTTONS + `<button type="submit" id="copiato" name="copia" value="1" formaction="/copia" formmethod="post" formtarget="_self" formnovalidate form="resource-layout-form" onclick="window.inline = (window.inline || 0) + 1" data-bs-toggle="modal" data-bs-target="#finestra" data-wi-qc-endpoint="/crea" class="btn btn-primary wi-submit btn-check disabled"><i id="icona" class="bi bi-files" data-wi-icon="files"></i> Copia</button>`,
        html: '<div class="modal" id="finestra" tabindex="-1"><div class="modal-dialog"><div class="modal-content p-3">Finestra</div></div></div>',
        setup: "$(document).on('click', '.wi-submit', event => { window.delegated = (window.delegated || 0) + 1; event.preventDefault(); })"
    });
    await until(page, COPIES, ['Salva e aggiungi', 'Salva', 'Copia']);
    const copy = await page.evaluate(() => {
        const el = document.querySelectorAll('.wi-save-bar-btn')[2];
        return { attributes: el.getAttributeNames().sort(), type: el.type, className: el.className, children: [...el.querySelectorAll('*')].flatMap(child => child.getAttributeNames()) };
    });
    assert.deepEqual(copy, { attributes: ['class', 'disabled', 'type'], type: 'button', className: 'btn btn-primary disabled wi-save-bar-btn', children: ['class'] });
    // check() cambia solo gli originali: la classe disabled tiene spenta la copia di Copia anche col campo pieno.
    const disabled = () => [...document.querySelectorAll('.wi-save-bar-btn')].map(el => el.disabled);
    await until(page, disabled, [true, true, true]);
    const step = change => page.evaluate(`{ const titolo = document.getElementById('titolo'), copiato = document.getElementById('copiato'); ${change}; check(); }`);
    await step("titolo.value = 'Titolo'");
    await until(page, disabled, [false, false, true]);
    await step("copiato.classList.remove('disabled')");
    await until(page, disabled, [false, false, false]);
    await step("titolo.value = ''");
    await until(page, disabled, [true, true, true]);
    await step("titolo.value = 'Titolo'");
    await until(page, disabled, [false, false, false]);
    await page.locator('.wi-save-bar-btn').nth(2).click();
    assert.deepEqual(await page.evaluate(() => [window.delegated, window.inline]), [1, 1]);
});

test('B23', async () => {
    const page = await open({ fields: TITOLO.replace('<input', '<input required') });
    await until(page, COPIES, ['Salva e aggiungi', 'Salva']);
    await page.evaluate(() => window.kept = document.querySelectorAll('.wi-save-bar-btn')[1]);
    // Modifica e lettura nello stesso evaluate, con un solo frame in mezzo.
    const after = change => page.evaluate(`(async () => {
        ${change};
        await new Promise(requestAnimationFrame);
        const copy = document.querySelectorAll('.wi-save-bar-btn')[1];
        return [copy === window.kept, copy.textContent.trim(), copy.disabled];
    })()`);
    assert.deepEqual(await after(''), [true, 'Salva', true]);
    assert.deepEqual(await after("document.getElementById('titolo').value = 'Titolo'; check()"), [true, 'Salva', false]);
    assert.deepEqual(await after("document.querySelector('[name=upload]').textContent = 'Salva ora'"), [true, 'Salva ora', false]);
    assert.deepEqual(await after("document.getElementById('titolo').value = ''; check()"), [true, 'Salva ora', true]);
});

test('B24', async () => {
    const buttons = BUTTONS.replace('>Salva e aggiungi<', '><i class="bi bi-plus-lg"></i> Salva e aggiungi<');
    // Prima sul bottone, poi sull'icona: il clic arriva alla copia e l'invio parte dall'originale.
    for (const target of ['.wi-save-bar-btn', '.wi-save-bar-btn i']) {
        const page = await open({ buttons });
        await until(page, STATE, 'buttons');
        const box = await page.locator(target).first().boundingBox();
        assert.ok(box.width > 0 && box.height > 0, `${target} senza dimensioni`);
        await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
        await posts(page, 1);
        await page.waitForTimeout(500);
        assert.deepEqual((await posts(page)).map(post => post.names.filter(name => name.startsWith('upload'))), [['upload-add']], target);
    }
});

test('B25', async () => {
    const page = await open();
    await until(page, STATE, 'buttons');
    await page.evaluate(() => window.kept = document.querySelectorAll('.wi-save-bar-btn')[1]);
    const box = await page.locator('.wi-save-bar-btn').nth(1).boundingBox();
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down();
    await page.evaluate(() => document.querySelector('[name=upload]').textContent = 'Salva ora');
    await until(page, COPIES, ['Salva e aggiungi', 'Salva ora']);
    await page.mouse.up();
    await posts(page, 1);
    await page.waitForTimeout(500);
    const list = await posts(page);
    assert.deepEqual(list.map(post => post.names.filter(name => name.startsWith('upload'))), [['upload']]);
    // Col POST trattenuto la pagina non risponde: annullato, il documento resta e si legge.
    await list[0].route.abort('aborted');
    assert.ok(await page.evaluate(() => window.kept === document.querySelectorAll('.wi-save-bar-btn')[1]), 'copia ricreata');
});

test('B26', async () => {
    const page = await open();
    await until(page, STATE, 'buttons');
    const copy = page.locator('.wi-save-bar-btn').nth(1);
    const ways = ['salva.disabled = true', "salva.classList.add('disabled')", "salva.setAttribute('aria-disabled', 'true')"];
    for (const way of ways) {
        // Da capo: Salva sotto lo schermo, originale attivo e focus sulla copia.
        await page.evaluate(() => {
            window.scrollTo(0, 0);
            const salva = document.querySelector('[name=upload]');
            salva.disabled = false;
            salva.classList.remove('disabled');
            salva.removeAttribute('aria-disabled');
        });
        await until(page, STATE, 'buttons');
        await until(page, () => document.querySelectorAll('.wi-save-bar-btn')[1].disabled, false);
        await copy.focus();
        await page.evaluate(`{ const salva = document.querySelector('[name=upload]'); ${way}; }`);
        await until(page, () => document.querySelectorAll('.wi-save-bar-btn')[1].disabled, true);
        const box = await copy.boundingBox();
        await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
        await page.keyboard.press('Enter');
        await page.keyboard.press('Space');
        await page.waitForTimeout(500);
        assert.equal((await posts(page)).length, 0, way);
        // Spazio sul body scorre la pagina: l'isola puo' sparire, ma non per un invio.
        assert.notEqual(await page.evaluate(STATE), 'submitting', way);
    }
    await page.evaluate(() => {
        window.scrollTo(0, 0);
        const salva = document.querySelector('[name=upload]');
        salva.disabled = false;
        salva.classList.remove('disabled');
        salva.setAttribute('aria-disabled', 'false');
    });
    await until(page, STATE, 'buttons');
    await until(page, () => document.querySelectorAll('.wi-save-bar-btn')[1].disabled, false);
    await copy.focus();
    await page.keyboard.press('Enter');
    assert.deepEqual((await posts(page, 1)).map(post => post.names.filter(name => name.startsWith('upload'))), [['upload']]);
});

test('B27', async () => {
    const keyboard = await open();
    await until(keyboard, STATE, 'buttons');
    await keyboard.locator('.wi-save-bar-btn').nth(1).focus();
    await keyboard.keyboard.press('Enter');
    await keyboard.keyboard.press('Enter');
    await posts(keyboard, 1);
    await keyboard.waitForTimeout(500);
    assert.equal((await posts(keyboard)).length, 1, 'Invio due volte');
    const page = await open();
    await until(page, STATE, 'buttons');
    // Lo spinner acceso sta sopra l'isola.
    assert.ok(await atIsland(page), "l'isola non e' in cima");
    await page.evaluate(() => loadingSpinner(true));
    assert.ok(await atIsland(page, '#loading-spinner'), "lo spinner non copre l'isola");
    await page.evaluate(() => loadingSpinner(false));
    const box = await page.locator('.wi-save-bar-btn').nth(1).boundingBox();
    await page.mouse.dblclick(box.x + box.width / 2, box.y + box.height / 2);
    await posts(page, 1);
    await page.waitForTimeout(500);
    assert.equal((await posts(page)).length, 1, 'doppio click');
});

test('B28', async () => {
    // Il primo onclick azzera il form prima dell'invio, il secondo annulla l'invio.
    for (const [onclick, sent] of [['window.counter = (window.counter || 0) + 1; wiSaveBar.reset(this.form)', 1], ['window.counter = (window.counter || 0) + 1; return false', 0]]) {
        const page = await open({ fields: TITOLO, buttons: BUTTONS.replace('name="upload"', `name="upload" onclick="${onclick}"`) });
        await page.locator('#titolo').pressSequentially('x');
        await until(page, STATE, 'dirty-buttons');
        const shown = dialog(page);
        await page.locator('.wi-save-bar-btn').nth(1).click({ noWaitAfter: true });
        assert.equal(await shown, null, onclick);
        const list = await posts(page, sent);
        assert.equal(list.length, sent, onclick);
        await Promise.all(list.map(post => post.route.abort('aborted')));
        assert.deepEqual(await page.evaluate(() => [window.counter, wiSaveBar.isDirty(document.getElementById('resource-layout-form'))]), [1, !sent], onclick);
    }
});

test('B29', async () => {
    const page = await open({ fields: TITOLO + ALTRA });
    await page.locator('#titolo').pressSequentially('x');
    await until(page, STATE, 'dirty-buttons');
    // Un listener di invio registrato dopo l'isola, come un form AJAX che poi spegne lo spinner.
    await page.evaluate(() => window.addEventListener('submit', event => {
        event.preventDefault();
        loadingSpinner(false);
    }));
    await page.locator('.wi-save-bar-btn').nth(1).click();
    await settle(page);
    assert.deepEqual(await page.evaluate(() => [document.querySelector('.wi-save-bar').dataset.wiState, document.querySelector('.wi-save-bar').inert]), ['dirty-buttons', false]);
    assert.equal((await posts(page)).length, 0);
    const shown = dialog(page);
    const click = page.locator('#altra').click({ noWaitAfter: true });
    const box = await shown;
    assert.equal(box?.type(), 'beforeunload');
    await box.dismiss();
    await click;
});

test('B30', async () => {
    // Nel formdata dell'invio (stesso task del submit) si legge lo stato dell'isola; il FormData di formSnapshot non ha il bottone.
    const setup = `document.getElementById('resource-layout-form').addEventListener('formdata', event => {
        if (!event.formData.has('upload') && !event.formData.has('upload-add')) return;
        const bar = document.querySelector('.wi-save-bar');
        window.seen = [bar.dataset.wiState, bar.inert];
    })`;
    const sends = [
        ['upload', page => page.locator('.wi-save-bar-btn').nth(1).click({ noWaitAfter: true })],
        ['upload-add', page => page.locator('#titolo').press('Enter', { noWaitAfter: true })],
        ['upload', page => page.evaluate(() => document.getElementById('resource-layout-form').requestSubmit(document.querySelector('[name=upload]')))]
    ];
    for (const [submitter, send] of sends) {
        const page = await open({ fields: TITOLO, setup });
        await until(page, STATE, 'buttons');
        await send(page);
        const [post] = await posts(page, 1);
        assert.deepEqual(post.names.filter(name => name.startsWith('upload')), [submitter]);
        // Col POST trattenuto la pagina non risponde: annullato, il documento resta e si legge.
        await post.route.abort('aborted');
        await settle(page);
        assert.deepEqual(await page.evaluate(() => [window.seen, getComputedStyle(document.querySelector('.wi-save-bar')).visibility]), [['submitting', true], 'hidden'], submitter);
    }
});

test('B31', async () => {
    const leave = () => {
        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        return event.defaultPrevented;
    };
    // Pulito, dopo un gesto vero: nessun avviso e la pagina se ne va.
    const unchanged = await open({ fields: TITOLO + ALTRA });
    await unchanged.locator('#titolo').click();
    assert.equal(await unchanged.evaluate(leave), false);
    const none = dialog(unchanged);
    await unchanged.locator('#altra').click();
    assert.equal(await none, null);
    await unchanged.waitForURL('**/altra');
    // Sporco: avviso sintetico e reale; dopo un invio non annullato piu' nessun avviso.
    const page = await open({ fields: TITOLO + ALTRA });
    await page.locator('#titolo').pressSequentially('x');
    await until(page, STATE, 'dirty-buttons');
    assert.equal(await page.evaluate(leave), true);
    const shown = dialog(page);
    const click = page.locator('#altra').click({ noWaitAfter: true });
    const box = await shown;
    assert.equal(box?.type(), 'beforeunload');
    await box.dismiss();
    await click;
    const after = dialog(page);
    await page.locator('.wi-save-bar-btn').nth(1).click({ noWaitAfter: true });
    assert.equal(await after, null);
    assert.equal((await posts(page, 1)).length, 1);
    // Due form: il primo sporco, un clic nel secondo pulito toglie la scritta ma non l'avviso.
    const two = await open({ fields: TITOLO + ALTRA, html: SECONDO });
    await two.locator('#titolo').pressSequentially('x');
    await until(two, STATE, 'dirty-buttons');
    await two.locator('#altro').click();
    await until(two, () => document.querySelector('.wi-save-bar').dataset.wiState.startsWith('dirty'), false);
    const warned = dialog(two);
    const away = two.locator('#altra').click({ noWaitAfter: true });
    const again = await warned;
    assert.equal(again?.type(), 'beforeunload');
    await again.dismiss();
    await away;
});

test('B32', async () => {
    // confirm registra il messaggio e se lo spinner era acceso in quel momento.
    const setup = `const ask = window.confirm;
    window.confirm = message => {
        window.asked = (window.asked || []).concat([[message, !document.getElementById('loading-spinner').classList.contains('d-none')]]);
        return ask.call(window, message);
    }`;
    // Clic su selector senza aspettare: i dialoghi arrivati entro un secondo, con risposta answers[i] (true = OK).
    const submit = async (page, selector, answers = []) => {
        const messages = [];
        const handler = box => {
            messages.push(box.message());
            return answers[messages.length - 1] ? box.accept() : box.dismiss();
        };
        page.on('dialog', handler);
        await page.locator(selector).click({ noWaitAfter: true });
        await page.waitForTimeout(1000);
        page.off('dialog', handler);
        return messages;
    };
    // Due form tracciati, il primo sporco: invio del secondo con Annulla e poi con OK.
    const page = await open({ fields: TITOLO, html: SECONDO, setup });
    const other = await page.evaluate(() => wiSaveBar.labels.confirmOther);
    await page.locator('#titolo').pressSequentially('x');
    await until(page, STATE, 'dirty-buttons');
    assert.deepEqual(await submit(page, '#secondo [name=invia]'), [other]);
    assert.equal((await posts(page)).length, 0);
    assert.deepEqual(await page.evaluate(() => [window.asked, document.getElementById('loading-spinner').classList.contains('d-none')]), [[[other, true]], true]);
    // Con OK niente avviso di uscita: il messaggio registrato e' solo la conferma.
    assert.deepEqual(await submit(page, '#secondo [name=invia]', [true]), [other]);
    const [post] = await posts(page, 1);
    assert.deepEqual([new URL(post.url).pathname, post.names], ['/secondo', ['altro', 'invia']]);
    // Form non tracciati, Button::post e form che non navigano la finestra, col form principale sporco.
    const forms = await open({
        fields: TITOLO,
        html: `<form id="accesso" method="POST" action="/accesso" onsubmit="loadingSpinner()"><input type="password" class="form-control" name="password" autocomplete="off"><button type="submit" class="btn btn-dark">Entra</button></form>
<form id="nuova" method="POST" action="about:blank" target="_blank"><button type="submit" class="btn btn-dark">Nuova finestra</button></form>
<form id="finestra" method="dialog"><button type="submit" class="btn btn-dark">Dialogo</button></form>
<form id="naviga" method="POST" action="/naviga"><button type="submit" class="btn btn-dark" formtarget="_blank" formaction="about:blank">Altra finestra</button><button type="submit" class="btn btn-dark" formmethod="dialog">Chiudi</button></form>
<form id="ajax" method="POST" action="/ajax"><button type="submit" class="btn btn-dark">Ajax</button></form>
<form method="post" action="/duplica" onsubmit="return window.confirm(&quot;Duplicare il record?&quot;);"><button type="submit" class="btn btn-dark" id="duplica">Duplica</button></form>`,
        setup: `document.getElementById('topbar').insertAdjacentHTML('beforeend', '<form id="cerca" action="/cerca" onsubmit="loadingSpinner()"><input type="search" name="q"><button type="submit">Cerca</button></form>');
        document.getElementById('ajax').addEventListener('submit', event => event.preventDefault())`
    });
    await forms.locator('#titolo').pressSequentially('x');
    await until(forms, STATE, 'dirty-buttons');
    for (const selector of ['#accesso button', '#cerca button']) {
        assert.deepEqual(await submit(forms, selector), [other], selector);
        assert.ok(await forms.evaluate(() => document.getElementById('loading-spinner').classList.contains('d-none')), selector);
    }
    for (const selector of ['#nuova button', '#finestra button', '#naviga button >> nth=0', '#naviga button >> nth=1', '#ajax button']) {
        assert.deepEqual(await submit(forms, selector), [], selector);
    }
    assert.deepEqual(await submit(forms, '#duplica', [true]), ['Duplicare il record?', other]);
    assert.deepEqual(forms.requests.filter(request => request.method === 'POST' || request.url.includes('/cerca')), []);
    // Al contrario: la ricerca compilata non ferma l'invio del form tracciato pulito.
    const search = await open({ setup: `document.getElementById('topbar').insertAdjacentHTML('beforeend', '<form id="cerca" action="/cerca"><input type="search" name="q"></form>')` });
    await search.locator('#cerca input').pressSequentially('mario');
    assert.deepEqual(await submit(search, '.wi-save-bar-btn >> nth=1'), []);
    assert.equal((await posts(search, 1)).length, 1);
});

test('B33', async () => {
    const page = await open({ fields: TITOLO });
    await page.locator('#titolo').pressSequentially('x');
    await until(page, STATE, 'dirty-buttons');
    const shown = dialog(page);
    const submit = page.evaluate(() => {
        const form = document.getElementById('resource-layout-form');
        wiSaveBar.reset(form);
        form.submit();
    });
    const box = await shown;
    await box?.dismiss();
    await submit;
    assert.equal(box, null);
    assert.equal((await posts(page, 1)).length, 1);
});

test('B34', async () => {
    // Non in invio: nessuna navigazione, lo stato si ricalcola (il campo torna vuoto senza eventi).
    const idle = await open({ fields: TITOLO });
    await idle.locator('#titolo').pressSequentially('x');
    await until(idle, STATE, 'dirty-buttons');
    await idle.evaluate(() => document.getElementById('titolo').value = '');
    await settle(idle);
    assert.equal(await idle.evaluate(STATE), 'dirty-buttons');
    await idle.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
    await until(idle, STATE, 'buttons');
    await idle.waitForTimeout(1000);
    assert.deepEqual(idle.requests.map(request => request.method), ['GET']);
    // In invio: spinner spento e una GET all'indirizzo senza #, che con il # sarebbe solo un salto di ancora.
    const page = await open();
    await until(page, STATE, 'buttons');
    await page.evaluate(() => history.replaceState(null, '', '#sezione'));
    await page.locator('.wi-save-bar-btn').nth(1).click({ noWaitAfter: true });
    const [post] = await posts(page, 1);
    // Col POST trattenuto la pagina non risponde: annullato, la pagina resta in invio e si legge.
    await post.route.abort('aborted');
    const spinner = await page.evaluate(() => {
        const before = document.getElementById('loading-spinner').classList.contains('d-none');
        window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
        return [before, document.getElementById('loading-spinner').classList.contains('d-none')];
    });
    assert.deepEqual(spinner, [false, true]);
    await page.waitForURL(PAGE);
    assert.deepEqual(page.requests.map(request => [request.method, request.url]), [['GET', PAGE], ['POST', PAGE + 'salva'], ['GET', PAGE]]);
});

test('B35', async () => {
    const http = require('node:http');
    const fs = require('node:fs');
    const types = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff' };
    // Server vero, senza no-store e senza page.route: la bfcache di Chrome resta possibile.
    const log = [];
    const server = http.createServer((request, response) => {
        const { pathname } = new URL(request.url, 'http://127.0.0.1');
        const file = asset(pathname);
        if (file) {
            response.writeHead(200, { 'Content-Type': types[path.extname(file)] || 'application/octet-stream' });
            return fs.createReadStream(file).pipe(response);
        }
        if (pathname !== '/' && pathname !== '/salva') {
            response.writeHead(404);
            return response.end();
        }
        let body = '';
        request.on('data', chunk => body += chunk);
        request.on('end', () => {
            log.push([request.method, pathname, body.length > 0]);
            response.writeHead(200, { 'Content-Type': 'text/html' });
            response.end(request.method === 'POST' ? '<p>Salvato</p>' : fixture());
        });
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const url = `http://127.0.0.1:${server.address().port}/`;
    const cached = await chromium.launch({ channel: process.env.CHROME_CHANNEL || 'chrome', headless: true, ignoreDefaultArgs: ['--disable-back-forward-cache'] });
    try {
        const page = await (await cached.newContext({ viewport: { width: 1280, height: 720 } })).newPage();
        page.setDefaultTimeout(5000);
        const shows = [];
        page.on('console', message => message.text().startsWith('pageshow ') && shows.push(message.text()));
        await page.addInitScript(() => window.addEventListener('pageshow', event => console.log('pageshow ' + event.persisted)));
        await page.goto(url);
        await until(page, STATE, 'buttons');
        await page.locator('.wi-save-bar-btn').nth(1).click();
        await page.waitForURL(url + 'salva');
        await page.waitForSelector('text=Salvato');
        // Anche la pagina Salvato scrive il suo pageshow: contano quelli dopo Indietro.
        const from = shows.length;
        await page.goBack({ waitUntil: 'commit' });
        for (let i = 0; i < 40 && log.length < 3; i++) await new Promise(resolve => setTimeout(resolve, 50));
        await page.waitForTimeout(500);
        if (!shows.slice(from).includes('pageshow true')) notReproduced('dopo Indietro nessun pageshow con persisted true: ' + shows.slice(from).join(', '));
        assert.deepEqual(log, [['GET', '/', false], ['POST', '/salva', true], ['GET', '/', false]]);
    } finally {
        await cached.close();
        server.close();
    }
});

run().catch(error => { console.error(error); process.exitCode = 1; });
```

- [ ] **Step 2: Lancia solo i casi B21-B35**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" SAVE_BAR_ONLY=B21,B22,B23,B24,B25,B26,B27,B28,B29,B30,B31,B32,B33,B34,B35 node test/backend-save-bar.browser.test.cjs
```

Expected: PASS in meno di un minuto, con uscita 0 e una sola riga:

```text
backend-save-bar browser: 15 passed
```

Se il Chrome installato non mette la pagina nella bfcache, al posto di quella riga ci sono `backend-save-bar browser: 14 passed` e `non riprodotto B35: dopo Indietro nessun pageshow con persisted true: ...`, e l'uscita resta 0. Se un caso fallisce per un errore di `saveBar.js` o di `save-bar.css`, la correzione va nel task della Parte C o I che ha scritto quel codice, non nel test.

- [ ] **Step 3: Lancia tutto il file e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" node test/backend-save-bar.browser.test.cjs
npm test
```

Expected: il test browser esce con 0 e il totale dei passati cresce di 15 rispetto al lancio completo del Task B2: con i casi B1-B35 la riga del totale è `backend-save-bar browser: 34 passed`, e le altre righe che B1 e B2 stampano già (`non riprodotto B4: ...` e le misure di B20) restano uguali. `npm test` finisce ancora con `backend-save-bar: 47 tests passed`, perché i test B non sono nella catena.

- [ ] **Step 4: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add test/backend-save-bar.browser.test.cjs
git commit -m "test(backend): test browser di copie, invio, uscita e ritorno dalla cache" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `1 file changed`.

### Task B4: focus, accessibilità, touch, livelli, posizione, tema scuro, stampa e riduzione del movimento (B36-B47)

Repo: **lib**.

Spec sez. 6.3, casi B36-B47 (sez. 3 K e L, 4 Stati, Posizione, Aspetto, Fascia e Varie, Q15). Il task aggiunge al file del Task B1 il blocco `// Task B4: ...`, subito prima dell'ultima riga, e non cambia gli helper del Task B1. In testa al blocco c'è un solo helper nuovo, `still(page)`, che serve a B37 e B38.

Scelte di questo task:
- **B36** parte dall'originale "Salva" e preme Tab: nel DOM le copie vengono subito dopo, perché la barra è in fondo al `body`. In `buttons` e `dirty-buttons` il Tab entra nelle copie, e questo è il controllo positivo. Non ci entra in `hidden`, e lo si prova due volte: durante la dissolvenza, quando l'isola ha ancora `visibility: visible` e il focus lo ferma solo `inert`, e dopo. Non ci entra nemmeno in `dirty-label` e `submitting`. In ogni stato la regione `.visually-hidden[role=status]` non sta dentro un elemento `[inert]`.
  - Per `submitting` il form invia verso un iframe nascosto (`target="invio"`). Il POST resta trattenuto e `posts(page, 1)` lo conta, ma la pagina non ha una navigazione in sospeso. Con il POST nella pagina stessa, `page.evaluate` dopo il Tab a volte non torna più (1 lancio su 6 nelle prove).
  - In `dirty-label` le copie sono già fuori dal Tab per `display: none` su `.wi-save-bar-actions`, quindi `actions.inert` non si distingue da un test browser.
- **B37** porta il fondo di Salva a 40px dal fondo della finestra (`buttons`), mette il focus sulla copia di Salva e scorre di 1px per frame finché l'isola va in `hidden`. `y` è l'ultima posizione data dal test.
  - L'originale conta come visibile con 1px di tolleranza (`rect.bottom <= innerHeight - band + 1`). Lì un `focus()` senza `preventScroll` sposta la pagina di 1px, per rispettare `scroll-padding-bottom`, e il caso lo vede: `1114 !== 1113`.
  - `still()` aspetta 5 frame con `scrollY` fermo, perché Bootstrap mette `scroll-behavior: smooth` su `:root`.
- **B38** usa un obbligatorio vuoto `#ultimo` dopo i bottoni, in due pagine. In ognuna prima il focus, poi `reportValidity()` (che restituisce `false`), e dopo lo scorrimento il campo deve stare tra la topbar (50px) e la fascia (`innerHeight - 96`).
  - **Campo ultimo della pagina.** Lo tiene sopra la fascia la riserva in fondo a `#content`; senza, finisce a 684,5px su 720.
  - **Campo con altro sotto il form, portato a 40px dal fondo, sotto l'isola.** Qui lo sposta `scroll-padding-bottom`; senza, resta a 679,5px.
- **B39** usa `hasTouch`, `isMobile` e una finestra 390x844. Con questo Chrome `pointer: coarse` vale, quindi il caso non è "non riprodotto".
  - **Tap sui campi di testo.** Vale per input di testo, `textarea`, Quill (`.ql-editor`) ed EditorJS (`.ce-paragraph`). Vale anche per la ricerca di Select2, che sta in `#content` fuori dal form. Ognuno mette `data-wi-save-bar-keyboard` e rende l'isola `visibility: hidden`.
  - **Tap sugli altri controlli.** Checkbox, file, range, color e submit lasciano l'isola visibile. Qui il tap non sempre sposta il focus, quindi il caso lo porta sull'elemento dopo il tap: la regola sotto test dipende dall'elemento a fuoco.
  - **Blur.** Dopo ogni blur l'isola torna.
  - **Passaggio tra due campi di testo.** Tra il primo e il secondo campo `sampler()` non vede nessun frame con l'isola visibile.
  - **Senza `pointer: coarse`.** Su una pagina desktop 1280x720 lo zoom con le dita si simula con `Emulation.setPageScaleFactor` via CDP. Con 1,25 `visualViewport.height` vale 576 e l'isola è visibile; con 1,5 vale 480 e l'isola è nascosta; con 1 torna a 720. La soglia è 0,75 × 720 = 540.
  - **Stato dell'isola.** Il tap sulla checkbox sporca il form, quindi lo stato atteso è `buttons` o `dirty-buttons`.
- **B40** apre una pagina nuova per ogni popup, perché il modal resterebbe aperto sopra i click dopo e Select2 ricorda il verso in cui si è aperto.
  - Ogni attivatore sta al centro orizzontale e a 150px dal fondo (100px l'icon picker), così il popup si apre sopra il centro dell'isola.
  - `atIsland()` deve dare l'isola prima dell'apertura e il popup dopo, con l'isola ancora `visible`.
  - La sidebar si apre dal suo sottomenu a 1280px e dal menu mobile `#menu` a 375px.
  - L'icon picker richiede `backend/js/form/iconPicker.js` in `<head>`.
- **B41** inserisce ogni popup nello stesso task in cui legge lo stile. Lì l'isola ha già `opacity: 0`, `visibility: hidden`, nessuna animazione e `pointer-events: none`: la regola `:has()` non ha transizione.
- **B42 e B43** iniettano `* { transition: none !important; }`: sono casi di geometria (sez. 6.3).
  - **B42, colonna.** A 1280px il centro dell'isola sta a 680px, cioè al centro di 80-1280, e a 768px sta a 384px, sempre con 1px di tolleranza. Senza il controllo del centro un'isola larga quanto la colonna a partire da 0 passerebbe.
  - **B42, righe.** Si contano le righe di testo delle copie, non i bottoni: un `Range` sul contenuto di ogni copia dà un rettangolo per riga, quindi conta anche un'etichetta che va a capo dentro il bottone. Con due bottoni il conteggio per bottone non supererebbe mai 2. A 1280px le righe devono essere 1: è il controllo positivo che il conteggio vede il testo.
  - **B43.** Lo scroll orizzontale della pagina resta nel caso come chiede la spec, ma da solo non può fallire: la barra è `position: fixed` e non allarga il documento. Per questo il caso ripete due volte un'etichetta lunga e controlla, a 1280px e a 320px, anche la colonna dell'isola (100-1260px e 20-300px), che ogni copia stia nella colonna e che nessuna etichetta esca dal suo bottone invece di andare a capo (`scrollWidth > clientWidth`).
  - B43 non controlla la fascia: con un'etichetta lunghissima l'uscita è un limite documentato.
- **B44** usa `html[data-bs-theme="dark"]` (opzione `theme: 'dark'`) e un obbligatorio vuoto, così `checkInput()` disabilita i submit. Le copie disabilitate sono due `btn-dark` ("Salva e aggiungi" e "Salva") e una `btn-outline-dark` ("Bozza", già `disabled` nel markup).
  - **Sfondo e bordo.** Lo sfondo è quello di `--bs-body-bg`, risolto in rgb da un elemento sonda che risolve anche `--bs-border-color`. "Bordo più chiaro" vuol dire: sullo sfondo scuro il bordo dell'isola ha un contrasto WCAG maggiore di quello di `--bs-border-color`. Oggi vale 2,69 contro 1,50. Nessuna soglia fissa: la spec chiede solo "più chiaro".
  - **Bottoni disabilitati.** Sfondo di `btn-dark`, bordo e testo di `btn-outline-dark`: tutti diversi dallo sfondo dell'isola.
- **B45** aspetta la fine delle transizioni dopo `emulateMedia({ media: 'print' })`. `#content` ha `transition: all`, e anche il padding torna a 20px in 300ms.
- **B46** non toglie le transizioni (sez. 6.3): due giri su e giù con `sampler()` da `init` vedono almeno una dissolvenza di `opacity` e nessuna animazione di `transform`.
- **B47** usa `form: ''`: il form della fixture non ha `data-wi-save-bar`, quindi nessun form è tracciato. Legge dopo `settle()`, così si vedrebbe anche una barra creata in ritardo.

**Files:**
- Modify: `test/backend-save-bar.browser.test.cjs` (blocco nuovo subito prima dell'ultima riga)

**Interfaces:**
- Consumes:
  - dal Task B1: `test()`, `run()`, `open()` con `fields`, `buttons`, `form`, `html`, `head`, `setup`, `theme`, `init` e le opzioni del contesto (`viewport`, `hasTouch`, `isMobile`, `reducedMotion`), `until()`, `settle()`, `place()`, `toBottom()`, `rect()`, `atIsland()`, `posts()`, `sampler()`, `notReproduced()`, le costanti `STATE`, `COPIES`, `SALVA` e `BUTTONS`;
  - dalla Parte C, `saveBar.js`: `data-wi-state`; `bar.inert` in `hidden` e `submitting`; `actions.inert` in `dirty-label`; la regione `role=status` messa nel `body` dopo la barra. Al passaggio a `hidden` o `dirty-label` il focus va dalla copia all'originale con `preventScroll`. `data-wi-save-bar-keyboard` si decide nel frame dopo `focusin` e `focusout`, e al resize di `visualViewport`, con `pointer: coarse` o la soglia 0,75;
  - dalla Parte I, `save-bar.css`: `--wi-save-bar-zindex` (7), la colonna da `left: 80px` con `padding-inline: 20px`, `--wi-save-bar-offset` (16px, 12px fino a 768px), il bordo `--wi-save-bar-border-color` del tema scuro, le regole `:has()` dei popup, `prefers-reduced-motion`, la riserva in `@media screen` e `@media print`. Da `header.css`: i colori disabilitati di `btn-dark` e `btn-outline-dark` nel tema scuro e `scroll-padding-top: 50px`;
  - dalla lib: `backend/js/form/iconPicker.js`, Select2 con il tema `bootstrap4`, il datepicker di `data-wi-date`, `loadingSpinner()`.
- Produces: `still(page) → Promise<void>`, in testa al blocco, che aspetta 5 frame con `scrollY` fermo. Lo usano solo B37 e B38.

- [ ] **Step 1: Aggiungi i casi B36-B47**

In `test/backend-save-bar.browser.test.cjs` sostituisci l'ultima riga del file:

```js
run().catch(error => { console.error(error); process.exitCode = 1; });
```

con il blocco del task seguito dalla stessa riga, che resta l'ultima:

```js
// Task B4: focus, accessibilita, touch, livelli, posizione, tema scuro, stampa e movimento (B36-B47)

// B37 e B38: aspetta che lo scorrimento sia fermo da 5 frame (Bootstrap scorre in modo liscio).
function still(page) {
    return page.evaluate(async () => {
        let last = NaN, same = 0;
        while (same < 5) {
            await new Promise(requestAnimationFrame);
            same = scrollY === last ? same + 1 : 0;
            last = scrollY;
        }
    });
}

test('B36', async () => {
    const page = await open({ html: '<iframe name="invio" hidden></iframe>' });
    // Tab dall'originale Salva: nel DOM le copie vengono subito dopo. Vero se il focus entra nell'isola.
    const tab = async () => {
        await page.locator(SALVA).evaluate(el => el.focus({ preventScroll: true }));
        await page.keyboard.press('Tab');
        return page.evaluate(() => !!document.activeElement.closest('.wi-save-bar'));
    };
    // Per ogni regione status della save bar: vero se sta in una zona inert.
    const status = () => page.evaluate(() => [...document.querySelectorAll('.visually-hidden[role=status]')].map(el => !!el.closest('[inert]')));
    await until(page, STATE, 'buttons');
    assert.deepEqual(await status(), [false]);
    await place(page, 40);
    await settle(page);
    assert.equal(await tab(), true, 'in buttons il focus deve raggiungere le copie');
    await toBottom(page);
    await until(page, STATE, 'hidden');
    // Ancora nella dissolvenza: l'isola e' visibile, il focus lo ferma solo inert.
    assert.equal(await page.locator('.wi-save-bar').evaluate(el => getComputedStyle(el).visibility), 'visible');
    assert.equal(await tab(), false, 'focus nelle copie durante la dissolvenza');
    await settle(page);
    assert.equal(await tab(), false, 'focus nelle copie in hidden');
    assert.deepEqual(await status(), [false]);
    await page.locator('#nome').pressSequentially('x');
    await until(page, STATE, 'dirty-label');
    assert.equal(await tab(), false, 'focus nelle copie in dirty-label');
    assert.deepEqual(await status(), [false]);
    await place(page, 40);
    await until(page, STATE, 'dirty-buttons');
    assert.equal(await tab(), true, 'in dirty-buttons il focus deve raggiungere le copie');
    assert.deepEqual(await status(), [false]);
    // Invio verso l'iframe: lo stato e' submitting, ma la pagina non ha una navigazione in sospeso
    // (con il POST trattenuto nella pagina stessa, page.evaluate a volte non torna piu').
    await page.evaluate(() => document.getElementById('resource-layout-form').target = 'invio');
    await page.locator('.wi-save-bar-btn').nth(1).click({ noWaitAfter: true });
    await until(page, STATE, 'submitting');
    assert.equal((await posts(page, 1)).length, 1);
    assert.equal(await tab(), false, 'focus nelle copie in submitting');
    assert.deepEqual(await status(), [false]);
});

test('B37', async () => {
    const page = await open();
    await place(page, 40);
    await until(page, STATE, 'buttons');
    await settle(page);
    await page.locator('.wi-save-bar-btn').nth(1).focus();
    // 1px per frame finche' l'isola si nasconde: l'originale e' appena tornato visibile.
    // y e' l'ultima posizione data dal test: il passaggio del focus non deve spostarla, neanche di 1px.
    const y = await page.evaluate(async () => {
        let y = scrollY;
        while (document.querySelector('.wi-save-bar').dataset.wiState !== 'hidden') {
            window.scrollBy({ top: 1, behavior: 'instant' });
            y = scrollY;
            await new Promise(requestAnimationFrame);
        }
        return y;
    });
    await still(page);
    assert.equal(await page.evaluate(selector => document.activeElement === document.querySelector(selector), SALVA), true);
    assert.equal(await page.evaluate(() => scrollY), y);
});

test('B38', async () => {
    const FIELD = BUTTONS + '<input class="form-control mt-2" id="ultimo" name="ultimo" required>';
    // Dopo lo scorrimento il campo sta tra la topbar e la fascia.
    const visible = async page => {
        await still(page);
        const box = await rect(page, '#ultimo');
        const height = await page.evaluate(() => innerHeight);
        assert.ok(box.top >= 50 && box.bottom <= height - 96, `#ultimo da ${box.top} a ${box.bottom}px`);
    };
    // Ultimo campo della pagina: sopra la fascia lo tiene la riserva in fondo a #content.
    const last = await open({ buttons: FIELD });
    await last.locator('#ultimo').focus();
    await visible(last);
    await last.locator('#ultimo').blur();
    await last.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    assert.equal(await last.locator('#ultimo').evaluate(el => el.reportValidity()), false);
    await visible(last);
    // Con altro sotto il form e il campo a 40px dal fondo, sotto l'isola: lo sposta scroll-padding-bottom.
    const under = await open({ buttons: FIELD, html: '<div style="height: 800px"></div>' });
    await place(under, 40, '#ultimo');
    await under.locator('#ultimo').focus();
    await visible(under);
    await under.locator('#ultimo').blur();
    await place(under, 40, '#ultimo');
    assert.equal(await under.locator('#ultimo').evaluate(el => el.reportValidity()), false);
    await visible(under);
});

test('B39', async () => {
    const TOUCH = `<div class="col-12 d-flex flex-wrap gap-2">
<input class="form-control" id="testo" name="testo">
<textarea class="form-control" id="note" name="note"></textarea>
<div class="ql-editor" id="quill" contenteditable="true">Quill</div>
<div class="codex-editor"><div class="ce-paragraph cdx-block" id="editorjs" contenteditable="true">EditorJS</div></div>
<select id="scelta" name="scelta" style="width: 200px"><option>Uno</option><option>Due</option></select>
<input type="checkbox" id="spunta" name="spunta"><input type="file" id="file" name="file"><input type="range" id="range" name="range"><input type="color" id="colore" name="colore">
<button type="submit" id="invia" form="altro">Invia</button>
</div>`;
    const HIDDEN = { keyboard: true, visibility: 'hidden' };
    const SHOWN = { keyboard: false, visibility: 'visible' };
    const bar = () => {
        const el = document.querySelector('.wi-save-bar');
        return { keyboard: el.hasAttribute('data-wi-save-bar-keyboard'), visibility: getComputedStyle(el).visibility };
    };
    // Dopo le transizioni: l'isola in uno stato visibile (il tap sulla spunta sporca il form), a fuoco l'elemento atteso.
    const check = async (page, focused, expected) => {
        await settle(page);
        assert.match(await page.evaluate(STATE), /^(dirty-)?buttons$/);
        assert.equal(await page.evaluate(focused => document.activeElement.matches(focused), focused), true, `a fuoco non c'e' ${focused}`);
        assert.deepEqual(await page.evaluate(bar), expected, focused);
    };
    const blur = async page => {
        await page.evaluate(() => document.activeElement.blur());
        await settle(page);
        assert.deepEqual(await page.evaluate(bar), SHOWN, 'dopo il blur');
    };
    const page = await open({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 }, fields: TOUCH, html: '<form id="altro" onsubmit="event.preventDefault()"></form>', setup: "$('#scelta').select2({ theme: 'bootstrap4', width: 'style', dropdownParent: $('#content') })" });
    if (!await page.evaluate(() => matchMedia('(pointer: coarse)').matches)) notReproduced('con hasTouch e isMobile pointer: coarse non vale');
    await until(page, STATE, 'buttons');
    for (const id of ['#testo', '#note', '#quill', '#editorjs']) {
        await page.locator(id).tap();
        await check(page, id, HIDDEN);
        await blur(page);
    }
    // La ricerca di Select2 sta in #content, fuori dal form.
    await page.locator('#scelta + .select2').tap();
    await check(page, '.select2-search__field', HIDDEN);
    assert.equal(await page.evaluate(() => !!document.activeElement.closest('form')), false);
    await blur(page);
    await page.keyboard.press('Escape');
    for (const id of ['#spunta', '#file', '#range', '#colore', '#invia']) {
        await page.locator(id).tap();
        // Il tap non sempre sposta il focus: lo si porta sull'elemento.
        await page.locator(id).focus();
        await check(page, id, SHOWN);
        await blur(page);
    }
    // Tra due campi di testo nessun frame mostra l'isola.
    await page.locator('#testo').tap();
    await check(page, '#testo', HIDDEN);
    await page.evaluate(sampler);
    await page.locator('#note').tap();
    await check(page, '#note', HIDDEN);
    const samples = await page.evaluate(() => window.samples);
    assert.ok(samples.length > 0);
    assert.deepEqual(samples.filter(sample => sample.visibility !== 'hidden'), []);
    // Senza pointer: coarse decide l'altezza del visualViewport rispetto a 0,75 x innerHeight (540px su 720).
    const desk = await open({ fields: '<div class="col-12"><input class="form-control" id="testo" name="testo"></div>' });
    assert.equal(await desk.evaluate(() => matchMedia('(pointer: coarse)').matches), false);
    await desk.locator('#testo').click();
    await until(desk, STATE, 'buttons');
    await check(desk, '#testo', SHOWN);
    const cdp = await desk.context().newCDPSession(desk);
    for (const [scale, height, expected] of [[1.25, 576, SHOWN], [1.5, 480, HIDDEN], [1, 720, SHOWN]]) {
        await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: scale });
        await check(desk, '#testo', expected);
        assert.equal(await desk.evaluate(() => Math.round(visualViewport.height)), height);
    }
});

test('B40', async () => {
    // I popup si aprono sopra il centro dell'isola: ogni attivatore sta a gap px dal fondo.
    const LAYERS = `<div style="height: 800px"></div><div class="col-12 d-flex flex-column align-items-center gap-5">
<div class="dropdown" id="tendina"><button type="button" class="btn btn-outline-dark" data-bs-toggle="dropdown">Azioni</button><ul class="dropdown-menu">${['Uno', 'Due', 'Tre', 'Quattro'].map(text => `<li><button type="button" class="dropdown-item">${text}</button></li>`).join('')}</ul></div>
<select id="scelta" name="scelta" style="width: 240px"><option>Uno</option><option>Due</option><option>Tre</option></select>
<input class="form-control" id="data" name="data" data-wi-date="true" style="width: 240px">
<div class="input-group wi-icon-picker" style="width: 240px"><span class="input-group-text"><i class="bi wi-show-icon"></i></span><input class="form-control" id="icona" name="icona" data-wi-icon-picker><button type="button" class="btn btn-outline-secondary" data-wi-icon-picker-open="icona"><i class="bi bi-grid"></i></button></div>
<button type="button" class="btn btn-outline-dark" id="apri" data-bs-toggle="modal" data-bs-target="#finestra">Finestra</button>
</div>`;
    const MODAL = '<div class="modal fade" id="finestra" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-body">Finestra</div></div></div></div>';
    const layers = [
        { popup: '.dropdown-menu.show', anchor: '#tendina', gap: 150, show: page => page.locator('#tendina [data-bs-toggle]').click() },
        { popup: '.select2-dropdown', anchor: '#scelta + .select2', gap: 150, show: page => page.locator('#scelta + .select2').click() },
        { popup: '.datepicker', anchor: '#data', gap: 150, show: page => page.locator('#data').click() },
        { popup: '.wi-icon-picker-panel', anchor: '.wi-icon-picker', gap: 100, show: page => page.locator('[data-wi-icon-picker-open]').click() },
        { popup: '#finestra', show: page => page.locator('#apri').click() },
        { popup: '#sidebar', show: page => page.locator('[data-bs-target="#menu-risorse"]').click() },
        { popup: '#loading-spinner', show: page => page.evaluate(() => loadingSpinner(true)) },
        { popup: '#sidebar', viewport: { width: 375, height: 812 }, show: page => page.locator('#menu').click() }
    ];
    for (const { popup, anchor, gap, show, viewport } of layers) {
        const page = await open({ fields: LAYERS, html: MODAL, head: '<script src="/src/build/backend/js/form/iconPicker.js"></script>', setup: "$('#scelta').select2({ theme: 'bootstrap4', width: 'style', dropdownParent: $('#content') })", ...viewport && { viewport } });
        if (anchor) await place(page, gap, anchor);
        await until(page, STATE, 'buttons');
        await settle(page);
        assert.equal(await atIsland(page), true, `senza ${popup} deve vincere l'isola`);
        await show(page);
        await settle(page);
        assert.equal(await page.locator('.wi-save-bar').evaluate(el => getComputedStyle(el).visibility), 'visible');
        assert.equal(await atIsland(page, popup), true, `${popup} deve coprire l'isola`);
    }
});

test('B41', async () => {
    const page = await open();
    await until(page, STATE, 'buttons');
    await settle(page);
    for (const html of ['<div class="ce-popover ce-popover--opened"></div>', '<div class="tc-popover tc-popover--opened"></div>', '<div data-wi-save-bar-hide-when-open></div>']) {
        assert.equal(await atIsland(page), true);
        // Letto nello stesso task dell'inserimento: nascosta subito, senza transizione.
        const now = await page.evaluate(html => {
            document.body.insertAdjacentHTML('beforeend', `<div id="popup">${html}</div>`);
            const bar = document.querySelector('.wi-save-bar');
            const style = getComputedStyle(bar);
            return { opacity: style.opacity, visibility: style.visibility, animations: bar.getAnimations().length, events: getComputedStyle(document.querySelector('.wi-save-bar-island')).pointerEvents };
        }, html);
        assert.deepEqual(now, { opacity: '0', visibility: 'hidden', animations: 0, events: 'none' }, html);
        assert.equal(await atIsland(page), false, html);
        await page.evaluate(() => document.getElementById('popup').remove());
        await settle(page);
    }
});

test('B42', async () => {
    const page = await open({ head: '<style>* { transition: none !important; }</style>' });
    // Riquadro dell'isola, righe di testo delle copie (anche un'etichetta che va a capo) e scroll orizzontale, dopo le animazioni.
    const measure = async width => {
        await page.setViewportSize({ width, height: 720 });
        await place(page, 40);
        await until(page, STATE, 'buttons');
        await settle(page);
        return page.evaluate(() => {
            const box = document.querySelector('.wi-save-bar-island').getBoundingClientRect();
            const rows = new Set([...document.querySelectorAll('.wi-save-bar-btn:not([hidden])')].flatMap(el => {
                const range = document.createRange();
                range.selectNodeContents(el);
                return [...range.getClientRects()].map(line => Math.round(line.top));
            }));
            return { left: box.left, right: box.right, top: box.top, bottom: innerHeight - box.bottom, center: (box.left + box.right) / 2, height: innerHeight, rows: rows.size, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth };
        });
    };
    assert.deepEqual(await page.evaluate(COPIES), ['Salva e aggiungi', 'Salva']);
    const wide = await measure(1280);
    assert.ok(wide.left >= 100 && wide.right <= 1260, `a 1280px isola da ${wide.left} a ${wide.right}px`);
    assert.ok(Math.abs(wide.center - 680) < 1, `a 1280px centro a ${wide.center}px`);
    assert.ok(Math.abs(wide.bottom - 16) < 1, `a 1280px ${wide.bottom}px dal fondo`);
    const tablet = await measure(768);
    assert.ok(tablet.left >= 20 && tablet.right <= 748, `a 768px isola da ${tablet.left} a ${tablet.right}px`);
    assert.ok(Math.abs(tablet.center - 384) < 1, `a 768px centro a ${tablet.center}px`);
    assert.ok(Math.abs(tablet.bottom - 12) < 1, `a 768px ${tablet.bottom}px dal fondo`);
    const phone = await measure(320);
    assert.equal(wide.rows, 1);
    assert.ok(phone.rows <= 2, `a 320px le copie stanno su ${phone.rows} righe`);
    assert.ok(phone.top >= phone.height - 96, `a 320px l'isola esce dalla fascia (${phone.top}px)`);
    assert.ok(phone.left >= 20 && phone.right <= 300, `a 320px isola da ${phone.left} a ${phone.right}px`);
    assert.equal(phone.scroll, 0);
});

test('B43', async () => {
    const page = await open({ head: '<style>* { transition: none !important; }</style>' });
    await page.locator(SALVA).evaluate(el => el.textContent = 'Salva il record e torna subito all\'elenco completo di tutte le risorse del modulo corrente'.repeat(2));
    // Colonna dell'isola per larghezza: 80px di menu solo sopra 768px, 20px di margine per lato.
    for (const [width, left, right] of [[1280, 100, 1260], [320, 20, 300]]) {
        await page.setViewportSize({ width, height: 720 });
        await place(page, 40);
        await until(page, STATE, 'buttons');
        await settle(page);
        const view = await page.evaluate(([left, right]) => {
            const box = document.querySelector('.wi-save-bar-island').getBoundingClientRect();
            // Copie che escono dalla colonna o con l'etichetta fuori dal bottone invece che a capo.
            const wide = [...document.querySelectorAll('.wi-save-bar-btn:not([hidden])')].filter(el => {
                const copy = el.getBoundingClientRect();
                return copy.left < left || copy.right > right || el.scrollWidth > el.clientWidth;
            }).map(el => el.textContent.slice(0, 20));
            return { left: box.left, right: box.right, wide, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth };
        }, [left, right]);
        assert.equal(view.scroll, 0, `scroll orizzontale a ${width}px`);
        assert.ok(view.left >= left && view.right <= right, `a ${width}px isola da ${view.left} a ${view.right}px`);
        assert.deepEqual(view.wide, [], `a ${width}px`);
    }
});

test('B44', async () => {
    // Una copia disabilitata per tipo: il campo obbligatorio vuoto disabilita gli invii.
    const page = await open({ theme: 'dark', buttons: BUTTONS + '<button type="submit" name="bozza" class="btn btn-outline-dark mt-2" disabled>Bozza</button><input class="form-control mt-2" id="vuoto" name="vuoto" required>' });
    await place(page, 40);
    await until(page, STATE, 'buttons');
    await settle(page);
    const look = await page.evaluate(() => {
        // I colori calcolati in rgb: un elemento sonda risolve --bs-body-bg e --bs-border-color.
        const probe = document.body.appendChild(document.createElement('div'));
        probe.style.cssText = 'background-color: var(--bs-body-bg); border-color: var(--bs-border-color)';
        const island = getComputedStyle(document.querySelector('.wi-save-bar-island'));
        const copies = [...document.querySelectorAll('.wi-save-bar-btn:not([hidden])')].map(el => {
            const style = getComputedStyle(el);
            return { text: el.textContent.trim(), disabled: el.disabled, background: style.backgroundColor, border: style.borderTopColor, color: style.color };
        });
        return { body: getComputedStyle(probe).backgroundColor, border: getComputedStyle(probe).borderTopColor, background: island.backgroundColor, edge: island.borderTopColor, copies };
    });
    // Contrasto WCAG tra un colore rgb/rgba e lo sfondo opaco.
    const channels = color => color.match(/[\d.]+/g).map(Number);
    const luminance = rgb => {
        const [r, g, b] = rgb.map(value => (value /= 255) <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const contrast = (color, background) => {
        const [r, g, b, a = 1] = channels(color);
        const base = channels(background);
        const mixed = [r, g, b].map((value, i) => value * a + base[i] * (1 - a));
        const [light, dark] = [luminance(mixed), luminance(base)].sort((x, y) => y - x);
        return (light + 0.05) / (dark + 0.05);
    };
    assert.equal(look.background, look.body);
    // Bordo piu' chiaro di --bs-border-color: sullo sfondo scuro vuol dire piu' contrasto.
    assert.ok(contrast(look.edge, look.background) > contrast(look.border, look.background), `bordo ${look.edge} non piu' chiaro di ${look.border}`);
    assert.deepEqual(look.copies.map(copy => [copy.text, copy.disabled]), [['Salva e aggiungi', true], ['Salva', true], ['Bozza', true]]);
    const [dark, , outline] = look.copies;
    assert.notEqual(dark.background, look.background, 'btn-dark disabilitato con lo sfondo dell\'isola');
    assert.notEqual(outline.border, look.background, 'bordo di btn-outline-dark disabilitato uguale allo sfondo');
    assert.notEqual(outline.color, look.background, 'testo di btn-outline-dark disabilitato uguale allo sfondo');
});

test('B45', async () => {
    const page = await open();
    await until(page, STATE, 'buttons');
    await page.emulateMedia({ media: 'print' });
    // #content ha transition: all, anche il padding-bottom torna a 20px in 300ms.
    await settle(page);
    const print = await page.evaluate(() => ({
        display: getComputedStyle(document.querySelector('.wi-save-bar')).display,
        reserve: getComputedStyle(document.documentElement).getPropertyValue('--wi-save-bar-reserve'),
        padding: getComputedStyle(document.getElementById('content')).paddingBottom,
        bottom: getComputedStyle(document.documentElement).scrollPaddingBottom
    }));
    assert.deepEqual(print, { display: 'none', reserve: '', padding: '20px', bottom: 'auto' });
});

test('B46', async () => {
    const page = await open({ reducedMotion: 'reduce', init: sampler });
    // Su e giu' due volte: la copia entra e esce solo con l'opacita'.
    for (const gap of [40, -1, 40, -1]) {
        await (gap < 0 ? toBottom(page) : place(page, gap));
        await until(page, STATE, gap < 0 ? 'hidden' : 'buttons');
        await settle(page);
    }
    const samples = await page.evaluate(() => window.samples);
    assert.ok(samples.some(sample => sample.animations.includes('opacity')), 'nessuna dissolvenza');
    assert.deepEqual(samples.filter(sample => sample.animations.includes('transform')), []);
    assert.equal(await page.locator('.wi-save-bar').evaluate(el => getComputedStyle(el).transform), 'none');
});

test('B47', async () => {
    const page = await open({ form: '' });
    await settle(page);
    const view = await page.evaluate(() => ({
        top: getComputedStyle(document.documentElement).scrollPaddingTop,
        bars: document.querySelectorAll('.wi-save-bar').length,
        on: document.documentElement.classList.contains('wi-save-bar-on'),
        reserve: getComputedStyle(document.documentElement).getPropertyValue('--wi-save-bar-reserve'),
        padding: getComputedStyle(document.getElementById('content')).paddingBottom
    }));
    assert.deepEqual(view, { top: '50px', bars: 0, on: false, reserve: '', padding: '20px' });
});

run().catch(error => { console.error(error); process.exitCode = 1; });
```

- [ ] **Step 2: Lancia solo i casi B36-B47**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" SAVE_BAR_ONLY=B36,B37,B38,B39,B40,B41,B42,B43,B44,B45,B46,B47 node test/backend-save-bar.browser.test.cjs
```

Expected: PASS in circa 30 secondi, con uscita 0 e una sola riga:

```text
backend-save-bar browser: 12 passed
```

Se Chrome non dà `pointer: coarse` con `hasTouch` e `isMobile`, la riga diventa `backend-save-bar browser: 11 passed` e ne compare una seconda, `non riprodotto B39: con hasTouch e isMobile pointer: coarse non vale`. Se B44 fallisce sui bottoni disabilitati, mancano i colori disabilitati del tema scuro in `header.css` (Parte I). Se B36 fallisce su `durante la dissolvenza`, `bar.inert` non copre `hidden` (Parte C).

- [ ] **Step 3: Lancia tutto il file e la catena**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" node test/backend-save-bar.browser.test.cjs
npm test
```

Expected: il test browser esce con 0, senza righe `falliti:`, stampa le sei righe di B20 e alla fine queste due righe:

```text
backend-save-bar browser: 46 passed
non riprodotto B4: con deviceScaleFactor 1.25 il rapporto di Salva in vista e' 1
```

È il lancio completo del Task B3 (34) più i 12 casi di questo task. Con un Chrome che ha ancora il bug di arrotondamento: `backend-save-bar browser: 47 passed` e nessuna riga `non riprodotto`. Se il Chrome installato non mette la pagina nella bfcache (B35) o non dà `pointer: coarse` (B39), il totale scende di uno per ogni caso e compare la sua riga `non riprodotto`, come negli Step 2 del Task B3 e di questo task; l'uscita resta 0. `npm test` finisce ancora con `backend-save-bar: 47 tests passed`, perché i test B non sono nella catena.

- [ ] **Step 4: Commit**

```bash
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git add test/backend-save-bar.browser.test.cjs
git commit -m "test(backend): test browser dell'isola di salvataggio, focus, touch, livelli, posizione, tema scuro, stampa e movimento ridotto" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: un commit con `1 file changed`. Push e PR non fanno parte di questo task (solo su richiesta esplicita dell'utente).

## Parte P — App PHP: attributi del form, renderer, viste, pagine, documentazione

Copre la sezione 5 della spec lato app (5.1-5.8, 5.11, 5.12), i test P1-P17 della 6.4, la parte app e skill di "Documentazione" e i casi Q5, Q7, Q14, Q18, Q19, Q20; le "Alternative scartate" della sezione 5 non si reintroducono.

Tutti i task dell'app si eseguono dalla radice del worktree `APP_WT=/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar`, sul branch `backend-save-bar`, preparato nel Task S1 (con `vendor/` da `composer install` e il risultato di base in `$HOME/.cache/wonder-tooling/save-bar/app-baseline.txt`). La shell non conserva la cartella tra un comando e l'altro: ogni blocco parte da `cd /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar`. I test nuovi stanno in `tests/`, che è in `.gitignore`: si aggiungono con `git add -f`. I comportamenti della lib (`data-wi-save-bar*`, `--wi-save-bar-reserve`, `window.wiSaveBar`) arrivano dalle Parti C e I; le viste si verificano nel browser con i casi I1-I9 e I10 (Q7), non con helper PHP nuovi.

### Task P1: `AttributeString::render()`

Repo: **app**.

**Files:**
- Modify: `class/App/Support/AttributeString.php` (in coda alla classe, dopo `has()`: righe 61-64 di oggi)
- Test: `tests/App/Support/AttributeStringTest.php` (nuovo)

**Interfaces:**
- Consumes: `Wonder\View\Component::renderAttributes()` (protetto, letto con Reflection nel test P8)
- Produces: `Wonder\App\Support\AttributeString::render(array $attributes, array $reserved = []): string`, senza spazio iniziale (Q5)

- [ ] **Step 1: Scrivi il test che fallisce**

Crea `tests/App/Support/AttributeStringTest.php`:

```php
<?php
/** php tests/App/Support/AttributeStringTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\AttributeString;
use Wonder\View\Component;

$stringable = new class implements Stringable {
    public function __toString(): string
    {
        return 'da oggetto';
    }
};

check('P1 true stampa l\'attributo senza valore', fn () =>
    AttributeString::render(['data-wi-save-bar' => true]) === 'data-wi-save-bar'
);

check('P2 false e null omettono l\'attributo, anche il dirty', fn () =>
    AttributeString::render(['data-wi-save-bar' => false, 'data-wi-save-bar-dirty' => null]) === ''
);

check('P3 un array diventa valori uniti da uno spazio, ripuliti e senza vuoti', fn () =>
    AttributeString::render(['class' => ['a', 'b']]) === 'class="a b"'
    && AttributeString::render(['class' => ['a', '', ' b ']]) === 'class="a b"'
    && AttributeString::render(['data-x' => ['0', 'a']]) === 'data-x="0 a"'
);

check('P4 Stringable stampato; array annidati e oggetti nell\'array saltati senza avvisi', function () use ($stringable) {
    $warnings = [];
    set_error_handler(function (int $errno, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $html = AttributeString::render([
            'title' => $stringable,
            'data-list' => ['a', ['b'], new stdClass(), $stringable],
            'data-object' => new stdClass(),
        ]);
    } finally {
        restore_error_handler();
    }

    return $warnings === [] && $html === 'title="da oggetto" data-list="a da oggetto"';
});

check('P5 escape con ENT_QUOTES ed ENT_SUBSTITUTE', fn () =>
    AttributeString::render(['title' => "<a href=\"x\">'&'</a>"]) === 'title="&lt;a href=&quot;x&quot;&gt;&#039;&amp;&#039;&lt;/a&gt;"'
    && AttributeString::render(['title' => "a\xC3\x28b"]) === "title=\"a\u{FFFD}(b\""
);

check('P6 chiavi riservate ignorate anche con maiuscole o spazi; chiavi ripulite; chiave vuota saltata', fn () =>
    AttributeString::render([
        ' ID ' => 'x',
        'Class' => 'y',
        'method' => 'get',
        'ENCTYPE' => 'z',
        'action' => '/a',
        'onsubmit' => 'return false',
        ' data-wi-save-bar ' => true,
        '  ' => 'vuota',
    ], ['id', 'method', 'enctype', 'action', 'onsubmit', 'class']) === 'data-wi-save-bar'
);

check('P7 array vuoto da stringa vuota e nessuno spazio iniziale', fn () =>
    AttributeString::render([]) === ''
    && AttributeString::render(['a' => false, 'b' => true]) === 'b'
    && AttributeString::render(['' => 'x', 'b' => '1']) === 'b="1"'
);

check('P8 stesso risultato di View\\Component::renderAttributes() su scalari e Stringable', function () use ($stringable) {
    $renderAttributes = new ReflectionMethod(Component::class, 'renderAttributes');
    $component = new Component('test');
    $cases = [
        [],
        ['data-wi-save-bar' => true, 'data-wi-save-bar-dirty' => false, 'hidden' => null],
        ['title' => 'Salva', 'tabindex' => 0, 'data-ratio' => 1.5, 'data-empty' => ''],
        ['title' => "<\"'&>", 'data-object' => $stringable],
        [' data-spaced ' => 'x', '' => 'y'],
    ];

    foreach ($cases as $attributes) {
        if (AttributeString::render($attributes) !== $renderAttributes->invoke($component, $attributes)) {
            return false;
        }
    }

    return true;
});

summary();
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/App/Support/AttributeStringTest.php`
Expected: FAIL con `8 test, 8 falliti` (exit 1); ogni riga riporta `Call to undefined method Wonder\App\Support\AttributeString::render()`.

- [ ] **Step 3: Scrivi l'implementazione minima**

In `class/App/Support/AttributeString.php` sostituisci la chiusura della classe:

```php
    {
        return array_key_exists($name, self::parse($attribute));
    }
}
```

con:

```php
    {
        return array_key_exists($name, self::parse($attribute));
    }

    /**
     * Serializza un array key => value in attributi HTML, senza spazio
     * iniziale: lo aggiunge chi stampa. `true` stampa l'attributo senza
     * valore, `false` e `null` lo omettono. Le chiavi si ripuliscono dagli
     * spazi; quelle vuote e quelle in `$reserved` (confronto in minuscolo)
     * si saltano.
     *
     * Differenze da `View\Component::renderAttributes()`: dentro un array
     * `"0"` resta (si salta solo la voce vuota dopo il trim) e le voci non
     * scalari e non Stringable si saltano invece di essere convertite.
     *
     * @param array<array-key, mixed> $attributes
     * @param list<string> $reserved
     */
    public static function render(array $attributes, array $reserved = []): string
    {
        $reserved = array_map('strtolower', $reserved);
        $html = [];

        foreach ($attributes as $key => $value) {
            $key = trim((string) $key);

            if ($key === '' || $value === null || $value === false || in_array(strtolower($key), $reserved, true)) {
                continue;
            }

            if ($value === true) {
                $html[] = $key;
                continue;
            }

            if (is_array($value)) {
                $value = implode(' ', array_filter(array_map(
                    static fn (mixed $item): string => is_scalar($item) || $item instanceof \Stringable ? trim((string) $item) : '',
                    $value
                ), static fn (string $item): bool => $item !== ''));
            } elseif (!is_scalar($value) && !$value instanceof \Stringable) {
                continue;
            }

            $html[] = $key.'="'.htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return implode(' ', $html);
    }
}
```

- [ ] **Step 4: Esegui il test e verifica che passi**

Run: `php tests/App/Support/AttributeStringTest.php && php -l class/App/Support/AttributeString.php`
Expected: PASS con `8 test, 0 falliti` e `No syntax errors detected in class/App/Support/AttributeString.php`.

- [ ] **Step 5: Commit**

```bash
git add -f tests/App/Support/AttributeStringTest.php
git add class/App/Support/AttributeString.php
git commit -m "AttributeString: render() per gli attributi del form" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

### Task P2: opzione `attributes` e `hasSubmit()` in `ResourceFormLayoutRenderer`

Repo: **app**.

**Files:**
- Modify: `class/Backend/Support/ResourceFormLayoutRenderer.php` (import alle righe 5 e 11-12, `render()` alle righe 31 e 39-40, metodo nuovo dopo `render()` alla riga 51)
- Test: `tests/Backend/Support/ResourceFormLayoutRendererTest.php` (nuovo)

**Interfaces:**
- Consumes: `AttributeString::render()` (Task P1); `splitVisibility()` privato già presente nel renderer (lo stesso che stampa `data-visible-when` / `data-hidden-when`)
- Produces: opzione `'attributes' => []` di `ResourceFormLayoutRenderer::render()`, stampata sul `<form>` dopo `class` (chiavi riservate `id`, `method`, `enctype`, `action`, `onsubmit`, `class` ignorate); `ResourceFormLayoutRenderer::hasSubmit(Form $form, string $name = 'upload'): bool`, usata da `resource/form.php` (Task P3)

- [ ] **Step 1: Scrivi il test che fallisce**

Crea `tests/Backend/Support/ResourceFormLayoutRendererTest.php` (il tag `$tag` è quello che il renderer stampa oggi, scritto prima della modifica):

```php
<?php
/** php tests/Backend/Support/ResourceFormLayoutRendererTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Accordion;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Modal;
use Wonder\Elements\Components\QuickCreateButton;
use Wonder\Elements\Form\Components\Submit;
use Wonder\Elements\Form\Form;

// Tag di oggi, scritto prima della modifica.
$tag = '<form id="resource-layout-form" method="POST" enctype="multipart/form-data" action="" onsubmit="loadingSpinner()" class="row g-3">';
$form = static fn (array $components = []): Form => (new Form)->components($components);
$render = static fn (array $options = []): string => ResourceFormLayoutRenderer::render($form(), $options);

check('P9 senza attributi, con [] e con tutti false il tag resta quello di oggi', fn () =>
    $render() === $tag.'</form>'
    && $render(['attributes' => []]) === $tag.'</form>'
    && $render(['attributes' => ['data-wi-save-bar' => false, 'data-wi-save-bar-dirty' => false]]) === $tag.'</form>'
);

check('P10 gli attributi della barra dopo class, con lo spazio del renderer', fn () =>
    $render(['attributes' => ['data-wi-save-bar' => true, 'data-wi-save-bar-dirty' => true]])
        === substr($tag, 0, -1).' data-wi-save-bar data-wi-save-bar-dirty></form>'
    && $render(['attributes' => ['data-wi-save-bar' => true, 'data-wi-save-bar-dirty' => false]])
        === substr($tag, 0, -1).' data-wi-save-bar></form>'
);

check('P11 le sei chiavi riservate sono ignorate e restano i valori fissi', fn () =>
    $render(['attributes' => [
        'id' => 'altro',
        'method' => 'GET',
        'enctype' => 'text/plain',
        'action' => '/altrove',
        'onsubmit' => 'return false',
        ' Class ' => 'd-none',
        'data-wi-save-bar' => true,
    ]]) === substr($tag, 0, -1).' data-wi-save-bar></form>'
);

check('P12 hasSubmit trova upload dentro Card, Accordion aperto e Container annidati', fn () =>
    ResourceFormLayoutRenderer::hasSubmit($form([new Submit('upload')]))
    && ResourceFormLayoutRenderer::hasSubmit($form([
        (new Container)->components([
            (new Card)->components([
                (new Accordion('Altro'))->expanded()->components([new Submit('upload')]),
            ]),
        ]),
    ]))
);

check('P12 hasSubmit non entra in Modal e QuickCreate', function () use ($form) {
    $risorsa = new class {
        public static function slug(): string
        {
            return 'feature';
        }
    };

    return !ResourceFormLayoutRenderer::hasSubmit($form([
        Modal::make('Conferma')->components([new Submit('upload')]),
        QuickCreateButton::make(get_class($risorsa))->layout(fn () => (new Form)->components([new Submit('upload')])),
    ]));
});

check('P12 con upload-add cerca solo quel nome', fn () =>
    !ResourceFormLayoutRenderer::hasSubmit($form([new Submit('upload')]), 'upload-add')
    && ResourceFormLayoutRenderer::hasSubmit($form([new Submit('upload-add')]), 'upload-add')
    && !ResourceFormLayoutRenderer::hasSubmit($form([new Submit('upload-add')]))
);

check('P12 non conta dentro una Card condizionale o un Accordion chiuso', fn () =>
    !ResourceFormLayoutRenderer::hasSubmit($form([
        (new Card)->visibleWhen('has_variants', 'true')->components([new Submit('upload')]),
    ]))
    && !ResourceFormLayoutRenderer::hasSubmit($form([
        (new Container)->hiddenWhen('has_variants', 'false')->components([new Submit('upload')]),
    ]))
    && !ResourceFormLayoutRenderer::hasSubmit($form([
        (new Accordion('Altro'))->components([new Submit('upload')]),
        (new Accordion('Chiuso'))->expanded(false)->components([new Submit('upload')]),
    ]))
);

check('P13 Card con visibleWhen: output identico, attributes() non cambia', fn () =>
    ResourceFormLayoutRenderer::render($form([
        (new Card)->columns(12)->columnSpan(12)
            ->visibleWhen('has_variants', 'true')
            ->attr('data-note', null)
            ->attr('class', 'ignorata')
            ->components(['<p>Varianti</p>']),
    ])->columns(12)) === $tag
        .'<div class="col-12" data-visible-when="has_variants" data-visible-when-values="true" data-wi-conditional-container="true" data-note="">'
        .'<div class="card border"><div class="card-body row g-3"><p>Varianti</p></div></div></div></form>'
);

summary();
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/Backend/Support/ResourceFormLayoutRendererTest.php`
Expected: FAIL con `8 test, 6 falliti` (exit 1). Passano già P9 e P13 (il tag e l'helper `attributes()` di oggi); P10 e P11 falliscono sul confronto, le quattro righe P12 con `Call to undefined method Wonder\Backend\Support\ResourceFormLayoutRenderer::hasSubmit()`.

- [ ] **Step 3: Scrivi l'implementazione minima**

In `class/Backend/Support/ResourceFormLayoutRenderer.php` sostituisci:

```php
namespace Wonder\Backend\Support;

use Wonder\Elements\Components\AbstractValueCard;
```

con:

```php
namespace Wonder\Backend\Support;

use Wonder\App\Support\AttributeString;
use Wonder\Elements\Components\AbstractValueCard;
```

In `class/Backend/Support/ResourceFormLayoutRenderer.php` sostituisci:

```php
use Wonder\Elements\Component as ElementComponent;
use Wonder\Elements\Form\Form;
```

con:

```php
use Wonder\Elements\Component as ElementComponent;
use Wonder\Elements\Form\Components\Submit;
use Wonder\Elements\Form\Form;
```

In `class/Backend/Support/ResourceFormLayoutRenderer.php` sostituisci:

```php
        $footer = (string) ($options['footer'] ?? '');
```

con:

```php
        $footer = (string) ($options['footer'] ?? '');
        $attributes = AttributeString::render(
            (array) ($options['attributes'] ?? []),
            ['id', 'method', 'enctype', 'action', 'onsubmit', 'class']
        );
```

In `class/Backend/Support/ResourceFormLayoutRenderer.php` sostituisci:

```php
        $html .= ' class="'.self::rowClass($form).'"';
        $html .= '>';
```

con:

```php
        $html .= ' class="'.self::rowClass($form).'"';
        $html .= $attributes === '' ? '' : ' '.$attributes;
        $html .= '>';
```

In `class/Backend/Support/ResourceFormLayoutRenderer.php` sostituisci:

```php
        $html .= '</form>';

        return $html;
    }
```

con:

```php
        $html .= '</form>';

        return $html;
    }

    /**
     * `true` se il layout ha già un Submit `$name` raggiungibile: entra in
     * Container, Card e Accordion aperti, non in Modal e QuickCreate, e salta
     * i componenti con `visibleWhen()`/`hiddenWhen()`.
     */
    public static function hasSubmit(Form $form, string $name = 'upload'): bool
    {
        $components = array_values((array) ($form->components ?? []));

        while ($components !== []) {
            $component = array_shift($components);

            if (!$component instanceof ElementComponent || self::splitVisibility($component)[0] !== '') {
                continue;
            }

            if ($component instanceof Submit && $component->name === $name) {
                return true;
            }

            if ($component instanceof Container || $component instanceof Card
                || ($component instanceof Accordion && $component->getSchema('expanded') === true)) {
                array_push($components, ...array_values((array) ($component->components ?? [])));
            }
        }

        return false;
    }
```

- [ ] **Step 4: Esegui il test e verifica che passi**

Run: `php tests/Backend/Support/ResourceFormLayoutRendererTest.php && php -l class/Backend/Support/ResourceFormLayoutRenderer.php`
Expected: PASS con `8 test, 0 falliti` e `No syntax errors detected in class/Backend/Support/ResourceFormLayoutRenderer.php`.

Run (regressione P13 sui test esistenti che passano dal renderer): `for f in tests/Themes/CardVisibilityTest.php tests/Themes/AccordionGridTest.php tests/Themes/HiddenFieldLayoutTest.php tests/Themes/InputButtonTest.php tests/Themes/ModalTest.php tests/Themes/QuickCreateButtonTest.php tests/Elements/Media/GalleryTest.php; do printf '%s: ' "$f"; php "$f" | tail -1; done`
Expected: PASS, ogni riga termina con `0 falliti` (`GalleryTest.php` stampa `PASS`): `6 test`, `12 test`, `3 test`, `13 test`, `12 test`, `24 test`.

- [ ] **Step 5: Commit**

```bash
git add -f tests/Backend/Support/ResourceFormLayoutRendererTest.php
git add class/Backend/Support/ResourceFormLayoutRenderer.php
git commit -m "Renderer: opzione attributes sul tag form e hasSubmit()" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

### Task P3: attributi della barra su `resource/form.php` e `scheduler/form.php`

Repo: **app**.

**Files:**
- Modify: `app/view/pages/backend/resource/form.php` (riga 9 `$locked`, ramo layout righe 34-42, ramo legacy riga 50)
- Modify: `app/view/pages/backend/scheduler/form.php` (righe 3-9)
- Test: nessun file nuovo. Le viste richiedono il runtime: le coprono lo snapshot I1 e i casi I2-I6 della Parte B/R (Q7). Qui si controllano `php -l` e il cablaggio con `grep`.

**Interfaces:**
- Consumes: opzione `attributes` e `ResourceFormLayoutRenderer::hasSubmit()` (Task P2); `AttributeString::render()` (Task P1); le variabili di vista `$READONLY`, `$READONLY_EDITABLE`, `$FORM_ERRORS`, `$FORM_LAYOUT` che `ResourcePagePresenter` passa già
- Produces: `data-wi-save-bar` su ogni form Resource non `$locked` (ramo layout e ramo legacy), `data-wi-save-bar-dirty` quando la vista ridisegna un POST fallito (`FORM_ERRORS`); il footer non aggiunge il Salva di default se il layout ha già un `Submit` `upload` raggiungibile (5.2); scheduler con `onsubmit` allineato alla condizione dell'attributo (Q18)

- [ ] **Step 1: Verifica lo stato di partenza**

Run: `grep -cF '$saveBarAttributes' app/view/pages/backend/resource/form.php; grep -cF 'hasSubmit($FORM_LAYOUT)' app/view/pages/backend/resource/form.php; grep -cF '$saveBar' app/view/pages/backend/scheduler/form.php`
Expected: FAIL con tre righe `0` (nessun cablaggio della barra nelle due viste).

- [ ] **Step 2: Modifica `resource/form.php`**

In `app/view/pages/backend/resource/form.php` sostituisci:

```php
    $locked = $readonly && !$partial;
```

con:

```php
    $locked = $readonly && !$partial;
    $saveBarAttributes = ['data-wi-save-bar' => !$locked, 'data-wi-save-bar-dirty' => !$locked && !empty($FORM_ERRORS)];
```

In `app/view/pages/backend/resource/form.php` sostituisci:

```php
                'action' => $locked ? '' : (string) ($FORM_ACTION ?? ''),
                'footer' => $locked
                    ? $noticeHtml
                    : ($partial ? $noticeHtml : '').'
                    <div class="col-12">
                        <wi-card class="col-12">
                            <div class="col-12">'.$submitHtml().'</div>
                        </wi-card>
                    </div>',
```

con:

```php
                'action' => $locked ? '' : (string) ($FORM_ACTION ?? ''),
                'attributes' => $saveBarAttributes,
                'footer' => $locked
                    ? $noticeHtml
                    : ($partial ? $noticeHtml : '').(\Wonder\Backend\Support\ResourceFormLayoutRenderer::hasSubmit($FORM_LAYOUT) ? '' : '
                    <div class="col-12">
                        <wi-card class="col-12">
                            <div class="col-12">'.$submitHtml().'</div>
                        </wi-card>
                    </div>'),
```

In `app/view/pages/backend/resource/form.php` sostituisci:

```php
      <?=$locked ? 'onsubmit="return false"' : 'onsubmit="loadingSpinner()"'?>>
```

con:

```php
      <?=$locked ? 'onsubmit="return false"' : 'onsubmit="loadingSpinner()"'?>
      <?=\Wonder\App\Support\AttributeString::render($saveBarAttributes)?>>
```

- [ ] **Step 3: Modifica `scheduler/form.php`**

La condizione `$saveBar` equivale a `!$locked` di `resource/form.php` e comanda sia gli attributi sia l'`onsubmit` (Q18): senza, con `READONLY` più `READONLY_EDITABLE` il salvataggio sarebbe sempre annullato e l'isola resterebbe su "non salvato".

In `app/view/pages/backend/scheduler/form.php` sostituisci:

```php
\Wonder\View\View::layout('backend.form');
echo \Wonder\Backend\Support\ResourceFormLayoutRenderer::render($FORM_LAYOUT, [
    'id' => 'resource-layout-form',
    'method' => $FORM_METHOD ?? 'POST',
    'action' => $FORM_ACTION ?? '',
    'onsubmit' => !empty($READONLY) ? 'return false' : 'loadingSpinner()',
]);
```

con:

```php
\Wonder\View\View::layout('backend.form');
$saveBar = empty($READONLY) || !empty($READONLY_EDITABLE);
echo \Wonder\Backend\Support\ResourceFormLayoutRenderer::render($FORM_LAYOUT, [
    'id' => 'resource-layout-form',
    'method' => $FORM_METHOD ?? 'POST',
    'action' => $FORM_ACTION ?? '',
    'onsubmit' => $saveBar ? 'loadingSpinner()' : 'return false',
    'attributes' => ['data-wi-save-bar' => $saveBar, 'data-wi-save-bar-dirty' => $saveBar && !empty($FORM_ERRORS)],
]);
```

- [ ] **Step 4: Verifica cablaggio e sintassi**

Run: `grep -cF '$saveBarAttributes' app/view/pages/backend/resource/form.php; grep -cF 'hasSubmit($FORM_LAYOUT)' app/view/pages/backend/resource/form.php; grep -cF '$saveBar' app/view/pages/backend/scheduler/form.php; php -l app/view/pages/backend/resource/form.php; php -l app/view/pages/backend/scheduler/form.php`
Expected: PASS con `3`, `1`, `3`, poi `No syntax errors detected in app/view/pages/backend/resource/form.php` e `No syntax errors detected in app/view/pages/backend/scheduler/form.php`.

Il markup atteso, verificato in sandbox rendendo la vista con un `Form` finto, e quello che I1-I6 confrontano nel browser:
- ramo layout: `... onsubmit="loadingSpinner()" class="row g-3" data-wi-save-bar>`; con `FORM_ERRORS` anche ` data-wi-save-bar-dirty`; sola lettura parziale: attributo presente; `$locked`: nessun attributo e nessun Salva;
- layout con `(new Card)->components([new Submit('upload')])`: nessun Salva nel footer; con la Card sotto `visibleWhen()`: il Salva del footer resta;
- ramo legacy: `onsubmit="loadingSpinner()"` seguito da `data-wi-save-bar>` (con `-dirty` dopo un POST fallito); `$locked`: `onsubmit="return false"` e nessun attributo.

- [ ] **Step 5: Commit**

```bash
git add app/view/pages/backend/resource/form.php app/view/pages/backend/scheduler/form.php
git commit -m "Form Resource e scheduler: attributi della barra di salvataggio" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

### Task P4: campo `written` di `user()` e gestione utenti

Repo: **app**.

**Files:**
- Modify: `app/function/user/user.php` (righe 256, 398, 573)
- Modify: `class/Backend/Support/UserManagementPageController.php` (righe 70, 73, 93)
- Modify: `app/view/pages/backend/user/manage.php` (riga 19)
- Test: `tests/Backend/Support/UserManagementPageControllerTest.php` (nuovo, P15)

**Interfaces:**
- Consumes: nulla dai task precedenti
- Produces: campo `written` nel ritorno di `user()` (`false` di default, `true` subito dopo `sqlInsert('user')` e `sqlModify('user')`); `UserManagementPageController::render(string $mode, ?int $id = null, ?array $values = null, bool $dirty = false)` (privato); variabile di vista `SAVE_BAR_DIRTY`; `data-wi-save-bar` e, se sporco, `data-wi-save-bar-dirty` sul form di `user/manage.php`. `written` serve anche all'account (Task P5).

Il segnale "sporco" è "la scrittura non è avvenuta", non il codice ALERT: `user()` a volte scrive e poi imposta un avviso (hook, consensi 900, mail 908) e a volte fallisce senza un codice riconoscibile (upload 920/923, `functionValidate`). `written` richiede il DB: si verifica in I7, I8 e I18; qui il test copre la firma (P15).

- [ ] **Step 1: Scrivi il test che fallisce**

Crea `tests/Backend/Support/UserManagementPageControllerTest.php`:

```php
<?php
/** php tests/Backend/Support/UserManagementPageControllerTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Support\UserManagementPageController;

// Gli attributi del form si verificano sul sito (I8): la pagina richiede il runtime del backend e, per gli admin, il DB.
$render = new ReflectionMethod(UserManagementPageController::class, 'render');
$signature = array_map(
    static fn (ReflectionParameter $parameter): string => (string) $parameter->getType().' $'.$parameter->getName()
        .($parameter->isOptional() ? ' = '.var_export($parameter->getDefaultValue(), true) : ''),
    $render->getParameters()
);

check('P15 i parametri di oggi non cambiano', fn () =>
    array_slice($signature, 0, 3) === ['string $mode', '?int $id = NULL', '?array $values = NULL']
);

check('P15 bool $dirty = false e l\'ultimo, facoltativo', fn () =>
    count($signature) === 4 && $signature[3] === 'bool $dirty = false'
);

summary();
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/Backend/Support/UserManagementPageControllerTest.php`
Expected: FAIL con `2 test, 1 falliti` (exit 1): passa `P15 i parametri di oggi non cambiano`, fallisce `P15 bool $dirty = false e l'ultimo, facoltativo`.

- [ ] **Step 3: Aggiungi `written` a `user()`**

In `app/function/user/user.php` sostituisci:

```php
            'already_registered' => false,
        ];
```

con:

```php
            'already_registered' => false,
            'written' => false,
        ];
```

In `app/function/user/user.php` sostituisci:

```php
                    $sql = sqlInsert('user', $UPLOAD);
```

con:

```php
                    $sql = sqlInsert('user', $UPLOAD);
                    $RETURN->written = true;
```

In `app/function/user/user.php` sostituisci:

```php
                sqlModify('user', $UPLOAD, 'id', $MODIFY_ID);
```

con:

```php
                sqlModify('user', $UPLOAD, 'id', $MODIFY_ID);
                $RETURN->written = true;
```

- [ ] **Step 4: Passa lo stato al form della gestione utenti**

In `class/Backend/Support/UserManagementPageController.php` sostituisci:

```php
        $this->render($mode, $id, $values);
    }

    private function render(string $mode, ?int $id = null, ?array $values = null): void
```

con:

```php
        $this->render($mode, $id, $values, empty($upload->written));
    }

    private function render(string $mode, ?int $id = null, ?array $values = null, bool $dirty = false): void
```

In `class/Backend/Support/UserManagementPageController.php` sostituisci:

```php
            'VALUES' => $values,
```

con:

```php
            'VALUES' => $values,
            'SAVE_BAR_DIRTY' => $dirty,
```

In `app/view/pages/backend/user/manage.php` sostituisci:

```php
<form class="col-12" action="<?=htmlspecialchars($FORM_ACTION, ENT_QUOTES, 'UTF-8')?>" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
```

con:

```php
<form class="col-12" action="<?=htmlspecialchars($FORM_ACTION, ENT_QUOTES, 'UTF-8')?>" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()" data-wi-save-bar<?=!empty($SAVE_BAR_DIRTY) ? ' data-wi-save-bar-dirty' : ''?>>
```

L'isola copia "Salva e aggiungi" (`upload-add`) e "Salva" (`upload`) in ordine di pagina; non serve altro.

- [ ] **Step 5: Esegui il test e verifica che passi**

Run: `php tests/Backend/Support/UserManagementPageControllerTest.php && php -l app/function/user/user.php && php -l class/Backend/Support/UserManagementPageController.php && php -l app/view/pages/backend/user/manage.php && grep -c "written" app/function/user/user.php`
Expected: PASS con `2 test, 0 falliti`, tre `No syntax errors detected in ...` e `3`.

- [ ] **Step 6: Commit**

```bash
git add -f tests/Backend/Support/UserManagementPageControllerTest.php
git add app/function/user/user.php class/Backend/Support/UserManagementPageController.php app/view/pages/backend/user/manage.php
git commit -m "Gestione utenti: barra di salvataggio e campo written di user()" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

### Task P5: account e file di configurazione

Repo: **app**.

**Files:**
- Modify: `class/App/PageSchema/AccountPageSchema.php` (riga 35, in `profileFormSchema()`)
- Modify: `app/http/backend/account/index.php` (dopo la riga 64)
- Modify: `app/view/pages/backend/account/index.php` (riga 26)
- Modify: `app/view/pages/backend/config/configuration-file.php` (riga 15)
- Test: `tests/App/PageSchema/AccountPageSchemaTest.php` (nuovo)

**Interfaces:**
- Consumes: campo `written` di `user()` (Task P4); la route POST di `/backend/account/`, già su main (`app/config/routes/route.backend.php:82`, commit 559d6d02), controllata nel Task P11
- Produces: `data-wi-save-bar-ignore` sulla password di conferma del profilo; variabile di vista `PROFILE_DIRTY`; `data-wi-save-bar` (e `-dirty` quando serve) sul form profilo dell'account e sul form di `configuration-file`. Il form "Modifica password" dell'account resta senza attributo.

`PROFILE_DIRTY` è vero solo se il profilo è stato inviato, `user()` è stato chiamato e non ha scritto. Con la password di conferma errata (905) il ramo `else` imposta solo `$ALERT` e lascia `$UPLOAD` non impostato: la pagina mostra i valori del DB e il form è pulito. La password di conferma si ignora perché Chrome la compila dopo il primo gesto; è l'unico uso ammesso di `data-wi-save-bar-ignore` (5.5).

- [ ] **Step 1: Scrivi il test che fallisce**

Crea `tests/App/PageSchema/AccountPageSchemaTest.php`:

```php
<?php
/** php tests/App/PageSchema/AccountPageSchemaTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\PageSchema\AccountPageSchema;

$ignored = static fn (array $schema): array => array_keys(array_filter(
    $schema,
    static fn ($field): bool => str_contains($field->render('bootstrap'), ' data-wi-save-bar-ignore')
));

check('Solo la password di conferma del profilo e ignorata dalla barra', fn () =>
    $ignored(AccountPageSchema::profileFormSchema([])) === ['password']
);

check('Form password, login, ripristino e nuova password non hanno campi ignorati', fn () =>
    $ignored(AccountPageSchema::passwordFormSchema()) === []
    && $ignored(AccountPageSchema::loginFormSchema()) === []
    && $ignored(AccountPageSchema::restoreFormSchema()) === []
    && $ignored(AccountPageSchema::setPasswordFormSchema()) === []
);

summary();
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/App/PageSchema/AccountPageSchemaTest.php`
Expected: FAIL con `2 test, 1 falliti` (exit 1): fallisce `Solo la password di conferma del profilo e ignorata dalla barra`.

- [ ] **Step 3: Ignora la password di conferma**

In `class/App/PageSchema/AccountPageSchema.php` sostituisci (in `profileFormSchema()`; la stessa riga della password torna nei form di login, ripristino e impostazione della password, che non si toccano):

```php
            'email' => FormField::key('email')->email()->required(),
            'password' => FormField::key('password')->password()->required(),
```

con:

```php
            'email' => FormField::key('email')->email()->required(),
            'password' => FormField::key('password')->password()->required()->attribute('data-wi-save-bar-ignore'),
```

- [ ] **Step 4: Esegui il test e verifica che passi**

Run: `php tests/App/PageSchema/AccountPageSchemaTest.php`
Expected: PASS con `2 test, 0 falliti`.

- [ ] **Step 5: Attributi sui form dell'account e di configuration-file**

Run (stato di partenza): `grep -cF "'PROFILE_DIRTY'" app/http/backend/account/index.php; grep -cF 'data-wi-save-bar' app/view/pages/backend/account/index.php; grep -cF 'data-wi-save-bar' app/view/pages/backend/config/configuration-file.php`
Expected: FAIL con tre righe `0`.

In `app/http/backend/account/index.php` sostituisci:

```php
    'VALUES' => $VALUES,
```

con:

```php
    'VALUES' => $VALUES,
    'PROFILE_DIRTY' => isset($_POST['modify']) && isset($UPLOAD) && empty($UPLOAD->written),
```

In `app/view/pages/backend/account/index.php` sostituisci:

```php
    <form class="col-9" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
```

con:

```php
    <form class="col-9" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()" data-wi-save-bar<?=!empty($PROFILE_DIRTY) ? ' data-wi-save-bar-dirty' : ''?>>
```

In `app/view/pages/backend/config/configuration-file.php` sostituisci:

```php
<form action="<?=htmlspecialchars(__r('backend.config.configuration-file'), ENT_QUOTES, 'UTF-8')?>" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
```

con:

```php
<form action="<?=htmlspecialchars(__r('backend.config.configuration-file'), ENT_QUOTES, 'UTF-8')?>" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()" data-wi-save-bar<?=!empty($ERRORS) ? ' data-wi-save-bar-dirty' : ''?>>
```

`configuration-file` fa redirect quando il salvataggio riesce; `ERRORS` non vuoto vuol dire che la vista ridisegna i valori inviati e non salvati.

- [ ] **Step 6: Verifica cablaggio e sintassi**

Run: `grep -cF "'PROFILE_DIRTY'" app/http/backend/account/index.php; grep -cF 'data-wi-save-bar' app/view/pages/backend/account/index.php; grep -cF 'data-wi-save-bar' app/view/pages/backend/config/configuration-file.php; for f in class/App/PageSchema/AccountPageSchema.php app/http/backend/account/index.php app/view/pages/backend/account/index.php app/view/pages/backend/config/configuration-file.php; do php -l "$f"; done`
Expected: PASS con `1`, `1`, `1` e quattro `No syntax errors detected in ...`. Il comportamento (905 pulito, 920 sporco, `confirmOther` dal form password, configuration-file con errore sporco) si prova in I7 e I9.

- [ ] **Step 7: Commit**

```bash
git add -f tests/App/PageSchema/AccountPageSchemaTest.php
git add class/App/PageSchema/AccountPageSchema.php app/http/backend/account/index.php app/view/pages/backend/account/index.php app/view/pages/backend/config/configuration-file.php
git commit -m "Account e file di configurazione: barra di salvataggio" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```


### Task P6: `autocomplete="new-password"` sui segreti di `SecurityResource`

Repo: **app**.

**Files:**
- Modify: `class/App/Resources/Config/SecurityResource.php` (righe 83, 89, 92, 96, 98)
- Test: `tests/App/Resources/SecurityResourceTest.php` (nuovo, P16)

**Interfaces:**
- Consumes: nulla dai task precedenti; `Input::autocomplete()` esiste già
- Produces: `autocomplete="new-password"` su `klaviyo_api_key`, `brevo_api_key`, `mail_password` (resi nel layout) e su `google_oauth_client_secret`, `apple_oauth_private_key` (commentati nel layout, righe 167 e 173). `InputPassword` senza opzioni non cambia.

Senza `new-password` Chrome può inserire la password di login nei segreti: il form risulterebbe modificato e si rischierebbe di salvare la password sbagliata. Si usa `->autocomplete()`, non `->attribute('autocomplete', ...)`; niente `data-wi-save-bar-ignore`, perché sono valori veri (5.5). I due campi commentati si controllano dallo schema con `SecurityResource::getInput()`, che li costruisce anche se il layout non li mostra; è più preciso del testo del file e non richiede il DB.

- [ ] **Step 1: Scrivi il test che fallisce**

Crea `tests/App/Resources/SecurityResourceTest.php`:

```php
<?php
/** php tests/App/Resources/SecurityResourceTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\Resources\Config\SecurityResource;

// formSchema() legge i servizi mail: senza runtime basta un elenco vuoto.
if (!function_exists('mailService')) {
    function mailService(): array
    {
        return [];
    }
}

$normalize = static fn (string $html): string => (string) preg_replace('/field_[a-z]{10}/', 'field_X', $html);

check('P16 InputPassword senza opzioni: render di oggi, senza new-password', fn () =>
    $normalize(FormField::key('pwd')->password()->render('bootstrap'))
        === '<div><div class="form-floating"><input class="form-control" type="password" name="pwd" id="field_X" value="" data-wi-check="true" placeholder="" /><label for="field_X">Pwd</label></div><div class="invalid-feedback"></div></div>'
);

check('P16 i tre segreti del layout si rendono con autocomplete="new-password"', function () {
    foreach (['klaviyo_api_key', 'brevo_api_key', 'mail_password'] as $key) {
        if (!str_contains(SecurityResource::getInput($key)->render('bootstrap'), ' autocomplete="new-password"')) {
            return false;
        }
    }

    return true;
});

// Nel layout sono commentati, ma formSchema() li dichiara comunque.
check('P16 google_oauth_client_secret e apple_oauth_private_key dichiarano new-password', function () {
    foreach (['google_oauth_client_secret', 'apple_oauth_private_key'] as $key) {
        if (!str_contains(SecurityResource::getInput($key)->render('bootstrap'), ' autocomplete="new-password"')) {
            return false;
        }
    }

    return true;
});

summary();
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/App/Resources/SecurityResourceTest.php`
Expected: FAIL con `3 test, 2 falliti` (exit 1): passa `P16 InputPassword senza opzioni: render di oggi, senza new-password`, falliscono i due controlli su `new-password`.

- [ ] **Step 3: Dichiara `new-password` sui cinque segreti**

In `class/App/Resources/Config/SecurityResource.php` sostituisci:

```php
            FormField::key('google_oauth_client_secret')->password(),
            FormField::key('google_oauth_redirect_uri')->text(),
            FormField::key('apple_oauth_client_id')->text(),
            FormField::key('apple_oauth_team_id')->text(),
            FormField::key('apple_oauth_key_id')->text(),
            FormField::key('apple_oauth_redirect_uri')->text(),
            FormField::key('apple_oauth_private_key')->password(),

            FormField::key('mail_service')->select(static::mailServiceOptions())->required(),
            FormField::key('brevo_api_key')->password(),
            FormField::key('mail_host')->text(),
            FormField::key('mail_port')->text(),
            FormField::key('mail_username')->text(),
            FormField::key('mail_password')->password(),

            FormField::key('klaviyo_api_key')->password(),
```

con:

```php
            FormField::key('google_oauth_client_secret')->password()->autocomplete('new-password'),
            FormField::key('google_oauth_redirect_uri')->text(),
            FormField::key('apple_oauth_client_id')->text(),
            FormField::key('apple_oauth_team_id')->text(),
            FormField::key('apple_oauth_key_id')->text(),
            FormField::key('apple_oauth_redirect_uri')->text(),
            FormField::key('apple_oauth_private_key')->password()->autocomplete('new-password'),

            FormField::key('mail_service')->select(static::mailServiceOptions())->required(),
            FormField::key('brevo_api_key')->password()->autocomplete('new-password'),
            FormField::key('mail_host')->text(),
            FormField::key('mail_port')->text(),
            FormField::key('mail_username')->text(),
            FormField::key('mail_password')->password()->autocomplete('new-password'),

            FormField::key('klaviyo_api_key')->password()->autocomplete('new-password'),
```

- [ ] **Step 4: Esegui il test e verifica che passi**

Run: `php tests/App/Resources/SecurityResourceTest.php && php -l class/App/Resources/Config/SecurityResource.php`
Expected: PASS con `3 test, 0 falliti` e `No syntax errors detected in class/App/Resources/Config/SecurityResource.php`. Il render nella pagina vera (form pulito all'apertura) si prova in I10.

- [ ] **Step 5: Commit**

```bash
git add -f tests/App/Resources/SecurityResourceTest.php
git add class/App/Resources/Config/SecurityResource.php
git commit -m "SecurityResource: autocomplete new-password sui segreti" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

### Task P7: Repeater, `change` allo svuotamento dell'ultima riga

Repo: **app**.

**Files:**
- Modify: `class/Themes/Bootstrap/Form/Components/Repeater.php` (righe 754-758 in `wiRepeaterRemoveRow`, righe 1115-1121 in `wiRepeaterSetFieldValue`)
- Modify: `tests/Themes/RepeaterAutonumericTest.php` (righe 161-165, solo il DOM finto)
- Test: `tests/Themes/RepeaterRemoveRowTest.php` (nuovo, P14)

**Interfaces:**
- Consumes: nulla dai task precedenti
- Produces: svuotando l'ultima riga visibile, checkbox e radio ricevono `checked = false` e un `change` con `bubbles: true`; gli altri campi passano da `window.wiRepeaterSetFieldValue(input, '')` (che emette `input` e `change` con `bubbles: true`, AutoNumeric compreso). Il ramo AutoNumeric di `wiRepeaterSetFieldValue` emette `change` con `bubbles: true` dopo `clear()` o `set()`. La barra (Parte C) e `check()` sui required lo ascoltano; I11 lo prova nel browser.

Aggiunta, rimozione e spostamento delle righe restano coperti dal MutationObserver della lib: nessun evento nuovo quando una riga si toglie davvero. `wiRepeaterRemoveRow` non aveva test; quello nuovo estrae le funzioni dal render e le esegue in node con un DOM finto, e senza node controlla il testo.

- [ ] **Step 1: Scrivi il test che fallisce**

Crea `tests/Themes/RepeaterRemoveRowTest.php`:

```php
<?php
/** php tests/Themes/RepeaterRemoveRowTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/**
 * Svuotare l'ultima riga deve farsi sentire: la barra di salvataggio e check()
 * ascoltano `change`. I pezzi di JS girano in node con un DOM finto quando
 * node c'e; altrimenti resta la prova sul testo.
 */
$field = new class {
    public array $schema = [];
};

$field->schema = [
    'id' => 'products',
    'name' => 'products',
    'label' => 'Prodotti',
    'value' => ['row_1' => ['price' => '12.5']],
    'columns' => [RepeaterColumn::key('price')->price()->label('Prezzo')->columnSpan(3)],
    'context' => ['nested' => true],
];

$html = (new Repeater)->render($field);
$js = substr($html, (int) strpos($html, '<script'));

// Il sorgente di `window.<nome> = window.<nome> || function (...) {...};`.
$extract = static function (string $name) use ($js): string {
    $start = strpos($js, 'window.'.$name.' = window.'.$name.' || function');
    if ($start === false) { throw new RuntimeException("{$name} non trovata"); }

    $depth = 0;

    for ($i = strpos($js, '{', $start), $n = strlen($js); $i < $n; $i++) {
        if ($js[$i] === '{') { $depth++; }
        if ($js[$i] === '}' && --$depth === 0) {
            return substr($js, $start, $i - $start + 1).';';
        }
    }

    throw new RuntimeException("{$name} non chiusa");
};

// Esegue il codice in node e decodifica il JSON stampato; null senza node.
$run = static function (string $code): mixed {
    $candidates = [trim((string) shell_exec('command -v node 2>/dev/null'))];
    foreach (glob((getenv('HOME') ?: '').'/Library/Application Support/Herd/config/nvm/versions/node/*/bin/node') ?: [] as $path) {
        $candidates[] = $path;
    }

    $bin = null;
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_executable($candidate)) { $bin = $candidate; break; }
    }
    if ($bin === null) { return null; }

    $file = tempnam(sys_get_temp_dir(), 'wirep').'.js';
    file_put_contents($file, "globalThis.window = globalThis;\n".$code);
    $out = trim((string) shell_exec(escapeshellarg($bin).' '.escapeshellarg($file).' 2>&1'));
    @unlink($file);

    $data = json_decode($out, true);
    if ($data === null) { throw new RuntimeException('node: '.$out); }

    return $data;
};

// Una riga con checkbox, radio, testo, textarea, select e un prezzo AutoNumeric.
$dom = <<<'JS'
var events = [];
var setCalls = [];
var cleared = 0;
var removed = 0;
window.wiRepeaterConfirmDelete = function (onConfirm) { onConfirm(); };
window.AutoNumeric = { getAutoNumericElement: function (field) { return field.numeric || null; } };
var setFieldValue = window.wiRepeaterSetFieldValue;
window.wiRepeaterSetFieldValue = function (field, value) { setCalls.push(field.name + '=' + value); setFieldValue(field, value); };

function remove(visibleRows) {
  var fields = ['checkbox', 'radio', 'text', 'textarea', 'select-one', 'price'].map(function (type) {
    return {
      name: type,
      type: type === 'price' ? 'text' : type,
      value: 'x',
      checked: true,
      numeric: type === 'price' ? { clear: function () { cleared++; }, set: function () {} } : null,
      dispatchEvent: function (event) { events.push(type + ':' + event.type + ':' + event.bubbles); }
    };
  });
  var container = { dataset: {}, querySelectorAll: function () { return new Array(visibleRows); } };
  var row = {
    parentElement: container,
    querySelector: function () { return null; },
    querySelectorAll: function () { return fields; },
    remove: function () { removed++; }
  };
  window.wiRepeaterRemoveRow({ closest: function () { return row; }, getAttribute: function () { return null; } });

  return fields.map(function (field) { return field.type === 'checkbox' || field.type === 'radio' ? field.checked : field.value; });
}
JS;

check('P14 svuotare l\'ultima riga: change con bubbles su ogni campo, testo via wiRepeaterSetFieldValue', function () use ($extract, $run, $dom) {
    $code = $extract('wiRepeaterNumberFromText').$extract('wiRepeaterSetFieldValue').$extract('wiRepeaterRemoveRow');
    $data = $run($code.$dom.<<<'JS'

var values = remove(1);
console.log(JSON.stringify({ values: values, events: events, setCalls: setCalls, cleared: cleared, removed: removed }));
JS);

    if ($data === null) {
        echo "    (node non trovato: prova sul testo)\n";
        $remove = $extract('wiRepeaterRemoveRow');

        return str_contains($remove, "input.checked = false;\n                        input.dispatchEvent(new Event('change', { bubbles: true }));")
            && str_contains($remove, "window.wiRepeaterSetFieldValue(input, '');")
            && !str_contains($remove, "input.value = '';");
    }

    return $data === [
        'values' => [false, false, '', '', '', 'x'],
        'events' => [
            'checkbox:change:true',
            'radio:change:true',
            'text:input:true', 'text:change:true',
            'textarea:input:true', 'textarea:change:true',
            'select-one:input:true', 'select-one:change:true',
            'price:change:true',
        ],
        'setCalls' => ['text=', 'textarea=', 'select-one=', 'price='],
        'cleared' => 1,
        'removed' => 0,
    ];
});

check('P14 togliere una riga che non e l\'ultima: nessun change in piu', function () use ($extract, $run, $dom) {
    $code = $extract('wiRepeaterNumberFromText').$extract('wiRepeaterSetFieldValue').$extract('wiRepeaterRemoveRow');
    $data = $run($code.$dom.<<<'JS'

var values = remove(2);
console.log(JSON.stringify({ values: values, events: events, setCalls: setCalls, removed: removed }));
JS);

    if ($data === null) { echo "    (node non trovato: prova saltata)\n"; return true; }

    return $data === [
        'values' => [true, true, 'x', 'x', 'x', 'x'],
        'events' => [],
        'setCalls' => [],
        'removed' => 1,
    ];
});

check('P14 il ramo AutoNumeric di wiRepeaterSetFieldValue emette change con bubbles', function () use ($extract, $run) {
    $code = $extract('wiRepeaterNumberFromText').$extract('wiRepeaterSetFieldValue');
    $data = $run($code.<<<'JS'

var events = [];
var numeric = { set: function () {}, clear: function () {} };
var field = { dispatchEvent: function (event) { events.push(event.type + ':' + event.bubbles); } };
window.AutoNumeric = { getAutoNumericElement: function () { return numeric; } };
window.wiRepeaterSetFieldValue(field, '12,50');
window.wiRepeaterSetFieldValue(field, '');
console.log(JSON.stringify(events));
JS);

    if ($data === null) {
        echo "    (node non trovato: prova sul testo)\n";

        return str_contains($extract('wiRepeaterSetFieldValue'), "numeric.set(raw);\n        }\n\n        field.dispatchEvent(new Event('change', { bubbles: true }));");
    }

    return $data === ['change:true', 'change:true'];
});

summary();
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/Themes/RepeaterRemoveRowTest.php`
Expected: FAIL con `3 test, 2 falliti` (exit 1): passa `P14 togliere una riga che non e l'ultima: nessun change in piu`, falliscono `P14 svuotare l'ultima riga: change con bubbles su ogni campo, testo via wiRepeaterSetFieldValue` e `P14 il ramo AutoNumeric di wiRepeaterSetFieldValue emette change con bubbles`.

- [ ] **Step 3: Dai un `dispatchEvent` al campo finto del test AutoNumeric**

Il test esistente passa `{}` come campo: con il `change` del ramo AutoNumeric fallirebbe con `TypeError: field.dispatchEvent is not a function` (`5 test, 1 falliti`). Il campo finto riceve un `dispatchEvent` vuoto; le asserzioni non cambiano.

In `tests/Themes/RepeaterAutonumericTest.php` sostituisci:

```php
var numeric = { set: function (v) { set.push(v); }, clear: function () { cleared++; } };
window.AutoNumeric = { getAutoNumericElement: function () { return numeric; } };
window.wiRepeaterSetFieldValue({}, '1.234,50 €');
window.wiRepeaterSetFieldValue({}, '12 pz');
window.wiRepeaterSetFieldValue({}, '');
```

con:

```php
var numeric = { set: function (v) { set.push(v); }, clear: function () { cleared++; } };
var field = { dispatchEvent: function () {} };
window.AutoNumeric = { getAutoNumericElement: function () { return numeric; } };
window.wiRepeaterSetFieldValue(field, '1.234,50 €');
window.wiRepeaterSetFieldValue(field, '12 pz');
window.wiRepeaterSetFieldValue(field, '');
```

Run: `php tests/Themes/RepeaterAutonumericTest.php`
Expected: PASS con `5 test, 0 falliti` anche prima dell'implementazione.

- [ ] **Step 4: Emetti `change` nello svuotamento e nel ramo AutoNumeric**

In `class/Themes/Bootstrap/Form/Components/Repeater.php` sostituisci:

```php
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = false;
                    } else {
                        input.value = '';
                    }
```

con:

```php
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = false;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    } else {
                        window.wiRepeaterSetFieldValue(input, '');
                    }
```

In `class/Themes/Bootstrap/Form/Components/Repeater.php` sostituisci:

```php
        if (raw === null) {
            numeric.clear();
            return;
        }

        numeric.set(raw);
    };
```

con:

```php
        if (raw === null) {
            numeric.clear();
        } else {
            numeric.set(raw);
        }

        field.dispatchEvent(new Event('change', { bubbles: true }));
    };
```

- [ ] **Step 5: Esegui i test e verifica che passino**

Run: `php tests/Themes/RepeaterRemoveRowTest.php && php tests/Themes/RepeaterAutonumericTest.php && php -l class/Themes/Bootstrap/Form/Components/Repeater.php`
Expected: PASS con `3 test, 0 falliti`, `5 test, 0 falliti` e `No syntax errors detected in class/Themes/Bootstrap/Form/Components/Repeater.php`.

- [ ] **Step 6: Commit**

```bash
git add -f tests/Themes/RepeaterRemoveRowTest.php tests/Themes/RepeaterAutonumericTest.php
git add class/Themes/Bootstrap/Form/Components/Repeater.php
git commit -m "Repeater: change con bubbles allo svuotamento dell'ultima riga" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

### Task P8: `header.php` sottrae `--wi-save-bar-reserve`

Repo: **app**.

**Files:**
- Modify: `app/view/components/backend/layout/header.php` (riga 229)
- Test: nessun test PHP; controllo con `grep` e `php -l`, il comportamento si misura in I14

**Interfaces:**
- Consumes: `--wi-save-bar-reserve` (Parte C, `save-bar.css`), variabile in sola lettura
- Produces: il `min-height` del contenitore di pagina scala dello spazio riservato alla fascia, così le pagine corte non hanno scroll vuoto. Con la lib vecchia la variabile non esiste, vale `0px` e il layout resta com'è, in qualsiasi ordine di rilascio.

Nessun'altra modifica al layout dell'app (5.6). Un sito che sovrascrive tutto `header.php` non sottrae la riserva e ha al massimo 96px di scroll in più: si documenta nel Task P9 e nella skill (Task P10).

- [ ] **Step 1: Verifica lo stato di partenza**

Run: `grep -cF 'var(--wi-save-bar-reserve, 0px)' app/view/components/backend/layout/header.php`
Expected: FAIL con `0` (exit 1).

- [ ] **Step 2: Sottrai la riserva dal `min-height`**

In `app/view/components/backend/layout/header.php` sostituisci:

```php
        <div class="w-100" style="min-height: calc(100vh - (50px + 22.5px + 1rem + 20px));">
```

con:

```php
        <div class="w-100" style="min-height: calc(100vh - (50px + 22.5px + 1rem + 20px) - var(--wi-save-bar-reserve, 0px));">
```

- [ ] **Step 3: Verifica**

Run: `grep -cF 'var(--wi-save-bar-reserve, 0px)' app/view/components/backend/layout/header.php && php -l app/view/components/backend/layout/header.php`
Expected: PASS con `1` e `No syntax errors detected in app/view/components/backend/layout/header.php`.

- [ ] **Step 4: Commit**

```bash
git add app/view/components/backend/layout/header.php
git commit -m "Layout backend: min-height sottrae --wi-save-bar-reserve" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

### Task P9: documentazione dell'app, `AGENTS.md` e CHANGELOG

Repo: **app**.

Spec sez. 6 (Documentazione) e Limiti noti, compreso Q8: niente `autocomplete="off"` e il ricaricamento di Firefox documentato come limite.

**Files:**
- Create: `docs/app/concetti/form/save-bar.md`
- Modify: `docs/app/SUMMARY.md` (dopo la riga 36)
- Modify: `docs/app/concetti/form/README.md` (dopo la riga 69)
- Modify: `docs/app/concetti/componenti/README.md` (dopo la riga 463, nota su `Button::post`)
- Modify: `docs/app/piattaforma/layout.md` (dopo la riga 138, "Override target nel progetto host")
- Modify: `AGENTS.md` (dopo la riga 256, punto nuovo dopo il CheckTree; riga 437, link)
- Modify: `CHANGELOG.md` (in fondo a `### Added`, `### Changed` e `### Fixed` di `## Unreleased`)
- Test: nessun test PHP; controllo dei link con `grep` e `test -f`

**Interfaces:**
- Consumes: i contratti dei Task P1-P8; nomi pubblici della lib (`data-wi-save-bar*`, `window.wiSaveBar`, `wi:save-bar:change`, `--wi-save-bar-reserve`, `setUpSaveBar()`) dalla Parte C; la pagina della lib `docs/javascript/save-bar.md` dalla Parte I
- Produces: la pagina `docs/app/concetti/form/save-bar.md` (Cos'è, Come si dichiara, Stato iniziale sporco, Submit da script e AJAX, Come funziona, Vincoli, Dove si trova nel codice) con i link da `SUMMARY.md`, `concetti/form/README.md`, `concetti/componenti/README.md` e `piattaforma/layout.md`; il punto in `AGENTS.md`; le voci del CHANGELOG. La skill si aggiorna nel Task P10.

Il CHANGELOG dell'app ha una sola `## Unreleased` che il rilascio non taglia: le voci nuove vanno in fondo a ogni sottosezione. `AGENTS.md:437` puntava a `docs/app/elementi/form-system.md`, che non esiste: diventa `docs/app/concetti/form/theme-system.md`.

- [ ] **Step 1: Verifica lo stato di partenza**

Run:

```bash
for f in docs/app/SUMMARY.md docs/app/concetti/form/README.md docs/app/concetti/componenti/README.md docs/app/piattaforma/layout.md; do l=$(grep -o '([^)]*save-bar\.md)' "$f" | tr -d '()'); if [ -n "$l" ] && [ -f "$(dirname "$f")/$l" ]; then echo "ok $f"; else echo "MANCA $f"; fi; done
grep -cF 'docs/app/concetti/form/save-bar.md' AGENTS.md; grep -cF 'docs/app/elementi/form-system.md' AGENTS.md; grep -cF 'data-wi-save-bar' CHANGELOG.md
```

Expected: FAIL con quattro righe `MANCA ...`, poi `0`, `1`, `0`.

- [ ] **Step 2: Scrivi la pagina della barra**

Crea `docs/app/concetti/form/save-bar.md`:

````markdown
---
icon: floppy-disk
---

# Barra di salvataggio

## Cos'è

Nei form del backend, quando i bottoni di salvataggio escono dallo schermo, le
loro copie compaiono in un'isola fissa in basso. Se il form ha modifiche non
salvate, l'isola mostra "Operazione non salvata" e l'uscita dalla pagina chiede
conferma.

Il comportamento, JS e CSS, sta tutto in `wonder-image/lib`. L'app dichiara
solo gli attributi sul `<form>`. Senza JS, o in un browser senza le API che
servono alla lib, restano i bottoni originali.

## Come si dichiara

| Attributo | Dove | Significato |
|---|---|---|
| `data-wi-save-bar` | `<form>` | attiva l'isola per il form |
| `data-wi-save-bar-dirty` | `<form>` | il form nasce "non salvato"; conta la presenza, non il valore |
| `data-wi-save-bar-ignore` | bottone, campo o contenitore | il bottone non si copia; i campi dentro non contano |
| `data-wi-save-bar-hide-when-open` | qualsiasi elemento nel `body` | finché l'elemento esiste l'isola si nasconde (popup di widget terzi) |

### Resource

Le Resource non dichiarano niente: `resource/form.php` mette gli attributi sul
form, sia con `formLayoutSchema()` sia nel ramo legacy. Un form in sola lettura
senza campi modificabili non li riceve; uno in sola lettura parziale sì.

Una vista che usa `ResourceFormLayoutRenderer::render()` in proprio passa gli
attributi con l'opzione `attributes`:

```php
<?php $saveBar = empty($READONLY) || !empty($READONLY_EDITABLE); ?>

<?=\Wonder\Backend\Support\ResourceFormLayoutRenderer::render($FORM_LAYOUT, [
    'action' => (string) ($FORM_ACTION ?? ''),
    'attributes' => ['data-wi-save-bar' => $saveBar, 'data-wi-save-bar-dirty' => $saveBar && !empty($FORM_ERRORS)],
])?>
```

L'opzione passa da `AttributeString::render()`: `true` stampa l'attributo
senza valore, `false` e `null` lo omettono. Le chiavi `id`, `method`,
`enctype`, `action`, `onsubmit` e `class` si ignorano, perché le imposta il
renderer. Un'app più vecchia ignora l'opzione: nessun attributo e nessun
errore.

### Form scritti a mano

L'attributo fisso si scrive letterale, quello condizionale inline:

```php
<form method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()" data-wi-save-bar<?=!empty($ERRORS) ? ' data-wi-save-bar-dirty' : ''?>>
```

Così si attiva anche nei moduli copia-incolla e nei siti legacy, che non
ricevono patch. Il vecchio `preventFormSubmit(form)`, che arma l'avviso di
uscita a ogni apertura e lo toglie all'invio, è superato: con
`data-wi-save-bar` l'avviso parte solo se ci sono modifiche. Si toglie quando
si aggiunge l'attributo.

Restano senza attributo i form che non salvano un record: login, recupero,
ripristino e impostazione della password, "Esegui update" in home, filtri GET,
dashboard dello scheduler ed eliminazione riga di `ScheduleResource`,
`Button::post`, sql-download, demo frontend in `app/build/src/docs/*` e
caricamento massivo dei media.

## Stato iniziale sporco

`data-wi-save-bar-dirty` si mette quando la pagina ridisegna valori che non
sono stati salvati, cioè dopo un POST fallito:

| Pagina | Condizione |
|---|---|
| Resource e scheduler | `!empty($FORM_ERRORS)` |
| Account, form profilo | `$PROFILE_DIRTY`: POST `modify` che non ha scritto |
| Gestione utenti | `$SAVE_BAR_DIRTY`: stessa regola |
| File di configurazione | `!empty($ERRORS)` |

Il segnale è la scrittura mancata, non l'ALERT. `user()` restituisce il campo
`written`: `false` di default, `true` subito dopo l'insert o l'update della
riga `user`. Un ALERT può arrivare anche dopo una scrittura riuscita (hook,
consensi, mail) e un errore può non avere un codice riconoscibile.

Mai un valore vuoto: la lib guarda la presenza, quindi
`data-wi-save-bar-dirty=""` segna il form sporco. L'attributo si legge solo
all'avvio; `reset()` e un invio non annullato azzerano lo stato senza toglierlo
dal DOM.

## Submit da script e AJAX

```js
// Invio da script: form.submit() non genera l'evento submit.
wiSaveBar?.reset(form);
form.submit();

// Salvataggio AJAX: nella callback di successo.
wiSaveBar?.reset(form);
```

Senza `reset()`, un invio da script fa partire l'avviso di uscita. Dopo un
salvataggio AJAX, le modifiche fatte durante la richiesta risultano salvate.

| Membro | Uso |
|---|---|
| `wiSaveBar.changed(elOrForm?)` | un valore è stato scritto senza evento: ricontrolla tutti i form tracciati |
| `wiSaveBar.reset(form)` | lo stato attuale diventa il punto di partenza |
| `wiSaveBar.isDirty(form)` | stato attuale del form |
| `wiSaveBar.absorb(el)` | i valori dentro `el` diventano il punto di partenza, il resto no |
| `wiSaveBar.labels` | `{ group, unsaved, confirmOther }`, sovrascrivibili prima dell'avvio |
| evento `wi:save-bar:change` | sul form, `detail.dirty`, solo al passaggio pulito/sporco |

`absorb(el)` serve solo per scritture non fatte dall'utente, come una
precompilazione AJAX: sul contenitore più stretto che contiene i campi scritti,
mai sul form intero e mai dopo una modifica dell'utente.

## Come funziona

- `setUpPage()` avvia la barra con `setUpSaveBar()`. Una pagina senza form
  tracciati non crea niente.
- La lib fotografa ogni form tracciato con `new FormData(form)`, cioè quello
  che il salvataggio spedirebbe. Il form è sporco se ha `-dirty` o se la foto
  di adesso è diversa da quella di partenza: annullare a mano una modifica lo
  riporta pulito.
- Finché l'utente non tocca la pagina (tasto, clic, tocco, incolla), ogni
  differenza diventa il nuovo punto di partenza: le riscritture dei widget
  all'avvio non contano.
- I campi dentro `.modal` e `[data-wi-save-bar-ignore]` non contano.
- Si copiano gli elementi del form con `type="submit"` o classe `.wi-submit`,
  compresi quelli collegati con `form="id"`, tranne quelli dentro `.modal`,
  `.offcanvas` e `[data-wi-save-bar-ignore]`. La copia chiama `click()`
  sull'originale, che parte con il suo `name`, il suo `onclick` e gli handler
  delegati.
- Un'isola per pagina, legata all'ultimo form tracciato usato. L'etichetta
  segue quel form; l'avviso di uscita scatta se un qualsiasi form tracciato è
  sporco.
- In fondo alla pagina la lib riserva lo spazio `--wi-save-bar-reserve`, che
  `header.php` del backend sottrae dall'altezza minima del contenuto.

La descrizione completa, con stati, fascia e token CSS, è in
`wonder-image/lib`, `docs/javascript/save-bar.md`.

## Vincoli

- **Copie disabilitate come l'originale.** `check()` disabilita Salva finché
  manca un campo obbligatorio, e la copia lo segue.
- **Più form in una pagina.** Inviare un form che lascia la pagina mentre un
  altro form tracciato è sporco chiede conferma (`labels.confirmOther`). Vale
  anche per i form non tracciati, come la password dell'account. Con un
  `Button::post` che ha la sua `confirm()` le domande sono due: è accettato.
- **EditorJS** segnala le modifiche dopo circa 400ms: uscire prima non dà
  l'avviso.
- **Password e segreti.** Una password in un form tracciato che non è la
  credenziale di login dell'utente dichiara `->autocomplete('new-password')`,
  come i segreti di `SecurityResource`; altrimenti il browser ci inserisce la
  password salvata e il form risulta modificato. `data-wi-save-bar-ignore`
  serve solo alla conferma della propria password, nell'account.
  `new-password` non è il default di `password()`: login e recupero usano
  `current-password`.
- **Un Submit `upload` nel layout sostituisce il Salva di default.** Se
  `formLayoutSchema()` contiene già un `Submit` con name `upload`, il footer
  non aggiunge il suo. Lo decide
  `ResourceFormLayoutRenderer::hasSubmit($form, $name = 'upload')`: entra in
  Container, Card e Accordion aperti (`expanded(true)`), non in Modal e
  QuickCreate, e salta i componenti con `visibleWhen()` o `hiddenWhen()`. Un
  Submit in una Card condizionale o in un Accordion chiuso non conta, e il
  Salva di default resta.
- **Niente `autocomplete="off"`.** Firefox, ricaricando la pagina, rimette i
  valori non salvati e l'isola li prende come punto di partenza. È un limite
  accettato.
- **Override di `header.php`.** Un sito che sovrascrive tutto
  `header.php` non sottrae la riserva e ha al massimo 96px di scroll in più.
  Per allinearlo si aggiunge `- var(--wi-save-bar-reserve, 0px)` al
  `min-height` del contenitore della pagina.
- **Override delle viste dei moduli.** Un override identico al pacchetto si
  cancella. Un override personalizzato riceve a mano i due attributi, oppure
  si ripubblica solo quel file, per esempio
  `php forge publish:module immobili pages/backend/immobili/form.php --force`.
  Mai `--force` sull'albero intero: sovrascrive le viste personalizzate.
- **`.offcanvas-{bp}`** non si esclude da solo: sui suoi bottoni serve
  `data-wi-save-bar-ignore`.
- **Aggiornare un sito** alla lib 2.1.2-alpha.17:
  - se `package.json` ha già `wonder-image` in `^2.1.2-alpha.*`, basta
    `composer update`, perché `forge config` esegue `npm install`;
  - se è fuori range (`^2.1.1-alpha.*`, `^2.0.x`, `^2.1.0`), si esegue
    `npm install wonder-image@^2.1.2-alpha.17` e si fa il commit di
    `package.json` e `package-lock.json`, perché la CI usa `npm ci`.

## Dove si trova nel codice

| Elemento | File |
|---|---|
| Isola e stile | `wonder-image/lib`: `src/build/backend/js/form/saveBar.js`, `src/build/backend/css/save-bar.css` |
| Serializzatore degli attributi | `class/App/Support/AttributeString.php` (`render()`) |
| Opzione `attributes` e `hasSubmit()` | `class/Backend/Support/ResourceFormLayoutRenderer.php` |
| Form delle Resource | `app/view/pages/backend/resource/form.php` |
| Scheduler | `app/view/pages/backend/scheduler/form.php` |
| Account | `app/http/backend/account/index.php`, `app/view/pages/backend/account/index.php` |
| Gestione utenti | `class/Backend/Support/UserManagementPageController.php`, `app/view/pages/backend/user/manage.php` |
| File di configurazione | `app/view/pages/backend/config/configuration-file.php` |
| Campo `written` di `user()` | `app/function/user/user.php` |
| Riserva in fondo alla pagina | `app/view/components/backend/layout/header.php` |
````

- [ ] **Step 3: Collega la pagina dall'indice e dalle pagine vicine**

In `docs/app/SUMMARY.md` sostituisci:

```markdown
  * [Creazione rapida da campo FK](concetti/form/quick-create.md)
```

con:

```markdown
  * [Creazione rapida da campo FK](concetti/form/quick-create.md)
  * [Barra di salvataggio](concetti/form/save-bar.md)
```

In `docs/app/concetti/form/README.md` sostituisci:

```markdown
- [Creazione rapida da campo FK](quick-create.md) — un "+" che crea al volo la
  risorsa collegata in un modal e aggiorna il campo.
```

con:

```markdown
- [Creazione rapida da campo FK](quick-create.md) — un "+" che crea al volo la
  risorsa collegata in un modal e aggiorna il campo.
- [Barra di salvataggio](save-bar.md) — i bottoni di salvataggio in un'isola
  fissa in basso e l'avviso prima di uscire con modifiche non salvate.
```

In `docs/app/concetti/componenti/README.md` sostituisci (nota accanto a `Button::post`, sul modello di quella sui Modal):

```markdown
equivalente. Usa `->confirm($message)` per la conferma e `->formAttributes()`
solo per attributi aggiuntivi del form.
```

con:

```markdown
equivalente. Usa `->confirm($message)` per la conferma e `->formAttributes()`
solo per attributi aggiuntivi del form.

Due cose da sapere con la [barra di salvataggio](../form/save-bar.md):

- **Mai dentro il form di una Resource.** Il browser butta via il `<form>`
  annidato e il bottone invia il form della Resource. Mettilo nelle azioni
  della tabella o fuori dal form.
- **Due domande se il form è sporco.** Fuori dal form della Resource,
  `Button::post` chiede prima la sua `confirm()`; se il form della Resource ha
  modifiche non salvate, poi arriva anche la conferma della barra
  (`labels.confirmOther`). È voluto.
```

In `docs/app/piattaforma/layout.md` sostituisci:

```markdown
- `custom/view/components/frontend/layout/*`
- `custom/view/components/backend/layout/*`
```

con:

```markdown
- `custom/view/components/frontend/layout/*`
- `custom/view/components/backend/layout/*`

Il `header.php` del backend sottrae `var(--wi-save-bar-reserve, 0px)` dal
`min-height` del contenitore della pagina: è lo spazio che la
[barra di salvataggio](../concetti/form/save-bar.md) riserva in fondo. Un
override integrale di `header.php` deve fare lo stesso, altrimenti la pagina ha
al massimo 96px di scroll in più.
```

- [ ] **Step 4: Aggiorna `AGENTS.md`**

In `AGENTS.md` sostituisci:

```markdown
  `change` only on real differences. Keep the empty `name[]` hidden input for
  checkbox trees. See `docs/app/concetti/form/form-field.md`.
```

con:

```markdown
  `change` only on real differences. Keep the empty `name[]` hidden input for
  checkbox trees. See `docs/app/concetti/form/form-field.md`.

- Backend save bar: a `<form data-wi-save-bar>` copies its submit buttons into
  a fixed island when they scroll away and warns before leaving with unsaved
  changes; JS and CSS live only in `wonder-image/lib`. `resource/form.php`
  adds the attributes unless `$locked`, through the renderer `attributes`
  option serialized by `AttributeString::render()`; hand-written forms use
  literal attributes. `data-wi-save-bar-dirty` marks a re-rendered failed POST
  and is never printed empty; for `user()` pages the signal is
  `user()->written`, never the ALERT code. `header.php` subtracts
  `--wi-save-bar-reserve`. A layout `Submit` named `upload` replaces the
  footer Save (`ResourceFormLayoutRenderer::hasSubmit()`). Non-login
  passwords in tracked forms use `->autocomplete('new-password')`;
  `data-wi-save-bar-ignore` is only for the account confirmation password.
  Call `wiSaveBar?.reset(form)` before `form.submit()` and after AJAX
  success. See `docs/app/concetti/form/save-bar.md`.
```

In `AGENTS.md` sostituisci:

```markdown
Full docs in `docs/app/elementi/form-system.md`.
```

con:

```markdown
Full docs in `docs/app/concetti/form/theme-system.md`.
```

- [ ] **Step 5: Aggiorna il CHANGELOG**

In `CHANGELOG.md` sostituisci (fine di `### Added`):

```markdown
  (movimento → versione → articolo). Basta anche un descrittore con sole
  `relations`.
```

con:

```markdown
  (movimento → versione → articolo). Basta anche un descrittore con sole
  `relations`.
- Barra di salvataggio nel backend: un `<form data-wi-save-bar>` copia i
  bottoni di salvataggio in un'isola fissa in basso quando escono dallo
  schermo e chiede conferma prima di uscire con modifiche non salvate. JS e
  CSS stanno in `wonder-image/lib` dalla 2.1.2-alpha.17; l'app mette gli
  attributi sui form delle Resource, dello scheduler, dell'account, della
  gestione utenti e del file di configurazione. Con una lib più vecchia gli
  attributi restano inerti.
- `AttributeString::render(array $attributes, array $reserved = [])`
  serializza gli attributi HTML: `true` senza valore, `false` e `null`
  omessi, valori escapati. `ResourceFormLayoutRenderer::render()` accetta
  l'opzione `attributes` per il tag `<form>` e
  `ResourceFormLayoutRenderer::hasSubmit($form, $name = 'upload')` dice se il
  layout contiene già un Submit con quel name.
- `user()` restituisce il campo `written`: `true` solo dopo l'insert o
  l'update della riga `user`.
```

In `CHANGELOG.md` sostituisci (fine di `### Changed`):

```markdown
- La pagina "Errori" (`ErrorReportResource`) sta in Dev → Log e diagnostica,
  sempre solo per `admin`: prima era in Set Up.
```

con:

```markdown
- La pagina "Errori" (`ErrorReportResource`) sta in Dev → Log e diagnostica,
  sempre solo per `admin`: prima era in Set Up.
- `header.php` del backend sottrae `var(--wi-save-bar-reserve, 0px)` dal
  `min-height` del contenitore della pagina, così le pagine corte non hanno
  scroll vuoto sopra la barra di salvataggio. Con una lib senza barra la
  variabile vale 0 e l'altezza resta com'era.
```

In `CHANGELOG.md` sostituisci (fine di `### Fixed`, ultime righe del file):

```markdown
  contenuto di qualunque di questi quattro tag, anche prima di quanto direbbe
  HTML5, e quello che segue resta come testo escapato.
```

con:

```markdown
  contenuto di qualunque di questi quattro tag, anche prima di quanto direbbe
  HTML5, e quello che segue resta come testo escapato.
- Form con `formLayoutSchema()`: un `Submit` con name `upload` nel layout
  prende il posto del Salva del footer, prima i Salva erano due. Un Submit
  dentro Modal, QuickCreate, Accordion chiusi o componenti con `visibleWhen()`
  o `hiddenWhen()` non conta, e il Salva del footer resta.
- Repeater: svuotare l'ultima riga emette `change` con `bubbles` su ogni campo
  e passa i campi non checkbox o radio da `wiRepeaterSetFieldValue()`, quindi
  AutoNumeric si svuota dalla sua API e `check()` vede lo svuotamento. Prima
  si assegnava solo `value`, senza eventi.
- `SecurityResource`: i segreti (`klaviyo_api_key`, `brevo_api_key`,
  `mail_password`, `google_oauth_client_secret`, `apple_oauth_private_key`)
  hanno `autocomplete="new-password"`: prima il browser poteva inserirci la
  password di login salvata.
```

- [ ] **Step 6: Verifica link e riferimenti**

Run:

```bash
for f in docs/app/SUMMARY.md docs/app/concetti/form/README.md docs/app/concetti/componenti/README.md docs/app/piattaforma/layout.md; do l=$(grep -o '([^)]*save-bar\.md)' "$f" | tr -d '()'); if [ -n "$l" ] && [ -f "$(dirname "$f")/$l" ]; then echo "ok $f"; else echo "MANCA $f"; fi; done
grep -cF 'docs/app/concetti/form/save-bar.md' AGENTS.md; grep -cF 'docs/app/elementi/form-system.md' AGENTS.md; grep -cF 'data-wi-save-bar' CHANGELOG.md; test -f docs/app/concetti/form/theme-system.md && echo theme-system-ok
```

Expected: PASS con quattro righe `ok ...`, poi `1`, `0`, `1` e `theme-system-ok`. Rileggi a mano la pagina nuova contro i nomi condivisi: `AttributeString::render()`, opzione `attributes`, `hasSubmit()`, `data-wi-save-bar`, `-dirty`, `-ignore`, `-hide-when-open`, `--wi-save-bar-reserve`, `window.wiSaveBar` (`changed`, `reset`, `isDirty`, `absorb`, `labels`), `wi:save-bar:change`, `setUpSaveBar()`.

- [ ] **Step 7: Commit**

```bash
git add docs/app/concetti/form/save-bar.md docs/app/SUMMARY.md docs/app/concetti/form/README.md docs/app/concetti/componenti/README.md docs/app/piattaforma/layout.md AGENTS.md CHANGELOG.md
git commit -m "Documentazione della barra di salvataggio" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

### Task P10: skill `wi-app` e `wi-site`

Repo: **skills**.

**Files:**
- Modify: `skills/wi-app/references/model-and-resource.md` (indice dopo la riga 20; sottosezione nuova dopo la riga 412, prima di `### \`permissionSchema()\``; paragrafo dopo la riga 788, in fondo a CustomPageSchema)
- Modify: `skills/wi-site/references/workflows.md` (dopo le righe 27, 38 e 47)
- Test: nessun test; controllo con `grep`

**Interfaces:**
- Consumes: i contratti dei Task P1-P8 e la pagina `docs/app/concetti/form/save-bar.md` (Task P9); la versione `2.1.2-alpha.17` della lib e i range dei siti dalla Parte R
- Produces: sottosezione `### Backend save bar` (ancora `#backend-save-bar`) nella skill `wi-app`, richiamata da `wi-site`; in `wi-site` l'aggiornamento della lib alla nuova alpha, l'opt-in dei form scritti a mano, gli override dei moduli con `publish:module` per singolo file e l'override integrale di `header.php`.

Si lavora nel worktree delle skill `SKL_WT=/Users/andreamarinoni/Developer/packages/skills/.claude/worktrees/backend-save-bar`, sul branch `backend-save-bar` creato nel Task S1: ogni blocco parte da `cd /Users/andreamarinoni/Developer/packages/skills/.claude/worktrees/backend-save-bar`. Le skill documentano l'uso dell'app e della lib, non gli interni (regola "USE, do not MODIFY" dell'`AGENTS.md` delle skill). Gli installati in `.agents/` non si toccano: si risincronizzano con `npx skills` dopo il push (solo su richiesta esplicita dell'utente).

- [ ] **Step 1: Verifica lo stato di partenza**

Run: `grep -c 'Backend save bar' skills/wi-app/references/model-and-resource.md; grep -c '^### Backend save bar$' skills/wi-app/references/model-and-resource.md; grep -c 'save.bar' skills/wi-site/references/workflows.md`
Expected: FAIL con tre righe `0`.

- [ ] **Step 2: Sottosezione "Backend save bar" nella skill `wi-app`**

In `skills/wi-app/references/model-and-resource.md` sostituisci:

```markdown
  - [`formSchema()`](#formschema)
  - [`permissionSchema()`](#permissionschema)
```

con:

```markdown
  - [`formSchema()`](#formschema)
  - [Backend save bar](#backend-save-bar)
  - [`permissionSchema()`](#permissionschema)
```

In `skills/wi-app/references/model-and-resource.md` sostituisci:

```markdown
For layouts, override `formLayoutSchema(): ?Form` and compose Cards / Containers around `static::getInput('field_name')`. See `class/App/Resources/Css/CssAlertResource.php` for a full layout example.
```

con:

```markdown
For layouts, override `formLayoutSchema(): ?Form` and compose Cards / Containers around `static::getInput('field_name')`. See `class/App/Resources/Css/CssAlertResource.php` for a full layout example.

### Backend save bar

Every Resource form is tracked by the backend save bar with nothing to declare: `resource/form.php` prints `data-wi-save-bar` on the `<form>` (layout and legacy branch) unless the form is fully locked, and `data-wi-save-bar-dirty` when it re-renders a failed save (`FORM_ERRORS`). The lib copies the form's submit buttons into a fixed island when they scroll away and warns before leaving with unsaved changes.

- A `Submit` named `upload` in `formLayoutSchema()` replaces the footer Save (`ResourceFormLayoutRenderer::hasSubmit()`). It counts inside Container, Card and `expanded(true)` Accordion; not inside Modal, QuickCreate or components with `visibleWhen()` / `hiddenWhen()`.
- A view that calls `ResourceFormLayoutRenderer::render()` itself passes `'attributes' => ['data-wi-save-bar' => $saveBar, 'data-wi-save-bar-dirty' => $saveBar && !empty($FORM_ERRORS)]`, with `$saveBar = empty($READONLY) || !empty($READONLY_EDITABLE)`. `AttributeString::render()` prints `true` as a bare attribute and omits `false` / `null`; the option ignores `id`, `method`, `enctype`, `action`, `onsubmit` and `class`, which the renderer sets. Never print `data-wi-save-bar-dirty=""`: presence alone marks the form dirty.
- A password that is not the user's login credential declares `->autocomplete('new-password')` (see `SecurityResource`), otherwise the browser autofills it and the form looks modified. `data-wi-save-bar-ignore` is only for the account's own confirmation password.
- Never put `Button::post()` inside a Resource form: the browser drops the nested `<form>`.
- Scripts call `wiSaveBar?.reset(form)` before `form.submit()` and in AJAX success callbacks. `wiSaveBar?.absorb(el)` is only for writes the user did not make (init fills, AJAX prefill), on the narrowest container, never on the whole form.

Full contract: `docs/app/concetti/form/save-bar.md`.
```

In `skills/wi-app/references/model-and-resource.md` sostituisci:

```markdown
Routes for a custom page schema are wired manually in the project's route files (custom backend route + handler). The schema only defines the form inputs and labels — it does not register routes by itself.
```

con:

```markdown
Routes for a custom page schema are wired manually in the project's route files (custom backend route + handler). The schema only defines the form inputs and labels — it does not register routes by itself.

A hand-written backend `<form>` that saves a record opts into the save bar with a literal `data-wi-save-bar` attribute, plus an inline `data-wi-save-bar-dirty` when the page re-renders unsaved values after a failed POST. For `user()` flows the signal is `user()->written` (`false` when nothing was written), never the ALERT code. See [Backend save bar](#backend-save-bar).
```

- [ ] **Step 3: Verifica e commit della skill `wi-app`**

Run: `grep -c 'Backend save bar' skills/wi-app/references/model-and-resource.md; grep -c '^### Backend save bar$' skills/wi-app/references/model-and-resource.md`
Expected: PASS con `3` (indice, titolo, rimando da CustomPageSchema) e `1`: l'ancora `#backend-save-bar` ha il suo titolo.

```bash
git add skills/wi-app/references/model-and-resource.md
git commit -m "wi-app: barra di salvataggio del backend nei form Resource e scritti a mano" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 4: Aggiornamento dei siti, form a mano e override nella skill `wi-site`**

In `skills/wi-site/references/workflows.md` sostituisci:

```markdown
- `php forge start` runs the local server and may fill missing `.env` values during local setup.
```

con:

```markdown
- `php forge start` runs the local server and may fill missing `.env` values during local setup.
- Updating the lib to a new alpha (the backend save bar needs `wonder-image` `2.1.2-alpha.17`): if `package.json` already allows it (`^2.1.2-alpha.*`), `composer update` is enough because `forge config` runs `npm install`; outside the range (`^2.1.1-alpha.*`, `^2.0.x`, `^2.1.0`) run `npm install wonder-image@^2.1.2-alpha.17` and commit `package.json` and `package-lock.json`, because CI uses `npm ci`.
```

In `skills/wi-site/references/workflows.md` sostituisci:

```markdown
- One file per Model / Resource — do not collapse multiple tables into a single file.
```

con:

```markdown
- One file per Model / Resource — do not collapse multiple tables into a single file.
- Backend save bar: Resource forms are tracked automatically (contract in `wi-app/references/model-and-resource.md` → "Backend save bar"). A hand-written `<form>` in the site or in a copy-paste module opts in with a literal `data-wi-save-bar` attribute; remove any old `preventFormSubmit(form)` call when you add it.
- Module view overrides in `custom/modules/<slug>/view/`: delete an override identical to the package view; a customized one gets the save bar attributes by hand, or only that file is republished with `php forge publish:module immobili pages/backend/immobili/form.php --force`. Never run `--force` on the whole tree: it overwrites customized views.
```

In `skills/wi-site/references/workflows.md` sostituisci:

```markdown
- Preserve the `{frontend,backend}` split — do not reintroduce flat or legacy ad hoc folders.
```

con:

```markdown
- Preserve the `{frontend,backend}` split — do not reintroduce flat or legacy ad hoc folders.
- A full override of `custom/view/components/backend/layout/header.php` must subtract `var(--wi-save-bar-reserve, 0px)` from the page container `min-height`, like the framework header; otherwise backend pages get up to 96px of extra scroll.
```

- [ ] **Step 5: Verifica e commit della skill `wi-site`**

Run: `grep -c 'save.bar' skills/wi-site/references/workflows.md; test -f skills/wi-app/references/model-and-resource.md && echo rimando-ok`
Expected: PASS con `4` (aggiornamento della lib, form a mano, override dei moduli, `header.php`) e `rimando-ok`.

```bash
git add skills/wi-site/references/workflows.md
git commit -m "wi-site: barra di salvataggio, aggiornamento della lib e override dei moduli" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 6: Sincronizzazione (solo su richiesta esplicita dell'utente)**

Push del branch delle skill e PR verso `main` (prima `gh pr list` per unirsi a una PR aperta, senza force) (solo su richiesta esplicita dell'utente). Dopo il merge, in un sito o nell'app: `php forge skills` oppure `npx skills update` (solo su richiesta esplicita dell'utente). Non si modifica `.agents/` a mano.

### Task P11: controlli finali

Repo: **app** (nessun commit: il task non cambia file del repo).

**Files:**
- Create: `$HOME/.cache/wonder-tooling/save-bar/app-prepend.php` (fuori dai repo), `$HOME/.cache/wonder-tooling/save-bar/app-after.txt`, `$HOME/.cache/wonder-tooling/save-bar/gestionale-after.txt`
- Test: tutti i test dell'app (`git ls-files tests`), i 71 test unitari del gestionale e i 2 di immobili

**Interfaces:**
- Consumes: i commit dei Task P1-P9; `app-baseline.txt` e `gestionale-baseline.txt` del Task S1; la checkout principale di `packages/gestionale` e `packages/immobili`, con `vendor/wonder-image/app` in symlink verso `packages/app`
- Produces: lint pulito, 130 test dell'app senza fallimenti, suite dei moduli uguali alla base con `class/` del worktree; la route POST dell'account confermata per il Task P5 e per i casi I dell'account

Nessuna classe nuova o spostata sotto `class/` (`AttributeString` esiste già), quindi `composer dumpautoload` non serve. Le suite dei moduli girano sulla checkout principale, come la base del Task S1. Il loro `vendor/wonder-image/app` punta alla checkout principale dell'app, cioè a `main`. Il prepend fa risolvere prima le classi `Wonder\` da `class/` del worktree. Le funzioni di `app/` (per esempio `user()`) restano quelle di `main`, perché Composer le carica come `files` all'avvio.

- [ ] **Step 1: Lint di ogni file PHP toccato**

```bash
cd /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
for f in \
  class/App/Support/AttributeString.php \
  class/Backend/Support/ResourceFormLayoutRenderer.php \
  class/Backend/Support/UserManagementPageController.php \
  class/App/PageSchema/AccountPageSchema.php \
  class/App/Resources/Config/SecurityResource.php \
  class/Themes/Bootstrap/Form/Components/Repeater.php \
  app/function/user/user.php \
  app/http/backend/account/index.php \
  app/view/pages/backend/resource/form.php \
  app/view/pages/backend/scheduler/form.php \
  app/view/pages/backend/user/manage.php \
  app/view/pages/backend/account/index.php \
  app/view/pages/backend/config/configuration-file.php \
  app/view/components/backend/layout/header.php \
  tests/App/Support/AttributeStringTest.php \
  tests/Backend/Support/ResourceFormLayoutRendererTest.php \
  tests/Backend/Support/UserManagementPageControllerTest.php \
  tests/App/PageSchema/AccountPageSchemaTest.php \
  tests/App/Resources/SecurityResourceTest.php \
  tests/Themes/RepeaterRemoveRowTest.php \
  tests/Themes/RepeaterAutonumericTest.php; do php -l "$f" >/dev/null || echo "ERRORE $f"; done; echo "lint finito"
```

Expected: solo `lint finito`. Una riga `ERRORE` indica il file da correggere nel task che lo ha toccato.

- [ ] **Step 2: Tutti i test dell'app contro la base**

```bash
cd /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
git ls-files tests | grep -v -e harness.php -e scheduler-integration.php | wc -l
for f in $(git ls-files tests | grep -v -e harness.php -e scheduler-integration.php); do php "$f" >/dev/null || echo "FALLITO $f"; done \
  | tee "$HOME/.cache/wonder-tooling/save-bar/app-after.txt"
diff "$HOME/.cache/wonder-tooling/save-bar/app-baseline.txt" "$HOME/.cache/wonder-tooling/save-bar/app-after.txt" && echo "come la base"
```

Expected:
- `130`: i 124 della base più i 6 file nuovi aggiunti con `git add -f` (`AttributeStringTest`, `ResourceFormLayoutRendererTest`, `UserManagementPageControllerTest`, `AccountPageSchemaTest`, `SecurityResourceTest`, `RepeaterRemoveRowTest`);
- nessuna riga `FALLITO`, poi `come la base`.

Se il conteggio è 124-129, un test nuovo non è stato aggiunto con `git add -f`: torna al commit del suo task.

- [ ] **Step 3: Prepend dell'autoloader verso il worktree**

Scrivi il file fuori dai repo:

```bash
mkdir -p "$HOME/.cache/wonder-tooling/save-bar"
cat > "$HOME/.cache/wonder-tooling/save-bar/app-prepend.php" <<'PHP'
<?php
// Risolve Wonder\ su class/ del worktree dell'app prima del vendor del modulo.
$worktree = getenv('WI_APP_WORKTREE');

if (! is_string($worktree) || ! is_dir($worktree.'/class')) {
    fwrite(STDERR, "WI_APP_WORKTREE non valido\n");
    exit(1);
}

require getcwd().'/vendor/autoload.php';

spl_autoload_register(static function (string $class) use ($worktree): void {
    $file = $worktree.'/class/'.str_replace('\\', '/', substr($class, 7)).'.php';

    if (str_starts_with($class, 'Wonder\\') && is_file($file)) {
        require $file;
    }
}, true, true);
PHP
```

Il file richiede per primo il `vendor/autoload.php` del modulo, dalla cartella corrente. Composer mette il suo loader in testa alla coda, quindi il prepend va registrato dopo, sempre in testa. Senza `WI_APP_WORKTREE` valido il prepend esce con errore, così i test non girano per sbaglio sul codice di `main`.

```bash
cd /Users/andreamarinoni/Developer/packages/gestionale
WI_APP_WORKTREE=/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar \
  php -d auto_prepend_file="$HOME/.cache/wonder-tooling/save-bar/app-prepend.php" <<'PHP'
<?php
foreach ([
    \Wonder\App\Support\AttributeString::class,
    \Wonder\Backend\Support\ResourceFormLayoutRenderer::class,
    \Wonder\Backend\Support\UserManagementPageController::class,
    \Wonder\App\PageSchema\AccountPageSchema::class,
    \Wonder\App\Resources\Config\SecurityResource::class,
    \Wonder\Themes\Bootstrap\Form\Components\Repeater::class,
] as $class) {
    echo (new ReflectionClass($class))->getFileName(), PHP_EOL;
}
PHP
php -d auto_prepend_file="$HOME/.cache/wonder-tooling/save-bar/app-prepend.php" tests/GestionaleTest.php; echo "exit=$?"
```

Expected:
- sei percorsi, tutti sotto `/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar/class/`: nessuno sotto `vendor/wonder-image/app` o `packages/app/class/`;
- poi `WI_APP_WORKTREE non valido` e `exit=1`: senza la variabile il prepend si ferma.

`php -r` non esegue `auto_prepend_file`: per questo il controllo passa lo script da stdin.

- [ ] **Step 4: Suite del gestionale e di immobili con l'app modificata**

```bash
cd /Users/andreamarinoni/Developer/packages/gestionale
git status --porcelain | wc -l
ls tests/*Test.php | wc -l
for f in tests/*Test.php; do
  WI_APP_WORKTREE=/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar \
    php -d auto_prepend_file="$HOME/.cache/wonder-tooling/save-bar/app-prepend.php" "$f" >/dev/null || echo "FALLITO $f"
done | tee "$HOME/.cache/wonder-tooling/save-bar/gestionale-after.txt"
diff "$HOME/.cache/wonder-tooling/save-bar/gestionale-baseline.txt" "$HOME/.cache/wonder-tooling/save-bar/gestionale-after.txt" && echo "come la base"
git status --porcelain | wc -l
```

Expected:
- lo stesso numero di file modificati prima e dopo, uguale a quello annotato nel Task S1;
- `71`, nessuna riga `FALLITO`, poi `come la base`.

Su PHP 8.5 alcuni test stampano su stderr `Deprecated: Method ReflectionProperty::setAccessible()`. Compare anche nella base e non è un fallimento. Il gestionale ha 71 test unitari, non i 74 della spec. `tests/integrazione/` resta fuori come nella base, perché usa il DB di `ecommerce-site`.

```bash
cd /Users/andreamarinoni/Developer/packages/immobili
test -f vendor/autoload.php && readlink vendor/wonder-image/app || echo "non eseguibile: manca vendor/, serve composer install"
for f in tests/listing-routes.php tests/property-media.php; do
  WI_APP_WORKTREE=/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar \
    php -d auto_prepend_file="$HOME/.cache/wonder-tooling/save-bar/app-prepend.php" "$f" || echo "FALLITO $f"
done
```

Expected: `../../../app/`, poi `Listing route checks passed` e `Property media checks passed`.

Se compare `non eseguibile`, non lanciare `composer install` di tua iniziativa, perché cambia la checkout di immobili: segna i 2 test "non eseguibili: manca vendor/" nel resoconto e chiedi all'utente.

- [ ] **Step 5: Route dell'account, attributi delle viste e commit del branch**

```bash
cd /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
grep -cF "Route::post('/', \$ROOT_APP.'/http/backend/account/index.php')" app/config/routes/route.backend.php
for f in app/view/pages/backend/resource/form.php app/view/pages/backend/scheduler/form.php app/view/pages/backend/user/manage.php app/view/pages/backend/account/index.php app/view/pages/backend/config/configuration-file.php class/App/PageSchema/AccountPageSchema.php; do
  echo "$(grep -c 'data-wi-save-bar' "$f") $f"
done
git status --short --branch
git log --format=%s main..HEAD
```

Expected:
- `1`: la POST di `/backend/account/` c'è, quindi il salvataggio dell'account del Task P5 ha una route;
- sei righe che iniziano con `1`: ogni vista e `AccountPageSchema` dichiarano la barra una sola volta. `ResourceFormLayoutRenderer` non contiene il nome, perché lo riceve dall'opzione `attributes`;
- `## backend-save-bar` senza file modificati;
- nel log i nove commit dei Task P1-P9, sopra quelli della spec e del piano.

Se la route manca (per esempio dopo un rebase su un `main` diverso), fermati e avvisa l'utente: senza route il form dell'account risponde 404 al salvataggio e i casi I dell'account sono bloccati.

- [ ] **Step 6: Push e PR del branch dell'app (solo su richiesta esplicita dell'utente)**

Prima `gh pr list --head backend-save-bar --state open`: se esiste già una PR per il branch, si aggiungono i commit con un push fast-forward, mai con force (solo su richiesta esplicita dell'utente). Altrimenti `git push -u origin backend-save-bar` e `gh pr create --base main` (solo su richiesta esplicita dell'utente). Il corpo della PR termina con la riga di attribuzione di Claude Code. Il merge e la release dell'app si fanno come descritto nella Parte R, e anch'essi solo su richiesta esplicita dell'utente.

## Parte R — Preparazione, moduli, siti, rilascio e integrazione

Copre la vista del modulo immobili (5.9), gli override pubblicati e i siti (5.10), "Rilascio e compatibilità", i casi di integrazione I1-I22 e M1-M3 della 6.5 e la checklist finale della 6.6.

Regole valide per tutta la parte:
- lo stato della shell non persiste tra un comando e l'altro: ogni blocco ridefinisce le variabili che usa e lavora con percorsi assoluti;
- `/Users/andreamarinoni/Developer/packages` e `/Users/andreamarinoni/Desktop/PROGETTI/template` sono la stessa cartella: se l'hook rifiuta Edit/Write su un worktree col percorso `Developer/packages`, si usa lo stesso percorso sotto `Desktop/PROGETTI/template`;
- push, PR, merge, release npm, tag e pubblicazioni si fanno **solo su richiesta esplicita dell'utente**; ogni comando di questo tipo è marcato così;
- non si stampa mai il contenuto di un `.env`: si leggono solo le chiavi non segrete con `grep -n '^APP_URL=\|^APP_ENV=\|^APP_DOMAIN='`;
- la cartella di lavoro fuori dai repo è `$HOME/.cache/wonder-tooling/save-bar` (accanto alla cache di Playwright); i backup dei siti stanno in `/Users/andreamarinoni/Developer/boilerplates/.save-bar-backup/<sito>/` (`boilerplates/` non è un repo git).

### Task R1: attributi della barra nella vista backend di immobili

Repo: **immobili** (worktree `IMM_WT`, branch `backend-save-bar`).

La vista `view/pages/backend/immobili/form.php` chiama `ResourceFormLayoutRenderer::render()` con opzioni proprie, quindi non riceve i `$saveBarAttributes` di `resource/form.php`. Gli attributi si scrivono letterali, con la stessa condizione di `scheduler/form.php` (Task P3). Un'app che non conosce l'opzione `attributes` la ignora: il renderer di `main` legge solo `id`, `action`, `method`, `enctype`, `onsubmit` e `footer`.

**Files:**
- Modify: `view/pages/backend/immobili/form.php` (righe 12-15 e 27-35)
- Modify: `CHANGELOG.md` (dopo il blocco "Compatibilità Composer", righe 22-27)
- Modify: `docs/frontend/personalizzare-le-view.md` (dopo la riga 19)
- Test: `tests/save-bar-view-check.php`, controllo usa e getta: `tests/*` è ignorato (`.gitignore:7 /tests/*`), non si committa e si cancella alla fine

**Interfaces:**
- Consumes: opzione `attributes` di `ResourceFormLayoutRenderer::render()` (Task P2), variabili della vista `READONLY`, `READONLY_EDITABLE` e `FORM_ERRORS` di `ResourcePagePresenter`
- Produces: `form#immobile-resource-form[data-wi-save-bar]`, con `data-wi-save-bar-dirty` dopo un POST fallito (casi I20 e I22)

- [ ] **Step 1: Scrivi il controllo che fallisce**

Crea `tests/save-bar-view-check.php`. Il controllo sostituisce `View` e il renderer con due classi finte, include la vista e legge le opzioni passate al renderer.

```php
<?php

// Verifica usa e getta della vista backend: non va committata (tests/* e ignorato).
namespace Wonder\View {
    final class View
    {
        public static function layout(string $name): void {}

        public static function end(): void {}
    }
}

namespace Wonder\Backend\Support {
    final class ResourceFormLayoutRenderer
    {
        public static array $options = [];

        public static function render(mixed $layout, array $options = []): string
        {
            self::$options = $options;

            return '';
        }
    }
}

namespace Wonder\Plugin\Immobili\Support\Forms {
    final class ImmobileForm
    {
        public static function text(string $key): string
        {
            return $key;
        }
    }
}

namespace {
    use Wonder\Backend\Support\ResourceFormLayoutRenderer;

    set_error_handler(static function (int $severity, string $message): bool {
        throw new ErrorException($message, 0, $severity);
    });

    function check(bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    }

    // Rende la vista con variabili finte e restituisce le opzioni passate al renderer.
    function formOptions(array $vars): array
    {
        ResourceFormLayoutRenderer::$options = [];
        extract(['FORM_LAYOUT' => null] + $vars);
        ob_start();
        try {
            include dirname(__DIR__).'/view/pages/backend/immobili/form.php';
        } finally {
            ob_end_clean();
        }

        return ResourceFormLayoutRenderer::$options;
    }

    $clean = ['data-wi-save-bar' => true, 'data-wi-save-bar-dirty' => false];
    $dirty = ['data-wi-save-bar' => true, 'data-wi-save-bar-dirty' => true];

    $options = formOptions([]);
    check(($options['attributes'] ?? null) === $clean, 'Default form tracked and clean');
    check($options['id'] === 'immobile-resource-form' && $options['action'] === '', 'Id and action unchanged');
    check($options['method'] === 'POST' && $options['enctype'] === 'multipart/form-data', 'Method and enctype unchanged');
    check(formOptions(['FORM_ERRORS' => ['titolo' => 'x']])['attributes'] === $dirty, 'Failed POST starts dirty');
    check(formOptions(['VALUES' => ['provider' => 'gestionaleimmobiliare']])['attributes'] === $clean, 'Feed record tracked and clean');
    check(formOptions(['READONLY' => true, 'READONLY_EDITABLE' => [], 'FORM_ERRORS' => ['x' => 'y']])['attributes'] === ['data-wi-save-bar' => false, 'data-wi-save-bar-dirty' => false], 'Read-only form not tracked');
    check(formOptions(['READONLY' => true, 'READONLY_EDITABLE' => ['stato'], 'FORM_ERRORS' => ['x' => 'y']])['attributes'] === $dirty, 'Editable read-only form tracked');

    echo "Backend form save bar checks passed\n";
}
```

- [ ] **Step 2: Esegui il controllo e verifica che fallisca**

Run: `cd /Users/andreamarinoni/Developer/packages/immobili/.claude/worktrees/backend-save-bar && git check-ignore -v tests/save-bar-view-check.php && php tests/save-bar-view-check.php`

Expected: `.gitignore:7:/tests/*	tests/save-bar-view-check.php`, poi FAIL con `PHP Fatal error:  Uncaught RuntimeException: Default form tracked and clean`.

- [ ] **Step 3: Aggiungi gli attributi alla vista**

In `view/pages/backend/immobili/form.php` sostituisci:

```php
if (is_object($NAME ?? null)) {
    \Wonder\App\LegacyGlobals::set('NAME', $NAME);
}
?>
```

con:

```php
if (is_object($NAME ?? null)) {
    \Wonder\App\LegacyGlobals::set('NAME', $NAME);
}

$saveBar = empty($READONLY) || !empty($READONLY_EDITABLE);
?>
```

e sostituisci:

```php
        'action' => (string) ($FORM_ACTION ?? ''),
    ]
)?>
```

con:

```php
        'action' => (string) ($FORM_ACTION ?? ''),
        'attributes' => [
            'data-wi-save-bar' => $saveBar,
            'data-wi-save-bar-dirty' => $saveBar && !empty($FORM_ERRORS),
        ],
    ]
)?>
```

Oggi Immobile non ha `syncSchema`, quindi `READONLY` vale sempre `false`: la condizione serve solo per il futuro. Il record da feed resta tracciato: il suo avviso e il suo script non cambiano.

- [ ] **Step 4: Esegui il controllo e il lint con i due PHP**

Run:
```bash
cd /Users/andreamarinoni/Developer/packages/immobili/.claude/worktrees/backend-save-bar
php tests/save-bar-view-check.php
"/Users/andreamarinoni/Library/Application Support/Herd/bin/php82" tests/save-bar-view-check.php
php -l view/pages/backend/immobili/form.php
"/Users/andreamarinoni/Library/Application Support/Herd/bin/php82" -l view/pages/backend/immobili/form.php
git diff --stat
```

Expected: due volte `Backend form save bar checks passed`, due volte `No syntax errors detected in view/pages/backend/immobili/form.php`, poi `view/pages/backend/immobili/form.php | 6 ++++++`.

- [ ] **Step 5: CHANGELOG e docs degli override**

In `CHANGELOG.md` sostituisci:

```markdown
- Il modulo accetta sia le release `^2.2` sia `dev-main` di
  `wonder-image/app`, senza richiedere alias di versione nei siti in sviluppo.

### ⚠️ Breaking — path delle view e namespace
```

con:

```markdown
- Il modulo accetta sia le release `^2.2` sia `dev-main` di
  `wonder-image/app`, senza richiedere alias di versione nei siti in sviluppo.

### Barra di salvataggio del backend

- Il form degli immobili dichiara la barra di salvataggio di `wonder-image/app` con `data-wi-save-bar` e `data-wi-save-bar-dirty` nell'opzione `attributes` del renderer: dopo un salvataggio fallito parte già come non salvato. Con una versione dell'app che non conosce l'opzione non cambia niente.
- Un override di `pages/backend/immobili/form.php` nel sito non riceve gli attributi: vedi `docs/frontend/personalizzare-le-view.md`.

### ⚠️ Breaking — path delle view e namespace
```

In `docs/frontend/personalizzare-le-view.md` sostituisci:

```markdown
Il meccanismo è gestito da `Immobili::viewPath()`, che controlla prima l'override del sito.

## Componenti disponibili
```

con:

```markdown
Il meccanismo è gestito da `Immobili::viewPath()`, che controlla prima l'override del sito.

### Override del form del backend

La view `pages/backend/immobili/form.php` dichiara la barra di salvataggio di `wonder-image/app` con gli attributi `data-wi-save-bar` e `data-wi-save-bar-dirty`. Un override nel sito non li riceve da solo:

- se l'override è identico al file del modulo, cancellalo;
- se è personalizzato, aggiungi a mano i due attributi come nella view del modulo, oppure ripubblica solo quel file con `php forge publish:module immobili pages/backend/immobili/form.php --force`;
- non usare mai `--force` su tutto l'albero: sovrascrive le view personalizzate.

## Componenti disponibili
```

Run: `git diff --stat`

Expected: `CHANGELOG.md | 5 +++++`, `docs/frontend/personalizzare-le-view.md | 8 ++++++++`, `view/pages/backend/immobili/form.php | 6 ++++++`, `3 files changed, 19 insertions(+)`.

- [ ] **Step 6: Commit e pulizia del controllo usa e getta**

```bash
cd /Users/andreamarinoni/Developer/packages/immobili/.claude/worktrees/backend-save-bar
git add view/pages/backend/immobili/form.php CHANGELOG.md docs/frontend/personalizzare-le-view.md
git commit -m "Add backend save bar attributes to the property form" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
rm tests/save-bar-view-check.php
git status --porcelain --ignored tests
```

Expected: un commit con 3 file e 19 righe aggiunte; l'ultimo comando non stampa niente. Il messaggio è in inglese come la storia del repo (`Add ListingRoute presets and deferred media updates`). La verifica nel browser è I20 (Task R6); la release del modulo è lo Step 4 del Task R9.

### Task R2: preparazione dell'integrazione

Repo: nessun commit. Si lavora in `$HOME/.cache/wonder-tooling/save-bar` (d'ora in poi `W`) e, in modo reversibile, in tre siti di `/Users/andreamarinoni/Developer/boilerplates`: new-site (new.test), ecommerce-site (ecommerce.test) e immobili-site (immobili.test).

Il Task parte quando lib, app e immobili sono completi sui loro branch (Parti C, I e P e Task R1). Tutto quello che cambia nei siti viene annotato prima e ripristinato nel Task R8. Nei siti non si esegue mai `composer` né `npm` in questa fase.

**Files:**
- Create: `$W/siti.sh` e `$W/siti-test.sh` (sostituzioni e ripristino nei siti), `$W/snap.js`, `$W/style.js`, `$W/stato.js` e `$W/casi.js` (helper per `javascript_tool`)
- Create: `$W/app-base` e `$W/lib-base` (worktree staccati sul punto di partenza dei branch), `$W/lib-base-dist` e `$W/lib-dist` (build della lib fuori dal repo)
- Create: `$W/integrazione-esiti.txt` e `$W/record-di-prova.txt`
- Modify, temporaneo: nei tre siti `vendor/wonder-image/app` e `assets/lib/wonder-image/dist`; in immobili-site anche `vendor/wonder-image/immobili` e la riga `APP_URL` di `.env`
- Create, temporaneo: `new-site/app/Resources/SaveBarProbe/SaveBarProbeResource.php`, la Resource di prova dei casi I1, I3-I6, I11 e I12

**Interfaces:**
- Consumes: `LIB_WT`, `APP_WT` e `IMM_WT` del Task S1 con il lavoro delle Parti C, I e P e del Task R1
- Produces:
  - i siti puntano all'app e alla lib nuove; `siti.sh app <sito> nuova|base|originale` e `siti.sh dist <sito> nuova|base|originale` cambiano l'una o l'altra;
  - `siti.sh ripristina <sito>` rimette tutto com'era e confronta lo stato con quello annotato;
  - i backup in `/Users/andreamarinoni/Developer/boilerplates/.save-bar-backup/<sito>/`;
  - gli helper `__wiSnapTake`/`__wiSnapDiff`, `__wiStyleTake`/`__wiStyleDiff`, `__wiBar`/`__wiConfirmStub`, `__wiState`/`__wiUntil`, `__wiLogSubmits`/`__wiSubmits`, `__wiLeave` e `__wiHeightFor`;
  - l'utente è autenticato nel pannello Browser su new.test, ecommerce.test e immobili.test;
  - le regole comuni dei casi (Step 12), valide per i Task R3-R6.

- [ ] **Step 1: Prerequisiti e stato di partenza**

```bash
LIB_WT=/Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
APP_WT=/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
IMM_WT=/Users/andreamarinoni/Developer/packages/immobili/.claude/worktrees/backend-save-bar
SITES=/Users/andreamarinoni/Developer/boilerplates
test -f "$LIB_WT/src/build/backend/js/form/saveBar.js" && test -f "$LIB_WT/src/build/backend/css/save-bar.css" && echo "lib pronta"
grep -c "function hasSubmit" "$APP_WT/class/Backend/Support/ResourceFormLayoutRenderer.php"
grep -cF 'var(--wi-save-bar-reserve, 0px)' "$APP_WT/app/view/components/backend/layout/header.php"
grep -c data-wi-save-bar "$IMM_WT/view/pages/backend/immobili/form.php"
for wt in "$LIB_WT" "$APP_WT" "$IMM_WT"; do git -C "$wt" status --porcelain | wc -l; done
test ! -e "$SITES/.save-bar-backup" && echo "nessun backup"
for s in new-site ecommerce-site immobili-site; do
  echo "== $s"
  git -C "$SITES/$s" status --porcelain | wc -l
  for d in "$SITES/$s"/vendor/wonder-image/*; do printf '%s %s\n' "${d##*/}" "$(readlink "$d" || echo dir)"; done
  grep -n '^APP_URL=\|^APP_ENV=\|^APP_DOMAIN=' "$SITES/$s/.env"
done
herd links | grep -E '\| (new|ecommerce|immobili) +\|' | awk -F'|' '{print $2, $5}'
```

Expected:
- `lib pronta`, poi `1`, `1` e `2`;
- tre volte `0`, cioè i worktree non hanno modifiche da committare;
- `nessun backup`;
- new-site: `0`, `app dir`, le chiavi `APP_DOMAIN=new.test`, `APP_URL=https://new.test` e `APP_ENV=local`;
- ecommerce-site: `5`, `app dir`, `gestionale ../../../../packages/gestionale/`, le chiavi `APP_DOMAIN=ecommerce.test`, `APP_URL=https://ecommerce.test` e `APP_ENV=local`;
- immobili-site: `5`, `app dir`, `immobili dir`, le chiavi `APP_DOMAIN=immobili.site` e `APP_URL=https://immobili.site`, senza `APP_ENV`;
- i tre link di Herd verso `new-site`, `ecommerce-site` e `immobili-site`.

I numeri di file modificati nei siti sono quelli di oggi. Se sono diversi non è un errore: `siti.sh` annota lo stato reale nello Step 7 e lo confronta al ripristino. Se un worktree ha modifiche, fermati: il lavoro delle parti precedenti va prima committato sul suo branch. Se esiste già `.save-bar-backup`, fermati e chiedi all'utente: una preparazione precedente non è stata ripristinata.

- [ ] **Step 2: Script dei siti e sua prova**

`siti.sh` sposta le cartelle originali nel backup e mette al loro posto dei symlink; la `dist` della lib si copia senza `-p`, così `Asset::version()` cambia `?v=`. Ogni comando termina con `herd restart`, che svuota anche l'opcache. `siti-test.sh` prova lo script su siti finti, con un `herd` finto nel `PATH`, sotto `$W/tmp`.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
mkdir -p "$W"
cat > "$W/siti.sh" <<'SH'
#!/bin/bash
# Prepara, cambia e ripristina app e dist della lib nei siti di prova della barra di salvataggio.
set -euo pipefail

SITES=${SITES:-/Users/andreamarinoni/Developer/boilerplates}
BACKUP=${BACKUP:-$SITES/.save-bar-backup}
W=${W:-$HOME/.cache/wonder-tooling/save-bar}
APP_WT=${APP_WT:-/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar}

fail() { echo "$*" >&2; exit 1; }

usage() {
    fail "uso: siti.sh prepara <sito> [<modulo>=<cartella> ...] [APP_URL=<url>]
     siti.sh app <sito> nuova|base|originale
     siti.sh dist <sito> nuova|base|originale
     siti.sh ripristina <sito>"
}

links() {
    for d in "$root"/vendor/wonder-image/*; do
        printf '%s %s\n' "${d##*/}" "$(readlink "$d" || echo dir)"
    done
}

# Le dist della lib si copiano senza -p, cosi cambia il ?v= degli asset; l'originale torna con le sue date.
set_dist() {
    local dist=$root/assets/lib/wonder-image/dist
    rm -rf "$dist"
    case "$1" in
    nuova) cp -R "$W/lib-dist" "$dist" ;;
    base) cp -R "$W/lib-base-dist" "$dist" ;;
    *) cp -Rp "$bk/dist" "$dist" ;;
    esac
    echo "$site dist $1, wiSaveBar in head.js: $(grep -c wiSaveBar "$dist/backend/head.js" || true)"
}

same() {
    if diff "$@"; then echo "uguale: ${*: -1}"; else echo "DIVERSO: ${*: -1}"; bad=1; fi
}

cmd=${1:-}; site=${2:-}
[ -n "$site" ] || usage
root=$SITES/$site
bk=$BACKUP/$site
bad=0
[ -d "$root/vendor/wonder-image" ] || fail "sito non trovato: $root"
shift 2

case "$cmd" in
prepara)
    [ ! -e "$bk" ] || fail "backup gia presente: $bk"
    [ -d "$W/lib-dist/backend" ] || fail "manca la build della lib in $W/lib-dist"
    for arg in app=$APP_WT "$@"; do
        case "$arg" in
        APP_URL=*) grep -q '^APP_URL=' "$root/.env" || fail "APP_URL assente in $root/.env" ;;
        *=*)
            [ -d "$root/vendor/wonder-image/${arg%%=*}" ] && [ ! -L "$root/vendor/wonder-image/${arg%%=*}" ] \
                || fail "vendor/wonder-image/${arg%%=*} non e una cartella"
            [ -d "${arg#*=}" ] || fail "manca ${arg#*=}"
            ;;
        *) usage ;;
        esac
    done
    git -C "$root" status --porcelain > "$W/$site-status-before.txt"
    links > "$W/$site-links-before.txt"
    mkdir -p "$bk"
    for arg in app=$APP_WT "$@"; do
        case "$arg" in
        APP_URL=*)
            cp -p "$root/.env" "$bk/env"
            sed -i '' "s#^APP_URL=.*#$arg#" "$root/.env"
            grep -n '^APP_URL=' "$root/.env"
            ;;
        *)
            mv "$root/vendor/wonder-image/${arg%%=*}" "$bk/vendor-${arg%%=*}"
            ln -s "${arg#*=}" "$root/vendor/wonder-image/${arg%%=*}"
            ;;
        esac
    done
    cp -Rp "$root/assets/lib/wonder-image/dist" "$bk/dist"
    set_dist nuova
    links
    ;;
app)
    case "${1:-}" in
    nuova) src=$APP_WT ;;
    base) src=$W/app-base ;;
    originale) src=$bk/vendor-app ;;
    *) usage ;;
    esac
    [ -d "$src" ] || fail "manca $src"
    [ -L "$root/vendor/wonder-image/app" ] || fail "vendor/wonder-image/app non e un symlink: fai prima la preparazione"
    ln -sfn "$src" "$root/vendor/wonder-image/app"
    echo "$site app -> $(readlink "$root/vendor/wonder-image/app")"
    ;;
dist)
    case "${1:-}" in nuova|originale) ;; base) [ -d "$W/lib-base-dist/backend" ] || fail "manca $W/lib-base-dist" ;; *) usage ;; esac
    [ -d "$bk/dist" ] || fail "manca il backup $bk/dist: fai prima la preparazione"
    set_dist "$1"
    ;;
ripristina)
    [ -d "$bk/dist" ] || fail "manca il backup $bk/dist"
    for saved in "$bk"/vendor-*; do
        [ -e "$saved" ] || continue
        module=${saved##*/vendor-}
        [ -L "$root/vendor/wonder-image/$module" ] || fail "vendor/wonder-image/$module non e un symlink"
        rm "$root/vendor/wonder-image/$module"
        mv "$saved" "$root/vendor/wonder-image/$module"
    done
    if [ -f "$bk/env" ]; then
        cp -p "$bk/env" "$root/.env"
        if cmp -s "$bk/env" "$root/.env"; then echo "uguale: .env"; else echo "DIVERSO: .env"; bad=1; fi
    fi
    set_dist originale
    rm -rf "$root/app/Resources/SaveBarProbe"
    same -r "$bk/dist" "$root/assets/lib/wonder-image/dist"
    git -C "$root" status --porcelain > "$W/$site-status-after.txt"
    links > "$W/$site-links-after.txt"
    same "$W/$site-status-before.txt" "$W/$site-status-after.txt"
    same "$W/$site-links-before.txt" "$W/$site-links-after.txt"
    ;;
*) usage ;;
esac

herd restart
[ "$bad" = 0 ] || fail "ripristino con differenze: vedi le righe DIVERSO"
SH
cat > "$W/siti-test.sh" <<'SH'
#!/bin/bash
# Prova di siti.sh su siti finti, con herd finto nel PATH.
set -uo pipefail
T=$(cd "$(dirname "$0")" && pwd)/tmp
rm -rf "$T"; mkdir -p "$T/bin" "$T/sites" "$T/w/app-base/class" "$T/w/lib-dist/backend" "$T/w/lib-base-dist/backend" "$T/appwt/class" "$T/immwt/src"
printf '#!/bin/sh\necho "herd $*" >> "%s/herd.log"\n' "$T" > "$T/bin/herd"; chmod +x "$T/bin/herd"
echo 'wiSaveBar nuova' > "$T/w/lib-dist/backend/head.js"; echo 'base' > "$T/w/lib-base-dist/backend/head.js"
export PATH="$T/bin:$PATH" SITES="$T/sites" W="$T/w" APP_WT="$T/appwt"
S="$T/../siti.sh"
failures=0
expect() { if eval "$1"; then echo "ok   $2"; else echo "FAIL $2"; failures=$((failures+1)); fi; }

mk() {
    local r=$T/sites/$1
    mkdir -p "$r/vendor/wonder-image/app/class" "$r/assets/lib/wonder-image/dist/backend" "$r/app/Resources"
    echo "vecchia $1" > "$r/vendor/wonder-image/app/class/marker"
    echo 'vecchia' > "$r/assets/lib/wonder-image/dist/backend/head.js"
    touch -t 202001010000 "$r/assets/lib/wonder-image/dist/backend/head.js"
    printf 'APP_KEY=segreto\nAPP_URL=https://immobili.site\nDB_PASS=x\n' > "$r/.env"
    printf '/vendor/\n/assets/lib/\n/.env\n' > "$r/.gitignore"
    echo base > "$r/composer.lock"
    git -C "$r" init -q && git -C "$r" add -A && git -C "$r" -c user.email=t@t -c user.name=t commit -qm init
    echo modificato > "$r/composer.lock"
}
mk new-site; mk immobili-site
mkdir -p "$T/sites/immobili-site/vendor/wonder-image/immobili"; echo mod > "$T/sites/immobili-site/vendor/wonder-image/immobili/marker"
ln -s ../../../../gestionale/ "$T/sites/new-site/vendor/wonder-image/gestionale"

"$S" prepara new-site > "$T/out1" 2>&1; expect '[ $? = 0 ]' "prepara new-site"
N=$T/sites/new-site
expect '[ "$(readlink $N/vendor/wonder-image/app)" = "$APP_WT" ]' "app -> worktree"
expect '[ "$(cat $T/sites/.save-bar-backup/new-site/vendor-app/class/marker)" = "vecchia new-site" ]' "backup app con mv"
expect 'grep -q nuova $N/assets/lib/wonder-image/dist/backend/head.js' "dist nuova copiata"
expect '[ $N/assets/lib/wonder-image/dist/backend/head.js -nt $T/sites/.save-bar-backup/new-site/dist/backend/head.js ]' "dist nuova con data nuova"
expect 'grep -q "wiSaveBar in head.js: 1" $T/out1' "conteggio wiSaveBar"
expect '[ "$(cat $T/w/new-site-links-before.txt)" = "$(printf "app dir\ngestionale ../../../../gestionale/")" ]' "readlink annotati"
expect '[ "$(cat $T/w/new-site-status-before.txt)" = " M composer.lock" ]' "status annotato"
"$S" prepara new-site > "$T/out2" 2>&1; expect '[ $? = 1 ] && grep -q "backup gia presente" $T/out2' "seconda preparazione rifiutata"

"$S" prepara immobili-site immobili=$T/immwt APP_URL=https://immobili.test > "$T/out3" 2>&1; expect '[ $? = 0 ]' "prepara immobili-site"
I=$T/sites/immobili-site
expect '[ "$(readlink $I/vendor/wonder-image/immobili)" = "$T/immwt" ]' "immobili -> worktree"
expect 'grep -q "^APP_URL=https://immobili.test$" $I/.env && grep -q "^DB_PASS=x$" $I/.env' ".env: solo APP_URL cambiato"
expect '! grep -q -e segreto -e DB_PASS $T/out3' "nessun segreto stampato"

rm -rf "$T/sites/.save-bar-backup/x"; mk x; "$S" prepara x nonesiste=$T/immwt > "$T/out4" 2>&1
expect '[ $? = 1 ] && [ ! -e $T/sites/.save-bar-backup/x ] && [ ! -L $T/sites/x/vendor/wonder-image/app ]' "argomento sbagliato: nessuna modifica"

"$S" app new-site base > /dev/null; expect '[ "$(readlink $N/vendor/wonder-image/app)" = "$T/w/app-base" ]' "app base"
"$S" app new-site originale > /dev/null; expect '[ "$(cat $N/vendor/wonder-image/app/class/marker)" = "vecchia new-site" ]' "app originale"
"$S" app new-site nuova > /dev/null; expect '[ "$(readlink $N/vendor/wonder-image/app)" = "$APP_WT" ]' "app nuova"
"$S" dist new-site originale > /dev/null; expect 'grep -q vecchia $N/assets/lib/wonder-image/dist/backend/head.js && [ ! $N/assets/lib/wonder-image/dist/backend/head.js -nt $T/sites/.save-bar-backup/new-site/dist/backend/head.js ]' "dist originale con le sue date"
"$S" dist new-site base > "$T/out8"; expect 'grep -q base $N/assets/lib/wonder-image/dist/backend/head.js && grep -q "dist base, wiSaveBar in head.js: 0" $T/out8' "dist base"
mv "$T/w/lib-base-dist" "$T/w/lib-base-dist.x"; "$S" dist new-site base > /dev/null 2>&1; expect '[ $? = 1 ] && grep -q base $N/assets/lib/wonder-image/dist/backend/head.js' "dist base mancante rifiutata senza toccare la dist"; mv "$T/w/lib-base-dist.x" "$T/w/lib-base-dist"
"$S" dist new-site nuova > /dev/null; expect 'grep -q nuova $N/assets/lib/wonder-image/dist/backend/head.js' "dist nuova di nuovo"
"$S" app new-site boh > /dev/null 2>&1; expect '[ $? = 1 ]' "modalita sconosciuta rifiutata"
"$S" app x nuova > /dev/null 2>&1; expect '[ $? = 1 ]' "app senza preparazione rifiutata"

mkdir -p $N/app/Resources/SaveBarProbe; touch $N/app/Resources/SaveBarProbe/SaveBarProbeResource.php
"$S" ripristina new-site > "$T/out5" 2>&1; expect '[ $? = 0 ]' "ripristina new-site"
expect '[ ! -L $N/vendor/wonder-image/app ] && [ "$(cat $N/vendor/wonder-image/app/class/marker)" = "vecchia new-site" ]' "app ripristinata"
expect '[ ! -e $N/app/Resources/SaveBarProbe ]' "probe tolta"
expect '[ "$(grep -c "^uguale" $T/out5)" = 3 ] && ! grep -q DIVERSO $T/out5' "tre controlli uguali"

"$S" ripristina immobili-site > "$T/out6" 2>&1; expect '[ $? = 0 ]' "ripristina immobili-site"
expect 'grep -q "^APP_URL=https://immobili.site$" $I/.env && grep -q "uguale: .env" $T/out6' ".env ripristinato"
expect '[ "$(cat $I/vendor/wonder-image/immobili/marker)" = mod ]' "immobili ripristinato"
expect '! grep -q -e segreto -e DB_PASS $T/out6' "nessun segreto stampato al ripristino"

"$S" prepara new-site > /dev/null 2>&1 || true
rm -rf $T/sites/.save-bar-backup/new-site/dist/backend/head.js.bak; echo extra > $N/composer.json
"$S" ripristina new-site > "$T/out7" 2>&1; expect '[ $? = 1 ] && grep -q "DIVERSO: .*status-after.txt" $T/out7' "differenza di status segnalata"
expect '[ "$(grep -c "herd restart" $T/herd.log)" -ge 10 ]' "herd restart dopo ogni comando"

echo "failures=$failures"; [ $failures = 0 ] && echo "siti.sh ok"
SH
chmod +x "$W/siti.sh" "$W/siti-test.sh"
bash -n "$W/siti.sh" && bash -n "$W/siti-test.sh" && echo "sintassi ok"
"$W/siti-test.sh" | tail -2
rm -rf "$W/tmp"
```

Expected: `sintassi ok`, poi `failures=0` e `siti.sh ok`. Se compare una riga `FAIL`, lancia `"$W/siti-test.sh" | grep FAIL`, correggi `siti.sh` e ripeti: lo script va usato sui siti veri solo quando la prova è verde.

- [ ] **Step 3: Helper per il pannello Browser**

Quattro file da incollare con `javascript_tool`, dopo averli letti con Read. Ogni navigazione li cancella, quindi si reincollano dopo ogni `navigate`, ricarica o invio di form.
- `snap.js`: `__wiSnapTake(label, urls)` scarica le pagine con la sessione corrente e le normalizza (id casuali `_xxxxxxxxxx`, token esadecimali lunghi, orari, `?v=`); `__wiSnapDiff(a, b, limit)` confronta due raccolte riga per riga.
- `style.js`: `__wiStyleTake(label, url, width, height, wait)` apre la pagina in un iframe nascosto e registra gli stili calcolati di ogni elemento, esclusi l'isola e la sua regione `role="status"`; `__wiStyleDiff(a, b, limit)` elenca le differenze.
- `stato.js`: `__wiBar()` riassume la barra (form tracciati, isola visibile, etichetta, copie, Salva della pagina, testo della regione di stato, `confirm()` registrati); `__wiConfirmStub(risposta)` sostituisce `window.confirm`.
- `casi.js`:
  - `__wiState()` legge lo stato interno dell'isola (`data-wi-state`);
  - `__wiUntil(stato, ms)` aspetta quello stato e restituisce lo stato letto allo scadere;
  - `__wiLogSubmits()` registra in `sessionStorage` il pulsante di ogni invio, e `__wiSubmits()` restituisce il registro e lo svuota;
  - `__wiLeave()` dice se la pagina chiederebbe conferma all'uscita;
  - `__wiHeightFor(selettore)` calcola l'altezza della finestra che porta tutti i Salva della pagina sotto la fascia dell'isola.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
cat > "$W/snap.js" <<'JS'
window.__wiSnap ??= {};
window.__wiSnapNorm = html => html
    .replace(/_[a-z]{10}\b/g, '_ID')
    .replace(/\b[0-9a-f]{32,}\b/g, 'HEX')
    .replace(/\b\d{1,2}:\d{2}(:\d{2})?\b/g, 'HH:MM')
    .replace(/\?v=\d+/g, '?v=V');
window.__wiSnapTake = async (label, urls) => {
    const out = {};
    for (const url of urls) {
        const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
        out[url] = res.status + ' ' + res.url + '\n' + window.__wiSnapNorm(await res.text());
    }
    window.__wiSnap[label] = out;
    return Object.entries(out).map(([url, html]) => url + ' -> ' + html.split('\n', 1)[0] + ' (' + html.length + ')');
};
window.__wiSnapDiff = (a = 'base', b = 'mod', limit = 20) => {
    const count = html => {
        const map = new Map();
        for (const line of html.split('\n').map(l => l.trim().slice(0, 300)).filter(Boolean)) map.set(line, (map.get(line) ?? 0) + 1);
        return map;
    };
    const only = (x, y) => [...x].flatMap(([line, n]) => Array(Math.max(0, n - (y.get(line) ?? 0))).fill(line));
    const out = {};
    for (const url of Object.keys(window.__wiSnap[a] ?? {})) {
        const before = count(window.__wiSnap[a][url]);
        const after = count(window.__wiSnap[b]?.[url] ?? '');
        const lines = [...only(before, after).map(l => '- ' + l), ...only(after, before).map(l => '+ ' + l)];
        if (lines.length) out[url] = lines.length > limit ? [...lines.slice(0, limit), '... altre ' + (lines.length - limit)] : lines;
    }
    return out;
};
JS
cat > "$W/style.js" <<'JS'
window.__wiStyle ??= {};
window.__wiStyleProps = ['display', 'visibility', 'position', 'color', 'background-color', 'border-top-color', 'border-top-width', 'border-top-left-radius', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'margin-top', 'margin-bottom', 'font-size', 'font-weight', 'line-height', 'opacity', 'box-shadow', 'min-height', 'scroll-padding-top', 'scroll-padding-bottom'];
window.__wiStyleTake = (label, url, width = 1280, height = 900, wait = 2000) => new Promise((resolve, reject) => {
    const frame = document.createElement('iframe');
    frame.style.cssText = `position:fixed;left:0;top:0;width:${width}px;height:${height}px;border:0;opacity:0;pointer-events:none`;
    frame.onload = () => setTimeout(() => {
        try {
            const doc = frame.contentDocument;
            const view = frame.contentWindow;
            const elements = [doc.documentElement, ...doc.body.querySelectorAll('*')]
                .filter(el => !el.closest('script, style, template, noscript, .wi-save-bar')
                    && !(el.matches('[role="status"]') && el.previousElementSibling?.matches('.wi-save-bar')));
            const content = doc.querySelector('#content');
            const snap = {
                url: view.location.href,
                page: {
                    elements: elements.length,
                    scroll: doc.scrollingElement.scrollHeight - view.innerHeight,
                    content: content ? Math.round(content.getBoundingClientRect().height) : null,
                    island: !!doc.querySelector('.wi-save-bar'),
                    on: doc.documentElement.classList.contains('wi-save-bar-on'),
                    theme: doc.documentElement.getAttribute('data-bs-theme'),
                },
                styles: elements.map((el, i) => {
                    const css = view.getComputedStyle(el);
                    const rect = el.getBoundingClientRect();
                    const name = el.tagName.toLowerCase() + [...el.classList].map(c => '.' + c).join('');
                    return [i + ' ' + name, window.__wiStyleProps.map(p => css.getPropertyValue(p)).concat(Math.round(rect.width), Math.round(rect.height))];
                }),
            };
            window.__wiStyle[label] = snap;
            resolve(label + ': ' + JSON.stringify(snap.page) + ' ' + snap.url);
        } catch (error) {
            reject(error);
        } finally {
            frame.remove();
        }
    }, wait);
    frame.src = url;
    document.body.append(frame);
});
window.__wiStyleDiff = (a, b, limit = 30) => {
    const x = window.__wiStyle[a];
    const y = window.__wiStyle[b];
    const page = Object.keys(x.page).filter(k => x.page[k] !== y.page[k]).map(k => `${k}: ${x.page[k]} -> ${y.page[k]}`);
    if (x.page.elements !== y.page.elements) {
        return { page, styles: ['markup diverso: confronto per elemento non possibile'] };
    }
    const keys = window.__wiStyleProps.concat('width', 'height');
    const styles = [];
    x.styles.forEach(([name, values], i) => {
        const other = y.styles[i][1];
        keys.forEach((key, k) => {
            if (values[k] !== other[k]) styles.push(`${name} ${key}: ${values[k]} -> ${other[k]}`);
        });
    });
    return { page, styles: styles.slice(0, limit), total: styles.length };
};
JS
cat > "$W/stato.js" <<'JS'
// Stato visibile della barra di salvataggio: si rilegge dopo ogni navigazione.
window.__wiShown = el => !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility === 'visible';
window.__wiConfirmStub = (answer = true) => {
    window.__wiConfirms = [];
    window.confirm = text => {
        window.__wiConfirms.push(String(text));
        return answer;
    };
    return 'confirm() finto, risponde ' + answer;
};
window.__wiBar = async (wait = 400) => {
    await new Promise(resolve => setTimeout(resolve, wait));
    const shown = window.__wiShown;
    const text = el => el ? el.textContent.replace(/\s+/g, ' ').trim() : '';
    const name = b => (text(b) || b.value) + (b.disabled || b.classList.contains('disabled') ? ' (disabilitato)' : '');
    const bar = document.querySelector('.wi-save-bar');
    const status = bar?.nextElementSibling?.matches('[role="status"]') ? bar.nextElementSibling : null;
    const forms = [...document.querySelectorAll('form[data-wi-save-bar]')];
    const label = bar?.querySelector('.wi-save-bar-label');
    return {
        forms: forms.map(f => ({
            id: f.id,
            action: f.getAttribute('action'),
            dirtyAttr: f.hasAttribute('data-wi-save-bar-dirty'),
            dirty: window.wiSaveBar?.isDirty(f) ?? null,
        })),
        island: shown(bar?.querySelector('.wi-save-bar-island')),
        label: shown(label) ? text(label) : '',
        copies: [...(bar?.querySelectorAll('.wi-save-bar-btn') ?? [])].filter(shown).map(name),
        buttons: forms.flatMap(f => [...f.elements]).filter(b => b.type === 'submit' && shown(b)).map(name),
        status: text(status),
        confirms: window.__wiConfirms ?? [],
    };
};
JS
cat > "$W/casi.js" <<'JS'
// Helper dei casi di comportamento (Task R4-R6): stato della barra, invii, avviso di uscita, altezza della finestra.
window.__wiState = () => document.querySelector('.wi-save-bar')?.dataset.wiState ?? null;
// Lo stato si ricalcola a ogni fotogramma: col pannello nascosto passa fino a un secondo.
window.__wiUntil = async (state, timeout = 3000) => {
    const end = performance.now() + timeout;
    while (window.__wiState() !== state && performance.now() < end) await new Promise(resolve => setTimeout(resolve, 50));
    return window.__wiState();
};
window.__wiLogSubmits = () => {
    if (window.__wiLogging) return 'registro invii gia attivo';
    window.__wiLogging = true;
    document.addEventListener('submit', event => {
        const list = JSON.parse(sessionStorage.getItem('wiSubmits') || '[]');
        list.push((event.submitter?.name ?? '') + '=' + (event.submitter?.value ?? ''));
        sessionStorage.setItem('wiSubmits', JSON.stringify(list));
    }, true);
    return 'registro invii attivo';
};
window.__wiSubmits = () => {
    const list = JSON.parse(sessionStorage.getItem('wiSubmits') || '[]');
    sessionStorage.removeItem('wiSubmits');
    return list;
};
// Avviso di uscita: preventPageExit annulla anche un beforeunload sintetico (B31).
window.__wiLeave = () => {
    const event = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(event);
    return event.defaultPrevented;
};
window.__wiHeightFor = (selector = 'form[data-wi-save-bar] [type="submit"], form[data-wi-save-bar] .wi-submit') => {
    window.scrollTo(0, 0);
    const bottoms = [...document.querySelectorAll(selector)]
        .filter(el => el.getClientRects().length > 0)
        .map(el => el.getBoundingClientRect().bottom);
    return bottoms.length ? Math.round(Math.max(...bottoms) + 60) : null;
};
JS
for f in snap style stato casi; do node --check "$W/$f.js" && echo "$f.js ok"; done
```

Expected: `snap.js ok`, `style.js ok`, `stato.js ok` e `casi.js ok`.

- [ ] **Step 4: App di base**

La base di confronto è il punto da cui è partito il branch dell'app, non `main` di oggi: se nel frattempo `main` è andato avanti, le differenze di I1 includerebbero lavoro estraneo. Il worktree è staccato e senza `vendor/`, perché nei siti l'autoload è quello del sito. Niente `git stash`: la checkout principale dell'app contiene worktree annidati e cartelle non tracciate.

```bash
APP_WT=/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
W="$HOME/.cache/wonder-tooling/save-bar"
git -C /Users/andreamarinoni/Developer/packages/app worktree add --detach "$W/app-base" "$(git -C "$APP_WT" merge-base HEAD main)"
git -C "$W/app-base" log --oneline -1
grep -c data-wi-save-bar "$W/app-base/app/view/pages/backend/resource/form.php" "$APP_WT/app/view/pages/backend/resource/form.php"
```

Expected: `HEAD is now at <hash> ...`, lo stesso commit nel log, poi `.../app-base/app/view/pages/backend/resource/form.php:0` e `.../backend-save-bar/app/view/pages/backend/resource/form.php:1`.

- [ ] **Step 5: Lib di base, costruita fuori dal repo**

La `dist` di confronto si costruisce con lo stesso comando della nuova, così le differenze vengono solo dal codice. La `dist/` tracciata della lib non si usa e non si tocca.

```bash
LIB_WT=/Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
W="$HOME/.cache/wonder-tooling/save-bar"
git -C /Users/andreamarinoni/Developer/packages/lib worktree add --detach "$W/lib-base" "$(git -C "$LIB_WT" merge-base HEAD main)"
cd "$W/lib-base"
npm ci --no-audit --no-fund
npx webpack --output-path "$W/lib-base-dist" 2>&1 | tail -1
grep -c wiSaveBar "$W/lib-base-dist/backend/head.js"
git -C "$W/lib-base" status --porcelain | wc -l
```

Expected: `HEAD is now at <hash> ...`, `npm ci` senza errori, una riga con `compiled`, poi `0` e `0`. Oggi la build termina con `compiled with 3 warnings`: gli avvisi sono gli stessi di `main` e non contano. `grep -c` stampa `0` ed esce con codice 1: è l'esito atteso.

- [ ] **Step 6: Lib nuova, costruita fuori dal repo**

```bash
LIB_WT=/Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
W="$HOME/.cache/wonder-tooling/save-bar"
cd "$LIB_WT"
npx webpack --output-path "$W/lib-dist" 2>&1 | tail -1
grep -c wiSaveBar "$W/lib-dist/backend/head.js"
grep -c 'wi-save-bar' "$W/lib-dist/backend/head.css"
git -C "$LIB_WT" status --porcelain | wc -l
git -C "$LIB_WT" diff --stat main...HEAD -- dist | wc -l
find "$W/lib-dist" -type f | wc -l
find /Users/andreamarinoni/Developer/boilerplates/new-site/assets/lib/wonder-image/dist -type f | wc -l
```

Expected:
- una riga con `compiled`;
- almeno `1` e almeno `1` (la build è minificata, quindi spesso su una sola riga);
- `0` e `0`: la build non ha scritto nel worktree e il ramo non tocca `dist/`;
- due conteggi di file; oggi `683` per la build e `412` per la `dist` del sito. La differenza non conta: la copia della `dist` nel sito sostituisce la cartella intera e il ripristino la rimette identica.

- [ ] **Step 7: Prepara i tre siti**

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
"$W/siti.sh" prepara new-site
"$W/siti.sh" prepara ecommerce-site
"$W/siti.sh" prepara immobili-site immobili=/Users/andreamarinoni/Developer/packages/immobili/.claude/worktrees/backend-save-bar APP_URL=https://immobili.test
ls "$W"/*-before.txt
```

Expected, oltre all'output di `herd restart`:
- new-site: `new-site dist nuova, wiSaveBar in head.js: N` con N almeno 1, poi `app /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar`;
- ecommerce-site: la stessa riga della `dist`, poi la riga `app` e `gestionale ../../../../packages/gestionale/`;
- immobili-site: `<n>:APP_URL=https://immobili.test`, la riga della `dist`, la riga `app` e `immobili /Users/andreamarinoni/Developer/packages/immobili/.claude/worktrees/backend-save-bar`;
- sei file `*-status-before.txt` e `*-links-before.txt`.

immobili-site ha `APP_URL=https://immobili.site`, e ogni pagina di immobili.test farebbe redirect a quel dominio, che non esiste in locale. `siti.sh` cambia solo quella riga e salva `.env` intero nel backup, senza stamparlo. Se un comando si ferma con `backup gia presente`, un'esecuzione precedente è a metà: fermati e chiedi all'utente.

- [ ] **Step 8: Resource di prova su new-site**

La Resource usa il modello Announcement, ma non scrive mai nel DB. È una pagina-form (`isFormPage()`) con anche la creazione, quindi ha due pagine:
- `/backend/save-bar-probe/create/` invia a `store`, che chiama `mutateRequestValues()`: l'eccezione ferma il salvataggio e la pagina si rende di nuovo con l'errore e `data-wi-save-bar-dirty` (il fallimento di I5);
- `/backend/save-bar-probe/` invia a `submit`, che chiama `submitFormPage()`: nella Resource base non fa niente, quindi segue l'avviso 650 e il redirect al form pulito (il successo di I5).

Il parametro `mode` sceglie il caso sulla pagina `/backend/save-bar-probe/?mode=<mode>`. L'azione del form non ha la query: il POST arriva a `submit` senza `mode`, cioè a una pagina non in sola lettura, e va a buon fine. Nelle modalità in sola lettura la Resource non registra `create`, quindi `/backend/save-bar-probe/create/?mode=locked` dà 404.

La modalità `widgets` aggiunge i widget dei casi I11 e I12 con valori iniziali fissi. Il POST della pagina arriva a `submit` senza `mode`, quindi solo con i tre campi di base: avviso 650, nessuna scrittura. La DynamicCheck cerca sullo stesso file: una richiesta con `mode=widgets` e `post=true` riceve una lista finta (Uno, Due, Tre) e si ferma lì. Quel codice parte al caricamento del file, perché `ResourceRegistry` carica le Resource del sito all'avvio, prima del routing e del login.

| `mode` | Pagina |
|---|---|
| vuoto | layout con Card, tracciato |
| `legacy` | senza layout, ramo legacy di `resource/form.php` |
| `locked` | sola lettura senza campi modificabili |
| `partial` | sola lettura con `text` modificabile |
| `submit` | come `partial`, con un Submit `upload` nel layout |
| `hidden` | un Submit in una Card con `visibleWhen('kind', 'b')` |
| `scheduler`, `scheduler-locked` | la vista di ScheduleResource, modificabile in parte o bloccata |
| `widgets` | modificabile, con repeater, Quill, EditorJS, Select2, datepicker, telefono, CheckTree, DynamicCheck, campo Google Places (gli attributi del `googleAddress()` del backend) e generatore di codici |

```bash
PROBE=/Users/andreamarinoni/Developer/boilerplates/new-site/app/Resources/SaveBarProbe
mkdir -p "$PROBE"
cat > "$PROBE/SaveBarProbeResource.php" <<'PHP'
<?php

namespace App\Resources\SaveBarProbe;

use Wonder\App\Models\Communications\Announcement;
use Wonder\App\Resource;
use Wonder\App\Resources\Scheduler\ScheduleResource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Components\Submit;
use Wonder\Elements\Form\Form;

// Resource temporanea per i casi I1, I3-I6, I11 e I12: non si committa, si cancella al ripristino.
final class SaveBarProbeResource extends Resource
{
    private const PIXEL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    public static string $model = Announcement::class;
    public static string $path = 'save-bar-probe';

    private static function mode(): string
    {
        return (string) ($_GET['mode'] ?? '');
    }

    public static function icon(): string
    {
        return 'bi-bug';
    }

    public static function isFormPage(): bool
    {
        return true;
    }

    public static function isReadonly(): bool
    {
        return !in_array(self::mode(), ['', 'hidden', 'legacy', 'widgets'], true);
    }

    public static function editableWhenReadonly(): array
    {
        return str_ends_with(self::mode(), 'locked') ? [] : ['text'];
    }

    public static function formSchema(): array
    {
        $fields = [
            FormField::key('name')->text()->label('Nome')->value('Prova'),
            FormField::key('text')->textarea()->label('Testo'),
            FormField::key('kind')->select(['a' => 'A', 'b' => 'B'])->label('Tipo')->value('a'),
        ];

        if (self::mode() !== 'widgets') {
            return $fields;
        }

        $blocks = ['blocks' => [
            ['type' => 'paragraph', 'data' => ['text' => 'Paragrafo']],
            ['type' => 'table', 'data' => ['withHeadings' => false, 'content' => [['Cella A1', 'Cella B1'], ['Cella A2', 'Cella B2']]]],
            ['type' => 'image', 'data' => ['file' => ['url' => self::PIXEL], 'caption' => '', 'withBorder' => false, 'stretched' => false, 'withBackground' => false]],
        ]];

        return array_merge($fields, [
            FormField::key('rows')->repeater([
                RepeaterColumn::key('rkind')->select(['a' => 'A', 'b' => 'B'])->label('Tipo riga')->required(),
                RepeaterColumn::key('rtag')->selectSearch(['x' => 'X', 'y' => 'Y'])->label('Etichetta'),
                RepeaterColumn::key('rprice')->price()->label('Prezzo'),
                RepeaterColumn::key('rnote')->text()->label('Nota')->visibleWhen('rkind', 'b'),
                RepeaterColumn::key('rimage')->fileDragDrop('image')->label('Immagine'),
            ])->label('Righe')->value([
                ['rkind' => 'a', 'rtag' => 'x', 'rprice' => '10'],
                ['rkind' => 'b', 'rtag' => 'y', 'rprice' => '1234.5', 'rnote' => 'Nota'],
            ]),
            FormField::key('quill')->textarea('plus')->label('Quill')->value('<p>Testo</p>'),
            FormField::key('quill_required')->textarea('plus')->label('Quill obbligatorio')->required()->value('<p>Testo</p>'),
            FormField::key('quill_empty')->textarea('plus')->label('Quill vuoto dal DB')->value('<p><br></p>'),
            FormField::key('editor')->textarea('blog')->label('EditorJS')->value(json_encode($blocks)),
            FormField::key('tags')->selectSearch(['x' => 'X', 'y' => 'Y', 'z' => 'Z'], true)->label('Etichette')->value(['x']),
            FormField::key('day')->dateInput()->label('Giorno')->value('2026-09-27'),
            FormField::key('phone')->phone()->label('Telefono')->value('+39 333 123 4567'),
            FormField::key('tree')->checkTree(['1' => ['name' => 'Uno', 'child' => ['11' => 'Uno.1', '12' => 'Uno.2']], '2' => 'Due'], true)->label('Albero')->value(['11']),
            FormField::key('dyn')->dynamicCheck('/backend/save-bar-probe/create/?mode=widgets')->label('Ricerca')->value(['1', '3']),
            // Attributi del googleAddress() del backend: FormField::googleAddress() non ha un renderer Bootstrap.
            FormField::key('address')->text()->label('Indirizzo')->disabled()->attribute('data-wi-search-place="true" data-wi-callback="__wiPlaceProbe"'),
            FormField::key('code')->textGenerator()->label('Codice'),
        ]);
    }

    public static function formLayoutSchema(): ?Form
    {
        $mode = self::mode();
        if ($mode === 'legacy') {
            return null;
        }

        $cards = [(new Card)->components([
            static::getInput('name')->columnSpan(12),
            static::getInput('text')->columnSpan(12),
            static::getInput('kind')->columnSpan(12),
        ])->columns(12)->columnSpan(12)];

        if ($mode === 'submit' || str_starts_with($mode, 'scheduler')) {
            $cards[] = (new Card)->components([new Submit('upload')])->columnSpan(12);
        }

        if ($mode === 'hidden') {
            $cards[] = (new Card)->visibleWhen('kind', 'b')->components([new Submit('upload')])->columnSpan(12);
        }

        if ($mode === 'widgets') {
            $cards[] = (new Card)->components(array_map(
                fn (string $key) => static::getInput($key)->columnSpan(12),
                ['rows', 'quill', 'quill_required', 'quill_empty', 'editor', 'tags', 'day', 'phone', 'tree', 'dyn', 'address', 'code']
            ))->columns(12)->columnSpan(12);
        }

        return (new Form)->components($cards)->columns(12);
    }

    public static function pageSchema(): PageSchema
    {
        $page = PageSchema::for(static::class)->only(['create', 'store']);

        if (str_starts_with(self::mode(), 'scheduler')) {
            $page->view('form', ScheduleResource::pageSchema()->get('views')['form']);
        }

        return $page;
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)->enabled(false);
    }

    public static function mutateRequestValues(array $values, string $action, string $context = 'backend', ?array $oldValues = null): array
    {
        throw new \InvalidArgumentException('Errore di prova della barra di salvataggio.');
    }
}

// Risposta finta della DynamicCheck: parte al caricamento del file, prima del routing e del login.
if (($_GET['mode'] ?? '') === 'widgets' && ($_POST['post'] ?? '') === 'true') {
    $ids = json_decode((string) ($_POST['id'] ?? ''), true);
    $search = (string) ($_POST['search'] ?? '');
    $items = [];
    foreach (['1' => 'Uno', '2' => 'Due', '3' => 'Tre'] as $value => $label) {
        if (is_array($ids) ? in_array((string) $value, array_map('strval', $ids), true) : stripos($label, $search) !== false) {
            $items[] = ['value' => (string) $value, 'label' => $label, 'input-value' => $search];
        }
    }
    echo json_encode($items);
    exit;
}
PHP
php -l "$PROBE/SaveBarProbeResource.php"
"/Users/andreamarinoni/Library/Application Support/Herd/bin/php82" -l "$PROBE/SaveBarProbeResource.php"
```

Expected: due volte `No syntax errors detected in .../SaveBarProbeResource.php`. La Resource non si committa: `siti.sh ripristina new-site` cancella la cartella.

- [ ] **Step 9: I siti caricano il codice dei worktree**

```bash
SITES=/Users/andreamarinoni/Developer/boilerplates
for s in new-site ecommerce-site immobili-site; do
  (cd "$SITES/$s" && php -r 'require "vendor/autoload.php"; echo (new ReflectionClass(Wonder\Backend\Support\ResourceFormLayoutRenderer::class))->getFileName(), "\n";')
done
(cd "$SITES/immobili-site" && php -r 'require "vendor/autoload.php"; echo (new ReflectionClass(Wonder\Plugin\Immobili\Immobili::class))->getFileName(), "\n";')
```

Expected: tre volte `/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar/class/Backend/Support/ResourceFormLayoutRenderer.php`, poi `/Users/andreamarinoni/Developer/packages/immobili/.claude/worktrees/backend-save-bar/src/Immobili.php`. PHP risolve i symlink, quindi compare il percorso reale del worktree; un percorso sotto `vendor/` vuol dire che la sostituzione non è avvenuta.

- [ ] **Step 10: Controllo HTTP senza sessione**

```bash
for h in new.test ecommerce.test immobili.test; do
  printf '%s login %s\n' "$h" "$(curl -sk -o /dev/null -w '%{http_code}' "https://$h/backend/account/login/")"
  js=$(curl -sk "https://$h/backend/account/login/" | grep -o "https://$h/assets/lib/wonder-image/dist/backend/head\.js?v=[0-9]*" | head -1)
  printf '%s head.js %s wiSaveBar %s\n' "$h" "${js##*\?}" "$(curl -sk "$js" | grep -c wiSaveBar)"
done
curl -sk -o /dev/null -w '%{http_code} %{redirect_url}\n' https://new.test/backend/save-bar-probe/create/
```

Expected:
- tre righe `<host> login 200`;
- tre righe `<host> head.js v=<numero> wiSaveBar N`, con N almeno 1;
- `302 https://new.test/backend/account/login/?redirect=...`: la route della Resource di prova esiste e chiede il login. Il valore di `redirect` non si controlla.

Con `404` la Resource non è stata trovata: controlla il percorso dello Step 8. Con un redirect verso `immobili.site`, la riga `APP_URL` non è cambiata.

- [ ] **Step 11: Login dell'utente e controllo delle pagine**

Chiedi all'utente di fare il login nel pannello Browser su `https://new.test/backend/`, `https://ecommerce.test/backend/` e `https://immobili.test/backend/`, con un amministratore locale. Claude non scrive le password. La sessione dura 4 ore: se una pagina torna al login durante i casi, chiedi di nuovo il login e riprendi dal passo interrotto.

Su ogni host, dalla pagina `/backend/`, incolla `snap.js` e poi esegui con `javascript_tool`:

```js
await __wiSnapTake('smoke', window.__wiUrls = [
  '/backend/',
  '/backend/announcements/',
  '/backend/announcements/create/',
  '/backend/save-bar-probe/create/',
  '/backend/app/config/user/create/',
  '/backend/app/config/api-users/create/',
  '/backend/account/',
  '/backend/app/config/configuration-file/',
  '/backend/app/config/credentials/1/edit/',
  '/backend/app/scheduler/schedules/create/',
  '/backend/app/config/locations/',
])
```

Questo è l'elenco di new.test. Su ecommerce.test usa `['/backend/', '/backend/app/gestionale/prodotti/', '/backend/app/gestionale/prodotti/create/', '/backend/app/gestionale/attributi/', '/backend/app/gestionale/versioni/']`. Su immobili.test usa `['/backend/', '/backend/immobili/', '/backend/immobili/create/', '/backend/residenze/', '/backend/residenze/create/']`. Su immobili.test non si aprono le pagine dello scheduler: in quel sito manca `dragonmantank/cron-expression`, che serve solo allo scheduler.

Poi, sullo stesso host:

```js
Object.entries(window.__wiSnap.smoke).map(([url, html]) => url + ' ' + (html.match(/Fatal error|Uncaught|SQLSTATE|Warning:|Deprecated:|Notice:/g) ?? []).join(','))
```

Expected:
- ogni riga del primo comando è `<url> -> 200 https://<host><url> (<lunghezza>)`, con lo stesso URL: nessun redirect al login;
- ogni riga del secondo comando è solo l'URL, senza parole dopo.

Se una pagina dà un errore PHP o un codice diverso da 200, fermati. Annota il caso come `BLOCCATO` nello Step 12, con la pagina e il messaggio, e chiedi all'utente se l'errore esiste anche con `siti.sh app <sito> originale`. Un errore che compare solo con l'app nuova è una regressione da correggere nella Parte P prima di continuare.

- [ ] **Step 12: Registro degli esiti e regole comuni dei casi**

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
[ -e "$W/integrazione-esiti.txt" ] || printf 'caso | host | esito | nota\n' > "$W/integrazione-esiti.txt"
[ -e "$W/record-di-prova.txt" ] || printf 'host | pagina | record | creato nel caso\n' > "$W/record-di-prova.txt"
cat "$W/integrazione-esiti.txt" "$W/record-di-prova.txt"
```

Expected: le due righe di intestazione.

Ogni caso aggiunge una riga a `integrazione-esiti.txt` per host, con uno di questi esiti:
- `OK`;
- `FALLITO`, con cosa si è visto;
- `BLOCCATO`, con cosa manca;
- `NON APPLICABILE`, con il motivo;
- `NON VERIFICATO`, con il motivo (solo per le prove manuali e per i controlli touch).

Ogni record creato durante i casi va in `record-di-prova.txt`. Claude non cancella record: alla fine l'elenco si consegna all'utente (Task R8).

Regole comuni dei Task R3-R6:
- **Helper:** dopo ogni `navigate`, ricarica o invio di form si reincollano gli helper che servono.
- **Stato della barra:**
  - l'isola ricalcola lo stato a ogni fotogramma, e con il pannello Browser nascosto (`tabs_context` dice `hidden`) un fotogramma arriva anche dopo un secondo;
  - quando il caso aspetta uno stato, si esegue prima `await __wiUntil('<stato>')`, che deve restituire lo stesso stato, e poi `await __wiBar()`;
  - quando il caso aspetta che qualcosa non cambi (isola nascosta, form pulito), si usa `await __wiBar(1500)`;
  - gli stati sono `hidden`, `buttons`, `dirty-buttons`, `dirty-label` e `submitting`. Con `buttons` l'isola mostra le copie anche con un form pulito: succede quando i Salva della pagina sono sotto la fascia.
- **Console:**
  - dopo ogni caso si leggono gli errori e gli avvisi con `read_console_messages` (`onlyErrors: false`);
  - se ci sono errori o avvisi, si rifà la stessa pagina con `siti.sh app <sito> base` e `siti.sh dist <sito> base`, poi si torna a `nuova` per entrambe;
  - il caso fallisce solo per i messaggi che compaiono con le versioni nuove e non con quelle di base.
- **`confirm()`:** prima di un'azione che può chiedere conferma si esegue `__wiConfirmStub(true)`, oppure `__wiConfirmStub(false)` dove il caso lo dice. I testi chiesti finiscono in `__wiBar().confirms`.
- **Avviso di uscita:**
  1. un clic con `computer` su un'area neutra della pagina, per esempio il titolo;
  2. `__wiLeave()` restituisce `true` con modifiche non salvate e `false` senza;
  3. con `true`, `navigate` verso `/backend/` senza `force` può mostrare il dialogo "Leave site?": se compare, si ripete con `force: true`. L'esito del caso si basa su `__wiLeave()`, perché il pannello nascosto può navigare senza mostrare il dialogo.
- **Finestra alta quanto serve:** alcuni casi vogliono i Salva della pagina sotto la fascia, cioè l'isola con le copie:
  1. `resize_window` a 1280x900, perché prima del primo ridimensionamento la finestra del pannello misura 0x0;
  2. `__wiHeightFor()` restituisce l'altezza `h`;
  3. `resize_window` a 1280 x `h`.

  Il pannello annulla il ridimensionamento a fine turno: se il caso continua nel turno dopo, si ripete dal punto 1. A fine caso si torna con `resize_window` e preset `desktop`.
- **Invii:** prima di cliccare una copia si esegue `__wiLogSubmits()`. Dopo la navigazione si reincolla `casi.js` e si legge `__wiSubmits()`: l'elemento è `<name>=<value>` del pulsante che ha inviato il form.
- **Copie dell'isola:** `read_page` con `filter: interactive` elenca ogni copia come `button "<testo>"` con `type="button"`, dopo tutti i pulsanti della pagina, perché l'isola è in fondo al `body`. Si clicca con `computer`, `left_click` e il `ref` della copia.
- **Tema scuro:** si esegue `localStorage.setItem('theme', 'dark')` e si ricarica. Alla fine del caso si esegue `localStorage.setItem('theme', 'light')` e si ricarica.
- **Touch:** si esegue `matchMedia('(pointer: coarse)').matches`. Se vale `false`, la parte touch del caso è `NON VERIFICATO` e passa a M2.
- **Password:**
  - Claude non scrive mai una password vera;
  - dove serve una password sbagliata, Claude scrive `sbagliata-save-bar`;
  - dove serve quella giusta, la scrive l'utente nel pannello Browser.
- **Record veri:** se un caso modifica un record esistente, prima si annota il valore e alla fine lo si rimette e si salva.
- **App e lib:** si lavora con app e `dist` nuove, salvo dove il caso dice altro. Chi cambia l'una o l'altra la rimette a `nuova` alla fine del caso.

### Task R3: casi I1, I10 e I17 (markup)

Repo: nessun commit. Si lavora nel pannello Browser e in `$HOME/.cache/wonder-tooling/save-bar` (`W`), con i siti preparati nel Task R2.

I tre casi confrontano l'HTML reso dal server, non il comportamento dell'isola:
- **caso I1:** differenze tra app di base e app nuova su pagine vere;
- **caso I10:** i campi segreti di SecurityResource;
- **caso I17:** le pagine escluse, che non devono avere `data-wi-save-bar`.

L'isola non compare in questi confronti: la crea la lib nel browser, mentre gli snapshot scaricano l'HTML con `fetch`.

**Files:**
- Create: `$W/markup.js` (helper per `javascript_tool`)
- Modify: `$W/integrazione-esiti.txt` (esiti dei casi I1, I10 e I17)

**Interfaces:**
- Consumes: dal Task R2 `siti.sh app <sito> base|nuova`, `snap.js` (`__wiSnapTake`, `__wiSnapDiff`), `stato.js` (`__wiBar`), `casi.js` (`__wiState`), la sessione nel pannello Browser sui tre host, le regole comuni dello Step 12
- Produces:
  - `__wiI1(a, b)`, che classifica le differenze di I1;
  - `__wiEditLinks(limit)`, che legge i link di modifica di una lista;
  - `__wiForms(urls)` e `__wiLiveForms()`, che contano i form tracciati;
  - le righe I1, I10 e I17 del registro.

- [ ] **Step 1: Helper dei casi di markup**

`markup.js` contiene quattro helper.

`__wiI1(a, b)` lavora sulle raccolte di `__wiSnapTake`. Per ogni URL:
- scarta come rumore le righe che cambiano tra due prese della stessa app, cioè tra `a` e `a2` e tra `b` e `b2`;
- confronta le righe rimaste e riconosce le differenze attese una per una;
- elenca tutto il resto come `INATTESE`, mostrando il punto della riga dove cambia.

Le righe di un layout Resource stanno tutte su una riga sola: per questo una riga modificata si confronta token per token. Sono ammessi solo gli attributi aggiunti e il cambio di `onsubmit` dello scheduler.

| Nome nel risultato | Differenza riconosciuta | Da dove viene |
|---|---|---|
| `header` | `min-height` di `header.php` con `- var(--wi-save-bar-reserve, 0px)` | Task P8 |
| `attributo` | `data-wi-save-bar` aggiunto a un tag | Task P3, P4, P5 e R1 |
| `ignore` | `data-wi-save-bar-ignore` aggiunto a un tag | Task P5, password di conferma dell'account |
| `autocomplete` | `autocomplete="new-password"` aggiunto a un tag | Task P6 |
| `scheduler` | `onsubmit="return false"` diventa `onsubmit="loadingSpinner()"` | Task P3 (Q18) |
| `footer` | il blocco di 5 righe del Salva di default tolto | Task P2 e P3 (`hasSubmit()`) |
| `repeater` | le righe di `wiRepeaterRemoveRow` e `wiRepeaterSetFieldValue`, una volta per ogni Repeater della pagina | Task P7 |

Dopo le differenze, il risultato ha tre campi facoltativi:
- `ripetitori=N`: i contenitori Repeater nella pagina nuova, da confrontare con `repeater=N`;
- `rumore=N`: le righe scartate come rumore;
- `INATTESE: [...]`: le differenze non riconosciute, al massimo 10.

Gli altri tre helper:
- `__wiEditLinks(limit)`: aspetta al massimo 5 s le righe di una lista DataTables e restituisce i primi `limit` percorsi `/edit/`;
- `__wiForms(urls)`: scarica le pagine con la sessione e conta i form e quelli con `data-wi-save-bar`;
- `__wiLiveForms()`: fa lo stesso conteggio sulla pagina aperta, comprese le righe aggiunte da JavaScript.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
cat > "$W/markup.js" <<'JS'
// Caso I1: confronta due raccolte di __wiSnapTake e riconosce una per una le differenze attese.
// Le righe che cambiano tra due prese della stessa app (a e a + '2', b e b + '2') sono rumore e si scartano.
window.__wiI1 = (a = 'base', b = 'mod') => {
    const header = [
        '<div class="w-100" style="min-height: calc(100vh - (50px + 22.5px + 1rem + 20px));">',
        '<div class="w-100" style="min-height: calc(100vh - (50px + 22.5px + 1rem + 20px) - var(--wi-save-bar-reserve, 0px));">',
    ];
    const footer = [
        '<div class="col-12">',
        '<wi-card class="col-12">',
        '<div class="col-12"><button type="submit" id="field_ID" name="upload" class="float-end btn btn-dark wi-submit" disabled>Salva</button></div>',
        '</wi-card>',
        '</div>',
    ];
    const repeaterOld = ['return;', "input.value = '';"];
    const repeaterNew = [
        "input.dispatchEvent(new Event('change', { bubbles: true }));",
        '} else {',
        "window.wiRepeaterSetFieldValue(input, '');",
        "field.dispatchEvent(new Event('change', { bubbles: true }));",
    ];
    const inserted = {
        'data-wi-save-bar': 'attributo',
        'data-wi-save-bar-ignore': 'ignore',
        'autocomplete="new-password"': 'autocomplete',
    };
    const lines = html => html.split('\n').map(l => l.trim()).filter(Boolean);
    const count = list => list.reduce((map, l) => map.set(l, (map.get(l) ?? 0) + 1), new Map());
    const minus = (x, y) => new Map([...x].map(([l, n]) => [l, n - (y.get(l) ?? 0)]).filter(([, n]) => n > 0));
    const has = (map, list) => list.every(l => (map.get(l) ?? 0) >= list.filter(k => k === l).length);
    const take = (map, l) => (map.get(l) ?? 0) > 1 ? map.set(l, map.get(l) - 1) : map.delete(l);
    const lcp = (x, y) => { let i = 0; while (i < x.length && x[i] === y[i]) i++; return i; };
    const clip = (l, others) => {
        const start = Math.max(0, Math.max(0, ...others.map(o => lcp(l, o))) - 60);
        return (start ? '...' : '') + l.slice(start, start + 200);
    };
    const tokens = l => l.split(/(?<!\s)(?=\s)|(?=>)/).map(t => t.trim()).filter(Boolean);
    // Allinea le due righe token per token: ammette solo attributi aggiunti e l'onsubmit dello scheduler.
    const walk = (old, now) => {
        const x = tokens(old), y = tokens(now), found = [];
        let i = 0, j = 0;
        while (i < x.length || j < y.length) {
            if (i < x.length && j < y.length && x[i] === y[j]) { i++; j++; continue; }
            if (j < y.length && inserted[y[j]]) { found.push(inserted[y[j]]); j++; continue; }
            if (x[i] === 'onsubmit="return' && x[i + 1] === 'false"' && y[j] === 'onsubmit="loadingSpinner()"') { found.push('scheduler'); i += 2; j++; continue; }
            return null;
        }
        return found;
    };
    const out = {};
    for (const url of Object.keys(window.__wiSnap[a])) {
        const snap = (label, fallback) => count(lines(window.__wiSnap[label]?.[url] ?? fallback));
        const base = snap(a, ''), mod = snap(b, '');
        const base2 = snap(a + '2', window.__wiSnap[a][url]), mod2 = snap(b + '2', window.__wiSnap[b]?.[url] ?? '');
        const noisy = new Set([base, mod].flatMap((x, k) => { const y = [base2, mod2][k]; return [...minus(x, y).keys(), ...minus(y, x).keys()]; }));
        for (const l of noisy) { base.delete(l); mod.delete(l); }
        const gone = minus(base, mod);
        const added = minus(mod, base);
        const rules = {};
        const hit = name => { rules[name] = (rules[name] ?? 0) + 1; };
        while (gone.has(header[0]) && added.has(header[1])) { take(gone, header[0]); take(added, header[1]); hit('header'); }
        for (const [now, n] of [...added]) {
            for (let k = 0; k < n; k++) {
                const old = [...gone.keys()].find(o => walk(o, now)?.length);
                if (old === undefined) break;
                walk(old, now).forEach(hit);
                take(gone, old);
                take(added, now);
            }
        }
        while (has(gone, footer)) { footer.forEach(l => take(gone, l)); hit('footer'); }
        while (has(gone, repeaterOld) && has(added, repeaterNew)) {
            repeaterOld.forEach(l => take(gone, l));
            repeaterNew.forEach(l => take(added, l));
            hit('repeater');
        }
        const left = [
            ...[...gone].flatMap(([l, n]) => Array(n).fill('- ' + clip(l, [...added.keys()]))),
            ...[...added].flatMap(([l, n]) => Array(n).fill('+ ' + clip(l, [...gone.keys()]))),
        ];
        const repeaters = ((window.__wiSnap[b][url] ?? '').match(/ class="w-100 wi-input-repeater" data-wi-repeater="/g) ?? []).length;
        out[url] = Object.keys(rules).sort().map(k => k + '=' + rules[k]).join(' ')
            + (repeaters ? ' | ripetitori=' + repeaters : '')
            + (noisy.size ? ' | rumore=' + noisy.size : '')
            + (left.length ? ' | INATTESE: ' + JSON.stringify(left.slice(0, 10)) : '');
    }
    return out;
};
// Link di modifica di una lista DataTables: aspetta al massimo 5 s le righe caricate via AJAX.
window.__wiEditLinks = async (limit = 1) => {
    for (let i = 0; i < 20 && !document.querySelector('a[href*="/edit/"]'); i++) await new Promise(r => setTimeout(r, 250));
    return [...new Set([...document.querySelectorAll('a[href*="/edit/"]')].map(a => new URL(a.href).pathname))].slice(0, limit);
};
// Form delle pagine scaricate con la sessione corrente: quanti sono e quali hanno data-wi-save-bar.
window.__wiForms = async urls => {
    const name = f => f.id || f.getAttribute('action') || '(senza id)';
    const out = {};
    for (const url of urls) {
        const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        const forms = [...doc.querySelectorAll('form')];
        out[url] = res.status + ' ' + new URL(res.url).pathname + ' | form=' + forms.length
            + ' | tracciati=' + JSON.stringify(forms.filter(f => f.hasAttribute('data-wi-save-bar')).map(name));
    }
    return out;
};
// Gli stessi dati per la pagina aperta, comprese le righe aggiunte da JavaScript.
window.__wiLiveForms = () => {
    const forms = [...document.querySelectorAll('form')];
    return 'form=' + forms.length + ' | tracciati=' + JSON.stringify(forms.filter(f => f.hasAttribute('data-wi-save-bar')).map(f => f.id || f.getAttribute('action') || '(senza id)'));
};
JS
node --check "$W/markup.js" && echo "markup.js ok"
```

Expected: `markup.js ok`.

- [ ] **Step 2: Pagine di modifica da confrontare**

Le liste caricano le righe via AJAX, quindi i link di modifica si leggono dalla pagina aperta.

Su new.test, per ognuna delle quattro liste:
1. `navigate` alla lista;
2. incolla `markup.js`;
3. esegui con `javascript_tool`:

```js
const links = await __wiEditLinks();
sessionStorage.setItem('wiEdit:' + location.pathname, JSON.stringify(links));
links
```

Le liste di new.test sono:
- `https://new.test/backend/announcements/`
- `https://new.test/backend/app/scheduler/schedules/`
- `https://new.test/backend/app/config/user/`
- `https://new.test/backend/app/config/locations/`

Ripeti la stessa procedura su:
- `https://ecommerce.test/backend/app/gestionale/prodotti/`
- `https://immobili.test/backend/immobili/`
- `https://immobili.test/backend/residenze/`

Expected: per ogni lista un array con un percorso `/backend/.../<id>/edit/`.

`sessionStorage` vale per host e per scheda, quindi lo Step 3 ritrova i link senza ricopiarli. Una lista vuota dà `[]`:
- se è vuota la lista degli annunci, la Resource con layout in modifica resta coperta da `credentials/1/edit/`;
- per le altre liste vuote, annota nel registro la pagina di modifica come `NON APPLICABILE`, con il motivo "nessun record".

Claude non crea record per questo caso.

- [ ] **Step 3: Caso I1 su new.test**

Prima le due prese con l'app di base:

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app new-site base
```

Expected: `new-site app -> /Users/andreamarinoni/.cache/wonder-tooling/save-bar/app-base` e l'output di `herd restart`.

`navigate` a `https://new.test/backend/`, incolla `snap.js` e `markup.js`, poi esegui:

```js
const probe = '/backend/save-bar-probe/';
window.__wiUrls = [
  '/backend/',
  '/backend/announcements/',
  '/backend/announcements/create/',
  probe,
  probe + 'create/',
  ...['legacy', 'locked', 'partial', 'submit', 'hidden', 'scheduler', 'scheduler-locked'].map(m => probe + '?mode=' + m),
  '/backend/app/scheduler/schedules/',
  '/backend/app/scheduler/schedules/create/',
  '/backend/app/config/user/',
  '/backend/app/config/user/create/',
  '/backend/app/config/api-users/create/',
  '/backend/account/',
  '/backend/app/config/configuration-file/',
  '/backend/app/config/credentials/1/edit/',
  '/backend/app/config/locations/',
  '/backend/app/config/locations/create/',
  ...Object.keys(sessionStorage).filter(k => k.startsWith('wiEdit:')).sort().flatMap(k => JSON.parse(sessionStorage.getItem(k))),
];
[await __wiSnapTake('base', window.__wiUrls), await __wiSnapTake('base2', window.__wiUrls)]
```

Poi le due prese con l'app nuova, senza navigare: gli helper e le prese restano nella pagina aperta.

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app new-site nuova
```

```js
[await __wiSnapTake('mod', window.__wiUrls), await __wiSnapTake('mod2', window.__wiUrls), __wiI1()]
```

Expected:
- in tutte e quattro le prese, ogni riga è `<url> -> 200 https://new.test<url> (<lunghezza>)`, con lo stesso URL;
- `__wiI1()` restituisce per ogni URL solo le differenze della tabella.

| URL | Risultato atteso |
|---|---|
| `/backend/` | `header=1` |
| `/backend/announcements/`, `/backend/app/scheduler/schedules/`, `/backend/app/config/user/`, `/backend/app/config/locations/` | `header=1` |
| `/backend/announcements/create/` e la sua pagina di modifica | `attributo=1 header=1` |
| `/backend/save-bar-probe/`, `/backend/save-bar-probe/create/` e `/backend/save-bar-probe/?mode=` con `legacy`, `partial`, `hidden` | `attributo=1 header=1` |
| `?mode=locked` e `?mode=scheduler-locked` | `header=1` |
| `?mode=submit` | `attributo=1 footer=1 header=1` |
| `?mode=scheduler` | `attributo=1 header=1 scheduler=1` |
| `/backend/app/scheduler/schedules/create/` e la sua pagina di modifica | `attributo=1 header=1` |
| `/backend/app/config/user/create/` e la sua pagina di modifica | `attributo=1 header=1` |
| `/backend/app/config/api-users/create/` | `attributo=1 header=1 repeater=2 \| ripetitori=2` |
| `/backend/account/` | `attributo=1 header=1 ignore=1` |
| `/backend/app/config/configuration-file/` | `attributo=1 header=1` |
| `/backend/app/config/credentials/1/edit/` | `attributo=1 autocomplete=3 header=1` |
| `/backend/app/config/locations/create/` e la sua pagina di modifica | `attributo=1 header=1 repeater=N \| ripetitori=N`, con lo stesso N |

Il campo `rumore=N` può comparire su qualsiasi URL e non è un errore.

Su new.test le pianificazioni non sono in sola lettura (`APP_ENV=local`), quindi le loro pagine hanno solo l'attributo. La sola lettura dello scheduler è coperta da `?mode=scheduler` e `?mode=scheduler-locked`. Nel core non ci sono Resource legacy senza layout: il ramo legacy di `resource/form.php` lo copre `?mode=legacy`. Gestione utenti, account e configuration-file sono le altre viste senza layout.

- [ ] **Step 4: Caso I1 su ecommerce.test e immobili.test**

Stessa procedura dello Step 3.

Su ecommerce.test:
1. esegui `"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app ecommerce-site base`;
2. `navigate` a `https://ecommerce.test/backend/` e incolla `snap.js` e `markup.js`;
3. esegui:

```js
window.__wiUrls = [
  '/backend/',
  '/backend/app/gestionale/prodotti/',
  '/backend/app/gestionale/prodotti/create/',
  ...Object.keys(sessionStorage).filter(k => k.startsWith('wiEdit:')).sort().flatMap(k => JSON.parse(sessionStorage.getItem(k))),
];
[await __wiSnapTake('base', window.__wiUrls), await __wiSnapTake('base2', window.__wiUrls)]
```

4. esegui `"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app ecommerce-site nuova`;
5. esegui `[await __wiSnapTake('mod', window.__wiUrls), await __wiSnapTake('mod2', window.__wiUrls), __wiI1()]`.

Su immobili.test si fa lo stesso con `immobili-site` e con questo elenco:

```js
window.__wiUrls = [
  '/backend/',
  '/backend/immobili/',
  '/backend/immobili/create/',
  '/backend/residenze/',
  '/backend/residenze/create/',
  ...Object.keys(sessionStorage).filter(k => k.startsWith('wiEdit:')).sort().flatMap(k => JSON.parse(sessionStorage.getItem(k))),
];
[await __wiSnapTake('base', window.__wiUrls), await __wiSnapTake('base2', window.__wiUrls)]
```

Expected: le prese come nello Step 3, e questi risultati.

| Host | URL | Risultato atteso |
|---|---|---|
| ecommerce.test | `/backend/`, `/backend/app/gestionale/prodotti/` | `header=1` |
| ecommerce.test | `/backend/app/gestionale/prodotti/create/` e la sua pagina di modifica (ProductModelResource) | `attributo=1 header=1 repeater=N \| ripetitori=N`, con lo stesso N |
| immobili.test | `/backend/`, `/backend/immobili/`, `/backend/residenze/` | `header=1` |
| immobili.test | `/backend/immobili/create/` e la sua pagina di modifica (ImmobileResource) | `attributo=1 header=1 repeater=N \| ripetitori=N`, con lo stesso N |
| immobili.test | `/backend/residenze/create/` e la sua pagina di modifica (ResidenzaResource) | `attributo=1 footer=1 header=1` |

Su immobili.test si cambia solo l'app: `vendor/wonder-image/immobili` resta il worktree del Task R1 in tutte e quattro le prese. L'`attributo` di ImmobileResource viene dall'opzione `attributes` della vista del modulo, che l'app di base ignora. ResidenzaResource ha un `Submit` nel layout e usa la vista di default, quindi perde il footer.

- [ ] **Step 5: Esito del caso I1**

Se un URL ha `INATTESE`, rileggi le sue differenze complete:

```js
__wiSnapDiff('base', 'mod', 60)['<url>']
```

Il caso I1 è `FALLITO` per quell'host se:
- restano righe `INATTESE`;
- `repeater` e `ripetitori` hanno numeri diversi;
- un URL non ha le differenze della tabella.

Nella nota vanno l'URL e le righe. Se una differenza viene solo da un valore che cambia a ogni richiesta e che la normalizzazione non copre, rifai le quattro prese di quell'host: le seconde prese lo scartano come rumore. Il caso resta `FALLITO` solo se la riga ricompare.

Con esito positivo sui tre host:

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
printf '%s\n' \
  'I1 | new.test | OK | solo differenze attese (header, attributo, ignore, autocomplete, scheduler, footer, repeater)' \
  'I1 | ecommerce.test | OK | solo differenze attese' \
  'I1 | immobili.test | OK | solo differenze attese' >> "$W/integrazione-esiti.txt"
tail -3 "$W/integrazione-esiti.txt"
```

Expected: le tre righe appena scritte. Le pagine di modifica `NON APPLICABILE` dello Step 2 vanno in righe a parte, per esempio `I1 | new.test | NON APPLICABILE | /backend/app/config/locations/<id>/edit/: nessun record`, con il percorso reale.

- [ ] **Step 6: Caso I10, SecurityResource**

`navigate` a `https://new.test/backend/app/config/credentials/1/edit/`, incolla `stato.js` e `casi.js`, poi esegui:

```js
[[...document.querySelectorAll('#resource-layout-form input[type="password"]')].map(i => i.name + '=' + i.getAttribute('autocomplete')), await __wiBar(1500), __wiState()]
```

Expected:
- il primo elemento è `["klaviyo_api_key=new-password", "brevo_api_key=new-password", "mail_password=new-password"]`, in qualsiasi ordine;
- in `__wiBar()`:
  - `forms` ha un solo elemento con `id: "resource-layout-form"`, `dirtyAttr: false` e `dirty: false`;
  - `label` e `status` sono `""`;
- lo stato è `hidden` oppure `buttons`:
  - con `hidden`, `island` è `false`;
  - con `buttons`, `island` è `true` e `copies` è `["Salva"]`: la pagina è lunga e il Salva è sotto la fascia.

Uno stato `dirty-buttons` o `dirty-label` fa fallire il caso: il form appena aperto non deve risultare modificato.

Il comando legge solo nome e `autocomplete` dei campi, mai il loro valore. Poi leggi la console con `read_console_messages` (regole comuni del Task R2).

```bash
printf '%s\n' 'I10 | new.test | OK | tre campi segreti con autocomplete=new-password, form pulito' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

Il comportamento dell'autofill di Chrome si prova in M1 (Task R7).

- [ ] **Step 7: Caso I17, sorgenti e pagine senza sessione**

```bash
APP_WT=/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
grep -rl data-wi-save-bar "$APP_WT/app" "$APP_WT/class" | sed "s#^$APP_WT/##" | sort
for h in new.test ecommerce.test immobili.test; do
  for p in login password-recovery; do
    printf '%s %s %s\n' "$h" "$p" "$(curl -sk "https://$h/backend/account/$p/" | grep -c data-wi-save-bar)"
  done
done
```

Expected:
- il primo comando elenca esattamente sei file:
  - `app/view/pages/backend/account/index.php`
  - `app/view/pages/backend/config/configuration-file.php`
  - `app/view/pages/backend/resource/form.php`
  - `app/view/pages/backend/scheduler/form.php`
  - `app/view/pages/backend/user/manage.php`
  - `class/App/PageSchema/AccountPageSchema.php`
- mancano quindi, tra gli altri:
  - le viste di login e password (`account/login.php`, `password-recovery.php`, `password-restore.php`, `password-set.php`);
  - `home.php`, con il form "Esegui update";
  - la dashboard dello scheduler e `ScheduleResource.php`, con il form di eliminazione della riga;
  - i filtri (`class/Backend/Filter/*`);
  - `RendersButtonPostForm.php`, cioè i form dei `Button::post`;
  - `upload-massive.php` e `sql-download.php`;
- sei righe `<host> login 0` e `<host> password-recovery 0`.

Il renderer dei layout mette l'attributo tramite l'opzione `attributes`, quindi non compare nell'elenco. `password-restore` e `password-set` senza token fanno redirect al login: per loro vale il controllo del sorgente.

- [ ] **Step 8: Caso I17, pagine con sessione**

Su new.test, dalla pagina `https://new.test/backend/` con `markup.js` incollato:

```js
await __wiForms([
  '/backend/',
  '/backend/announcements/',
  '/backend/app/scheduler/',
  '/backend/app/scheduler/schedules/',
  '/backend/app/media/upload-massive/',
  '/backend/app/config/sql-download/',
])
```

Su immobili.test, dalla pagina `https://immobili.test/backend/` con `markup.js` incollato, esegui `await __wiForms(['/backend/immobili/'])`: la lista ha i filtri e il `Button::post` che sincronizza tutti gli immobili dal feed (`ImmobileResource::tableLayoutSchema()`).

Expected: ogni riga è `200 <stesso percorso> | form=<n> | tracciati=[]`. `/backend/` deve avere almeno un form, "Esegui update", perché l'utente è amministratore.

Le righe della lista delle pianificazioni arrivano via AJAX. `navigate` a `https://new.test/backend/app/scheduler/schedules/`, incolla `markup.js` ed esegui:

```js
await __wiEditLinks(); [__wiLiveForms(), document.querySelectorAll('form[action$="/delete/"]').length]
```

Expected: `["form=<n> | tracciati=[]", <m>]`. `<m>` è il numero di pianificazioni create dal backend nella pagina della lista: solo loro hanno il form "Elimina" (`ScheduleResource::actionsCell()`). Con `0` non c'è un form di eliminazione da controllare nella pagina e vale il controllo del sorgente dello Step 7: annotalo nella nota.

```bash
printf '%s\n' \
  'I17 | new.test | OK | login, password, home, filtri, scheduler, upload-massive, sql-download senza form tracciati' \
  'I17 | immobili.test | OK | Button::post e filtri della lista senza form tracciati' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

Un form tracciato in una di queste pagine fa fallire il caso: nella nota vanno la pagina e il nome del form.

### Task R4: Pagine del core (casi I2-I9)

Repo: nessun commit. Si lavora nel pannello Browser su new.test e in `$HOME/.cache/wonder-tooling/save-bar` (`W`), con i siti preparati nel Task R2 (app e `dist` nuove). Su new-site si toccano per pochi minuti tre file, ognuno copiato prima in `W` e rimesso nello stesso caso: `custom/config/permissions.php` (I8), `robots.txt` e `.htaccess` (I9).

| Caso | Pagine di new.test | Cosa prova |
|---|---|---|
| I2 | `/backend/announcements/create/`, `/backend/save-bar-probe/` | Resource con layout: stati dell'isola, copia che invia |
| I3 | `/backend/save-bar-probe/?mode=legacy` | ramo senza layout di `resource/form.php` |
| I4 | `/backend/save-bar-probe/?mode=` `locked`, `partial`, `submit`, `hidden` | form bloccato, sola lettura parziale, Submit nel layout, Submit nascosto |
| I5 | `/backend/save-bar-probe/create/`, `/backend/save-bar-probe/` | POST fallito e POST riuscito |
| I6 | `/backend/app/scheduler/schedules/create/`, `/backend/save-bar-probe/?mode=scheduler` e `scheduler-locked` | vista dello scheduler |
| I8 | `/backend/app/config/api-users/` | gestione utenti |
| I7 | `/backend/account/` | account, due form nella pagina |
| I9 | `/backend/app/config/configuration-file/` | file di configurazione |

I8 si fa prima di I7: I7 provoca il 907 con lo username dell'utente API creato in I8.

La Resource di prova (`SaveBarProbeResource`, Task R2) sostituisce le varianti che il core non ha: sola lettura parziale, Submit nel layout, Submit nascosto da `visibleWhen`, sola lettura dello scheduler e un errore che esiste solo sul server. Il suo `mutateRequestValues()` lancia sempre `Errore di prova della barra di salvataggio.`, quindi `/create/` fallisce sempre. Il POST della pagina `/backend/save-bar-probe/` passa invece da `submitFormPage()`: avviso 650 e redirect a `/backend/save-bar-probe/`, senza scrivere nulla.

Nessun caso di questo task manda mail vere:
- gli utenti si creano nell'area API, perché la creazione di un utente del backend manda la mail di benvenuto (`UserManagementResource::sendsWelcomeMail()`);
- ogni POST della gestione utenti porta `_skip_api_token_mail=1`, che ferma la mail "Credenziali API" di `apiUser()`;
- gli indirizzi sono `@example.com`;
- la verifica email di I8 si ferma prima dell'invio, con il codice 900.

**Files:**
- Create: `$W/core.js` (helper per `javascript_tool`)
- Create: `$W/i8-backup/permissions.php`, `$W/i9-backup/.htaccess`, `$W/i9-backup/robots.txt` (copie di sicurezza)
- Modify: `$W/integrazione-esiti.txt` (esiti dei casi I2-I9)
- Modify: `$W/record-di-prova.txt` (utenti API creati in I8)
- Modify, e rimessi nello stesso caso: `/Users/andreamarinoni/Developer/boilerplates/new-site/custom/config/permissions.php`, `/Users/andreamarinoni/Developer/boilerplates/new-site/robots.txt`, `/Users/andreamarinoni/Developer/boilerplates/new-site/.htaccess`

**Interfaces:**
- Consumes:
  - dal Task R2: `stato.js` (`__wiBar`, `__wiConfirmStub`), `casi.js` (`__wiState`, `__wiUntil`, `__wiLogSubmits`, `__wiSubmits`, `__wiLeave`, `__wiHeightFor`), la Resource di prova su new-site, la sessione di amministratore su new.test e le regole comuni dello Step 12;
  - dal Task R3: `markup.js` (`__wiEditLinks`, `__wiLiveForms`).
- Produces:
  - `core.js`, con gli helper della tabella dello Step 1, usati anche nei Task R5 e R6;
  - `sessionStorage.wiI8` su new.test: lo username dell'utente API di prova, letto da I7;
  - le righe I2-I9 del registro e le righe degli utenti API in `record-di-prova.txt`.

- [ ] **Step 1: Helper delle pagine del core**

| Helper | Cosa fa |
|---|---|
| `__wiTitle()` | testo del titolo `h3` in `#content`, per il click neutro |
| `__wiAlerts()` | codici passati ad `alertToast(N)` negli script inline della pagina |
| `__wiDanger()` | testo degli `.alert.alert-danger`, separati da ` \| ` |
| `__wiNoApiMail(form)` | aggiunge al form `_skip_api_token_mail=1`, con `data-wi-save-bar-ignore` |
| `__wiFill(values, form)` | scrive i valori da script e manda `input` e `change`; per ogni campo `nome=true` se il valore è rimasto |
| `__wiFocus(name, form)` | mette il focus in fondo al campo e restituisce il suo nome |
| `__wiEditLinkOf(text, timeout)` | percorso `/edit/` della riga di lista con una cella uguale a `text` |
| `__wiAccountKeys`, `__wiAccountRead()`, `__wiAccountTake()`, `__wiAccountSame()` | valori del profilo in `sessionStorage` e confronto solo con booleani |
| `__wiUntilDirty(dirty, timeout)` | aspetta uno stato `dirty-*` (o uno stato pulito con `false`) e restituisce lo stato |
| `__wiSpinner()` | `true` se `#loading-spinner` è visibile |

`form` vale per default il primo `form[data-wi-save-bar]`. I valori del profilo non compaiono mai nell'output: `__wiAccountSame()` restituisce solo `true` o `false` per chiave, compreso `files` (numero di file in FilePond).

Una scrittura da script conta come modifica solo dopo la prima interazione vera: prima, l'isola la assorbe nella base. Per questo ogni caso comincia con il click sul titolo.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
cat > "$W/core.js" <<'JS'
// Helper dei casi delle pagine del core (Task R4): avvisi, campi, record di prova, profilo.
window.__wiTitle = () => document.querySelector('#content h3')?.textContent.replace(/\s+/g, ' ').trim() ?? null;
window.__wiAlerts = () => [...document.scripts].filter(s => !s.src).flatMap(s => [...s.textContent.matchAll(/alertToast\((\d+)\)/g)].map(m => Number(m[1])));
window.__wiDanger = () => [...document.querySelectorAll('.alert.alert-danger')].map(el => el.textContent.replace(/\s+/g, ' ').trim()).join(' | ');
window.__wiNoApiMail = (form = document.querySelector('form[data-wi-save-bar]')) => {
    if (!form.querySelector('[name="_skip_api_token_mail"]')) {
        form.insertAdjacentHTML('beforeend', '<input type="hidden" name="_skip_api_token_mail" value="1" data-wi-save-bar-ignore>');
    }
    return form.querySelectorAll('[name="_skip_api_token_mail"]').length === 1 ? 'mail del token disattivata' : 'ERRORE';
};
window.__wiFill = (values, form = document.querySelector('form[data-wi-save-bar]')) => Object.entries(values).map(([name, value]) => {
    const el = form.querySelector('[name="' + name + '"]');
    if (!el) return name + '=false';
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    return name + '=' + (el.value === String(value));
});
window.__wiFocus = (name, form = document.querySelector('form[data-wi-save-bar]')) => {
    const el = form.querySelector('[name="' + name + '"]');
    if (!el) return null;
    el.focus();
    try { el.setSelectionRange(el.value.length, el.value.length); } catch (error) {}
    return document.activeElement === el ? name : null;
};
window.__wiEditLinkOf = async (text, timeout = 5000) => {
    const end = performance.now() + timeout;
    for (;;) {
        const row = [...document.querySelectorAll('tr')].find(tr => [...tr.cells].some(td => td.textContent.trim() === text) && tr.querySelector('a[href*="/edit/"]'));
        if (row) return new URL(row.querySelector('a[href*="/edit/"]').href).pathname;
        if (performance.now() >= end) return null;
        await new Promise(resolve => setTimeout(resolve, 250));
    }
};
window.__wiAccountKeys = ['name', 'surname', 'username', 'phone', 'color', 'email'];
window.__wiAccountRead = () => {
    const form = document.querySelector('[name="surname"]').form;
    const out = {};
    for (const key of window.__wiAccountKeys) out[key] = form.querySelector('[name="' + key + '"]')?.value ?? null;
    out.files = form.querySelectorAll('.filepond--item').length;
    return out;
};
window.__wiAccountTake = () => { sessionStorage.setItem('wiAccount', JSON.stringify(window.__wiAccountRead())); return 'valori del profilo salvati'; };
window.__wiAccountSame = () => {
    const saved = JSON.parse(sessionStorage.getItem('wiAccount') || 'null');
    if (!saved) return null;
    const now = window.__wiAccountRead();
    return Object.fromEntries(Object.keys(saved).map(key => [key, saved[key] === now[key]]));
};
window.__wiUntilDirty = async (dirty = true, timeout = 3000) => {
    const end = performance.now() + timeout;
    const done = () => String(window.__wiState()).startsWith('dirty-') === dirty;
    while (!done() && performance.now() < end) await new Promise(resolve => setTimeout(resolve, 50));
    return window.__wiState();
};
window.__wiSpinner = () => !document.getElementById('loading-spinner')?.classList.contains('d-none');
JS
node --check "$W/core.js" && echo "core.js ok"
```

Expected: `core.js ok`.

In tutti i casi del task, dopo ogni `navigate`, ricarica o invio si incollano, letti con Read, `stato.js`, `casi.js` e `core.js` (più `markup.js` dove il passo lo dice).

**Scrittura in un campo.** Quando un passo dice "scrivi `<testo>` in `<campo>`":
1. `__wiTitle()` dà il testo del titolo; `find` con quel testo dà il `ref` del titolo; `computer` `left_click` sul `ref`. È l'interazione vera che serve all'isola e all'avviso di uscita. Il titolo occupa la larghezza della card, quindi il suo centro cade a destra del testo, lontano dalla freccia indietro a sinistra. Se la pagina cambia, torna indietro con `navigate` e ripeti il passo;
2. `__wiFocus('<campo>')` deve restituire il nome del campo;
3. `computer` `type` con il testo. "Cancella" vuol dire `computer` `key` `Backspace`, con il focus ancora nel campo.

**Click su una copia.** `read_page` con `filter: interactive`, poi `computer` `left_click` sul `ref` dell'ultimo `button "<testo>"` con quel testo: le copie stanno dopo i pulsanti della pagina (regole comuni).

**Click su un Salva della pagina.** `find` con il testo del pulsante, `computer` `scroll_to` sul `ref` del pulsante con quel nome esatto (non la copia, non un titolo `h6`), poi `left_click` sullo stesso `ref`.

- [ ] **Step 2: Caso I2, Resource con layout**

`navigate` a `https://new.test/backend/announcements/create/` e incolla gli helper.

```js
await __wiBar(1500)
```

Expected: `forms` con un solo elemento, `id: "resource-layout-form"`, `dirtyAttr: false` e `dirty: false`; `label` e `status` `""`.

Applica la regola "finestra alta quanto serve" (1280x900, `__wiHeightFor()`, 1280 x `h`).

```js
[await __wiUntil('buttons'), await __wiBar()]
```

Expected: `"buttons"`; `island: true`, `label: ""`, `copies: ["Salva (disabilitato)"]`, `buttons: ["Salva (disabilitato)"]`, `status: ""`. Il Salva è disabilitato perché `name` è obbligatorio e vuoto (`check()`).

In fondo alla pagina il Salva sale sopra la fascia, grazie alla riserva di `#content`:

```js
window.scrollTo(0, document.documentElement.scrollHeight);
[await __wiUntil('hidden'), (await __wiBar()).island, (await __wiBar()).copies]
```

Expected: `["hidden", false, []]`.

```js
window.scrollTo(0, 0); await __wiUntil('buttons')
```

Expected: `"buttons"`.

Scrivi `x` in `name` (procedura dello Step 1), poi:

```js
[await __wiUntil('dirty-buttons'), await __wiBar()]
```

Expected: `"dirty-buttons"`; `forms[0].dirty: true` e `dirtyAttr: false`; `label` e `status` `"Operazione non salvata"`; `copies: ["Salva"]`.

Cancella la `x`, poi:

```js
[await __wiUntil('buttons'), await __wiBar()]
```

Expected: `"buttons"`; `forms[0].dirty: false`; `label` e `status` `""`; `copies: ["Salva (disabilitato)"]`. Poi un click sul titolo e `__wiLeave()` restituisce `false`.

Poi la copia che invia. `navigate` a `https://new.test/backend/save-bar-probe/`, incolla gli helper, applica la regola della finestra ed esegui `[await __wiUntil('buttons'), await __wiBar()]`.

Expected: `"buttons"`, `copies: ["Salva"]`, `buttons: ["Salva"]`: `name` vale `Prova` e non ci sono campi obbligatori.

Scrivi `x` in `name`, esegui `[await __wiUntil('dirty-buttons'), __wiLogSubmits()]`, poi clicca la copia `Salva`. Dopo il redirect incolla gli helper:

```js
[location.pathname, __wiSubmits(), __wiAlerts(), await __wiBar(1500)]
```

Expected: `"/backend/save-bar-probe/"`, `["upload="]`, un array che contiene `650`, e un solo form con `dirtyAttr: false` e `dirty: false`. Il pulsante `submit()` del backend non ha `value`, quindi il registro scrive `upload=`.

Torna con `resize_window` e preset `desktop`, poi leggi la console (regole comuni).

```bash
printf '%s\n' 'I2 | new.test | OK | layout: attributo senza -dirty, stati hidden/buttons/dirty-buttons, regione di stato, copia che invia (650) e pagina pulita' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

Le animazioni di B2 si misurano nella Parte B. B24, cioè la copia di "Salva e aggiungi" con un solo POST, si prova in I8.

- [ ] **Step 3: Caso I3, ramo legacy senza layout**

`navigate` a `https://new.test/backend/save-bar-probe/?mode=legacy` e incolla gli helper.

```js
[document.querySelector('form[data-wi-save-bar]')?.id, document.querySelector('form[data-wi-save-bar]')?.getAttribute('onsubmit'), document.querySelectorAll('#resource-layout-form').length, await __wiBar(1500)]
```

Expected: `""`, `"loadingSpinner()"`, `0`, e in `__wiBar()` un solo form con `dirtyAttr: false`, `dirty: false`, `buttons: ["Salva"]`, `label` e `status` `""`.

Poi gli stessi passi dello Step 2 sulla stessa pagina, con questi risultati:
1. regola della finestra, poi `[await __wiUntil('buttons'), await __wiBar()]` → `"buttons"`, `copies: ["Salva"]`;
2. `window.scrollTo(0, document.documentElement.scrollHeight); await __wiUntil('hidden')` → `"hidden"`; `window.scrollTo(0, 0); await __wiUntil('buttons')` → `"buttons"`;
3. scrivi `x` in `name`, poi `[await __wiUntil('dirty-buttons'), await __wiBar()]` → `"dirty-buttons"`, `label` e `status` `"Operazione non salvata"`, `copies: ["Salva"]`;
4. cancella la `x`, poi `[await __wiUntil('buttons'), await __wiBar()]` → `"buttons"`, `dirty: false`, `status: ""`;
5. scrivi di nuovo `x` in `name`, esegui `[await __wiUntil('dirty-buttons'), __wiLogSubmits()]` e clicca la copia `Salva`;
6. dopo il redirect incolla gli helper ed esegui `[location.pathname, __wiSubmits(), __wiAlerts(), await __wiBar(1500)]` → `"/backend/save-bar-probe/"`, `["upload="]`, un array che contiene `650`, form pulito.

Torna al preset `desktop` e leggi la console.

```bash
printf '%s\n' 'I3 | new.test | OK | ramo legacy: form senza id con attributo, stessi stati, copia che invia (650) e pagina pulita' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

- [ ] **Step 4: Caso I4, form bloccato e sola lettura**

Form bloccato. `navigate` a `https://new.test/backend/save-bar-probe/?mode=locked`, incolla `stato.js`, `casi.js`, `core.js` e `markup.js`:

```js
[__wiState(), __wiLiveForms(), document.querySelector('.alert.alert-warning')?.textContent.trim(), document.querySelectorAll('#resource-layout-form [type="submit"]').length]
```

Expected: `null`, `"form=<n> | tracciati=[]"`, `"Si modifica in locale e si pubblica con il deploy."`, `0`. Senza form tracciati la lib non crea l'isola.

Sola lettura parziale. `navigate` a `https://new.test/backend/save-bar-probe/?mode=partial` e incolla gli helper:

```js
[document.querySelectorAll('.alert.alert-warning').length, await __wiBar(1500)]
```

Expected: `1`, un solo form `resource-layout-form` pulito, `buttons: ["Salva"]`. Applica la regola della finestra, scrivi `x` in `text` (l'unico campo modificabile), poi `[await __wiUntil('dirty-buttons'), await __wiBar()]` → `"dirty-buttons"`, `copies: ["Salva"]`. Cancella la `x`, poi `await __wiUntil('buttons')` → `"buttons"`.

Sola lettura parziale con un Submit nel layout. `navigate` a `https://new.test/backend/save-bar-probe/?mode=submit` e incolla gli helper:

```js
[document.querySelectorAll('.alert.alert-warning').length, document.querySelectorAll('#resource-layout-form [type="submit"]').length, await __wiBar(1500)]
```

Expected: `1`, `1`, `buttons: ["Salva"]`: il footer ha l'avviso ma non il suo Salva (`hasSubmit()`).

Submit in una Card nascosta. `navigate` a `https://new.test/backend/save-bar-probe/?mode=hidden` e incolla gli helper:

```js
[document.querySelectorAll('#resource-layout-form [type="submit"]').length, await __wiBar(1500)]
```

Expected: `2` e `buttons: ["Salva"]`: il Submit della Card nascosta c'è nel DOM ma non si vede, e il footer tiene il suo Salva.

Applica la regola della finestra, fai un click sul titolo, poi:

```js
[__wiFill({ kind: 'b' }), await __wiUntil('dirty-buttons'), await __wiBar()]
```

Expected: `["kind=true"]`, `"dirty-buttons"`, `buttons: ["Salva", "Salva"]`, `copies: ["Salva", "Salva"]`.

```js
[__wiFill({ kind: 'a' }), await __wiUntil('buttons'), await __wiBar()]
```

Expected: `["kind=true"]`, `"buttons"`, `dirty: false`, `copies: ["Salva"]`.

Torna al preset `desktop` e leggi la console.

```bash
printf '%s\n' 'I4 | new.test | OK | bloccato senza isola; parziale con isola; Submit nel layout con avviso e un solo Salva; Submit nascosto con il Salva del footer' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

- [ ] **Step 5: Caso I5, POST fallito e POST riuscito**

La finestra resta 1280x900: qui conta il Salva della pagina, non la copia.

POST fallito. `navigate` a `https://new.test/backend/save-bar-probe/create/`, incolla gli helper, `resize_window` a 1280x900. Scrivi `x` in `name`, esegui `[await __wiUntilDirty(), __wiLogSubmits()]`, poi clicca il Salva della pagina. Dopo il POST incolla gli helper:

```js
[__wiSubmits(), __wiDanger(), document.querySelector('form[data-wi-save-bar] [name="name"]').value === 'Provax', await __wiUntilDirty(), await __wiBar()]
```

Expected:
- `["upload="]`;
- `"Errore di prova della barra di salvataggio."`;
- `true`: il valore scritto resta;
- uno stato che comincia con `dirty-`, già al caricamento;
- in `__wiBar()`: `dirtyAttr: true`, `dirty: true`, `label` e `status` `"Operazione non salvata"`.

Poi un click sul titolo: `__wiLeave()` restituisce `true`. `navigate` a `https://new.test/backend/save-bar-probe/`, con `force: true` se compare "Leave site?".

POST riuscito. Sulla pagina `https://new.test/backend/save-bar-probe/` incolla gli helper, scrivi `x` in `name`, esegui `[await __wiUntilDirty(), __wiLogSubmits()]` e clicca il Salva della pagina. Dopo il redirect incolla gli helper:

```js
[location.pathname, __wiSubmits(), __wiAlerts(), await __wiBar(1500)]
```

Expected: `"/backend/save-bar-probe/"`, `["upload="]`, un array che contiene `650`, form con `dirtyAttr: false` e `dirty: false`. Un click sul titolo, poi `__wiLeave()` restituisce `false`.

Torna al preset `desktop` e leggi la console.

```bash
printf '%s\n' 'I5 | new.test | OK | POST fallito: -dirty, etichetta al caricamento, avviso di uscita dopo il click; POST riuscito: redirect e pagina pulita' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

- [ ] **Step 6: Caso I6, scheduler**

Scheduler modificabile (new.test ha `APP_ENV=local`). `navigate` a `https://new.test/backend/app/scheduler/schedules/create/` e incolla gli helper:

```js
[document.getElementById('resource-layout-form')?.getAttribute('onsubmit'), await __wiBar(1500)]
```

Expected: `"loadingSpinner()"`, un solo form `resource-layout-form` pulito, `buttons: ["Salva pianificazione (disabilitato)"]`: `name` è obbligatorio e vuoto.

Applica la regola della finestra: `[await __wiUntil('buttons'), (await __wiBar()).copies]` → `["buttons", ["Salva pianificazione (disabilitato)"]]`.

POST fallito. Fai un click sul titolo, poi:

```js
[__wiFill({ name: 'Prova barra salvataggio', parameters: '[1]' }), await __wiUntil('dirty-buttons'), await __wiBar()]
```

Expected: `["name=true", "parameters=true"]`, `"dirty-buttons"`, `copies: ["Salva pianificazione"]`.

Esegui `__wiLogSubmits()` e clicca la copia `Salva pianificazione`. Dopo il POST incolla gli helper:

```js
[__wiSubmits(), __wiDanger(), await __wiUntilDirty(), await __wiBar()]
```

Expected: `["upload=true"]`, `"Inserire un oggetto JSON, ad esempio {\"feed\":\"1\"}."`, uno stato `dirty-*`, `dirtyAttr: true`, `dirty: true`. Un click sul titolo, poi `__wiLeave()` restituisce `true`. Nessuna pianificazione viene creata.

`mutateRequestValues()` controlla, in quest'ordine, il token CSRF, il nome, l'attività (`ConfiguredTask::resolve()`) e poi il JSON. Se il messaggio è un altro, per esempio perché `task_key` non ha opzioni, il caso è `BLOCCATO` con il messaggio letto.

Sola lettura senza campi modificabili. `navigate` a `https://new.test/backend/save-bar-probe/?mode=scheduler-locked`, con `force: true` se compare "Leave site?". Incolla `stato.js`, `casi.js`, `core.js` e `markup.js`:

```js
[__wiState(), __wiLiveForms(), document.getElementById('resource-layout-form')?.getAttribute('onsubmit')]
```

Expected: `null`, `"form=<n> | tracciati=[]"`, `"return false"`.

Sola lettura con `READONLY_EDITABLE`. `navigate` a `https://new.test/backend/save-bar-probe/?mode=scheduler` e incolla gli helper:

```js
[document.getElementById('resource-layout-form')?.getAttribute('onsubmit'), await __wiBar(1500)]
```

Expected: `"loadingSpinner()"`, un solo form `resource-layout-form` pulito, `buttons: ["Salva"]`. Applica la regola della finestra, scrivi `x` in `text`, esegui `[await __wiUntil('dirty-buttons'), __wiLogSubmits()]` e clicca la copia `Salva`. Dopo il redirect incolla gli helper ed esegui `[location.pathname, __wiSubmits(), __wiAlerts(), await __wiBar(1500)]`.

Expected: `"/backend/save-bar-probe/"`, `["upload="]`, un array che contiene `650`, form pulito. Il salvataggio parte davvero perché l'`onsubmit` segue la stessa condizione dell'attributo.

Torna al preset `desktop` e leggi la console.

```bash
printf '%s\n' 'I6 | new.test | OK | scheduler modificabile con isola; POST fallito sporco; READONLY senza isola e return false; READONLY_EDITABLE con isola e salvataggio (650)' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

- [ ] **Step 7: Caso I8, gestione utenti (area API)**

Prima del caso, un utente API deve già esistere: `Credentials::appToken()` legge la riga 1 di `api_users`, e il primo utente creato qui la occuperebbe. `navigate` a `https://new.test/backend/app/config/api-users/`, incolla `markup.js` ed esegui `(await __wiEditLinks()).length`.

Expected: `1`. Con `0` il caso è `BLOCCATO` con la nota "nessun utente API esistente", e si passa allo Step 8 senza la parte del 907.

Username di prova, valido per tutto il task:

```js
sessionStorage.setItem('wiI8', 'save-bar-api-' + String(Date.now()).slice(-6)); sessionStorage.getItem('wiI8')
```

Expected: `"save-bar-api-<6 cifre>"`. Nel resto del caso `<u>` è questo valore.

Form nuovo. `navigate` a `https://new.test/backend/app/config/api-users/create/` e incolla gli helper:

```js
[__wiNoApiMail(), await __wiBar(1500)]
```

Expected: `"mail del token disattivata"`, un solo form pulito, `buttons: ["Salva e aggiungi (disabilitato)", "Salva (disabilitato)"]`, in ordine di pagina.

Applica la regola della finestra e fai un click sul titolo, poi:

```js
[__wiNoApiMail(), __wiFill({ name: 'Prova', surname: 'Barra', username: sessionStorage.getItem('wiI8'), email: sessionStorage.getItem('wiI8') + '@example.com', authority: [...document.querySelector('form[data-wi-save-bar] [name="authority"]').options].map(o => o.value).find(v => v !== '') }), await __wiUntil('dirty-buttons'), await __wiBar()]
```

Expected: `"mail del token disattivata"`, cinque voci `=true`, `"dirty-buttons"`, `copies: ["Salva e aggiungi", "Salva"]`.

Esegui `__wiLogSubmits()` e clicca la copia `Salva e aggiungi`. Dopo il redirect incolla gli helper:

```js
[location.pathname, __wiSubmits(), __wiAlerts(), ['name', 'surname', 'username', 'email'].map(n => document.querySelector('form[data-wi-save-bar] [name="' + n + '"]').value), await __wiBar(1500)]
```

Expected: `"/backend/app/config/api-users/create/"`, `["upload-add="]`, nessun codice 9xx, `["", "", "", ""]`, form con `dirtyAttr: false` e `dirty: false`. È l'utente A.

Validazione fallita (907). Sulla stessa pagina incolla gli helper, applica la regola della finestra e fai un click sul titolo, poi:

```js
[__wiNoApiMail(), __wiFill({ name: 'Prova', surname: 'Barra', username: sessionStorage.getItem('wiI8'), email: sessionStorage.getItem('wiI8') + '-dup@example.com', authority: [...document.querySelector('form[data-wi-save-bar] [name="authority"]').options].map(o => o.value).find(v => v !== '') }), await __wiUntil('dirty-buttons'), __wiLogSubmits()]
```

Clicca la copia `Salva`. Dopo il POST incolla gli helper:

```js
[__wiSubmits(), __wiAlerts(), document.querySelector('form[data-wi-save-bar] [name="username"]').value === sessionStorage.getItem('wiI8'), await __wiUntilDirty(), await __wiBar()]
```

Expected: `["upload="]`, `[907]`, `true`, uno stato `dirty-*`, `dirtyAttr: true`, `dirty: true`. Nessun utente creato.

Salvataggio riuscito (utente B). Sulla stessa pagina applica la regola della finestra e fai un click sul titolo, poi:

```js
[__wiNoApiMail(), __wiFill({ name: 'Prova', surname: 'Barra', username: sessionStorage.getItem('wiI8') + 'b', email: sessionStorage.getItem('wiI8') + 'b@example.com', authority: [...document.querySelector('form[data-wi-save-bar] [name="authority"]').options].map(o => o.value).find(v => v !== '') }), await __wiUntilDirty(), __wiLogSubmits()]
```

Clicca la copia `Salva`. Dopo il redirect incolla gli helper ed esegui `[location.pathname, __wiSubmits(), __wiAlerts(), __wiState()]`.

Expected: `"/backend/app/config/api-users/"`, `["upload="]`, nessun codice 9xx, `null` (la lista non ha form tracciati).

Salvato con un avviso (900). La verifica email obbligatoria nell'area `api` fa passare `user()` dal ramo `written` con un codice d'errore: la configurazione non ha un link per la mail, quindi `userSendEmailVerificationMail()` si ferma con 900 prima di spedire.

```bash
SITE=/Users/andreamarinoni/Developer/boilerplates/new-site
W="$HOME/.cache/wonder-tooling/save-bar"
mkdir -p "$W/i8-backup"
cp -p "$SITE/custom/config/permissions.php" "$W/i8-backup/permissions.php"
printf '%s\n' "Permissions::area('api')->verification('email', ['required' => true]);" >> "$SITE/custom/config/permissions.php"
tail -2 "$SITE/custom/config/permissions.php"
php -l "$SITE/custom/config/permissions.php"
```

Expected: le righe `use Wonder\App\Permission\Permissions;` e `Permissions::area('api')->verification('email', ['required' => true]);`, poi `No syntax errors detected`.

Aspetta 3 secondi (l'opcache rilegge i file cambiati dopo 2 s). `navigate` a `https://new.test/backend/app/config/api-users/create/`, incolla gli helper, applica la regola della finestra e fai un click sul titolo, poi:

```js
[__wiNoApiMail(), __wiFill({ name: 'Prova', surname: 'Barra', username: sessionStorage.getItem('wiI8') + 'c', email: sessionStorage.getItem('wiI8') + 'c@example.com', authority: [...document.querySelector('form[data-wi-save-bar] [name="authority"]').options].map(o => o.value).find(v => v !== '') }), await __wiUntil('dirty-buttons'), __wiLogSubmits()]
```

Clicca la copia `Salva`. Dopo il POST incolla gli helper ed esegui `[location.pathname, __wiSubmits(), __wiAlerts(), await __wiBar(1500)]`.

Expected: `"/backend/app/config/api-users/create/"`, `["upload="]`, `[900]`, form con `dirtyAttr: false` e `dirty: false`: l'utente C è scritto, quindi la pagina è pulita. Se invece arriva la lista senza 900, la verifica non era attiva: l'utente C è creato comunque e la parte del 900 è `BLOCCATO`.

Rimetti subito il file:

```bash
SITE=/Users/andreamarinoni/Developer/boilerplates/new-site
W="$HOME/.cache/wonder-tooling/save-bar"
cp -p "$W/i8-backup/permissions.php" "$SITE/custom/config/permissions.php"
cmp "$W/i8-backup/permissions.php" "$SITE/custom/config/permissions.php" && echo "permissions.php ripristinato"
git -C "$SITE" status --porcelain custom/config/permissions.php | wc -l
```

Expected: `permissions.php ripristinato` e `0`.

Modifica dell'utente A. `navigate` a `https://new.test/backend/app/config/api-users/`, incolla `core.js` ed esegui `await __wiEditLinkOf(sessionStorage.getItem('wiI8'))`. Con `null`, l'utente è in un'altra pagina della lista: esegui `document.querySelector('input[id$="__search_input"]').focus()`, scrivi `<u>` con `computer` `type` e ripeti `await __wiEditLinkOf(sessionStorage.getItem('wiI8'))`.

Expected: `"/backend/app/config/api-users/<id>/edit/"`. `navigate` a quel percorso e incolla gli helper:

```js
[__wiNoApiMail(), await __wiBar(1500)]
```

Expected: `"mail del token disattivata"`, un solo form con `dirtyAttr: false` e `dirty: false`. Esegui `__wiLogSubmits()` e clicca il Salva della pagina con nome esatto `Salva`. Dopo il redirect incolla gli helper ed esegui `[location.pathname, __wiSubmits(), __wiAlerts().includes(970), __wiState()]`.

Expected: `"/backend/app/config/api-users/"`, `["upload="]`, `false`, `null`.

Torna al preset `desktop` e leggi la console. Poi il registro e i record, con `<u>` scritto per intero:

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
U=save-bar-api-NNNNNN   # il valore di sessionStorage.getItem('wiI8')
printf '%s\n' \
  "new.test | /backend/app/config/api-users/ | utente API $U | I8" \
  "new.test | /backend/app/config/api-users/ | utente API ${U}b | I8" \
  "new.test | /backend/app/config/api-users/ | utente API ${U}c | I8" >> "$W/record-di-prova.txt"
printf '%s\n' 'I8 | new.test | OK | 907 sporco; Salva e aggiungi porta al form vuoto e pulito; salvato pulito; 900 (al posto di 908, stesso ramo written) pulito; modifica utente API pulita e senza 970' >> "$W/integrazione-esiti.txt"
tail -3 "$W/record-di-prova.txt"
```

Expected: le tre righe dei record, con lo username vero.

- [ ] **Step 8: Caso I7, account**

`navigate` a `https://new.test/backend/account/`, incolla `stato.js`, `casi.js`, `core.js` e `markup.js`, `resize_window` a 1280x900.

```js
[document.querySelector('[name="surname"]').form.hasAttribute('data-wi-save-bar'), document.querySelector('[name="old-password"]').form.hasAttribute('data-wi-save-bar'), !!document.querySelector('[name="surname"]').form.querySelector('[name="password"]').closest('[data-wi-save-bar-ignore]'), __wiLiveForms(), await __wiBar(1500), __wiAccountTake()]
```

Expected:
- `true`: il form del profilo è tracciato;
- `false`: il form della password no;
- `true`: la password di conferma è esclusa;
- `"form=<n> | tracciati=[\"(senza id)\"]"`;
- in `__wiBar()`: un solo form con `dirtyAttr: false` e `dirty: false`, `buttons: ["Modifica dati (disabilitato)"]` (la password di conferma è obbligatoria e vuota);
- `"valori del profilo salvati"`.

Password di conferma errata (905). Scrivi `x` in `name`, poi:

```js
[await __wiUntilDirty(), __wiFill({ password: 'sbagliata-save-bar' }), __wiLogSubmits()]
```

Clicca il pulsante della pagina `Modifica dati`. Dopo il POST incolla gli helper:

```js
[__wiSubmits(), __wiAlerts(), await __wiBar(1500), __wiAccountSame()]
```

Expected: `["modify="]`, `[905]`, form pulito (`dirtyAttr: false`, `dirty: false`), tutte le chiavi `true`: la pagina mostra i valori del DB. Un click sul titolo, poi `__wiLeave()` restituisce `false`.

`user()` fallisce dopo la password giusta (907). Fai un click sul titolo, poi:

```js
[__wiFill({ username: sessionStorage.getItem('wiI8') }), await __wiUntilDirty()]
```

Expected: `["username=true"]` e uno stato `dirty-*`. Chiedi all'utente di scrivere la sua password nel campo password del form "Modifica dati", nel pannello Browser, e di rispondere in chat quando ha finito. Poi esegui `__wiLogSubmits()` e clicca `Modifica dati`. Dopo il POST incolla gli helper:

```js
[__wiSubmits(), __wiAlerts(), await __wiUntilDirty(), await __wiBar()]
```

Expected: `["modify="]`, `[907]`, uno stato `dirty-*`, `dirtyAttr: true`, `dirty: true`. Se compare `604`, lo username dell'account è cambiato: il caso è `FALLITO` e l'utente rimette il suo username (Claude non lo scrive).

Modifica riuscita (604). `navigate` a `https://new.test/backend/account/` in GET, con `force: true` se compare "Leave site?". Incolla gli helper ed esegui `[await __wiBar(1500), __wiAccountSame()]`: form pulito e tutte le chiavi `true`. Fai un click sul titolo, chiedi all'utente di scrivere di nuovo la password nello stesso campo e di rispondere in chat. Poi esegui `__wiLogSubmits()` e clicca `Modifica dati`. Dopo il POST incolla gli helper ed esegui `[__wiSubmits(), __wiAlerts(), await __wiBar(1500), __wiAccountSame()]`.

Expected: `["modify="]`, `[604]`, form pulito, tutte le chiavi `true`. Un click sul titolo, poi `__wiLeave()` restituisce `false`. Una chiave `false` vuol dire che il salvataggio ha normalizzato quel valore: annota solo il nome della chiave e chiedi all'utente di controllarlo, senza leggere il valore.

Profilo sporco e invio del form password (`confirmOther`). Esegui `__wiConfirmStub(false)`, scrivi `x` in `surname`, poi:

```js
window.__wiSameDoc = true;
[await __wiUntilDirty(), __wiFill({ 'old-password': 'sbagliata-save-bar', 'new-password': 'sbagliata-save-bar' }, document.querySelector('[name="old-password"]').form)]
```

Expected: uno stato `dirty-*` e `["old-password=true", "new-password=true"]`. Clicca il pulsante della pagina `Modifica password`, poi:

```js
[window.__wiSameDoc === true, (await __wiBar()).confirms, __wiSpinner(), location.pathname]
```

Expected: `true`, `["Ci sono modifiche non salvate in un altro modulo della pagina. Continuare e perderle?"]`, `false`, `"/backend/account/"`. Annulla ha fermato l'invio e spento lo spinner.

Verso opposto: form password compilato, profilo pulito. `__wiFocus('surname')` deve restituire `"surname"`; cancella la `x`, poi:

```js
[await __wiUntilDirty(false), __wiConfirmStub(false), __wiFill({ password: 'sbagliata-save-bar' }), __wiLogSubmits()]
```

Expected: uno stato che non comincia con `dirty-`, `"confirm() finto, risponde false"`, `["password=true"]`. Clicca `Modifica dati`: con `confirm()` che risponde `false`, una domanda fermerebbe l'invio. Dopo il POST incolla gli helper ed esegui `[window.__wiSameDoc, __wiSubmits(), __wiAlerts()]`.

Expected: `undefined` (la pagina è nuova), `["modify="]`, `[905]`.

Torna al preset `desktop` e leggi la console.

```bash
printf '%s\n' 'I7 | new.test | OK | 905 pulito con i valori del DB; 907 (al posto di 920) sporco; 604 pulito; confirmOther dal form password; verso opposto senza domanda; form password senza attributo' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

L'autocompilazione di Chrome sulla password di conferma si prova in M1 (Task R7).

- [ ] **Step 9: Caso I9, file di configurazione**

Il salvataggio scrive prima `.htaccess` e poi `robots.txt`. Un `robots.txt` in sola lettura fa fallire la seconda scrittura; la prima riscrive `.htaccess` con il testo del form, con gli a capo CRLF del browser. Per questo si copiano tutti e due.

```bash
SITE=/Users/andreamarinoni/Developer/boilerplates/new-site
W="$HOME/.cache/wonder-tooling/save-bar"
mkdir -p "$W/i9-backup"
cp -p "$SITE/.htaccess" "$SITE/robots.txt" "$W/i9-backup/"
chmod a-w "$SITE/robots.txt"
ls -l "$SITE/robots.txt" | cut -c1-10
```

Expected: `-r--r--r--`.

`navigate` a `https://new.test/backend/app/config/configuration-file/`, incolla gli helper ed esegui `await __wiBar(1500)`: un solo form pulito, `buttons: ["Modifica"]`. Applica la regola della finestra: `[await __wiUntil('buttons'), (await __wiBar()).copies]` → `["buttons", ["Modifica"]]`.

Errore. Scrivi `x` in `robots`, esegui `[await __wiUntil('dirty-buttons'), __wiLogSubmits()]` e clicca la copia `Modifica`. Dopo il POST incolla gli helper:

```js
[__wiSubmits(), __wiDanger(), await __wiUntilDirty(), await __wiBar()]
```

Expected: `["modify=true"]`, `"Impossibile salvare il file robots.txt."`, uno stato `dirty-*`, `dirtyAttr: true`, `dirty: true`. Un click sul titolo, poi `__wiLeave()` restituisce `true`. Se invece l'URL finisce con `?alert=654`, il file era scrivibile lo stesso: il caso è `BLOCCATO`.

Salvataggio riuscito:

```bash
chmod u+w /Users/andreamarinoni/Developer/boilerplates/new-site/robots.txt
```

Nella stessa pagina applica la regola della finestra, esegui `[await __wiUntil('dirty-buttons'), __wiLogSubmits()]` e clicca la copia `Modifica`. Dopo il redirect incolla gli helper ed esegui `[location.search, __wiSubmits(), __wiAlerts(), await __wiBar(1500)]`.

Expected: `"?alert=654"`, `["modify=true"]`, `[654]`, form pulito.

Rimetti i due file:

```bash
SITE=/Users/andreamarinoni/Developer/boilerplates/new-site
W="$HOME/.cache/wonder-tooling/save-bar"
cp -p "$W/i9-backup/.htaccess" "$W/i9-backup/robots.txt" "$SITE/"
cmp "$W/i9-backup/.htaccess" "$SITE/.htaccess" && cmp "$W/i9-backup/robots.txt" "$SITE/robots.txt" && echo "file di configurazione ripristinati"
ls -l "$SITE/robots.txt" | cut -c1-10
```

Expected: `file di configurazione ripristinati` e `-rw-r--r--`.

Torna al preset `desktop` e leggi la console.

```bash
printf '%s\n' 'I9 | new.test | OK | errore di scrittura sporco con avviso di uscita; salvataggio riuscito (654) pulito; file rimessi' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
tail -8 "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

Expected: le righe I2-I9 del task. Un caso non `OK` va scritto con l'esito vero e la nota, al posto della riga di esempio.

### Task R5: Widget, layout e compatibilità (casi I11-I16, I18)

Repo: nessun commit. Si lavora nel pannello Browser su new.test ed ecommerce.test e in `$HOME/.cache/wonder-tooling/save-bar` (`W`), con i siti preparati nel Task R2 (app e `dist` nuove). Su new-site si tocca per pochi minuti un solo file versionato, `custom/view/components/backend/layout/header.php`: prima si copia in `W`, poi si rimette nello stesso caso (I14).

| Caso | Pagine | Cosa prova |
|---|---|---|
| I11 | new.test `/backend/save-bar-probe/?mode=widgets` | Repeater: svuotare l'ultima riga |
| I12 | new.test `/backend/save-bar-probe/?mode=widgets`, ecommerce.test `/backend/app/gestionale/prodotti/create/` | widget veri: apertura pulita, modifiche, ritorno al valore iniziale |
| I13 | new.test `/backend/save-bar-probe/` | tasto Indietro dopo un salvataggio |
| I14 | new.test `/backend/save-bar-probe/`, `/backend/announcements/` | layout: riserva, header sovrascritto, lib vecchia |
| I15 | new.test `/backend/announcements/`, `/backend/announcements/create/` | lib nuova con l'app originale del sito |
| I16 | new.test `/backend/announcements/create/` a 375px | menu mobile, tema scuro del sito, touch |
| I18 | new-site da riga di comando | `user()` fuori dal backend |

La pagina `?mode=widgets` è lunga: a 1280x900 i suoi Salva stanno già sotto la fascia, quindi basta `resize_window` a 1280x900, senza `__wiHeightFor()`.

**Files:**
- Create: `$W/widget.js` (helper per `javascript_tool`)
- Create: `$W/i14-backup/header.php`, `$W/i14-backup/status-prima.txt` (copia di sicurezza e stato git del file)
- Create e cancellato nello stesso caso: `$W/i18-user.php`
- Modify: `$W/integrazione-esiti.txt` (esiti dei casi I11-I16 e I18)
- Modify: `$W/record-di-prova.txt` (categoria di I12 e utente di I18)
- Modify, e rimesso nello stesso caso: `/Users/andreamarinoni/Developer/boilerplates/new-site/custom/view/components/backend/layout/header.php`

**Interfaces:**
- Consumes:
  - dal Task R2: `stato.js` (`__wiShown`, `__wiBar`, `__wiConfirmStub`), `casi.js` (`__wiState`, `__wiUntil`, `__wiLogSubmits`, `__wiSubmits`, `__wiLeave`, `__wiHeightFor`), `style.js` (`__wiStyleTake`, `__wiStyleDiff`), `siti.sh`, la Resource di prova su new-site, la sessione di amministratore e le regole comuni dello Step 12;
  - dal Task R3: `markup.js` (`__wiEditLinks`);
  - dal Task R4: `core.js` (`__wiTitle`, `__wiAlerts`, `__wiFocus`, `__wiUntilDirty`) e le regole "Scrittura in un campo", "Click su una copia" e "Click su un Salva della pagina" del suo Step 1.
- Produces:
  - `widget.js`, con gli helper della tabella dello Step 1, usato anche nel Task R6;
  - le righe I11-I16 e I18 del registro, la categoria di I12 e l'utente di I18 in `record-di-prova.txt`.

- [ ] **Step 1: Helper dei widget**

| Helper | Cosa fa |
|---|---|
| `__wiFocusIn(target)` | mette il focus su un elemento o selettore; in un `contenteditable` porta il cursore in fondo |
| `__wiEditorFocus(name)` | mette il focus nel Quill o nell'EditorJS della textarea `name` |
| `__wiRows()` | righe visibili del repeater, con i valori dei loro campi |
| `__wiLabelDelete()` | dà un nome accessibile ai cestini delle righe e al pulsante di conferma, per `find` |
| `__wiBarVisibility()` | toglie l'attributo della tastiera e legge `visibility` dell'isola |
| `__wiTreeValues()` | valori inviati dal CheckTree `tree` |

Il cestino del repeater è solo un'icona, senza testo: `__wiLabelDelete()` gli dà un `aria-label` e restituisce i nomi, così `find` trova il `ref`. `__wiBarVisibility()` serve dove il caso guarda i popup degli editor: su mobile il focus in un campo mette anche l'attributo della tastiera, che nasconderebbe l'isola comunque.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
cat > "$W/widget.js" <<'JS'
// Helper dei casi dei widget (Task R5-R6): focus negli editor e nelle ricerche, righe del repeater.
window.__wiFocusIn = target => {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) return null;
    el.focus();
    if (el.isContentEditable) {
        const range = document.createRange();
        range.selectNodeContents(el);
        range.collapse(false);
        getSelection().removeAllRanges();
        getSelection().addRange(range);
    }
    return el.contains(document.activeElement) ? (el.id || el.className || el.tagName) : null;
};
window.__wiEditorFocus = name => {
    const area = document.querySelector('textarea[name="' + name + '"]');
    const box = area && document.getElementById(area.id + '_editor');
    const target = box?.querySelector('.ql-editor') ?? [...(box?.querySelectorAll('.ce-paragraph') ?? [])].pop();
    return target && window.__wiFocusIn(target) ? name : null;
};
window.__wiRows = () => [...document.querySelectorAll('.wi-repeater-row')].filter(window.__wiShown).map(row => {
    const field = key => row.querySelector('[name$="[' + key + ']"]');
    const price = field('rprice');
    return {
        rkind: field('rkind')?.value ?? null,
        rtag: field('rtag')?.value ?? null,
        rprice: price ? (window.AutoNumeric?.getAutoNumericElement(price)?.getNumericString() ?? price.value) : null,
        rnote: window.__wiShown(field('rnote')),
        files: row.querySelectorAll('.filepond--item').length,
        pond: !!row.querySelector('.filepond--root'),
    };
});
window.__wiLabelDelete = () => {
    document.querySelector('#wi-repeater-delete-modal [data-wi-confirm-delete="true"]')?.setAttribute('aria-label', 'Conferma eliminazione riga');
    return [...document.querySelectorAll('.wi-repeater-row')].filter(window.__wiShown).map((row, i) => {
        row.querySelector('.wi-repeater-delete')?.setAttribute('aria-label', 'Cestino riga ' + (i + 1));
        return 'Cestino riga ' + (i + 1);
    });
};
window.__wiBarVisibility = () => {
    const bar = document.querySelector('.wi-save-bar');
    bar?.removeAttribute('data-wi-save-bar-keyboard');
    return bar ? getComputedStyle(bar).visibility : null;
};
window.__wiTreeValues = () => [...document.querySelectorAll('[data-wi-tree-values="tree[]"] input')].map(i => i.value).filter(Boolean);
JS
node --check "$W/widget.js" && echo "widget.js ok"
```

Expected: `widget.js ok`.

In tutti i casi del task, dopo ogni `navigate`, ricarica o invio si incollano, letti con Read, `stato.js`, `casi.js`, `core.js` e `widget.js`. Valgono le regole "Scrittura in un campo", "Click su una copia" e "Click su un Salva della pagina" del Task R4, Step 1. Ogni caso comincia con il click sul titolo, perché prima della prima interazione vera l'isola assorbe le scritture da script nella base.

- [ ] **Step 2: Caso I11, svuotare l'ultima riga del repeater**

`resize_window` a 1280x900, `navigate` a `https://new.test/backend/save-bar-probe/?mode=widgets`, aspetta 2 secondi con `computer` `wait`, incolla gli helper e clicca il titolo (procedura della scrittura, punto 1).

```js
[__wiLabelDelete(), __wiRows(), await __wiBar(1500)]
```

Expected: `["Cestino riga 1", "Cestino riga 2"]`; due righe, `{rkind: "a", rtag: "x", rprice: "10", rnote: false, ...}` e `{rkind: "b", rtag: "y", rprice: "1234.5", rnote: true, ...}`, entrambe con `pond: true`; un solo form pulito.

Elimina la prima riga: `find` con `Cestino riga 1`, `left_click` sul `ref`; poi di nuovo `__wiLabelDelete()`, `find` con `Conferma eliminazione riga` e `left_click` sul `ref`.

```js
[__wiRows(), await __wiUntilDirty(), (await __wiBar()).forms[0].dirty]
```

Expected: una sola riga, `{rkind: "b", rtag: "y", rprice: "1234.5", rnote: true, files: 0, pond: true}`; uno stato `dirty-*`; `true`.

Ripeti sull'ultima riga rimasta: `__wiLabelDelete()` restituisce `["Cestino riga 1"]`, poi `find` e `left_click` su `Cestino riga 1`, `__wiLabelDelete()`, `find` e `left_click` su `Conferma eliminazione riga`. Il repeater può aggiungere righe, quindi l'ultima riga si svuota invece di sparire.

```js
[__wiRows(), await __wiUntil('dirty-buttons'), await __wiBar()]
```

Expected:
- `[{rkind: "", rtag: "", rprice: "", rnote: false, files: 0, pond: true}]`: Select2 e AutoNumeric vuoti, la nota nascosta perché `rkind` non vale più `b`, FilePond ricreato;
- `"dirty-buttons"`;
- `label` e `status` `"Operazione non salvata"`, `copies` e `buttons` `["Salva (disabilitato)"]`: `rkind` è obbligatorio e vuoto, quindi `check()` disabilita Salva.

Un click sul titolo, poi `__wiLeave()` restituisce `true`. `navigate` a `https://new.test/backend/` con `force: true`, torna al preset `desktop` e leggi la console.

```bash
printf '%s\n' 'I11 | new.test | OK | ultima riga svuotata: Select2, AutoNumeric e nota vuoti, FilePond ricreato, isola sporca, Salva disabilitato da check()' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

- [ ] **Step 3: Caso I12, apertura pulita, Select2, datepicker e Quill**

`resize_window` a 1280x900 e `navigate` a `https://new.test/backend/save-bar-probe/?mode=widgets`. Incolla subito gli helper e clicca il titolo senza aspettare: così il click arriva prima che la DynamicCheck, il telefono e gli editor finiscano di caricare, e le loro scritture tardive devono essere assorbite lo stesso.

```js
[document.querySelectorAll('[data-wi-search-url*="save-bar-probe"] ~ .card-body input:checked').length, __wiTreeValues(), await __wiBar(3000)]
```

Expected: `2` (Uno e Tre, dai valori `['1', '3']`), `["11"]`, un solo form con `dirtyAttr: false` e `dirty: false`. La stessa lettura copre il telefono, `quill_empty` (`<p><br></p>` dal DB), le etichette, l'albero e la DynamicCheck all'apertura.

Select2 multiplo:

```js
$('select[name^="tags"]').val(['x', 'z']).trigger('change');
[await __wiUntilDirty(), $('select[name^="tags"]').val()]
```

Expected: uno stato `dirty-*` e `["x", "z"]`.

```js
$('select[name^="tags"]').val(['x']).trigger('change');
await __wiUntilDirty(false)
```

Expected: `"buttons"`.

Datepicker:

```js
const day = $('[data-wi-date="true"]');
day.datepicker('setDate', new Date(2026, 8, 28));
[day.val(), await __wiUntilDirty()]
```

Expected: `"28/09/2026"` e uno stato `dirty-*`.

```js
const day = $('[data-wi-date="true"]');
day.datepicker('setDate', new Date(2026, 8, 27));
[day.val(), await __wiUntilDirty(false)]
```

Expected: `"27/09/2026"` e `"buttons"`.

Quill, una lettera scritta e cancellata: `__wiEditorFocus('quill')` restituisce `"quill"`, poi `computer` `type` con `x` e `computer` `key` `Backspace`.

```js
[document.querySelector('textarea[name="quill"]').value, await __wiUntilDirty(false)]
```

Expected: `"<p>Testo</p>"` e `"buttons"`.

Quill obbligatorio svuotato: `__wiEditorFocus('quill_required')` restituisce `"quill_required"`, poi `computer` `key` `cmd+a` e `computer` `key` `Backspace`.

```js
[document.querySelector('textarea[name="quill_required"]').value, await __wiUntilDirty(), (await __wiBar()).copies]
```

Expected: `""`, uno stato `dirty-*`, `["Salva (disabilitato)"]`. È il cambio voluto del Task I1: un Quill vuoto scrive `''`, quindi un campo obbligatorio vuoto disabilita Salva.

`navigate` a `https://new.test/backend/` con `force: true` e leggi la console. Le righe del registro di I12 si scrivono tutte insieme alla fine dello Step 7.

- [ ] **Step 4: Caso I12, EditorJS**

`resize_window` a 1280x900, `navigate` a `https://new.test/backend/save-bar-probe/?mode=widgets`, aspetta 2 secondi, incolla gli helper e clicca il titolo.

Un'immagine che finisce di caricare:

```js
const img = document.querySelector('#' + document.querySelector('textarea[name="editor"]').id + '_editor img');
await new Promise(resolve => { img.addEventListener('load', resolve, { once: true }); img.src = img.src.split('#')[0] + '#wi'; setTimeout(resolve, 2000); });
await __wiBar(1500)
```

Expected: un solo form pulito.

Il mouse su una tabella: `find` con `Cella B2`, `computer` `hover` sul `ref`, poi `await __wiBar(1500)`.

Expected: un solo form pulito.

Una modifica e l'uscita dopo 500ms. `__wiEditorFocus('editor')` restituisce `"editor"`; poi, in un solo `browser_batch`:
1. `computer` `type` con ` x`;
2. `javascript_tool` con `await new Promise(resolve => setTimeout(resolve, 500)); __wiLeave()`.

Expected: il secondo elemento restituisce `true`. Poi `await __wiUntilDirty()` restituisce uno stato `dirty-*`.

Menu aperti su mobile. `resize_window` con preset `mobile`, `navigate` di nuovo alla stessa pagina con `force: true`, aspetta 2 secondi, incolla gli helper e clicca il titolo. `__wiEditorFocus('editor')` restituisce `"editor"`; `computer` `key` `Return` crea un blocco vuoto, poi `await __wiUntilDirty()` restituisce uno stato `dirty-*`.

`computer` `key` `Tab` apre il menu dei blocchi. Se non si apre, esegui `document.querySelector('.ce-toolbar__plus').click()`.

```js
[!!document.querySelector('.ce-popover--opened'), __wiBarVisibility(), __wiState()]
```

Expected: `[true, "hidden", "dirty-..."]`: lo stato resta sporco, ma l'isola è nascosta finché il menu è aperto.

`computer` `key` `Escape`, poi:

```js
[!!document.querySelector('.ce-popover--opened'), __wiBarVisibility()]
```

Expected: `[false, "visible"]`.

`navigate` a `https://new.test/backend/` con `force: true`, torna al preset `desktop` e leggi la console.

- [ ] **Step 5: Caso I12, CheckTree e DynamicCheck**

`resize_window` a 1280x900, `navigate` a `https://new.test/backend/save-bar-probe/?mode=widgets`, aspetta 2 secondi, incolla gli helper e clicca il titolo.

Un nodo con figli spuntati chiuso e riaperto:

```js
$('[data-wi-tree]').jstree('close_all');
await new Promise(resolve => setTimeout(resolve, 300));
$('[data-wi-tree]').jstree('open_all');
[__wiTreeValues(), await __wiBar(1500)]
```

Expected: `["11"]` e un solo form pulito.

Spunto in un ordine e tolgo in un altro. Per ogni voce: `find` con il testo, `left_click` sul `ref` del `treeitem` (non sulla casella di ricerca), poi la lettura.
1. `Uno.2`, poi `[__wiTreeValues(), await __wiUntilDirty()]` → contiene `"11"` e `"12"`, stato `dirty-*`;
2. `Uno.1`, poi `__wiTreeValues()` → `["12"]`;
3. `Uno.1`, poi `__wiTreeValues()` → contiene `"11"` e `"12"`;
4. `Uno.2`, poi `[__wiTreeValues(), await __wiUntilDirty(false)]` → `["11"]` e `"buttons"`;
5. `Due`, poi `await __wiUntilDirty()` → stato `dirty-*`;
6. `Due`, poi `[__wiTreeValues(), await __wiUntilDirty(false)]` → `["11"]` e `"buttons"`.

La ricerca dell'albero, che ridisegna i nodi: `__wiFocusIn(document.querySelector('[data-wi-tree]').closest('.card').querySelector('[data-wi-search]'))` non restituisce `null`; poi `computer` `type` con `Due`, aspetta 1 secondo e `computer` `key` `Backspace` con `repeat: 3`.

```js
[__wiTreeValues(), await __wiBar(1500)]
```

Expected: `["11"]` e un solo form pulito.

La ricerca della DynamicCheck, che riordina i risultati: `__wiFocusIn('[data-wi-search-url*="save-bar-probe"]')` non restituisce `null`; poi `computer` `type` con `Due`, aspetta 2 secondi e `computer` `key` `Backspace` con `repeat: 3`, poi aspetta 2 secondi.

```js
[[...document.querySelectorAll('[data-wi-search-url*="save-bar-probe"] ~ .card-body input:checked')].map(i => i.value).sort(), await __wiBar(1500)]
```

Expected: `["1", "3"]` e un solo form pulito.

`navigate` a `https://new.test/backend/` e leggi la console.

- [ ] **Step 6: Caso I12, Google Places e generatore di codici**

`resize_window` a 1280x900, `navigate` a `https://new.test/backend/save-bar-probe/?mode=widgets`, incolla gli helper e clicca subito il titolo. Poi definisci la callback che il campo chiama:

```js
window.__wiPlaceProbe = (input, place) => { window.__wiPlace = place.getPlace?.()?.address_components?.length ?? 0; };
[document.querySelector('[name="address"]').disabled, typeof google === 'undefined' ? 'no-google' : (google.maps?.places ? 'places' : 'no-places')]
```

Click prima che Places abiliti il campo (Q17): `find` con `Indirizzo`, `left_click` sul `ref` del campo, poi `await __wiBar(1500)` → un solo form pulito.

Se il secondo valore non è `"places"`, la chiave di Google non è caricata in locale: scrivi la riga del registro e passa al generatore.

```bash
printf '%s\n' 'I12 | new.test | BLOCCATO | Places: API non caricata in locale, scelta di un indirizzo non provata' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

Se invece vale `"places"`: aspetta che `document.querySelector('[name="address"]').disabled` sia `false`, `__wiFocus('address')` restituisce `"address"`, `computer` `type` con `Via Roma 1 Milano`, aspetta 2 secondi, `computer` `key` `Down` e `computer` `key` `Return`.

```js
[window.__wiPlace ?? null, await __wiUntilDirty()]
```

Expected: un numero maggiore di 0 e uno stato `dirty-*`. Poi `navigate` di nuovo alla pagina con `force: true`, incolla gli helper e clicca il titolo.

Generatore di codici: `find` con `GENERA`, `left_click` sul `ref`, poi:

```js
[document.querySelector('[name="code"]').value !== '', await __wiUntilDirty()]
```

Expected: `true` e uno stato `dirty-*`.

FilePond con immagini già salvate si prova in I20, su immobili.test, dove esistono record con foto.

`navigate` a `https://new.test/backend/` con `force: true` e leggi la console.

- [ ] **Step 7: Caso I12, quick-create**

`resize_window` a 1280x900, `navigate` a `https://ecommerce.test/backend/app/gestionale/prodotti/create/`, aspetta 2 secondi, incolla gli helper e clicca il titolo.

```js
sessionStorage.setItem('wiI12', 'Save bar I12 ' + String(Date.now()).slice(-6));
[sessionStorage.getItem('wiI12'), await __wiBar(1500)]
```

Expected: il nome della categoria di prova e un form pulito.

`find` con `Aggiungi categoria`, `left_click` sul `ref`. Poi:

```js
[!!document.querySelector('.modal.show .wi-qc-submit'), (await __wiBar()).copies.every(text => !document.querySelector('.modal.show')?.textContent.includes(text) || text.startsWith('Salva'))]
```

Expected: `[true, true]`. Poi controlla con `read_page` (`filter: interactive`) che dopo i pulsanti del modal non ci sia una copia del suo Salva: il modal sta nel `body`, fuori dal form, quindi l'isola non lo copia.

Scrivi il nome di `sessionStorage.wiI12` nel primo campo di testo del modal: `find` con il segnaposto del campo del nome, `left_click` sul `ref`, `computer` `type` con il nome. Poi dai un nome al pulsante e cliccalo:

```js
document.querySelector('.modal.show .wi-qc-submit').setAttribute('aria-label', 'Salva nel modal');
'ok'
```

`find` con `Salva nel modal`, `left_click` sul `ref`, aspetta 2 secondi.

```js
[document.querySelector('.modal.show') === null, await __wiUntilDirty()]
```

Expected: `true` e uno stato `dirty-*`: la categoria creata viene scelta nel form del prodotto.

Annota il record creato:

```bash
printf '%s\n' 'ecommerce.test | quick-create da /backend/app/gestionale/prodotti/create/ | categoria <nome di sessionStorage.wiI12> | I12' >> "$HOME/.cache/wonder-tooling/save-bar/record-di-prova.txt"
```

Un click sul titolo, `__wiLeave()` restituisce `true`, `navigate` a `https://ecommerce.test/backend/` con `force: true`. Il prodotto non si salva. Leggi la console.

Scrivi le righe di I12 (una riga `BLOCCATO` di Places, se c'è, resta separata):

```bash
printf '%s\n' 'I12 | new.test | OK | apertura pulita, Select2 e datepicker avanti e indietro, Quill, Quill obbligatorio vuoto, EditorJS (immagine, tabella, uscita a 500ms, popup su mobile), CheckTree, DynamicCheck, Places, generatore' 'I12 | ecommerce.test | OK | quick-create: Salva del modal non copiato, categoria creata e form sporco' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

- [ ] **Step 8: Caso I13, tasto Indietro dopo un salvataggio**

È una verifica di idoneità: il backend risponde `no-store`, quindi Chrome di solito non ripristina la pagina dalla cache e il caso diventa non applicabile.

`navigate` a `https://new.test/backend/save-bar-probe/`, incolla gli helper e clicca il titolo. Poi:

```js
window.__wiMark = true;
sessionStorage.removeItem('wiI13ps');
addEventListener('pageshow', event => sessionStorage.setItem('wiI13ps', String(event.persisted)));
[__wiFocus('name'), __wiLogSubmits()]
```

Expected: `["name", "registro invii attivo"]`. `computer` `type` con `x`, poi clicca il Salva della pagina (`find`, `scroll_to`, `left_click`). La Resource di prova rifiuta ogni salvataggio: si torna alla pagina con l'avviso 650, senza scrivere nel DB.

`navigate` con `url: "back"`, incolla gli helper, poi:

```js
const nav = performance.getEntriesByType('navigation')[0];
[sessionStorage.getItem('wiI13ps'), nav.type, JSON.stringify(nav.notRestoredReasons?.reasons ?? null), window.__wiMark ?? null, __wiAlerts()]
```

Poi `read_network_requests` con `urlPattern: "save-bar-probe"`: la lista deve avere un solo POST, e l'ultima richiesta deve essere un GET.

Esiti:
- `null`, `"back_forward"` e motivi presenti: pagina non ripristinata, come previsto;
  ```bash
  printf '%s\n' 'I13 | new.test | NON APPLICABILE | non applicabile in Chrome (no-store): pagina non ripristinata; copertura B34-B35' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
  ```
- `"true"`, `__wiMark` `null` e un solo POST: la pagina ripristinata si è ricaricata in GET senza un secondo invio;
  ```bash
  printf '%s\n' 'I13 | new.test | OK | pagina ripristinata e ricaricata in GET, un solo POST' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
  ```
- `__wiMark` `true`, oppure due POST: la pagina è rimasta quella vecchia, o il form è stato inviato di nuovo. Scrivi `I13 | new.test | FALLITO | <cosa si è visto>`.

Leggi la console.

- [ ] **Step 9: Caso I14, layout con app nuova e vecchia**

I confronti usano `style.js`: la pagina ospite `https://new.test/backend/` resta aperta tra una lettura e l'altra, e `siti.sh` cambia app e lib da Bash senza navigare. `navigate` a `https://new.test/backend/` e incolla `style.js`.

App di base e lib nuova:

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app new-site base
```

```js
[await __wiStyleTake('form-base', '/backend/save-bar-probe/'), await __wiStyleTake('list-base', '/backend/announcements/', 1280, 900, 3000)]
```

App nuova e lib nuova:

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app new-site nuova
```

```js
[await __wiStyleTake('form-nuova', '/backend/save-bar-probe/'), await __wiStyleTake('list-nuova', '/backend/announcements/', 1280, 900, 3000)]
```

```js
const s = __wiStyle;
[s['form-base'].page.scroll, s['form-nuova'].page.scroll, s['form-nuova'].page.on, s['list-base'].page.on, s['list-nuova'].page.on, s['list-base'].page.content, s['list-nuova'].page.content, __wiStyleDiff('list-base', 'list-nuova')]
```

Expected:
- i due `scroll` della pagina corta con form sono uguali: nessuno scroll vuoto, perché `header.php` sottrae la riserva;
- `true` per `form-nuova`, `false` per le due liste: una lista senza form non ha riserva;
- le due altezze di `#content` sono uguali;
- il confronto delle liste dà `page: []` e `total: 0`. Se DataTables finisce di caricare in tempi diversi, ripeti le due letture della lista una volta prima di segnare `FALLITO`.

Header sovrascritto da un sito, cioè la copia dell'header attuale:

```bash
S=/Users/andreamarinoni/Developer/boilerplates/new-site
W="$HOME/.cache/wonder-tooling/save-bar"
F=custom/view/components/backend/layout/header.php
mkdir -p "$W/i14-backup"
git -C "$S" status --porcelain -- "$F" > "$W/i14-backup/status-prima.txt"
cp -p "$S/$F" "$W/i14-backup/header.php"
cp "$W/app-base/app/view/components/backend/layout/header.php" "$S/$F"
herd restart
```

```js
await __wiStyleTake('form-override', '/backend/save-bar-probe/');
const d = __wiStyle['form-override'].page.scroll - __wiStyle['form-nuova'].page.scroll;
[__wiStyle['form-override'].page.on, d, d >= 0 && d <= 96]
```

Expected: `[true, <d>, true]`: l'header copiato non sottrae la riserva, quindi in fondo restano al massimo 96px in più.

Isola e Salva con l'header sovrascritto: `resize_window` a 1280x900, `navigate` a `https://new.test/backend/save-bar-probe/?mode=widgets`, incolla gli helper e clicca il titolo. `await __wiUntil('buttons')` restituisce `"buttons"`. Poi `__wiFocus('name')`, `computer` `type` con `x` e un click sul titolo.

```js
window.scrollTo(0, document.documentElement.scrollHeight);
await new Promise(resolve => setTimeout(resolve, 500));
const save = [...document.querySelectorAll('#resource-layout-form [type="submit"]')].filter(__wiShown).pop();
const r = save.getBoundingClientRect();
[await __wiUntil('dirty-label'), document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)?.closest('button, [type="submit"]') === save]
```

Expected: `["dirty-label", true]`: in fondo alla pagina l'isola mostra solo l'etichetta e non copre il Salva.

`navigate` a `https://new.test/backend/` con `force: true`. Rimetti l'header del sito e controlla:

```bash
S=/Users/andreamarinoni/Developer/boilerplates/new-site
W="$HOME/.cache/wonder-tooling/save-bar"
F=custom/view/components/backend/layout/header.php
cp -p "$W/i14-backup/header.php" "$S/$F"
herd restart
cmp "$W/i14-backup/header.php" "$S/$F" && git -C "$S" status --porcelain -- "$F" | diff - "$W/i14-backup/status-prima.txt" && echo "header rimesso"
```

Expected: `header rimesso`.

Lib di base con app di base e con app nuova. Incolla di nuovo `style.js` nella pagina ospite.

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" dist new-site base
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app new-site base
```

```js
[await __wiStyleTake('form-oggi', '/backend/save-bar-probe/'), await __wiStyleTake('list-oggi', '/backend/announcements/', 1280, 900, 3000)]
```

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app new-site nuova
```

```js
[await __wiStyleTake('form-mix', '/backend/save-bar-probe/'), await __wiStyleTake('list-mix', '/backend/announcements/', 1280, 900, 3000), __wiStyleDiff('form-oggi', 'form-mix'), __wiStyleDiff('list-oggi', 'list-mix')]
```

Expected: nei due confronti `page: []` e `total: 0`. La lib vecchia non ha le regole dell'isola, quindi l'app nuova con la lib vecchia ha lo stesso layout di oggi. Se il form ha `markup diverso` perché l'app nuova aggiunge l'isola, confronta invece `__wiStyle['form-oggi'].page` e `__wiStyle['form-mix'].page` senza `island`, `on` ed `elements`: `scroll` e `content` devono essere uguali.

`navigate` a `https://new.test/backend/save-bar-probe/` e poi a `https://new.test/backend/announcements/`, leggendo la console dopo ciascuna: nessun errore con app nuova e lib vecchia. Poi rimetti la lib nuova:

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" dist new-site nuova
```

```bash
printf '%s\n' 'I14 | new.test | OK | pagina corta senza scroll vuoto, lista senza riserva e #content uguale, header sovrascritto con al massimo 96px in più e Salva non coperto, app nuova e lib vecchia come oggi' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

- [ ] **Step 10: Caso I15, lib nuova con l'app originale del sito**

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app new-site originale
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" dist new-site base
```

`navigate` a `https://new.test/backend/`, incolla `style.js` e leggi con la lib di base, in tema chiaro e poi scuro. L'iframe legge lo stesso `localStorage` della pagina ospite.

```js
localStorage.setItem('theme', 'light');
[await __wiStyleTake('list-light-base', '/backend/announcements/', 1280, 900, 3000), await __wiStyleTake('form-light-base', '/backend/announcements/create/')]
```

```js
localStorage.setItem('theme', 'dark');
[await __wiStyleTake('list-dark-base', '/backend/announcements/', 1280, 900, 3000), await __wiStyleTake('form-dark-base', '/backend/announcements/create/')]
```

Poi con la lib nuova, sempre nella stessa pagina ospite:

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" dist new-site nuova
```

```js
[await __wiStyleTake('list-dark-nuova', '/backend/announcements/', 1280, 900, 3000), await __wiStyleTake('form-dark-nuova', '/backend/announcements/create/')]
```

```js
localStorage.setItem('theme', 'light');
[await __wiStyleTake('list-light-nuova', '/backend/announcements/', 1280, 900, 3000), await __wiStyleTake('form-light-nuova', '/backend/announcements/create/')]
```

Controlli:

```js
const s = __wiStyle;
[['list-light-nuova', 'form-light-nuova', 'form-dark-nuova'].map(k => [s[k].page.island, s[k].page.on]), __wiStyleDiff('list-light-base', 'list-light-nuova'), __wiStyleDiff('form-light-base', 'form-light-nuova'), __wiStyleDiff('list-dark-base', 'list-dark-nuova')]
```

Expected:
- `[[false, false], [false, false], [false, false]]`: l'app originale non mette l'attributo, quindi niente isola;
- tre confronti con `page: []` e un solo stile, `0 html scroll-padding-top: auto -> 50px`: il tema chiaro è invariato, e anche la lista in tema scuro.

Form in tema scuro: cambiano solo i bottoni scuri disabilitati, che nella lib nuova diventano leggibili.

```js
const d = __wiStyleDiff('form-dark-base', 'form-dark-nuova', 500);
const names = [...new Set(d.styles.map(s => s.split(' ').slice(0, 2).join(' ')))];
sessionStorage.setItem('wiI15btn', String(names.filter(n => /btn-(outline-)?dark/.test(n)).length));
[d.page, d.total, names]
```

Expected: `page: []`; in `names` ci sono solo `0 html`, elementi con `.btn-dark` o `.btn-outline-dark` ed elementi dentro di loro, per esempio `i.bi-*`.

Poi, in tema scuro sulla pagina vera: `localStorage.setItem('theme', 'dark')`, `navigate` a `https://new.test/backend/announcements/create/` e:

```js
[Number(sessionStorage.getItem('wiI15btn')), document.querySelectorAll('.btn-dark:disabled, .btn-outline-dark:disabled').length]
```

Expected: due numeri uguali. I bottoni scuri cambiati sono proprio quelli disabilitati, come Salva con il nome vuoto; quelli attivi restano come prima. Poi `localStorage.setItem('theme', 'light')` e ricarica.

Scroll al primo errore di validazione: `resize_window` a 1280x600, `navigate` a `https://new.test/backend/announcements/create/` e:

```js
window.scrollTo(0, document.documentElement.scrollHeight);
const form = document.querySelector('#resource-layout-form');
const valid = form.reportValidity();
await new Promise(resolve => setTimeout(resolve, 500));
const first = form.querySelector(':invalid:not(fieldset)');
[valid, first?.name, Math.round(first.getBoundingClientRect().top) >= Math.round(document.querySelector('#topbar').getBoundingClientRect().bottom)]
```

Expected: `[false, "name", true]`: con `scroll-padding-top` il primo campo non valido non finisce sotto la barra in alto.

Quill obbligatorio, come cambio voluto: `resize_window` a 1280x900, `navigate` a `https://new.test/backend/save-bar-probe/?mode=widgets` e incolla `stato.js`, `core.js` e `widget.js`. Se la pagina non si apre con l'app originale, scrivi `I15 | new.test | BLOCCATO | Quill obbligatorio: la pagina di prova non si apre con l'app originale` e passa oltre. Altrimenti `__wiEditorFocus('quill_required')` restituisce `"quill_required"`; poi `computer` `key` `cmd+a`, `computer` `type` con `x`, `computer` `key` `cmd+a` e `computer` `key` `Backspace`.

```js
[document.querySelector('textarea[name="quill_required"]').value, document.querySelector('#resource-layout-form [type="submit"]').disabled]
```

Expected: `["", true]`.

Rimetti l'app nuova. La lib è già nuova, il tema già chiaro.

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app new-site nuova
```

Torna al preset `desktop` e leggi la console.

```bash
printf '%s\n' 'I15 | new.test | OK | app originale: nessuna isola; tema chiaro invariato salvo scroll-padding-top; in scuro cambiano solo i bottoni scuri disabilitati; primo errore sotto la topbar; Quill obbligatorio vuoto disabilita Salva' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

- [ ] **Step 11: Caso I16, pagine vere a 375px e tema scuro**

`resize_window` con preset `mobile`, `navigate` a `https://new.test/backend/announcements/create/`, incolla gli helper e clicca il titolo. `__wiFocus('name')` restituisce `"name"`; poi `computer` `type` con `x`, un click sul titolo e `await __wiUntilDirty()` → uno stato `dirty-*`.

Il menu mobile vero copre l'isola. Il pulsante del menu è solo un'icona, quindi prima gli si dà un nome:

```js
document.getElementById('menu').setAttribute('aria-label', 'Menu mobile');
'ok'
```

`find` con `Menu mobile`, `left_click` sul `ref`, poi:

```js
const r = document.querySelector('.wi-save-bar-island').getBoundingClientRect();
[document.getElementById('sidebar').classList.contains('show'), !!document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)?.closest('#sidebar')]
```

Expected: `[true, true]`. Chiudi il menu con un nuovo `left_click` su `Menu mobile`.

Tema scuro con il CSS del sito: `localStorage.setItem('theme', 'dark')`, ricarica, incolla gli helper, clicca il titolo e rendi il form sporco come sopra.

```js
const css = getComputedStyle(document.querySelector('.wi-save-bar-island'));
[css.backgroundColor, css.color, css.backgroundColor !== css.color && css.backgroundColor !== 'rgba(0, 0, 0, 0)']
```

Expected: due colori diversi e `true`. Fai uno screenshot con `computer` `screenshot` come prova. Poi `localStorage.setItem('theme', 'light')` e ricarica.

Touch: `matchMedia('(pointer: coarse)').matches`.
- Se `true`: incolla gli helper, clicca il titolo, `__wiFocus('name')`, aspetta 300ms e leggi `[document.querySelector('.wi-save-bar').hasAttribute('data-wi-save-bar-keyboard'), getComputedStyle(document.querySelector('.wi-save-bar')).visibility]` → `[true, "hidden"]`. Dopo un click sul titolo, la stessa lettura dà `[false, "visible"]`.
- Se `false`: scrivi la riga del touch e la prova passa a M2.
  ```bash
  printf '%s\n' 'I16 | new.test | NON VERIFICATO | touch: pointer coarse false, passa a M2' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
  ```

`navigate` a `https://new.test/backend/` con `force: true`, torna al preset `desktop` e leggi la console.

```bash
printf '%s\n' "I16 | new.test | OK | a 375px il menu mobile copre l'isola; in tema scuro l'isola usa i colori del sito" >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

- [ ] **Step 12: Caso I18, `user()` fuori dal backend**

Il caso controlla che il nuovo `written` non rompa chi chiama `user()` fuori dal backend, come la registrazione del frontend. Lo script si ferma prima di creare l'utente se l'area chiede la verifica dell'email o ha una funzione di creazione del sito: in quel caso partirebbe una mail vera.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
N=$(date +%s | tail -c 7)
cat > "$W/i18-user.php" <<PHP
<?php
\$ROOT = '/Users/andreamarinoni/Developer/boilerplates/new-site';
\$GLOBALS['ROOT'] = \$ROOT;
chdir(\$ROOT);
\$FRONTEND = false;
\$BACKEND = false;
require \$ROOT.'/vendor/wonder-image/app/wonder-image.php';

\$P = permissionArea('frontend');
\$cfg = userPermissionEmailVerificationConfig(\$P, userPermissionVerificationRules(\$P), 'signup');
if (!empty(\$cfg['required']) || !empty(\$P->functionCreation ?? '')) {
    echo "BLOCCATO: area frontend con verifica email o funzione di creazione\n";
    exit(1);
}

\$result = \user(['name' => 'Save', 'surname' => 'Bar', 'email' => 'save-bar-i18-$N@example.com', 'area' => 'frontend', 'active' => 'true'], null);
echo json_encode(['id' => \$result->user->id ?? null, 'written' => \$result->written ?? null, 'alert' => \$GLOBALS['ALERT'] ?? null]), "\n";
PHP
"/Users/andreamarinoni/Library/Application Support/Herd/bin/php82" -d display_errors=1 -d error_reporting=E_ALL "$W/i18-user.php"
echo "email: save-bar-i18-$N@example.com"
```

Expected: una riga JSON con `id` maggiore di 0, `written: true` e `alert` `null` o vuoto, nessun `Warning`, `Notice` o `Deprecated`, poi la riga `email: ...`. Se compare `BLOCCATO: ...`, non è stato creato nessun utente: scrivi `I18 | new-site CLI | BLOCCATO | area frontend con verifica email o funzione di creazione` e passa oltre.

Con l'utente creato, annotalo e cancella lo script:

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
printf '%s\n' 'new.test | CLI user() area frontend | utente <email stampata sopra> | I18' >> "$W/record-di-prova.txt"
printf '%s\n' 'I18 | new-site CLI | OK | user() area frontend: utente creato, written true, nessun avviso' >> "$W/integrazione-esiti.txt"
rm "$W/i18-user.php"
```

- [ ] **Step 13: Controlla le righe del registro**

```bash
tail -12 "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
tail -3 "$HOME/.cache/wonder-tooling/save-bar/record-di-prova.txt"
```

Expected: le righe I11-I16 e I18 del task (per I12 una riga per new.test e una per ecommerce.test, più quella di Places se `BLOCCATO`; per I16 anche quella del touch se `NON VERIFICATO`), la categoria di I12 e l'utente di I18. Un caso non `OK` va scritto con l'esito vero e la nota, al posto della riga di esempio.

### Task R6: Moduli (casi I19, I20) e ricerca di ql-editor

Repo: nessun commit. Si lavora nel pannello Browser su ecommerce.test e immobili.test e in `$HOME/.cache/wonder-tooling/save-bar` (`W`), con i siti preparati nel Task R2 (app e `dist` nuove; immobili-site usa il worktree `backend-save-bar` del modulo e `APP_URL=https://immobili.test`). Nessun prodotto e nessun immobile si salva: ogni pagina si lascia con `force`, tranne l'invio di I20 che il server rifiuta.

| Caso | Pagine | Cosa prova |
|---|---|---|
| I19 | ecommerce.test `/backend/app/gestionale/prodotti/` e la modifica di un prodotto | griglia varianti, pannelli aperti e chiusi senza modifiche |
| I20 | immobili.test `/backend/immobili/`, `/backend/immobili/create/`, `/backend/residenze/create/` e due pagine di modifica | app vecchia e nuova, un solo Salva, errori del server, record da feed, foto salvate, riga media svuotata |
| `ql-editor` | sorgenti di siti e moduli | chi usa ancora le classi interne di Quill |

**Files:**
- Modify: `$W/integrazione-esiti.txt` (esiti di I19, I20 e della ricerca)
- Modify: `$W/record-di-prova.txt`, solo se l'invio di I20 crea un immobile

**Interfaces:**
- Consumes:
  - dal Task R2: `stato.js` (`__wiShown`, `__wiBar`), `casi.js` (`__wiUntil`, `__wiLogSubmits`, `__wiSubmits`, `__wiLeave`), `siti.sh`, la sessione di amministratore su ecommerce.test e immobili.test e le regole comuni dello Step 12;
  - dal Task R3: `markup.js` (`__wiEditLinks(limit)`);
  - dal Task R4: `core.js` (`__wiAlerts`, `__wiDanger`, `__wiUntilDirty`) e la regola "Click su un Salva della pagina" del suo Step 1;
  - dal Task R5: `widget.js` (`__wiLabelDelete`).
- Produces: le righe I19, I20 e `ql-editor` del registro, lette dal Task R8.

- [ ] **Step 1: Caso I19, gestionale su ecommerce.test**

`resize_window` a 1280x900, `navigate` a `https://ecommerce.test/backend/app/gestionale/prodotti/`, incolla `markup.js` ed esegui `await __wiEditLinks(1)`. Expected: un percorso `/backend/app/gestionale/prodotti/<id>/edit/`. Se la lista è vuota, scrivi `I19 | ecommerce.test | BLOCCATO | nessun prodotto nella lista` e passa allo Step 2.

`navigate` a `https://ecommerce.test` più quel percorso e incolla `stato.js`, `casi.js` e `core.js`.

```js
[document.querySelectorAll('#resource-layout-form [name="sku"]').length, document.querySelector('#resource-layout-form')?.hasAttribute('data-wi-save-bar')]
```

Expected: un numero maggiore o uguale a 1 e `true`. La griglia varianti legge lo SKU con `#resource-layout-form [name="sku"]`, quindi il selettore deve trovare ancora il campo.

Aspetta 3 secondi con `computer` `wait` e clicca il titolo. Poi dai un nome ai comandi dei pannelli presenti:

```js
const out = [];
const toggle = [...document.querySelectorAll('.wi-repeater-group-toggle')].find(__wiShown);
if (toggle) { toggle.setAttribute('aria-label', 'Chiudi gruppo 1'); out.push('Chiudi gruppo 1'); }
const stock = [...document.querySelectorAll('[data-bs-target="#wi-location-stock"]')].find(__wiShown);
if (stock) { stock.setAttribute('aria-label', 'Apri giacenza'); out.push('Apri giacenza'); }
document.querySelector('#wi-location-stock [data-bs-dismiss="modal"].btn-secondary')?.setAttribute('aria-label', 'Annulla giacenza');
out
```

Per ogni nome restituito, e poi per l'Accordion:
- `Chiudi gruppo 1`: `find` e `left_click` due volte, per chiudere e riaprire il gruppo di varianti;
- `Apri giacenza`: `find` e `left_click`, aspetta 1 secondo, poi `find` con `Annulla giacenza` e `left_click`;
- l'Accordion: `find` con `Compila le informazioni avanzate`; se esiste (il prodotto non ha varianti), `left_click` due volte.

Annota quali pannelli c'erano. Poi:

```js
[await __wiBar(1500), __wiLeave()]
```

Expected: un solo form pulito e `false`. Se il form risulta sporco, il caso è `FALLITO`: la correzione è un `absorb()` documentato nel modulo, non un cambio dell'isola.

`navigate` a `https://ecommerce.test/backend/` con `force: true` e leggi la console.

```bash
printf '%s\n' 'I19 | ecommerce.test | OK | sku trovato; aperti e chiusi senza modifiche: <pannelli presenti>; form pulito e nessun avviso di uscita' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

- [ ] **Step 2: Caso I20, app vecchia e app nuova**

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app immobili-site originale
```

`navigate` a `https://immobili.test/backend/immobili/create/`:

```js
[document.querySelectorAll('[data-wi-save-bar]').length, !!document.querySelector('.wi-save-bar'), !!document.querySelector('#immobile-resource-form')]
```

Expected: `[0, false, true]`: l'app vecchia ignora l'opzione del modulo. Leggi la console: nessun errore, secondo la regola comune della console.

```bash
"$HOME/.cache/wonder-tooling/save-bar/siti.sh" app immobili-site nuova
```

Ricarica la pagina:

```js
[document.querySelector('#immobile-resource-form')?.hasAttribute('data-wi-save-bar'), !!document.querySelector('.wi-save-bar')]
```

Expected: `[true, true]`.

- [ ] **Step 3: Caso I20, un solo Salva in ResidenzaResource**

`navigate` a `https://immobili.test/backend/residenze/create/`, incolla `stato.js`, `casi.js` e `core.js` e porta la finestra all'altezza che mette i Salva sotto la fascia (regola comune "Finestra alta quanto serve").

```js
[[...document.querySelectorAll('form[data-wi-save-bar] [type="submit"], form[data-wi-save-bar] .wi-submit')].filter(el => el.getClientRects().length > 0).length, await __wiUntil('buttons'), (await __wiBar()).copies]
```

Expected: `1`, `"buttons"` e una sola copia: un solo Salva nella pagina e uno nell'isola.

Torna al preset `desktop`.

- [ ] **Step 4: Caso I20, errori del server**

`navigate` a `https://immobili.test/backend/immobili/create/`, incolla `stato.js`, `casi.js` e `core.js` e clicca il titolo. Togli la validazione del browser, così il form arriva al server con i campi obbligatori vuoti:

```js
const form = document.querySelector('#immobile-resource-form');
form.noValidate = true;
form.querySelectorAll('[required]').forEach(el => el.removeAttribute('required'));
form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = false; });
[form.noValidate, form.querySelectorAll('[required]').length, __wiLogSubmits()]
```

Expected: `[true, 0, "registro invii attivo"]`. Clicca il Salva della pagina (`find`, `scroll_to`, `left_click`) e aspetta il caricamento. Incolla di nuovo gli helper:

```js
[location.pathname, __wiSubmits().length, __wiDanger(), __wiAlerts(), await __wiUntilDirty(), (await __wiBar()).forms[0].dirtyAttr]
```

Expected: `/backend/immobili/create/`, `1`, un testo di errore o almeno un codice di `alertToast`, uno stato `dirty-*` e `true`: la pagina ridisegnata con `FORM_ERRORS` si apre sporca.

Se invece il percorso è una pagina `/edit/`, il server ha creato un immobile: annotalo in `record-di-prova.txt` (`immobili.test | /backend/immobili/create/ | immobile <id dal percorso> | I20`) e scrivi `I20 | immobili.test | BLOCCATO | FORM_ERRORS: il server ha accettato il form vuoto`.

`navigate` a `https://immobili.test/backend/` con `force: true` e leggi la console.

- [ ] **Step 5: Caso I20, record da feed, foto salvate e riga media svuotata**

`navigate` a `https://immobili.test/backend/immobili/` e incolla `markup.js`. Le pagine di modifica si leggono con `fetch`, che fa solo GET:

```js
const links = await __wiEditLinks(25);
const pages = await Promise.all(links.map(async path => [path, await (await fetch(path, { credentials: 'same-origin' })).text()]));
const feed = pages.find(([, html]) => html.includes('const feedRecord = true'))?.[0] ?? null;
const photos = pages
    .map(([path, html]) => [path, [...new DOMParser().parseFromString(html, 'text/html').querySelectorAll('[name^="images["][name$="[preview_url]"]')].filter(input => input.value).length])
    .filter(([, count]) => count > 0)
    .sort((a, b) => a[1] - b[1])[0] ?? null;
sessionStorage.setItem('wiI20', JSON.stringify({ feed, photos }));
[links.length, feed, photos]
```

Expected: il numero di pagine lette, un percorso per `feed` e `[percorso, numero di foto]` per `photos` (il record con meno foto). Se uno dei due è `null`, scrivi la sua riga `BLOCCATO` (`I20 | immobili.test | BLOCCATO | nessun record da feed tra le righe lette`, oppure `... | nessun record con foto salvate tra le righe lette`) e salta la sua parte.

Record da feed: `navigate` al percorso di `feed`, incolla `stato.js`, `casi.js` e `core.js`, aspetta 3 secondi e clicca il titolo.

```js
[await __wiBar(1500), __wiLeave()]
```

Expected: un solo form pulito e `false`: `lockFeedFields` e `noValidate` non sporcano il form.

Foto salvate: `navigate` al percorso di `photos`, incolla gli stessi helper e `widget.js`, aspetta 3 secondi e clicca il titolo. `await __wiBar(1500)` restituisce un solo form pulito: FilePond che carica le immagini salvate non sporca il form.

Riga media svuotata, sulla stessa pagina. La pagina ha più repeater (foto, planimetrie, link): `__wiLabelDelete()` numera i cestini di tutte le righe visibili e lo snippet tiene solo quelli delle foto.

```js
const labels = __wiLabelDelete();
const rows = [...document.querySelectorAll('.wi-repeater-row')].filter(__wiShown);
labels.filter((label, i) => rows[i].querySelector('[name^="images["]'))
```

Expected: i cestini delle foto, per esempio `["Cestino riga 3", "Cestino riga 4"]`. Clicca il primo (`find` con il suo nome e `left_click`), poi `find` con `Conferma eliminazione riga` e `left_click`. Riesegui lo snippet, che rinumera i cestini, e ripeti finché resta un solo cestino delle foto; elimina anche quello. Il repeater può aggiungere righe, quindi l'ultima riga si svuota invece di sparire.

```js
const photos = [...document.querySelectorAll('.wi-repeater-row')].filter(row => __wiShown(row) && row.querySelector('[name^="images["]'));
[photos.map(row => [row.querySelectorAll('.filepond--item').length, !!row.querySelector('.filepond--root')]), await __wiUntilDirty()]
```

Expected: `[[0, true]]` e uno stato `dirty-*`: resta una riga vuota, FilePond si ricrea e il form è sporco.

`navigate` a `https://immobili.test/backend/` con `force: true`: le foto non si salvano. Leggi la console.

```bash
printf '%s\n' 'I20 | immobili.test | OK | app vecchia senza attributo e isola; app nuova con data-wi-save-bar su #immobile-resource-form; un solo Salva in ResidenzaResource; FORM_ERRORS sporco; record da feed e foto salvate puliti; riga media svuotata sporca con FilePond ricreato' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

- [ ] **Step 6: Ricerca di `ql-editor` in siti e moduli**

Il Task I1 cambia il valore di un Quill vuoto e l'ascolto degli eventi: chi legge le classi interne di Quill potrebbe dipenderne. `rg` non c'è, e sul Mac `grep` è ugrep.

```bash
cd /Users/andreamarinoni/Developer; grep -rn -I --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=dist --exclude-dir=.claude --exclude-dir=.git --exclude='*.min.*' --exclude='*.map' -F "ql-editor" boilerplates clients packages/assets packages/blog packages/ecommerce packages/form packages/gallery packages/gestionale packages/getrix packages/immobili packages/menu packages/rsvp 2>/dev/null | grep -v '/assets/lib/' | cut -c1-120
```

Expected: esattamente 3 righe, tutte in `hydrorobic-it`: `function/backend/input.php:294`, `function/backend/input.php:297` e `assets/v.2.3/css/backend/add.css:44:.ql-editor{`. Il sito è legacy e non usa l'app, quindi il cambio non lo tocca.

```bash
printf '%s\n' "ql-editor | siti e moduli | OK | solo hydrorobic-it (legacy, non usa l'app): function/backend/input.php:294,297 e assets/v.2.3/css/backend/add.css:44" >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

Se compaiono altre righe, scrivi `ql-editor | siti e moduli | FALLITO | <file:riga>` per ognuna che legge il contenuto o il valore di Quill: va adeguata prima del rilascio.

- [ ] **Step 7: Controlla le righe del registro**

```bash
tail -6 "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

Expected: le righe I19, I20 e `ql-editor` del task, più eventuali righe `BLOCCATO` di I20. Un caso non `OK` va scritto con l'esito vero e la nota, al posto della riga di esempio.

### Task R7: Prove manuali M1-M3 dell'utente

Repo: nessun commit. Le prove le fa l'utente, sul suo Chrome e sui suoi telefoni. Claude prepara le pagine, gli manda i testi di questo Task e annota gli esiti in `$HOME/.cache/wonder-tooling/save-bar` (`W`).

Il Task si fa dopo i Task R3-R6 e prima del ripristino del Task R8, con i siti ancora preparati dal Task R2. Gli strumenti di Claude non riproducono queste prove:
- il pannello Browser non ha password salvate e manda i clic come mouse;
- il simulatore iOS richiede Xcode;
- un telefono non raggiunge `*.test`.

Per ogni prova l'esito ammesso, oltre a `OK` e `FALLITO`, è `NON VERIFICATO` con il motivo (regole comuni del Task R2, Step 12).

**Files:**
- Create: `$W/tunnel-env.sh` e `$W/tunnel-env-test.sh` (`APP_URL` temporaneo di new-site per il tunnel di M2, e la sua prova)
- Modify: `$W/integrazione-esiti.txt` (righe M1, M2 e M3)
- Modify, temporaneo: la riga `APP_URL` di `new-site/.env` durante M2, con la copia intera in `/Users/andreamarinoni/Developer/boilerplates/.save-bar-backup/new-site/env`

**Interfaces:**
- Consumes:
  - dal Task R2 i siti preparati, la Resource di prova `/backend/save-bar-probe/` e le regole comuni dello Step 12;
  - dal Task R3 la riga I10: i tre campi segreti di SecurityResource hanno `autocomplete=new-password` e il form si apre pulito;
  - dal Task P5 `data-wi-save-bar-ignore` sulla password di conferma del profilo.
- Produces:
  - `tunnel-env.sh apri https://<host>` e `tunnel-env.sh chiudi`;
  - le righe `M1`, `M2` e `M3` di `integrazione-esiti.txt`;
  - `new-site/.env` com'era prima di M2, senza copia rimasta in `.save-bar-backup/new-site/env`.

- [ ] **Step 1: Stato di partenza**

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
SITES=/Users/andreamarinoni/Developer/boilerplates
readlink "$SITES/new-site/vendor/wonder-image/app"
js=$(curl -sk https://new.test/backend/account/login/ | grep -o 'https://new.test/assets/lib/wonder-image/dist/backend/head\.js?v=[0-9]*' | head -1)
printf 'head.js wiSaveBar %s\n' "$(curl -sk "$js" | grep -c wiSaveBar)"
curl -sk -o /dev/null -w '%{http_code} %{redirect_url}\n' https://new.test/backend/save-bar-probe/
grep -c '^I10 | new.test | OK' "$W/integrazione-esiti.txt"
grep -c '^M[123] |' "$W/integrazione-esiti.txt"
test ! -e "$SITES/.save-bar-backup/new-site/env" && echo "nessun backup di .env"
grep -n '^APP_URL=' "$SITES/new-site/.env"
xcode-select -p
xcrun --find simctl 2>/dev/null || echo "simctl assente"
test -f "$HOME/Library/Application Support/Herd/config/valet/CA/LaravelValetCASelfSigned.pem" && echo "CA di Herd presente"
(cd "$SITES/new-site" && herd share --help | head -2)
```

Expected:
- `/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar`;
- `head.js wiSaveBar N`, con N almeno 1;
- `302 https://new.test/backend/account/login/?redirect=...`: la Resource di prova c'è e chiede il login;
- `1`, cioè I10 è passato su new.test;
- `0`, cioè nessuna riga M scritta;
- `nessun backup di .env`;
- `3:APP_URL=https://new.test`;
- oggi `/Library/Developer/CommandLineTools` e `simctl assente`: Xcode non è installato e M3 sarà `NON VERIFICATO` (Step 7);
- `CA di Herd presente`;
- `Description:` e `  Share a local url with a remote expose server`.

Con un `readlink` diverso o N uguale a `0`, new-site non è preparato: fermati e rifai il Task R2 dallo Step 7. Con I10 a `0`, fermati: il controllo di Chrome su SecurityResource presuppone i campi di I10.

- [ ] **Step 2: Script dell'APP_URL temporaneo e sua prova**

Un telefono raggiunge new-site solo con il tunnel pubblico di `herd share`, e tutti gli URL assoluti e i redirect dell'app vengono da `APP_URL`. Senza il cambio, il login dal telefono rimanda a `https://new.test`, che il telefono non raggiunge. `tunnel-env.sh` cambia solo quella riga, tiene la copia intera di `.env` nel backup di new-site e non stampa altro che la riga `APP_URL`. `siti.sh ripristina` rimette comunque `.env` da `$BACKUP/new-site/env` se la copia è ancora lì.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
cat > "$W/tunnel-env.sh" <<'SH'
#!/bin/bash
# APP_URL temporaneo di new-site per M2: apri <url> lo punta al tunnel, chiudi lo rimette com'era.
set -euo pipefail

SITES=${SITES:-/Users/andreamarinoni/Developer/boilerplates}
BACKUP=${BACKUP:-$SITES/.save-bar-backup}
root=$SITES/new-site
bk=$BACKUP/new-site

fail() { echo "$*" >&2; exit 1; }

case "${1:-}" in
apri)
    url=${2:-}
    [[ "$url" =~ ^https://[A-Za-z0-9.-]+$ ]] || fail "URL non valido: serve https://<host> senza / finale"
    [ -d "$bk/dist" ] || fail "manca $bk/dist: new-site non e preparato"
    [ ! -e "$bk/env" ] || fail "backup di .env gia presente: esegui prima tunnel-env.sh chiudi"
    grep -q '^APP_URL=' "$root/.env" || fail "APP_URL assente in $root/.env"
    cp -p "$root/.env" "$bk/env"
    sed -i '' "s#^APP_URL=.*#APP_URL=$url#" "$root/.env"
    grep -n '^APP_URL=' "$root/.env"
    ;;
chiudi)
    [ -f "$bk/env" ] || fail "manca $bk/env: niente da ripristinare"
    cp -p "$bk/env" "$root/.env"
    cmp -s "$bk/env" "$root/.env" || fail "DIVERSO: .env"
    rm "$bk/env"
    echo "uguale: .env"
    grep -n '^APP_URL=' "$root/.env"
    ;;
*) fail "uso: tunnel-env.sh apri https://<host> | chiudi" ;;
esac

herd restart
SH
cat > "$W/tunnel-env-test.sh" <<'SH'
#!/bin/bash
# Prova di tunnel-env.sh su un new-site finto, con herd finto nel PATH.
set -uo pipefail
T=$(cd "$(dirname "$0")" && pwd)/tmp-tunnel
rm -rf "$T"; mkdir -p "$T/bin" "$T/sites/new-site" "$T/sites/.save-bar-backup/new-site/dist"
printf '#!/bin/sh\necho "herd $*" >> "%s/herd.log"\n' "$T" > "$T/bin/herd"; chmod +x "$T/bin/herd"
export PATH="$T/bin:$PATH" SITES="$T/sites"
S="$T/../tunnel-env.sh"
E=$T/sites/new-site/.env
B=$T/sites/.save-bar-backup/new-site
printf 'APP_KEY=segreto\nAPP_DOMAIN=new.test\nAPP_URL=https://new.test\nDB_PASS=x\n' > "$E"
touch -t 202001010000 "$E"
cp -p "$E" "$T/env-originale"
failures=0
expect() { if eval "$1"; then echo "ok   $2"; else echo "FAIL $2"; failures=$((failures+1)); fi; }

"$S" apri https://abc.sharedwithexpose.com/ > "$T/out1" 2>&1; expect '[ $? = 1 ] && cmp -s $E $T/env-originale && [ ! -e $B/env ]' "URL con / finale rifiutato"
"$S" apri 'http://abc.sharedwithexpose.com' > "$T/out1" 2>&1; expect '[ $? = 1 ] && cmp -s $E $T/env-originale && [ ! -e $B/env ]' "URL senza https rifiutato"
"$S" apri https://abc.sharedwithexpose.com > "$T/out2" 2>&1; expect '[ $? = 0 ]' "apri"
expect 'grep -q "^APP_URL=https://abc.sharedwithexpose.com$" $E && grep -q "^APP_DOMAIN=new.test$" $E && grep -q "^DB_PASS=x$" $E' ".env: solo APP_URL cambiato"
expect 'cmp -s $B/env $T/env-originale' "backup uguale all'originale"
expect '! grep -q -e segreto -e DB_PASS $T/out2' "nessun segreto stampato"
"$S" apri https://altro.sharedwithexpose.com > "$T/out3" 2>&1; expect '[ $? = 1 ] && grep -q "gia presente" $T/out3 && grep -q abc.sharedwithexpose.com $E' "seconda apertura rifiutata"
"$S" chiudi > "$T/out4" 2>&1; expect '[ $? = 0 ] && grep -q "uguale: .env" $T/out4 && grep -q "APP_URL=https://new.test$" $T/out4' "chiudi"
expect 'cmp -s $E $T/env-originale && [ ! $E -nt $T/env-originale ] && [ ! -e $B/env ]' ".env com'era, date comprese, backup tolto"
expect '! grep -q -e segreto -e DB_PASS $T/out4' "nessun segreto stampato alla chiusura"
"$S" chiudi > "$T/out5" 2>&1; expect '[ $? = 1 ] && grep -q "niente da ripristinare" $T/out5' "seconda chiusura rifiutata"
rm -rf "$B/dist"; "$S" apri https://abc.sharedwithexpose.com > "$T/out6" 2>&1; expect '[ $? = 1 ] && cmp -s $E $T/env-originale && [ ! -e $B/env ]' "sito non preparato: nessuna modifica"
expect '[ "$(grep -c "herd restart" $T/herd.log)" = 2 ]' "herd restart dopo apri e chiudi"

echo "failures=$failures"; [ $failures = 0 ] && echo "tunnel-env.sh ok"
SH
chmod +x "$W/tunnel-env.sh" "$W/tunnel-env-test.sh"
bash -n "$W/tunnel-env.sh" && bash -n "$W/tunnel-env-test.sh" && echo "sintassi ok"
"$W/tunnel-env-test.sh" | tail -2
rm -rf "$W/tmp-tunnel"
```

Expected: `sintassi ok`, poi `failures=0` e `tunnel-env.sh ok`. Se compare una riga `FAIL`, lancia `"$W/tunnel-env-test.sh" | grep FAIL`, correggi lo script e ripeti. Lo script va usato su new-site solo quando la prova è verde.

- [ ] **Step 3: M1, testo per l'utente**

M1 si fa nel Chrome dell'utente, l'unico con la password salvata. Manda all'utente questo testo:

```text
Mi serve una prova sul tuo Chrome, non nel pannello Browser: solo il tuo Chrome ha le password salvate.

Prima di tutto: in Chrome deve esserci la password salvata per new.test. Se non c'e, entra in https://new.test/backend/ con l'amministratore locale e salva la password quando Chrome lo propone.

Pagina account
1. Apri https://new.test/backend/account/
2. Clicca una volta sul titolo "Impostazioni account" e aspetta 2 secondi: dopo il primo clic Chrome compila i campi.
3. Nel riquadro "Modifica dati", la password di conferma e stata compilata da Chrome (ci sono i puntini)?
4. In basso compare una barra con "Modifiche non salvate"? Non deve comparire. Non premere Salva.
5. Se provi a uscire dalla pagina, Chrome chiede conferma? Non deve chiederla.

Pagina credenziali
6. Apri https://new.test/backend/app/config/credentials/1/edit/
7. Clicca una volta sul titolo della pagina e aspetta 2 secondi.
8. Chrome ha scritto la tua password di accesso in uno dei campi segreti (le chiavi di Klaviyo e di Brevo, la password della mail)? Compare "Modifiche non salvate"?
9. Non premere Salva: salveresti le credenziali vere del sito. Se uscendo Chrome chiede conferma, esci senza salvare.

Se vuoi mandarmi i valori esatti, apri la Console (Cmd+Opt+J). Se Chrome lo chiede, scrivi "allow pasting" e premi Invio. Poi incolla la riga della pagina in cui ti trovi e mandami il risultato. Le righe non mostrano le password: dicono solo se un campo e pieno o e cambiato.

Pagina account:
[...document.querySelectorAll('form[data-wi-save-bar]')].map((f, n) => 'form ' + n + ' sporco=' + wiSaveBar.isDirty(f)).concat([...document.querySelectorAll('form[data-wi-save-bar] input[type="password"]')].map(i => i.name + ' compilato=' + (i.value !== '') + ' autofill=' + i.matches(':autofill')), 'stato=' + document.querySelector('.wi-save-bar')?.dataset.wiState)

Pagina credenziali:
(f => ['sporco=' + wiSaveBar.isDirty(f), 'stato=' + document.querySelector('.wi-save-bar')?.dataset.wiState].concat([...f.querySelectorAll('input[type="password"]')].map(i => i.name + ' cambiato=' + (i.value !== i.defaultValue) + ' autofill=' + i.matches(':autofill'))))(document.getElementById('resource-layout-form'))
```

La password la salva e la scrive solo l'utente: Claude non la vede e non la scrive.

- [ ] **Step 4: M1, esiti**

Esito atteso sulla pagina account:
- l'utente vede la password di conferma compilata, nessuna barra "Modifiche non salvate" e nessuna conferma all'uscita;
- con la riga della Console: `form 0 sporco=false`, `password compilato=true autofill=true` e `stato=hidden`, oppure `stato=buttons` se il Salva della pagina è sotto la fascia.

Esito atteso sulla pagina delle credenziali:
- nessun campo segreto compilato da Chrome e nessuna barra "Modifiche non salvate";
- con la riga della Console: `sporco=false`, `stato=hidden` oppure `stato=buttons`, e `cambiato=false autofill=false` per `klaviyo_api_key`, `brevo_api_key` e `mail_password`.

Scrivi una riga per pagina. Con l'esito atteso:

```bash
printf '%s\n' \
  'M1 | new.test | OK | account: Chrome compila la password di conferma, form pulito, nessuna conferma di uscita' \
  'M1 | new.test | OK | SecurityResource: Chrome non scrive la password di login nei campi segreti, form pulito' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

Con un esito diverso scrivi le righe a mano con lo stesso formato:
- `FALLITO` se l'account risulta sporco con la sola password di conferma compilata, oppure se Chrome scrive in un campo segreto. Nella nota va cosa si è visto e, per le credenziali, il nome del campo;
- `NON VERIFICATO` se Chrome non ha compilato la password di conferma, per esempio `M1 | new.test | NON VERIFICATO | account: Chrome non ha compilato la password di conferma (nessuna password salvata per new.test)`.

Un `FALLITO` di M1 si discute con l'utente prima del Task R8: il Task R8 si ferma sulle righe `FALLITO`.

- [ ] **Step 5: M2, decisione dell'utente e apertura del tunnel**

`herd share` apre un tunnel pubblico verso new-site: la decisione spetta all'utente, e anche il comando lo lancia lui nel suo terminale. Durante M2 non si fanno altri casi su new.test, perché le sue pagine rimandano all'indirizzo del tunnel. Manda all'utente questo testo:

```text
Per M2 serve un telefono vero, e un telefono non raggiunge new.test. L'unico modo e il tunnel pubblico di herd share: finche resta aperto, new-site e raggiungibile da internet a un indirizzo casuale. Decidi tu se aprirlo.

Se lo apri:
1. In un tuo terminale esegui:
   cd /Users/andreamarinoni/Developer/boilerplates/new-site && herd share
   Per proteggere il tunnel puoi aggiungere --basicAuth="<utente>:<password>", scelti da te: non dirmeli. Se Herd chiede un token di Expose, configuralo tu.
2. Mandami l'indirizzo https che stampa. Lo imposto come APP_URL di new-site finche il tunnel e aperto, poi lo rimetto com'era.

Se non lo apri, segno M2 come non verificato.
```

Se l'utente non apre il tunnel, scrivi la riga e passa allo Step 7:

```bash
printf '%s\n' 'M2 | new.test | NON VERIFICATO | utente non ha aperto il tunnel pubblico di herd share' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

Se l'utente manda l'indirizzo, sostituisci `<url>` con l'indirizzo senza `/` finale ed esegui:

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
U='<url>'
"$W/tunnel-env.sh" apri "$U"
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "$U/backend/save-bar-probe/"
js=$(curl -s "$U/backend/account/login/" | grep -o "$U/assets/lib/wonder-image/dist/backend/head\.js?v=[0-9]*" | head -1)
printf 'head.js wiSaveBar %s\n' "$(curl -s "$js" | grep -c wiSaveBar)"
```

Expected:
- `3:APP_URL=<url>` e l'output di `herd restart`;
- `302 <url>/backend/account/login/?redirect=...`: il redirect usa l'indirizzo del tunnel;
- `head.js wiSaveBar N`, con N almeno 1.

Con `--basicAuth` la prima `curl` dà `401` e il conteggio è `0`: in quel caso i controlli li fa il telefono, con le credenziali che conosce solo l'utente. Se il redirect punta ancora a `https://new.test`, la riga non è cambiata: esegui `"$W/tunnel-env.sh" chiudi` e fermati.

- [ ] **Step 6: M2, prova sul telefono, esiti e chiusura del tunnel**

Manda all'utente questo testo, con `<url>` sostituito:

```text
Il tunnel e pronto. Sul telefono:
1. Apri <url>/backend/ ed entra con l'amministratore locale.
2. Apri <url>/backend/save-bar-probe/: e una pagina di prova che non salva niente.
3. Tocca il campo "Nome". Con la tastiera aperta la barra in basso non deve vedersi.
4. Aggiungi una lettera e chiudi la tastiera (tocca fuori dal campo oppure "Fine"). Compare la barra con "Modifiche non salvate" o con Salva: deve stare tutta sopra la barra dei gesti di Android o sopra la linea della home di iPhone, senza esserne coperta.
5. Non serve premere Salva.

Fallo con Safari su iPhone e con Chrome 135 o successivo su Android, con la navigazione a gesti attiva nelle impostazioni del telefono. Per ogni telefono mandami il modello, la versione del sistema e del browser e cosa hai visto ai punti 3 e 4. Se puoi, aggiungi uno screenshot del punto 4.

Quando hai finito, chiudi il tunnel con Ctrl+C nel terminale e avvisami.
```

Quando l'utente avvisa che il tunnel è chiuso:

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
"$W/tunnel-env.sh" chiudi
test ! -e /Users/andreamarinoni/Developer/boilerplates/.save-bar-backup/new-site/env && echo "nessun backup di .env"
curl -sk -o /dev/null -w '%{http_code} %{redirect_url}\n' https://new.test/backend/save-bar-probe/
```

Expected: `uguale: .env`, `3:APP_URL=https://new.test`, l'output di `herd restart`, `nessun backup di .env` e `302 https://new.test/backend/account/login/?redirect=...`.

Se l'utente non avvisa ma il tunnel risulta chiuso, esegui comunque `chiudi`: finché la copia resta nel backup, new.test rimanda a un indirizzo che non esiste più.

Scrivi una riga per telefono, con i valori mandati dall'utente al posto di quelli fra `<>`. Con l'esito atteso:

```bash
printf '%s\n' \
  'M2 | new.test via herd share, Safari <versione> su iPhone <modello> iOS <versione> | OK | isola sopra la linea della home, nascosta con la tastiera aperta' \
  'M2 | new.test via herd share, Chrome <versione> su <modello> Android <versione> | OK | isola sopra la barra dei gesti, nascosta con la tastiera aperta' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

Con un esito diverso scrivi la riga a mano: `FALLITO` con cosa si è visto (isola coperta, isola sopra la tastiera), oppure `NON VERIFICATO` con il motivo per il telefono che manca, per esempio `M2 | new.test via herd share | NON VERIFICATO | nessun telefono Android con Chrome 135+`.

- [ ] **Step 7: M3, simulatore iOS**

M3 ripete i controlli di M2 nel simulatore iOS e richiede Xcode. Con `simctl assente` allo Step 1, come oggi, scrivi:

```bash
printf '%s\n' 'M3 | new.test | NON VERIFICATO | Xcode non installato (xcode-select -p = /Library/Developer/CommandLineTools, simctl assente)' >> "$HOME/.cache/wonder-tooling/save-bar/integrazione-esiti.txt"
```

Se invece l'utente ha installato Xcode e lo Step 1 ha stampato il percorso di `simctl`:
1. Claude elenca i simulatori con `xcrun simctl list devices available | grep -m3 iPhone`, avvia quello scelto dall'utente con `xcrun simctl boot "<nome>"` e apre il pannello con `control` e `action: "attach"`.
2. La CA di Herd la installa l'utente nel simulatore:
   `xcrun simctl keychain booted add-root-cert "$HOME/Library/Application Support/Herd/config/valet/CA/LaravelValetCASelfSigned.pem"`.
3. L'utente disattiva la tastiera hardware (menu I/O > Keyboard > Connect Hardware Keyboard), perché la prova vuole la tastiera del telefono.
4. L'utente fa il login in Safari del simulatore su `https://new.test/backend/`.
5. Claude apre `https://new.test/backend/save-bar-probe/` con `control` e `action: "open_url"`, poi tocca il campo "Nome" e fa `screenshot`: con la tastiera aperta l'isola non si vede.
6. Claude scrive una lettera con `action: "text"`, chiude la tastiera con "Done" e fa `screenshot`: l'isola è sopra la linea della home e non ne è coperta.

In quel caso scrivi `M3 | new.test | OK | simulatore <modello> iOS <versione>: isola sopra la linea della home, nascosta con la tastiera aperta`, oppure `FALLITO` con cosa si vede negli screenshot.

- [ ] **Step 8: Riepilogo delle prove manuali**

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
grep '^M[123] |' "$W/integrazione-esiti.txt"
test ! -e /Users/andreamarinoni/Developer/boilerplates/.save-bar-backup/new-site/env && echo "nessun backup di .env"
grep -n '^APP_URL=' /Users/andreamarinoni/Developer/boilerplates/new-site/.env
```

Expected:
- due righe `M1`, una o due righe `M2` e una riga `M3`, ciascuna con `OK`, `FALLITO` o `NON VERIFICATO`;
- `nessun backup di .env`;
- `3:APP_URL=https://new.test`.

Riassumi all'utente le righe M. Le righe `NON VERIFICATO` sono ammesse dal criterio 5 della 6.6; le righe `FALLITO` si discutono con lui prima del Task R8.

### Task R8: Chiusura dell'integrazione e ripristino dei siti

Repo: nessun commit. Si lavora in `$HOME/.cache/wonder-tooling/save-bar` (`W`), nei worktree del Task S1 e nei tre siti preparati dal Task R2.

Il Task si fa dopo i Task R3-R7. I criteri 1-6 della sez. 6.6 si controllano per primi, con i siti ancora preparati: se un controllo fallisce, il caso si può rifare subito, senza preparare di nuovo i siti. Solo dopo si ripristinano i siti (criterio 7), si consegnano i record creati e si riassume l'esito all'utente. I criteri I21 e I22 e la checklist "Dopo il rilascio" sono del Task R9.

**Files:**
- Create: `$W/npm-test.txt`, `$W/browser-test.txt` e `$W/dumpautoload.txt` (uscite dei controlli finali)
- Modify: `$W/app-after.txt` e `$W/gestionale-after.txt` (riscritti con lo stato finale), `$W/<sito>-status-after.txt` e `$W/<sito>-links-after.txt` (scritti da `siti.sh ripristina`)
- Modify, ripristino: nei tre siti `vendor/wonder-image/app` e `assets/lib/wonder-image/dist`; in immobili-site anche `vendor/wonder-image/immobili` e `.env`; in new-site `.env`, solo se il tunnel di M2 è rimasto aperto
- Delete: `new-site/app/Resources/SaveBarProbe/` (con `siti.sh ripristina`); `/Users/andreamarinoni/Developer/boilerplates/.save-bar-backup` (solo se il controllo dello Step 9 passa); i worktree staccati `$W/app-base` e `$W/lib-base`; le build `$W/lib-dist` e `$W/lib-base-dist`
- Test: `npm test` e test browser della lib, test dell'app, del gestionale e di immobili (nessun file nuovo)

**Interfaces:**
- Consumes:
  - dal Task S1 `LIB_WT`, `APP_WT`, `IMM_WT`, `SKL_WT`, Playwright nella cache, `app-baseline.txt` e `gestionale-baseline.txt`;
  - dal Task P11 `$W/app-prepend.php`;
  - dal Task R2 `siti.sh`, i file `*-status-before.txt` e `*-links-before.txt`, i backup in `.save-bar-backup`, `integrazione-esiti.txt`, `record-di-prova.txt` e le regole comuni dello Step 12;
  - dai Task R3-R6 le righe I1-I20 e la riga della ricerca di `ql-editor`;
  - dal Task R7 le righe M1-M3 e `tunnel-env.sh chiudi`.
- Produces:
  - i criteri 1-7 della sez. 6.6 controllati e riassunti all'utente, con la matrice degli esiti per caso e per host;
  - new-site, ecommerce-site e immobili-site com'erano prima del Task R2, senza backup rimasti;
  - i worktree dei branch (`LIB_WT`, `APP_WT`, `IMM_WT`, `SKL_WT`) e la cartella `W` restano per il Task R9;
  - `record-di-prova.txt` consegnato all'utente.

- [ ] **Step 1: Registro degli esiti (criterio 5)**

Il registro si scrive solo in coda. Due convenzioni chiudono una riga `FALLITO` o `BLOCCATO` senza cancellarla:
- **caso rifatto:** dopo una correzione il caso si rifà sullo stesso host e si aggiunge `<caso> | <host> | OK | rifatto: <nota>`;
- **eccezione accettata:** solo se l'utente la accetta in chat, si aggiunge `<caso> | <host> | FALLITO | eccezione accettata: <motivo e data>`. Claude non scrive mai un'eccezione di sua iniziativa.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
E="$W/integrazione-esiti.txt"
for c in I{1..20} M1 M2 M3; do grep -q "^$c |" "$E" || echo "manca $c"; done
printf 'righe con ql-editor: %s\n' "$(grep -ci 'ql-editor' "$E")"
echo "--- righe aperte"
awk -F' [|] ' 'NR > 1 { k = $1 " | " $2 } NR > 1 && $4 ~ /^eccezione accettata/ { delete aperti[k]; next } NR > 1 && ($3 == "FALLITO" || $3 == "BLOCCATO") { aperti[k] = aperti[k] NR ": " $0 "\n" } NR > 1 && $3 == "OK" && $4 ~ /^rifatto/ { delete aperti[k] } END { for (k in aperti) printf "%s", aperti[k] }' "$E"
echo "--- NON VERIFICATO fuori dalle prove manuali"
grep ' | NON VERIFICATO | ' "$E" | grep -v -i -e '^M[123] |' -e touch -e coarse
echo "--- esiti"
tail -n +2 "$E" | cut -d'|' -f3 | sort | uniq -c
```

Expected:
- nessuna riga `manca`;
- `righe con ql-editor: N`, con N almeno 1: è la riga della ricerca del Task R6;
- sotto `--- righe aperte` niente;
- sotto `--- NON VERIFICATO fuori dalle prove manuali` niente, oppure solo righe della parte touch di un caso;
- il conteggio per esito, con `OK`, `NON APPLICABILE`, `NON VERIFICATO` ed eventualmente `FALLITO` e `BLOCCATO` già chiusi.

Con una riga aperta, fermati. Mostra la riga all'utente e chiedi se rifare il caso dopo una correzione o se accettare l'eccezione. Con una riga `NON VERIFICATO` che non riguarda il touch, il caso non è fatto: va rifatto, perché `NON VERIFICATO` è ammesso solo per le prove manuali e per i controlli touch (Task R2, Step 12).

Poi stampa la matrice per il riepilogo dello Step 12:

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
awk -F' [|] ' 'NR > 1 { r[$1] = r[$1] (r[$1] ? ", " : "") $2 " " $3 } END { for (c in r) print c ": " r[c] }' "$W/integrazione-esiti.txt" | sort -V
```

Expected: una riga per caso, in ordine `I1`...`I20` e poi `M1`...`M3`, per esempio `I10: new.test OK` oppure `I19: ecommerce.test FALLITO, ecommerce.test OK`.

- [ ] **Step 2: Lib, criteri 1-3**

```bash
LIB_WT=/Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
W="$HOME/.cache/wonder-tooling/save-bar"
cd "$LIB_WT"
git status --short --branch
npm test > "$W/npm-test.txt" 2>&1; echo "npm test exit $?"
grep -e '^PASS: existing references' -e '^manifest: ok$' -e '^backend-save-bar: [0-9]* tests passed$' "$W/npm-test.txt"
PLAYWRIGHT_MODULE="$HOME/.cache/wonder-tooling/playwright/node_modules/playwright-core" node test/backend-save-bar.browser.test.cjs > "$W/browser-test.txt" 2>&1; echo "browser exit $?"
grep -e '^backend-save-bar browser: ' -e '^non riprodotto ' -e 'falliti: ' "$W/browser-test.txt"
n=$(sed -n 's/^backend-save-bar browser: \([0-9]*\) passed$/\1/p' "$W/browser-test.txt")
s=$(grep -c '^non riprodotto ' "$W/browser-test.txt")
echo "casi $((n + s)) di 47"
grep '^non riprodotto ' "$W/browser-test.txt" | grep -v -e '^non riprodotto B4:' -e '^non riprodotto B35:' -e '^non riprodotto B39:'
grep -e 'B20' "$W/browser-test.txt"
```

Expected:
- `## backend-save-bar` senza file modificati;
- `npm test exit 0`, poi `PASS: existing references, ...` (U38), `manifest: ok` (U39) e `backend-save-bar: 47 tests passed` (criterio 1);
- `browser exit 0`, la riga `backend-save-bar browser: N passed`, al più tre righe `non riprodotto` per B4, B35 e B39, nessuna riga `falliti:`;
- `casi 47 di 47`;
- il penultimo comando non stampa niente: nessun altro caso è "non riprodotto";
- l'ultimo stampa le righe di B20 che il test scrive (le mediane per scenario), se ce ne sono. B20 non compare fra i `falliti` né fra i `non riprodotto`: il suo controllo di Q4, con il margine, è nel test stesso (Task B2). Le righe vanno nel riepilogo (criterio 2).

Con una riga `falliti:`, fermati: rilancia solo quei casi con `SAVE_BAR_ONLY=<id separati da virgola>` davanti allo stesso comando. Se il caso passa da solo e fallisce nel lancio completo, è un test instabile: riportalo all'utente con le due uscite. Una correzione va nel task della Parte B, C o I che ha scritto quel codice, poi si rifà questo Step.

```bash
LIB_WT=/Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
W="$HOME/.cache/wonder-tooling/save-bar"
O="$W/build-check"
cd "$LIB_WT"
rm -rf "$O"
npx webpack --output-path "$O" 2>&1 | tail -1
printf 'head.js wiSaveBar %s\n' "$(grep -c wiSaveBar "$O/backend/head.js")"
printf 'head.css .wi-save-bar %s\n' "$(grep -c '\.wi-save-bar' "$O/backend/head.css")"
diff -rq "$O" "$W/lib-dist" > /dev/null && echo "uguale alla build provata nei siti" || echo "DIVERSA dalla build provata nei siti"
node test/manifest.test.cjs
git diff --stat main...HEAD -- dist | wc -l
git status --porcelain dist | wc -l
rm -rf "$O"
```

Expected (criterio 3):
- una riga con `compiled`; oggi `compiled with 3 warnings`, gli stessi avvisi di `main`;
- `head.js wiSaveBar N` e `head.css .wi-save-bar N`, con N almeno 1;
- `uguale alla build provata nei siti`: la build di webpack è ripetibile, lo stesso sorgente dà gli stessi file;
- `manifest: ok`;
- `0` e `0`: il ramo non tocca `dist/` e la build non ci ha scritto.

Con `DIVERSA dalla build provata nei siti`, la lib è cambiata dopo lo Step 6 del Task R2 e i casi hanno girato su una build vecchia. Fermati e riportalo all'utente: si ricostruisce con `npx webpack --output-path "$W/lib-dist"`, si esegue `"$W/siti.sh" dist <sito> nuova` sui tre siti e si rifanno i casi toccati dal cambio, con righe `rifatto`.

- [ ] **Step 3: App, criterio 4**

```bash
APP_WT=/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
W="$HOME/.cache/wonder-tooling/save-bar"
P82="/Users/andreamarinoni/Library/Application Support/Herd/bin/php82"
cd "$APP_WT"
git status --short --branch
for f in $(git diff --name-only --diff-filter=AM main...HEAD -- '*.php'); do
  php -l "$f" > /dev/null || echo "ERRORE $f"
  "$P82" -l "$f" > /dev/null || echo "ERRORE 8.2 $f"
done; echo "lint finito"
composer dumpautoload 2>&1 | tee "$W/dumpautoload.txt"
grep -ci -e warning -e ambiguous -e 'does not comply' "$W/dumpautoload.txt"
git status --porcelain | wc -l
```

Expected:
- `## backend-save-bar` senza file modificati;
- solo `lint finito`, con PHP 8.5 e con PHP 8.2, il minimo di `composer.json`;
- `Generating autoload files` e `Generated autoload files`, poi `0`: nessun avviso di PSR-4, classi ambigue o file fuori standard;
- `0`: `vendor/` è ignorato, quindi `dumpautoload` non cambia file del repo.

Poi i test dell'app contro la base, come nello Step 2 del Task P11:

```bash
cd /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
git ls-files tests | grep -v -e harness.php -e scheduler-integration.php | wc -l
for f in $(git ls-files tests | grep -v -e harness.php -e scheduler-integration.php); do php "$f" >/dev/null || echo "FALLITO $f"; done \
  | tee "$HOME/.cache/wonder-tooling/save-bar/app-after.txt"
diff "$HOME/.cache/wonder-tooling/save-bar/app-baseline.txt" "$HOME/.cache/wonder-tooling/save-bar/app-after.txt" && echo "come la base"
```

Expected: `130`, nessuna riga `FALLITO`, poi `come la base`.

Poi le suite dei moduli con l'app del worktree, come nello Step 4 del Task P11:

```bash
cd /Users/andreamarinoni/Developer/packages/gestionale
git status --porcelain | wc -l
ls tests/*Test.php | wc -l
for f in tests/*Test.php; do
  WI_APP_WORKTREE=/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar \
    php -d auto_prepend_file="$HOME/.cache/wonder-tooling/save-bar/app-prepend.php" "$f" >/dev/null || echo "FALLITO $f"
done | tee "$HOME/.cache/wonder-tooling/save-bar/gestionale-after.txt"
diff "$HOME/.cache/wonder-tooling/save-bar/gestionale-baseline.txt" "$HOME/.cache/wonder-tooling/save-bar/gestionale-after.txt" && echo "come la base"
git status --porcelain | wc -l
```

Expected: lo stesso numero di file modificati prima e dopo, uguale a quello annotato nel Task S1; `71`, nessuna riga `FALLITO`, poi `come la base`. Gli avvisi `Deprecated: Method ReflectionProperty::setAccessible()` su stderr ci sono anche nella base e non contano.

```bash
cd /Users/andreamarinoni/Developer/packages/immobili
test -f vendor/autoload.php && readlink vendor/wonder-image/app || echo "non eseguibile: manca vendor/, serve composer install"
for f in tests/listing-routes.php tests/property-media.php; do
  WI_APP_WORKTREE=/Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar \
    php -d auto_prepend_file="$HOME/.cache/wonder-tooling/save-bar/app-prepend.php" "$f" || echo "FALLITO $f"
done
```

Expected: `../../../app/`, poi `Listing route checks passed` e `Property media checks passed`. Con `non eseguibile`, non lanciare `composer install`: segna i 2 test come non eseguibili nel riepilogo e chiedi all'utente.

- [ ] **Step 4: Docs e skill, criterio 6**

Ogni file dell'elenco "Documentazione" della spec deve essere cambiato sul suo branch. `check` stampa `NON CAMBIATO` per un file uguale a `main` o assente.

```bash
check() { r=$1; shift; for f in "$@"; do git -C "$r" diff --quiet main...HEAD -- "$f" && echo "NON CAMBIATO $r/$f"; done; echo "controllati $# file in ${r#/Users/andreamarinoni/Developer/packages/}"; }
P=/Users/andreamarinoni/Developer/packages
check "$P/app/.claude/worktrees/backend-save-bar" AGENTS.md CHANGELOG.md docs/app/SUMMARY.md docs/app/concetti/form/README.md docs/app/concetti/form/save-bar.md docs/app/concetti/componenti/README.md docs/app/piattaforma/layout.md
check "$P/lib/.claude/worktrees/backend-save-bar" AGENTS.md CHANGELOG.md MANIFEST.json docs/SUMMARY.md docs/javascript/save-bar.md docs/reference/data-attributes.md docs/reference/js-reference.md docs/javascript/events.md docs/javascript/forms.md docs/styles/css-variables.md docs/integrations/quill.md
check "$P/skills/.claude/worktrees/backend-save-bar" skills/wi-app/references/model-and-resource.md skills/wi-site/references/workflows.md
check "$P/immobili/.claude/worktrees/backend-save-bar" CHANGELOG.md docs/frontend/personalizzare-le-view.md
```

Expected, senza righe `NON CAMBIATO`:
- `controllati 7 file in app/.claude/worktrees/backend-save-bar`;
- `controllati 11 file in lib/.claude/worktrees/backend-save-bar`;
- `controllati 2 file in skills/.claude/worktrees/backend-save-bar`;
- `controllati 2 file in immobili/.claude/worktrees/backend-save-bar`.

Poi i rimandi e le voci, con gli stessi controlli dei Task P9, P10, I5, I6 e R1:

```bash
cd /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
for f in docs/app/SUMMARY.md docs/app/concetti/form/README.md docs/app/concetti/componenti/README.md docs/app/piattaforma/layout.md; do l=$(grep -o '([^)]*save-bar\.md)' "$f" | tr -d '()'); if [ -n "$l" ] && [ -f "$(dirname "$f")/$l" ]; then echo "ok $f"; else echo "MANCA $f"; fi; done
grep -cF 'docs/app/concetti/form/save-bar.md' AGENTS.md; grep -cF 'docs/app/elementi/form-system.md' AGENTS.md; grep -cF 'data-wi-save-bar' CHANGELOG.md; test -f docs/app/concetti/form/theme-system.md && echo theme-system-ok
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
grep -cF '* [Save bar](javascript/save-bar.md)' docs/SUMMARY.md
node -p 'Object.keys(require("./MANIFEST.json").components.save_bar).join(",")'
cd /Users/andreamarinoni/Developer/packages/skills/.claude/worktrees/backend-save-bar
grep -c 'Backend save bar' skills/wi-app/references/model-and-resource.md
grep -c '^### Backend save bar$' skills/wi-app/references/model-and-resource.md
grep -c 'save.bar' skills/wi-site/references/workflows.md
cd /Users/andreamarinoni/Developer/packages/immobili/.claude/worktrees/backend-save-bar
grep -c '^### Barra di salvataggio del backend$' CHANGELOG.md
grep -c '^### Override del form del backend$' docs/frontend/personalizzare-le-view.md
```

Expected:
- quattro righe `ok`, poi `1`, `0`, `1` e `theme-system-ok` (Task P9);
- `1` e `js,css,global,contract,docs,internal_classes` (Task I5 e I6);
- `3`, `1` e `4` (Task P10);
- `1` e `1` (Task R1).

Infine leggi i diff file per file (`git -C <worktree> diff main...HEAD -- <file>`) e confrontali con l'elenco "Documentazione" della spec, voce per voce: sezioni della pagina nuova, rimandi, CHANGELOG con i cambi di comportamento da comunicare, `AGENTS.md` e skill. Una voce mancante si aggiunge nel task che ha scritto quel file, con un commit sul suo branch.

- [ ] **Step 5: Tema del pannello Browser, prima del ripristino**

Il tema si rimette qui perché dopo il ripristino immobili.test rimanda a immobili.site e non apre più pagine del proprio host. Su `https://new.test/backend/`, `https://ecommerce.test/backend/` e `https://immobili.test/backend/`, uno alla volta:
1. `navigate` all'indirizzo; va bene anche se la sessione è scaduta e si apre il login, perché `localStorage` è dello stesso host;
2. `javascript_tool` con `localStorage.setItem('theme', 'light'); [location.host, localStorage.getItem('theme')]`.

Expected: `["new.test","light"]`, `["ecommerce.test","light"]` e `["immobili.test","light"]`.

Poi `resize_window` con `preset: "desktop"` e `colorScheme: "light"`, per togliere anche un'eventuale emulazione di dimensioni o di tema scuro lasciata da un caso.

- [ ] **Step 6: Ripristino dei tre siti**

```bash
B=/Users/andreamarinoni/Developer/boilerplates/.save-bar-backup
if [ -e "$B/new-site/env" ]; then echo "tunnel di M2 ancora aperto"; else echo "tunnel gia chiuso"; fi
```

Expected: `tunnel gia chiuso`. Con `tunnel di M2 ancora aperto`, chiedi all'utente di chiudere `herd share` con Ctrl+C nel suo terminale e, quando conferma, esegui `"$HOME/.cache/wonder-tooling/save-bar/tunnel-env.sh" chiudi`: deve stampare `uguale: .env` e `3:APP_URL=https://new.test`.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
for s in new-site ecommerce-site immobili-site; do
  echo "== $s"
  "$W/siti.sh" ripristina "$s" || echo "FERMATI: $s"
done
```

Expected, per ogni sito:
- solo per immobili-site, per prima `uguale: .env`;
- `<sito> dist originale, wiSaveBar in head.js: 0`;
- `uguale: /Users/andreamarinoni/Developer/boilerplates/<sito>/assets/lib/wonder-image/dist`;
- `uguale: /Users/andreamarinoni/.cache/wonder-tooling/save-bar/<sito>-status-after.txt`;
- `uguale: /Users/andreamarinoni/.cache/wonder-tooling/save-bar/<sito>-links-after.txt`;
- l'output di `herd restart`;
- nessuna riga `DIVERSO` e nessuna `FERMATI`.

Con una riga `DIVERSO` lo script mostra prima il `diff`, e alla fine esce con `ripristino con differenze: vedi le righe DIVERSO`. Non rimettere a posto file dell'utente:
- se la differenza è un file lasciato da un caso (per esempio un hook temporaneo), si toglie con la pulizia di quel caso;
- se è un file che l'utente ha cambiato nel frattempo, si mostra all'utente e si lascia com'è.

Poi si rilancia `"$W/siti.sh" ripristina <sito>`: lo script si può ripetere, perché salta le cartelle di `vendor` già rimesse e ricopia `dist` e `.env` dal backup.

- [ ] **Step 7: Controllo del ripristino, criterio 7**

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
SITES=/Users/andreamarinoni/Developer/boilerplates
B=$SITES/.save-bar-backup
for s in new-site ecommerce-site immobili-site; do
  echo "== $s"
  git -C "$SITES/$s" status --porcelain | diff "$W/$s-status-before.txt" - && echo "status uguale"
  for d in "$SITES/$s"/vendor/wonder-image/*; do printf '%s %s\n' "${d##*/}" "$(readlink "$d" || echo dir)"; done | diff "$W/$s-links-before.txt" - && echo "readlink uguali"
  diff -r "$B/$s/dist" "$SITES/$s/assets/lib/wonder-image/dist" && echo "dist uguale al backup"
  printf 'wiSaveBar in head.js: %s\n' "$(grep -c wiSaveBar "$SITES/$s/assets/lib/wonder-image/dist/backend/head.js")"
  printf 'nel backup: %s\n' "$(ls -A "$B/$s" | tr '\n' ' ')"
  grep -n '^APP_URL=' "$SITES/$s/.env"
done
ls "$SITES/new-site/app/Resources"
curl -sk -o /dev/null -w '%{http_code}\n' https://new.test/backend/save-bar-probe/
curl -sk -o /dev/null -w '%{http_code} %{redirect_url}\n' https://immobili.test/backend/
```

Expected:
- per ogni sito `status uguale`, `readlink uguali`, `dist uguale al backup` e `wiSaveBar in head.js: 0`;
- `nel backup: dist ` per new-site ed ecommerce-site, `nel backup: dist env ` per immobili-site;
- `3:APP_URL=https://new.test`, `3:APP_URL=https://ecommerce.test` e `3:APP_URL=https://immobili.site`;
- `Site`: la Resource di prova non c'è più;
- `404`: la sua route non esiste più;
- `302 https://immobili.site/backend/account/login/?redirect=...`: immobili-site ha di nuovo il suo `APP_URL`. Per questo controllo non si usa la pagina di login, che risponde `200` anche con l'`APP_URL` originale.

Il criterio 7 chiede anche `herd restart`, fatto da `siti.sh` (Step 6), il tema `light` (Step 5) e il worktree `app-base` rimosso (Step 10).

- [ ] **Step 8: File rimasti nei siti**

`git status` non vede i file ignorati, come gli upload. Si cercano i file nuovi o cambiati dopo la preparazione, fuori dalle cartelle rimesse dal ripristino:

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
SITES=/Users/andreamarinoni/Developer/boilerplates
for s in new-site ecommerce-site immobili-site; do
  echo "== $s"
  find "$SITES/$s" \( -path "$SITES/$s/.git" -o -path "$SITES/$s/.claude" -o -path "$SITES/$s/vendor" -o -path "$SITES/$s/node_modules" -o -path "$SITES/$s/assets/lib" -o -path "$SITES/$s/storage" \) -prune -o -type f -newer "$W/$s-status-before.txt" ! -name '*.log' ! -name .DS_Store -print
done
```

Expected: nessun file, oppure solo file in `assets/upload/` e `assets/temp/` dei record elencati in `record-di-prova.txt`, che restano con i loro record.

Un file lasciato da un caso che ha una sua pulizia (per esempio un hook temporaneo del Task R4) si toglie con quella pulizia, poi si rifà questo Step. Ogni altro file si mostra all'utente: Claude non lo cancella.

- [ ] **Step 9: Backup dei siti**

Il backup contiene la `dist` originale di ogni sito e, per immobili-site, la copia intera di `.env`. Si rimuove solo se contiene soltanto quello e se è uguale a quanto c'è ora nei siti:

```bash
SITES=/Users/andreamarinoni/Developer/boilerplates
B=$SITES/.save-bar-backup
ok=1
[ "$(ls -A "$B")" = "$(printf 'ecommerce-site\nimmobili-site\nnew-site')" ] || { echo "cartelle inattese in $B"; ok=0; }
for s in new-site ecommerce-site immobili-site; do
  extra=$(ls -A "$B/$s" | grep -vxE 'dist|env' || true)
  [ -z "$extra" ] || { echo "$s: resta $extra"; ok=0; }
  diff -rq "$B/$s/dist" "$SITES/$s/assets/lib/wonder-image/dist" >/dev/null 2>&1 || { echo "$s: dist diversa"; ok=0; }
  if [ -e "$B/$s/env" ]; then cmp -s "$B/$s/env" "$SITES/$s/.env" || { echo "$s: .env diverso"; ok=0; }; fi
done
if [ "$ok" = 1 ]; then rm -rf "$B"; echo "backup rimosso"; else echo "backup lasciato: controlla"; fi
test ! -e "$B" && echo "nessun backup"
```

Expected: `backup rimosso` e `nessun backup`. Con `backup lasciato: controlla`, le righe prima dicono perché: una cartella `vendor-*` rimasta vuol dire che il ripristino non è finito (Step 6); una `dist` o un `.env` diversi vanno mostrati all'utente. Non rimuovere il backup a mano.

- [ ] **Step 10: Worktree staccati e build fuori dai repo**

`app-base` e `lib-base` servivano solo ai confronti dei casi. I worktree dei branch restano per il Task R9.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
git -C "$W/app-base" status --porcelain | wc -l
git -C "$W/lib-base" status --porcelain | wc -l
```

Expected: `0` e `0`. Con file modificati, fermati e chiedi all'utente: un caso ha scritto nel worktree di base.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
git -C /Users/andreamarinoni/Developer/packages/app worktree remove "$W/app-base"
git -C /Users/andreamarinoni/Developer/packages/lib worktree remove "$W/lib-base"
git -C /Users/andreamarinoni/Developer/packages/app worktree prune
git -C /Users/andreamarinoni/Developer/packages/lib worktree prune
git -C /Users/andreamarinoni/Developer/packages/app worktree list | grep -c "$W"
git -C /Users/andreamarinoni/Developer/packages/lib worktree list | grep -c "$W"
rm -rf "$W/lib-dist" "$W/lib-base-dist"
git -C /Users/andreamarinoni/Developer/packages/app worktree list | grep -c backend-save-bar
git -C /Users/andreamarinoni/Developer/packages/lib worktree list | grep -c backend-save-bar
```

Expected: `0` e `0` (i due `grep -c` escono con 1, come atteso), poi `1` e `1`: restano solo i worktree dei branch. `git worktree remove` accetta i file ignorati come `node_modules/` di `lib-base`; i commit non si toccano, perché i worktree erano staccati.

- [ ] **Step 11: Record creati durante i casi**

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
tail -n +2 "$W/record-di-prova.txt" | wc -l
cat "$W/record-di-prova.txt"
```

Expected: il numero dei record e l'elenco, con l'intestazione `host | pagina | record | creato nel caso`.

Con almeno un record, manda il file con `SendUserFile` (`files: ["/Users/andreamarinoni/.cache/wonder-tooling/save-bar/record-di-prova.txt"]`, `status: "normal"`) e questo testo:

```text
Ti mando record-di-prova.txt: sono i record creati durante le prove di integrazione, uno per riga con host, pagina, record e caso. Non li ho cancellati: decidi tu quali tenere.
Quelli di immobili.test ora non si aprono da immobili.test, che dopo il ripristino rimanda a immobili.site. Per vederli serve rimettere per un momento APP_URL=https://immobili.test nel .env di immobili-site.
```

Con `0`, scrivi all'utente che durante le prove non sono stati creati record, senza mandare il file.

- [ ] **Step 12: Riepilogo per l'utente**

Manda all'utente il riepilogo dei sette criteri, con i valori degli Step 1-11 al posto di quelli fra `<>`:

```text
Integrazione della barra di salvataggio: criteri di completamento (sez. 6.6)
1. npm test della lib: verde, backend-save-bar: 47 tests passed, con U38 e U39
2. test browser: <n> passati, non riprodotti: <casi e motivi>; B20: <mediane per scenario>
3. build fuori dal repo: head.js con wiSaveBar, head.css con .wi-save-bar, manifest ok, dist della lib non toccata dal ramo
4. app: lint pulito con PHP 8.5 e 8.2, dumpautoload senza avvisi, 130 test come la base; gestionale 71 come la base; immobili 2 su 2
5. casi: <matrice dello Step 1>; eccezioni accettate: <righe o nessuna>; ricerca di ql-editor: <nota della riga>
6. docs e skill: 22 file controllati uno per uno, nessuno mancante
7. ripristino: status, readlink e dist uguali a prima su new-site, ecommerce-site e immobili-site; herd restart fatto; tema light; Resource di prova tolta; worktree app-base e lib-base rimossi; backup rimosso
Record di prova: <numero>, elenco mandato a parte
Restano per il rilascio: I21 al passo 3, I22 al passo 5 e la checklist Dopo il rilascio.
```

Con un criterio non soddisfatto, il riepilogo lo dice nella sua riga e il Task R9 non parte finché l'utente non decide.

### Task R9: Sequenza di rilascio

Repo: lib, app, skills, immobili, new-site, immobili-site, rsvp-site e agliati-com.

I commit del Task sono tutti **solo su richiesta esplicita dell'utente**:
- quello di release della lib, scritto da `npm run release`;
- il merge dell'app, fatto da GitHub;
- quelli dei quattro siti.

Anche ogni push, PR, merge, release npm e tag è marcato così. Claude chiede prima di ciascuno e aspetta un sì in chat: un sì vale solo per l'azione chiesta, non per le successive.

Il Task parte solo se il riepilogo del Task R8 ha tutti i criteri 1-7 soddisfatti, o eccezioni accettate dall'utente. L'ordine è quello della spec ("Ordine di rilascio"):
1. lib;
2. app, con le skill;
3. siti boilerplate, con I21;
4. immobili;
5. agliati, con I22.

Poi si fa la checklist "Dopo il rilascio".

Il range dei siti è `^2.1.2-alpha.17`, non `^2.1.2-alpha.*` come scritto nella spec, per due motivi:
- `^2.1.2-alpha.*` non è un range valido per npm;
- il tag `latest` di npm è ancora `2.1.2-alpha.4`, quindi un range più basso come `^2.1.2-alpha.2` farebbe installare quella.

Il range si alza come negli aggiornamenti precedenti dei siti (`^2.1.2-alpha.13` -> `^2.1.2-alpha.15`).

**Files:**
- Modify (lib, `main`, con `npm run release`): `package.json`, `package-lock.json`, `dist/`; tag `v2.1.2-alpha.17`
- Modify (new-site, immobili-site, rsvp-site, agliati-com): `package.json`, `package-lock.json`, `composer.lock`
- Delete (agliati-com): `custom/modules/immobili/view/pages/backend/immobili/form.php`
- Create: `$W/rilascio-prima.txt` (versioni e riferimenti prima degli aggiornamenti), `$W/pr-lib.md`, `$W/pr-app.md` e `$W/pr-skills.md` (corpi delle PR)
- Modify: `$W/integrazione-esiti.txt` (righe I21 e I22)
- Test: `npm test` della lib su `main` prima della release, workflow `publish.yml`, controlli dei siti, `npm ci` in una copia temporanea, casi I21 e I22, deploy dei siti

**Interfaces:**
- Consumes:
  - il riepilogo del Task R8, con i criteri 1-7;
  - i branch `backend-save-bar` di lib, app, skills e immobili, nei worktree del Task S1, con i commit delle Parti B, C, I, P e del Task R1;
  - dal Task R2 `stato.js` e `casi.js`, e le regole comuni dello Step 12;
  - `$W/integrazione-esiti.txt`.
- Produces:
  - `wonder-image@2.1.2-alpha.17` sul canale `alpha` di npm;
  - `main` di app, skills e immobili con la barra di salvataggio;
  - i quattro siti con la lib 2.1.2-alpha.17 e l'app nuova;
  - le righe I21 e I22 del registro;
  - la checklist "Dopo il rilascio" controllata.

- [ ] **Step 1: Stato di partenza e release della lib 2.1.2-alpha.17**

Prima le PR aperte e lo stato dei branch:

```bash
P=/Users/andreamarinoni/Developer/packages
for r in lib app immobili skills; do
  echo "== $r"
  gh pr list -R "wonder-image/$r" --head backend-save-bar --state open --json number,title --jq '.[] | "PR \(.number) \(.title)"'
  git -C "$P/$r" fetch origin --quiet
  git -C "$P/$r" status --short --branch | head -1
  printf 'main %s origin/main %s branch %s\n' "$(git -C "$P/$r" rev-parse --short main)" "$(git -C "$P/$r" rev-parse --short origin/main)" "$(git -C "$P/$r" rev-parse --short backend-save-bar)"
  git -C "$P/$r" merge-base --is-ancestor origin/main backend-save-bar && echo "branch sopra origin/main" || echo "origin/main ha commit che il branch non ha"
  git -C "$P/$r/.claude/worktrees/backend-save-bar" status --porcelain | wc -l
done
```

Expected, per ogni repo:
- nessuna riga `PR`, oppure una PR del branch `backend-save-bar`: se c'è, si usa quella, con push fast-forward e mai con force;
- `## main...origin/main`, senza `ahead` o `behind`, per lib, immobili e skills;
- per lib, immobili e skills `branch sopra origin/main`;
- `0`: nessun file modificato nel worktree del branch.

Per l'app:
- può comparire `origin/main ha commit che il branch non ha`: si sistema allo Step 2;
- la checkout principale può essere `[ahead N]` per commit locali dell'utente non ancora pushati. Il 2026-09-28 era `[ahead 1]`, con `495b6fe1 Repeater: l'ultima riga svuotata svuota anche AutoNumeric`;
- non blocca il rilascio, perché il merge dell'app passa da GitHub e non da quella checkout;
- dillo all'utente e non fare il push di quei commit.

Se lib, immobili o skills hanno `origin/main` avanti, fermati e chiedi all'utente come procedere:
- **rebase del branch:** solo se il branch non è ancora su origin;
- **merge di `origin/main` nel branch.**

Dopo l'una o l'altra, rifai i controlli del Task R8 per quel repo: per la lib `npm test` e i test nel browser dello Step 2, senza il confronto con `$W/lib-dist` che lo Step 10 ha tolto; lo Step 3 per immobili; lo Step 4 per le skill.

Con `ahead` o `behind` sulla checkout principale di lib, immobili o skills, chiedi all'utente: non fare `pull` né `reset` di tua iniziativa. I loro merge partono da quella checkout.

Push del branch della lib e PR per la revisione (solo su richiesta esplicita dell'utente):

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
cat > "$W/pr-lib.md" <<'MD'
Barra di salvataggio del backend (`window.wiSaveBar`): i form con `data-wi-save-bar` mostrano in fondo alla pagina un'isola con lo stato delle modifiche e le copie dei pulsanti Salva.

- Stile dell'isola in `backend/head.css`, riserva in fondo con `--wi-save-bar-reserve` e `scroll-padding-top`.
- Quill vuoto vale `''`: un Quill obbligatorio scritto e cancellato disabilita Salva. Le scritture dirette nel DOM di `.ql-editor` non sono piu supportate.
- `btn-dark` disabilitato leggibile nel tema scuro.
- Test: `npm test` (U1-U39) e test nel browser B1-B47 (`test/backend-save-bar.browser.test.cjs`).
- Docs: `docs/javascript/save-bar.md`, `MANIFEST.json`, `AGENTS.md`, `CHANGELOG.md`.

Dopo il merge si pubblica la 2.1.2-alpha.17 sul canale alpha.

<riga di attribuzione delle PR indicata dalle istruzioni della sessione>
MD
cd /Users/andreamarinoni/Developer/packages/lib/.claude/worktrees/backend-save-bar
git push -u origin backend-save-bar
gh pr create -R wonder-image/lib --base main --head backend-save-bar --title "Barra di salvataggio del backend" --body-file "$W/pr-lib.md"
```

Prima del `gh pr create` metti al posto dell'ultima riga di `pr-lib.md` la riga di attribuzione delle PR. Se una PR c'era già, basta il push. Expected: il push crea il branch remoto e `gh pr create` stampa l'URL della PR.

Merge nella lib: fast-forward dalla checkout principale, come le PR #1 e #2 della lib, con la storia di `main` lineare (solo su richiesta esplicita dell'utente):

```bash
cd /Users/andreamarinoni/Developer/packages/lib
git fetch origin --quiet
git merge --ff-only backend-save-bar
git push origin main
gh pr list -R wonder-image/lib --head backend-save-bar --state merged --json number --jq '.[].number'
```

Expected: `Fast-forward`, il push di `main`, poi il numero della PR, che GitHub segna come unita perché i suoi commit sono in `main`.

Controlli prima della release, sulla checkout principale:

```bash
cd /Users/andreamarinoni/Developer/packages/lib
git branch --show-current
git fetch origin --quiet
git status --short --branch
git status --porcelain | wc -l
git merge-base --is-ancestor backend-save-bar main && echo "branch in main"
node -p 'require("./package.json").version'
npm view wonder-image@2.1.2-alpha.17 version --prefer-online 2>&1 | head -1
git ls-remote --tags origin refs/tags/v2.1.2-alpha.17 | wc -l
npm ci --no-audit --no-fund 2>&1 | tail -1
npm test 2>&1 | tail -1
```

Expected:
- `main`;
- `## main...origin/main` e `0`: `scripts/release.cjs` esegue `git add .`, quindi un file non tracciato e non ignorato finirebbe nel commit di release;
- `branch in main`;
- `2.1.2-alpha.15`;
- `npm error code E404`: la versione non esiste ancora;
- `0`: il tag non c'è su origin;
- la riga finale di `npm ci` (`added N packages ...`);
- `backend-save-bar: 47 tests passed`.

La copia ignorata `test/backend/file-references.test.cjs` della checkout principale non entra nel commit, perché è ignorata. Si toglie solo su richiesta esplicita dell'utente.

Release (solo su richiesta esplicita dell'utente):

```bash
cd /Users/andreamarinoni/Developer/packages/lib
npm run release -- 2.1.2-alpha.17
```

Lo script:
1. controlla il branch e il tag;
2. porta la versione a `2.1.2-alpha.17` in `package.json` e `package-lock.json`;
3. ricostruisce `dist/`;
4. fa il commit `Release 2.1.2-alpha.17` e il tag annotato `v2.1.2-alpha.17`;
5. fa il push di `main` e del tag.

Il messaggio è fisso nello script, come per le release precedenti, e non ha la riga `Co-Authored-By`.

Se lo script si ferma a metà, non rifarlo e non annullare niente di tua iniziativa. Mostra all'utente `git status --short --branch` e `git tag --list v2.1.2-alpha.17`, e chiedi.

```bash
cd /Users/andreamarinoni/Developer/packages/lib
git log -1 --format='%h %s'
git describe --exact-match HEAD
git status --short --branch
git show --stat --format= HEAD | tail -1
printf 'wiSaveBar in dist/backend/head.js: %s\n' "$(grep -c wiSaveBar dist/backend/head.js)"
gh run list -R wonder-image/lib --workflow publish.yml --limit 1 --json databaseId,headBranch,status --jq '.[] | "\(.databaseId) \(.headBranch) \(.status)"'
```

Expected:
- `<hash> Release 2.1.2-alpha.17`;
- `v2.1.2-alpha.17`;
- `## main...origin/main`;
- la riga di riepilogo del commit, con `package.json`, `package-lock.json` e i file di `dist/`;
- `wiSaveBar in dist/backend/head.js: N`, con N almeno 1;
- `<id> v2.1.2-alpha.17 <stato>`.

Poi, con l'id della riga sopra:

```bash
gh run watch <id> -R wonder-image/lib --exit-status > /dev/null; echo "publish exit $?"
npm view wonder-image@2.1.2-alpha.17 version --prefer-online
npm view wonder-image dist-tags --prefer-online
```

Expected:
- `publish exit 0`;
- `2.1.2-alpha.17`;
- `{ latest: '2.1.2-alpha.4', alpha: '2.1.2-alpha.17' }`: la versione esce sul canale `alpha` e `latest` non cambia.

Con `publish exit` diverso da 0, non rifare il tag. Mostra all'utente `gh run view <id> -R wonder-image/lib --log-failed | tail -40` e chiedi. Se `npm view` dà ancora E404 subito dopo il workflow verde, il registro è in ritardo: riprova dopo un minuto con `--prefer-online`.

- [ ] **Step 2: Merge dell'app e delle skill**

La route POST dell'account è già su `main` (commit 559d6d02), quindi non serve un commit a parte.

```bash
cd /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
gh pr list -R wonder-image/app --head backend-save-bar --state open --json number,url --jq '.[] | "\(.number) \(.url)"'
git fetch origin --quiet
git status --short --branch
git merge-base --is-ancestor origin/main HEAD && echo "branch sopra origin/main" || echo "origin/main ha commit che il branch non ha"
```

Expected: nessuna PR o la PR del branch; `## backend-save-bar` senza file modificati; una delle due righe finali.

Con `origin/main ha commit che il branch non ha`, si porta `main` nel branch con un merge, come negli altri branch dell'app (solo su richiesta esplicita dell'utente):

```bash
cd /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
git merge --no-edit origin/main
git status --short --branch
```

Expected: `Merge made by the 'ort' strategy.` e nessun conflitto. Con un conflitto, fermati e mostralo all'utente: non scegliere tu una delle due versioni.

Dopo il merge si rifanno lint e test:

```bash
cd /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
P82="/Users/andreamarinoni/Library/Application Support/Herd/bin/php82"
for f in $(git diff --name-only --diff-filter=AM origin/main...HEAD -- '*.php'); do
  php -l "$f" > /dev/null || echo "ERRORE $f"
  "$P82" -l "$f" > /dev/null || echo "ERRORE 8.2 $f"
done; echo "lint finito"
git ls-files tests | grep -v -e harness.php -e scheduler-integration.php | wc -l
for f in $(git ls-files tests | grep -v -e harness.php -e scheduler-integration.php); do php "$f" >/dev/null || echo "FALLITO $f"; done
echo "test finiti"
```

Expected: `lint finito`; `130`, oppure di più se `main` ha aggiunto test; nessuna riga `FALLITO`, poi `test finiti`.

Push e PR dell'app (solo su richiesta esplicita dell'utente):

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
cat > "$W/pr-app.md" <<'MD'
Barra di salvataggio del backend: i form del backend dichiarano `data-wi-save-bar` e la lib (`wonder-image` 2.1.2-alpha.17) mostra in fondo alla pagina un'isola con lo stato delle modifiche e le copie dei pulsanti Salva.

- Form delle Resource, scheduler, utenti, account e file di configurazione dichiarano la barra; dopo un salvataggio fallito il form parte come non salvato (`data-wi-save-bar-dirty`).
- Il footer non aggiunge il suo Salva quando il layout ha gia un Submit `upload`.
- `header.php` sottrae `--wi-save-bar-reserve` dall'altezza minima.
- Campi segreti con `autocomplete="new-password"`; `user()` restituisce il campo `written`.
- Repeater: lo svuotamento dell'ultima riga emette `change`.
- Docs: `docs/app/concetti/form/save-bar.md`, `AGENTS.md`, CHANGELOG.

<riga di attribuzione delle PR indicata dalle istruzioni della sessione>
MD
cd /Users/andreamarinoni/Developer/packages/app/.claude/worktrees/backend-save-bar
git push -u origin backend-save-bar
gh pr create -R wonder-image/app --base main --head backend-save-bar --title "Barra di salvataggio del backend" --body-file "$W/pr-app.md"
```

Prima del `gh pr create` metti al posto dell'ultima riga di `pr-app.md` la riga di attribuzione delle PR. Se una PR c'era già, basta il push fast-forward, mai con force.

Merge della PR, con il titolo dei merge dell'app (solo su richiesta esplicita dell'utente). Al posto di `<N>` va il numero della PR:

```bash
gh pr merge <N> -R wonder-image/app --merge --subject "Unisce la PR #<N> da backend-save-bar" --body "Barra di salvataggio del backend.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
gh pr view <N> -R wonder-image/app --json state,mergeCommit --jq '.state + " " + .mergeCommit.oid[0:7]'
git ls-remote https://github.com/wonder-image/app refs/heads/main | cut -c1-7
```

Expected: `MERGED <hash>`, poi lo stesso `<hash>`: è il riferimento che i siti devono ricevere allo Step 3.

La checkout principale `packages/app` resta dov'è. Da lì puntano i `vendor/wonder-image/app` in symlink di gestionale e immobili.

La si porta a `origin/main` con `git -C /Users/andreamarinoni/Developer/packages/app pull --ff-only` solo su richiesta esplicita dell'utente. Con commit locali non pushati (lo Step 1 li mostra), `--ff-only` si ferma: chiedi all'utente e non fare né rebase né merge di tua iniziativa.

Skill, come nello Step 6 del Task P10:

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
cd /Users/andreamarinoni/Developer/packages/skills/.claude/worktrees/backend-save-bar
gh pr list -R wonder-image/skills --head backend-save-bar --state open --json number --jq '.[].number'
git status --short --branch
git log --format='%h %s' main..HEAD
cat > "$W/pr-skills.md" <<'MD'
Skill `wi-app` e `wi-site`: barra di salvataggio del backend (`data-wi-save-bar`, override dei form dei moduli, aggiornamento dei siti alla lib 2.1.2-alpha.17).

<riga di attribuzione delle PR indicata dalle istruzioni della sessione>
MD
```

Expected: nessuna PR o la PR del branch; `## backend-save-bar` senza file modificati; i commit del Task P10.

Poi, con la riga di attribuzione al posto dell'ultima riga di `pr-skills.md` (solo su richiesta esplicita dell'utente):
1. push e PR:
   ```bash
   cd /Users/andreamarinoni/Developer/packages/skills/.claude/worktrees/backend-save-bar
   git push -u origin backend-save-bar
   gh pr create -R wonder-image/skills --base main --head backend-save-bar --title "Backend save bar nelle skill wi-app e wi-site" --body-file "$HOME/.cache/wonder-tooling/save-bar/pr-skills.md"
   ```
2. merge fast-forward, come la storia lineare delle skill:
   ```bash
   cd /Users/andreamarinoni/Developer/packages/skills
   git fetch origin --quiet
   git merge --ff-only backend-save-bar
   git push origin main
   ```

Expected: `Fast-forward` e il push di `main`.

Poi, sempre solo su richiesta esplicita dell'utente, `php forge skills` da `/Users/andreamarinoni/Developer/boilerplates/new-site`, che risincronizza le skill installate. Non si usa `npx skills` senza `--agent claude-code`, perché creerebbe una cartella `agent/`, e non si modifica `.agents/` a mano.

- [ ] **Step 3: Siti boilerplate e caso I21**

Prima si annotano lo stato e le versioni:

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
SITES=/Users/andreamarinoni/Developer/boilerplates
refs() { php -r '$l=json_decode(file_get_contents($argv[1]."/composer.lock"),true); foreach($l["packages"] as $p) if(str_starts_with($p["name"],"wonder-image/")) printf("%s %s %s %s\n", basename($argv[1]), $p["name"], $p["version"], substr($p["source"]["reference"]??"",0,7)); $n=json_decode(file_get_contents($argv[1]."/package-lock.json"),true); $j=json_decode(file_get_contents($argv[1]."/package.json"),true); printf("%s npm:wonder-image %s %s\n", basename($argv[1]), $n["packages"]["node_modules/wonder-image"]["version"]??"-", $j["dependencies"]["wonder-image"]??"-");' "$1"; }
: > "$W/rilascio-prima.txt"
for s in new-site immobili-site rsvp-site; do
  echo "== $s"
  git -C "$SITES/$s" fetch origin --quiet
  git -C "$SITES/$s" status --short --branch
  refs "$SITES/$s" | tee -a "$W/rilascio-prima.txt"
  grep -n '^APP_URL=' "$SITES/$s/.env"
done
printf 'app main %s\n' "$(git ls-remote https://github.com/wonder-image/app refs/heads/main | cut -c1-7)"
```

Expected per ogni sito:
- `## main...origin/main`, senza `ahead` o `behind`;
- le righe dei pacchetti;
- la riga di `APP_URL`.

Con i valori di oggi, prima dei commit di altre sessioni:

| Sito | File già modificati | `wonder-image/app` | Lib | Range | `APP_URL` |
|---|---|---|---|---|---|
| new-site | nessuno | `dev-main 64853dc` | `2.1.2-alpha.15` | `^2.1.2-alpha.15` | `3:APP_URL=https://new.test` |
| immobili-site | `composer.lock`, `custom/config/config.php`, `custom/view/components/frontend/sections/contact-form.php`, `lang/en/components.json`, `lang/it/components.json` | `dev-main 67bbce6` | `2.1.1-alpha.7` | `^2.1.1-alpha.5` | `3:APP_URL=https://immobili.site` |
| rsvp-site | `composer.lock` | `dev-main 82af322` | `2.1.1-alpha.6` | `^2.1.1-alpha.2` | `3:APP_URL=https://rsvp.test` |

In più:
- immobili-site ha la riga `wonder-image/immobili dev-main 91b6e48`;
- rsvp-site ha la riga `wonder-image/rsvp dev-main e3be528`;
- l'ultima riga è `app main <hash>`, uguale al merge dello Step 2.

Con `behind` fermati e chiedi all'utente: non fare `pull` di tua iniziativa.

Un `composer.lock` già modificato non si può separare dall'aggiornamento: finirebbe nello stesso commit. Mostra le differenze all'utente e chiedi se vanno nel commit o se preferisce sistemarle lui prima. Claude non le annulla.

```bash
SITES=/Users/andreamarinoni/Developer/boilerplates
git -C "$SITES/immobili-site" diff --stat -- composer.lock
git -C "$SITES/rsvp-site" diff -- composer.lock | grep -e '^[-+] *"reference"' -e '^[-+] *"version"' | head
```

Expected, con i valori di oggi:
- immobili-site: `composer.lock | 68` (34 righe tolte e 34 aggiunte): `symfony/console`, `symfony/http-foundation`, `symfony/routing` e `symfony/var-exporter` a `v7.4.18`, e `symfony/service-contracts` da `v3.7.1` a `v3.7.3`;
- rsvp-site: il riferimento di `wonder-image/app` da `32ce54f...` a `82af322...`.

Aggiornamento: prima la lib col range nuovo, poi l'app. `composer update` esegue `php forge config`, che fa `npm install wonder-image` (resta sul `2.1.2-alpha.17` del range) e `npm install`, il cui `postinstall` copia la `dist` in `assets/lib/wonder-image/dist`.

```bash
SITES=/Users/andreamarinoni/Developer/boilerplates
for s in new-site immobili-site rsvp-site; do
  echo "== $s"
  (cd "$SITES/$s" && npm install 'wonder-image@^2.1.2-alpha.17' --no-audit --no-fund && composer update wonder-image/app --no-interaction) || echo "FERMATI: $s"
done
```

Expected: per ogni sito `changed N packages` da npm, l'aggiornamento di `wonder-image/app` da Composer, l'output di `forge config`, nessuna riga `FERMATI`.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
SITES=/Users/andreamarinoni/Developer/boilerplates
SEMVER="$(npm root -g)/npm/node_modules/semver/bin/semver.js"
refs() { php -r '$l=json_decode(file_get_contents($argv[1]."/composer.lock"),true); foreach($l["packages"] as $p) if(str_starts_with($p["name"],"wonder-image/")) printf("%s %s %s %s\n", basename($argv[1]), $p["name"], $p["version"], substr($p["source"]["reference"]??"",0,7)); $n=json_decode(file_get_contents($argv[1]."/package-lock.json"),true); $j=json_decode(file_get_contents($argv[1]."/package.json"),true); printf("%s npm:wonder-image %s %s\n", basename($argv[1]), $n["packages"]["node_modules/wonder-image"]["version"]??"-", $j["dependencies"]["wonder-image"]??"-");' "$1"; }
printf 'app main %s\n' "$(git ls-remote https://github.com/wonder-image/app refs/heads/main | cut -c1-7)"
for s in new-site immobili-site rsvp-site; do
  d="$SITES/$s"
  echo "== $s"
  refs "$d"
  node "$SEMVER" -r "$(node -p 'require(process.argv[1]+"/package.json").dependencies["wonder-image"]' "$d")" 2.1.2-alpha.17 > /dev/null && echo "alpha.17 nel range" || echo "FUORI RANGE"
  printf 'wiSaveBar in head.js: %s\n' "$(grep -c wiSaveBar "$d/assets/lib/wonder-image/dist/backend/head.js")"
  diff -rq "$d/node_modules/wonder-image/dist" "$d/assets/lib/wonder-image/dist" > /dev/null && echo "dist uguale al pacchetto"
  git -C "$d" status --porcelain
  grep -n '^APP_URL=' "$d/.env"
  t=$(mktemp -d "$W/npmci.XXXXXX")
  cp "$d/package.json" "$d/package-lock.json" "$t/"
  [ -e "$d/.npmrc" ] && cp "$d/.npmrc" "$t/"
  (cd "$t" && npm ci --ignore-scripts --no-audit --no-fund > /dev/null 2>&1) && echo "npm ci ok" || echo "npm ci FALLITO"
  rm -rf "$t"
done
```

Expected per ogni sito:
- `wonder-image/app dev-main <hash>`, con lo stesso `<hash>` della riga `app main`;
- `npm:wonder-image 2.1.2-alpha.17 ^2.1.2-alpha.17` e `alpha.17 nel range`;
- `wiSaveBar in head.js: N`, con N almeno 1, e `dist uguale al pacchetto`;
- in `git status --porcelain` solo ` M composer.lock`, ` M package-lock.json` e ` M package.json`, più i file già modificati della tabella;
- la stessa riga di `APP_URL` di prima: `forge config` non la cambia;
- `npm ci ok`: il lock basta alla CI, che usa `npm ci`.

Un `<hash>` diverso da `app main` vuol dire che Packagist non ha ancora il merge. Aspetta qualche minuto e rilancia `composer update wonder-image/app --no-interaction` nel sito. Con un file cambiato che non è né nella tabella né fra i tre attesi (per esempio `composer.json` o `.gitignore` riscritti da `forge config`), mostra il diff all'utente e chiedi se va nel commit.

Caso I21 su rsvp.test, prima del commit, così un problema non arriva in produzione:
1. Chiedi all'utente di fare il login nel pannello Browser su `https://rsvp.test/backend/`. Claude non scrive le password.
2. `navigate` a `https://rsvp.test/backend/rsvp/events/create/` (EventResource del modulo rsvp). Se la pagina dà 404, apri `https://rsvp.test/backend/` e usa il link di creazione degli eventi nel menu.
3. Leggi con Read `$HOME/.cache/wonder-tooling/save-bar/stato.js` e `casi.js` e incollali con `javascript_tool`.
4. Esegui `[__wiState(), await __wiBar()]`. Expected:
   - lo stato è `hidden` o `buttons`;
   - `forms` ha un solo elemento, con `dirtyAttr: false` e `dirty: false`;
   - `buttons` ha un solo elemento, `"Salva (disabilitato)"`: la pagina ha un solo Salva, disabilitato finché Codice e Nome, obbligatori, sono vuoti.
5. Trova il campo del codice con `find` (`Codice`), clic con `computer` e `left_click` sul suo `ref`, poi `type` con il testo `x`.
6. Esegui `[await __wiUntil('dirty-label'), await __wiBar()]`. Expected:
   - lo stato è `dirty-label`, oppure `dirty-buttons` se il Salva della pagina è sotto la fascia: in quel caso `__wiUntil` restituisce `dirty-buttons` dopo 3 secondi;
   - `forms[0].dirty` è `true`, `island` è `true`, `label` e `status` sono `"Operazione non salvata"`;
   - `buttons` ha ancora un solo elemento, `"Salva (disabilitato)"`, perché Nome è vuoto;
   - `copies` è `[]` con `dirty-label` e `["Salva (disabilitato)"]` con `dirty-buttons`.
7. `read_console_messages` con `onlyErrors: false`: nessun errore e nessun avviso. Se ce ne sono, riportali nella nota con il testo.
8. Clic con `computer` sul titolo della pagina, poi `__wiLeave()`: deve restituire `true`.
9. `navigate` a `https://rsvp.test/backend/` con `force: true`. Il form non si salva e non si crea nessun record.

Poi annota l'esito, con la nota giusta se il caso fallisce:

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
printf 'I21 | rsvp.test | OK | EventResource: un form tracciato, un solo Salva, isola con modifiche, avviso di uscita\n' >> "$W/integrazione-esiti.txt"
tail -1 "$W/integrazione-esiti.txt"
```

Con un esito diverso da `OK`, scrivi `FALLITO` e cosa si è visto, fermati e chiedi all'utente prima dei commit.

Commit dei siti, solo con i tre file dell'aggiornamento (solo su richiesta esplicita dell'utente). Se l'utente lo chiede solo per alcuni siti, togli gli altri dall'elenco del `for`:

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
SITES=/Users/andreamarinoni/Developer/boilerplates
for s in new-site immobili-site rsvp-site; do
  d="$SITES/$s"
  old=$(awk -v s="$s" '$1 == s && $2 == "wonder-image/app" { print $4 }' "$W/rilascio-prima.txt")
  lib=$(awk -v s="$s" '$1 == s && $2 == "npm:wonder-image" { print $3 }' "$W/rilascio-prima.txt")
  new=$(php -r '$l=json_decode(file_get_contents($argv[1]),true); foreach($l["packages"] as $p) if($p["name"]==="wonder-image/app") echo substr($p["source"]["reference"],0,7);' "$d/composer.lock")
  av=$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["version"] ?? "dev-main";' "$d/vendor/wonder-image/app/composer.json")
  git -C "$d" commit -q -m "Aggiorna wonder-image/app a $av e wonder-image/lib a 2.1.2-alpha.17" -m "app: dev-main $old -> $new (versione letta da composer.json).
lib: $lib -> 2.1.2-alpha.17." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" -- package.json package-lock.json composer.lock
  git -C "$d" show --stat --format='%h %s' HEAD | cat
  git -C "$d" status --short --branch | head -1
done
```

Expected per ogni sito:
- `<hash> Aggiorna wonder-image/app a 2.4.0-beta.1 e wonder-image/lib a 2.1.2-alpha.17`, con la versione letta da `composer.json` dell'app;
- i tre file;
- `## main...origin/main [ahead 1]`.

`git commit -- <file>` prende solo quei file: gli altri file già modificati di immobili-site restano fuori dal commit, anche se erano nell'indice.

Push dei siti (solo su richiesta esplicita dell'utente). Il push su `main` avvia `deploy.yml`, che pubblica in produzione. Per immobili-site il push può aspettare la fine dello Step 4, così un solo push porta entrambi i commit. I deploy di immobili-site falliscono tutti dal primo, del 2026-07-29. L'ultimo, del 2026-09-05, si ferma allo step `Load secrets + generate .env` perché manca il `PROJECT_ID` di Bitwarden. Dillo all'utente prima del push.

```bash
SITES=/Users/andreamarinoni/Developer/boilerplates
for s in new-site rsvp-site; do git -C "$SITES/$s" push origin main; done
for r in new-site rsvp-site; do gh run list -R "wonder-image/$r" --workflow deploy.yml --limit 1 --json databaseId,displayTitle,status --jq '.[] | "\(.databaseId) \(.status) \(.displayTitle)"'; done
```

Expected: i due push, poi per ogni sito `<id> <stato> Aggiorna wonder-image/app a ...`. Per ogni id: `gh run watch <id> -R wonder-image/<sito> --exit-status > /dev/null; echo "deploy exit $?"` deve dare `deploy exit 0`. Se fallisce, mostra all'utente `gh run view <id> -R wonder-image/<sito> --log-failed | tail -40`.

- [ ] **Step 4: Release di immobili e aggiornamento di immobili-site**

La release del modulo è il merge del commit del Task R1 in `main`, perché i siti usano `wonder-image/immobili` in `dev-main`. Non si crea un tag: il CHANGELOG resta "## [2.0.0] - non ancora rilasciato", e un tag `v2.0.0` è una decisione separata dell'utente.

```bash
cd /Users/andreamarinoni/Developer/packages/immobili
git fetch origin --quiet
git status --short --branch
git merge-base --is-ancestor origin/main backend-save-bar && echo "fast-forward possibile"
git log --format='%h %s' main..backend-save-bar
grep -c data-wi-save-bar .claude/worktrees/backend-save-bar/view/pages/backend/immobili/form.php
```

Expected: `## main...origin/main`, `fast-forward possibile`, un commit `Add backend save bar attributes to the property form`, poi `2`.

Merge e push (solo su richiesta esplicita dell'utente):

```bash
cd /Users/andreamarinoni/Developer/packages/immobili
git merge --ff-only backend-save-bar
git push origin main
git ls-remote https://github.com/wonder-image/immobili refs/heads/main | cut -c1-7
```

Expected: `Fast-forward`, il push, poi il riferimento corto del commit del Task R1.

immobili-site:

```bash
SITES=/Users/andreamarinoni/Developer/boilerplates
d="$SITES/immobili-site"
(cd "$d" && composer update wonder-image/immobili --no-interaction) || echo "FERMATI: immobili-site"
printf 'immobili main %s\n' "$(git ls-remote https://github.com/wonder-image/immobili refs/heads/main | cut -c1-7)"
php -r '$l=json_decode(file_get_contents($argv[1]),true); foreach($l["packages"] as $p) if($p["name"]==="wonder-image/immobili") printf("%s %s\n", $p["name"], substr($p["source"]["reference"],0,7));' "$d/composer.lock"
grep -c data-wi-save-bar "$d/vendor/wonder-image/immobili/view/pages/backend/immobili/form.php"
git -C "$d" status --porcelain
grep -n '^APP_URL=' "$d/.env"
```

Expected:
- l'aggiornamento di `wonder-image/immobili` e l'output di `forge config`;
- `wonder-image/immobili <hash>`, uguale alla riga `immobili main`;
- `2`;
- in `git status --porcelain` ` M composer.lock` e i file già modificati della tabella dello Step 3;
- `3:APP_URL=https://immobili.site`.

Con un `<hash>` diverso, la cache VCS di Composer è in ritardo: rilancia `composer update wonder-image/immobili --no-interaction`.

La verifica nel browser di immobili è I20 (Task R6). Dopo il ripristino immobili.test rimanda a immobili.site e qui non si apre.

Commit (solo su richiesta esplicita dell'utente):

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
d=/Users/andreamarinoni/Developer/boilerplates/immobili-site
old=$(awk '$1 == "immobili-site" && $2 == "wonder-image/immobili" { print $4 }' "$W/rilascio-prima.txt")
new=$(php -r '$l=json_decode(file_get_contents($argv[1]),true); foreach($l["packages"] as $p) if($p["name"]==="wonder-image/immobili") echo substr($p["source"]["reference"],0,7);' "$d/composer.lock")
git -C "$d" commit -q -m "Aggiorna wonder-image/immobili" -m "immobili: dev-main $old -> $new. Il form degli immobili dichiara la barra di salvataggio del backend." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" -- composer.lock
git -C "$d" show --stat --format='%h %s' HEAD | cat
```

Expected: `<hash> Aggiorna wonder-image/immobili` e solo `composer.lock`.

Push (solo su richiesta esplicita dell'utente), con l'avviso sul deploy dello Step 3:

```bash
git -C /Users/andreamarinoni/Developer/boilerplates/immobili-site push origin main
gh run list -R wonder-image/immobili-site --workflow deploy.yml --limit 1 --json databaseId,status,displayTitle --jq '.[] | "\(.databaseId) \(.status) \(.displayTitle)"'
```

Expected: il push, poi `<id> <stato> Aggiorna wonder-image/immobili`. Poi `gh run watch <id> -R wonder-image/immobili-site --exit-status > /dev/null; echo "deploy exit $?"`. Se fallisce ancora a `Load secrets + generate .env` (`gh run view <id> -R wonder-image/immobili-site --log-failed | tail -40`), non dipende da questo rilascio: dillo all'utente.

- [ ] **Step 5: agliati e caso I22**

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
A=/Users/andreamarinoni/Developer/clients/agliati/projects/agliati-com
refs() { php -r '$l=json_decode(file_get_contents($argv[1]."/composer.lock"),true); foreach($l["packages"] as $p) if(str_starts_with($p["name"],"wonder-image/")) printf("%s %s %s %s\n", basename($argv[1]), $p["name"], $p["version"], substr($p["source"]["reference"]??"",0,7)); $n=json_decode(file_get_contents($argv[1]."/package-lock.json"),true); $j=json_decode(file_get_contents($argv[1]."/package.json"),true); printf("%s npm:wonder-image %s %s\n", basename($argv[1]), $n["packages"]["node_modules/wonder-image"]["version"]??"-", $j["dependencies"]["wonder-image"]??"-");' "$1"; }
cd "$A"
git fetch origin --quiet
git status --short --branch
refs "$A" | tee -a "$W/rilascio-prima.txt"
grep -n '^APP_URL=' .env
ls custom/modules/immobili/view/pages/backend/immobili/
diff custom/modules/immobili/view/pages/backend/immobili/form.php vendor/wonder-image/immobili/view/pages/backend/immobili/form.php && echo "override uguale al pacchetto installato"
```

Expected:
- `## main...origin/main` senza file modificati;
- oggi `agliati-com wonder-image/app dev-main 64853dc`, `agliati-com wonder-image/immobili dev-main ef06c1b` e `agliati-com npm:wonder-image 2.1.2-alpha.15 ^2.1.2-alpha.15`;
- `3:APP_URL=https://agliati.test`;
- `form.php` e `show.php`;
- `override uguale al pacchetto installato`.

Il confronto si fa prima di `composer update`, quando il `vendor` ha ancora il modulo senza attributi.

Se l'override è diverso, è personalizzato: fermati e chiedi all'utente. Secondo la regola dei docs, allora si aggiungono a mano i due attributi, oppure si ripubblica solo quel file con `php forge publish:module immobili pages/backend/immobili/form.php --force`. Mai `--force` su tutto l'albero: sovrascriverebbe le altre viste pubblicate in `custom/modules/immobili/view/` (28 file oltre a `form.php` il 2026-09-28), fra cui quelle personalizzate.

```bash
A=/Users/andreamarinoni/Developer/clients/agliati/projects/agliati-com
cd "$A"
git rm -q custom/modules/immobili/view/pages/backend/immobili/form.php
npm install 'wonder-image@^2.1.2-alpha.17' --no-audit --no-fund
composer update wonder-image/app wonder-image/immobili --no-interaction
```

Expected: `changed N packages`, l'aggiornamento dei due pacchetti e l'output di `forge config`.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
A=/Users/andreamarinoni/Developer/clients/agliati/projects/agliati-com
SEMVER="$(npm root -g)/npm/node_modules/semver/bin/semver.js"
refs() { php -r '$l=json_decode(file_get_contents($argv[1]."/composer.lock"),true); foreach($l["packages"] as $p) if(str_starts_with($p["name"],"wonder-image/")) printf("%s %s %s %s\n", basename($argv[1]), $p["name"], $p["version"], substr($p["source"]["reference"]??"",0,7)); $n=json_decode(file_get_contents($argv[1]."/package-lock.json"),true); $j=json_decode(file_get_contents($argv[1]."/package.json"),true); printf("%s npm:wonder-image %s %s\n", basename($argv[1]), $n["packages"]["node_modules/wonder-image"]["version"]??"-", $j["dependencies"]["wonder-image"]??"-");' "$1"; }
printf 'app main %s\n' "$(git ls-remote https://github.com/wonder-image/app refs/heads/main | cut -c1-7)"
printf 'immobili main %s\n' "$(git ls-remote https://github.com/wonder-image/immobili refs/heads/main | cut -c1-7)"
refs "$A"
node "$SEMVER" -r "$(node -p 'require(process.argv[1]+"/package.json").dependencies["wonder-image"]' "$A")" 2.1.2-alpha.17 > /dev/null && echo "alpha.17 nel range" || echo "FUORI RANGE"
printf 'wiSaveBar in head.js: %s\n' "$(grep -c wiSaveBar "$A/assets/lib/wonder-image/dist/backend/head.js")"
grep -c data-wi-save-bar "$A/vendor/wonder-image/immobili/view/pages/backend/immobili/form.php"
test ! -e "$A/custom/modules/immobili/view/pages/backend/immobili/form.php" && echo "override tolto"
test -f "$A/custom/modules/immobili/view/pages/backend/immobili/show.php" && echo "show.php resta"
git -C "$A" status --porcelain
grep -n '^APP_URL=' "$A/.env"
t=$(mktemp -d "$W/npmci.XXXXXX")
cp "$A/package.json" "$A/package-lock.json" "$t/"
[ -e "$A/.npmrc" ] && cp "$A/.npmrc" "$t/"
(cd "$t" && npm ci --ignore-scripts --no-audit --no-fund > /dev/null 2>&1) && echo "npm ci ok" || echo "npm ci FALLITO"
rm -rf "$t"
```

Expected:
- `wonder-image/app` e `wonder-image/immobili` con gli stessi `<hash>` delle righe `app main` e `immobili main`;
- `npm:wonder-image 2.1.2-alpha.17 ^2.1.2-alpha.17` e `alpha.17 nel range`;
- `wiSaveBar in head.js: N`, con N almeno 1;
- `2`, `override tolto` e `show.php resta`;
- in `git status --porcelain` solo ` M composer.lock`, `D  custom/modules/immobili/view/pages/backend/immobili/form.php`, ` M package-lock.json` e ` M package.json`. Un altro file cambiato da `forge config` (per esempio `.gitignore`) si mostra all'utente, che decide se va nel commit;
- `3:APP_URL=https://agliati.test`;
- `npm ci ok`.

Caso I22 su agliati.test, prima del commit:
1. Chiedi all'utente di fare il login nel pannello Browser su `https://agliati.test/backend/`. Claude non scrive le password.
2. `navigate` a `https://agliati.test/backend/team/create/` (TeamMemberResource, che ha già un Submit `upload` nel layout). Se la pagina dà 404, apri `https://agliati.test/backend/` e usa il link di creazione del team nel menu.
3. Leggi con Read `$HOME/.cache/wonder-tooling/save-bar/stato.js` e `casi.js` e incollali con `javascript_tool`.
4. Esegui `[__wiState(), await __wiBar()]`. Expected:
   - lo stato è `hidden` o `buttons`;
   - `forms` ha un solo elemento, con `dirtyAttr: false` e `dirty: false`;
   - `buttons` ha un solo elemento: il Salva di default non si aggiunge a quello del layout.
5. Clic con `computer` sul primo campo di testo del form (trovalo con `read_page` e `filter: interactive`), poi `type` con il testo `x`.
6. Esegui `[await __wiUntil('dirty-label'), await __wiBar()]`. Expected:
   - lo stato è `dirty-label`, oppure `dirty-buttons` se il Salva della pagina è sotto la fascia: in quel caso `__wiUntil` restituisce `dirty-buttons` dopo 3 secondi;
   - `forms[0].dirty` è `true`, `island` è `true`, `label` e `status` sono `"Operazione non salvata"`;
   - `buttons` ha ancora un solo elemento;
   - `copies` è `[]` con `dirty-label`, e con `dirty-buttons` ha un solo elemento con lo stesso testo di `buttons`.
7. `read_console_messages` con `onlyErrors: false`: nessun errore e nessun avviso.
8. Clic con `computer` sul titolo della pagina, poi `__wiLeave()`: deve restituire `true`.
9. `navigate` a `https://agliati.test/backend/immobili/create/` con `force: true`, reincolla `stato.js` ed esegui `await __wiBar()`. Expected: `forms` ha un solo elemento, con `id: "immobile-resource-form"` e `dirty: false`, e `buttons` ha un solo elemento. Il form ora viene dal pacchetto, perché l'override non c'è più.
10. `navigate` a `https://agliati.test/backend/`. Non si salva niente e non si crea nessun record.

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
printf 'I22 | agliati.test | OK | TeamMemberResource: un solo Salva, isola con modifiche, avviso di uscita; form immobili dal pacchetto con data-wi-save-bar\n' >> "$W/integrazione-esiti.txt"
tail -1 "$W/integrazione-esiti.txt"
```

Con un esito diverso da `OK`, scrivi `FALLITO` e cosa si è visto, fermati e chiedi all'utente prima del commit.

Commit (solo su richiesta esplicita dell'utente):

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
A=/Users/andreamarinoni/Developer/clients/agliati/projects/agliati-com
old=$(awk '$1 == "agliati-com" && $2 == "wonder-image/app" { print $4 }' "$W/rilascio-prima.txt")
oldi=$(awk '$1 == "agliati-com" && $2 == "wonder-image/immobili" { print $4 }' "$W/rilascio-prima.txt")
lib=$(awk '$1 == "agliati-com" && $2 == "npm:wonder-image" { print $3 }' "$W/rilascio-prima.txt")
new=$(php -r '$l=json_decode(file_get_contents($argv[1]),true); foreach($l["packages"] as $p) if($p["name"]==="wonder-image/app") echo substr($p["source"]["reference"],0,7);' "$A/composer.lock")
newi=$(php -r '$l=json_decode(file_get_contents($argv[1]),true); foreach($l["packages"] as $p) if($p["name"]==="wonder-image/immobili") echo substr($p["source"]["reference"],0,7);' "$A/composer.lock")
av=$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["version"] ?? "dev-main";' "$A/vendor/wonder-image/app/composer.json")
git -C "$A" commit -q -m "Aggiorna wonder-image/app a $av, wonder-image/immobili e wonder-image/lib a 2.1.2-alpha.17" -m "app: dev-main $old -> $new (versione letta da composer.json).
immobili: dev-main $oldi -> $newi.
lib: $lib -> 2.1.2-alpha.17.
Toglie l'override del form degli immobili, identico a quello del pacchetto: la vista del modulo dichiara la barra di salvataggio." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>" -- package.json package-lock.json composer.lock custom/modules/immobili/view/pages/backend/immobili/form.php
git -C "$A" show --stat --format='%h %s' HEAD | cat
git -C "$A" status --short --branch
```

Expected: il commit con quattro file, fra cui `custom/modules/immobili/view/pages/backend/immobili/form.php` tolto; poi `## main...origin/main [ahead 1]` senza altri file.

Push (solo su richiesta esplicita dell'utente). Il push avvia il deploy in produzione:

```bash
git -C /Users/andreamarinoni/Developer/clients/agliati/projects/agliati-com push origin main
gh run list -R wonder-image/agliati-com --workflow deploy.yml --limit 1 --json databaseId,status,displayTitle --jq '.[] | "\(.databaseId) \(.status) \(.displayTitle)"'
```

Expected: il push, poi `<id> <stato> Aggiorna wonder-image/app a ...`. `gh run watch <id> -R wonder-image/agliati-com --exit-status > /dev/null; echo "deploy exit $?"` deve dare `deploy exit 0`.

- [ ] **Step 6: Checklist "Dopo il rilascio"**

```bash
SITES=/Users/andreamarinoni/Developer/boilerplates
A=/Users/andreamarinoni/Developer/clients/agliati/projects/agliati-com
SEMVER="$(npm root -g)/npm/node_modules/semver/bin/semver.js"
for d in "$SITES/new-site" "$SITES/immobili-site" "$SITES/rsvp-site" "$A"; do
  r=$(node -p 'require(process.argv[1]+"/package.json").dependencies["wonder-image"]' "$d")
  v=$(node -p 'require(process.argv[1]+"/package-lock.json").packages["node_modules/wonder-image"].version' "$d")
  printf '%s range %s lock %s ' "${d##*/}" "$r" "$v"
  node "$SEMVER" -r "$r" 2.1.2-alpha.17 > /dev/null && echo "alpha.17 nel range" || echo "FUORI RANGE"
  printf 'wiSaveBar in head.js: %s\n' "$(grep -c wiSaveBar "$d/assets/lib/wonder-image/dist/backend/head.js")"
  git -C "$d" status --short --branch | head -1
done
printf 'immobili in immobili-site: %s\n' "$(grep -c data-wi-save-bar "$SITES/immobili-site/vendor/wonder-image/immobili/view/pages/backend/immobili/form.php")"
for r in new-site immobili-site rsvp-site agliati-com; do
  id=$(gh run list -R "wonder-image/$r" --workflow deploy.yml --limit 1 --json databaseId --jq '.[0].databaseId')
  printf '%s deploy %s: ' "$r" "$id"
  gh run view "$id" -R "wonder-image/$r" --json jobs --jq '[.jobs[].steps[] | select(.name | test("Installa Pacchetti JS")) | .conclusion] | join(",")'
done
```

Expected:
- per ogni sito `range ^2.1.2-alpha.17 lock 2.1.2-alpha.17 alpha.17 nel range`, cioè range e lock rigenerato;
- `wiSaveBar in head.js: N`, con N almeno 1: la dist del sito contiene la barra;
- `## main...origin/main` dopo i push, oppure `[ahead N]` per i siti di cui l'utente non ha chiesto il push;
- `immobili in immobili-site: 2`: la release di immobili è installata;
- `success` per lo step `npm ci` (`Installa Pacchetti JS`) di new-site, rsvp-site e agliati-com.

Per immobili-site lo step `npm ci` resta vuoto finché il deploy si ferma prima, a `Load secrets + generate .env`. In quel caso vale il controllo `npm ci ok` dello Step 3, e nel riepilogo si scrive che la CI di immobili-site non arriva a `npm ci`.

- [ ] **Step 7: Riepilogo e pulizia**

```bash
W="$HOME/.cache/wonder-tooling/save-bar"
grep -e '^I21 |' -e '^I22 |' "$W/integrazione-esiti.txt"
```

Expected: le righe I21 e I22 con `OK`.

Manda all'utente il riepilogo, con i valori degli Step 1-6 al posto di quelli fra `<>`:

```text
Rilascio della barra di salvataggio
1. lib: wonder-image 2.1.2-alpha.17 pubblicata sul canale alpha (workflow publish verde); latest resta 2.1.2-alpha.4
2. app: PR #<N> unita in main (<hash>); skill: main aggiornato <si o no>, forge skills <fatto o no>
3. new-site, immobili-site, rsvp-site: range ^2.1.2-alpha.17, lock 2.1.2-alpha.17, app <hash>; commit <hash per sito>; push <siti>; I21 <esito>
4. immobili: main <hash>, senza tag; immobili-site aggiornato, commit <hash>, push <si o no>
5. agliati: override del form tolto, app <hash>, immobili <hash>, lib 2.1.2-alpha.17; commit <hash>, push <si o no>; I22 <esito>
6. Dopo il rilascio: range e lock <esito>, dist con wiSaveBar <esito>, npm ci in CI <esito per sito>, immobili installato <esito>
Da decidere: tag v2.0.0 di immobili; deploy di immobili-site fermo a Load secrets; worktree e branch backend-save-bar
```

La pulizia si fa solo su richiesta esplicita dell'utente, dopo i merge:
- **worktree dei branch:** `git -C /Users/andreamarinoni/Developer/packages/<repo> worktree remove .claude/worktrees/backend-save-bar` per lib, app, immobili e skills. Prima controlla che `git -C /Users/andreamarinoni/Developer/packages/<repo>/.claude/worktrees/backend-save-bar status --porcelain | wc -l` dia `0`;
- **branch locali già uniti:** `git -C /Users/andreamarinoni/Developer/packages/<repo> branch -d backend-save-bar`; `-d` rifiuta un branch non unito;
- **branch remoti:** `git -C /Users/andreamarinoni/Developer/packages/<repo> push origin --delete backend-save-bar`;
- **copia ignorata della lib:** `test/backend/file-references.test.cjs` nella checkout principale della lib;
- **cartella di lavoro:** `$HOME/.cache/wonder-tooling/save-bar`, con i registri. Prima si consegnano all'utente `integrazione-esiti.txt` e `record-di-prova.txt`. La cache di Playwright in `$HOME/.cache/wonder-tooling/playwright` resta.
