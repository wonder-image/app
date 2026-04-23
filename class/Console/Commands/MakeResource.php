<?php

    namespace Wonder\Console\Commands;

    use Wonder\Console\Command;
    use Symfony\Component\Console\Input\InputArgument;
    
    class MakeResource extends Command {

        public $name = 'make:resource';
        public $commandName = 'Risorsa';

        public $namespace = "Wonder\App\Resources";
        public $root = "./class/App/Resources/";
        public $argument = [ 
            'name' => InputArgument::REQUIRED
        ];

        protected function normalizeResolved(array $resolved): array
        {

            if (!str_ends_with($resolved['className'], 'Resource')) {
                $resolved['className'] .= 'Resource';
            }

            $resolved['filePath'] = $resolved['dir'].DIRECTORY_SEPARATOR.$resolved['className'].'.php';

            return $resolved;

        }

        protected function template($className, $namespace): string
        {
            $resourceStem = preg_replace('/Resource$/', '', $className) ?: $className;
            $resourceLabel = $this->toLabel($resourceStem);
            $modelNamespace = str_replace('\\Resources', '\\Models', $namespace);
            $modelClass = $modelNamespace.'\\'.$resourceStem;

            $template = "<?php\n";
            $template .= "\n";
            $template .= "namespace {$namespace};\n";
            $template .= "\n";
            $template .= "use Wonder\\App\\Resource;\n";
            $template .= "use Wonder\\App\\ResourceSchema\\ApiSchema;\n";
            $template .= "use Wonder\\App\\ResourceSchema\\FormInput;\n";
            $template .= "use Wonder\\App\\ResourceSchema\\NavigationSchema;\n";
            $template .= "use Wonder\\App\\ResourceSchema\\PageSchema;\n";
            $template .= "use Wonder\\App\\ResourceSchema\\PermissionSchema;\n";
            $template .= "use Wonder\\App\\ResourceSchema\\TableColumn;\n";
            $template .= "use Wonder\\App\\ResourceSchema\\TableLayoutSchema;\n";
            $template .= "use Wonder\\Elements\\Components\\Card;\n";
            $template .= "use Wonder\\Elements\\Form\\Form;\n";
            $template .= "\n";
            $template .= "final class {$className} extends Resource\n";
            $template .= "{\n";
            $template .= "    public static string \$model = \\{$modelClass}::class;\n";
            $template .= "\n";
            $template .= "    public static function textSchema(): array\n";
            $template .= "    {\n";
            $template .= "        return [\n";
            $template .= "            'label' => '{$resourceLabel}',\n";
            $template .= "            'plural_label' => '{$resourceLabel}',\n";
            $template .= "            'last' => 'ultimi',\n";
            $template .= "            'all' => 'tutti',\n";
            $template .= "            'article' => 'i',\n";
            $template .= "            'full' => 'pieno',\n";
            $template .= "            'empty' => 'vuoto',\n";
            $template .= "            'this' => 'questo',\n";
            $template .= "        ];\n";
            $template .= "    }\n";
            $template .= "\n";
            $template .= "    public static function labelSchema(): array\n";
            $template .= "    {\n";
            $template .= "        return [\n";
            $template .= "            // 'name' => 'Nome',\n";
            $template .= "        ];\n";
            $template .= "    }\n";
            $template .= "\n";
            $template .= "    public static function formSchema(): array\n";
            $template .= "    {\n";
            $template .= "        return [\n";
            $template .= "            // Definisce gli input del backend.\n";
            $template .= "            FormInput::key('name')->text(),\n";
            $template .= "        ];\n";
            $template .= "    }\n";
            $template .= "\n";
            $template .= "    public static function formLayoutSchema(): ?Form\n";
            $template .= "    {\n";
            $template .= "        return (new Form)->components([\n";
            $template .= "            (new Card)->components([\n";
            $template .= "                static::getInput('name'),\n";
            $template .= "            ])->columnSpan(1),\n";
            $template .= "        ])->columns(1);\n";
            $template .= "    }\n";
            $template .= "\n";
            $template .= "    public static function tableSchema(): array\n";
            $template .= "    {\n";
            $template .= "        return [\n";
            $template .= "            TableColumn::key('name')->text(),\n";
            $template .= "        ];\n";
            $template .= "    }\n";
            $template .= "\n";
            $template .= "    public static function tableLayoutSchema(): TableLayoutSchema\n";
            $template .= "    {\n";
            $template .= "        return TableLayoutSchema::for(static::class)\n";
            $template .= "            ->title('Lista '.static::pluralLabel())\n";
            $template .= "            ->buttonAdd('Aggiungi '.static::label())\n";
            $template .= "            ->filters();\n";
            $template .= "    }\n";
            $template .= "\n";
            $template .= "    public static function pageSchema(): PageSchema\n";
            $template .= "    {\n";
            $template .= "        return PageSchema::for(static::class);\n";
            $template .= "    }\n";
            $template .= "\n";
            $template .= "    public static function apiSchema(): ApiSchema\n";
            $template .= "    {\n";
            $template .= "        return ApiSchema::for(static::class);\n";
            $template .= "    }\n";
            $template .= "\n";
            $template .= "    public static function permissionSchema(): PermissionSchema\n";
            $template .= "    {\n";
            $template .= "        return PermissionSchema::for(static::class)\n";
            $template .= "            ->backendCrud(['admin'])\n";
            $template .= "            ->apiCrud(['admin']);\n";
            $template .= "    }\n";
            $template .= "\n";
            $template .= "    public static function navigationSchema(): NavigationSchema\n";
            $template .= "    {\n";
            $template .= "        return NavigationSchema::for(static::class)\n";
            $template .= "            ->enabled(false);\n";
            $template .= "    }\n";
            $template .= "}\n";

            return $template;

        }

        private function toLabel(string $value): string
        {

            $value = preg_replace('/(?<!^)[A-Z]/', ' $0', $value);
            $value = strtolower((string) $value);

            return trim(preg_replace('/\s+/', ' ', (string) $value));

        }

    }
