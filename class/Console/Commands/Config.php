<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Dotenv\Dotenv;


class Config extends Command
{
    public $name = 'config';

    protected function configure(): void
    {
        $this->setName($this->name);
        $this->setDescription('Configura il progetto per locale o CI e installa wonder-image lato NPM');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isCi = $this->isCiEnvironment();

        $cwd = getcwd();
        $envPath = $cwd . '/.env';
        $composerJsonPath = $cwd . '/composer.json';
        $packageJsonPath = $cwd . '/package.json';
        $lines = file_exists($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];

        if ($lines === false) {
            $output->writeln('<error>❌ Impossibile leggere il file .env</error>');
            return Command::FAILURE;
        }

        // Verifico se il file .env ha la APP_KEY, se non ne ha una la creo
        $ENV_FILE = Dotenv::createImmutable($cwd);
        $ENV_FILE->safeLoad();
        \Wonder\App\EnvCompat::apply();

        if (!$isCi) {
            if (!$this->ensureCommandInstalled('node', ['node', '-v'], $output)) {
                return Command::FAILURE;
            }

            if (!$this->ensureCommandInstalled('npm', ['npm', '-v'], $output)) {
                return Command::FAILURE;
            }

            // Node 20+ è obbligatorio: il package npm `wonder-image` ha
            // `"engines": { "node": ">=20" }`. Con Node 16/18 `npm install
            // wonder-image` emette `EBADENGINE` e l'install funziona solo
            // per fortuna (build target ES2022 può rompersi a runtime).
            // Fail-fast con messaggio chiaro invece di lasciar passare il
            // warning npm in fondo a un log.
            if (!$this->ensureNodeMinimumVersion(20, $output)) {
                return Command::FAILURE;
            }
        }

        // APP_KEY è la chiave di firma per JWT, encryption interna e
        // appToken per le chiamate API. Se manca, una buona parte del
        // framework (login backend, /api/app/update/, api_users token,
        // ecc.) si rompe a runtime con `Dotenv ValidationException:
        // APP_KEY is missing`.
        //
        // `isset()` da solo non basta: una riga `APP_KEY=` (chiave
        // dichiarata ma vuota) passa `isset` e fa fallire la required()
        // di Credentials::appKey() in produzione. Controlliamo anche che
        // il valore sia non-vuoto.
        $existingAppKey = trim((string) ($_ENV['APP_KEY'] ?? ''));
        if ($existingAppKey === '') {

            $appKeyAdded = false;
            $appKey = bin2hex(random_bytes(32));

            // Se la riga esiste ma è vuota, la sostituiamo invece di
            // appenderne una seconda.
            foreach ($lines as $i => $line) {
                if (preg_match("/^APP_KEY=/", $line)) {
                    $lines[$i] = "APP_KEY=$appKey";
                    $appKeyAdded = true;
                    break;
                }
            }

            // Altrimenti la aggiungiamo subito sotto APP_URL per
            // ordinare bene il file.
            if (!$appKeyAdded) {
                foreach ($lines as $i => $line) {
                    if (preg_match("/^APP_URL=/", $line)) {
                        array_splice($lines, $i + 1, 0, "APP_KEY=$appKey");
                        $appKeyAdded = true;
                        break;
                    }
                }
            }

            // Fallback finale: in coda al file.
            if (!$appKeyAdded) {
                $lines[] = "APP_KEY=$appKey";
            }

            file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);

            // Aggiorna anche $_ENV in memoria così le righe di codice
            // sotto che leggono $_ENV['APP_KEY'] vedono il valore appena
            // generato senza dover ricaricare Dotenv.
            $_ENV['APP_KEY'] = $appKey;
            putenv('APP_KEY='.$appKey);

            $output->writeln('<info>✅ APP_KEY generata e scritta nel .env</info>');

        }

        $keyToIndex = $this->envKeyToIndex($lines);
        $updatedKeys = [];

        $appDomain = $this->normalizeDomain($this->envValue($lines, $keyToIndex, 'APP_DOMAIN'));
        if ($appDomain === '') {
            $appDomain = $this->defaultAppDomain($cwd);

            if ($appDomain !== '') {
                $output->writeln('<info>✅ APP_DOMAIN rilevato dalla cartella progetto: '.$appDomain.'</info>');
            } else {
                $appDomain = $this->askRequiredDomain($input, $output);
            }

            if ($appDomain === '') {
                return Command::FAILURE;
            }
        }

        if ($this->envValue($lines, $keyToIndex, 'APP_DOMAIN') !== $appDomain) {
            $this->setEnvValue($lines, $keyToIndex, 'APP_DOMAIN', $appDomain);
            $updatedKeys[] = 'APP_DOMAIN';
        }

        $appUrl = $this->buildAppUrl($appDomain);
        if ($this->envValue($lines, $keyToIndex, 'APP_URL') !== $appUrl) {
            $this->setEnvValue($lines, $keyToIndex, 'APP_URL', $appUrl);
            $updatedKeys[] = 'APP_URL';
        }

        if (count($updatedKeys) > 0) {
            file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
            $output->writeln('<info>✅ Configurazione aggiornata nel file .env: '.implode(', ', $updatedKeys).'</info>');
        }

        file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);

        if (!$this->updateComposerName($composerJsonPath, $appDomain, $output)) {
            return Command::FAILURE;
        }

        $assetsVersion = $this->envValue($lines, $keyToIndex, 'ASSETS_VERSION');
        if ($assetsVersion === '') {
            $assetsVersion = '0.0';
            $this->setEnvValue($lines, $keyToIndex, 'ASSETS_VERSION', $assetsVersion);
            file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
            $output->writeln('<info>✅ Configurazione aggiornata nel file .env: ASSETS_VERSION</info>');
        }

        // dev-shared layer: chiavi dev condivise tra tutti i progetti
        // locali (RECAPTCHA test, GTM/Pixel dev, SMTP locale/Mailtrap,
        // Klaviyo/Brevo dev, Maps API dev, ecc.). Auto-discovery per nome
        // del project Bitwarden `dev-shared`. No-op se:
        //   - sei in CI (su prod il .env viene da Bitwarden del progetto,
        //     mai da dev-shared);
        //   - non c'è ancora un BWS_ACCESS_TOKEN nel .env (prima esecuzione
        //     post-clone: l'utente esegue dopo `forge provision` che lo crea).
        // Idempotente: rilanciato non sovrascrive i valori già impostati
        // localmente (per-project override vince).
        if (!$isCi) {
            $bwAccessToken = trim((string) ($_ENV['BWS_ACCESS_TOKEN'] ?? ''));
            if ($bwAccessToken !== '') {
                $this->applyDevSharedToLocalEnv($bwAccessToken, $envPath, $lines, $keyToIndex, $output);
            }
        }

        // Crea package.json se non esiste
        if (!file_exists($packageJsonPath)) {
            $package = [
                'private' => true,
                'dependencies' => new \stdClass()
            ];

            file_put_contents($packageJsonPath, json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $output->writeln('<info>✅ package.json creato.</info>');
        } else {
            $output->writeln('<comment>⚠️ package.json già presente.</comment>');
        }

        if (!$isCi) {
            // 1) Aggiunge/aggiorna il package `wonder-image` in
            //    package.json + node_modules. NOTA: `npm install <pkg>`
            //    NON esegue i lifecycle script del progetto consumer
            //    (il `postinstall` di package.json che copia
            //    `node_modules/wonder-image/dist/` in
            //    `assets/lib/wonder-image/dist/`).
            if (!$this->runPassthruCommand('npm install wonder-image', $output, 'Impossibile installare wonder-image lato NPM.')) {
                return Command::FAILURE;
            }

            // 2) Esegue `npm install` (senza argomenti) per innescare il
            //    `postinstall` del progetto. Questo è il passo che
            //    materializza `assets/lib/wonder-image/dist/` (lo stile
            //    del sito). Idempotente: se tutto è già installato, è
            //    rapido e si limita a far runnare il lifecycle.
            //
            //    Senza questo step la cartella `assets/lib/` resta
            //    vuota e il sito si serve senza CSS/JS di wonder-image
            //    (sintomo: "il sito non ha stile").
            if (!$this->runPassthruCommand('npm install', $output, 'Impossibile eseguire npm install (postinstall del progetto: assets/lib/ non popolata).')) {
                return Command::FAILURE;
            }
        } else {
            $output->writeln('<comment>ℹ️ Ambiente CI rilevato: salto npm install wonder-image (il workflow esegue npm ci che innesca da solo il postinstall).</comment>');
        }

        if (!$isCi) {
            $output->writeln('<comment>ℹ️ Esegui `php forge provision` per GitHub e Bitwarden.</comment>');
            $output->writeln('<comment>ℹ️ Esegui `php forge update --local` per generare handler e file locali.</comment>');
        } else {
            $output->writeln('<comment>ℹ️ Esegui `php forge update` per i task applicativi.</comment>');
        }

        $output->writeln("<info>✅ Configurazione completata.</info>");

        return Command::SUCCESS;

    }

    protected function isCiEnvironment(): bool
    {
        foreach (['CI', 'GITHUB_ACTIONS'] as $key) {
            $value = $_ENV[$key] ?? getenv($key);

            if (!is_string($value)) {
                continue;
            }

            $value = strtolower(trim($value));

            if (in_array($value, ['1', 'true', 'yes'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function ensureBitwardenProjectId(string $appDomain, string $accessToken, OutputInterface $output): string
    {
        if (!$this->commandExists('bws')) {
            $output->writeln('<error>❌ bws non installato. Installa la CLI ufficiale Bitwarden Secrets Manager per creare BWS_PROJECT_ID automaticamente.</error>');
            return '';
        }

        $projects = $this->runCommand(
            ['bws', 'project', 'list', '--output', 'json'],
            ['BWS_ACCESS_TOKEN' => $accessToken]
        );

        if ($projects['exitCode'] !== 0) {
            $error = $projects['stderr'] !== '' ? $projects['stderr'] : 'Impossibile leggere i progetti Bitwarden.';
            $output->writeln('<error>❌ '.$error.'</error>');
            return '';
        }

        $projectsJson = json_decode($projects['stdout'], true);

        if (!is_array($projectsJson)) {
            $output->writeln('<error>❌ Risposta Bitwarden non valida durante la lettura dei project.</error>');
            return '';
        }

        foreach ($projectsJson as $project) {
            $name = strtolower(trim((string) ($project['name'] ?? '')));

            if ($name !== strtolower($appDomain)) {
                continue;
            }

            $projectId = trim((string) ($project['id'] ?? ''));

            if ($projectId !== '') {
                $output->writeln('<info>✅ Project Bitwarden già presente: '.$appDomain.'</info>');
                return $projectId;
            }
        }

        $output->writeln('<info>📦 Creo il project Bitwarden: '.$appDomain.'</info>');

        $project = $this->runCommand(
            ['bws', 'project', 'create', $appDomain, '--output', 'json'],
            ['BWS_ACCESS_TOKEN' => $accessToken]
        );

        if ($project['exitCode'] !== 0) {
            $error = $project['stderr'] !== '' ? $project['stderr'] : 'Impossibile creare il project Bitwarden.';
            $output->writeln('<error>❌ '.$error.'</error>');
            return '';
        }

        $projectJson = json_decode($project['stdout'], true);
        $projectId = trim((string) ($projectJson['id'] ?? ''));

        if ($projectId === '') {
            $output->writeln('<error>❌ Risposta Bitwarden non valida durante la creazione del project.</error>');
            return '';
        }

        $output->writeln('<info>✅ Project Bitwarden creato: '.$appDomain.'</info>');

        return $projectId;
    }

    /**
     * Verifica se la repo GitHub `$repoName` (`owner/repo`) esiste; se no la crea.
     *
     * @deprecated Il parametro storico era `$appDomain` (es. `test.wonderimage.it`),
     *             che spesso NON coincide con la repo (es. `wonder-image/new-site`).
     *             Da v2.1.x il chiamante deve passare il nome owner/repo risolto
     *             via `resolveGithubRepositoryFromGit()`.
     */
    protected function ensureGithubRepository(string $appDomain, OutputInterface $output): bool
    {
        if (!$this->commandExists('gh')) {
            $output->writeln('<error>❌ gh non installato. Impossibile creare la repository GitHub automaticamente.</error>');
            return false;
        }

        $repo = $this->runCommand(['gh', 'repo', 'view', $appDomain, '--json', 'id']);
        if ($repo['exitCode'] === 0) {
            $output->writeln('<info>✅ Repository GitHub già presente: '.$appDomain.'</info>');
            return true;
        }

        $auth = $this->runCommand(['gh', 'auth', 'status']);
        if ($auth['exitCode'] !== 0) {
            $output->writeln('<error>❌ Autenticazione GitHub non valida. Esegui gh auth login prima di creare la repository.</error>');
            return false;
        }

        $output->writeln('<info>📦 Creo la repository GitHub: '.$appDomain.'</info>');

        $create = $this->runCommand(['gh', 'repo', 'create', $appDomain, '--private']);

        if ($create['exitCode'] !== 0) {
            $error = $create['stderr'] !== '' ? $create['stderr'] : 'Impossibile creare la repository GitHub.';
            $output->writeln('<error>❌ '.$error.'</error>');
            return false;
        }

        $output->writeln('<info>✅ Repository GitHub creata: '.$appDomain.'</info>');

        return true;
    }

    protected function syncGithubRepositorySecrets(string $repoOrAppDomain, array $secrets, OutputInterface $output): bool
    {
        // Il parametro accetta SIA il repo name in forma `owner/repo` (modo
        // nuovo) SIA un dominio (modo legacy, retro-compat). Se non è
        // `owner/repo`, fallback al lookup via gh repo view.
        $repoName = str_contains($repoOrAppDomain, '/')
            ? $repoOrAppDomain
            : $this->resolveGithubRepositoryName($repoOrAppDomain);

        if ($repoName === '') {
            $output->writeln('<error>❌ Impossibile determinare il nome completo della repository GitHub.</error>');
            return false;
        }

        foreach ($secrets as $key => $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $result = $this->runCommand([
                'gh', 'secret', 'set', $key,
                '--repo', $repoName,
                '--body', $value,
            ]);

            if ($result['exitCode'] !== 0) {
                $error = $result['stderr'] !== '' ? $result['stderr'] : 'Impossibile aggiornare i repository secrets GitHub.';
                $output->writeln('<error>❌ '.$error.'</error>');
                return false;
            }
        }

        $output->writeln('<info>✅ Repository secrets GitHub aggiornati: BWS_ACCESS_TOKEN, BWS_PROJECT_ID</info>');

        return true;
    }

    protected function syncGithubRepositoryVariables(string $repoOrAppDomain, array $variables, OutputInterface $output): bool
    {
        $repoName = str_contains($repoOrAppDomain, '/')
            ? $repoOrAppDomain
            : $this->resolveGithubRepositoryName($repoOrAppDomain);

        if ($repoName === '') {
            $output->writeln('<error>❌ Impossibile determinare il nome completo della repository GitHub.</error>');
            return false;
        }

        foreach ($variables as $key => $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $result = $this->runCommand([
                'gh', 'variable', 'set', $key,
                '--repo', $repoName,
                '--body', $value,
            ]);

            if ($result['exitCode'] !== 0) {
                $error = $result['stderr'] !== '' ? $result['stderr'] : 'Impossibile aggiornare le repository variables GitHub.';
                $output->writeln('<error>❌ '.$error.'</error>');
                return false;
            }
        }

        $output->writeln('<info>✅ Repository variables GitHub aggiornate: APP_DOMAIN, ASSETS_VERSION</info>');

        return true;
    }

    protected function syncBitwardenProjectSecrets(string $bwProjectId, string $bwAccessToken, array $lines, array $keyToIndex, OutputInterface $output, array $remoteValues = []): bool
    {
        $map = $this->bitwardenProjectSecretMap();

        $existingSecrets = $this->bitwardenProjectSecrets($bwProjectId, $bwAccessToken, $output);

        if ($existingSecrets === null) {
            return false;
        }

        $updated = [];

        foreach ($map as $envKey => $secretKeys) {
            // 1) chiavi "remote-only" (vivono solo in Bitwarden / GitHub
            //    Secrets, non nel .env locale): leggi da $remoteValues;
            // 2) chiavi normali: leggi dal .env.
            $value = $remoteValues[$envKey] ?? $this->envValue($lines, $keyToIndex, $envKey);

            if ($value === '') {
                continue;
            }

            foreach ($secretKeys as $secretKey) {
                $secretId = $existingSecrets[$secretKey] ?? '';

                if ($secretId !== '') {
                    $result = $this->runCommand(
                        ['bws', 'secret', 'edit', '--value', $value, $secretId, '--output', 'json'],
                        ['BWS_ACCESS_TOKEN' => $bwAccessToken]
                    );
                } else {
                    $result = $this->runCommand(
                        ['bws', 'secret', 'create', $secretKey, $value, $bwProjectId, '--output', 'json'],
                        ['BWS_ACCESS_TOKEN' => $bwAccessToken]
                    );
                }

                if ($result['exitCode'] !== 0) {
                    $error = $result['stderr'] !== '' ? $result['stderr'] : 'Impossibile aggiornare i secrets Bitwarden.';
                    $output->writeln('<error>❌ '.$error.'</error>');
                    return false;
                }

                $updated[] = $secretKey;
            }
        }

        if (count($updated) > 0) {
            $output->writeln('<info>✅ Secrets Bitwarden aggiornati: '.implode(', ', $updated).'</info>');
        }

        return true;
    }

    protected function ensureBitwardenProjectEnvValues(InputInterface $input, OutputInterface $output, string $envPath, array &$lines, array &$keyToIndex, array &$remoteValues = []): bool
    {
        $cleanedEnv = [];
        $updatedEnv = [];
        $localEnvDisabled = $this->localEnvDisabledKeys();
        $autoGenKeys = $this->bitwardenAutoGenKeys();
        $secretAliases = $this->bitwardenProjectSecretAliases();

        $existingBitwarden = is_array($remoteValues['__bitwarden_existing'] ?? null)
            ? $remoteValues['__bitwarden_existing']
            : [];
        unset($remoteValues['__bitwarden_existing']);

        // Pass 0: migrazione alias. Se Bitwarden ha solo il vecchio nome
        // (es. DB_HOSTNAME), riusa il valore come default per il nuovo
        // (DB_HOST). Non cancella il vecchio: lo lasciamo all'utente.
        foreach ($secretAliases as $oldName => $newName) {
            if (!isset($existingBitwarden[$newName]) && isset($existingBitwarden[$oldName]) && $existingBitwarden[$oldName] !== '') {
                $existingBitwarden[$newName] = $existingBitwarden[$oldName];
                $output->writeln('<info>↪ Migrazione Bitwarden: ' . $oldName . ' → ' . $newName . ' (valore esistente riusato).</info>');
                $output->writeln('<comment>   Per rimuovere il vecchio: bws secret list "$BWS_PROJECT_ID" --output json | jq -r \'.[] | select(.key=="' . $oldName . '") | .id\' | xargs -I {} bws secret delete {}</comment>');
            }
        }

        // Pass 1: cleanup .env locale. Solo le chiavi "no-locale" residue
        // legacy vengono rimosse (FTP_*, APP_DEPLOY_TOKEN, GITHUB_API_TOKEN).
        // DB_*/USER_*/APP_KEY locali NON vengono mai toccate dal provision.
        foreach ($localEnvDisabled as $envKey) {
            if ($this->envValue($lines, $keyToIndex, $envKey) !== '') {
                $this->removeEnvValue($lines, $keyToIndex, $envKey);
                $cleanedEnv[] = $envKey;
            }
        }

        if (count($cleanedEnv) > 0) {
            file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
            $output->writeln('<info>🧹 Rimosse dal .env locale (vivono solo in Bitwarden/GitHub Secrets): ' . implode(', ', $cleanedEnv) . '</info>');
        }

        // Pass 2: per ogni chiave nella map Bitwarden, popola $remoteValues.
        // Il provision NON LEGGE MAI dal .env locale (i valori locali sono
        // dev, mentre i valori che vanno in Bitwarden sono di produzione e
        // possono essere DIVERSI). Single source of truth per produzione:
        // Bitwarden + wizard interattivo.
        foreach (array_keys($this->bitwardenProjectSecretMap()) as $envKey) {
            $existingBwValue = $existingBitwarden[$envKey] ?? '';
            $isAutoGen = in_array($envKey, $autoGenKeys, true);

            // 1) AUTO-GEN (es. APP_KEY): mantieni esistente o genera random.
            //    Mai prompt all'utente.
            if ($isAutoGen) {
                if ($existingBwValue !== '') {
                    $remoteValues[$envKey] = $existingBwValue;
                    continue;
                }

                $value = $this->autoGenerateValueFor($envKey) ?? bin2hex(random_bytes(32));
                $remoteValues[$envKey] = $value;
                $updatedEnv[] = $envKey . ' (auto-generato)';
                continue;
            }

            // 2) WIZARD prompt: default = valore esistente in Bitwarden, se
            //    presente. Enter mantiene, digitando si sostituisce. Le
            //    password sono input nascosto.
            $hidden = in_array($envKey, ['DB_PASSWORD', 'FTP_PASSWORD', 'USER_PASSWORD'], true);

            if ($existingBwValue !== '') {
                $value = $this->askWithDefault(
                    $input,
                    $output,
                    $envKey,
                    $envKey . ' [Enter = mantieni esistente]:',
                    $existingBwValue,
                    $hidden
                );

                if ($value !== $existingBwValue) {
                    $updatedEnv[] = $envKey;
                }
            } else {
                $value = $this->askRequiredValue(
                    $input,
                    $output,
                    $envKey,
                    'Inserisci ' . $envKey . ' (PRODUZIONE):',
                    $hidden
                );

                if ($value === '') {
                    return false;
                }
                $updatedEnv[] = $envKey . ' (nuovo)';
            }

            $remoteValues[$envKey] = $value;
        }

        if (count($updatedEnv) > 0) {
            $safe = array_map(
                fn ($k) => str_starts_with($k, 'DB_PASSWORD') || str_starts_with($k, 'FTP_PASSWORD') || str_starts_with($k, 'USER_PASSWORD') || str_starts_with($k, 'APP_KEY')
                    ? preg_replace('/^(\w+)/', '$1 [hidden]', $k)
                    : $k,
                $updatedEnv
            );
            $output->writeln('<info>✅ Valori produzione preparati per Bitwarden: ' . implode(', ', $safe) . '</info>');
        }

        return true;
    }

    /**
     * Lista delle chiavi che NON devono mai vivere nel .env locale.
     *
     * Sono i secret che servono solo in produzione (FTP credentials per il
     * deploy, deploy bearer per /api/app/update/, ecc.) e che vengono
     * propagati su:
     *
     * - Bitwarden Secrets Manager (project-level), da cui il workflow CI
     *   li scarica al momento del deploy e li scrive nel .env di produzione;
     * - GitHub Secrets della repo, per i token che il workflow legge
     *   come ${{ secrets.X }} prima ancora di poter parlare con Bitwarden
     *   (es. APP_DEPLOY_TOKEN).
     *
     * `BWS_ACCESS_TOKEN` e `BWS_PROJECT_ID` non sono in questa lista perché
     * non sono nemmeno presenti in `bitwardenProjectSecretMap()`: vivono
     * esclusivamente nei GitHub Secrets della repo (se ci fossero in
     * Bitwarden sarebbero ricorsivi). `provision` li scrive solo lì.
     *
     * @return string[]
     */
    /**
     * Chiavi che `forge provision` deve **rimuovere** dal `.env` locale se
     * presenti per residui legacy. Sono valori che vivono solo in
     * Bitwarden / GitHub Secrets e non hanno alcun senso in dev locale.
     *
     * NON include `DB_*`, `USER_*`, `APP_KEY`: quelle vivono ANCHE nel
     * `.env` locale (con valori di dev, separati da quelli di produzione)
     * e il provision non le tocca lì.
     *
     * @return string[]
     */
    protected function localEnvDisabledKeys(): array
    {
        return [
            'FTP_HOST',
            'FTP_USER',
            'FTP_PASSWORD',
            'FTP_PORT',
            'FTP_REMOTE_PATH',
            'APP_DEPLOY_TOKEN',
            'GITHUB_API_TOKEN', // legacy: viene rimossa al cleanup.
        ];
    }

    /**
     * Indica se le righe del .env contengono già modifiche pending da
     * scrivere (es. `setEnvValue` chiamata su una chiave). Usato per
     * decidere se chiamare `file_put_contents` anche quando il "diff" è
     * solo cleanup (rimosse righe legacy remote-only).
     *
     * Implementazione conservativa: se il file su disco è leggibile e
     * differisce dalla rappresentazione in memoria, vale rewrite.
     */
    protected function envFileNeedsRewrite(array $lines): bool
    {
        // Sempre true: il chiamante ha già deciso che serve rewrite.
        // Rimane come hook per future ottimizzazioni.
        return true;
    }

    /**
     * Per certe chiavi (token di deploy / shared secret) preferiamo generare
     * un valore random invece di chiederlo all'utente. Se la chiave non rientra
     * nei casi auto-gestiti, ritorna null e il chiamante chiederà input.
     */
    protected function autoGenerateValueFor(string $envKey): ?string
    {
        switch ($envKey) {
            case 'APP_DEPLOY_TOKEN':
                // Shared secret per il bypass deploy in /api/app/update/.
                // Vedi app/http/api/app/update.php (Wonder\App\Credentials::deployToken pattern).
                return bin2hex(random_bytes(32));
        }

        return null;
    }

    protected function bitwardenProjectSecretMap(): array
    {
        return [
            'APP_KEY'         => ['APP_KEY'],
            'DB_HOST'         => ['DB_HOST'],
            'DB_USER'         => ['DB_USER'],
            'DB_PASSWORD'     => ['DB_PASSWORD'],
            'DB_NAME'         => ['DB_NAME'],
            'FTP_HOST'        => ['FTP_HOST'],
            'FTP_USER'        => ['FTP_USER'],
            'FTP_PASSWORD'    => ['FTP_PASSWORD'],
            'FTP_PORT'        => ['FTP_PORT'],
            'FTP_REMOTE_PATH' => ['FTP_REMOTE_PATH'],
            'USER_USERNAME'   => ['USER_USERNAME'],
            'USER_PASSWORD'   => ['USER_PASSWORD'],
            // APP_DEPLOY_TOKEN volutamente non in Bitwarden: vive solo nei
            // GitHub Secrets della repo (gestito direttamente da Provision).
        ];
    }

    /**
     * Rinomine storiche delle chiavi (vecchio nome → nuovo nome).
     *
     * Quando `forge provision` legge i secret esistenti in Bitwarden e
     * trova ancora un secret con il vecchio nome ma non quello nuovo,
     * il valore viene riusato come default per il nuovo. Non cancella
     * il vecchio (lasciamo che l'utente lo elimini esplicitamente con
     * `bws secret delete`); aggiunge solo il nuovo.
     *
     * @return array<string,string>
     */
    protected function bitwardenProjectSecretAliases(): array
    {
        return [
            'DB_HOSTNAME' => 'DB_HOST',
            'DB_USERNAME' => 'DB_USER',
            'DB_DATABASE' => 'DB_NAME',
        ];
    }

    /**
     * Chiavi Bitwarden generate automaticamente (random hex 64) se mancanti.
     *
     * NON vengono lette dal `.env` locale, NON vengono mai salvate in
     * `.env` locale, e quando esistono già in Bitwarden vengono mantenute
     * (mai sovrascritte). L'unica cosa che le tocca è la PRIMA generazione.
     *
     * `APP_KEY` di produzione è qui dentro: deve essere stabile e diversa da
     * quella locale (che vive nel `.env` locale gestito da `forge config`).
     *
     * @return string[]
     */
    protected function bitwardenAutoGenKeys(): array
    {
        return [
            'APP_KEY',
        ];
    }

    /**
     * Nome convenzionale del project Bitwarden Secrets Manager che contiene
     * le chiavi dev condivise tra tutti i progetti locali (RECAPTCHA test
     * key, SMTP locale/Mailtrap, GTM/Pixel dev, Klaviyo/Brevo dev,
     * Google Maps API dev, ecc.).
     *
     * Auto-discovery per nome: niente UUID hardcoded nel framework,
     * niente `.env` da modificare in ogni progetto. Funziona out-of-the-box
     * appena hai un `BWS_ACCESS_TOKEN` con visibilità sul project.
     */
    protected const DEV_SHARED_PROJECT_NAME = 'dev-shared';

    /**
     * Risolve l'UUID del project Bitwarden "dev-shared".
     *
     * Strategia:
     *   1. Override esplicito via $_ENV['BWS_DEV_SHARED_PROJECT_ID']
     *      (use case edge: dev-shared per cliente, multi-tenant, ecc.).
     *   2. Altrimenti `bws project list` → primo project con
     *      `name == 'dev-shared'` (case-insensitive).
     *   3. Altrimenti null (graceful: forge config funziona lo stesso,
     *      senza layer dev-shared).
     */
    protected function resolveDevSharedProjectId(string $bwAccessToken, OutputInterface $output): ?string
    {
        $explicit = trim((string) ($_ENV['BWS_DEV_SHARED_PROJECT_ID'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        if (!$this->commandExists('bws')) {
            return null;
        }

        $result = $this->runCommand(
            ['bws', 'project', 'list', '--output', 'json'],
            ['BWS_ACCESS_TOKEN' => $bwAccessToken]
        );

        if ($result['exitCode'] !== 0) {
            return null;
        }

        $payload = json_decode($result['stdout'], true);
        if (!is_array($payload)) {
            return null;
        }

        foreach ($payload as $project) {
            $name = strtolower(trim((string) ($project['name'] ?? '')));
            if ($name === self::DEV_SHARED_PROJECT_NAME) {
                $id = trim((string) ($project['id'] ?? ''));
                return $id !== '' ? $id : null;
            }
        }

        return null;
    }

    /**
     * Chiavi che NON devono mai essere copiate da dev-shared al .env
     * locale, anche se per errore l'utente le ha messe nel project
     * condiviso.
     *
     * Categorie:
     * - Bootstrap Bitwarden (BWS_*): ricorsivi, vanno nel .env locale
     *   tramite `forge provision`.
     * - Identità del progetto (APP_DOMAIN, APP_URL, APP_KEY): per
     *   definizione diverse tra progetti.
     * - Secret di solo produzione (FTP_*, APP_DEPLOY_TOKEN, ecc.): mai
     *   in locale.
     * - Credenziali DB / utente admin (DB_*, USER_*): in locale sono
     *   valori dev per-progetto, mai shared.
     *
     * @return string[]
     */
    protected function devSharedDisabledKeys(): array
    {
        return [
            'BWS_ACCESS_TOKEN',
            'BWS_PROJECT_ID',
            'BWS_DEV_SHARED_PROJECT_ID',
            'APP_KEY',
            'APP_DOMAIN',
            'APP_URL',
            'APP_DEPLOY_TOKEN',
            'GITHUB_API_TOKEN',
            'ASSETS_VERSION',
            'DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME',
            'DB_HOSTNAME', 'DB_USERNAME', 'DB_DATABASE',
            'FTP_HOST', 'FTP_USER', 'FTP_PASSWORD', 'FTP_PORT', 'FTP_REMOTE_PATH',
            'USER_USERNAME', 'USER_PASSWORD', 'USER_NAME', 'USER_SURNAME', 'USER_EMAIL',
        ];
    }

    /**
     * Merge "fill-missing" delle chiavi del project Bitwarden "dev-shared"
     * nel .env locale.
     *
     * Policy:
     * - Se la chiave è già presente e non vuota nel .env locale: NIENTE.
     *   Il per-project override vince sempre su dev-shared (così tieni
     *   una chiave diversa per un singolo progetto se serve).
     * - Se la chiave è in `devSharedDisabledKeys()`: skip (project-
     *   specific o ricorsiva).
     * - Altrimenti: scrivi nel .env locale.
     *
     * No-op silenzioso se:
     *   - $bwAccessToken vuoto (provision non ancora eseguito)
     *   - bws non installato
     *   - project "dev-shared" non trovato (l'utente non l'ha ancora
     *     creato — messaggio informativo, non errore)
     *
     * NON viene mai chiamato da `forge provision` quando sincronizza il
     * project di produzione: dev-shared è puramente layer locale.
     */
    protected function applyDevSharedToLocalEnv(
        string $bwAccessToken,
        string $envPath,
        array &$lines,
        array &$keyToIndex,
        OutputInterface $output
    ): void {
        if ($bwAccessToken === '') {
            return;
        }

        $projectId = $this->resolveDevSharedProjectId($bwAccessToken, $output);
        if ($projectId === null) {
            $output->writeln('<comment>ℹ️ Nessun project Bitwarden "dev-shared" trovato. Per condividere chiavi dev (RECAPTCHA, GTM, SMTP, ...) tra progetti, crea un project chiamato `dev-shared` su Bitwarden Secrets Manager.</comment>');
            return;
        }

        $secrets = $this->bitwardenProjectSecretsWithValues($projectId, $bwAccessToken, $output);
        if (!is_array($secrets) || $secrets === []) {
            return;
        }

        $disabled = array_flip($this->devSharedDisabledKeys());
        $added = [];
        $skipped = [];
        $dirty = false;

        foreach ($secrets as $key => $value) {
            if (isset($disabled[$key])) {
                $skipped[] = $key;
                continue;
            }

            // Per-project override: se .env locale ha già un valore
            // non-vuoto, lo manteniamo. Vince sempre il progetto.
            $existing = $this->envValue($lines, $keyToIndex, $key);
            if ($existing !== '') {
                continue;
            }

            $this->setEnvValue($lines, $keyToIndex, $key, (string) $value);
            $added[] = $key;
            $dirty = true;
        }

        if ($dirty) {
            file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL);
            // Re-sincronizziamo l'indice dopo gli insert.
            $keyToIndex = $this->envKeyToIndex($lines);
            $output->writeln('<info>🔄 dev-shared → .env locale (fill-missing): '.implode(', ', $added).'</info>');
        } else {
            $output->writeln('<info>↺ dev-shared sincronizzato: niente da aggiungere al .env locale.</info>');
        }

        if ($skipped !== []) {
            $output->writeln('<comment>⚠️ dev-shared contiene chiavi project-specific che ho ignorato: '.implode(', ', $skipped).'. Andrebbero rimosse da `dev-shared` (vivono nel project del singolo sito).</comment>');
        }
    }

    protected function bitwardenProjectSecrets(string $bwProjectId, string $bwAccessToken, OutputInterface $output): ?array
    {
        $result = $this->runCommand(
            ['bws', 'secret', 'list', $bwProjectId, '--output', 'json'],
            ['BWS_ACCESS_TOKEN' => $bwAccessToken]
        );

        if ($result['exitCode'] !== 0) {
            $error = $result['stderr'] !== '' ? $result['stderr'] : 'Impossibile leggere i secrets Bitwarden.';
            $output->writeln('<error>❌ '.$error.'</error>');
            return null;
        }

        $payload = json_decode($result['stdout'], true);

        if (!is_array($payload)) {
            $output->writeln('<error>❌ Risposta Bitwarden non valida durante la lettura dei secrets.</error>');
            return null;
        }

        $secrets = [];

        foreach ($payload as $secret) {
            $key = trim((string) ($secret['key'] ?? ''));
            $id = trim((string) ($secret['id'] ?? ''));

            if ($key === '' || $id === '') {
                continue;
            }

            $secrets[$key] = $id;
        }

        return $secrets;
    }

    protected function resolveGithubRepositoryName(string $appDomain): string
    {
        $repo = $this->runCommand(['gh', 'repo', 'view', $appDomain, '--json', 'nameWithOwner', '--jq', '.nameWithOwner']);

        if ($repo['exitCode'] !== 0) {
            return '';
        }

        return trim($repo['stdout']);
    }

    /**
     * Risolve la repo GitHub del progetto a partire dal git remote `origin`
     * della cartella corrente — `owner/repo`. Se non c'è remote configurato,
     * usa `gh api user` per estrarre l'owner di default + basename del cwd.
     *
     * Sostituisce `resolveGithubRepositoryName($appDomain)` nel flow di
     * provision, perché l'`APP_DOMAIN` può essere `test.wonderimage.it`
     * (sub-domain di produzione) mentre la repo si chiama `new-site`
     * (nome cartella). Erano sempre stati confusi e per fortuna in passato
     * coincidevano spesso; ora vengono separati.
     */
    protected function resolveGithubRepositoryFromGit(string $cwd, OutputInterface $output): string
    {
        // Strategia 1: git remote get-url origin → estrai owner/repo
        $remote = $this->runCommand(['git', '-C', $cwd, 'remote', 'get-url', 'origin']);
        if ($remote['exitCode'] === 0) {
            $url = trim($remote['stdout']);
            // SSH: git@github.com:owner/repo.git
            // HTTPS: https://github.com/owner/repo(.git)?
            if (preg_match('#github\.com[:/]([^/]+)/([^/.\s]+?)(?:\.git)?/?$#', $url, $m) === 1) {
                return $m[1].'/'.$m[2];
            }
        }

        // Strategia 2: gh api user → estrai login + basename(cwd)
        $whoami = $this->runCommand(['gh', 'api', 'user', '--jq', '.login']);
        if ($whoami['exitCode'] === 0) {
            $owner = trim($whoami['stdout']);
            $folder = $this->normalizeProjectSlug(basename($cwd));
            if ($owner !== '' && $folder !== '') {
                $output->writeln('<comment>ℹ️ Nessun git remote origin configurato. Uso owner di default ('.$owner.') + nome cartella ('.$folder.').</comment>');
                return $owner.'/'.$folder;
            }
        }

        return '';
    }

    protected function updateComposerName(string $composerJsonPath, string $appDomain, OutputInterface $output): bool
    {
        if (!file_exists($composerJsonPath)) {
            $output->writeln('<error>❌ composer.json non trovato.</error>');
            return false;
        }

        $composerJson = file_get_contents($composerJsonPath);

        if ($composerJson === false) {
            $output->writeln('<error>❌ Impossibile leggere composer.json</error>');
            return false;
        }

        $name = 'wonder-image/'.$this->composerProjectName($appDomain);

        if (preg_match('/"name"\s*:\s*"([^"]+)"/', $composerJson, $matches) !== 1) {
            $output->writeln('<error>❌ Chiave name non trovata in composer.json</error>');
            return false;
        }

        if ($matches[1] === $name) {
            return true;
        }

        $updatedComposerJson = preg_replace('/"name"\s*:\s*"([^"]+)"/', '"name": "'.$name.'"', $composerJson, 1);

        if ($updatedComposerJson === null) {
            $output->writeln('<error>❌ Impossibile aggiornare il name in composer.json</error>');
            return false;
        }

        file_put_contents($composerJsonPath, $updatedComposerJson);

        $output->writeln('<info>✅ Aggiorno composer.json: '.$name.'</info>');

        return true;
    }

    protected function envKeyToIndex(array $lines): array
    {
        $keyToIndex = [];

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $matches) === 1) {
                $keyToIndex[$matches[1]] = $i;
            }
        }

        return $keyToIndex;
    }

    protected function envValue(array $lines, array $keyToIndex, string $key): string
    {
        if (!isset($keyToIndex[$key])) {
            return '';
        }

        $line = $lines[$keyToIndex[$key]];
        $parts = explode('=', $line, 2);
        $raw = $parts[1] ?? '';

        return trim(trim($raw), "\"'");
    }

    protected function setEnvValue(array &$lines, array &$keyToIndex, string $key, string $value): void
    {
        $row = $key.'='.$value;

        if (isset($keyToIndex[$key])) {
            $lines[$keyToIndex[$key]] = $row;
            return;
        }

        $lines[] = $row;
        $keyToIndex[$key] = count($lines) - 1;
    }

    protected function removeEnvValue(array &$lines, array &$keyToIndex, string $key): void
    {
        if (!isset($keyToIndex[$key])) {
            return;
        }

        unset($lines[$keyToIndex[$key]]);
        $lines = array_values($lines);
        $keyToIndex = $this->envKeyToIndex($lines);
    }

    protected function askRequiredDomain(InputInterface $input, OutputInterface $output): string
    {
        while (true) {
            $domain = $this->askRequiredValue($input, $output, 'APP_DOMAIN', 'Inserisci APP_DOMAIN:');

            if ($domain === '') {
                return '';
            }

            $normalizedDomain = $this->normalizeDomain($domain);

            if ($normalizedDomain !== '') {
                return $normalizedDomain;
            }

            $projectSlug = $this->normalizeProjectSlug($domain);

            if ($projectSlug !== '') {
                return $projectSlug;
            }

            $output->writeln('<error>❌ APP_DOMAIN non valido.</error>');
        }
    }

    /**
     * Come `askRequiredValue` ma con `$default`: se l'utente preme Enter
     * (input vuoto) viene restituito il default invece di insistere.
     * Pensato per i wizard "modifica o conferma" del provision.
     */
    protected function askWithDefault(InputInterface $input, OutputInterface $output, string $key, string $question, string $default, bool $hidden = false): string
    {
        if (!$input->isInteractive()) {
            return $default;
        }

        $helper = $this->getHelper('question');

        try {
            $prompt = new Question($question.' ');

            if ($hidden && method_exists($prompt, 'setHidden')) {
                $prompt->setHidden(true);

                if (method_exists($prompt, 'setHiddenFallback')) {
                    $prompt->setHiddenFallback(false);
                }
            } elseif ($hidden) {
                $output->writeln('<comment>⚠️ Input nascosto non disponibile, continuo con input visibile.</comment>');
            }

            $value = $helper->ask($input, $output, $prompt);
        } catch (\RuntimeException $e) {
            if (!$hidden) {
                throw $e;
            }
            // Se l'input nascosto non è supportato, ritorniamo il default
            // (l'utente non ha avuto modo di digitare).
            return $default;
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : $default;
    }

    /**
     * Estende `bitwardenProjectSecrets()` ritornando anche i VALORI dei
     * secret, non solo gli ID. Cache locale al singolo run.
     *
     * @return array<string,string>|null `null` se la chiamata bws fallisce.
     */
    protected function bitwardenProjectSecretsWithValues(string $bwProjectId, string $bwAccessToken, OutputInterface $output): ?array
    {
        $result = $this->runCommand(
            ['bws', 'secret', 'list', $bwProjectId, '--output', 'json'],
            ['BWS_ACCESS_TOKEN' => $bwAccessToken]
        );

        if ($result['exitCode'] !== 0) {
            $error = $result['stderr'] !== '' ? $result['stderr'] : 'Impossibile leggere i valori dei secret Bitwarden.';
            $output->writeln('<error>❌ '.$error.'</error>');
            return null;
        }

        $payload = json_decode($result['stdout'], true);

        if (!is_array($payload)) {
            return [];
        }

        $values = [];
        foreach ($payload as $secret) {
            $key = trim((string) ($secret['key'] ?? ''));
            $value = (string) ($secret['value'] ?? '');
            if ($key !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * Lista dei nomi dei secret della repo GitHub. `gh` non espone i valori
     * (sono cifrati); ritorniamo solo i nomi per sapere quali esistono già.
     *
     * @return string[]|null
     */
    protected function existingGithubRepositorySecretNames(string $repoOrAppDomain, OutputInterface $output): ?array
    {
        $repoName = str_contains($repoOrAppDomain, '/')
            ? $repoOrAppDomain
            : $this->resolveGithubRepositoryName($repoOrAppDomain);
        if ($repoName === '') {
            return null;
        }

        $result = $this->runCommand(['gh', 'secret', 'list', '--repo', $repoName, '--json', 'name']);

        if ($result['exitCode'] !== 0) {
            // Se la repo non esiste ancora o non c'è auth, ritorniamo lista vuota
            // (il provision creerà i secret necessari da zero).
            return [];
        }

        $payload = json_decode($result['stdout'], true);
        if (!is_array($payload)) {
            return [];
        }

        $names = [];
        foreach ($payload as $entry) {
            $name = trim((string) ($entry['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Mappa name => value delle GitHub Variables della repo.
     *
     * @return array<string,string>|null
     */
    protected function existingGithubRepositoryVariables(string $repoOrAppDomain, OutputInterface $output): ?array
    {
        $repoName = str_contains($repoOrAppDomain, '/')
            ? $repoOrAppDomain
            : $this->resolveGithubRepositoryName($repoOrAppDomain);
        if ($repoName === '') {
            return null;
        }

        $result = $this->runCommand(['gh', 'variable', 'list', '--repo', $repoName, '--json', 'name,value']);

        if ($result['exitCode'] !== 0) {
            return [];
        }

        $payload = json_decode($result['stdout'], true);
        if (!is_array($payload)) {
            return [];
        }

        $values = [];
        foreach ($payload as $entry) {
            $name = trim((string) ($entry['name'] ?? ''));
            $value = (string) ($entry['value'] ?? '');
            if ($name !== '') {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    protected function askRequiredValue(InputInterface $input, OutputInterface $output, string $key, string $question, bool $hidden = false): string
    {
        if (!$input->isInteractive()) {
            $output->writeln('<error>❌ '.$key.' mancante. Esegui il comando in modalità interattiva.</error>');
            return '';
        }

        $helper = $this->getHelper('question');

        while (true) {
            try {
                $prompt = new Question($question.' ');

                if ($hidden && method_exists($prompt, 'setHidden')) {
                    $prompt->setHidden(true);

                    if (method_exists($prompt, 'setHiddenFallback')) {
                        $prompt->setHiddenFallback(false);
                    }
                } elseif ($hidden) {
                    $output->writeln('<comment>⚠️ Input nascosto non disponibile, continuo con input visibile.</comment>');
                }

                $value = trim((string) $helper->ask($input, $output, $prompt));
            } catch (\RuntimeException $e) {
                if (!$hidden) {
                    throw $e;
                }

                $output->writeln('<comment>⚠️ Input nascosto non disponibile, continuo con input visibile.</comment>');
                $hidden = false;
                continue;
            }

            if ($value !== '') {
                return $value;
            }

            $output->writeln('<error>❌ '.$key.' obbligatorio.</error>');
        }
    }

    protected function normalizeDomain(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('#^https?://#i', '', $value);
        $value = trim((string) $value, "/ \t\n\r\0\x0B");
        $host = parse_url('https://'.$value, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            $value = $host;
        }

        return strtolower(trim($value));
    }

    protected function normalizeProjectSlug(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('#^https?://#i', '', $value);
        $value = trim((string) $value, "/ \t\n\r\0\x0B");
        $host = parse_url('https://'.$value, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            $value = $host;
        }

        $value = strtolower($value);
        $value = str_replace('.', '-', $value);
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value);
        $value = preg_replace('/-+/', '-', (string) $value);

        return trim((string) $value, '-');
    }

    protected function defaultAppDomain(string $cwd): string
    {
        $folder = trim(basename($cwd));

        if ($folder === '' || $folder === '.' || $folder === DIRECTORY_SEPARATOR) {
            return '';
        }

        return $this->defaultProjectLabel($folder);
    }

    /**
     * Whitelist di TLD note usate da `defaultProjectLabel()` per riconoscere
     * il suffisso TLD in nomi cartella tipo `progetto-com` o `progetto-it`.
     *
     * Mantenuta conservativa per evitare false positive su nomi legittimi
     * (es. `react-app`, `my-dev-tool`, `something-co`): NON sono inclusi
     * `co`, `me`, `dev`, `app`, `ai`, `blog`, `design`, `studio`, `agency`,
     * `media`, `digital`, `art` perché possono essere parte semantica del
     * nome del progetto piuttosto che TLD.
     *
     * Aggiungi qui se usi spesso una TLD non listata; ogni voce è una
     * stringa lowercase senza punto/trattino iniziale.
     */
    protected const PROJECT_LABEL_TLDS = [
        // gTLD storiche e generiche
        'com', 'net', 'org', 'info', 'biz',
        // ccTLD europei + più diffusi internazionali
        'it', 'de', 'fr', 'es', 'uk', 'eu', 'us', 'ca', 'au',
        'ch', 'at', 'be', 'nl', 'pl', 'pt', 'ie', 'dk', 'se', 'no', 'fi',
        // nuove gTLD usate da progetti web
        'io', 'xyz', 'cloud', 'online', 'store', 'tech', 'site',
    ];

    /**
     * Deriva una "label locale" del progetto da un nome che può essere:
     *
     *   - un dominio con dot:    `fatimagabrielewedding.com`   →  `fatimagabrielewedding`
     *   - un dominio con dash:   `fatimagabrielewedding-com`   →  `fatimagabrielewedding`
     *   - uno slug:              `my-project`                  →  `my-project`
     *
     * Differisce da `normalizeProjectSlug()` perché **strippa la TLD**
     * invece di trasformare i dot in dash. Usato per costruire URL locali
     * `<label>.test` evitando il vecchio bug `fatimagabrielewedding.com`
     * → `fatimagabrielewedding-com.test`.
     *
     * Strategia in due passi:
     *   1. Se il nome contiene un dot → prende la parte prima del primo
     *      dot (`client.org.uk` → `client`).
     *   2. Altrimenti se il nome termina con `-<tld>` dove `<tld>` è in
     *      `PROJECT_LABEL_TLDS` → strippa quel suffisso
     *      (`fatimagabrielewedding-com` → `fatimagabrielewedding`).
     *      Solo un passaggio: `client-org-uk` → `client-org` (acceptable).
     *
     * Composer name e GitHub repo name continuano a usare
     * `normalizeProjectSlug()` (dot→dash) perché composer non ammette
     * dot nei nomi package.
     *
     * Esempi:
     *   `wonder-image.it`               →  `wonder-image`
     *   `wonder-image-it`               →  `wonder-image`
     *   `client.org.uk`                 →  `client`
     *   `client-org-uk`                 →  `client-org`     (single pass)
     *   `fatimagabrielewedding.com`     →  `fatimagabrielewedding`
     *   `fatimagabrielewedding-com`     →  `fatimagabrielewedding`
     *   `my-project`                    →  `my-project`
     *   `react-app`                     →  `react-app`      (app non whitelistato)
     *   `something-co`                  →  `something-co`   (co non whitelistato)
     *   `My_Project.test`               →  `my-project`
     */
    protected function defaultProjectLabel(string $value): string
    {
        $value = trim($value);

        if ($value === '' || $value === '.' || $value === DIRECTORY_SEPARATOR) {
            return '';
        }

        // Rimuovo eventuale schema http(s):// e slash di chiusura.
        $value = preg_replace('#^https?://#i', '', $value);
        $value = trim((string) $value, "/ \t\n\r\0\x0B");

        // 1. Dominio con dot → prendo l'etichetta più a sinistra.
        //    Sopravvive anche `client.org.uk` → `client`.
        if (str_contains($value, '.')) {
            $value = strstr($value, '.', true) ?: $value;
        }

        // 2. Suffisso TLD con dash (`-com`, `-it`, ...) → strippa SOLO se
        //    il chunk finale è in whitelist, per non rompere nomi tipo
        //    `react-app`. Match case-insensitive sul confronto, ma il
        //    sub-substring del valore originale preserva la casing che
        //    `normalizeProjectSlug` poi normalizza.
        $lower = strtolower($value);
        foreach (self::PROJECT_LABEL_TLDS as $tld) {
            $needle = '-'.$tld;
            $needleLen = strlen($needle);
            if (strlen($lower) > $needleLen && substr($lower, -$needleLen) === $needle) {
                $value = substr($value, 0, -$needleLen);
                break;
            }
        }

        return $this->normalizeProjectSlug($value);
    }

    protected function buildAppUrl(string $appDomain): string
    {
        $domain = $this->normalizeDomain($appDomain);

        if ($domain !== '' && str_contains($domain, '.')) {
            return 'https://'.$domain;
        }

        $slug = $this->normalizeProjectSlug($appDomain);

        return 'https://'.($slug !== '' ? $slug : 'app').'.test';
    }

    protected function composerProjectName(string $appDomain): string
    {
        $name = $this->normalizeProjectSlug($appDomain);

        return $name !== '' ? $name : 'app';
    }

    protected function commandExists(string $command): bool
    {
        $check = $this->runCommand(['sh', '-lc', 'command -v '.escapeshellarg($command).' >/dev/null 2>&1']);

        return $check['exitCode'] === 0;
    }

    /**
     * Verifica che la versione di `node` sul PATH sia >= $minMajor.
     *
     * Il package npm `wonder-image` ha `engines.node >= 20`: senza Node 20+
     * `npm install wonder-image` (invocato da `forge config`) emette warning
     * `EBADENGINE` e il sito può rompersi a runtime (build ES2022 non
     * compilabili da toolchain più vecchie).
     *
     * Esce su stderr con istruzioni concrete (Herd / nvm / brew) invece di
     * lasciar passare un warning npm in fondo a un log gigante.
     */
    protected function ensureNodeMinimumVersion(int $minMajor, OutputInterface $output): bool
    {
        $result = $this->runCommand(['node', '-v']);
        if ($result['exitCode'] !== 0) {
            // Già gestito da ensureCommandInstalled('node'). Non duplico l'errore.
            return true;
        }

        $raw = trim((string) $result['stdout']);

        // `node -v` ritorna "v20.11.1\n". Estraggo il major number.
        if (!preg_match('/^v?(\d+)\./', $raw, $m)) {
            $output->writeln('<comment>⚠️ Versione node non parseable ('.$raw.'), salto il check.</comment>');
            return true;
        }

        $major = (int) $m[1];
        if ($major >= $minMajor) {
            return true;
        }

        $output->writeln('<error>❌ Node '.$raw.' rilevato. Wonder Image richiede Node >= '.$minMajor.'.</error>');
        $output->writeln('<error>   Il package npm `wonder-image` ha "engines": { "node": ">='.$minMajor.'" }: con questa versione</error>');
        $output->writeln('<error>   `npm install wonder-image` emette EBADENGINE e la toolchain può rompersi a runtime.</error>');
        $output->writeln('');
        $output->writeln('<comment>   Soluzioni rapide:</comment>');
        $output->writeln('<comment>     • Herd:  aggiorna Herd alla versione più recente (Node '.$minMajor.'+ è di default)</comment>');
        $output->writeln('<comment>     • nvm:   nvm install '.$minMajor.' && nvm use '.$minMajor.'</comment>');
        $output->writeln('<comment>     • brew:  brew install node@'.$minMajor.' && brew link --overwrite node@'.$minMajor.'</comment>');

        return false;
    }

    protected function ensureCommandInstalled(string $command, array $versionCommand, OutputInterface $output, bool $autoInstall = true): bool
    {
        if (!$this->commandExists($command)) {
            if (!$autoInstall) {
                $output->writeln('<error>❌ '.$command.' non installato.</error>');
                return false;
            }

            $output->writeln('<comment>⚠️ '.$command.' non installato. Provo a installarlo.</comment>');

            if (!$this->installCommand($command, $output)) {
                return false;
            }
        }

        $version = $this->runCommand($versionCommand);

        if ($version['exitCode'] !== 0) {
            $output->writeln('<comment>⚠️ '.$command.' installato ma non disponibile correttamente. Provo a reinstallarlo.</comment>');

            if (!$this->installCommand($command, $output)) {
                return false;
            }

            $version = $this->runCommand($versionCommand);

            if ($version['exitCode'] !== 0) {
                $output->writeln('<error>❌ '.$command.' installato ma non disponibile correttamente.</error>');
                return false;
            }
        }

        $message = $version['stdout'] !== '' ? $version['stdout'] : $version['stderr'];

        if ($message !== '') {
            $message = strtok($message, PHP_EOL);
            $output->writeln('<info>✅ '.$command.' installato: '.$message.'</info>');
            return true;
        }

        $output->writeln('<info>✅ '.$command.' installato.</info>');

        return true;
    }

    protected function installCommand(string $command, OutputInterface $output): bool
    {
        return match ($command) {
            'node', 'npm' => $this->installWithBrew('node', $output),
            'gh' => $this->installWithBrew('gh', $output),
            'bws' => $this->installBws($output),
            default => false,
        };
    }

    protected function installWithBrew(string $formula, OutputInterface $output): bool
    {
        if (!$this->commandExists('brew')) {
            $output->writeln('<error>❌ brew non installato. Impossibile installare '.$formula.' automaticamente.</error>');
            return false;
        }

        $output->writeln('<info>📦 Eseguo: brew install '.$formula.'</info>');
        passthru('brew install '.escapeshellarg($formula), $exitCode);

        return $exitCode === 0;
    }

    protected function installBws(OutputInterface $output): bool
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $output->writeln('<error>❌ Installazione automatica di bws supportata solo su macOS.</error>');
            return false;
        }

        if (!$this->commandExists('brew')) {
            $output->writeln('<error>❌ brew non installato. Impossibile installare bws automaticamente.</error>');
            return false;
        }

        $target = $this->bitwardenMacTarget();

        if ($target === '') {
            $output->writeln('<error>❌ Architettura macOS non supportata per installare bws automaticamente.</error>');
            return false;
        }

        $brewPrefix = trim((string) $this->runCommand(['brew', '--prefix'])['stdout']);

        if ($brewPrefix === '') {
            $output->writeln('<error>❌ Impossibile determinare il percorso di brew.</error>');
            return false;
        }

        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'wonder-bws-'.getmypid();
        $assetUrl = 'https://github.com/bitwarden/sdk-sm/releases/latest/download/'.$target;
        $installPath = $brewPrefix.'/bin/bws';

        $command = 'rm -rf '.escapeshellarg($tempDir)
            .' && mkdir -p '.escapeshellarg($tempDir)
            .' && curl -fsSL '.escapeshellarg($assetUrl).' -o '.escapeshellarg($tempDir.'/bws.zip')
            .' && unzip -oq '.escapeshellarg($tempDir.'/bws.zip').' -d '.escapeshellarg($tempDir)
            .' && chmod +x '.escapeshellarg($tempDir.'/bws')
            .' && mv '.escapeshellarg($tempDir.'/bws').' '.escapeshellarg($installPath)
            .' && rm -rf '.escapeshellarg($tempDir);

        $output->writeln('<info>📦 Eseguo installazione bws da release ufficiale Bitwarden</info>');
        passthru($command, $exitCode);

        return $exitCode === 0;
    }

    protected function bitwardenMacTarget(): string
    {
        $machine = strtolower(php_uname('m'));

        return match ($machine) {
            'arm64', 'aarch64' => 'bws-aarch64-apple-darwin.zip',
            'x86_64' => 'bws-x86_64-apple-darwin.zip',
            default => '',
        };
    }

    protected function runCommand(array|string $command, array $env = [], ?string $cwd = null): array
    {
        $processEnv = getenv();

        if (!is_array($processEnv)) {
            $processEnv = [];
        }

        if (is_array($command)) {
            $command = $this->buildShellCommand($command);
        }

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd,
            array_merge($processEnv, $env)
        );

        if (!is_resource($process)) {
            return [
                'stdout' => '',
                'stderr' => '',
                'exitCode' => 1,
            ];
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'stdout' => trim((string) $stdout),
            'stderr' => trim((string) $stderr),
            'exitCode' => proc_close($process),
        ];
    }

    protected function buildShellCommand(array $command): string
    {
        return implode(' ', array_map('escapeshellarg', $command));
    }

    protected function runPassthruCommand(string $command, OutputInterface $output, string $errorMessage): bool
    {
        $output->writeln('<info>📦 Eseguo: '.$command.'</info>');
        passthru($command, $exitCode);

        if ($exitCode === 0) {
            return true;
        }

        $output->writeln('<error>❌ '.$errorMessage.'</error>');

        return false;
    }
}
