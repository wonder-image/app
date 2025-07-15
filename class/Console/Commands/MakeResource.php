<?php

    namespace Wonder\Console\Commands;

    use Wonder\Console\Command;
    use Symfony\Component\Console\Input\InputArgument;
    
    class MakeModel extends Command {

        public $name = 'make:resource';
        public $commandName = 'Risorsa';

        public $namespace = "App\Resources";
        public $root = "./class/Resources/";
        public $argument = [ 
            'name' => InputArgument::REQUIRED
        ];

        protected function template($className, $namespace): string
        {

            $template = "<?php\n";
            $template .= "\n";
            $template .= "\tnamespace {$namespace};\n";
            $template .= "\n";
            $template .= "\tclass {$className} {\n";
            $template .= "\n";
            $template .= "\t\tpublic static \$table = '';\n";
            $template .= "\t\tpublic static \$folder = '';\n";
            $template .= "\n";
            $template .= "\t}\n";

            return $template;

        }

    }