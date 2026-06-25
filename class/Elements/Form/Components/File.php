<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Field;

    class File extends Field {

        public string $type = 'file';

        public function file(string $file): self
        {

            return $this->schema('file', trim($file));

        }

        public function uploader(string $uploader): self
        {

            return $this->schema('uploader', trim($uploader));

        }

        public function maxFile(int $maxFile): self
        {

            return $this->schema('max_file', max(1, $maxFile));

        }

        public function maxSize(int $maxSize): self
        {

            return $this->schema('max_size', max(1, $maxSize));

        }

        public function directory(string $directory): self
        {

            return $this->schema('directory', $directory);

        }

        public function fileValue(mixed $value): self
        {

            return $this->schema('file_value', $value);

        }

        public function sizeBefore(bool $sizeBefore): self
        {

            return $this->schema('size_before', $sizeBefore);

        }

        public function minSizeImage(?string $size): self
        {

            return $this->schema('min_size_image', $size);

        }

        /**
         * Modalità di rendering. Default `dragdrop` (Filepond, drag&drop UI
         * usata da `inputFileDragDrop`). `classic` produce l'UI dell'admin
         * storico (form-control + info-block "File ammessi/massimi/peso" +
         * eventuale gallery dei file già caricati), come fa `inputFile`.
         */
        public function mode(string $mode): self
        {

            return $this->schema('mode', $mode === 'classic' ? 'classic' : 'dragdrop');

        }

        /**
         * HTML pre-renderizzato della gallery di file esistenti (usato in
         * modalità `classic`). La logica di build vive in
         * `inputFile()` perché ha bisogno di globals (`$PATH`, `$NAME`,
         * `$VALUES`); qui resta solo l'embed.
         */
        public function gallery(string $html): self
        {

            return $this->schema('gallery_html', $html);

        }

        /**
         * Stringa con la lista delle estensioni accettate, mostrata nel
         * blocco informativo della modalità `classic` (es. ".png - .jpg
         * - .jpeg"). Indipendente da `accept` (l'attributo HTML).
         */
        public function extensionsAccept(string $extensions): self
        {

            return $this->schema('extensions_accept', $extensions);

        }

        protected function renderInput(): string {

            return '';

        }

    }
