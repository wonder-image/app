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
 *   `APP_DEPLOY_TOKEN` (auto-generato) e `NPM_GITHUB_TOKEN` (token classic
 *   con solo `read:packages`, per `npm ci` del pacchetto privato
 *   `@wonder-image/lib`: solo se un workflow lo usa; lo chiede l'utente).
 * - **GitHub Variables** della repo: `APP_DOMAIN` di produzione,
 *   `ASSETS_VERSION`, `FORCE_WWW`, `APP_TRUST_PROXY` e `APP_PROXY`
 *   (`nessuno`, `cloudflare`, `altro`).
 * - **Cloudflare** (solo con `APP_PROXY=cloudflare`): `CLOUDFLARE_API_TOKEN`,
 *   `CLOUDFLARE_ZONE_ID` e `CLOUDFLARE_ACCOUNT_ID` nel progetto Bitwarden,
 *   poi `forge cloudflare:security`.
 * - **Bitwarden Secrets Manager**: `APP_KEY` e `APP_ENCRYPTION_KEY` di
 *   produzione (auto-gen solo se mancano, mai sovrascritte, diverse da
 *   quelle locali), `DB_*`/`FTP_*`/`USER_*` di produzione.
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
        $bwTokenAttempts = 0;
        while (true) {
            if ($bwAccessToken === '') {
                $output->writeln('<comment>ℹ️  Si crea in Bitwarden → Secrets Manager → Machine accounts → il machine account → Access tokens → «Create access token».</comment>');
                $output->writeln('<comment>   Link: https://vault.bitwarden.com/#/sm (guida: https://bitwarden.com/help/access-tokens/)</comment>');
                $bwAccessToken = $this->askRequiredValue($input, $output, 'BWS_ACCESS_TOKEN', 'Inserisci BWS_ACCESS_TOKEN Bitwarden:', true);
                if ($bwAccessToken === '') {
                    return Command::FAILURE;
                }
                $this->setEnvValue($lines, $keyToIndex, 'BWS_ACCESS_TOKEN', $bwAccessToken);
                file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL);
                $output->writeln('<info>✅ Salvato BWS_ACCESS_TOKEN nel .env locale</info>');
            }

            // Un token revocato o scaduto farebbe fallire in silenzio i
            // passi successivi (e finirebbe nei GitHub Secrets): si
            // verifica subito e, se Bitwarden lo rifiuta, se ne chiede uno nuovo.
            $bwCheck = $this->runCommand(['bws', 'project', 'list', '--output', 'json'], ['BWS_ACCESS_TOKEN' => $bwAccessToken]);
            if ($bwCheck['exitCode'] === 0 || !self::bitwardenTokenRejected($bwCheck['stderr'].' '.$bwCheck['stdout'])) {
                break;
            }

            $bwTokenAttempts++;
            $output->writeln('<error>❌ Bitwarden rifiuta il BWS_ACCESS_TOKEN (revocato, scaduto o errato).</error>');
            if (!$input->isInteractive() || $bwTokenAttempts >= 3) {
                $output->writeln('<error>   Crea un nuovo access token e rilancia `php forge provision` in modalità interattiva.</error>');
                return Command::FAILURE;
            }
            $bwAccessToken = '';
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

        // 6b. NPM_GITHUB_TOKEN: solo se un workflow lo usa. Un errore qui non
        //     ferma il provisioning (come Cloudflare): si avvisa e si prosegue.
        try {
            $this->provisionNpmToken($input, $output, $repoName, $bwProjectId, $bwAccessToken, $existingGhSecrets);
        } catch (\Throwable $e) {
            $output->writeln('<comment>⚠️  NPM_GITHUB_TOKEN non gestito: '.$e->getMessage().'. Rilancia provision per riprovare.</comment>');
        }

        // 6c. CLOUDFLARE_API_TOKEN: chiesto e salvato qui, insieme agli altri
        //     token e prima del wizard (Cloudflare lo mostra una volta sola).
        //     `cloudflare:security` resta in fondo. Un errore non ferma il
        //     provisioning.
        $cloudflareReady = false;
        if ($proxy === 'cloudflare') {
            try {
                $cloudflareReady = $this->provisionCloudflareToken($input, $output, $appDomain, $bwProjectId, $bwAccessToken);
            } catch (\Throwable $e) {
                $output->writeln('<comment>⚠️  CLOUDFLARE_API_TOKEN non gestito: '.$e->getMessage().'. Rilancia provision per riprovare.</comment>');
            }
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
        //    Bitwarden esistente. APP_KEY e APP_ENCRYPTION_KEY sono in
        //    `bitwardenAutoGenKeys`: se mancano vengono generate random, se
        //    esistono vengono mantenute. Niente di tutto questo finisce mai
        //    nel .env locale per i remote-only. Se la lettura fallisce ci si
        //    ferma: trattarla come "nessun secret" rigenererebbe le chiavi.
        $existingBitwarden = $this->bitwardenProjectSecretsWithValues($bwProjectId, $bwAccessToken, $output);

        if ($existingBitwarden === null) {
            $output->writeln('<error>❌ Secret Bitwarden non leggibili: provisioning interrotto senza modificare nulla.</error>');
            return Command::FAILURE;
        }

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

        // 10. Cloudflare: il token è già in Bitwarden (passo 6c), qui si
        //     lancia `cloudflare:security`. Un errore qui non invalida il
        //     provisioning appena completato.
        if ($cloudflareReady) {
            $this->runCloudflareSecurity($input, $output, $appDomain);
        }

        return Command::SUCCESS;
    }

    /**
     * Chiede `CLOUDFLARE_API_TOKEN` e lo salva nel progetto Bitwarden con gli
     * ID di zona e account (mai nel .env di produzione).
     *
     * @return bool true se token e ID sono disponibili per `cloudflare:security`
     */
    private function provisionCloudflareToken(InputInterface $input, OutputInterface $output, string $appDomain, string $bwProjectId, string $bwAccessToken): bool
    {
        // Un workflow vecchio copia nel .env di produzione ogni secret di Bitwarden
        $unsafe = self::workflowsLeakingCloudflare(getcwd() ?: '.');
        if ($unsafe !== []) {
            foreach ($unsafe as $file) {
                $output->writeln('<error>❌ '.$file.' scrive i secret di Bitwarden nel .env senza escludere CLOUDFLARE_*: il token finirebbe in produzione.</error>');
            }
            $output->writeln('<comment>ℹ️  Aggiungi CLOUDFLARE_* a FTP_*|BWS_*) e lo step «☁️ Cloudflare» (vedi docs/app/piattaforma/cloudflare.md), poi rilancia provision. Token Cloudflare non salvato.</comment>');
            return false;
        }

        $existing = $this->bitwardenProjectSecretsWithValues($bwProjectId, $bwAccessToken, $output) ?? [];
        $current = (string) ($existing['CLOUDFLARE_API_TOKEN'] ?? '');

        if ($current === '' && $input->isInteractive()) {
            foreach (self::cloudflareTokenInstructions($appDomain) as $line) {
                $output->writeln('<comment>'.$line.'</comment>');
            }
        }

        $token = $this->askWithDefault(
            $input,
            $output,
            'CLOUDFLARE_API_TOKEN',
            'CLOUDFLARE_API_TOKEN ('.($current !== '' ? 'Enter = mantieni' : 'Enter = salta').'):',
            $current,
            true
        );

        if ($token === '') {
            $output->writeln('<comment>ℹ️  Nessun CLOUDFLARE_API_TOKEN: `cloudflare:security` saltato. Rilancia provision per aggiungerlo (permessi in docs/app/piattaforma/cloudflare.md).</comment>');
            return false;
        }

        try {
            $zone = (new \Wonder\Cloudflare\Client($token))->findZone($appDomain);
        } catch (\Throwable $e) {
            $output->writeln('<error>❌ Cloudflare: '.$e->getMessage().'</error>');
            return false;
        }

        if ($zone === null) {
            $output->writeln('<error>❌ Nessuna zona Cloudflare per '.$appDomain.' visibile con questo token: non salvato.</error>');
            return false;
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
                return false;
            }
            $output->writeln('<info>✅ '.$key.' salvato in Bitwarden.</info>');
        }

        foreach ($values as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
        }

        return true;
    }

    private function runCloudflareSecurity(InputInterface $input, OutputInterface $output, string $appDomain): void
    {
        $command = $this->getApplication()?->find('cloudflare:security');
        if ($command === null) {
            return;
        }

        $cloudflareInput = new \Symfony\Component\Console\Input\ArrayInput(['--domain' => $appDomain]);
        $cloudflareInput->setInteractive($input->isInteractive());
        $command->run($cloudflareInput, $output);
    }

    /**
     * `NPM_GITHUB_TOKEN`: token GitHub classic (solo `read:packages`) con cui
     * `npm ci` scarica `@wonder-image/lib` da GitHub Packages. GitHub non
     * permette di crearlo via API: lo crea l'utente, qui si chiede e si salva.
     *
     * @param string[] $existingGhSecrets nomi dei GitHub Secrets già presenti
     */
    private function provisionNpmToken(InputInterface $input, OutputInterface $output, string $repoName, string $bwProjectId, string $bwAccessToken, array $existingGhSecrets): void
    {
        $cwd = getcwd() ?: '.';

        if (self::workflowsUsingNpmToken($cwd) === []) {
            return;
        }

        // Una lettura fallita non vale "assente": si rischierebbe un doppione.
        $existing = $this->bitwardenProjectSecretsWithValues($bwProjectId, $bwAccessToken, $output);
        if ($existing === null) {
            $output->writeln('<comment>⚠️  Project Bitwarden non leggibile: NPM_GITHUB_TOKEN saltato. Rilancia provision per riprovare.</comment>');
            return;
        }

        // Workflow che copierebbero il token nel .env di produzione.
        $unsafe = self::workflowsLeakingKey($cwd, 'NPM_GITHUB_TOKEN');
        $warnUnsafe = static function () use ($unsafe, $output): void {
            foreach ($unsafe as $file) {
                $output->writeln('<comment>⚠️  '.$file.' scrive i secret di Bitwarden nel .env senza escludere NPM_GITHUB_TOKEN: il token finirebbe in produzione.</comment>');
                $output->writeln('<comment>   Aggiungi NPM_GITHUB_TOKEN alle chiavi escluse (elenco FTP_*|BWS_*) e rilancia provision.</comment>');
            }
        };

        // 1. Già in Bitwarden: si sincronizza nei GitHub Secrets.
        $fromBitwarden = (string) ($existing['NPM_GITHUB_TOKEN'] ?? '');
        if ($fromBitwarden !== '') {
            if ($this->syncGithubRepositorySecrets($repoName, ['NPM_GITHUB_TOKEN' => $fromBitwarden], $output)) {
                $output->writeln('<info>↺ NPM_GITHUB_TOKEN preso da Bitwarden e sincronizzato in GitHub Secrets ('.$repoName.').</info>');
            }
            $warnUnsafe();
            return;
        }

        // 2. Solo nei GitHub Secrets: il valore non è leggibile. Si richiede
        //    soltanto se lo si può salvare in Bitwarden senza rischi.
        $inGithub = in_array('NPM_GITHUB_TOKEN', $existingGhSecrets, true);
        if ($inGithub && ($unsafe !== [] || !$input->isInteractive())) {
            $output->writeln('<info>↺ NPM_GITHUB_TOKEN già presente in GitHub Secrets ('.$repoName.'), mantenuto invariato (non è in Bitwarden).</info>');
            $warnUnsafe();
            return;
        }

        // 3. Manca: serve crearlo a mano.
        if (!$input->isInteractive()) {
            $output->writeln('<comment>ℹ️  NPM_GITHUB_TOKEN manca: senza, il deploy si ferma a `npm ci`. Rilancia `php forge provision` in modalità interattiva.</comment>');
            return;
        }

        if ($inGithub) {
            $output->writeln('<comment>ℹ️  NPM_GITHUB_TOKEN è nei GitHub Secrets ma non in Bitwarden (un GitHub Secret non si può leggere).</comment>');
            $output->writeln('<comment>   Per salvarlo anche in Bitwarden incollalo di nuovo, oppure creane uno nuovo (classic, solo read:packages):</comment>');
        } else {
            $output->writeln('<comment>ℹ️  Il deploy scarica @wonder-image/lib da GitHub Packages e serve NPM_GITHUB_TOKEN.</comment>');
            $output->writeln('<comment>   Crea un token classic con il solo scope read:packages, con l\'account proprietario del pacchetto:</comment>');
        }
        $output->writeln('<comment>   '.self::npmTokenCreationUrl($repoName).'</comment>');
        $output->writeln('<comment>   Expiration: scegli «No expiration» (se il link non l\'ha già selezionata).</comment>');

        $token = trim($this->askWithDefault($input, $output, 'NPM_GITHUB_TOKEN', 'NPM_GITHUB_TOKEN (Enter = salta):', '', true));
        if ($token === '') {
            if (!$inGithub) {
                $output->writeln('<comment>⚠️  Nessun NPM_GITHUB_TOKEN: il deploy si fermerà a `npm ci` finché il secret manca. Rilancia provision per aggiungerlo.</comment>');
            }
            return;
        }

        if (!$this->syncGithubRepositorySecrets($repoName, ['NPM_GITHUB_TOKEN' => $token], $output)) {
            return;
        }

        // Bitwarden solo se nessun workflow lo copierebbe nel .env di produzione.
        if ($unsafe !== []) {
            $warnUnsafe();
            $output->writeln('<comment>ℹ️  NPM_GITHUB_TOKEN salvato solo in GitHub Secrets, non in Bitwarden.</comment>');
            return;
        }

        $ids = $this->bitwardenProjectSecrets($bwProjectId, $bwAccessToken, $output);
        if ($ids === null) {
            $output->writeln('<error>❌ NPM_GITHUB_TOKEN non salvato in Bitwarden (è comunque in GitHub Secrets).</error>');
            return;
        }
        $command = isset($ids['NPM_GITHUB_TOKEN'])
            ? ['bws', 'secret', 'edit', '--value', $token, $ids['NPM_GITHUB_TOKEN'], '--output', 'json']
            : ['bws', 'secret', 'create', 'NPM_GITHUB_TOKEN', $token, $bwProjectId, '--output', 'json'];
        $result = $this->runCommand($command, ['BWS_ACCESS_TOKEN' => $bwAccessToken]);

        if ($result['exitCode'] !== 0) {
            $output->writeln('<error>❌ NPM_GITHUB_TOKEN non salvato in Bitwarden (è comunque in GitHub Secrets).</error>');
            return;
        }
        $output->writeln('<info>✅ NPM_GITHUB_TOKEN salvato in Bitwarden e in GitHub Secrets.</info>');
    }

    /**
     * Workflow GitHub che usano `secrets.NPM_GITHUB_TOKEN` (riga non commentata).
     *
     * @return string[] percorsi relativi
     */
    public static function workflowsUsingNpmToken(string $cwd): array
    {
        $files = glob(rtrim($cwd, '/').'/.github/workflows/*.{yml,yaml}', GLOB_BRACE) ?: [];
        $using = [];

        foreach ($files as $file) {
            if (preg_match('/^[ \t]*[^#\n]*secrets\.NPM_GITHUB_TOKEN\b/m', (string) file_get_contents($file))) {
                $using[] = '.github/workflows/'.basename($file);
            }
        }

        return $using;
    }

    /** Link di creazione del token classic, con scope e descrizione precompilati. */
    public static function npmTokenCreationUrl(string $repoName): string
    {
        return 'https://github.com/settings/tokens/new?scopes=read:packages&default_expires_at=none&description='.rawurlencode('npm '.$repoName);
    }

    /**
     * Permessi del token Cloudflare usati da `cloudflare:security`:
     * etichetta nel modulo => [chiave del link, livello].
     * Documentate da Cloudflare: zone, zone_settings, ssl_and_certificates,
     * dns. Le altre tre seguono lo stesso schema ma non sono documentate: se
     * Cloudflare non le riconosce le scarta, e vanno aggiunte nel modulo.
     */
    public const CLOUDFLARE_TOKEN_PERMISSIONS = [
        'Zone › Zone › Read' => ['zone', 'read'],
        'Zone › Zone Settings › Edit' => ['zone_settings', 'edit'],
        'Zone › Zone WAF › Edit' => ['zone_waf', 'edit'],
        'Zone › Bot Management › Edit' => ['bot_management', 'edit'],
        'Zone › Page Shield › Edit' => ['page_shield', 'edit'],
        'Zone › SSL and Certificates › Read' => ['ssl_and_certificates', 'read'],
        'Zone › DNS › Read' => ['dns', 'read'],
    ];

    /** Link di creazione del token Cloudflare, con nome e permessi precompilati. */
    public static function cloudflareTokenCreationUrl(string $appDomain): string
    {
        $groups = [];
        foreach (self::CLOUDFLARE_TOKEN_PERMISSIONS as [$key, $type]) {
            $groups[] = ['key' => $key, 'type' => $type];
        }

        return 'https://dash.cloudflare.com/profile/api-tokens'
            .'?permissionGroupKeys='.rawurlencode((string) json_encode($groups))
            .'&accountId='.rawurlencode('*')
            .'&zoneId=all'
            .'&name='.rawurlencode(self::cloudflareZoneName($appDomain));
    }

    /**
     * Riconosce dall'output di `bws` un access token rifiutato da Bitwarden
     * (revocato, scaduto o malformato), distinto da un errore di rete.
     */
    public static function bitwardenTokenRejected(string $bwsOutput): bool
    {
        return preg_match('/invalid_client|invalid_grant|access token is not in a valid format|doesn\'t contain a decryption key|401 Unauthorized/i', $bwsOutput) === 1;
    }

    /** Dominio senza `www.`: è il nome della zona da scegliere nel modulo. */
    public static function cloudflareZoneName(string $appDomain): string
    {
        return (string) preg_replace('/^www\./', '', strtolower(trim($appDomain)));
    }

    /**
     * Righe stampate prima di chiedere il token: link e cosa controllare nel
     * modulo (il link non può scegliere la zona né la scadenza).
     *
     * @return string[]
     */
    public static function cloudflareTokenInstructions(string $appDomain): array
    {
        $lines = [
            'ℹ️  Cloudflare è davanti al sito: serve CLOUDFLARE_API_TOKEN. Crealo da questo link (nome e permessi precompilati):',
            '   '.self::cloudflareTokenCreationUrl($appDomain),
            '   Nel modulo controlla che ci siano questi permessi (aggiungi quelli mancanti):',
        ];
        foreach (array_keys(self::CLOUDFLARE_TOKEN_PERMISSIONS) as $label) {
            $lines[] = '     - '.$label;
        }
        $lines[] = '   Zone Resources: Include › Specific zone › la zona di '.self::cloudflareZoneName($appDomain).' (il link propone tutte le zone).';
        $lines[] = '   TTL: lascia vuote le date, così il token non scade.';

        return $lines;
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

    /**
     * Workflow GitHub che scrivono nel `.env` senza escludere `CLOUDFLARE_*`
     * in un pattern del `case` (come `FTP_*|BWS_*|CLOUDFLARE_*)`).
     *
     * @return string[] percorsi relativi
     */
    public static function workflowsLeakingCloudflare(string $cwd): array
    {
        return self::workflowsLeakingKey($cwd, 'CLOUDFLARE_*');
    }

    /**
     * Workflow GitHub che scrivono nel `.env` senza escludere `$pattern`
     * (testo letterale, es. `NPM_GITHUB_TOKEN`) in un pattern del `case`,
     * nel workflow stesso o in uno script `.github/scripts/*.sh` che richiama.
     *
     * @return string[] percorsi relativi
     */
    public static function workflowsLeakingKey(string $cwd, string $pattern): array
    {
        $files = glob(rtrim($cwd, '/').'/.github/workflows/*.{yml,yaml}', GLOB_BRACE) ?: [];
        $unsafe = [];

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            if (!preg_match('/>>\s*\.env\b/', $content)) {
                continue;
            }
            // Il filtro può stare in uno script richiamato dal workflow
            // (es. `.github/scripts/secrets-to-env.sh`): si legge anche quello.
            if (preg_match_all('#\.github/scripts/[\w.-]+\.sh#', $content, $scripts)) {
                foreach (array_unique($scripts[0]) as $script) {
                    $path = rtrim($cwd, '/').'/'.$script;
                    if (is_file($path)) {
                        $content .= "\n".(string) file_get_contents($path);
                    }
                }
            }
            if (!preg_match('/^[ \t]*[^#\n]*'.preg_quote($pattern, '/').'[^\n]*\)[ \t]*(;;)?[ \t]*$/m', $content)) {
                $unsafe[] = '.github/workflows/'.basename($file);
            }
        }

        return $unsafe;
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
