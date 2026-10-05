<?php

namespace Wonder\App\Models\Contacts;

use Throwable;
use Wonder\App\Model;
use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Dove si consegna: gli indirizzi di una scheda della rubrica.
 *
 * Sono un'altra cosa dall'indirizzo di fatturazione, che sta sulla scheda: chi
 * compra per l'ufficio e si fa consegnare a casa ne ha due, e chi ha tre
 * cantieri ne ha tre.
 *
 * Destinatario e telefono arrivano dall'estensione del core
 * (`withContactName()`, `withPhone()`): al corriere serve sapere chi cercare,
 * e quel nome spesso non è quello della scheda.
 */
class ContactAddress extends Model
{
    public static string $table = 'contact_addresses';
    public static string $folder = 'contacts';
    public static string $icon = 'bi bi-geo';

    /** L'indirizzo semplice del core, con destinatario e telefono. */
    public static function address(): AddressExtension
    {
        return AddressExtension::simple(countryDefault: 'IT')
            ->withLink(false)
            ->withContactName()
            ->withPhone();
    }

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('contact_id')->int()->null(false)->foreign(Contact::$table),
            Column::key('label')->length(100),
            ...static::address()->tableSchema(),
            Column::key('is_default')->enum(['true', 'false'])->default('false'),
            Column::key('position')->int(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_contact' => ['index' => 'contact_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('contact_id')->number()->decimals(0),
            Field::key('label')->text(),
            ...static::address()->dataSchema(),
            Field::key('is_default')->text()->sanitize(false),
            Field::key('position')->number()->decimals(0),
        ];
    }

    public static function decorate(array $row): array
    {
        try {
            return static::address()->decorate($row);
        } catch (Throwable) {
            // L'indirizzo "bello" lo compone il core con le sue funzioni
            // globali, che nei comandi di `forge` non esistono: lì la riga
            // torna com'è invece di far esplodere chi la legge.
            return $row;
        }
    }
}
