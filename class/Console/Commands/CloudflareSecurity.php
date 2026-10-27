<?php

namespace Wonder\Console\Commands;

use Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Wonder\Cloudflare\Client;
use Wonder\Cloudflare\CloudflareException;

/**
 * `forge cloudflare:security`
 *
 *   php forge cloudflare:security [--dry-run] [--domain esempio.it]
 *
 * Allinea la zona Cloudflare del sito alla configurazione Wonder Image:
 * HTTPS (Full strict solo con certificato valido all'origine), TLS minimo
 * 1.2, Page Shield, Security Level, Browser Integrity Check, la regola WAF
 * «wonder-image» che salta le sfide su `/api/` (tranne `/api/frontend/`) e
 * il rate limit su `/api/`. Tocca solo le proprie regole: le altre restano.
 *
 * Token: `CLOUDFLARE_API_TOKEN` dall'ambiente, poi dal progetto Bitwarden
 * del sito (`BWS_ACCESS_TOKEN` + `BWS_PROJECT_ID`), poi prompt nascosto.
 * Senza token il comando salta senza errori. Mai passarlo come argomento.
 *
 * Override opzionali in `custom/config/cloudflare.php` (ritorna un array).
 */
class CloudflareSecurity extends Config
{
    public const SKIP_RULE_REF = 'wonder_image_api_skip';
    public const RATE_LIMIT_RULE_REF = 'wonder_image_api_ratelimit';
    public const RULE_PREFIX = 'wonder-image';

    public const DEFAULT_CONFIG = [
        'bot_fight_mode' => false,
        'security_level' => 'medium',
        'page_shield' => true,
        'api_skip' => true,
        'rate_limit' => true,
        // richieste per IP ogni 10 secondi su /api/
        'rate_limit_requests' => 60,
    ];

    public $name = 'cloudflare:security';

    private bool $dryRun = false;
    private int $failures = 0;

    protected function configure(): void
    {
        $this->setName($this->name);
        $this->setDescription('Allinea HTTPS, WAF e rate limit della zona Cloudflare del sito');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Mostra cosa cambierebbe senza applicarlo');
        $this->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Dominio del sito (default APP_DOMAIN)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cwd = getcwd() ?: '.';
        Dotenv::createImmutable($cwd)->safeLoad();
        \Wonder\App\EnvCompat::apply();

        $this->dryRun = (bool) $input->getOption('dry-run');
        $this->failures = 0;

        $domain = self::baseDomain($this->normalizeDomain((string) ($input->getOption('domain') ?: self::env('APP_DOMAIN'))));
        if ($domain === '') {
            $output->writeln('<error>❌ Dominio non indicato: usa --domain o imposta APP_DOMAIN.</error>');
            return Command::FAILURE;
        }

        $bwsValues = null;
        $token = self::env('CLOUDFLARE_API_TOKEN');
        if ($token === '') {
            $bwsValues = $this->bwsValues($output);
            $token = trim((string) ($bwsValues['CLOUDFLARE_API_TOKEN'] ?? ''));
        }
        if ($token === '' && $input->isInteractive() && !$this->isCiEnvironment()) {
            $token = $this->askWithDefault($input, $output, 'CLOUDFLARE_API_TOKEN', 'CLOUDFLARE_API_TOKEN (Enter per saltare):', '', true);
        }
        if ($token === '') {
            $output->writeln('<comment>ℹ️  CLOUDFLARE_API_TOKEN non disponibile: configurazione Cloudflare saltata.</comment>');
            return Command::SUCCESS;
        }

        $config = self::resolveConfig($cwd);
        $client = new Client($token);

        $output->writeln('<info>☁️  Cloudflare per '.$domain.($this->dryRun ? ' (dry-run)' : '').'</info>');

        try {
            $zone = $this->resolveZone($client, $domain, self::env('CLOUDFLARE_ZONE_ID') ?: (string) ($bwsValues['CLOUDFLARE_ZONE_ID'] ?? ''));
        } catch (Throwable $e) {
            $output->writeln('<error>❌ Zona non leggibile: '.$e->getMessage().'</error>');
            $output->writeln('<error>   Il token deve avere almeno Zone › Zone › Read.</error>');
            return Command::FAILURE;
        }

        if ($zone === null) {
            $output->writeln('<error>❌ Nessuna zona Cloudflare per '.$domain.' visibile con questo token.</error>');
            return Command::FAILURE;
        }

        $output->writeln('   CLOUDFLARE_ZONE_ID='.$zone['id']);
        $output->writeln('   CLOUDFLARE_ACCOUNT_ID='.$zone['account_id']);

        $zoneId = $zone['id'];
        $isFree = in_array(strtolower($zone['plan']), ['free', 'free website'], true);

        $this->step($output, 'HTTPS', 'Zone Settings: Edit', fn () => $this->applySsl($client, $zoneId, $domain, $output));
        $this->step($output, 'Impostazioni', 'Zone Settings: Edit', fn () => $this->applySettings($client, $zoneId, $config, $output));
        $this->step($output, 'Bot Fight Mode', 'Bot Management: Edit', fn () => $this->applyBotFightMode($client, $zoneId, (bool) $config['bot_fight_mode'], $output));

        if ($config['page_shield']) {
            $this->step($output, 'Page Shield', 'Page Shield: Edit', fn () => $this->applyPageShield($client, $zoneId, $output));
        }
        if ($config['api_skip']) {
            $this->step($output, 'Regola WAF', 'Zone WAF: Edit', fn () => $this->applyRule(
                $client, $zoneId, 'http_request_firewall_custom', self::apiSkipRule(), false, $output
            ));
        }
        if ($config['rate_limit']) {
            $this->step($output, 'Rate limit', 'Zone WAF: Edit', fn () => $this->applyRule(
                $client, $zoneId, 'http_ratelimit', self::rateLimitRule((int) $config['rate_limit_requests']), $isFree, $output
            ));
        }

        $this->step($output, 'Controlli', 'DNS: Read', fn () => $this->printNotices($client, $zoneId, $domain, $output));

        if ($this->failures > 0) {
            $output->writeln('<error>❌ '.$this->failures.' passi Cloudflare non riusciti.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>✅ Cloudflare allineato.</info>');
        return Command::SUCCESS;
    }

    /** Valore da ambiente, compreso `.env` caricato da Dotenv. */
    private static function env(string $key): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return is_string($value) ? trim($value) : '';
    }

    /** `www.esempio.it` → `esempio.it`: la regola copre sia @ che www. */
    public static function baseDomain(string $domain): string
    {
        $domain = strtolower(trim($domain, ". \t\n\r"));

        return str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;
    }

    public static function resolveConfig(string $cwd): array
    {
        $config = self::DEFAULT_CONFIG;
        $path = $cwd.'/custom/config/cloudflare.php';

        if (is_file($path)) {
            $custom = require $path;
            if (is_array($custom)) {
                $config = array_merge($config, array_intersect_key($custom, self::DEFAULT_CONFIG));
            }
        }

        return $config;
    }

    public static function apiSkipRule(): array
    {
        return [
            'ref' => self::SKIP_RULE_REF,
            'description' => self::RULE_PREFIX.': nessuna sfida su /api/ (tranne /api/frontend/)',
            'expression' => '(starts_with(http.request.uri.path, "/api/") and not starts_with(http.request.uri.path, "/api/frontend/"))',
            'action' => 'skip',
            'action_parameters' => ['products' => ['securityLevel', 'bic']],
            'enabled' => true,
        ];
    }

    /** Piano free: periodo 10 s, block per 10 s, contatore per IP. */
    public static function rateLimitRule(int $requests): array
    {
        return [
            'ref' => self::RATE_LIMIT_RULE_REF,
            'description' => self::RULE_PREFIX.': rate limit /api/',
            'expression' => 'starts_with(http.request.uri.path, "/api/")',
            'action' => 'block',
            'ratelimit' => [
                'characteristics' => ['cf.colo.id', 'ip.src'],
                'period' => 10,
                'requests_per_period' => max(1, $requests),
                'mitigation_timeout' => 10,
            ],
            'enabled' => true,
        ];
    }

    public static function isOwnRule(array $rule, string $ref): bool
    {
        return ($rule['ref'] ?? '') === $ref;
    }

    /** Vero se ogni campo desiderato coincide con quello esistente. */
    public static function ruleMatches(array $existing, array $desired): bool
    {
        foreach ($desired as $key => $value) {
            if ($key === 'ref') {
                continue;
            }
            if (!self::sameValue($existing[$key] ?? null, $value)) {
                return false;
            }
        }

        return true;
    }

    private static function sameValue(mixed $a, mixed $b): bool
    {
        if (is_array($a) && is_array($b)) {
            if (array_is_list($a) && array_is_list($b)) {
                sort($a);
                sort($b);
                return $a === $b;
            }
            foreach ($b as $key => $value) {
                if (!self::sameValue($a[$key] ?? null, $value)) {
                    return false;
                }
            }
            return true;
        }

        return $a === $b;
    }

    /**
     * Cosa fare sulla fase: `create` (entrypoint assente), `add`, `update`,
     * `ok` oppure `blocked` (piano free con un'altra regola già presente).
     */
    public static function rulePlan(?array $ruleset, array $desired, bool $singleRuleLimit): string
    {
        if ($ruleset === null) {
            return 'create';
        }

        $rules = (array) ($ruleset['rules'] ?? []);
        foreach ($rules as $rule) {
            if (self::isOwnRule($rule, $desired['ref'])) {
                return self::ruleMatches($rule, $desired) ? 'ok' : 'update';
            }
        }

        return $singleRuleLimit && $rules !== [] ? 'blocked' : 'add';
    }

    private function step(OutputInterface $output, string $label, string $permission, callable $fn): void
    {
        try {
            $fn();
        } catch (CloudflareException $e) {
            $this->failures++;
            $output->writeln('<error>❌ '.$label.': '.$e->getMessage().'</error>');
            if ($e->isPermissionError()) {
                $output->writeln('<error>   Permesso del token da verificare: '.$permission.'.</error>');
            }
        } catch (Throwable $e) {
            $this->failures++;
            $output->writeln('<error>❌ '.$label.': '.$e->getMessage().'</error>');
        }
    }

    private function bwsValues(OutputInterface $output): ?array
    {
        $accessToken = self::env('BWS_ACCESS_TOKEN');
        $projectId = self::env('BWS_PROJECT_ID');

        if ($accessToken === '' || $projectId === '' || !$this->commandExists('bws')) {
            return null;
        }

        return $this->bitwardenProjectSecretsWithValues($projectId, $accessToken, $output);
    }

    /** @return array{id:string,name:string,account_id:string,plan:string}|null */
    private function resolveZone(Client $client, string $domain, string $zoneId): ?array
    {
        if ($zoneId !== '') {
            $zone = $client->get('/zones/'.rawurlencode($zoneId));
            $name = strtolower((string) ($zone['name'] ?? ''));

            if ($name !== '' && ($domain === $name || str_ends_with($domain, '.'.$name))) {
                return [
                    'id' => (string) $zone['id'],
                    'name' => $name,
                    'account_id' => (string) ($zone['account']['id'] ?? ''),
                    'plan' => (string) ($zone['plan']['legacy_id'] ?? $zone['plan']['name'] ?? ''),
                ];
            }
        }

        return $client->findZone($domain);
    }

    private function setting(Client $client, string $zoneId, string $name): mixed
    {
        $result = $client->get('/zones/'.$zoneId.'/settings/'.$name);

        return $result['value'] ?? null;
    }

    private function updateSetting(Client $client, string $zoneId, string $name, mixed $value, OutputInterface $output): void
    {
        $current = $this->setting($client, $zoneId, $name);

        if ($current === $value) {
            $output->writeln('   ✓ '.$name.' = '.json_encode($value));
            return;
        }

        if ($this->dryRun) {
            $output->writeln('   → '.$name.': '.json_encode($current).' → '.json_encode($value));
            return;
        }

        $client->patch('/zones/'.$zoneId.'/settings/'.$name, ['value' => $value]);
        $output->writeln('<info>   ✅ '.$name.': '.json_encode($current).' → '.json_encode($value).'</info>');
    }

    private function applySettings(Client $client, string $zoneId, array $config, OutputInterface $output): void
    {
        $settings = [
            'always_use_https' => 'on',
            'automatic_https_rewrites' => 'on',
            'min_tls_version' => '1.2',
            'tls_1_3' => 'on',
            'browser_check' => 'on',
            'security_level' => (string) $config['security_level'],
        ];

        foreach ($settings as $name => $value) {
            $this->updateSetting($client, $zoneId, $name, $value, $output);
        }
    }

    private function applySsl(Client $client, string $zoneId, string $domain, OutputInterface $output): void
    {
        $mode = (string) $this->setting($client, $zoneId, 'ssl');

        if ($mode === 'strict') {
            $output->writeln('   ✓ ssl = "strict"');
            return;
        }

        $origins = $this->originHosts($client, $zoneId, $domain);
        $checked = [];

        foreach ($origins as $host => $ip) {
            $error = self::originCertificateError($host, $ip);
            if ($error !== null) {
                $output->writeln('<comment>⚠️  Certificato dell\'origine non valido per '.$host.' ('.$error.'): SSL resta "'.$mode.'".</comment>');
                $this->warnFlexible($mode, $output);
                return;
            }
            $checked[] = $host;
        }

        if ($checked === []) {
            $output->writeln('<comment>⚠️  Nessun record A/AAAA/CNAME per '.$domain.': impossibile verificare l\'origine, SSL resta "'.$mode.'".</comment>');
            $this->warnFlexible($mode, $output);
            return;
        }

        if ($this->dryRun) {
            $output->writeln('   → ssl: "'.$mode.'" → "strict" (origine verificata: '.implode(', ', $checked).')');
            return;
        }

        $client->patch('/zones/'.$zoneId.'/settings/ssl', ['value' => 'strict']);
        $output->writeln('<info>   ✅ ssl: "'.$mode.'" → "strict" (origine verificata: '.implode(', ', $checked).')</info>');
    }

    private function warnFlexible(string $mode, OutputInterface $output): void
    {
        if ($mode === 'flexible' || $mode === 'off') {
            $output->writeln('<comment>⚠️  Con SSL "'.$mode.'" il traffico Cloudflare → origine non è cifrato: installa un certificato all\'origine (es. Let\'s Encrypt) e rilancia.</comment>');
        }
    }

    /**
     * Host @ e www con l'IP dell'origine letto dai record DNS (se proxati il
     * DNS pubblico risponderebbe con gli IP di Cloudflare).
     *
     * @return array<string,string> host => ip
     */
    private function originHosts(Client $client, string $zoneId, string $domain): array
    {
        $origins = [];

        foreach ([$domain, 'www.'.$domain] as $host) {
            foreach ($this->dnsRecords($client, $zoneId, $host) as $record) {
                $content = (string) ($record['content'] ?? '');
                $ip = match ($record['type'] ?? '') {
                    'A', 'AAAA' => $content,
                    'CNAME' => gethostbyname($content),
                    default => '',
                };

                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $origins[$host] = $ip;
                    break;
                }
            }
        }

        return $origins;
    }

    private function dnsRecords(Client $client, string $zoneId, string $host): array
    {
        $records = $client->get('/zones/'.$zoneId.'/dns_records', ['name' => $host]);

        return array_values(array_filter(
            (array) $records,
            fn ($record) => in_array($record['type'] ?? '', ['A', 'AAAA', 'CNAME'], true)
        ));
    }

    /** Null se l'origine presenta un certificato valido per l'host. */
    public static function originCertificateError(string $host, string $ip): ?string
    {
        $curl = curl_init('https://'.$host.'/');
        $target = str_contains($ip, ':') ? '['.$ip.']' : $ip;

        curl_setopt_array($curl, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$host.':443:'.$target],
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        try {
            $ok = curl_exec($curl) !== false;
            $error = $ok ? null : curl_error($curl);
        } finally {
            unset($curl);
        }

        return $error;
    }

    private function applyBotFightMode(Client $client, string $zoneId, bool $desired, OutputInterface $output): void
    {
        $current = (bool) ($client->get('/zones/'.$zoneId.'/bot_management')['fight_mode'] ?? false);
        $label = fn (bool $on) => $on ? 'attivo' : 'spento';

        if ($current === $desired) {
            $output->writeln('   ✓ Bot Fight Mode '.$label($current));
            return;
        }

        // In CI solo segnalato: è un interruttore che l'utente può aver voluto
        if ($this->isCiEnvironment() || $this->dryRun) {
            $output->writeln('<comment>⚠️  Bot Fight Mode '.$label($current).', configurato '.$label($desired).'. '
                .($current ? 'Sfida anche le chiamate del deploy su /api/ e non si può escludere con una regola.' : '').'</comment>');
            return;
        }

        $client->put('/zones/'.$zoneId.'/bot_management', ['fight_mode' => $desired]);
        $output->writeln('<info>   ✅ Bot Fight Mode: '.$label($current).' → '.$label($desired).'</info>');
    }

    private function applyPageShield(Client $client, string $zoneId, OutputInterface $output): void
    {
        $enabled = (bool) ($client->get('/zones/'.$zoneId.'/page_shield')['enabled'] ?? false);

        if ($enabled) {
            $output->writeln('   ✓ Page Shield attivo');
            return;
        }

        if ($this->dryRun) {
            $output->writeln('   → Page Shield: spento → attivo');
            return;
        }

        $client->put('/zones/'.$zoneId.'/page_shield', ['enabled' => true]);
        $output->writeln('<info>   ✅ Page Shield attivato</info>');
    }

    private function applyRule(Client $client, string $zoneId, string $phase, array $rule, bool $singleRuleLimit, OutputInterface $output): void
    {
        $entrypoint = '/zones/'.$zoneId.'/rulesets/phases/'.$phase.'/entrypoint';

        try {
            $ruleset = $client->get($entrypoint);
        } catch (CloudflareException $e) {
            if (!$e->isNotFound()) {
                throw $e;
            }
            $ruleset = null;
        }

        $plan = self::rulePlan($ruleset, $rule, $singleRuleLimit);
        $query = $this->dryRun ? ['dry_run' => 'true'] : [];
        $prefix = $this->dryRun ? '   → validata (dry-run): ' : '<info>   ✅ ';
        $suffix = $this->dryRun ? '' : '</info>';

        switch ($plan) {
            case 'ok':
                $output->writeln('   ✓ '.$rule['description']);
                return;

            case 'blocked':
                $output->writeln('<comment>⚠️  '.$phase.' ha già un\'altra regola e il piano free ne consente una: «'.$rule['description'].'» non aggiunta.</comment>');
                return;

            case 'create':
                $client->request('PUT', $entrypoint, ['rules' => [$rule]], $query);
                $output->writeln($prefix.'creata «'.$rule['description'].'»'.$suffix);
                return;

            case 'update':
                $existing = array_values(array_filter($ruleset['rules'], fn ($r) => self::isOwnRule($r, $rule['ref'])))[0];
                $client->request('PATCH', '/zones/'.$zoneId.'/rulesets/'.$ruleset['id'].'/rules/'.$existing['id'], $rule, $query);
                $output->writeln($prefix.'aggiornata «'.$rule['description'].'»'.$suffix);
                return;

            case 'add':
                // prima posizione: lo skip vale solo per le regole successive
                $client->request('POST', '/zones/'.$zoneId.'/rulesets/'.$ruleset['id'].'/rules', $rule + ['position' => ['index' => 1]], $query);
                $output->writeln($prefix.'aggiunta «'.$rule['description'].'»'.$suffix);
                return;
        }
    }

    private function printNotices(Client $client, string $zoneId, string $domain, OutputInterface $output): void
    {
        $output->writeln('<comment>ℹ️  HSTS resta all\'origine (header del sito): non attivarlo anche in Cloudflare.</comment>');

        $dnssec = (string) ($client->get('/zones/'.$zoneId.'/dnssec')['status'] ?? '');
        if ($dnssec !== 'active') {
            $output->writeln('<comment>⚠️  DNSSEC '.($dnssec !== '' ? $dnssec : 'non attivo').': attivalo in Cloudflare (DNS › Settings) e inserisci il record DS dal registrar.</comment>');
        }

        foreach ([$domain, 'www.'.$domain] as $host) {
            foreach ($this->dnsRecords($client, $zoneId, $host) as $record) {
                if (($record['proxied'] ?? false) !== true) {
                    $output->writeln('<comment>⚠️  Record '.$record['type'].' '.$host.' non proxato: il traffico non passa da Cloudflare.</comment>');
                }
            }
        }
    }
}
