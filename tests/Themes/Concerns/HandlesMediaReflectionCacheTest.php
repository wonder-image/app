<?php
/** php tests/Themes/Concerns/HandlesMediaReflectionCacheTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

/** Espone il metodo protetto e la dimensione della cache per-classe. */
final class HandlesMediaProbe
{
    use \Wonder\Themes\Concerns\HandlesMedia;

    public function callRenderSlideContent(mixed $slide, string $theme): string
    {
        return $this->renderSlideContent($slide, $theme);
    }

    public static function cacheSize(): int
    {
        return count(self::$renderSignatureCache);
    }
}

final class SlidePublicNoParam
{
    public function render(): string { return 'NOPARAM'; }
}

final class SlidePublicWithParam
{
    public function render(string $theme): string { return 'THEME:' . $theme; }
}

final class SlidePrivateRender
{
    private function render(): string { return 'SEGRETO'; }
}

$probe = new HandlesMediaProbe();

check('slide public senza parametro', function () use ($probe) {
    return $probe->callRenderSlideContent(new SlidePublicNoParam(), 'wonder') === 'NOPARAM';
});

check('slide public con parametro theme', function () use ($probe) {
    return $probe->callRenderSlideContent(new SlidePublicWithParam(), 'wonder') === 'THEME:wonder';
});

check('render privato produce stringa vuota', function () use ($probe) {
    return $probe->callRenderSlideContent(new SlidePrivateRender(), 'wonder') === '';
});

check('stringa passa inalterata', function () use ($probe) {
    return $probe->callRenderSlideContent('ciao', 'wonder') === 'ciao';
});

check('int/float diventano stringa', function () use ($probe) {
    return $probe->callRenderSlideContent(42, 'wonder') === '42'
        && $probe->callRenderSlideContent(3.5, 'wonder') === '3.5';
});

check('cache deduplica per classe (2 render stessa classe = 1 entry)', function () use ($probe) {
    // Classi già viste finora: SlidePublicNoParam, SlidePublicWithParam,
    // SlidePrivateRender = 3 classi distinte con render().
    $probe->callRenderSlideContent(new SlidePublicNoParam(), 'wonder'); // ri-render, no nuova entry
    $probe->callRenderSlideContent(new SlidePublicWithParam(), 'bootstrap');
    return HandlesMediaProbe::cacheSize() === 3;
});

summary();
