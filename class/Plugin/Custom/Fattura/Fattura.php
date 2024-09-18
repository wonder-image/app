<?php

    # Documentazione: [ https://www.fatturapa.gov.it/export/documenti/Specifiche_tecniche_del_formato_FatturaPA_V1.3.2.pdf ]

    namespace Wonder\Plugin\Custom\Fattura;
    use Wonder\Plugin\Custom\Fattura\Valori\{
        Natura, RegimiFiscali, TipiDocumento, CondizioniPagamento, Pagamento
    };

    use DOMDocument;

    class Fattura {

        public const XMLNS = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';

        public const ID_PAESE = "IT";
        public const PROGRESSIVO_INVIO = 1;
        public const CODICE_DESTINATARIO = "0000000";

        public const FORMATO_TRASMISSIONE = 'FPR12';
        public const TIPO_DOCUMENTO = "TD01";
        public const CONDIZIONI_PAGAMENTO = "TP02";

        public const VALUTA = "EUR";
        public const ESIGIBILITA_IVA = 'D';

        public const VALORI_NATURA = Natura::Valori;

        public $default;
        public $file;
        public $header;
        public $body;

        public function __construct($ProgressivoInvio = self::PROGRESSIVO_INVIO, $Data = null, $TipoDocumento = self::TIPO_DOCUMENTO){

            $this->file = [];
            $this->file['FatturaElettronica'] = [];
            $this->file['FatturaElettronica']['attributes'] = [
                "versione" => self::FORMATO_TRASMISSIONE,
                "xmlns" => self::XMLNS
            ];

            $this->file['FatturaElettronica']['child'] = [];
            
            $this->header = [];
            $this->body = [];

            $this->default['TipoDocumento'] = $TipoDocumento;
            $this->default['Divisa'] = self::VALUTA;
            $this->default['Data'] = ($Data == null) ? date("Y-m-d") : $Data;
            
            $this->default['IdPaese'] = self::ID_PAESE;
            $this->default['FormatoTrasmissione'] = self::FORMATO_TRASMISSIONE;
            $this->default['EsigibilitaIVA'] = self::ESIGIBILITA_IVA;
            $this->default['CondizioniPagamento'] = self::CONDIZIONI_PAGAMENTO;

            $this->default['ProgressivoInvio'] = $ProgressivoInvio;
            $this->default['CodiceDestinatario'] = self::CODICE_DESTINATARIO;

            $this->default['NumeroLinea'] = 1;

            $this->default['Imponibile'] = 0;
            $this->default['Imposta'] = 0;

            $this->default['Sconto'] = 0;
            $this->default['Maggiorazione'] = 0;
            $this->default['Arrotondamento'] = 0;

            $this->default['Bollo'] = 0;

            $this->DatiGenerali();

        }

        # Setta variabili

            /**  @param string $IdPaese Sigla ISO 3166-1 alpha-2 code */ 
            public function IdPaese($IdPaese = self::ID_PAESE) { $this->default['IdPaese'] = strtoupper($IdPaese); }

            /**  @param string $codiceTrasmissione FPA12 | FPR12 */ 
            public function FormatoTrasmissione($FormatoTrasmissione = self::FORMATO_TRASMISSIONE) { 

                $this->default['FormatoTrasmissione'] = strtoupper($FormatoTrasmissione); 
                $this->DatiGenerali();

            }

            public function DataDocumento($DataDocumento) { 

                $this->default['Data'] = date("Y-m-d", strtotime($DataDocumento)); 
                $this->DatiGenerali();

            }
            
            public function CodiceDestinatario($CodiceDestinatario = self::CODICE_DESTINATARIO) { $this->default['CodiceDestinatario'] = strtoupper($CodiceDestinatario); }

            /** @param string $Valuta ISO 4217 alpha-3:2001 */
            public function Valuta($Valuta = self::VALUTA) { 

                $this->default['Divisa'] = strtoupper($Valuta); 
                $this->DatiGenerali();

            }

            public function CondizioniPagamento($CondizioniPagamento = self::CONDIZIONI_PAGAMENTO) { 

                $this->default['CondizioniPagamento'] = strtoupper($CondizioniPagamento); 
                $this->DatiGenerali();

            }

            /**  @param string $codiceTrasmissione I | D | S */ 
            public function EsigibilitaIVA($EsigibilitaIVA = self::ESIGIBILITA_IVA) { $this->default['EsigibilitaIVA'] = strtoupper($EsigibilitaIVA); }
            
        # Array utili
            public function RegimeFiscale($CodiceRegime = null) { return ($CodiceRegime == null) ? RegimiFiscali::Valori : RegimiFiscali::Valori[$CodiceRegime]; }

            public function TipoDocumento($CodiceDocumento = null) { return ($CodiceDocumento == null) ? TipiDocumento::Valori : TipiDocumento::Valori[$CodiceDocumento]; }

            public function Natura($CodiceNatura = null) { return ($CodiceNatura == null) ? Natura::Valori : Natura::Valori[$CodiceNatura]; }

            public function CondizionePagamento($CondizionePagamento = null) { return ($CondizionePagamento == null) ? CondizioniPagamento::Valori : CondizioniPagamento::Valori[$CondizionePagamento]; }

            public function Pagamento($CodicePagamento = null) { return ($CodicePagamento == null) ? Pagamento::Valori : Pagamento::Valori[$CodicePagamento]; }


        # FATTURAELETTRONICAHEADER

            public function Contatti($Telefono = null, $Fax = null, $Email = null) {
                
                $Contatti = [];
                
                if ($Telefono != null) { $Contatti['Telefono'] = str_replace(' ', '', $Telefono); }
                if ($Fax != null) { $Contatti['Fax'] = str_replace(' ', '', $Fax); }
                if ($Email != null) { $Contatti['Email'] = str_replace(' ', '', $Email); }
                
                return $Contatti;

            }

            /**
             * Indirizzo
             *
             * @param  mixed $Via Alfanumerico Max 60 caratteri
             * @param  mixed $NumeroCivico Alfanumerico Max 8 caratteri
             * @param  int $CAP Numerico Max 8 caratteri
             * @param  mixed $Comune Alfanumerico Max 60 caratteri
             * @param  mixed $Provincia Sigla Alfanumerico Max 2 caratteri
             * @param  mixed $Nazione Sigla ISO 3166-1 alpha-2 code
             * @return array
             * 
             */
            public function Indirizzo($Via, $NumeroCivico, $CAP, $Comune, $Provincia, $Nazione) {
                
                $Indirizzo = [];

                $Indirizzo['Indirizzo'] = $Via;
                $Indirizzo['NumeroCivico'] = $NumeroCivico;
                $Indirizzo['CAP'] = $CAP;
                $Indirizzo['Comune'] = $Comune;
                $Indirizzo['Provincia'] = strtoupper($Provincia);
                $Indirizzo['Nazione'] = strtoupper($Nazione);
                
                return $Indirizzo;

            }
        
            /**
             * IscrizioneREA
             *
             * @param  mixed $Ufficio Alfanumerico Max 2 caratteri
             * @param  mixed $NumeroREA Alfanumerico Max 20 caratteri
             * @param  mixed $CapitaleSociale Numerico number_format($CapitaleSociale, 2, '.', '') Tra 4-15 caratteri
             * @param  bool $SocioUnico
             * @param  bool $StatoLiquidazione
             * @return array
             * 
             */
            public function IscrizioneREA($Ufficio, $NumeroREA, $CapitaleSociale, $SocioUnico = false, $StatoLiquidazione = false) {

                $REA = [];

                $REA['Ufficio'] = $Ufficio;
                $REA['NumeroREA'] = $NumeroREA;
                $REA['CapitaleSociale'] = $CapitaleSociale;
                $REA['SocioUnico'] = ($SocioUnico == true) ? 'SU' : 'SE';
                $REA['StatoLiquidazione'] = ($StatoLiquidazione == true) ? 'LS' : 'LN';
                
                return $REA;

            }

            public function IscrizioneAlbo($Nome, $Provincia, $NumeroIscrizione, $Descrizione = '') {
                
                $Albo = [];

                $Albo['AlboProfessionale'] = $Nome;
                $Albo['ProvinciaAlbo'] = $Provincia;
                $Albo['NumeroIscrizioneAlbo'] = $NumeroIscrizione;

                if (!empty($Descrizione)) { $Albo['DescrizioneAlbo'] = $Descrizione; }
                
                return $Albo;

            } 
            
            public function AnagraficaAzienda($Denominazione, $CodEORI = null) {
                
                $Anagrafica = [];
                $Anagrafica['Denominazione'] = $Denominazione;

                if ($CodEORI != null) { $Anagrafica['CodEORI'] = $CodEORI; }

                
                return $Anagrafica;

            }

            public function AnagraficaPrivato($Nome, $Cognome, $Titolo = null, $CodEORI = null) {
                
                $Anagrafica = [];

                if ($CodEORI != null) { $Anagrafica['CodEORI'] = $CodEORI; }

                $Anagrafica['Nome'] = $Nome;
                $Anagrafica['Cognome'] = $Cognome;

                if ($Titolo != null) { $Anagrafica['Titolo'] = $Titolo; }
                
                return $Anagrafica;

            }
            
            public function IdTrasmittente($IdCodice, $IdPaese = null) {

                $IdTrasmittente = [];

                $IdTrasmittente['IdPaese'] = $IdPaese == null ? $this->default['IdPaese'] : $IdPaese;
                $IdTrasmittente['IdCodice'] = $IdCodice;
                
                return $IdTrasmittente;
                
            }

            public function IdFiscaleIVA($IdCodice, $IdPaese = null) {

                $IdFiscaleIVA['IdFiscaleIVA'] = [];
                $IdFiscaleIVA['IdFiscaleIVA']['IdPaese'] = $IdPaese == null ? $this->default['IdPaese'] : $IdPaese;
                $IdFiscaleIVA['IdFiscaleIVA']['IdCodice'] = $IdCodice;
                
                return $IdFiscaleIVA;
                
            }

            public function DatiAnagrafici($DatiFiscali, $Anagrafica, $RegimeFiscale, $CodiceFiscale = null, $Albo = []) {

                $DatiAnagrafici = [];
                
                $DatiAnagrafici = array_merge($DatiAnagrafici, $DatiFiscali);
                if (!empty($CodiceFiscale)) { $DatiAnagrafici['CodiceFiscale'] = $CodiceFiscale; }
                $DatiAnagrafici['Anagrafica'] = $Anagrafica;

                if (!empty($Albo)) { $DatiAnagrafici = array_merge($DatiAnagrafici, $Albo); }
                if ($RegimeFiscale != null) { $DatiAnagrafici['RegimeFiscale'] = $RegimeFiscale; }

                return $DatiAnagrafici;

            }

            public function DatiTrasmissione($PartitaIVA, $CodiceDestinatario = '', $PECDestinatario = '', $Telefono = '', $Email = '') {

                $DatiTrasmissione = [];
                $DatiTrasmissione['IdTrasmittente'] = $this->IdTrasmittente($PartitaIVA);
                $DatiTrasmissione['ProgressivoInvio'] = $this->default['ProgressivoInvio'];
                $DatiTrasmissione['FormatoTrasmissione'] = $this->default['FormatoTrasmissione'];

                $DatiTrasmissione['CodiceDestinatario'] = empty($CodiceDestinatario) ? $this->default['CodiceDestinatario'] : $CodiceDestinatario;
                
                if (!empty($PECDestinatario)) { $DatiTrasmissione['PECDestinatario'] = $PECDestinatario; }


                if (!empty($Telefono) || !empty($Email)) {

                    $DatiTrasmissione['ContattiTrasmittente'] = [];

                    if (!empty($Telefono)) { $DatiTrasmissione['ContattiTrasmittente']['Telefono'] = $Telefono; }
                    if (!empty($Email)) { $DatiTrasmissione['ContattiTrasmittente']['Email'] = $Email; }

                }

                $this->header['DatiTrasmissione'] = $DatiTrasmissione;

            }

            public function CedentePrestatore($DatiAnagrafici, $SedeLegale, $Contatti = '', $RiferimentoAmministrativo = '', $StabileOrganizzazione = '', $IscrizioneRea = '') {

                $CedentePrestatore = [];
                $CedentePrestatore['DatiAnagrafici'] = $DatiAnagrafici;
                $CedentePrestatore['Sede'] = $SedeLegale;

                if (!empty($Contatti)) { $CedentePrestatore['Contatti'] = $Contatti; }
                if (!empty($RiferimentoAmministrativo)) { $CedentePrestatore['RiferimentoAmministrativo'] = $RiferimentoAmministrativo; }
                if (!empty($StabileOrganizzazione)) { $CedentePrestatore['StabileOrganizzazione'] = $StabileOrganizzazione; }
                if (!empty($IscrizioneRea)) { $CedentePrestatore['IscrizioneRea'] = $IscrizioneRea; }
                
                $this->header['CedentePrestatore'] = $CedentePrestatore;

            }

            public function RappresentanteFiscale($DatiAnagrafici) {

                $RappresentanteFiscale = [];
                $RappresentanteFiscale['DatiAnagrafici'] = $DatiAnagrafici;

                return $RappresentanteFiscale;

            }

            public function CessionarioCommittente($DatiAnagrafici, $SedeLegale, $RappresentanteFiscale = '', $StabileOrganizzazione = '') {

                $CessionarioCommittente = [];
                $CessionarioCommittente['DatiAnagrafici'] = $DatiAnagrafici;
                $CessionarioCommittente['Sede'] = $SedeLegale;

                if (!empty($StabileOrganizzazione)) { $CessionarioCommittente['StabileOrganizzazione'] = $StabileOrganizzazione; }
                if (!empty($RappresentanteFiscale)) { $CessionarioCommittente['RappresentanteFiscale'] =$RappresentanteFiscale; }

                $this->header['CessionarioCommittente'] = $CessionarioCommittente;

            }

            public function TerzoIntermediarioOSoggettoEmittente($DatiAnagrafici) {

                $TerzoIntermediarioOSoggettoEmittente = [];
                $TerzoIntermediarioOSoggettoEmittente['DatiAnagrafici'] = $DatiAnagrafici;

                $this->header['TerzoIntermediarioOSoggettoEmittente'] = $TerzoIntermediarioOSoggettoEmittente;

            }


        # FATTURAELETTRONICABADY
            public function DatiGenerali() {

                $this->body['DatiGenerali'] = [];

                $this->body['DatiGenerali']['DatiGeneraliDocumento'] = [];
                $this->body['DatiGenerali']['DatiGeneraliDocumento']['TipoDocumento'] = $this->default['TipoDocumento'];
                $this->body['DatiGenerali']['DatiGeneraliDocumento']['Divisa'] = $this->default['Divisa'];

                $this->body['DatiGenerali']['DatiGeneraliDocumento']['Data'] = $this->default['Data'];
                $this->body['DatiGenerali']['DatiGeneraliDocumento']['Numero'] = $this->default['ProgressivoInvio'];
                
                $this->body['DatiBeniServizi'] = [];

                $this->body['DatiBeniServizi']['DettaglioLinee'] = [];
                $this->body['DatiBeniServizi']['DettaglioLinee']['array'] = true;
                $this->body['DatiBeniServizi']['DettaglioLinee']['child'] = [];

                $this->body['DatiBeniServizi']['DatiRiepilogo'] = [];
                $this->body['DatiBeniServizi']['DatiRiepilogo']['array'] = true;
                $this->body['DatiBeniServizi']['DatiRiepilogo']['child'] = [];

                $this->body['DatiPagamento'] = [];
                $this->body['DatiPagamento']['CondizioniPagamento'] = $this->default['CondizioniPagamento'];

                $this->body['DatiPagamento']['DettaglioPagamento'] = [];
                $this->body['DatiPagamento']['DettaglioPagamento']['array'] = true;
                $this->body['DatiPagamento']['DettaglioPagamento']['child'] = [];


            }

            public function Bollo($ImportoBollo) {

                $ImportoBollo = number_format($ImportoBollo, 2, '.', '');

                if ($ImportoBollo > 0) {

                    $DatiBollo = [];

                    $DatiBollo['BolloVirtuale'] = 'SI';
                    $DatiBollo['ImportoBollo'] = $ImportoBollo;

                    $this->body['DatiGenerali']['DatiGeneraliDocumento']['DatiBollo'] = $DatiBollo;

                }

                $this->default['Bollo'] = $ImportoBollo;

                $this->Totale();
            
            }

            private function DatiRiepilogo($AliquotaIVA, $Natura, $ImponibileImporto) {

                $DatiRiepilogo = [];

                $ImponibileImporto = number_format($ImponibileImporto, 2, '.', '');
                $AliquotaIVA = number_format($AliquotaIVA, 2, '.', '');

                if ($AliquotaIVA > 0) {

                    $key = array_search($AliquotaIVA, array_column($this->body['DatiBeniServizi']['DatiRiepilogo']['child'], 'AliquotaIVA'));

                } else {

                    $key = array_search($Natura, array_column($this->body['DatiBeniServizi']['DatiRiepilogo']['child'], 'Natura'));

                }

                if (!empty($key) || $key === 0) {

                    $DatiRiepilogo = $this->body['DatiBeniServizi']['DatiRiepilogo']['child'][$key];

                    $DatiRiepilogo['ImponibileImporto'] = $DatiRiepilogo['ImponibileImporto'] + $ImponibileImporto;

                    $DatiRiepilogo['Imposta'] = number_format($DatiRiepilogo['ImponibileImporto'] * ($AliquotaIVA / 100), 2, '.', '');

                    $this->body['DatiBeniServizi']['DatiRiepilogo']['child'][$key] = $DatiRiepilogo;

                } else {

                    $DatiRiepilogo = [];

                    $DatiRiepilogo['AliquotaIVA'] = $AliquotaIVA;

                    if ($AliquotaIVA == 0) { $DatiRiepilogo['Natura'] = $Natura; }

                    $DatiRiepilogo['ImponibileImporto'] = $ImponibileImporto;

                    $DatiRiepilogo['Imposta'] = number_format($ImponibileImporto * ($AliquotaIVA / 100), 2, '.', '');

                    if ($AliquotaIVA == 0) {
                        $DatiRiepilogo['RiferimentoNormativo'] = $this->Natura($Natura);
                    } else {
                        $DatiRiepilogo['EsigibilitaIVA'] = $this->default['EsigibilitaIVA'];
                    }

                    array_push($this->body['DatiBeniServizi']['DatiRiepilogo']['child'], $DatiRiepilogo);

                }

                $this->default['Imponibile'] = number_format($this->default['Imponibile'] + $ImponibileImporto, 2, '.', '');
                
                $Imposta = number_format($ImponibileImporto * ($AliquotaIVA / 100), 2, '.', '');
                $this->default['Imposta'] = number_format($this->default['Imposta'] + $Imposta, 2, '.', '');

                $this->Totale();

            }

            public function Sconto($Importo, $Simbolo = "valuta") {

                $ScontoMaggiorazione = [];

                $ScontoMaggiorazione['Tipo'] = 'SC';

                $Importo = number_format($Importo, 2, '.', '');

                if ($Simbolo == 'valuta') {
                    $ScontoMaggiorazione['Importo'] = $Importo;
                } elseif ($Simbolo == 'percentuale') {
                    $ScontoMaggiorazione['Percentuale'] = $Importo;
                }

                return $ScontoMaggiorazione;

            }

            public function ScontoTotale($Importo) {

                $this->default['Sconto'] = number_format($this->default['Sconto'] + $Importo, 2, '.', '');

                if ($this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione']['array'] == true) {
                    
                    array_push($this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione']['child'], $this->Sconto($Importo, 'valuta'));
                
                } else {

                    $this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione'] = [];

                    $this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione']['array'] = true;
                    $this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione']['child'] = [];
                    array_push($this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione']['child'], $this->Sconto($Importo, 'valuta'));

                }

                $this->Totale();

            }

            public function Maggiorazione($Importo, $Simbolo = "valuta") {

                $ScontoMaggiorazione = [];

                $ScontoMaggiorazione['Tipo'] = 'MG';

                $Importo = number_format($Importo, 2, '.', '');

                if ($Simbolo == 'valuta') {
                    $ScontoMaggiorazione['Importo'] = $Importo;
                } elseif ($Simbolo == 'percentuale') {
                    $ScontoMaggiorazione['Percentuale'] = $Importo;
                }

                return $ScontoMaggiorazione;

            }

            public function MaggiorazioneTotale($Importo) {

                $this->default['Maggiorazione'] = number_format($this->default['Maggiorazione'] + $Importo, 2, '.', '');

                if ($this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione']['array'] == true) {
                    
                    array_push($this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione']['child'], $this->Maggiorazione($Importo, 'valuta'));
                
                } else {

                    $this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione'] = [];

                    $this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione']['array'] = true;
                    $this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione']['child'] = [];
                    array_push($this->body['DatiGenerali']['DatiGeneraliDocumento']['ScontoMaggiorazione']['child'], $this->Maggiorazione($Importo, 'valuta'));

                }

                $this->Totale();

            }

            public function Arrotondamento($Importo) {

                $this->default['Arrotondamento'] = number_format($Importo, 3, '.', '');

                $this->body['DatiGenerali']['DatiGeneraliDocumento']['Arrotondamento'] = $Importo;

                $this->Totale();

            }

            public function Causale($Causale) {

                $this->body['DatiGenerali']['DatiGeneraliDocumento']['Causale'] = $Causale;

            }

            public function Linea($RiferimentoAmministrazione, $Descrizione, $Quantita, $PrezzoUnitario, $AliquotaIVA, $Natura = "", $ScontoMaggiorazione = []) {

                $Linea = [];

                $Linea['NumeroLinea'] = $this->default['NumeroLinea'];
                $Linea['Descrizione'] = $Descrizione;

                $Linea['Quantita'] = number_format($Quantita, 2, '.', '');
                $Linea['PrezzoUnitario'] = number_format($PrezzoUnitario, 3, '.', '');

                $PrezzoTotale = number_format($PrezzoUnitario * $Quantita, 3, '.', '');

                if (!empty($ScontoMaggiorazione)) { 

                    $Linea['ScontoMaggiorazione'] = $ScontoMaggiorazione; 
                    
                    if (isset($ScontoMaggiorazione['Importo'])) {

                        $ImportoSconto = number_format($ScontoMaggiorazione['Importo'] * $Quantita, 3, '.', '');

                    } else if (isset($ScontoMaggiorazione['Percentuale'])) {

                        $ImportoSconto = number_format(($PrezzoTotale * ($ScontoMaggiorazione['Percentuale'] / 100)), 3, '.', '');
                        $ImponibileImporto = number_format($PrezzoTotale - $ImportoSconto, 3, '.', '');
                        
                    }

                    if ($ScontoMaggiorazione['Tipo'] == "SC") {
                        $ImponibileImporto = number_format($PrezzoTotale - $ImportoSconto, 3, '.', '');
                    } else if ($ScontoMaggiorazione['Tipo'] == "MG") {
                        $ImponibileImporto = number_format($PrezzoTotale + $ImportoSconto, 3, '.', '');
                    }

                } else {

                    $ImponibileImporto = $PrezzoTotale;

                }

                $Linea['PrezzoTotale'] = number_format($ImponibileImporto, 3, '.', '');

                $Linea['AliquotaIVA'] = number_format($AliquotaIVA, 2, '.', '');

                if (!empty($Natura)) { $Linea['Natura'] = $Natura; }

                $Linea['RiferimentoAmministrazione'] = $RiferimentoAmministrazione;

                $this->default['NumeroLinea']++;

                array_push($this->body['DatiBeniServizi']['DettaglioLinee']['child'], $Linea);

                $this->DatiRiepilogo($AliquotaIVA, $Natura, $ImponibileImporto);

                $this->Totale();

            }

            public function Totale() {

                $Subtotale = $this->default['Imponibile'] + $this->default['Imposta'];

                $Sconto = $this->default['Sconto'];
                $Maggiorazione = $this->default['Maggiorazione'];
                $Arrotondamento = $this->default['Arrotondamento'];

                $Bollo = $this->default['Bollo'];

                $Totale = $Subtotale - $Sconto + $Maggiorazione + $Arrotondamento;
                $Totale += $Bollo;

                $Totale = number_format($Totale, 2, '.', '');

                return $Totale;

            }

            public function DettaglioPagamento($ModalitaPagamento, $ImportoPagamento, $DataScadenzaPagamento = null, $Beneficiario = null, $IstitutoFinanziario = null, $IBAN = null) { 

                $DettaglioPagamento = [];

                if ($Beneficiario != null) { $DettaglioPagamento['Beneficiario'] = $Beneficiario; }

                $DettaglioPagamento['ModalitaPagamento'] = strtoupper($ModalitaPagamento); 

                if ($DataScadenzaPagamento != null) { $DettaglioPagamento['DataScadenzaPagamento'] = $DataScadenzaPagamento; }
                if ($IstitutoFinanziario != null) { $DettaglioPagamento['IstitutoFinanziario'] = $IstitutoFinanziario; }
                if ($IBAN != null) { $DettaglioPagamento['IBAN'] = $IBAN; }
                if ($ImportoPagamento != null) { $DettaglioPagamento['ImportoPagamento'] = number_format($ImportoPagamento, 2, '.', ''); }
                
                array_push($this->body['DatiPagamento']['DettaglioPagamento']['child'], $DettaglioPagamento);

            }


        # Esportazione
            private function arrayToXml($ARRAY, $FILENAME = null, $VERSION = "1.0", $ENCODING = "UTF-8") {

                $XML = new DOMDocument($VERSION, $ENCODING);
        
                $XML->preserveWhiteSpace = true;
                $XML->formatOutput = true;
        
                $XML = $this->createXML($XML, $ARRAY);
        
                if ($FILENAME == null) {
                    
                    return $XML->saveXML();
        
                } else {
        
                    header("Content-Type: text/xml");
                    header("Content-Disposition: attachment; filename=\"$FILENAME.xml\"");
                    header("Pragma: no-cache");
                    header("Expires: 0");
        
                    echo $XML->saveXML();
        
                }
        
            }

            private function createXml($XML, $CHILD, $PARENT = null) {

                if ($PARENT == null) {
                    $PARENT = $XML;
                }
                
                foreach ($CHILD as $NAME => $VALUE) {
                    
                    if (is_array($VALUE)) {
        
                        $elementValue = isset($VALUE['value']) ? $VALUE['value'] : "";
                        $elementAttributes = isset($VALUE['attributes']) ? $VALUE['attributes'] : "";
                        $elementChild = isset($VALUE['child']) ? $VALUE['child'] : "";
                        $elementArray = ($VALUE['array'] == true) ? true : false;
                        
                        if (!$elementArray) {
        
                            $element = $XML->createElement($NAME, $elementValue);
        
                            if (is_array($elementAttributes)) {
                                foreach ($elementAttributes as $attribute => $value) { $element->setAttribute($attribute, $value); }
                            }
        
                            $PARENT = $PARENT->appendChild($element);
        
                            if (is_array($elementChild)) {
                                foreach ($elementChild as $childName => $childValue) { 
        
                                    $child = [];
                                    $child[$childName] = $childValue;
        
                                    if (is_array($childValue)) {
                                        $XML = $this->createXml($XML, $child, $PARENT); 
                                    } else {
                                        $element = $XML->createElement($NAME, $childValue);
                                        $PARENT->appendChild($element);
                                    }
        
                                }
                            }
        
                        } else {
        
                            foreach ($elementChild as $key => $childValue) {
        
                                $child = [];
                                $child[$NAME] = $childValue;
        
                                if (is_array($childValue)) {
                                    $XML = $this->createXml($XML, $child, $PARENT); 
                                } else {
                                    $element = $XML->createElement($NAME, $childValue);
                                    $PARENT->appendChild($element);
                                }
        
                            } 
        
                        }
        
                        if (empty($elementValue) && empty($elementAttributes) && empty($elementChild)) {
                            foreach ($VALUE as $childName => $childValue) { 
        
                                $child = [];
                                $child[$childName] = $childValue;
        
                                if (is_array($childValue)) {
                                    $XML = $this->createXml($XML, $child, $PARENT); 
                                } else {
                                    $element = $XML->createElement($childName, $childValue);
                                    $PARENT->appendChild($element);
                                }
        
                            }
                        } 
        
                    } else {
        
                        $element = $XML->createElement($NAME, $VALUE);
                        $PARENT->appendChild($element);
        
                    }
                    
                }
        
                return $XML;
        
            }

            private function prettyPrint($array) {

                print("<pre>".print_r($array,true)."</pre>");

            }

            /**
             * Undocumented function
             *
             * @param string $type
             * @param boolean $action = inline | download
             * @return void
             * 
             */
            public function export($type = 'array', $action = false) {

                $this->file['FatturaElettronica']['child']['FatturaElettronicaHeader'] = [];
                $this->file['FatturaElettronica']['child']['FatturaElettronicaHeader']['attributes'] = [ 'xmlns' => '' ];
                $this->file['FatturaElettronica']['child']['FatturaElettronicaHeader']['child'] = $this->header;

                $this->file['FatturaElettronica']['child']['FatturaElettronicaBody'] = [];
                $this->file['FatturaElettronica']['child']['FatturaElettronicaBody']['attributes'] = [ 'xmlns' => '' ];
                $this->file['FatturaElettronica']['child']['FatturaElettronicaBody']['child'] = $this->body;

                if ($type == 'array') {

                    if ($action != false) {
                        if ($action == "inline") {
                            $this->prettyPrint($this->file);
                        }
                    } else {
                        return $this->file;
                    }

                } elseif ($type = 'xml') {

                    if ($action != false) {
                        if ($action == "inline") {
                            header("Content-Type: text/xml");
                            echo $this->arrayToXml($this->file);
                        } elseif ($action == "download") {
                            $this->arrayToXml($this->file, 'Fattura '.$this->default['ProgressivoInvio']);
                        }
                    } else {
                        return $this->arrayToXml($this->file);
                    }

                }

            }
            
        #

    }