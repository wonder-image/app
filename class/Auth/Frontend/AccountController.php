<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\Models\Contacts\Contact;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Http\Csrf;
use Wonder\Http\Route;
use Wonder\View\View;

/** Pagine del pannello account del core. I moduli lo estendono per le loro sezioni. */
class AccountController
{
    public function __construct(protected readonly AccountPanel $panel, protected readonly AuthProfile $auth) {}

    public function handle(string $action, array $parameters = []): void
    {
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
        $contact = $this->contact((int) $user->id);
        $this->page(AccountPage::view('index'), 'overview', [
            'title' => (string) __t('account.overview.title'),
            'seo_url' => Route::url('account.index'),
            'contact' => $contact,
            'rows' => $this->panel->overviewRows([], $user),
            'errors' => $this->contactErrors($contact),
        ]);
    }

    /** Dati personali: tre righe con un modal ciascuna; ogni modal posta qui con il campo nascosto `form`. */
    protected function personal(): void
    {
        $user = $this->user();
        $userId = (int) $user->id;
        $contact = $this->contact($userId);
        $hasPassword = trim((string) (\sqlSelect('user', ['id' => $userId], 1)->row['password'] ?? '')) !== '';
        $open = '';
        $errors = [];
        $values = [];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->requireCsrf();
            $form = (string) ($_POST['form'] ?? '');
            // Senza password l'email non si cambia (la richiede AccountEmail): quel modal non esiste.
            $errors = match ($form) {
                'personal' => $this->savePersonal($userId),
                'email' => $hasPassword ? $this->requestEmail($user) : $this->notFound(),
                'password' => $this->changePassword($userId),
                default => $this->notFound(),
            };
            $open = $form;
            $values = array_diff_key($_POST, array_flip([Csrf::FIELD, 'form', 'current_password', 'password']));
        }

        $modals = [AccountModal::make(
            'account-personal', (string) __t('account.personal.modal_title'),
            $this->panel->personalFields($this->personalFields($user, $contact, $values), $user),
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

        $this->page(AccountPage::view('personal'), 'personal', [
            'title' => (string) __t('account.personal.label'),
            'seo_url' => Route::url('account.personal'),
            'rows' => $this->panel->personalRows(AccountPersonal::rows($user, $contact, $hasPassword), $user),
            'modals' => $modals,
            'errors' => $this->contactErrors($contact),
        ]);
    }

    /**
     * Esito del link nella email: il solo messaggio, senza pannello, senza login e senza toccare la sessione.
     * Funziona anche da un altro browser, quindi non passa dalla route privata.
     */
    protected function confirmEmail(): void
    {
        $outcome = AccountEmail::confirm((string) ($_GET['token'] ?? ''));
        AccountPage::seo((string) __t('account.email.title'), Route::url('account.email.confirm'));
        View::make($this->auth->viewPath('message'), [
            'auth_profile' => $this->auth,
            'message_key' => 'account.email.'.$outcome,
        ])->render();
    }

    /** @return list<string> Errori da mostrare nel modal; con il salvataggio riuscito esce con un redirect. */
    protected function savePersonal(int $userId): array
    {
        $result = AccountPersonal::save($userId, $_POST, $this->panel, $this->auth->phoneRequired());
        if ($result->success) {
            $this->flash((string) __t('account.saved'));
            $this->redirect(Route::url('account.personal'));
        }
        return $this->translate($result->errors, $result->messages);
    }

    /** @return list<string> */
    protected function requestEmail(object $user): array
    {
        $newEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
        $result = AccountEmail::request($user, $newEmail, (string) ($_POST['current_password'] ?? ''), Route::url('account.email.confirm'), $this->mailer());
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
        $result = AccountPassword::change($userId, array_intersect_key($_POST, array_flip(['current_password', 'password'])));
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
    protected function personalFields(object $user, array $contact, array $values): array
    {
        $phone = (string) ($values['phone'] ?? $contact['phone'] ?? $user->phone ?? '');
        $prefix = (string) ($values['phone_prefix'] ?? $contact['phone_prefix'] ?? '+39');
        // Il telefono dell'utente è canonico (`+39333…`): nel campo va solo il numero, dopo il prefisso.
        if (str_starts_with(trim($phone), '+')) {
            $digits = preg_replace('/\D+/', '', $phone);
            $prefixDigits = preg_replace('/\D+/', '', $prefix);
            if ($prefixDigits !== '' && str_starts_with($digits, $prefixDigits)) {
                $phone = substr($digits, strlen($prefixDigits));
            }
        }
        $required = $this->auth->phoneRequired();

        return [
            'name' => FormField::key('name')->text()->label((string) __t('auth.fields.name'))->required()->value((string) ($values['name'] ?? $user->name ?? '')),
            'surname' => FormField::key('surname')->text()->label((string) __t('auth.fields.surname'))->required()->value((string) ($values['surname'] ?? $user->surname ?? '')),
            'birth_date' => FormField::key('birth_date')->textDate()->label((string) __t('account.personal.birth_date'))->value((string) ($values['birth_date'] ?? $contact['birth_date'] ?? '')),
            'phone_prefix' => FormField::key('phone_prefix')->phonePrefix()->label((string) __t('auth.fields.prefix'))->value($prefix),
            'phone' => ($required ? FormField::key('phone')->phone()->required() : FormField::key('phone')->phone())->label((string) __t('auth.fields.mobile'))->value($phone),
        ];
    }

    /** La scheda cliente collegata a un altro utente non si può usare: lo si dice nella pagina. */
    protected function contactErrors(array $contact): array
    {
        return $contact === [] ? [(string) __t('account.errors.contact')] : [];
    }

    protected function user(): object { return \infoUser((int) ($_SESSION['user_id'] ?? 0), 'id'); }

    protected function contact(int $userId): array
    {
        $contact = Contact::find(['user_id' => $userId], 1);
        if (!is_array($contact) || empty($contact['id'])) {
            ContactAccount::link($userId);
            $contact = Contact::find(['user_id' => $userId], 1);
        }
        return is_array($contact) && !empty($contact['id']) ? $contact : [];
    }

    protected function page(string $view, string $active, array $data = []): void
    {
        AccountPage::render($view, ['active' => $active] + $data);
    }

    protected function flash(string $message): void { $_SESSION['wonder_account_notice'] = $message; }

    /** Chi spedisce la posta del cambio email; `null` è `sendMail`. I test lo sostituiscono. */
    protected function mailer(): ?callable { return null; }

    protected function requireCsrf(): void
    {
        if (!Csrf::verify()) {
            $this->invalidCsrf();
        }
    }

    protected function redirect(string $url): never { header('Location: '.$url); exit; }
    protected function notFound(): never { http_response_code(404); exit; }
    protected function invalidCsrf(): never { http_response_code(419); exit('CSRF token invalid'); }
}
