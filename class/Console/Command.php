<?php

    namespace Wonder\Console;

    use Symfony\Component\Console\Command\Command as SymfonyCommand;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;


    abstract class Command extends SymfonyCommand {

        public $name, $commandName, $namespace, $root;
        public $argument = [];

        abstract protected function template($className, $namespace): string;

        protected function resolveArgument($name)
        {

            $name = str_replace('/', '\\', $name); // supporta anche slash
            $parts = explode('\\', $name);
            $className = array_pop($parts);
            $relativeNamespace = implode('\\', $parts);
            $relativePath = implode(DIRECTORY_SEPARATOR, $parts);

            $fullNamespace = $relativeNamespace ? "\\$relativeNamespace" : "";
            $fullPath = $relativePath ? DIRECTORY_SEPARATOR . $relativePath : "";

            return [
                'className' => $className,
                'namespace' => $fullNamespace,
                'dir' => $fullPath,
                'filePath' => $fullPath . DIRECTORY_SEPARATOR . "$className.php"
            ];
            
        }

        protected function ensureDir(string $path): void
        {
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }

        protected function configure(): void 
        {

            $this
                ->setName($this->name)
                ->setDescription('Crea un nuovo'.$this->commandName);

            foreach ($this->argument as $name => $mode) {
                $this->addArgument($name, $mode, $name.' del '.$this->commandName);
            }
            
        }

        protected function execute(InputInterface $input, OutputInterface $output): int 
        {

            $resolver = $this->resolveArgument($input->getArgument('name'));

            $className = $resolver['className'];
            $namespace = $resolver['namespace'];
            $dir = $resolver['dir'];
            $filePath = $resolver['filePath'];

            $namespace = $this->namespace.$resolver['namespace'];

            $controllerDir = "{$this->root}$dir";
            $controllerPath = "{$this->root}$filePath";

            # Verifico che la cartella esista, in alternativa la creo 
            $this->ensureDir($controllerDir);

            # Verifico se il modello esiste già
            if (file_exists($controllerPath)) {

                $output->writeln("<error>❌ ".$this->commandName." $namespace già esistente!</error>");
                return self::FAILURE;

            }

            # Creo il file
            file_put_contents(
                $controllerPath, 
                $this->template($className, $namespace)
            );

            # Inserisco la risposta nel terminale
            $output->writeln("<info>✅ ".$this->commandName." $namespace\\$className creato con successo.</info>");
            
            return self::SUCCESS;

        }

    }