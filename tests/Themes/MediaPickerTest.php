<?php
/** php tests/Themes/MediaPickerTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

defined('APP_URL') || define('APP_URL', 'https://example.test');
defined('APP_VERSION') || define('APP_VERSION', 'dev');
defined('RESPONSIVE_IMAGE_SIZES') || define('RESPONSIVE_IMAGE_SIZES', [240, 480]);

use Wonder\App\Media\MediaLibrary;
use Wonder\App\Media\MemoryMediaStore;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs\InputMedia;
use Wonder\Elements\Form\Components\MediaPicker;
use Wonder\Themes\Support\PageAssets;

/**
 * Il campo media del backend: id in un input nascosto, anteprima, scelta
 * dalla libreria, file nuovo, rimozione. Finestra, stile e script una
 * volta per pagina.
 */

MediaLibrary::useStore(new MemoryMediaStore([
    ['id' => 10, 'type' => 'image', 'slug' => 'scarpa', 'name' => 'Scarpa <rossa>', 'alt' => 'Scarpa', 'file' => 'scarpa.jpg'],
    ['id' => 12, 'type' => 'document', 'slug' => 'manuale', 'name' => 'Manuale', 'file' => 'manuale.pdf'],
]));

/** Il campo senza finestra, stile e script (che escono una volta sola). */
$campo = static function (InputMedia $input): string {
    PageAssets::once('wi-media-field', '');

    return $input->render('bootstrap');
};

/** Gli attributi di un tag, nell'ordine in cui sono scritti. */
$attributi = static function (string $html, string $pattern): array {
    if (preg_match($pattern, $html, $tag) !== 1) {
        throw new RuntimeException('tag non trovato');
    }

    preg_match_all('#\s([^\s"=<>/]+)(?:="[^"]*")?#', $tag[0], $found);

    return $found[1];
};

check('il DSL dà un InputMedia che compila un MediaPicker', function () {
    $input = FormField::key('image_id')->label('Immagine')->mediaImage();

    return $input instanceof InputMedia && $input->compile() instanceof MediaPicker;
});

check('l\'id sta in un input nascosto con il nome della colonna', function () use ($campo) {
    $html = $campo(FormField::key('image_id')->mediaImage()->label('Immagine')->value('10'));

    return preg_match('#<input type="hidden" class="wi-media-value" id="[a-z_]+" name="image_id" value="10" data-wi-media-value#', $html) === 1;
});

check('il file nuovo parte con il form in un campo a parte, con le estensioni del tipo', function () use ($campo) {
    $html = $campo(FormField::key('image_id')->mediaImage()->value('10'));

    return str_contains($html, 'name="image_id__wi_media_upload"')
        && str_contains($html, 'accept=".png,.jpg,.jpeg,.webp"')
        && str_contains($html, 'File ammessi: png, jpg, jpeg, webp. Peso massimo 5 MB.');
});

check('anteprima di un\'immagine: miniatura e nome, escapato', function () use ($campo) {
    $html = $campo(FormField::key('image_id')->mediaImage()->value(10));

    return str_contains($html, '<img class="wi-media-thumbnail" src="https://example.test/assets/upload/images/scarpa-240.jpg" alt="" data-wi-media-thumbnail>')
        && str_contains($html, 'Scarpa &lt;rossa&gt;</div>')
        && !str_contains($html, '<rossa>')
        && str_contains($html, 'data-wi-media-glyph hidden')
        && !str_contains($html, 'is-empty');
});

check('anteprima di un documento: nome e segno del tipo, niente miniatura', function () use ($campo) {
    $html = $campo(FormField::key('manual_id')->mediaDocument()->value('12'));

    return str_contains($html, 'data-wi-media="document"')
        && str_contains($html, 'data-wi-media-thumbnail hidden')
        && str_contains($html, '<i class="bi bi-file-earmark-text wi-media-glyph" aria-hidden="true" data-wi-media-glyph></i>')
        && str_contains($html, '>Manuale</div>')
        && str_contains($html, 'accept=".pdf,.doc,');
});

check('campo vuoto: stato vuoto e "Togli" nascosto', function () use ($campo) {
    $html = $campo(FormField::key('image_id')->mediaImage());

    return str_contains($html, 'class="wi-media-field is-empty"')
        && str_contains($html, 'value="" data-wi-media-value')
        && str_contains($html, '>Nessun file scelto</div>')
        && str_contains($html, 'data-wi-media-remove hidden');
});

check('un media che non c\'è più, o di un altro tipo: l\'id resta e il campo lo dice', function () use ($campo) {
    $missing = $campo(FormField::key('image_id')->mediaImage()->value('999'));
    $wrong = $campo(FormField::key('image_id')->mediaImage()->value('12'));

    return str_contains($missing, 'value="999"')
        && str_contains($missing, '>Media non trovato</div>')
        && str_contains($missing, 'non è più in libreria')
        && !preg_match('#data-wi-media-remove hidden#', $missing)
        && str_contains($wrong, '>Media non trovato</div>');
});

check('un valore che non è un id non arriva nel markup', function () use ($campo) {
    $html = $campo(FormField::key('image_id')->mediaImage()->value('"><script>alert(1)</script>'));

    return str_contains($html, 'value="" data-wi-media-value') && !str_contains($html, '<script>alert');
});

check('le tre azioni e la ricerca del selettore', function () use ($campo) {
    $html = $campo(FormField::key('image_id')->mediaImage()->value('10'));

    return str_contains($html, 'data-wi-media-choose>')
        && str_contains($html, 'Scegli dalla libreria')
        && str_contains($html, 'Carica un file')
        && str_contains($html, 'data-wi-media-remove>')
        && str_contains($html, 'data-wi-media-search="https://example.test/api/backend/media/search/"')
        && str_contains($html, 'data-wi-media="image"');
});

check('ogni attributo una volta sola sull\'input nascosto, classe del campo accanto a quella del tema', function () use ($campo, $attributi) {
    $html = $campo(
        FormField::key('image_id')->mediaImage()->value('10')->required()
            ->attribute('class="mia" id="altro" name="altro" value="7" data-wi-media-value="x" data-prova="1"')
    );
    $names = $attributi($html, '#<input type="hidden"[^>]*>#');

    return count($names) === count(array_unique($names))
        && str_contains($html, 'class="wi-media-value mia"')
        && in_array('data-prova', $names, true)
        && !in_array('required', $names, true);
});

check('obbligatorio: asterisco e segnale per lo script, non un required su un input nascosto', function () use ($campo) {
    $html = $campo(FormField::key('image_id')->mediaImage()->label('Immagine')->required());

    return str_contains($html, '>Immagine*</label>') && str_contains($html, 'data-wi-media-required="true"');
});

check('disabilitato: l\'id non parte e le azioni sono spente', function () use ($campo) {
    $html = $campo(FormField::key('image_id')->mediaImage()->value('10')->disabled());

    return preg_match('#<input type="hidden"[^>]* disabled#', $html) === 1
        && str_contains($html, 'data-wi-media-choose disabled')
        && str_contains($html, 'data-wi-media-upload disabled')
        && str_contains($html, 'data-wi-media-remove disabled');
});

check('l\'errore del campo esce sotto, e il campo è segnato', function () use ($campo) {
    $html = $campo(FormField::key('image_id')->mediaImage()->value('10')->error('Il media scelto non è più in libreria.'));

    return str_contains($html, 'class="wi-media-field is-invalid"')
        && str_contains($html, '<div class="invalid-feedback d-block">Il media scelto non è più in libreria.</div>');
});

check('finestra, stile e script escono una volta per pagina, dal primo campo', function () {
    PageAssets::reset();
    $first = FormField::key('image_id')->mediaImage()->render('bootstrap');
    $second = FormField::key('cover_id')->mediaImage()->render('bootstrap');

    return substr_count($first, 'id="wi-media-picker"') === 1
        && substr_count($first, '<style data-wi-media-style>') === 1
        && substr_count($first, '<script data-wi-media-script>') === 1
        && str_contains($first, 'data-wi-modal-detach')
        && str_contains($first, 'data-wi-media-term')
        && !str_contains($second, 'wi-media-picker')
        && !str_contains($second, '<script')
        && !str_contains($second, '<style');
});

check('la finestra non ha un form: non si annida in quello del record', function () {
    $modal = \Wonder\Themes\Bootstrap\Form\Components\MediaPicker::modal();

    return !str_contains($modal, '<form') && str_contains($modal, 'modal-dialog-scrollable') && str_contains($modal, 'modal-lg');
});

check('lo script non contiene valori PHP e non usa window.confirm', function () {
    $script = \Wonder\Themes\Bootstrap\Form\Components\MediaPicker::script();

    return !str_contains($script, 'example.test')
        && !str_contains($script, 'confirm(')
        && !str_contains($script, 'innerHTML')
        && str_contains($script, "'X-WI-CSRF'");
});

check('nel tema Wonder il campo non esiste, come il Repeater', function () {
    try {
        FormField::key('image_id')->mediaImage()->render('wonder');
    } catch (RuntimeException) {
        return true;
    }

    return false;
});

MediaLibrary::useStore(null);

summary();
