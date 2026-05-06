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
 *   `ASSETS_VERSION`.
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

        if (!$this->ensureCommandInstalled('bws', ['bws', '--version'], $output, false)) {
            return Command::FAILURE;
        }

        if (!$this->ensureCommandInstalled('gh', ['gh', '--version'], $output)) {
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

        // 7. GitHub Variables: APP_DOMAIN + ASSETS_VERSION.
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

        return Command::SUCCESS;
    }
}
