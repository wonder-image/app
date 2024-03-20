<?php

    namespace Wonder\Plugin\Getrix;

    use ZipArchive;

    class Import {

        private $endpoint = [
            'immobili' => 'https://feed.getrix.it/xml/',
            'categorie' => 'https://feed.getrix.it/xml/tipologie.asp',
            'comuni' => 'https://feed.getrix.it/xml/comuni.asp',
            'quartieri' => 'https://feed.getrix.it/xml/quartieri.asp'
        ];

        public function Immobili($getrixId) {

            $getrixFile = $this->endpoint['immobili'].$getrixId.'.zip';

            $TMP_ZIP = $getrixId.'.zip';
            $TMP_XML = $getrixId.'.xml';

            if (copy($getrixFile, $TMP_ZIP)) {

                $zip = new ZipArchive;
                $unzipped = $zip->open($TMP_ZIP);

                if ($unzipped === true) {
                    
                    # Estraggo il file zip
                    $zip->extractTo(getcwd());
                    $zip->close();
                        
                    # Trasformo il file XML in un'array
                    $array = simplexml_load_file($TMP_XML)->children();

                    # Elimino lo zip e xml
                    unlink($TMP_ZIP);
                    unlink($TMP_XML);

                    return $array;
                        
                } else {

                    return [];

                }

            } else {

                return [];

            }

        }

        public function Categorie() {

            return json_decode(json_encode(simplexml_load_file($this->endpoint['categorie'])), true)['Categoria'];

        }

        public function Comuni() {

            return json_decode(json_encode(simplexml_load_file($this->endpoint['comuni'])), true)['Comuni'];

        }

        public function Quartieri() {

            return json_decode(json_encode(simplexml_load_file($this->endpoint['quartieri'])), true)['Quartiere'];

        }

    }