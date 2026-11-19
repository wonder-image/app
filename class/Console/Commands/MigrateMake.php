<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** `forge migrate:make <nome> [--destructive]`: crea il file nel sito (`database/migrations`). */
class MigrateMake extends Command
{
    public $name = 'migrate:make';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Crea una migrazione versionata vuota nel sito')
            ->addArgument('name', InputArgument::REQUIRED, 'Descrizione breve, es. togli_colonna_vecchia')
            ->addOption('destructive', null, InputOption::VALUE_NONE, 'Dichiara destructive(): ammette dropColumn e dropIndex')
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Cartella di destinazione', 'database/migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $input->getArgument('name'))), '_');

        if ($slug === '') {
            $output->writeln('<error>❌ Nome non valido.</error>');
            return Command::FAILURE;
        }

        $dir = (string) $input->getOption('dir');
        $dir = str_starts_with($dir, '/') ? $dir : (getcwd() ?: '.').'/'.$dir;

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $output->writeln('<error>❌ Cartella non creabile.</error>');
            return Command::FAILURE;
        }

        $path = $dir.'/'.gmdate('YmdHis').'_'.$slug.'.php';
        $destructive = $input->getOption('destructive') ? 'true' : 'false';

        file_put_contents($path, <<<PHP
<?php

use Wonder\\Sql\\Migration;

// Solo in avanti: niente down(). Operazioni idempotenti, una cosa per file.
return new class {
    public function description(): string
    {
        return '{$slug}';
    }

    public function destructive(): bool
    {
        return {$destructive};
    }

    public function up(Migration \$db): void
    {
        //
    }
};

PHP);

        $output->writeln('✔ '.$path);

        return Command::SUCCESS;
    }
}
