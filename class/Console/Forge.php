<?php

    namespace Wonder\Console;

    use Symfony\Component\Console\Application;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class Forge
    {
        protected array $commands = [];

        public function __construct()
        {
            
            $this->commands = [
                \Wonder\Console\Commands\Config::class,
                \Wonder\Console\Commands\MakeModel::class,
                // altri comandi...
            ];

        }

        public function register(string $name, string $class): void
        {
            $this->commands[$name] = $class;
        }

        public function run(InputInterface $input, OutputInterface $output): int
        {
                    
            $app = new Application();

            foreach ($this->commands as $class) {
                $app->add(new $class);
            }

            return $app->run($input, $output);

        }

        private function printAvailableCommands(OutputInterface $output): void
        {
            $output->writeln("📦 Comandi disponibili:");
            foreach ($this->commands as $cmd => $class) {
                $output->writeln("  → $cmd");
            }
        }

        protected function listCommands(): void
        {
            echo "📦 Comandi disponibili:\n";
            foreach ($this->commands as $name => $class) {
                echo "  → $name\n";
            }
        }

    }
