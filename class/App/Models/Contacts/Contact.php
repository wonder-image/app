<?php

namespace Wonder\App\Models\Contacts;

use Throwable;
use Wonder\App\Model;
use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\App\Support\ModelCode;
use Wonder\Sql\TableSchema as Column;

/** Shared contact identity with one billing address; commercial options belong to modules. */
class Contact extends Model
{
    public static string $table = 'contacts';
    public static string $folder = 'contacts';
    public static string $icon = 'bi bi-person-vcard';

    /** I dati di fatturazione del core, sempre con la stessa configurazione. */
    public static function billing(): AddressExtension
    {
        // Niente link a Google Maps: l'indirizzo di fatturazione non si visita.
        return AddressExtension::billing(countryDefault: 'IT')->withLink(false);
    }

    /** Operational identities must not travel through configuration sync. */
    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema(['code', 'email', 'color']),
            ...static::billing()->tableSchema(),
            Column::key('is_customer')->enum(['true', 'false'])->default('true'),
            Column::key('is_supplier')->enum(['true', 'false'])->default('false'),
            Column::key('user_id')->int(),
            Column::key('note')->type('TEXT'),
            Column::key('custom_data')->json(),
            Column::key('active')->enum(['true', 'false'])->default('true'),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_customer' => ['index' => 'is_customer'],
            'ind_supplier' => ['index' => 'is_supplier'],
            'ind_user' => ['index' => 'user_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('code')->text()->uniqueCode('con_'),
            ...static::billing()->dataSchema(),
            Field::key('email')->email(),
            Field::key('is_customer')->text()->sanitize(false),
            Field::key('is_supplier')->text()->sanitize(false),
            Field::key('user_id')->number()->decimals(0),
            Field::key('color')->text()->sanitize(false),
            Field::key('note')->text(),
            Field::key('custom_data')->json(),
            Field::key('active')->text()->sanitize(false),
        ];
    }

    /** L'indirizzo già composto, come lo mostra il core. */
    public static function decorate(array $row): array
    {
        try {
            return static::billing()->decorate($row);
        } catch (Throwable) {
            // L'indirizzo "bello" lo compone il core con le sue funzioni
            // globali, che nei comandi di `forge` non esistono: lì la riga
            // torna com'è invece di far esplodere chi la legge.
            return $row;
        }
    }

    /**
     * Codice nuovo per una scheda.
     *
     * `Model::prepare()` formatta i valori ma non genera i codici unici: li fa
     * il flusso dei form. Chi inserisce una riga da codice chiede il codice qui.
     */
    public static function newCode(): string
    {
        return ModelCode::make(static::class, 'con_');
    }

    public static function create(array $values): object
    {
        if (trim((string) ($values['code'] ?? '')) === '') {
            $values['code'] = static::newCode();
        }

        return parent::create($values);
    }
}
