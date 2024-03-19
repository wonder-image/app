<?php

    namespace Wonder\Plugin\Getrix;

    class Translate {


        private $BOOLEAN = [
            "1" => 'presente',
            "2" => 'assente'
        ];

        private function __return($array, $key) {

            if (is_null($key)) {
                return $array;
            } else {
                return ucfirst($array[$key]);
            }

        }

        # Categoria
            private $CATEGORIA = [
                "1" => 'Immobili Residenziali',
                "2" => 'Immobili Commerciali',
                "3" => 'Attività Commerciali',
                "4" => 'Case Vacanza',
                "5" => 'Terreni'
            ];

            public function Categoria($key = null) { return $this->__return($this->CATEGORIA, $key); }

            
        # Contratto
            private $CONTRATTO = [
                "V" => 'Vendita',
                "A" => 'Affitto'
            ];

            public function Contratto($key = null) { return $this->__return($this->CONTRATTO, $key); }


        # TipoSpese
            private $TIPO_SPESE = [
                "0" => 'mese',
                "1" => 'anno'
            ];

            public function TipoSpese($key = null) { return $this->__return($this->TIPO_SPESE, $key); }


        # DurataContratto
            private $DURATA_CONTRATTO = [
                "1" => '4 + 4 Anni',
                "2" => '6 + 6',
                "3" => '8 + 8',
                "4" => '12 + 12',
                "5" => 'studentesco',
                "6" => 'uso foresteria',
                "7" => '3 + 2',
                "8" => 'stagionale',
                "9" => 'transitorio',
                "255" => 'Altro'
            ];

            public function DurataContratto($key = null) { return $this->__return($this->DURATA_CONTRATTO, $key); }


        # TipoProprieta
            private $TIPO_PROPRIETA = [
                "1" => 'intera proprietà',
                "2" => 'nuda proprietà',
                "3" => 'parziale proprietà',
                "4" => 'usufrutto',
                "5" => 'multiproprietà',
                "6" => 'diritto di superficie'
            ];

            public function TipoProprieta($key = null) { return $this->__return($this->TIPO_PROPRIETA, $key); }


        # SituazioneImmobile
            private $SITUAZIONE_IMMOBILE = [
                "A" => 'disponibile',
                "B" => 'proposta in corso',
                "C" => 'compromesso',
                "D" => 'venduto',
                "E" => 'locato'
            ];

            public function SituazioneImmobile($key = null) { return $this->__return($this->SITUAZIONE_IMMOBILE, $key); }


        # RESIDENZIALE
            # StatoImmobile
                private $STATO_IMMOBILE = [
                    "1" => 'libero',
                    "2" => 'libero al rogito',
                    "3" => 'occupato dal proprietario',
                    "4" => 'occupato dall\'inquilino',
                    "5" => 'in costruzione',
                    "6" => 'non ancora costruito'
                ];

                public function StatoImmobile($key = null) { return $this->__return($this->STATO_IMMOBILE, $key); }


            # PianoFuoriTerra
                private $PIANO_FUORI_TERRA = [
                    "1" => 'terra',
                    "2" => 'scantinato',
                    "3" => 'seminterrato',
                    "4" => 'rialzato',
                    "5" => 'ammezzato',
                    "6" => 'alto',
                    "7" => 'ultimo piano',
                    "8" => 'intero edificio',
                    "9" => 'interrato'
                ];

                public function PianoFuoriTerra($key = null) { return $this->__return($this->PIANO_FUORI_TERRA, $key); }

                
            # Cucina
                private $CUCINA = [
                    "1" => 'abitabile',
                    "2" => 'angolo cottura',
                    "3" => 'cucinino',
                    "4" => 'semi abitabile',
                    "5" => 'tinello',
                    "6" => 'a vista',
                    "255" => 'non presente'
                ];

                public function Cucina($key = null) { return $this->__return($this->CUCINA, $key); }


            # BoxAuto
                private $BOX_AUTO = [
                    "1" => 'singolo',
                    "2" => 'doppio',
                    "3" => 'triplo',
                    "255" => 'assente'
                ];
                
                public function BoxAuto($key = null) { return $this->__return($this->BOX_AUTO, $key); }
            

            # Boolean
                public function Cantina($key = null) { return $this->__return($this->BOOLEAN, $key); }
                public function Ripostiglio($key = null) { return $this->__return($this->BOOLEAN, $key); }
                public function Soffitta($key = null) { return $this->__return($this->BOOLEAN, $key); }
                public function Mansarda($key = null) { return $this->__return($this->BOOLEAN, $key); }
                public function Taverna($key = null) { return $this->__return($this->BOOLEAN, $key); }
                public function GiardinoPrivato($key = null) { return $this->__return($this->BOOLEAN, $key); }
                public function Lavanderia($key = null) { return $this->__return($this->BOOLEAN, $key); }
                public function Soppalco($key = null) { return $this->__return($this->BOOLEAN, $key); }
                public function TerrenoAnnesso($key = null) { return $this->__return($this->BOOLEAN, $key); }

            # Riscaldamento
                private $RISCALDAMENTO = [
                    "1" => 'autonomo',
                    "2" => 'centralizzato',
                    "255" => 'assente'
                ];

                public function Riscaldamento($key = null) { return $this->__return($this->RISCALDAMENTO, $key); }


            # TipoRiscaldamento
                private $TIPO_RISCALDAMENTO = [
                    "1" => 'metano',
                    "2" => 'gasolio',
                    "3" => 'gpl',
                    "4" => 'pannelli',
                    "5" => 'aria',
                    "6" => 'legna',
                    "7" => 'solare',
                    "8" => 'fotovoltaico',
                    "9" => 'teleriscaldamento',
                    "10" => 'pompa di calore',
                    "11" => 'elettrica',
                    "12" => 'gas',
                    "13" => 'pellet',
                    "255" => 'altro'
                ];
                
                public function TipoRiscaldamento($key = null) { return $this->__return($this->TIPO_RISCALDAMENTO, $key); }


            # Arredamento
                private $ARREDAMENTO = [
                    "1" => 'parziale',
                    "2" => 'completo',
                    "255" => 'assente'
                ];

                public function Arredamento($key = null) { return $this->__return($this->ARREDAMENTO, $key); }


            # StatoArredamento
                private $STATO_ARREDAMENTO = [
                    "1" => 'nuovo',
                    "2" => 'discreto',
                    "3" => 'ottimo',
                    "4" => 'buono'
                ];

                public function StatoArredamento($key = null) { return $this->__return($this->STATO_ARREDAMENTO, $key); }


            # TipoCostruzione
                private $TIPO_COSTRUZIONE = [
                    "1" => 'econimica',
                    "2" => 'civile',
                    "3" => 'medio signorile',
                    "4" => 'signorile',
                    "5" => 'epoca',
                    "6" => 'ringhiera',
                    "7" => 'lusso',
                    "255" => 'civile'
                ];

                public function TipoCostruzione($key = null) { return $this->__return($this->TIPO_COSTRUZIONE, $key); }


            # TipologiaUso
                private $TIPOLOGIA_USO = [
                    "1" => 'commerciale',
                    "2" => 'industriale',
                    "3" => 'commerciale ed industriale'
                ];

                public function TipologiaUso($key = null) { return $this->__return($this->TIPOLOGIA_USO, $key); }


            # StatoManutenzione
                private $STATO_MANUTENZIONE = [
                    "1" => 'nuovo',
                    "2" => 'buono',
                    "3" => 'ristrutturato',
                    "4" => 'mediocre',
                    "5" => 'da ristrutturare',
                    "6" => 'ottimo',
                    "7" => 'discreto'
                ];
                
                public function StatoManutenzione($key = null) { return $this->__return($this->STATO_MANUTENZIONE, $key); }


            # StatoCostruzione
                private $STATO_COSTRUZIONE = [
                    "1" => 'nuovo',
                    "2" => 'buono',
                    "3" => 'ristrutturato',
                    "4" => 'mediocre',
                    "5" => 'da ristrutturare',
                    "6" => 'ottimo',
                    "7" => 'discreto'
                ];
                
                public function StatoCostruzione($key = null) { return $this->__return($this->STATO_COSTRUZIONE, $key); }


            # Impianto
                private $IMPIANTO = [
                    "1" => 'centralizzato',
                    "2" => 'singolo',
                    "255" => 'assente'
                ];

                public function ImpiantoTV($key = null) { return $this->__return($this->IMPIANTO, $key); }
                public function ImpiantoSatellitare($key = null) { return $this->__return($this->IMPIANTO, $key); }


            # Barriere
                private $BARRIERE = [
                    "1" => 'barriere architettoniche assenti',
                    "2" => 'barriere architettoniche presenti'
                ];

                public function Barriere($key = null) { return $this->__return($this->BARRIERE, $key); }

                
            # Tapparelle
                private $TAPPARELLE = [
                    "1" => 'plastica',
                    "2" => 'legno',
                    "3" => 'metallo'
                ];
                
                public function Tapparelle($key = null) { return $this->__return($this->TAPPARELLE, $key); }

                
            # Persiane
                private $PERSIANE = [
                    "1" => 'plastica',
                    "2" => 'legno',
                    "3" => 'metallo'
                ];

                public function Persiane($key = null) { return $this->__return($this->PERSIANE, $key); }

                
            # InfissiEsterni
                private $INFISSI_ESTERNI = [
                    "1" => 'vetro/plastica',
                    "2" => 'vetro/legno',
                    "3" => 'vetro/metallo',
                    "4" => 'doppio vetro/plastica',
                    "5" => 'doppio vetro/legno',
                    "6" => 'doppio vetro/metallo'
                ];
                
                public function InfissiEsterni($key = null) { return $this->__return($this->INFISSI_ESTERNI, $key); }

                
            # LeggeClasseEnergetica
                private $LEGGE_CLASSE_ENERGETICA = [
                    "0" => 'DL 192/2005',
                    "1" => 'Legge 90/2013'
                ];
                
                public function LeggeClasseEnergetica($key = null) { return $this->__return($this->LEGGE_CLASSE_ENERGETICA, $key); }

                
            # EdificioEnergia
                private $EDIFICIO_ENERGIA = [
                    "1" => 'bassa',
                    "5" => 'media',
                    "10" => 'alta'
                ];

                public function EdificioEnergiaEstate($key = null) { return $this->__return($this->EDIFICIO_ENERGIA, $key); }
                public function EdificioEnergiaInverno($key = null) { return $this->__return($this->EDIFICIO_ENERGIA, $key); }

                
            # Orientamento
                private $ORIENTAMENTO = [
                    "1" => 'nord',
                    "2" => 'nord-est',
                    "3" => 'est',
                    "4" => 'sud-est',
                    "5" => 'sud',
                    "6" => 'sud-ovest',
                    "7" => 'ovest',
                    "8" => 'nord-ovest',
                    "9" => 'nord-ovest-sud-est',
                    "10" => 'nord-ovest-sud',
                    "11" => 'nord-est-sud',
                    "12" => 'est-nord-ovest',
                    "13" => 'est-sud-ovest',
                    "14" => 'sud-nord',
                    "15" => 'ovest-est'
                ];

                public function Orientamento($key = null) { return $this->__return($this->ORIENTAMENTO, $key); }


            # Pavimento
                private $PAVIMENTO = [
                    "1" => 'ceramica',
                    "2" => 'marmo',
                    "3" => 'cotto',
                    "4" => 'marmette',
                    "5" => 'granito',
                    "6" => 'linoleum',
                    "7" => 'moquette',
                    "8" => 'parquet',
                    "9" => 'cemento'
                ];

                public function Pavimentazione($key = null) { return $this->__return($this->PAVIMENTO, $key); }
                public function PavimentoCamere($key = null) { return $this->__return($this->PAVIMENTO, $key); }
                public function PavimentoCucina($key = null) { return $this->__return($this->PAVIMENTO, $key); }
                public function PavimentoSoggiorno($key = null) { return $this->__return($this->PAVIMENTO, $key); }
                public function PavimentoBagno($key = null) { return $this->__return($this->PAVIMENTO, $key); }
                public function PavimentoAltreCamere($key = null) { return $this->__return($this->PAVIMENTO, $key); }


            # AcquaCalda
                private $ACQUA_CALDA = [
                    "1" => 'centralizzata',
                    "2" => 'autonoma',
                    "255" => 'assente'
                ];
                
                public function AcquaCalda($key = null) { return $this->__return($this->ACQUA_CALDA, $key); }


            # StatoSerramenti
                private $STATO_SERRAMENTI = [
                    "1" => 'nuovo',
                    "2" => 'ottimo',
                    "3" => 'buono',
                    "4" => 'discreto',
                    "5" => 'mediocre',
                    "6" => 'da sostituire'
                ];

                public function StatoSerramenti($key = null) { return $this->__return($this->STATO_SERRAMENTI, $key); }
            

            # Panoramico
                private $PANORAMICO = [
                    "1" => 'si',
                    "2" => 'no'
                ];
                
                public function Panoramico($key = null) { return $this->__return($this->PANORAMICO, $key); }


            # TipoFacciata
                private $TIPO_FACCIATA = [
                    "1" => 'blocchi a vista',
                    "2" => 'cemento armato a vista',
                    "3" => 'intonaco civile',
                    "4" => 'intonaco rustico',
                    "5" => 'mattoni a vista',
                    "6" => 'travertino'
                ];
                
                public function TipoFacciata($key = null) { return $this->__return($this->TIPO_FACCIATA, $key); }


            # Vista
                private $VISTA = [
                    "1" => 'mare',
                    "2" => 'lago',
                    "3" => 'montagna'
                ];
                
                public function Vista($key = null) { return $this->__return($this->VISTA, $key); }


            # Collaborazione
                private $COLLABORAZIONE = [
                    "1" => 'si',
                    "2" => 'no'
                ];
                
                public function Collaborazione($key = null) { return $this->__return($this->COLLABORAZIONE, $key); }

            
        # COMMERCIALE
                
            # StatoNuovaCostruzione
                private $STATO_NUOVA_COSTRUZIONE = [
                    "1" => 'già pronto',
                    "2" => 'in costruzione',
                    "3" => 'da rifinire'
                ];
                
                public function StatoNuovaCostruzione($key = null) { return $this->__return($this->STATO_NUOVA_COSTRUZIONE, $key); }
            

            # Porzione
                private $PORZIONE = [
                    "1" => 'complesso',
                    "2" => 'indipendente'
                ];

                public function Porzione($key = null) { return $this->__return($this->PORZIONE, $key); }

            # SettoreMerceologico
                private $SETTORE_MERCEOLOGICO = [
                    "1" => 'industriale',
                    "2" => 'produzione',
                    "3" => 'commerciale - vendita ingrosso',
                    "4" => 'commerciale - vendita dettaglio',
                    "5" => 'produzione e commerciale',
                    "6" => 'servizi'
                ];

                public function SettoreMerceologico($key = null) { return $this->__return($this->SETTORE_MERCEOLOGICO, $key); }

            
            # TipoStruttura
                private $TIPO_STRUTTURA = [
                    "1" => 'prefabbricato',
                    "2" => 'cemento',
                    "3" => 'metallo',
                    "4" => 'vetrata'
                ];

                public function TipoStruttura($key = null) { return $this->__return($this->TIPO_STRUTTURA, $key); }


            # TipoStruttura
                private $ILLUMINAZIONE = [
                    "1" => 'tradizionale',
                    "2" => 'fluorescenza',
                    "3" => 'alogena'
                ];

                public function Illuminazione($key = null) { return $this->__return($this->ILLUMINAZIONE, $key); }


            # Boolean
                public function Allarme($key = null) { return $this->__return($this->BOOLEAN, $key); }
                public function Antincendio($key = null) { return $this->__return($this->BOOLEAN, $key); }
                public function BagniDisabili($key = null) { return $this->__return($this->BOOLEAN, $key); }
                public function Seminterrato($key = null) { return $this->__return($this->BOOLEAN, $key); }


            # TipoCondizionamento
                private $TIPO_CONDIZIONAMENTO = [
                    "1" => 'a tutta aria (portata costante)',
                    "2" => 'a tutta aria (portata variabile)',
                    "3" => 'misti a ventilconvettori',
                    "4" => 'misti ad induttori',
                    "5" => 'misti a soffitti freddi',
                    "6" => 'a espansione diretta',
                    "7" => 'ad acqua'
                ];

                public function TipoCondizionamento($key = null) { return $this->__return($this->TIPO_CONDIZIONAMENTO, $key); }


        # ATTIVITÀ

            # Boolean
                public function AreaFumatori($key = null) { return $this->__return($this->BOOLEAN, $key); }


        # TERRENO

            # TipoLotti
                private $TIPO_LOTTI = [
                    "1" => 'corpo unico',
                    "2" => 'lotti confinanti',
                    "3" => 'lotti non confinanti'
                ];

                public function TipoLotti($key = null) { return $this->__return($this->TIPO_LOTTI, $key); }


            # MorfologiaTerritorio
                private $MORFOLOGIA_TERRITORIO = [
                    "1" => 'pianeggiante',
                    "2" => 'collinare',
                    "3" => 'terrazzato',
                    "4" => 'roccioso'
                ];

                public function MorfologiaTerritorio($key = null) { return $this->__return($this->MORFOLOGIA_TERRITORIO, $key); }


            # StatoCostruzioneAbitativa - StatoCostruzioneAgricola - StatoCostruzioneCommerciale
                private $STATO_COSTRUZIONE_ABITATIVA = [
                    "1" => 'rudere',
                    "2" => 'incompleto'
                ];

                public function StatoCostruzioneAbitativa($key = null) { return $this->__return($this->STATO_COSTRUZIONE_ABITATIVA, $key); }
                public function StatoCostruzioneAgricola($key = null) { return $this->__return($this->STATO_COSTRUZIONE_ABITATIVA, $key); }
                public function StatoCostruzioneCommerciale($key = null) { return $this->__return($this->STATO_COSTRUZIONE_ABITATIVA, $key); }


        # VACANZE

            # Ingresso
                private $INGRESSO = [
                    "1" => 'indipendente',
                    "2" => 'comune'
                ];

                public function Ingresso($key = null) { return $this->__return($this->INGRESSO, $key); }


            # Ingresso
                private $TIPO_LOCALITA_PRINCIPALE = [
                    "1" => 'mare',
                    "2" => 'lago',
                    "3" => 'montagna',
                    "4" => 'terme',
                    "5" => 'collina',
                    "6" => 'città d\'arte',
                    "7" => 'fiume',
                    "8" => 'impianti sportivi'
                ];

                public function TipoLocalitaPrincipale($key = null) { return $this->__return($this->TIPO_LOCALITA_PRINCIPALE, $key); }

                
        # IMMAGINI

            # Tipo
                private $TIPO = [
                    "F" => 'fotografia',
                    "P" => 'piantina'
                ];

                public function Tipo($key = null) { return $this->__return($this->TIPO, $key); }

    }