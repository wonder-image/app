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

## Pannello account e storage

`AccountPanel` fornisce la policy di presentazione. I componenti condivisi sono
`frontend.account.navigation` e `frontend.account.row`; i layout sono
`frontend.account.auth` e `frontend.account.panel`. La navigazione usa voci
`key`, `href`, `label`, `icon`: lo stato attivo è semantico (`aria-current`).
Il menu laterale diventa orizzontale e scorrevole su telefono. Righe con
separatore, dati a sinistra e azioni a destra sostituiscono i box annidati.
Lo stesso componente può presentare le future righe ordini/coupon; le loro
query, autorizzazioni e logiche restano nel modulo responsabile.

Per personalizzare i dati personali usare `personalFields()`,
`validatePersonal()` (messaggi tradotti per l'alert), `personalUserValues()`
(whitelist esplicita) e `afterPersonalSaved()`. `overviewRows()` personalizza
il riepilogo. Il controller ecommerce conserva gli invarianti base su identità
e cellulare; gli hook aggiungono richieste, non aggirano i controlli.

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

`AccountAddressModal::make($id, $title, $fields, $action, $cancelUrl, $csrf)`
colloca Annulla e Salva nel `wi-modal-footer`, allineati a destra in questo
ordine. Salva usa l'attributo nativo `form` per inviare il form nel body,
conservando CSRF e validazione; Annulla chiude il dialogo senza inviare dati.
Il form autonomo mantiene le proprie azioni; nel modal sono disabilitate
tramite `show_actions = false`, senza duplicare pulsanti o form.
La composizione riusa `Modal::frontend()` e `frontend.account.address-form`. Il tema Wonder
riusa `wi-modal` e `modal('#id')`; il renderer Bootstrap rimane compatibile.
L'opt-in mantiene invisibili sul frontend i vecchi modal backend. Il controller
mantiene ownership, CSRF, route non-JS e gli alert fuori dal form; su errore
restituisce la lista con valori conservati nel modal, non un alert dentro di
esso. Passare i dialoghi al layout con `page_modals`: sono resi dopo `main`,
fuori dalla colonna dei contenuti/form. Il core offre ora Resource generiche
per la rubrica; il gestionale mantiene il proprio pannello più completo.
Vedi [Contatti backend](contatti-backend.md).

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

- Dal core: `php tests/auth-frontend.php` e lint dei file modificati.
- Dal modulo: `php tests/AuthValidatorTest.php`,
  `php tests/integrazione/AuthCoreViewsTest.php`,
  `php tests/integrazione/AuthHttpTest.php`,
  `php tests/integrazione/AuthAccountTest.php`,
  `php tests/integrazione/CartSessionTest.php` e
  `php tests/integrazione/ImpersonationTest.php` nel sito locale disponibile.
- Browser: login, signup, recupero/ripristino, captcha mancante, alert esterno
  al form, viewport mobile e regressioni del tema. Google e consegna email
  reali richiedono credenziali e trasporto di test.
