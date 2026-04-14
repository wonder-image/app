<?php

namespace Wonder\Console\Commands;

use mysqli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DbInit extends LocalEnvironmentCommand
{
    public $name = 'db:init';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setAliases(['local:db:init'])
            ->setDescription('Inizializza .env e fa il provisioning del database locale MySQL.')
            ->addOption('app-domain', null, InputOption::VALUE_REQUIRED, 'APP_DOMAIN del progetto')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host locale per APP_URL', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Porta locale per APP_URL', '8088')
            ->addOption('db-hostname', null, InputOption::VALUE_REQUIRED, 'Valore APP DB_HOSTNAME da scrivere nel file .env')
            ->addOption('app-db-username', null, InputOption::VALUE_REQUIRED, 'Username applicativo da scrivere nel file .env')
            ->addOption('app-db-password', null, InputOption::VALUE_REQUIRED, 'Password applicativa da scrivere nel file .env')
            ->addOption('admin-host', null, InputOption::VALUE_REQUIRED, 'Host admin MySQL')
            ->addOption('admin-port', null, InputOption::VALUE_REQUIRED, 'Porta admin MySQL')
            ->addOption('admin-username', null, InputOption::VALUE_REQUIRED, 'Username admin MySQL')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Password admin MySQL')
            ->addOption('charset', null, InputOption::VALUE_REQUIRED, 'Charset database applicativo', 'latin1')
            ->addOption('collation', null, InputOption::VALUE_REQUIRED, 'Collation database applicativo', 'latin1_swedish_ci')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Esegue il provisioning senza chiedere conferma');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cwd = getcwd() ?: '.';
        $lines = $this->readEnvLines($cwd, $output);

        if ($lines === null) {
            return Command::FAILURE;
        }

        $host = trim((string) $input->getOption('host'));
        $port = (int) $input->getOption('port');
        $charset = trim((string) $input->getOption('charset'));
        $collation = trim((string) $input->getOption('collation'));
        $force = (bool) $input->getOption('force');

        if ($host === '') {
            $output->writeln('<error>❌ Host locale non valido.</error>');
            return Command::FAILURE;
        }

        if (!$this->validatePort($port)) {
            $output->writeln('<error>❌ Porta non valida. Usa un valore tra 1 e 65535.</error>');
            return Command::FAILURE;
        }

        $adminHost = trim((string) $input->getOption('admin-host'));
        if ($adminHost === '') {
            $adminHost = $this->askPromptedValue($input, $output, 'ADMIN_HOST', 'Inserisci admin-host MySQL [127.0.0.1]:', '127.0.0.1');
        }

        $adminPortValue = trim((string) $input->getOption('admin-port'));
        if ($adminPortValue === '') {
            $adminPortValue = $this->askPromptedValue($input, $output, 'ADMIN_PORT', 'Inserisci admin-port MySQL [3306]:', '3306');
        }

        if (!ctype_digit($adminPortValue)) {
            $output->writeln('<error>❌ admin-port non valido. Usa un numero intero.</error>');
            return Command::FAILURE;
        }

        $adminPort = (int) $adminPortValue;

        if (!$this->validatePort($adminPort)) {
            $output->writeln('<error>❌ Porta admin MySQL non valida. Usa un valore tra 1 e 65535.</error>');
            return Command::FAILURE;
        }

        $adminUsername = trim((string) $input->getOption('admin-username'));
        if ($adminUsername === '') {
            $adminUsername = $this->askPromptedValue($input, $output, 'ADMIN_USERNAME', 'Inserisci admin-username MySQL [root]:', 'root');
        }

        $adminPassword = (string) $input->getOption('admin-password');
        if ($adminPassword === '') {
            $adminPassword = $this->askPromptedValue($input, $output, 'ADMIN_PASSWORD', 'Inserisci admin-password MySQL:', '', true);
        }

        if ($adminHost === '' || $adminUsername === '' || $adminPassword === '') {
            $output->writeln('<error>❌ Credenziali admin MySQL mancanti. Usa le opzioni CLI oppure rispondi ai prompt interattivi.</error>');
            return Command::FAILURE;
        }

        if (!$this->isMysqlWord($charset) || !$this->isMysqlWord($collation)) {
            $output->writeln('<error>❌ Charset o collation non validi.</error>');
            return Command::FAILURE;
        }

        $keyToIndex = $this->envKeyToIndex($lines);
        $appDomainOption = $this->normalizeDomain(trim((string) $input->getOption('app-domain')));
        $appDomain = $appDomainOption !== ''
            ? $appDomainOption
            : $this->normalizeDomain($this->envValue($lines, $keyToIndex, 'APP_DOMAIN'));

        if ($appDomain === '') {
            $appDomain = $this->defaultAppDomain($cwd);

            if ($appDomain !== '') {
                $output->writeln('<info>✅ APP_DOMAIN rilevato dalla cartella progetto: '.$appDomain.'</info>');
            }
        }

        if ($appDomain === '') {
            $appDomain = $this->askRequiredDomain($input, $output);
        }

        if ($appDomain === '') {
            return Command::FAILURE;
        }

        $derivedDatabase = $this->deriveDatabaseNameFromAppDomain($appDomain);
        $existingDbDatabase = $this->envValue($lines, $keyToIndex, 'DB_DATABASE');
        $existingMainDatabase = $this->parseMainDatabase($existingDbDatabase);

        if (
            $existingMainDatabase !== ''
            && !$this->isMissingEnvValue($existingDbDatabase, 'DB_DATABASE')
            && $existingMainDatabase !== $derivedDatabase
            && $appDomainOption === ''
        ) {
            $output->writeln('<error>❌ DB_DATABASE già valorizzato con `'.$existingDbDatabase.'`, ma APP_DOMAIN genera `main:'.$derivedDatabase.'`.</error>');
            $output->writeln('<comment>ℹ️ Il comando non sovrascrive DB_DATABASE già validi senza un input esplicito.</comment>');
            return Command::FAILURE;
        }

        $dbDatabase = $this->formatMainDatabase($derivedDatabase, $existingDbDatabase);
        $dbHostnameOption = trim((string) $input->getOption('db-hostname'));
        $dbHostname = $dbHostnameOption !== ''
            ? $dbHostnameOption
            : $this->envValue($lines, $keyToIndex, 'DB_HOSTNAME');

        if ($this->isMissingEnvValue($dbHostname, 'DB_HOSTNAME')) {
            $dbHostname = $adminHost.':'.$adminPort;
        }

        $appDbUsernameOption = trim((string) $input->getOption('app-db-username'));
        $appDbPasswordOption = (string) $input->getOption('app-db-password');
        $resolvedAppDbUsername = $appDbUsernameOption !== ''
            ? $this->normalizeMysqlIdentifierOption($appDbUsernameOption, $this->buildDefaultDbUsername($derivedDatabase))
            : $this->envValue($lines, $keyToIndex, 'DB_USERNAME');

        if ($this->isMissingEnvValue($resolvedAppDbUsername, 'DB_USERNAME')) {
            $resolvedAppDbUsername = $this->buildDefaultDbUsername($derivedDatabase);
        }

        $resolvedAppDbPassword = $appDbPasswordOption !== ''
            ? $appDbPasswordOption
            : $this->envValue($lines, $keyToIndex, 'DB_PASSWORD');

        if ($this->isMissingEnvValue($resolvedAppDbPassword, 'DB_PASSWORD')) {
            $resolvedAppDbPassword = $this->randomAlphaNumeric(20);
        }

        $resolvedCharset = $this->envValue($lines, $keyToIndex, 'DB_CHARSET');
        if ($this->isMissingEnvValue($resolvedCharset, 'DB_CHARSET')) {
            $resolvedCharset = $charset;
        }

        $resolvedCollation = $this->envValue($lines, $keyToIndex, 'DB_COLLATION');
        if ($this->isMissingEnvValue($resolvedCollation, 'DB_COLLATION')) {
            $resolvedCollation = $collation;
        }

        if (!$this->isMysqlWord($resolvedCharset) || !$this->isMysqlWord($resolvedCollation)) {
            $output->writeln('<error>❌ DB_CHARSET o DB_COLLATION non validi nel file .env o nelle opzioni CLI.</error>');
            return Command::FAILURE;
        }

        $resolvedAppUrl = $this->envValue($lines, $keyToIndex, 'APP_URL');
        if ($this->isMissingEnvValue($resolvedAppUrl, 'APP_URL')) {
            $resolvedAppUrl = $this->buildLocalAppUrl($host, $port);
        }

        $resolvedAppKey = $this->envValue($lines, $keyToIndex, 'APP_KEY');
        if ($this->isMissingEnvValue($resolvedAppKey, 'APP_KEY')) {
            $resolvedAppKey = bin2hex(random_bytes(32));
        }

        $resolvedUserPassword = $this->envValue($lines, $keyToIndex, 'USER_PASSWORD');
        if ($this->isMissingEnvValue($resolvedUserPassword, 'USER_PASSWORD')) {
            $resolvedUserPassword = $this->randomAlphaNumeric(8);
        }

        $output->writeln('');
        $output->writeln('<info>📋 Riepilogo provisioning locale</info>');
        $output->writeln('  APP_DOMAIN: '.$appDomain);
        $output->writeln('  Database derivato: '.$derivedDatabase);
        $output->writeln('  Admin MySQL: '.$adminHost.':'.$adminPort);
        $output->writeln('  Host DB app: '.$dbHostname);
        $output->writeln('  Utente DB app: '.$resolvedAppDbUsername);
        $output->writeln('  Charset: '.$resolvedCharset);
        $output->writeln('  Collation: '.$resolvedCollation);

        if (!$force && !$this->confirmAction($input, $output, 'Procedo con inizializzazione .env e provisioning MySQL?')) {
            $output->writeln('<comment>ℹ️ Operazione annullata.</comment>');
            return Command::FAILURE;
        }

        $updatedKeys = [];
        $updatedKeys = array_merge($updatedKeys, $this->syncEnvValue($lines, $keyToIndex, 'APP_DOMAIN', $appDomain, $appDomainOption !== ''));
        $updatedKeys = array_merge($updatedKeys, $this->syncEnvValue($lines, $keyToIndex, 'APP_URL', $resolvedAppUrl));
        $updatedKeys = array_merge($updatedKeys, $this->syncEnvValue($lines, $keyToIndex, 'APP_KEY', $resolvedAppKey));
        $updatedKeys = array_merge($updatedKeys, $this->syncEnvValue($lines, $keyToIndex, 'DB_HOSTNAME', $dbHostname, $dbHostnameOption !== ''));
        $updatedKeys = array_merge($updatedKeys, $this->syncEnvValue($lines, $keyToIndex, 'DB_USERNAME', $resolvedAppDbUsername, $appDbUsernameOption !== ''));
        $updatedKeys = array_merge($updatedKeys, $this->syncEnvValue($lines, $keyToIndex, 'DB_PASSWORD', $resolvedAppDbPassword, $appDbPasswordOption !== ''));
        $updatedKeys = array_merge($updatedKeys, $this->syncEnvValue($lines, $keyToIndex, 'DB_DATABASE', $dbDatabase, $appDomainOption !== ''));
        $updatedKeys = array_merge($updatedKeys, $this->syncEnvValue($lines, $keyToIndex, 'DB_CHARSET', $resolvedCharset));
        $updatedKeys = array_merge($updatedKeys, $this->syncEnvValue($lines, $keyToIndex, 'DB_COLLATION', $resolvedCollation));
        $updatedKeys = array_merge($updatedKeys, $this->syncEnvValue($lines, $keyToIndex, 'USER_PASSWORD', $resolvedUserPassword));
        $updatedKeys = array_values(array_unique($updatedKeys));

        if (!$this->writeEnvLines($cwd, $lines, $output, $updatedKeys)) {
            return Command::FAILURE;
        }

        mysqli_report(MYSQLI_REPORT_OFF);
        $admin = @new mysqli($adminHost, $adminUsername, $adminPassword, '', $adminPort);

        if ($admin->connect_errno) {
            $output->writeln('<error>❌ Connessione admin MySQL fallita: '.$admin->connect_error.'</error>');
            return Command::FAILURE;
        }

        if (!$this->provisionDatabase($admin, $derivedDatabase, $resolvedCharset, $resolvedCollation, $resolvedAppDbUsername, $resolvedAppDbPassword, $output)) {
            $admin->close();
            return Command::FAILURE;
        }

        $admin->close();
        $output->writeln('<info>✅ Provisioning database locale completato.</info>');

        return Command::SUCCESS;
    }

    private function syncEnvValue(array &$lines, array &$keyToIndex, string $key, string $value, bool $forceWrite = false): array
    {
        $existing = $this->envValue($lines, $keyToIndex, $key);

        if (!$forceWrite && !$this->isMissingEnvValue($existing, $key)) {
            return [];
        }

        if ($existing === $value) {
            return [];
        }

        $this->setEnvValue($lines, $keyToIndex, $key, $value);

        return [$key];
    }

    private function provisionDatabase(
        mysqli $admin,
        string $databaseName,
        string $charset,
        string $collation,
        string $appUsername,
        string $appPassword,
        OutputInterface $output
    ): bool {
        $databaseIdentifier = $this->quoteMysqlIdentifier($databaseName);
        $charsetIdentifier = $this->quoteMysqlWord($charset);
        $collationIdentifier = $this->quoteMysqlWord($collation);

        if (!$this->runAdminQuery(
            $admin,
            'CREATE DATABASE IF NOT EXISTS '.$databaseIdentifier.' CHARACTER SET '.$charsetIdentifier.' COLLATE '.$collationIdentifier,
            $output,
            'Impossibile creare il database applicativo.'
        )) {
            return false;
        }

        $output->writeln('<info>✅ Database verificato: '.$databaseName.'</info>');

        foreach (['localhost', '127.0.0.1'] as $grantHost) {
            if (!$this->ensureApplicationUser($admin, $appUsername, $appPassword, $grantHost, $output)) {
                return false;
            }

            if (!$this->runAdminQuery(
                $admin,
                'GRANT ALL PRIVILEGES ON '.$databaseIdentifier.'.* TO '.$this->quoteMysqlAccount($admin, $appUsername, $grantHost),
                $output,
                'Impossibile assegnare i privilegi a '.$appUsername.'@'.$grantHost.'.'
            )) {
                return false;
            }

            $output->writeln('<info>✅ Utente verificato: '.$appUsername.'@'.$grantHost.'</info>');
        }

        if (!$this->runAdminQuery($admin, 'FLUSH PRIVILEGES', $output, 'Impossibile eseguire FLUSH PRIVILEGES.')) {
            return false;
        }

        return true;
    }

    private function ensureApplicationUser(mysqli $admin, string $username, string $password, string $host, OutputInterface $output): bool
    {
        $account = $this->quoteMysqlAccount($admin, $username, $host);
        $passwordLiteral = $this->quoteMysqlString($admin, $password);

        $createIfMissing = 'CREATE USER IF NOT EXISTS '.$account.' IDENTIFIED BY '.$passwordLiteral;

        if (!$admin->query($createIfMissing)) {
            if ($admin->errno !== 1064) {
                $output->writeln('<error>❌ Impossibile creare l\'utente '.$username.'@'.$host.': '.$admin->error.'</error>');
                return false;
            }

            $createLegacy = 'CREATE USER '.$account.' IDENTIFIED BY '.$passwordLiteral;

            if (!$admin->query($createLegacy) && $admin->errno !== 1396) {
                $output->writeln('<error>❌ Impossibile creare l\'utente '.$username.'@'.$host.': '.$admin->error.'</error>');
                return false;
            }
        }

        $alterUser = 'ALTER USER '.$account.' IDENTIFIED BY '.$passwordLiteral;

        if ($admin->query($alterUser)) {
            return true;
        }

        if ($admin->errno !== 1064) {
            $output->writeln('<error>❌ Impossibile aggiornare la password per '.$username.'@'.$host.': '.$admin->error.'</error>');
            return false;
        }

        $setPassword = 'SET PASSWORD FOR '.$account.' = PASSWORD('.$passwordLiteral.')';

        if ($admin->query($setPassword)) {
            return true;
        }

        $output->writeln('<error>❌ Impossibile aggiornare la password per '.$username.'@'.$host.': '.$admin->error.'</error>');
        return false;
    }

    private function runAdminQuery(mysqli $admin, string $sql, OutputInterface $output, string $message): bool
    {
        if ($admin->query($sql)) {
            return true;
        }

        $output->writeln('<error>❌ '.$message.' '.$admin->error.'</error>');
        return false;
    }

    private function quoteMysqlIdentifier(string $value): string
    {
        return '`'.str_replace('`', '``', trim($value)).'`';
    }

    private function quoteMysqlWord(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_]+/', '', $value);
    }

    private function quoteMysqlString(mysqli $admin, string $value): string
    {
        return "'".$admin->real_escape_string($value)."'";
    }

    private function quoteMysqlAccount(mysqli $admin, string $username, string $host): string
    {
        return $this->quoteMysqlString($admin, $username).'@'.$this->quoteMysqlString($admin, $host);
    }

    private function isMysqlWord(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1;
    }
}
