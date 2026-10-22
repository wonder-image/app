<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\ResourceSchema\FormField;
use Wonder\Http\Csrf;
use Wonder\Http\Route;
use Wonder\View\View;

/** Pagine del pannello account del core. I moduli lo estendono per le loro sezioni. */
class AccountController
{
    /** L'azione in corso: dice a {@see renderCsrfFailure()} quale pagina ridisegnare. */
    protected string $action = '';

    public function __construct(protected readonly AccountPanel $panel, protected readonly AuthProfile $auth) {}

    public function handle(string $action, array $parameters = []): void
    {
        $this->action = $action;
        match ($action) {
            'index' => $this->overview(),
            'personal' => $this->personal(),
            'email.confirm' => $this->confirmEmail(),
            default => $this->notFound(),
        };
    }

    protected function overview(): void
    {
        if (!$this->panel->enabled('overview')) {
            $first = array_values($this->panel->navigation($this->user()))[0]['href'] ?? '';
            $first !== '' ? $this->redirect($first) : $this->notFound();
        }
        $user = $this->user();
        $this->page(AccountPage::view('index'), 'overview', [
            'title' => (string) __t('account.overview.title'),
            'seo_url' => Route::url('account.index'),
            'rows' => $this->panel->overviewRows(AccountOverview::rows($this->panel, $user), $user),
            'errors' => $this->panel->pageErrors([], $user, 'overview'),
        ]);
    }

    /**
     * Dati personali: dati, email, password e i modal delle estensioni (la fatturazione di customer-account).
     * Una riga con un modal ciascuna; ogni modal posta qui con il campo nascosto `form`.
     */
    protected function personal(): void
    {
        $open = '';
        $errors = [];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->requireCsrf();
            $user = $this->user();
            $form = $this->text($_POST['form'] ?? '');
            // Senza password l'email non si cambia (la richiede AccountEmail): quel modal non esiste.
            $errors = match ($form) {
                'personal' => $this->savePersonal((int) $user->id),
                'email' => $this->hasPassword((int) $user->id) ? $this->requestEmail($user) : $this->notFound(),
                'password' => $this->changePassword((int) $user->id),
                default => $this->saveExtensionForm($form, $user),
            };
            $open = $form;
        }

        $this->renderPersonal($open, $errors, $open !== '' ? $this->personalValues() : []);
    }

    /**
     * La pagina dei dati personali con il modal `$open` aperto, i suoi errori e i valori scritti.
     *
     * @param list<string> $errors Già tradotti.
     */
    protected function renderPersonal(string $open, array $errors, array $values): void
    {
        $user = $this->user();
        $hasPassword = $this->hasPassword((int) $user->id);

        $modals = [AccountModal::make(
            'account-personal', (string) __t('account.personal.modal_title'),
            // Solo i valori del proprio form: anche la fatturazione posta `name` e `surname`.
            $this->panel->personalFormFields(
                $this->panel->personalFields($this->personalFields($user, $open === 'personal' ? $values : []), $user),
                $user, $open === 'personal' ? $values : [],
            ),
            Route::url('account.personal'), ['form' => 'personal'],
            $open === 'personal' ? $errors : [], $open === 'personal',
        )];
        if ($hasPassword) {
            $modals[] = AccountModal::make(
                'account-email', (string) __t('account.email.title'),
                [
                    'current_email' => FormField::key('current_email')->email()->label((string) __t('account.email.current'))->value((string) ($user->email ?? ''))->disabled(),
                    'email' => FormField::key('email')->email()->label((string) __t('account.email.new'))->autocomplete('email')->required()->value((string) ($open === 'email' ? ($values['email'] ?? '') : '')),
                    'current_password' => FormField::key('current_password')->password()->label((string) __t('account.email.password'))->autocomplete('current-password')->required(),
                ],
                Route::url('account.personal'), ['form' => 'email'],
                $open === 'email' ? $errors : [], $open === 'email',
            );
        }
        $modals[] = AccountModal::make(
            'account-password', (string) __t('account.password.modal_title'),
            AccountPassword::fields($hasPassword),
            Route::url('account.personal'), ['form' => 'password'],
            $open === 'password' ? $errors : [], $open === 'password',
        );
        $modals = $this->panel->personalModals($modals, $user, $open, $errors, $values);

        $this->page(AccountPage::view('personal'), 'personal', [
            'title' => (string) __t('account.personal.label'),
            'seo_url' => Route::url('account.personal'),
            'rows' => $this->panel->personalRows(AccountPersonal::rows($user, $hasPassword), $user),
            'modals' => $modals,
            'errors' => $this->panel->pageErrors([], $user, 'personal'),
        ]);
    }

    protected function hasPassword(int $userId): bool
    {
        return trim((string) (\sqlSelect('user', ['id' => $userId], 1)->row['password'] ?? '')) !== '';
    }

    /** I valori postati da rimettere nei form: senza token, password e campo `form`. */
    protected function personalValues(): array
    {
        return array_diff_key($this->post(), array_flip([Csrf::FIELD, 'form', 'current_password', 'password']));
    }

    protected function requireSection(string $section): void
    {
        if (!$this->panel->enabled($section)) {
            $this->notFound();
        }
    }

    /**
     * Link della email di conferma. La GET non cambia niente (i filtri antispam aprono i link appena arrivano):
     * mostra il bottone, o subito l'esito se il link non vale più. Il cambio avviene con il POST del bottone.
     * Solo il messaggio, senza pannello, senza login e senza toccare la sessione: funziona anche da un altro browser,
     * quindi non passa dalla route privata.
     */
    protected function confirmEmail(): void
    {
        AccountPage::seo((string) __t('account.email.title'), Route::url('account.email.confirm'));

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->requireCsrf();
            $this->emailOutcome(AccountEmail::confirm($this->text($_POST['token'] ?? '')));
            return;
        }

        $token = $this->text($_GET['token'] ?? '');
        $email = AccountEmail::pendingEmail($token);
        if ($email === null) {
            $this->emailOutcome('invalid');
            return;
        }
        $this->renderEmailConfirm($token, $email);
    }

    protected function renderEmailConfirm(string $token, string $email, ?int $alert = null): void
    {
        View::make($this->auth->viewPath('email-confirm'), [
            'auth_profile' => $this->auth,
            'token' => $token,
            'email' => $email,
            'action' => Route::url('account.email.confirm'),
            'alert' => $alert,
        ])->render();
    }

    /** @param string $outcome `confirmed`, `invalid` o `taken`. */
    protected function emailOutcome(string $outcome): void
    {
        View::make($this->auth->viewPath('message'), [
            'auth_profile' => $this->auth,
            'message_key' => 'account.email.'.$outcome,
        ])->render();
    }

    /** @return list<string> Errori da mostrare nel modal; con il salvataggio riuscito esce con un redirect. */
    protected function savePersonal(int $userId): array
    {
        $result = AccountPersonal::save($userId, $this->post(), $this->panel, $this->auth->phoneRequired());
        if ($result->success) {
            $this->flash((string) __t('account.saved'));
            $this->redirect(Route::url('account.personal'));
        }
        return $this->translate($result->errors, $result->messages);
    }

    /** @return list<string> Il form di un'estensione: errori già tradotti; salvato, esce con un redirect; di nessuno, 404. */
    protected function saveExtensionForm(string $form, object $user): array
    {
        $errors = $this->panel->savePersonalForm($form, $user, $this->post()) ?? $this->notFound();
        if ($errors === []) {
            $this->flash((string) __t('account.saved'));
            $this->redirect(Route::url('account.personal'));
        }
        return $errors;
    }

    /** @return list<string> */
    protected function requestEmail(object $user): array
    {
        $post = $this->post();
        $newEmail = strtolower(trim((string) ($post['email'] ?? '')));
        $result = AccountEmail::request($user, $newEmail, (string) ($post['current_password'] ?? ''), Route::url('account.email.confirm'), $this->mailer());
        if ($result->success) {
            // L'Alert fa l'escape del testo: l'indirizzo, che l'utente ha scritto, non si protegge due volte.
            $this->flash((string) __t('account.email.sent', ['email' => $newEmail]));
            $this->redirect(Route::url('account.personal'));
        }
        // `messageKeys` non conosce `mail`: l'errore di invio ha il suo messaggio.
        $mail = ($result->errors['mail'] ?? '') === 'send' ? [(string) __t('account.email.errors.send')] : [];
        return $this->translate(array_diff_key($result->errors, ['mail' => true]), $mail);
    }

    /** @return list<string> */
    protected function changePassword(int $userId): array
    {
        $result = AccountPassword::change($userId, array_intersect_key($this->post(), array_flip(['current_password', 'password'])));
        if ($result->success) {
            $this->flash((string) __t('account.password.saved'));
            $this->redirect(Route::url('account.personal'));
        }
        return isset($result->errors['user']) ? [(string) __t('account.errors.save')] : $this->translate($result->errors);
    }

    /**
     * @param array<string, string> $errors
     * @param list<string> $messages Già tradotti.
     * @return list<string>
     */
    protected function translate(array $errors, array $messages = []): array
    {
        return array_values(array_unique([
            ...array_map(static fn (string $key): string => (string) __t($key), AuthValidationAlert::messageKeys($errors)),
            ...$messages,
        ]));
    }

    /** @return array<string, \Wonder\App\ResourceSchema\Input> */
    protected function personalFields(object $user, array $values): array
    {
        $phone = (string) ($values['phone'] ?? $user->phone ?? '');
        $prefix = (string) ($values['phone_prefix'] ?? AuthValidator::defaultPrefix());
        // Il telefono dell'utente è canonico (`+39333…`): nel campo va solo il numero, dopo il prefisso.
        $phone = AuthValidator::localPhone($phone, $prefix);
        $required = $this->auth->phoneRequired();

        return [
            'name' => FormField::key('name')->text()->label((string) __t('auth.fields.name'))->required()->value((string) ($values['name'] ?? $user->name ?? '')),
            'surname' => FormField::key('surname')->text()->label((string) __t('auth.fields.surname'))->required()->value((string) ($values['surname'] ?? $user->surname ?? '')),
            'phone_prefix' => FormField::key('phone_prefix')->phonePrefix()->label((string) __t('auth.fields.prefix'))->value($prefix),
            'phone' => ($required ? FormField::key('phone')->phone()->required() : FormField::key('phone')->phone())->label((string) __t('auth.fields.mobile'))->value($phone),
        ];
    }

    protected function user(): object { return \infoUser((int) ($_SESSION['user_id'] ?? 0), 'id'); }

    protected function page(string $view, string $active, array $data = []): void
    {
        AccountPage::render($view, ['active' => $active] + $data);
    }

    protected function flash(string $message): void { $_SESSION['wonder_account_notice'] = $message; }

    /** Chi spedisce la posta del cambio email; `null` è `sendMail`. I test lo sostituiscono. */
    protected function mailer(): ?callable { return null; }

    /** Un valore della richiesta come testo: un array (`name[]=x`) non è un valore e vale vuoto. */
    protected function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** `$_POST` con i campi mandati come array svuotati: nessun campo diventa «Array» né fa scattare avvisi. */
    protected function post(): array
    {
        return array_map(static fn (mixed $value): mixed => is_array($value) ? '' : $value, $_POST);
    }

    protected function requireCsrf(): void
    {
        if (!Csrf::verify()) {
            $this->invalidCsrf();
        }
    }

    protected function redirect(string $url): never { header('Location: '.$url); exit; }
    protected function notFound(): never { http_response_code(404); exit; }

    /** Token non valido (pagina scaduta): ridisegna la pagina con l'avviso ed esce, così il salvataggio non parte. */
    protected function invalidCsrf(): never
    {
        $this->renderCsrfFailure();
        exit;
    }

    /**
     * Punto di estensione: i moduli che aggiungono azioni lo sovrascrivono per le loro e chiamano `parent` per le altre.
     * Mai «CSRF» né «token» nel testo: l'avviso è il 929. Senza una pagina da ridisegnare torna al pannello.
     */
    protected function renderCsrfFailure(): void
    {
        switch ($this->action) {
            case 'personal':
                // L'errore va nel modal riaperto; senza modal resta il toast.
                $open = $this->text($_POST['form'] ?? '');
                if ($open === '') {
                    $GLOBALS['ALERT'] = Csrf::ALERT;
                }
                $this->renderPersonal($open, [Csrf::failureMessage()], $open !== '' ? $this->personalValues() : []);
                return;
            case 'email.confirm':
                $token = $this->text($_POST['token'] ?? '');
                $email = AccountEmail::pendingEmail($token);
                $GLOBALS['ALERT'] = Csrf::ALERT;
                $email === null ? $this->emailOutcome('invalid') : $this->renderEmailConfirm($token, $email, Csrf::ALERT);
                return;
            default:
                Csrf::flashFailure();
                $this->redirect(Route::url('account.index') ?: $this->auth->home());
        }
    }
}
