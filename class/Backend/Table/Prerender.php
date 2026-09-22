<?php

namespace Wonder\Backend\Table;

/**
 * Primo draw della tabella servito senza rete.
 *
 * DataTables 2 ha rimosso l'opzione `deferLoading` della 1.x: l'unico modo di
 * evitare la chiamata AJAX iniziale e' consegnargli il payload del primo draw
 * gia' pronto (vedi `createDataTables` in wonder-image/lib). Qui vivono le due
 * parti pure di quel meccanismo: comporre la request che DataTables avrebbe
 * inviato, e serializzare il payload per l'embed in pagina.
 */
final class Prerender
{
    private const DEFAULT_LENGTH = 10;

    /**
     * Compone la request del primo draw a partire dalla config della tabella.
     *
     * Deve combaciare con quello che DataTables invierebbe al primo giro
     * (stessa pagina, lunghezza, ricerca e ordinamento), altrimenti il payload
     * pre-renderizzato mostrerebbe righe diverse da quelle attese.
     *
     * @param array<string,mixed> $config config prodotta da Table::buildConfig()
     * @return array<string,mixed> request nella forma attesa da ListProvider::fetch()
     */
    public static function request(array $config): array
    {
        $default = is_array($config['default'] ?? null) ? $config['default'] : [];

        $page   = max((int) ($default['page'] ?? 0), 0);
        $length = (int) ($default['length'] ?? self::DEFAULT_LENGTH);

        if ($length < 1) {
            $length = self::DEFAULT_LENGTH;
        }

        $request = $config;

        $request['draw']   = 1;
        $request['start']  = $page * $length;
        $request['length'] = $length;
        $request['search'] = [
            'value' => (string) ($default['search'] ?? ''),
            'regex' => false,
        ];
        $request['order'] = [[
            'name' => (string) ($default['order'] ?? ''),
            'dir'  => (string) ($default['order_direction'] ?? 'desc'),
        ]];

        return $request;
    }

    /**
     * Serializza un payload per l'embed dentro un tag <script>.
     *
     * JSON_HEX_TAG e' obbligatorio: le celle contengono HTML generato dai
     * formatter e testo inserito dagli utenti, quindi un `</script>` nei dati
     * chiuderebbe il blocco in anticipo. Unicode e slash restano leggibili per
     * non gonfiare il payload.
     *
     * @param array<string|int,mixed> $payload
     */
    public static function encode(array $payload): string
    {
        return (string) json_encode(
            $payload,
            JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Genera lo <script> che avvia la tabella lato client.
     *
     * Il payload resta un argomento a se' invece di entrare nella config:
     * la config e' anche il corpo delle richieste successive all'endpoint,
     * quindi le righe iniziali tornerebbero al server ad ogni pagina.
     *
     * @param array<string,mixed> $config
     * @param array<string,mixed>|null $payload primo draw, null se non disponibile
     */
    public static function script(string $id, string $endpoint, array $config, ?array $payload = null): string
    {
        $arguments = "'".$id."', '".$endpoint."', ".self::encode($config);

        if ($payload !== null) {
            $arguments .= ', '.self::encode($payload);
        }

        $script  = '<script>';
        $script .= "window.addEventListener('loaded', (event) => {";
        $script .= 'createDataTables('.$arguments.')';
        $script .= '})';
        $script .= '</script>';

        return $script;
    }
}
