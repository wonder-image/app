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
        //    se già configurato. NON deriva dal nome cartella locale (che
        //    serve a Herd, non al deploy). Il valore va in GitHub Variables;
        //    NON viene scritto nel .env locale.
        $existingGhVars = $this->existingGithubRepositoryVariables(
            // primo tentativo: useremo poi appDomain risolto, qui passa
            // qualunque non-empty (gh usa la repo del cwd se la string non
            // matcha). Comunque le GH var dipendono dalla repo, e la repo
            // potrebbe non esistere ancora — in quel caso la lista sarà vuota.
            $this->envValue($lines, $keyToIndex, 'APP_DOMAIN'),
            $output
        ) ?? [];
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

        // 4. Repo GitHub: crea/verifica.
        if (!$this->ensureGithubRepository($appDomain, $output)) {
            return Command::FAILURE;
        }

        // 5. GitHub Secrets: BWS_*. Non APP_DEPLOY_TOKEN — quello viene
        //    gestito subito dopo, separatamente, per sapere se esiste già.
        if (!$this->syncGithubRepositorySecrets($appDomain, [
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
        $existingGhSecrets = $this->existingGithubRepositorySecretNames($appDomain, $output) ?? [];
        if (!in_array('APP_DEPLOY_TOKEN', $existingGhSecrets, true)) {
            $deployToken = bin2hex(random_bytes(32));
            if (!$this->syncGithubRepositorySecrets($appDomain, [
                'APP_DEPLOY_TOKEN' => $deployToken,
            ], $output)) {
                return Command::FAILURE;
            }
            $output->writeln('<info>🔑 Generato APP_DEPLOY_TOKEN e salvato in GitHub Secrets.</info>');
        } else {
            $output->writeln('<info>↺ APP_DEPLOY_TOKEN già presente in GitHub Secrets, mantenuto invariato.</info>');
        }

        // Avvisa se esiste ancora il vecchio nome (legacy, GitHub blocca
        // i secret che iniziano con GITHUB_ ma se è stato creato manualmente
        // o ereditato non lo eliminiamo automaticamente).
        if (in_array('GITHUB_API_TOKEN', $existingGhSecrets, true)) {
            $output->writeln('<comment>⚠️  GITHUB_API_TOKEN ancora presente in GitHub Secrets (nome legacy). Puoi rimuoverlo manualmente con:</comment>');
            $output->writeln('<comment>   gh secret delete GITHUB_API_TOKEN --repo '.$this->resolveGithubRepositoryName($appDomain).'</comment>');
        }

        // 7. GitHub Variables: APP_DOMAIN + ASSETS_VERSION.
        $assetsVersion = $existingGhVars['ASSETS_VERSION'] ?? '';
        if ($assetsVersion === '') {
            $assetsVersion = $this->envValue($lines, $keyToIndex, 'ASSETS_VERSION');
        }
        if ($assetsVersion === '') {
            $assetsVersion = '0.0';
        }

        if (!$this->syncGithubRepositoryVariables($appDomain, [
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
