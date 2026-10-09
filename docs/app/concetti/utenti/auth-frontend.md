# Auth frontend e pannello account

I flussi frontend riutilizzabili vivono in `class/Auth/Frontend`, senza
dipendenze da ecommerce o gestionale. Il backend mantiene le proprie pagine:
nessuna route frontend viene attivata automaticamente sui siti esistenti.

## Attivazione e responsabilità

Il sito o modulo registra `AuthRoutes::register(new AuthProfile())` nel proprio
file delle route frontend. Il profilo definisce prefisso URL, nomi delle route,
area, authority ammesse, destinazione dopo login, consensi, durata dei token e
provider. Ogni profilo deve avere una `key()` univoca e stabile: identifica
nonce, purpose del completamento e action reCAPTCHA. Le route possono essere
ricaricate dal framework; la registrazione accetta nuovamente lo stesso tipo
di profilo, ma rifiuta tipi diversi con la stessa chiave.

Prima di usarlo, registrare la permission nel builder del sito/modulo con
le route login, sign-in, recupero e ripristino password e con
`verification('email', ...)` che punti a `<routePrefix>.email.verify` e
`<routePrefix>.email.sent`. La verifica email deve essere richiesta per il
flusso locale in due passaggi. Il core non inventa nuove authority né modifica
i permessi dei siti esistenti. `registrationAuthority()` è la singola
authority inviata al vecchio orchestratore `user()`; `authorities()` è invece
l'elenco di accesso ammesso da login e dal gateway federato.

`AuthController` gestisce login/logout, registrazione in due passaggi, verifica
email, reset, Google e, se abilitata, impersonificazione. Riutilizza i servizi
di sicurezza esistenti; `UserAccountGateway` opera solo sugli utenti del core.
Il gestionale non serve per autenticarsi. `EcommerceAuthProfile` è un
adattatore del modulo: impone cellulare, privacy/condizioni ecommerce e collega
il contatto con `afterUserSaved()`. Nessuna fatturazione o regola di gioco
entra nel suo payload di registrazione.

## Aggiungere campi senza duplicare il flusso

Estendere `AuthProfile` (oppure `EcommerceAuthProfile` nel negozio). Le superfici
sono `login`, `signup-request`, `signup-completion`, `password-recovery` e
`password-restore`.

```php
public function fields(string $surface, array $values = [], bool $passwordRequired = true): array
{
    $fields = parent::fields($surface, $values, $passwordRequired);
    if ($surface === 'login') {
        $fields['country'] = FormField::key('country')->country()
            ->label((string) __t('pages.auth.country'))->required()
            ->value($values['country'] ?? '');
    }
    return $fields;
}

public function validate(string $surface, array $input, bool $passwordRequired = true): array
{
    $errors = parent::validate($surface, $input, $passwordRequired);
    if ($surface === 'login' && !in_array($input['country'] ?? '', ['IT', 'DE'], true)) {
        $errors['country'] = 'invalid';
    }
    return $errors;
}

public function validationMessages(array $errors): array
{
    $messages = parent::validationMessages(array_diff_key($errors, ['country' => true]));
    if (isset($errors['country'])) { $messages[] = 'pages.auth.country_invalid'; }
    return $messages;
}
```

Importare `Wonder\App\ResourceSchema\FormField` e definire i testi in `lang/`.
`columns()` e `fieldSpan()` configurano la griglia: il cellulare conserva un
prefisso breve e il numero esteso. Le nuove traduzioni condivise sono `auth.*`
e `account.*`; eventuali override del vecchio `ecommerce.auth.*` vanno riportati
in `lang/{locale}/auth.json` del sito. Le traduzioni ecommerce restano per
compatibilità e per le pagine business del modulo.
`required()` nel form **non** sostituisce la validazione server. La nazione nel
login è una scelta applicativa aggiuntiva, mai un'alternativa alle credenziali.

`userValues()` ammette solo i campi esplicitamente autorizzati. Un nuovo campo
visualizzato non viene salvato automaticamente. Per dati che appartengono
all'utente, estendere questa whitelist; per fatturazione, contatti o sistemi
esterni usare `afterUserSaved($userId, $surface, $input)` con un servizio del
modulo/sito. Gli hook devono essere idempotenti e validare i dati prima delle
scritture: un errore non deve consumare il token di completamento. Non assumere
una transazione distribuita con i provider esterni.

Il login Google usa `validateFederated($identity, $input, $newAccount)` come
policy esplicita separata. La verifica dell'ID token non sostituisce la
validazione di eventuali campi applicativi; il profilo deve applicare lì le
proprie richieste e, se necessario, registrare consensi espliciti. Il profilo
ecommerce conserva il comportamento preesistente: non interpreta le policy
mostrate da Google come accettazioni dei checkbox locali. `requiresCompletion()`
determina se un federato deve completare il profilo; estenderlo quando si
richiedono campi ulteriori al cellulare.

## Pannello account

Il pannello del cliente è del core, come l'auth: `AccountRoutes` si attiva a
richiesta e non cambia i siti che non lo registrano. Il sito o il modulo lo
registra nel file delle route frontend, accanto a `AuthRoutes`:

```php
AuthRoutes::register(new AuthProfile());
AccountRoutes::register(new AccountPanel(), new AuthProfile());
```

`register(AccountPanel $panel, ?AuthProfile $auth = null)` apre il gruppo
`/account/`: nomi `account.*`, area `frontend`, login richiesto e authority di
`AccountPanel::authorities()` (per default `['client']`). Il profilo dà al
pannello logout, cellulare obbligatorio e la vista dei messaggi; senza, usa
`AuthProfile`. Registrare due pannelli di classi diverse lancia
`LogicException`; ripetere la stessa registrazione è innocuo.

| Nome | URL | Note |
|---|---|---|
| `account.index` | `/account/` | Panoramica |
| `account.personal` | `/account/dati-personali/` | GET e POST |
| `account.email.confirm` | `/account/email/conferma/?token=…` | pubblica, senza pannello |
| `account.addresses` | `/account/indirizzi/` | elenco a schede |
| `account.addresses.create` | `/account/indirizzi/nuovo/` | GET e POST |
| `account.addresses.edit` | `/account/indirizzi/{id}/` | GET e POST |
| `account.addresses.delete` | `/account/indirizzi/{id}/elimina/` | solo POST |
| `account.billing` | `/account/fatturazione/` | GET e POST |

Gli URL del login e della registrazione (`/account/auth/…`) non cambiano. Le
sezioni sono `overview`, `personal`, `addresses` e `billing`: una sottoclasse di
`AccountPanel` toglie da `sections()` `personal`, `addresses` o `billing` e le
loro route non si registrano.
La stessa sottoclasse cambia `title()`, `authorities()` e `parentLayout()`; se
sovrascrive un hook chiama `parent::`, altrimenti perde le estensioni dei moduli.
Le viste del pannello sono sigillate: non si sostituiscono da `custom/`, si
estendono con i ganci qui sotto. `AccountPage` imposta il SEO (`NOINDEX,NOFOLLOW`,
breadcrumb vuoto) e passa alla vista menu, avviso e modal.

### Estendere il pannello da un modulo

Un modulo non copia il controller: implementa `AccountExtension` (o estende
`BaseAccountExtension`, che non fa nulla, e sovrascrive solo i ganci che gli
servono) e la registra con `AccountRoutes::extend()`. L'ordine rispetto a
`register()` non conta: le route dell'estensione nascono appena c'è il pannello.

| Gancio | Cosa fa |
|---|---|
| `routes()` | Registra le route del modulo; per quelle private usare `AccountRoutes::group(callable)`, che mette prefisso `/account`, nomi `account.*`, login e permessi del pannello. |
| `navigation(array $items, object $user)` | Riceve le voci per chiave (`overview`, `personal`, `addresses`, `billing`), ciascuna con `label`, `href`, `icon`: si aggiunge, si ritocca o si toglie con `unset`. Una voce senza `href` o etichetta non esce. |
| `overviewRows(array $rows, object $user)` | Righe della Panoramica. |
| `personalRows(array $rows, object $user)` | Righe di «Dati personali». |
| `personalFields`, `validatePersonal`, `personalUserValues`, `afterPersonalSaved` | Campi del modal dei dati personali, messaggi di validazione già tradotti, valori da scrivere sull'utente oltre a quelli del core, ed effetti dopo il salvataggio. Aggiungono richieste, non aggirano le verifiche su identità e cellulare. |
| `head()` | Markup da aggiungere nell'head delle pagine del pannello (font, stile). |

Una riga ha questa forma, ed è quella che stampa `frontend.account.row`:

```php
[
    'key' => 'orders',
    'columns' => [['label' => 'Ordini', 'value' => '3']],
    'action' => [
        'label' => 'Vedi', 'href' => Route::url('account.orders'),
        'modal' => '',        // id di un modal: apre il dialogo al posto del link
        'icon' => 'bi bi-bag',
        'disabled' => false,  // bottone spento, con `hint` come nota
        'hint' => '',
    ],
]
```

Le pagine nuove sono di un controller che estende `AccountController`: la classe
non è `final` e i suoi helper (`page()`, `user()`, `contact()`, `flash()`,
`redirect()`, `requireCsrf()`, `translate()`) sono `protected`. Il modulo
risponde alle sue azioni e passa le altre a `parent::handle()`:

```php
use Wonder\Auth\Frontend\AccountController;
use Wonder\Auth\Frontend\AccountRoutes;
use Wonder\Auth\Frontend\BaseAccountExtension;
use Wonder\Http\Route;

final class ShopAccountExtension extends BaseAccountExtension
{
    public function routes(): void
    {
        AccountRoutes::group(static function (): void {
            // Il file del modulo che costruisce il controller (vedi sotto).
            $handler = dirname(__DIR__, 2).'/http/frontend/account.php';
            Route::get('/ordini/', $handler, ['account_action' => 'orders'])->name('orders');
        });
    }

    public function navigation(array $items, object $user): array
    {
        $items['orders'] = ['label' => (string) __t('shop.orders'), 'href' => Route::url('account.orders'), 'icon' => 'bi bi-bag'];
        return $items;
    }
}

class ShopAccountController extends AccountController
{
    public function handle(string $action, array $parameters = []): void
    {
        match ($action) {
            'orders' => $this->orders(),
            default => parent::handle($action, $parameters),
        };
    }

    protected function orders(): void
    {
        // La vista del modulo: il percorso dipende da dove sta il controller.
        $view = dirname(__DIR__, 2).'/view/pages/account/orders.php';
        $this->page($view, 'orders', ['title' => (string) __t('shop.orders'), 'seo_url' => Route::url('account.orders')]);
    }
}
```

Il modulo registra l'estensione dove registra le sue route, con
`AccountRoutes::extend(new ShopAccountExtension())`: senza questa chiamata
`routes()` e `navigation()` non partono mai. L'ecommerce lo fa in
`config/routes/route.frontend.php`, subito dopo `AccountRoutes::register()`
(l'ordine non conta):

```php
AccountRoutes::register(new AccountPanel(), new AuthProfile());
AccountRoutes::extend(new ShopAccountExtension());
```

Il file `account.php` del modulo costruisce `ShopAccountController` con
`AccountRoutes::panel()` e `AccountRoutes::auth()` e chiama `handle()` con
`$ROUTE_META['account_action']`, come fa `app/http/frontend/account.php` per il
core. La vista del modulo apre con `$account_panel->layout(compact('title',
'active', 'navigation', 'errors', 'notice', 'modals', 'logout_url',
'logout_token', 'head', 'user'))` e chiude con `View::end()`, come le viste
del core. Il secondo argomento di `page()` è la voce di menu attiva.

I componenti condivisi sono `frontend.account.navigation`,
`frontend.account.row` e `frontend.account.pagination` (vedi «Paginazione» qui
sotto). La navigazione usa voci `key`, `href`, `label`, `icon`:
lo stato attivo è semantico (`aria-current`). Il menu laterale diventa
orizzontale e scorrevole su telefono. Righe con separatore, dati a sinistra e
azioni a destra sostituiscono i box annidati.

### Paginazione

Una sezione con molte righe (ordini, coupon) si divide in pagine con
`Wonder\Auth\Frontend\AccountPagination` e il componente
`frontend.account.pagination`. La pagina si chiede con `?pagina=N`; la prima
pagina è l'URL base, senza parametro.

`AccountPagination::make(int $total, mixed $page, int $perPage = 10)` restituisce
`page`, `pages`, `per_page`, `offset`, `from`, `to`, `total` e `limit`, la
stringa `offset, per_page` da mettere nel `LIMIT` della query. La pagina chiesta
si riporta sempre tra 1 e l'ultima: `0`, un numero negativo, un testo o un array
danno la prima, `2.7` dà la 2 e una pagina oltre la fine dà l'ultima, quindi non
escono errori né tabelle vuote e «Risultati da X a Y di Z» resta coerente.
`requested()` legge `?pagina=` così com'è e lo lascia ripulire a `make()`;
`url($base, $page)` costruisce il link a una pagina (aggiunge `&` se la base ha già
un `?`); `window($pagination)` dà le pagine da mostrare, la corrente con due per
lato.

Il componente riceve `pagination` (il risultato di `make()`) e `base_url` (l'URL
della sezione, senza `?pagina`) e stampa il piede della tabella: «Risultati da X
a Y di Z» e i quadrati delle pagine, con la corrente segnata da
`aria-current="page"` e le frecce spente agli estremi (`aria-disabled`). Va
messo come ultimo figlio dentro `.wi-row-table`. Con zero righe non stampa nulla:
in quel caso la vista mostra lo stato vuoto (`.wi-empty-state`) al posto della
tabella. I testi sono del core, sotto `account.pagination.*` (`summary`, `label`,
`previous`, `next`).

```php
protected function orders(): void
{
    // $total: quante righe ci sono in tutto (una COUNT(*) con le stesse condizioni).
    $pagination = AccountPagination::make($total, AccountPagination::requested());
    // $rows: le sole righe della pagina, con LIMIT $pagination['limit'] e un ordine
    // stabile (la data, poi l'id), altrimenti le pagine possono ripetere o saltare righe.
    $this->page($view, 'orders', [
        'title' => (string) __t('shop.orders'),
        'seo_url' => Route::url('account.orders'),
        'rows' => $rows,
        'pagination' => $pagination,
        'base_url' => Route::url('account.orders'),
    ]);
}
```

La vista stampa la tabella a righe e il componente in fondo. Con le tre righe
dell'esempio (`$pagination['total']` vale 3) il piede dice «Risultati da 1 a 3 di
3» e ha un solo quadrato, il 1, tra le due frecce spente:

```php
<?php
use Wonder\Elements\Components\Button;
use Wonder\View\View;
?>
<div class="wi-row-table" style="--wi-row-table-columns: minmax(0, 2fr) minmax(0, 1fr) auto">
    <div class="wi-row-table__head">
        <span class="wi-row-table__cell">Ordine</span>
        <span class="wi-row-table__cell">Totale</span>
        <span class="wi-row-table__cell"></span>
    </div>
    <?php foreach ($rows as $row): // tre righe ?>
        <div class="wi-row-table__row">
            <div class="wi-row-table__cell">
                <div class="wi-row-table__title"><?=e($row['title'])?></div>
                <div class="wi-row-table__subtitle"><?=e($row['date'])?></div>
            </div>
            <div class="wi-row-table__cell"><strong><?=e($row['total'])?></strong></div>
            <div class="wi-row-table__cell">
                <?=Button::to($row['href'], 'Visualizza')->outline()->variant('black')->size('sm')->arrow()->render()?>
            </div>
        </div>
    <?php endforeach; ?>
    <?=View::component('frontend.account.pagination', ['pagination' => $pagination, 'base_url' => $base_url])?>
</div>
```

### Dati personali, email e password

«Dati personali» ha tre righe e ognuna apre il suo modal: la prima per nome, data
di nascita (`birth_date` della scheda `Contact`) e cellulare, la seconda per
l'email, la terza per la password. Il modal dell'email c'è solo se l'account ha già
una password (il cambio email la chiede): senza, la riga non ha «Modifica» ma la
nota «Imposta prima una password per cambiare l'email», e la password si imposta dal
terzo modal.
`AccountPersonal` salva nome, data e cellulare, passando dai ganci
dell'estensione (`validatePersonal()` prima, `personalUserValues()` e
`afterPersonalSaved()` dopo), in una sola transazione con la scheda: o tutto o
niente. Il core conserva gli invarianti base su identità e cellulare.

Il cambio email (`AccountEmail`) chiede la password attuale e manda alla nuova
casella un link monouso (token `email_change`, 24 ore) a
`GET /account/email/conferma/?token=…`; fino al clic resta valida la vecchia.
L'esito è una pagina di solo messaggio, senza pannello e senza toccare la
sessione, quindi funziona anche da un altro browser. Un account senza password
(creato con Google) non può cambiare email dal pannello: la riga mostra solo la nota.

Il cambio password vive in `Wonder\Auth\Frontend\AccountPassword`. `fields()`
chiede la password attuale solo se l'account ne ha una: un account nato da un
accesso federato la imposta senza. Non c'è il campo di conferma: il pannello
chiama `AuthValidator::completion($input, true, false, false)`. `change($userId, $input)` valida (password
attuale, regole di `AuthValidator::completion()`, nuova diversa dall'attuale),
salva l'hash, revoca tutti i token "ricordami" dell'utente con
`RememberMe::revokeUser()` e rigenera l'id di sessione: la sessione corrente
resta aperta, un cookie copiato prima del cambio non entra più. Gli errori si
traducono con `AuthValidationAlert::messageKeys()`.

Ogni submit del pannello ha la classe `wi-input-submit`: la lib lo tiene spento
finché mancano i campi obbligatori. Chi aggiunge un form a mano nel pannello
fa lo stesso (vedi `AccountModal` più sotto).

## Storage dei contatti

La rappresentazione è distinta dall'archiviazione. Il core possiede i modelli
`Wonder\App\Models\Contacts\Contact`, `ContactAddress` e
`Wonder\App\Models\System\ExternalReference`. Sono disponibili anche senza
gestionale: una scheda contiene una fatturazione e il collegamento all'utente,
più indirizzi di consegna vivono nella relazione. Tutti escludono la sync dei
dati operativi. I nomi SQL sono `contacts`, `contact_addresses` e
`external_references`. Forge update esegue `SharedContactTablesMigration`
prima dell'allineamento degli schemi: rinomina in un'unica operazione le
corrispondenti tabelle legacy `gst_*`, conservando ID, righe e FK. Se esistono
entrambi i nomi si interrompe senza cancellare o fondere dati. Aggiornare app
e gestionale insieme ed eseguire Forge update prima di riaprire il sito.

Il gestionale mantiene subclass compatibili nei vecchi namespace; Contact
aggiunge listino, metodo e termine di pagamento. Le sue resource e i vincoli
commerciali restano nel modulo, non compaiono nei siti app-only. Non disabilitare
il gestionale su un database operativo senza un backup e una verifica dello
schema: Forge allinea le colonne al modello del modulo abilitato.

`Wonder\Auth\Frontend\ContactAccount::link($userId, $input)` offre il
collegamento riutilizzabile senza dati di fatturazione: riusa la scheda già
collegata o una email non assegnata, rifiuta una scheda di un altro account.
Invocarlo da `afterUserSaved()` quando un progetto richiede questa rubrica;
l'auth generica non crea contatti automaticamente. Per un cellulare passato
esplicitamente usare `phone_prefix` e numero nazionale `phone`.

`AccountAddressForm::layout()` usa un singolo contenitore per cella e applica
la visibilità condizionale dell'azienda alla cella intera, senza buchi nella
griglia; i campi aziendali seguono `type = business` anche per i required.

`AccountAddressValidation::schema()` dichiara i required del salvataggio
completo e conserva i vincoli aggiuntivi dell'estensione; `validate()` controlla
anche chiavi omesse, valori vuoti e province appartenenti al paese, restituendo
messaggi tradotti con la label. La provincia è richiesta quando il dataset
del paese contiene stati/province. L'etichetta della spedizione è opzionale.
Non imporre questi vincoli al Model Contact: l'account nasce senza
fatturazione. I campi fiscali opzionali usano `WhenFilledValidator`, mentre
eventuali required espliciti continuano a valere anche per un valore vuoto.

`AccountModal::make($id, $title, $fields, $action, $hidden, $errors, $open)`
compone il modal del pannello con `Modal::frontend()`: un form POST verso
`$action` con CSRF, i campi (`AccountAddressForm::layout()`) nel corpo e, nel
`wi-modal-footer`, un solo «Salva» nero a tutta larghezza. Il bottone ha classe
`wi-input-submit`, quindi resta spento finché mancano i campi obbligatori. Con
`$open = true` e `$errors` il modal esce già aperto (`wi-show`), con gli errori
dentro e i valori inseriti: è la risposta a un salvataggio non riuscito.
`AccountModal::confirm($id, $title, $text, $action, $label)` fa lo stesso per
una conferma, come l'eliminazione di un indirizzo. Il tema Wonder riusa
`wi-modal` e `modal('#id')`; il renderer Bootstrap rimane compatibile. L'opt-in
mantiene invisibili sul frontend i vecchi modal backend. Il controller mantiene
ownership, CSRF e route non-JS; passa i dialoghi alla pagina nell'array `modals`:
sono resi dopo `main`, fuori dalla colonna dei contenuti/form. Il core offre ora
Resource generiche per la rubrica; il gestionale mantiene il proprio pannello
più completo. Vedi [Contatti backend](contatti-backend.md).

Paese e provincia sono FormField `country('province')` / `states($country)`,
alimentati da `countries()` / `states()` come i vecchi helper. La provincia
parte senza selezione automatica. La lib aggiornata gestisce le select native
e aggiorna insieme opzioni e lista visibile attraverso `/api/states/`, senza
azzerare il valore al semplice focusout. Distribuire anche il bundle frontend
aggiornato; i vecchi text-list rimangono supportati.

Il tema Wonder mantiene il select nativo visibile finché la lib non lo
arricchisce. `data-wi-select-search` abilita la ricerca senza nuove dipendenze:
la label attiva il pulsante visibile e porta alla ricerca, le frecce cambiano
l'opzione attiva, Invio seleziona ed Esc chiude solo la lista prima del modal.
Il layer `wi-modal` (1100) supera l'header del sito (101); i dialoghi restano
fuori dal contenitore del form e gli alert restano nella pagina.

`AccountAddressForm::fields($extension, $values, $submitted)` applica le label
tradotte dell'estensione e il prefisso del paese quando manca su una nuova
pagina; conserva il prefisso esplicito e i valori vuoti di un POST fallito.
`layout($fields)` compone `Container` con colonne responsive: nessun tema
forzato o CSS aggiuntivo. Il controller decide ownership, CSRF, whitelist e
validazione; la classe di presentazione non salva dati.

I metodi di pagamento appartengono al provider, mai al database delle password
o al frontend. ExternalReference distingue test/live, provider e tipo di
oggetto, con unicità sull'identificativo esterno. Conservare solo riferimenti,
mai PAN/CVC o segreti di pagamento; il portale Stripe resta da integrare.

## Tema e sicurezza frontend

Non forzare `render('wonder')`. Le view del core usano il tema della pagina e
le classi esistenti della lib, senza copiare CSS legacy o creare nuove classi
`wi-*`. Le viste si personalizzano con i normali override `custom/view`;
campi, validazione e persistenza si estendono dal profilo, non dall'HTML.

I colori auth usano `--auth-bg-color`, `--auth-tx-color`,
`--auth-form-bg-color` e `--auth-form-tx-color` con fallback ai token del sito.
`--auth-form-border-color` resta disponibile per varianti con contenitore
bordato. Input, focus, bottoni e alert continuano a leggere i token condivisi;
il pannello usa `--primary-color`, `--primary-color-10`, `--tx-color-10`,
`--input-border-color`, `--button-border-radius` e `--spacer`.

Gli errori appaiono in un solo alert di pagina, fuori dal form. CSRF condiviso
(`AuthSession` delega a [`Wonder\Http\Csrf`](../form/csrf.md), stesso token)
e reCAPTCHA con action scoped proteggono i POST pubblici tradizionali. Google
ha CSRF, nonce monouso scoped per profilo e verifica server-side; un provider
disattivato non è accettato dal controller. I redirect restano same-origin.

Auth è `NOINDEX,FOLLOW`, account `NOINDEX,NOFOLLOW`, canonical senza query,
un solo h1, breadcrumb SEO vuoto e nessun JSON-LD pubblico per queste pagine.
`frontend.layout.body-start` inizializza il dataLayer con il solo id utente
interno (null per ospiti) e consuma una volta gli eventi `login`/`sign_up`
accodati solo dopo il successo. Niente email, telefoni, token o indirizzi.

## Verifiche

### Apertura dei modal

`Modal::make(...)->id('shipping-new')->frontend()` abilita il renderer Wonder
senza cambiare la compatibilità dei modal backend. Il tema rimane quello della
pagina. `Button::to($fallbackUrl, $label)->opensModal('shipping-new')` genera
il trigger Wonder o Bootstrap appropriato; niente `onclick` nelle viste.
Non usare il trigger su submit, reset o lightbox. La lib mantiene `modal()`
per le viste legacy, gestisce focus, Esc, Tab e dialoghi chiusi inert.
L'href mantiene la pagina di modifica senza JavaScript. Gli alert restano
nella pagina, non nel modal né nel form.

- Dal core: `php tests/auth-frontend.php`, `php tests/account-password.php`,
  `php tests/account-routes.php` e lint dei file modificati.
- Dal modulo: `php tests/AuthValidatorTest.php`,
  `php tests/integrazione/AuthCoreViewsTest.php`,
  `php tests/integrazione/AuthHttpTest.php`,
  `php tests/integrazione/AuthAccountTest.php`,
  `php tests/integrazione/CartSessionTest.php` e
  `php tests/integrazione/ImpersonationTest.php` nel sito locale disponibile.
- Browser: login, signup, recupero/ripristino, captcha mancante, alert esterno
  al form, viewport mobile e regressioni del tema. Google e consegna email
  reali richiedono credenziali e trasporto di test.
