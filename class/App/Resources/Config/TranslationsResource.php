<?php

namespace Wonder\App\Resources\Config;

use InvalidArgumentException;
use Throwable;
use Wonder\App\Logger;
use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\Backend\Support\FlashAlert;
use Wonder\Http\Csrf;
use Wonder\Localization\Catalog\Catalog;
use Wonder\Localization\Catalog\ImportPreview;
use Wonder\Localization\Catalog\ImportStore;
use Wonder\Localization\Catalog\Sheet;
use Wonder\Localization\Catalog\TranslationSource;
use Wonder\Localization\LanguageContext;

/**
 * Pagina "Traduzioni": export del catalogo per il traduttore e import in due
 * passi (upload con anteprima, poi conferma). Dal client arrivano solo il
 * token dell'anteprima e gli id scelti: il diff si rilegge dal server.
 */
final class TranslationsResource extends NavigationOnlyResource
{
    public static function path(): string { return 'app/config/translations'; }
    public static function icon(): string { return 'bi-translate'; }
    public static function titleLabel(): string { return 'Traduzioni'; }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('set-up')
            ->group('translations', 'Traduzioni', 40)
            ->title('Export e import')
            ->order(10)
            ->authority(['admin']);
    }

    public static function pageView(): string
    {
        return dirname(__DIR__, 4).'/app/view/pages/backend/config/translations.php';
    }

    public static function url(array $query = []): string
    {
        $url = __r('backend.config.translations');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    public static function exportUrl(array $query): string
    {
        return __r('backend.config.translations.export').'?'.http_build_query($query);
    }

    /** @return array<string, string> codice => nome */
    public static function languages(): array
    {
        $langs = [];

        foreach (LanguageContext::getLangs() as $code => $lang) {
            $langs[(string) $code] = (string) ($lang['name'] ?? $code);
        }

        return $langs ?: [LanguageContext::getDefaultLang() => LanguageContext::getDefaultLang()];
    }

    /**
     * Sorgente e lingue scelte, validate contro il catalogo e le lingue del
     * sito; un valore sconosciuto ricade sul default.
     *
     * @return array{source: string, from: string, to: string}
     */
    public static function selection(array $input): array
    {
        $sources = Catalog::all();
        $langs = array_keys(self::languages());
        $default = in_array(LanguageContext::getDefaultLang(), $langs, true) ? LanguageContext::getDefaultLang() : $langs[0];

        $source = (string) ($input['source'] ?? '');
        $source = isset($sources[$source]) ? $source : (string) (array_key_first($sources) ?? '');

        $from = (string) ($input['from'] ?? '');
        $from = in_array($from, $langs, true) ? $from : $default;

        $to = (string) ($input['to'] ?? '');

        if (!in_array($to, $langs, true) || $to === $from) {
            $to = current(array_diff($langs, [$from])) ?: $from;
        }

        return ['source' => $source, 'from' => $from, 'to' => $to];
    }

    public static function store(): ImportStore
    {
        return new ImportStore();
    }

    public static function handleSubmit(): void
    {
        $selection = self::selection($_POST);
        $back = self::url($selection);

        if (!Csrf::verify()) {
            FlashAlert::custom('Sessione scaduta', 'Ricarica la pagina e riprova.', 'danger');
        } elseif ($selection['from'] === $selection['to']) {
            FlashAlert::custom('Lingue uguali', 'Scegli una lingua di destinazione diversa dalla sorgente.', 'warning');
        } elseif (($source = Catalog::find($selection['source'])) === null) {
            FlashAlert::custom('Sorgente sconosciuta', 'Scegli una sorgente dall\'elenco.', 'danger');
        } elseif (($_POST['action'] ?? '') === 'confirm') {
            $back = self::confirm($source, $selection, (string) ($_POST['token'] ?? ''), (array) ($_POST['ids'] ?? []));
        } else {
            $back = self::upload($source, $selection);
        }

        header('Location: '.$back, true, 303);
        exit;
    }

    private static function upload(TranslationSource $source, array $selection): string
    {
        $file = $_FILES['sheet'] ?? null;
        $tmp = is_array($file['tmp_name'] ?? null) ? ($file['tmp_name'][0] ?? '') : ($file['tmp_name'] ?? '');
        $name = is_array($file['name'] ?? null) ? ($file['name'][0] ?? '') : ($file['name'] ?? '');

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            FlashAlert::custom('Nessun file', 'Carica il foglio del traduttore (.xlsx o .csv).', 'warning');

            return self::url($selection);
        }

        try {
            $rows = Sheet::read($tmp, pathinfo($name, PATHINFO_EXTENSION), $selection['from'], $selection['to']);
            $preview = ImportPreview::build($source->entries($selection['from'], $selection['to']), $rows);
        } catch (InvalidArgumentException $exception) {
            FlashAlert::custom('File non valido', e($exception->getMessage()), 'danger');

            return self::url($selection);
        } catch (Throwable $exception) {
            Logger::log($exception, 'backend', 'translations', 'ERROR', 'error', ['source' => $selection['source']], false);
            FlashAlert::custom('Import non riuscito', 'Il file non si può leggere.', 'danger');

            return self::url($selection);
        }

        $token = self::store()->save($selection + ['file' => $name, 'preview' => $preview]);

        return self::url($selection + ['preview' => $token]);
    }

    private static function confirm(TranslationSource $source, array $selection, string $token, array $ids): string
    {
        $store = self::store();
        $data = $store->load($token);

        if ($data === null || ($data['source'] ?? '') !== $selection['source']
            || ($data['from'] ?? '') !== $selection['from'] || ($data['to'] ?? '') !== $selection['to']) {
            FlashAlert::custom('Anteprima scaduta', 'Carica di nuovo il file.', 'warning');

            return self::url($selection);
        }

        $changes = ImportPreview::changes((array) $data['preview'], array_map('strval', $ids));

        if ($changes === []) {
            FlashAlert::custom('Nessuna riga scelta', 'Seleziona almeno una traduzione da applicare.', 'warning');

            return self::url($selection + ['preview' => $token]);
        }

        $entries = $source->entries($selection['from'], $selection['to']);
        $incomplete = ImportPreview::incompleteGroups($entries, $changes);

        if ($incomplete !== []) {
            FlashAlert::custom(
                'Lista incompleta',
                'Traduci tutte le voci della lista: '.e(implode(', ', array_merge(...array_values($incomplete)))).'.',
                'danger'
            );

            return self::url($selection + ['preview' => $token]);
        }

        $errors = $source->apply($selection['from'], $selection['to'], $changes);
        $applied = count($changes) - count($errors);
        $store->delete($token);

        if ($errors !== []) {
            $lines = [];

            foreach (array_slice($errors, 0, 10, true) as $id => $error) {
                $lines[] = e($id).': '.e($error);
            }

            FlashAlert::custom("Applicate {$applied} traduzioni, ".count($errors).' con errori', implode('<br>', $lines), 'warning');
        } else {
            FlashAlert::saved("Applicate {$applied} traduzioni.");
        }

        return self::url($selection);
    }

    /** Download del catalogo in xlsx o csv per la selezione chiesta. */
    public static function export(array $input): void
    {
        $selection = self::selection($input);
        $source = Catalog::find($selection['source']);
        $format = ($input['format'] ?? 'xlsx') === 'csv' ? 'csv' : 'xlsx';

        if ($source === null) {
            http_response_code(404);
            exit;
        }

        $rows = Sheet::rows($source->entries($selection['from'], $selection['to']), $selection['from'], $selection['to']);
        $body = $format === 'csv' ? Sheet::csv($rows) : Sheet::xlsx($rows);
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($selection['source']));
        $filename = "traduzioni-{$slug}-{$selection['from']}-{$selection['to']}.{$format}";

        header('Content-Type: '.($format === 'csv'
            ? 'text/csv; charset=utf-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.strlen($body));
        header('Cache-Control: no-store');
        echo $body;
        exit;
    }
}
