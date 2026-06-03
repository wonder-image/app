<?php

namespace Wonder\App\Models\Consent;

use Wonder\App\LegacyGlobals;
use Wonder\App\Model;
use Wonder\App\ResourceRegistry;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class ConsentEvent extends Model
{
    public static string $table = 'consent_events';
    public static string $folder = 'app/log/consent';
    public static string $icon = 'bi bi-shield-check';

    public static function tableSchema(): array
    {
        return [
            // `user_id` nullable per coprire il consenso "lead" raccolto da
            // form pubblici (es. contatto/iscrizione newsletter) dove non
            // esiste ancora un account. Per quei casi l'identificatore è
            // `subject_email`. Per i signup utente resta il pattern storico
            // (user_id valorizzato, subject_email NULL).
            Column::key('user_id')->int()->null()->foreign('user'),
            Column::key('subject_email')->length(320)->null(),
            // Link polimorfico al record che ha originato il consenso
            // (es. `subject_ref_type = 'requests'`, `subject_ref_id = 123`
            // per il consenso raccolto dal form di contatto in
            // `RequestResource`). Permette traccia bidirezionale senza FK
            // rigida verso una singola tabella.
            Column::key('subject_ref_type')->length(120)->null(),
            Column::key('subject_ref_id')->int()->null(),
            Column::key('consent_type')->length(120)->null(false),
            Column::key('action')->enum(['accept', 'reject', 'withdraw'])->null(false),
            Column::key('legal_document_id')->int()->null()->foreign('legal_documents'),
            Column::key('occurred_at')->datetime()->null(false),
            Column::key('ip_address')->length(45)->null(false),
            Column::key('user_agent')->length(1000)->null(false),
            Column::key('locale')->length(2)->null(false),
            Column::key('source')->enum(['web', 'app', 'api', 'admin'])->null(false),
            Column::key('ui_surface')->length(120)->null(false),
            Column::key('evidence_json')->json()->null(),
            Column::key('creation')->datetime()->null(false),
        ];
    }

    public static function tableOptions(): array
    {
        return [
            'audit_columns' => true,
            'audit_auto_columns' => false,
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'idx_user_consent_type_time' => [
                'index' => ['user_id', 'consent_type', 'occurred_at'],
            ],
            'idx_subject_email_consent_type_time' => [
                'index' => ['subject_email', 'consent_type', 'occurred_at'],
            ],
            // Lookup veloce "dato un record sorgente, mostrami i consensi":
            // `SELECT * FROM consent_events WHERE subject_ref_type = ?
            // AND subject_ref_id = ?`.
            'idx_subject_ref' => [
                'index' => ['subject_ref_type', 'subject_ref_id'],
            ],
            'idx_legal_document_id' => [
                'index' => 'legal_document_id',
            ],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('user_id')->number(),
            Field::key('subject_email')->email(),
            Field::key('subject_ref_type')->text(),
            Field::key('subject_ref_id')->number(),
            Field::key('consent_type')->text()->required(),
            Field::key('action')->text()->required(),
            Field::key('legal_document_id')->number(),
            Field::key('occurred_at')->text()->required(),
            Field::key('ip_address')->text()->required(),
            Field::key('user_agent')->text()->required(),
            Field::key('locale')->text()->required(),
            Field::key('source')->text()->required(),
            Field::key('ui_surface')->text()->required(),
            Field::key('evidence_json')->text()->json()->sanitize(false),
            Field::key('creation')->text(),
        ];
    }

    /**
     * URL backend del record sorgente di un evento consenso (es. dato
     * `subject_ref_type='requests'` + `subject_ref_id=42`, ritorna
     * `/{backend}/requests/42/`).
     *
     * Accetta una riga di `consent_events` come array assoc o object —
     * cioè il formato che ritornano sia `sqlSelect()` sia
     * `ConsentEventRepository::findById()`/`findBySubjectRef()`.
     *
     * Ritorna `null` (non eccezione) quando:
     *  - `subject_ref_*` non sono valorizzati (consenso registrato prima
     *    dell'introduzione della tracciabilità, oppure scrittura manuale)
     *  - nessuna `Resource` registrata mappa la tabella sorgente
     *  - `$PATH->backend` non è disponibile (ambiente CLI/test)
     *
     * Comodo nelle view di `ConsentEvent` backend per linkare "Origine
     * → {nome resource}".
     */
    public static function backendUrl(array|object $row): ?string
    {
        $data = is_object($row) ? (array) $row : $row;

        $subjectRefType = trim((string) ($data['subject_ref_type'] ?? ''));
        $subjectRefId = (int) ($data['subject_ref_id'] ?? 0);

        if ($subjectRefType === '' || $subjectRefId <= 0) {
            return null;
        }

        $resourceClass = ResourceRegistry::resolveByTable($subjectRefType);

        if ($resourceClass === null) {
            return null;
        }

        $path = LegacyGlobals::get('PATH');
        $backend = is_object($path) ? trim((string) ($path->backend ?? '')) : '';

        if ($backend === '') {
            return null;
        }

        return rtrim($backend, '/').'/'.$resourceClass::path().'/'.$subjectRefId.'/';
    }
}
