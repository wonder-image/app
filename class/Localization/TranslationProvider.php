<?php

    namespace Wonder\Localization;

    use Wonder\Localization\LanguageContext;
    use RuntimeException, RecursiveIteratorIterator, RecursiveDirectoryIterator;

    class TranslationProvider
    {
        private static ?self $instance = null;

        private array $translations = [];
        private array $defaultTranslations = [];
        private string $lang;
        private string $defaultLang;
        private array $pathFiles;
        private array $globalReplacements = [];

        private function __construct()
        {

            $this->lang = LanguageContext::getLang();
            $this->defaultLang = LanguageContext::getDefaultLang();
            $this->pathFiles = LanguageContext::getPathFiles();

            $this->translations = $this->loadFiles($this->lang);
            $this->defaultTranslations = LanguageContext::getLang() == LanguageContext::getDefaultLang() ? $this->translations : $this->loadFiles($this->defaultLang);
            
        }

        public static function init(): self
        {
            
            if (self::$instance === null) {

                self::$instance = new self();

            } else {

                // Aggiorna le proprietà se già inizializzato
                self::$instance->lang = LanguageContext::getLang();
                self::$instance->defaultLang = LanguageContext::getDefaultLang();
                self::$instance->pathFiles = LanguageContext::getPathFiles();
                self::$instance->translations = self::$instance->loadFiles(self::$instance->lang);
                self::$instance->defaultTranslations = self::$instance->lang == self::$instance->defaultLang
                    ? self::$instance->translations
                    : self::$instance->loadFiles(self::$instance->defaultLang);

            }

            return self::$instance;

        }

        public static function setGlobals(array $globals): self
        {

            if (!self::$instance) {
                throw new RuntimeException("TranslationProvider non inizializzato.");
            }

            self::$instance->globalReplacements = array_merge(self::$instance->globalReplacements, $globals);

            return self::$instance;

        }

        private function getGlobalReplacements(): array
        {

            return $this->globalReplacements;

        }

        public static function get(string $key, array $replacements = []): mixed
        {

            if (!self::$instance) {
                throw new RuntimeException("TranslationProvider non inizializzato.");
            }

            // 1. Prova lingua corrente
            $value = self::$instance->getNestedValue(self::$instance->translations, $key);

            // 2. Se non trovato, prova default
            if ($value === null) {
                $value = self::$instance->getNestedValue(self::$instance->defaultTranslations, $key);
            }

            // 3. Se ancora null → errore
            if ($value === null) {
                throw new RuntimeException("Chiave di traduzione mancante: '{$key}'");
            }

            // 4. Sostituzione placeholder
            if (!empty($replacements)) {
                $value = self::$instance->applyReplacements($value, $replacements);
            }

            // 5. Sostituzione variabili globali predefinite
            $value = self::$instance->replaceGlobals($value);

            return $value;

        }
        
        private function applyReplacements(mixed $value, array $replacements): mixed
        {
            if (is_string($value)) {
                foreach ($replacements as $placeholder => $replacement) {
                    $value = str_replace('{{' . $placeholder . '}}', $replacement, $value);
                }
                return $value;
            }

            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $value[$k] = $this->applyReplacements($v, $replacements);
                }
                return $value;
            }

            if (is_object($value)) {
                foreach ($value as $k => $v) {
                    $value->$k = $this->applyReplacements($v, $replacements);
                }
                return $value;
            }

            return $value;
        }

        private function replaceGlobals(mixed $value): mixed
        {

            if (is_string($value)) {
                foreach ($this->getGlobalReplacements() as $placeholder => $replacement) {
                    $value = str_replace('{{' . $placeholder . '}}', $replacement, $value);
                }
                return $value;
            }

            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $value[$k] = $this->replaceGlobals($v);
                }
                return $value;
            }

            if (is_object($value)) {
                foreach ($value as $k => $v) {
                    $value->$k = $this->replaceGlobals($v);
                }
                return $value;
            }

            return $value;
        }

        private function getNestedValue(array $array, string $key): mixed
        {

            $segments = explode('.', $key);
            $current = $array;

            foreach ($segments as $segment) {
                if (!is_array($current) || !array_key_exists($segment, $current)) {
                    return null;
                }
                $current = $current[$segment];
            }

            return $current;

        }


        private function loadFiles(string $lang): array
        {
        
            // Carica i file in ordine e sovrascrivi solo i valori ridichiarati nei nuovi file
            $result = [];

            foreach ($this->pathFiles as $basePath) {
                $path = "{$basePath}/{$lang}/";
                if (!is_dir($path)) { continue; }

                // Trova tutti i file JSON ricorsivamente, ordinati per data di modifica crescente
                $files = [];
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'json') {
                        $files[] = $file->getPathname();
                    }
                }

                usort($files, function($a, $b) {
                    return filemtime($a) <=> filemtime($b);
                });

                foreach ($files as $filePath) {
                    // Calcola la chiave principale come percorso relativo dal path base senza estensione .json
                    $relativePath = substr($filePath, strlen($path));
                    $relativePath = preg_replace('/\.json$/', '', $relativePath);
                    $keys = explode(DIRECTORY_SEPARATOR, $relativePath);

                    $content = json_decode(file_get_contents($filePath), true);
                    if (is_array($content)) {
                        // Inserisce il contenuto sotto la chiave principale
                        $ref = &$result;
                        foreach ($keys as $key) {
                            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                                $ref[$key] = [];
                            }
                            $ref = &$ref[$key];
                        }
                        // Sovrascrive solo i valori ridichiarati
                        $ref = self::arrayMergeDeclared($ref, $content);
                        unset($ref);
                    }
                }
            }

            return $result;

        }

        /**
         * Merge only declared values from $content into $ref, preserving existing keys in $ref
         */
        private static function arrayMergeDeclared(array $ref, array $content): array
        {
            foreach ($content as $key => $value) {
                if (is_array($value) && isset($ref[$key]) && is_array($ref[$key])) {
                    $ref[$key] = self::arrayMergeDeclared($ref[$key], $value);
                } else {
                    $ref[$key] = $value;
                }
            }
            return $ref;
        }
        
    }