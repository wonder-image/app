<?php

    namespace Wonder\Plugin\Getrix;

    use ZipArchive;

    class Import {

        private $endpoint = "https://feed.getrix.it/xml/";
        public string $xml;
        public object $array;

        function __construct($getrixId) {

            $getrixFile = $this->endpoint.$getrixId.'.zip';

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
                        $this->xml = $TMP_XML;
                    
                    # Trasformo il file XML in un'array
                        $this->array = simplexml_load_file($this->xml)->children();
                    
                    # Elimino lo zip e xml
                        unlink($TMP_ZIP);
                        unlink($TMP_XML);
                        
                }

            }

        }

    }