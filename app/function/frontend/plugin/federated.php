<?php

    use Wonder\Auth\Federated\AppleIdTokenVerifierStub;
    use Wonder\Auth\Federated\Bridge\LegacySessionLoginAdapter;
    use Wonder\Auth\Federated\Bridge\LegacyUserAccountGateway;
    use Wonder\Auth\Federated\FederatedIdentityRepository;
    use Wonder\Auth\Federated\FederatedLoginService;
    use Wonder\Auth\Federated\FederatedProvider;
    use Wonder\Auth\Federated\GoogleIdTokenVerifierStub;

    /**
     * Render di un bottone login federato.
     *
     * - Se $url è valorizzata renderizza un link.
     * - Se $url è vuota renderizza un button submit con `name=federated_provider`.
     */
    function inputFederatedLoginButton(string $provider, string $url = '', string $attributes = '', ?string $label = null): string
    {

        $provider = FederatedProvider::normalize($provider);
        if (!FederatedProvider::isSupported($provider)) {
            return '';
        }

        $label = trim((string) $label);
        if ($label === '') {
            $label = ($provider === FederatedProvider::APPLE) ? 'Accedi con Apple' : 'Accedi con Google';
        }

        $providerEsc = htmlspecialchars($provider, ENT_QUOTES, 'UTF-8');
        $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $classes = 'btn wi-federated-btn wi-federated-btn-'.$providerEsc;
        $attributes = trim($attributes);

        if (trim($url) !== '') {
            $urlEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            return "<a href=\"$urlEsc\" class=\"$classes\" data-provider=\"$providerEsc\" $attributes>$labelEsc</a>";
        }

        return "<button type=\"submit\" class=\"$classes\" name=\"federated_provider\" value=\"$providerEsc\" data-provider=\"$providerEsc\" $attributes>$labelEsc</button>";

    }

    function inputGoogleLoginButton(string $url = '', string $attributes = '', ?string $label = null): string
    {
        return inputFederatedLoginButton(FederatedProvider::GOOGLE, $url, $attributes, $label);
    }

    function inputAppleLoginButton(string $url = '', string $attributes = '', ?string $label = null): string
    {
        return inputFederatedLoginButton(FederatedProvider::APPLE, $url, $attributes, $label);
    }

    /**
     * Render helper unico per entrambi i bottoni.
     *
     * @param array{
     *   google_url?: string,
     *   apple_url?: string,
     *   google_attributes?: string,
     *   apple_attributes?: string,
     *   container_attributes?: string,
     *   google_label?: string,
     *   apple_label?: string
     * } $options
     */
    function inputFederatedLoginButtons(array $options = []): string
    {

        $google = inputGoogleLoginButton(
            (string) ($options['google_url'] ?? ''),
            (string) ($options['google_attributes'] ?? ''),
            isset($options['google_label']) ? (string) $options['google_label'] : null
        );

        $apple = inputAppleLoginButton(
            (string) ($options['apple_url'] ?? ''),
            (string) ($options['apple_attributes'] ?? ''),
            isset($options['apple_label']) ? (string) $options['apple_label'] : null
        );

        $containerAttributes = trim((string) ($options['container_attributes'] ?? ''));
        $html = "<div class=\"d-flex flex-wrap gap-2 wi-federated-login\" $containerAttributes>$google$apple</div>";

        return $html;

    }

    /**
     * Verifica login federato in stile `verifyRecaptcha()`.
     *
     * POST supportati:
     * - federated_provider: google|apple
     * - federated_id_token (fallback: google_id_token / apple_id_token / id_token)
     *
     * @param array $POST
     * @param string $AREA
     * @param null|string|array $PERMIT_REQUIRED
     * @param array{
     *   default_authority?: string,
     *   remember_me?: bool,
     *   alert_map?: array<string,int>,
     *   table?: string,
     *   google_verifier?: callable,
     *   apple_verifier?: callable
     * } $options
     */
    function verifyFederatedLogin($POST, string $AREA = 'frontend', $PERMIT_REQUIRED = null, array $options = []): bool
    {

        global $ALERT;
        global $FEDERATED_AUTH_RESULT;

        $provider = FederatedProvider::normalize((string) ($POST['federated_provider'] ?? ''));

        $idToken = trim((string) ($POST['federated_id_token'] ?? ''));
        if ($idToken === '' && $provider === FederatedProvider::GOOGLE) {
            $idToken = trim((string) ($POST['google_id_token'] ?? ($POST['id_token'] ?? '')));
        }
        if ($idToken === '' && $provider === FederatedProvider::APPLE) {
            $idToken = trim((string) ($POST['apple_id_token'] ?? ($POST['id_token'] ?? '')));
        }

        if (!FederatedProvider::isSupported($provider) || $idToken === '') {
            $ALERT = (int) (($options['alert_map']['invalid_federated_request'] ?? null) ?: 900);
            return false;
        }

        $requiredAuthorities = [];
        if (is_array($PERMIT_REQUIRED)) {
            $requiredAuthorities = $PERMIT_REQUIRED;
        } elseif (is_string($PERMIT_REQUIRED) && trim($PERMIT_REQUIRED) !== '') {
            $requiredAuthorities = [ trim($PERMIT_REQUIRED) ];
        }

        try {
            $identity = null;

            if ($provider === FederatedProvider::GOOGLE && isset($options['google_verifier']) && is_callable($options['google_verifier'])) {
                $identity = $options['google_verifier']($idToken, $POST);
            } elseif ($provider === FederatedProvider::APPLE && isset($options['apple_verifier']) && is_callable($options['apple_verifier'])) {
                $identity = $options['apple_verifier']($idToken, $POST);
            } elseif ($provider === FederatedProvider::GOOGLE) {
                $identity = (new GoogleIdTokenVerifierStub())->verify($idToken);
            } else {
                $identity = (new AppleIdTokenVerifierStub())->verify($idToken);
            }

            $table = trim((string) ($options['table'] ?? 'auth_federated'));
            if ($table === '') { $table = 'auth_federated'; }

            $repo = new FederatedIdentityRepository($table);
            $users = new LegacyUserAccountGateway((string) ($options['default_authority'] ?? ''));
            $session = new LegacySessionLoginAdapter((bool) ($options['remember_me'] ?? true));
            $service = new FederatedLoginService($users, $repo, $session);

            $result = $service->authenticate($identity, $AREA, $requiredAuthorities);
            $FEDERATED_AUTH_RESULT = $result;

            if ($result->success) {
                return true;
            }

            $alertMap = is_array($options['alert_map'] ?? null) ? $options['alert_map'] : [];
            $ALERT = (int) ($alertMap[$result->reason] ?? 900);
            return false;

        } catch (Throwable $exception) {

            $reason = (string) $exception->getMessage();
            $alertMap = is_array($options['alert_map'] ?? null) ? $options['alert_map'] : [];
            $ALERT = (int) ($alertMap[$reason] ?? 900);
            return false;

        }

    }

