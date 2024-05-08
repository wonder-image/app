<?php

    namespace Wonder\Plugin\Gestim;

    use ZipArchive;

    class Import {

        public $agenzie, $lookup, $annunci;
        public $lookupArray = [];

        public function File($file, $idAgenzia, $idSito) {

            $zipName = basename($file, ".zip");;

            $TMP_ZIP = $zipName.'.zip';

            $TMP_XML_AGENZIE = $idAgenzia.'_'.$idSito.'_agenzie.xml';
            $TMP_XML_LOOKUP = $idSito.'_lookup.xml';
            $TMP_XML_ANNUNCI = $idAgenzia.'_'.$idSito.'_annunci.xml';

            if (copy($file, $TMP_ZIP)) {

                $zip = new ZipArchive;
                $unzipped = $zip->open($TMP_ZIP);

                if ($unzipped === true) {
                    
                    # Estraggo il file zip
                    $zip->extractTo(getcwd());
                    $zip->close();
                        
                    # Trasformo i file XML in un'array
                    $this->agenzie = simplexml_load_file($TMP_XML_AGENZIE, null, LIBXML_NOCDATA)->children();
                    $this->lookup = simplexml_load_file($TMP_XML_LOOKUP, null, LIBXML_NOCDATA)->children();
                    $this->annunci = simplexml_load_file($TMP_XML_ANNUNCI, null, LIBXML_NOCDATA)->children();

                    # Creo l'array con le specifiche dei lookup
                    $this->LookupLoad();

                    # Elimino lo zip e xml
                    unlink($TMP_ZIP);
                    unlink($TMP_XML_AGENZIE);
                    unlink($idSito.'_agenzie.xml');
                    unlink($TMP_XML_LOOKUP);
                    unlink($idAgenzia.'_'.$idSito.'_lookup.xml');
                    unlink($TMP_XML_ANNUNCI);
                        
                }

            }

        }

        public function Immobili() {

            $annunci = json_decode(json_encode($this->annunci), true);

            return $annunci['immobili']['immobile'];

        }

        public function Tipologie($file) {

            $tipologie = simplexml_load_file($file, null, LIBXML_NOCDATA)->children();
            $tipologie = json_decode(json_encode($tipologie), true);

            return $tipologie['categoria'];

        }

        private function LookupLoad() {

            # Questo script genera un array con il valore dei campi
            $lookup = json_decode(json_encode($this->lookup), true);

            $CAMPI = [];

            foreach ($lookup['campi']['campo'] as $key => $campo) {
                
                $id = $campo['id'];
                $CAMPI[$id] = [];
                $CAMPI[$id]['nome'] = $campo['nome_campo'];
                $CAMPI[$id]['tipo'] = $campo['tipo'];
                $CAMPI[$id]['valori'] = [];

            }

            foreach ($lookup['valore_campi']['valore_campo'] as $key => $valore) {
                
                $campoId = $valore['id_campo'];
                $CAMPI[$campoId]['valori'][$valore['id_valore']] = $valore['testo'];

            }
            
            foreach ($CAMPI as $key => $value) {
                
                if (!empty($value['valori'])) {
                    $k = $value['nome'];
                    $this->lookupArray[$k] = $value['valori'];
                }

            }

        }

        public function LookupValue($column, $key) {

            $key = is_array($key) ? "" : $key;

            return isset($this->lookupArray[$column][$key]) ? $this->lookupArray[$column][$key] : "";

        }

    }