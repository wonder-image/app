<?php

namespace Wonder\Backend\Support;

use Wonder\App\ResourceSchema\FormField;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Modal;
use Wonder\Elements\Components\Tabs;
use Wonder\Elements\Components\Text;
use Wonder\Elements\Form\Form;

/**
 * Il bottone «File» di una lista e la sua finestra: la scheda Esporta
 * (GET verso la rotta `export.file`) e la scheda Importa (POST al
 * caricamento). Con una sola delle due la finestra non ha la barra delle schede.
 */
final class ResourceFileModal
{
    /**
     * @param array{action: string, label: string, formats: array<string, string>, profiles: array<int, string>, layout_formats: list<string>|null}|null $export
     * @param array{action: string, layout: string, profiles: array<int, string>, default: ?int, sample: string}|null $import
     * @param array{format: string, layout: ?int}|null $last
     *
     * @return array{button: Button, modal: Modal}|null
     */
    public static function build(string $slug, ?array $export, ?array $import, ?array $last = null): ?array
    {
        if ($export === null && $import === null) {
            return null;
        }

        $clean = trim((string) preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug)), '-');
        $id = 'wi-file-'.($clean !== '' ? $clean : 'resource');
        $tabs = Tabs::make($id)->columns(12);

        if ($export !== null) {
            $label = trim((string) $export['label']);
            $tabs->tab('export', $label !== '' ? $label : 'Esporta', [self::exportForm($export, $last)]);
        }

        if ($import !== null) {
            $tabs->tab('import', 'Importa', [self::importForm($import)]);
        }

        return [
            'button' => Button::make('File')->variant('secondary')->size('sm')
                ->icon('bi bi-arrow-down-up', 'start')->opensModal($id),
            'modal' => Modal::make('File')->id($id)->columns(12)->components([$tabs]),
        ];
    }

    /**
     * Formato e tracciato da proporre: l'ultima scelta, se esiste ancora.
     *
     * @param array<string, string> $formats
     * @param array<int, string> $profiles
     * @param array{format: string, layout: ?int}|null $last
     *
     * @return array{format: string, layout: ?int}
     */
    public static function preselect(array $formats, array $profiles, ?array $last): array
    {
        $format = (string) ($last['format'] ?? '');
        $layout = isset($last['layout']) ? (int) $last['layout'] : null;

        return [
            'format' => array_key_exists($format, $formats) ? $format : (string) array_key_first($formats),
            'layout' => $layout !== null && array_key_exists($layout, $profiles) ? $layout : null,
        ];
    }

    private static function exportForm(array $export, ?array $last): Form
    {
        $formats = (array) $export['formats'];
        $profiles = (array) $export['profiles'];
        $layoutFormats = $export['layout_formats'];
        $choice = self::preselect($formats, $profiles, $last);

        $acceptsLayout = $layoutFormats === null
            ? $formats !== []
            : array_intersect_key($formats, array_flip($layoutFormats)) !== [];

        $fields = [];

        if ($profiles !== [] && $acceptsLayout) {
            $fields[] = FormField::key('layout')->select(['' => 'Tutte le colonne'] + $profiles)
                ->label('Tracciato')->value((string) ($choice['layout'] ?? ''))->columnSpan(12);
        }

        $format = FormField::key('format');
        $format = count($formats) <= 4 ? $format->radio($formats)->pills() : $format->select($formats);
        $format->label('Formato')->value($choice['format'])->columnSpan(12);

        $fields[] = $format;

        if ($layoutFormats !== null && $profiles !== []) {
            $covered = array_intersect_key($formats, array_flip($layoutFormats));

            if (count($covered) < count($formats) && $covered !== []) {
                $fields[] = Text::make('I tracciati valgono per '.self::joinLabels(array_values($covered)))->muted()->columnSpan(12);
            }
        }

        $fields[] = Button::make('Scarica')->type('submit')->variant('primary')->columnSpan(12);

        return (new Form)->action((string) $export['action'], 'get')->columns(12)->components($fields);
    }

    private static function importForm(array $import): Form
    {
        $profiles = (array) $import['profiles'];
        $fields = [
            FormField::key('layout')->hidden()->value((string) $import['layout']),
            FormField::key('action')->hidden()->value('upload'),
        ];

        if ($profiles !== []) {
            $fields[] = FormField::key('import_layout_id')
                ->select(['' => 'Nessuno, abbino le colonne io'] + $profiles)
                ->label('Tracciato')->value((string) ($import['default'] ?? ''))->columnSpan(12);
        }

        $fields[] = FormField::key('file')->file('spreadsheet')->label('File .xlsx o .csv')->columnSpan(12);
        $fields[] = Button::to((string) $import['sample'], 'Scarica il modello')->variant('link')->columnSpan(12);
        $fields[] = Button::make('Continua')->type('submit')->variant('primary')->columnSpan(12);

        return (new Form)->action((string) $import['action'], 'post')->columns(12)->components($fields);
    }

    /** «A», «A e B», «A, B e C». */
    private static function joinLabels(array $labels): string
    {
        $labels = array_map('strval', $labels);
        $last = array_pop($labels);

        return $labels === [] ? (string) $last : implode(', ', $labels).' e '.$last;
    }
}
