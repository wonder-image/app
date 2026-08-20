<?php

namespace Wonder\Backend\Table;

use Wonder\App\Credentials;
use Wonder\Backend\Table\Field;
use Wonder\Backend\Table\SSP;

final class ListProvider
{
    /**
     * Build the SSP column set (db name, dt index, format, formatter).
     *
     * @param array<int,array<string,mixed>> $fields config.fields entries
     * @return array<int,array<string,mixed>>
     */
    public static function buildColumns(array $fields, bool $arrow, int $lineCount, Field $renderer): array
    {
        $columns = [];
        $columnN = 0;

        foreach ($fields as $format) {
            $columnName = $format['name'];
            $other      = $format['other'] ?? [];

            if ($columnName === 'position-up' || $columnName === 'position-down') {
                $arrowDir   = $columnName === 'position-up' ? 'position_arrow_up' : 'position_arrow_down';
                $columnName = 'id';
                $format     = ['visible' => $arrow, 'lines' => $lineCount];
                $formatter  = static function ($row, $column, $format) use ($renderer, $arrowDir) {
                    return $renderer->newField($row, $arrowDir, $format);
                };
            } elseif ($columnName === 'menu') {
                $columnName = 'id';
                $format     = $other;
                $formatter  = static function ($row, $column, $format) use ($renderer) {
                    return $renderer->newField($row, 'action_button', $format);
                };
            } else {
                $format    = empty($other) ? $format : $other;
                $formatter = static function ($row, $column, $format) use ($renderer) {
                    return $renderer->newField($row, $column, $format);
                };
            }

            $columns[] = [
                'db'        => $columnName,
                'dt'        => $columnN,
                'format'    => $format,
                'formatter' => $formatter,
            ];

            $columnN++;
        }

        return $columns;
    }

    /**
     * Run the server-side processing query and return the SSP result array.
     *
     * @param array<string,mixed> $request DataTables request (same shape as $_POST)
     * @return array{draw:int,recordsTotal:int,recordsFiltered:int,data:array}
     */
    public static function fetch(array $request, object $name, object $text, object $user, object $page, $path): array
    {
        // query / query_filter / query_custom / search_columns are raw SQL
        // fragments and identifier blobs generated server-side (see
        // Table::buildConfig) and round-tripped through the untrusted client.
        // Verify their HMAC signature before use; a tampered or missing value
        // aborts the request so it can never inject SQL.
        $query       = ConfigCodec::decode((string) ($request['config']['query'] ?? ''));
        $queryFilter = ConfigCodec::decode((string) ($request['config']['query_filter'] ?? ''));
        $queryCustom = ConfigCodec::decode((string) ($request['config']['query_custom'] ?? ''));
        $searchBlob  = ConfigCodec::decode((string) ($request['config']['search_columns'] ?? ''));

        if ($query === null || $queryFilter === null || $queryCustom === null || $searchBlob === null) {
            return self::signatureError($request);
        }

        $custom = (object) [];
        $custom->query        = $query;
        $custom->query_filter = $queryFilter;
        $custom->query_all    = $queryCustom;
        $custom->search_field = json_decode($searchBlob, true);
        if (!is_array($custom->search_field)) {
            $custom->search_field = [];
        }

        $custom->arrow = (($request['default']['order'] ?? '') === 'position');
        if (isset($request['search']) && ($request['search']['value'] ?? '') !== '') {
            $custom->arrow = false;
        }
        if (!empty($custom->query_filter)) {
            $custom->arrow = false;
        }

        $custom->order_column    = $request['order'][0]['name'] ?? ($request['default']['order'] ?? '');
        $custom->order_direction = $request['order'][0]['dir']  ?? ($request['default']['order_direction'] ?? '');

        $lineCount = (int) sqlCount($name->table, $custom->query, 'id', true);

        $renderer = new Field($name, $path, $text, $user, $page);
        $columns  = self::buildColumns((array) $request['fields'], $custom->arrow, $lineCount, $renderer);

        $credentials = Credentials::database();
        $sqlDetails  = [
            'user' => $credentials->username,
            'pass' => $credentials->password,
            'db'   => $name->database,
            'host' => $credentials->hostname,
        ];

        return SSP::complex(
            $request,
            $sqlDetails,
            $name->table,
            'id',
            $columns,
            $custom->search_field,
            $custom->query_filter,
            $custom->query_all,
            $custom->order_column,
            $custom->order_direction
        );
    }

    /**
     * DataTables-shaped error response returned when the signed config fails
     * verification. Keeps `draw` so the client can correlate the response, and
     * returns zero rows so no data leaks.
     *
     * @param array<string,mixed> $request
     * @return array{draw:int,recordsTotal:int,recordsFiltered:int,data:array,error:string}
     */
    private static function signatureError(array $request): array
    {
        return [
            'draw'            => isset($request['draw']) ? (int) $request['draw'] : 0,
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
            'error'           => 'Invalid table configuration signature.',
        ];
    }
}
