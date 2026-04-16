<?php

    namespace Wonder\Console\Commands;

    use Wonder\Console\Command;
    use Symfony\Component\Console\Input\InputArgument;
    
    class MakeModel extends Command {

        public $name = 'make:model';
        public $commandName = 'Modello';

        public $namespace = "Wonder\App\Models";
        public $root = "./class/App/Models/";
        public $argument = [ 
            'name' => InputArgument::REQUIRED
        ];

        protected function template($className, $namespace): string
        {
            $table = $this->toSnakeCase($className);
            $folder = $this->toKebabCase($className);

            $template = "<?php\n";
            $template .= "\n";
            $template .= "namespace {$namespace};\n";
            $template .= "\n";
            $template .= "use Wonder\\App\\Model;\n";
            $template .= "use Wonder\\Data\\UploadSchema as Field;\n";
            $template .= "use Wonder\\Sql\\TableSchema as Column;\n";
            $template .= "\n";
            $template .= "final class {$className} extends Model\n";
            $template .= "{\n";
            $template .= "    public static string \$table = '{$table}';\n";
            $template .= "    public static string \$folder = '{$folder}';\n";
            $template .= "    public static string \$icon = 'bi bi-circle';\n";
            $template .= "\n";
            $template .= "    public static function tableSchema(): array\n";
            $template .= "    {\n";
            $template .= "        return [\n";
            $template .= "            // Column::key('name'),\n";
            $template .= "        ];\n";
            $template .= "    }\n";
            $template .= "\n";
            $template .= "    public static function dataSchema(): array\n";
            $template .= "    {\n";
            $template .= "        return [\n";
            $template .= "            // Field::key('name')->text()->required(),\n";
            $template .= "        ];\n";
            $template .= "    }\n";
            $template .= "}\n";

            return $template;

        }

        private function toSnakeCase(string $value): string
        {

            $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);

            return strtolower((string) $value);

        }

        private function toKebabCase(string $value): string
        {

            return str_replace('_', '-', $this->toSnakeCase($value));

        }

    }
