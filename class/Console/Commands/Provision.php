<?php

namespace Wonder\Console\Commands;

use Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `forge provision`
 *
 * Wizard interattivo per il setup dei secret di **produzione**. Non tocca i
 * valori applicativi del `.env` locale (APP_DOMAIN, APP_URL, DB_*, USER_*
 * locali ecc.): quelli sono gestiti da `forge config`. Provision lavora
 * solo su:
 *
 * - **GitHub Secrets** della repo: `BWS_ACCESS_TOKEN`, `BWS_PROJECT_ID`,
 *   `APP_DEPLOY_TOKEN` (auto-generato).
 * - **GitHub Variables** della repo: `APP_DOMAIN` di produzione,
 *   `ASSETS_VERSION`, `FORCE_WWW`, `APP_TRUST_PROXY` e `APP_PROXY`
 *   (`nessuno`, `cloudflare`, `altro`).
 * - **Cloudflare** (solo con `APP_PROXY=cloudflare`): `CLOUDFLARE_API_TOKEN`,
 *   `CLOUDFLARE_ZONE_ID` e `CLOUDFLARE_ACCOUNT_ID` nel progetto Bitwarden,
 *   poi `forge cloudflare:security`.
 * - **Bitwarden Secrets Manager**: `APP_KEY` di produzione (auto-gen,
 *   diversa da quella locale), `DB_*`/`FTP_*`/`USER_*` di produzione.
 *
 * Il wizard mostra come default i valori esistenti (in Bitwarden o in
 * GitHub Variables): premendo Enter si conferma, digitando un nuovo valore
 * si sostituisce. Le password sono input nascosto.
 */
class Provision extends Config
{
    public $name = 'provision';

    protected function configure(): void
    {
        $this->setName($this->name);
        $this->setDescription('Wizard interattivo per i secret di produzione (Bitwarden + GitHub Secrets/Variables)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->isCiEnvironment()) {
            $output->writeln('<comment>ℹ️ Ambiente CI rilevato: php forge provision è pensato solo per il locale.</comment>');
            return Command::SUCCESS;
        }

        $cwd = getcwd() ?: '.';
        $envPath = $cwd.'/.env';
        $lines = file_exists($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];

        if ($lines === false) {
            $output->writeln('<error>❌ Impossibile leggere il file .env</error>');
            return Command::FAILURE;
        }

        Dotenv::createImmutable($cwd)->safeLoad();
        \Wonder\App\EnvCompat::apply();

        if (!$this->ensureCommandInstalled('bws', self::REQUIRED_COMMAND_VERSION_COMMANDS['bws'], $output, false)) {
            return Command::FAILURE;
        }

        if (!$this->ensureCommandInstalled('gh', self::REQUIRED_COMMAND_VERSION_COMMANDS['gh'], $output)) {
            return Command::FAILURE;
        }

        $keyToIndex = $this->envKeyToIndex($lines);

        // 0. Risolvi la repository GitHub UNA volta. La repo si chiama come
        //    la cartella locale (es. `wonder-image/new-site`), NON come il
        //    dominio di produzione (es. `test.wonderimage.it`). Tenerli
        //    separati è essenziale: il dominio cambia per ambiente, la repo
        //    è univoca per progetto.
        $repoName = $this->resolveGithubRepositoryFromGit($cwd, $output);
        if ($repoName === '') {
            $output->writeln('<error>❌ Impossibile determinare la repository GitHub.</error>');
            $output->writeln('<error>   Configura il git remote: git remote add origin git@github.com:OWNER/REPO.git</error>');
            $output->writeln('<error>   Oppure crea la repo via gh: gh repo create OWNER/'.basename($cwd).' --private</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>📦 Repository GitHub: '.$repoName.'</info>');

        // 1. BWS_ACCESS_TOKEN: serve per parlare con Bitwarden. Resta nel
        //    .env locale (e sarà sincronizzato anche nei GitHub Secrets
        //    più avanti).
        $bwAccessToken = $this->envValue($lines, $keyToIndex, 'BWS_ACCESS_TOKEN');
        if ($bwAccessToken === '') {
            $bwAccessToken = $this->askRequiredValue($input, $output, 'BWS_ACCESS_TOKEN', 'Inserisci BWS_ACCESS_TOKEN Bitwarden:', true);
            if ($bwAccessToken === '') {
                return Command::FAILURE;
            }
            $this->setEnvValue($lines, $keyToIndex, 'BWS_ACCESS_TOKEN', $bwAccessToken);
            file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL);
            $output->writeln('<info>✅ Salvato BWS_ACCESS_TOKEN nel .env locale</info>');
        }

        // dev-shared layer (locale): se l'utente ha un project Bitwarden
        // `dev-shared` con chiavi condivise tra progetti (RECAPTCHA test,
        // GTM/Pixel dev, SMTP locale, Klaviyo/Brevo dev, ...), riempi i
        // buchi nel .env locale. Eseguito qui (oltre che da forge config)
        // perché provision è il punto in cui BWS_ACCESS_TOKEN diventa
        // disponibile per la prima volta in setup fresh:
        //   1. composer install → forge config (no BWS, skip dev-shared)
        //   2. forge provision → salva BWS_ACCESS_TOKEN + dev-shared merge
        // Non tocca MAI il project di produzione. dev-shared è puramente
        // un layer locale.
        $this->applyDevSharedToLocalEnv($bwAccessToken, $envPath, $lines, $keyToIndex, $output);

        // 2. APP_DOMAIN di PRODUZIONE: prompt con default da GitHub Variables
        //    della repo (NON dal .env locale: localmente è il valore Herd-
        //    style, in prod è il dominio reale). Il valore va in GitHub
        //    Variables della repo; NON viene scritto nel .env locale.
        $existingGhVars = $this->existingGithubRepositoryVariables($repoName, $output) ?? [];
        $existingAppDomain = $this->normalizeDomain($existingGhVars['APP_DOMAIN'] ?? '');

        $appDomain = $existingAppDomain;
        if ($input->isInteractive()) {
            $promptDefault = $existingAppDomain;
            $appDomain = $this->askWithDefault(
                $input,
                $output,
                'APP_DOMAIN',
                $promptDefault === ''
                    ? 'Inserisci APP_DOMAIN di PRODUZIONE (es. www.example.it):'
                    : 'APP_DOMAIN di PRODUZIONE [Enter = '.$promptDefault.']:',
                $promptDefault
            );
            $appDomain = $this->normalizeDomain($appDomain);
        }
        if ($appDomain === '') {
            $output->writeln('<error>❌ APP_DOMAIN di produzione obbligatorio.</error>');
            return Command::FAILURE;
        }

        // 2b. FORCE_WWW: www sì per il dominio principale, no per un
        //     sottodominio (shop.esempio.it non ha www né il certificato).
        $existingForceWww = self::parseYesNo($existingGhVars['FORCE_WWW'] ?? '');
        $forceWww = $existingForceWww ?? self::defaultForceWww($appDomain);
        if ($input->isInteractive()) {
            $answer = $this->askWithDefault(
                $input,
                $output,
                'FORCE_WWW',
                'Forzare www (www.'.preg_replace('/^www\./', '', $appDomain).')? [s/n, Enter = '.($forceWww ? 's' : 'n').']:',
                $forceWww ? 's' : 'n'
            );
            $parsed = self::parseYesNo($answer);
            if ($parsed === null) {
                $output->writeln('<comment>⚠️  Risposta non valida, uso '.($forceWww ? 's' : 'n').'.</comment>');
            }
            $forceWww = $parsed ?? $forceWww;
        }

        // 2c. APP_PROXY e APP_TRUST_PROXY: solo con un proxy davanti si
        //     legge X-Forwarded-For; senza, l'IP sarebbe falsificabile.
        //     Default «cloudflare» se i nameserver del dominio sono di Cloudflare.
        $existingTrustProxy = self::parseYesNo($existingGhVars['APP_TRUST_PROXY'] ?? '');
        $trustProxy = $existingTrustProxy ?? false;
        $proxy = self::proxyFromAnswer($existingGhVars['APP_PROXY'] ?? '', '');
        if ($proxy === '') {
            $proxy = match (true) {
                self::usesCloudflareNameservers(self::nameservers($appDomain)) => 'cloudflare',
                $trustProxy => 'altro',
                default => 'nessuno',
            };
        }
        if ($input->isInteractive()) {
            $answer = $this->askWithDefault(
                $input,
                $output,
                'APP_PROXY',
                'Che proxy o CDN c\'è davanti al sito? (nessuno, cloudflare, altro) [Enter = '.$proxy.']:',
                $proxy
            );
            $proxy = self::proxyFromAnswer($answer, $proxy);
            if ($proxy === 'altro') {
                $output->writeln('<comment>ℹ️  Con APP_TRUST_PROXY l\'IP è l\'ultimo valore di X-Forwarded-For: il proxy deve impostarlo.</comment>');
            }
        }
        $trustProxy = self::trustProxyFromAnswer($proxy, $trustProxy);

        // 3. BWS_PROJECT_ID: cerca / crea il project Bitwarden per questo dominio.
        $bwProjectId = $this->envValue($lines, $keyToIndex, 'BWS_PROJECT_ID');
        if ($bwProjectId === '') {
            $bwProjectId = $this->ensureBitwardenProjectId($appDomain, $bwAccessToken, $output);
            if ($bwProjectId === '') {
                return Command::FAILURE;
            }
            $this->setEnvValue($lines, $keyToIndex, 'BWS_PROJECT_ID', $bwProjectId);
            file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL);
            $output->writeln('<info>✅ Salvato BWS_PROJECT_ID nel .env locale</info>');
        }

        // 4. Repo GitHub: crea/verifica. Avvisa se esiste un'altra repo
        //    creata erroneamente con il nome del dominio.
        if (!$this->ensureGithubRepository($repoName, $output)) {
            return Command::FAILURE;
        }

        $strayDomainRepo = $this->runCommand(['gh', 'repo', 'view', $appDomain, '--json', 'nameWithOwner', '--jq', '.nameWithOwner']);
        if ($strayDomainRepo['exitCode'] === 0) {
            $strayName = trim($strayDomainRepo['stdout']);
            if ($strayName !== '' && $strayName !== $repoName) {
                $output->writeln('<comment>⚠️  Esiste una repo GitHub `'.$strayName.'` (probabilmente creata da un provision precedente con il dominio come nome).</comment>');
                $output->writeln('<comment>   Tutti i secret/variables vengono ora salvati su `'.$repoName.'` correttamente.</comment>');
                $output->writeln('<comment>   Per eliminare la repo orfana: gh repo delete '.$strayName.' --yes</comment>');
            }
        }

        // 5. GitHub Secrets: BWS_*. Non APP_DEPLOY_TOKEN — quello viene
        //    gestito subito dopo, separatamente, per sapere se esiste già.
        if (!$this->syncGithubRepositorySecrets($repoName, [
            'BWS_ACCESS_TOKEN' => $bwAccessToken,
            'BWS_PROJECT_ID' => $bwProjectId,
        ], $output)) {
            return Command::FAILURE;
        }

        // 6. APP_DEPLOY_TOKEN: solo GitHub Secrets, mai Bitwarden. Auto-gen
        //    random la PRIMA volta. Se esiste già, non lo tocchiamo (perché
        //    sarebbe rotture per i deploy in corso e perché `gh secret list`
        //    non espone il valore quindi non possiamo "leggerlo per
        //    confermare l'identità" dopo).
        $existingGhSecrets = $this->existingGithubRepositorySecretNames($repoName, $output) ?? [];

        if (!in_array('APP_DEPLOY_TOKEN', $existingGhSecrets, true)) {
            $deployToken = bin2hex(random_bytes(32));
            if (!$this->syncGithubRepositorySecrets($repoName, [
                'APP_DEPLOY_TOKEN' => $deployToken,
            ], $output)) {
                return Command::FAILURE;
            }
            $output->writeln('<info>🔑 Generato APP_DEPLOY_TOKEN e salvato in GitHub Secrets ('.$repoName.').</info>');

            // Verifica post-sync: gh secret list deve ora ritornare il nome.
            // Se non lo ritorna, segnalo prima che il deploy fallisca.
            $verify = $this->existingGithubRepositorySecretNames($repoName, $output) ?? [];
            if (!in_array('APP_DEPLOY_TOKEN', $verify, true)) {
                $output->writeln('<error>❌ APP_DEPLOY_TOKEN risulta NON salvato dopo il push su '.$repoName.'.</error>');
                $output->writeln('<error>   Verifica manualmente con: gh secret list --repo '.$repoName.'</error>');
                $output->writeln('<error>   Se serve, settalo a mano:</error>');
                $output->writeln('<error>     gh secret set APP_DEPLOY_TOKEN --repo '.$repoName.' --body "$(php -r \'echo bin2hex(random_bytes(32));\')"</error>');
                return Command::FAILURE;
            }
            $output->writeln('<info>✅ APP_DEPLOY_TOKEN verificato in gh secret list ('.$repoName.').</info>');
        } else {
            $output->writeln('<info>↺ APP_DEPLOY_TOKEN già presente in GitHub Secrets ('.$repoName.'), mantenuto invariato.</info>');
        }

        // Avvisa se esiste ancora il vecchio nome (legacy).
        if (in_array('GITHUB_API_TOKEN', $existingGhSecrets, true)) {
            $output->writeln('<comment>⚠️  GITHUB_API_TOKEN ancora presente in GitHub Secrets (nome legacy). Puoi rimuoverlo manualmente con:</comment>');
            $output->writeln('<comment>   gh secret delete GITHUB_API_TOKEN --repo '.$repoName.'</comment>');
        }

        // 7. GitHub Variables: APP_DOMAIN, ASSETS_VERSION, FORCE_WWW, APP_TRUST_PROXY, APP_PROXY.
        $assetsVersion = $existingGhVars['ASSETS_VERSION'] ?? '';
        if ($assetsVersion === '') {
            $assetsVersion = $this->envValue($lines, $keyToIndex, 'ASSETS_VERSION');
        }
        if ($assetsVersion === '') {
            $assetsVersion = '0.0';
        }

        if (!$this->syncGithubRepositoryVariables($repoName, [
            'APP_DOMAIN' => $appDomain,
            'ASSETS_VERSION' => $assetsVersion,
            'FORCE_WWW' => $forceWww ? 'true' : 'false',
            'APP_TRUST_PROXY' => $trustProxy ? 'true' : 'false',
            'APP_PROXY' => $proxy,
        ], $output)) {
            return Command::FAILURE;
        }

        // 8. Wizard Bitwarden: per ogni secret della map, mostra default da
        //    Bitwarden esistente. APP_KEY è in `bitwardenAutoGenKeys`: se
        //    manca viene generata random, se esiste viene mantenuta. Niente
        //    di tutto questo finisce mai nel .env locale per i remote-only.
        $existingBitwarden = $this->bitwardenProjectSecretsWithValues($bwProjectId, $bwAccessToken, $output) ?? [];

        $remoteValues = [
            // canale interno per passare la mappa "valori esistenti" al
            // metodo wizard senza cambiare la signature pubblica
            '__bitwarden_existing' => $existingBitwarden,
        ];

        if (!$this->ensureBitwardenProjectEnvValues($input, $output, $envPath, $lines, $keyToIndex, $remoteValues)) {
            return Command::FAILURE;
        }

        // 9. Pusha tutti i valori della map su Bitwarden (creazione o edit
        //    a seconda che il secret esista già nel project).
        if (!$this->syncBitwardenProjectSecrets($bwProjectId, $bwAccessToken, $lines, $keyToIndex, $output, $remoteValues)) {
            return Command::FAILURE;
        }

        $output->writeln('<info>✅ Provisioning GitHub e Bitwarden completato.</info>');

        // 10. Cloudflare: token e ID nel progetto Bitwarden (mai nel .env di
        //     produzione), poi `cloudflare:security`. Un errore qui non
        //     invalida il provisioning appena completato.
        if ($proxy === 'cloudflare') {
            $this->provisionCloudflare($input, $output, $appDomain, $bwProjectId, $bwAccessToken);
        }

        return Command::SUCCESS;
    }

    private function provisionCloudflare(InputInterface $input, OutputInterface $output, string $appDomain, string $bwProjectId, string $bwAccessToken): void
    {
        $existing = $this->bitwardenProjectSecretsWithValues($bwProjectId, $bwAccessToken, $output) ?? [];
        $current = (string) ($existing['CLOUDFLARE_API_TOKEN'] ?? '');

        $token = $this->askWithDefault(
            $input,
            $output,
            'CLOUDFLARE_API_TOKEN',
            'CLOUDFLARE_API_TOKEN ('.($current !== '' ? 'Enter = mantieni' : 'Enter = salta').'):',
            $current,
            true
        );

        if ($token === '') {
            $output->writeln('<comment>ℹ️  Nessun CLOUDFLARE_API_TOKEN: crea un token con i permessi indicati in docs/app/piattaforma/cloudflare.md e rilancia provision.</comment>');
            return;
        }

        try {
            $zone = (new \Wonder\Cloudflare\Client($token))->findZone($appDomain);
        } catch (\Throwable $e) {
            $output->writeln('<error>❌ Cloudflare: '.$e->getMessage().'</error>');
            return;
        }

        if ($zone === null) {
            $output->writeln('<error>❌ Nessuna zona Cloudflare per '.$appDomain.' visibile con questo token: non salvato.</error>');
            return;
        }

        $values = [
            'CLOUDFLARE_API_TOKEN' => $token,
            'CLOUDFLARE_ZONE_ID' => $zone['id'],
            'CLOUDFLARE_ACCOUNT_ID' => $zone['account_id'],
        ];

        $ids = $this->bitwardenProjectSecrets($bwProjectId, $bwAccessToken, $output) ?? [];
        foreach ($values as $key => $value) {
            if ($value === '' || ($existing[$key] ?? null) === $value) {
                continue;
            }

            $command = isset($ids[$key])
                ? ['bws', 'secret', 'edit', '--value', $value, $ids[$key], '--output', 'json']
                : ['bws', 'secret', 'create', $key, $value, $bwProjectId, '--output', 'json'];
            $result = $this->runCommand($command, ['BWS_ACCESS_TOKEN' => $bwAccessToken]);

            if ($result['exitCode'] !== 0) {
                $output->writeln('<error>❌ '.$key.' non salvato in Bitwarden.</error>');
                return;
            }
            $output->writeln('<info>✅ '.$key.' salvato in Bitwarden.</info>');
        }

        foreach ($values as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
        }

        $command = $this->getApplication()?->find('cloudflare:security');
        if ($command === null) {
            return;
        }

        $cloudflareInput = new \Symfony\Component\Console\Input\ArrayInput(['--domain' => $appDomain]);
        $cloudflareInput->setInteractive($input->isInteractive());
        $command->run($cloudflareInput, $output);
    }

    /** Suffissi a due livelli più comuni: `esempio.co.uk` è un dominio principale. */
    private const TWO_LEVEL_SUFFIXES = ['co.uk', 'org.uk', 'ac.uk', 'gov.uk', 'com.au', 'net.au', 'org.au', 'co.nz', 'com.br', 'co.jp', 'co.za', 'com.mx', 'com.ar', 'com.tr'];

    /** Vero se il dominio (senza www) è un sottodominio, es. `shop.esempio.it`. */
    public static function isSubdomain(string $domain): bool
    {
        $domain = strtolower(trim($domain, ". \t\n\r\0\x0B"));
        $domain = (string) preg_replace('/^www\./', '', $domain);
        $labels = array_values(array_filter(explode('.', $domain), 'strlen'));
        $base = 2;
        if (count($labels) >= 3 && in_array(implode('.', array_slice($labels, -2)), self::TWO_LEVEL_SUFFIXES, true)) {
            $base = 3;
        }

        return count($labels) > $base;
    }

    /** Default di FORCE_WWW: true per il dominio principale, false per un sottodominio. */
    public static function defaultForceWww(string $domain): bool
    {
        return !self::isSubdomain($domain);
    }

    /** s/si/sì/y/yes/true/1 → true, n/no/false/0 → false, altro → null. */
    public static function parseYesNo(string $value): ?bool
    {
        $value = strtolower(trim($value));

        return match (true) {
            in_array($value, ['s', 'si', 'sì', 'y', 'yes', 'true', '1', 'on'], true) => true,
            in_array($value, ['n', 'no', 'false', '0', 'off'], true) => false,
            default => null,
        };
    }

    /** Risposta sul proxy: vuota = default, altrimenti `nessuno`, `cloudflare` o `altro`. */
    public static function proxyFromAnswer(string $answer, string $default): string
    {
        $answer = strtolower(trim($answer));

        return match (true) {
            $answer === '' => $default,
            in_array($answer, ['nessuno', 'none', 'no', 'n', 'false', '0'], true) => 'nessuno',
            in_array($answer, ['cloudflare', 'cf'], true) => 'cloudflare',
            default => 'altro',
        };
    }

    /** Vero se tutti i nameserver sono `*.ns.cloudflare.com`. */
    public static function usesCloudflareNameservers(array $nameservers): bool
    {
        if ($nameservers === []) {
            return false;
        }

        foreach ($nameservers as $ns) {
            if (!str_ends_with(strtolower(rtrim((string) $ns, '.')), '.ns.cloudflare.com')) {
                return false;
            }
        }

        return true;
    }

    /** Nameserver del dominio principale (www e sottodominio risalgono al padre). */
    private static function nameservers(string $domain): array
    {
        $labels = explode('.', (string) preg_replace('/^www\./', '', strtolower($domain)));

        while (count($labels) >= 2) {
            $records = @dns_get_record(implode('.', $labels), DNS_NS);
            if (is_array($records) && $records !== []) {
                return array_column($records, 'target');
            }
            array_shift($labels);
        }

        return [];
    }

    /** Risposta alla domanda sul proxy: vuota = default, «nessuno» = false, un proxy = true. */
    public static function trustProxyFromAnswer(string $answer, bool $default): bool
    {
        $answer = strtolower(trim($answer));
        if ($answer === '') {
            return $default;
        }
        if (in_array($answer, ['nessuno', 'none', 'no', 'n', 'false', '0'], true)) {
            return false;
        }

        return true;
    }
}
