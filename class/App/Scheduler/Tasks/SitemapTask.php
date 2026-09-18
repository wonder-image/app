<?php

namespace Wonder\App\Scheduler\Tasks;

use Wonder\App\Scheduler\{AbstractTask, Context, Process};

class SitemapTask extends AbstractTask
{
    public function key(): string { return 'wonder.sitemap'; }
    public function label(): string { return 'Generazione sitemap'; }
    public function enabled(): bool { return true; }
    public function run(Context $context): array
    {
        $context->externalProcess = true;
        $configuration = dirname(__DIR__, 4).'/vendor-static/xml-sitemaps/data/generator.conf';
        $previous = libxml_use_internal_errors(true);
        try {
            $settings = simplexml_load_file($configuration, \SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($settings === false) { throw new \RuntimeException('Configurazione sitemap non valida.'); }
        $options = [];
        foreach ($settings->option as $option) { $options[(string) $option['name']] = (string) $option; }
        $host = rtrim((string) parse_url($options['xs_initurl'] ?? '', PHP_URL_HOST), '.');
        $target = $options['xs_smname'] ?? '';
        if (!str_contains($host, '.') || $target === '' || !str_starts_with($target, rtrim($GLOBALS['ROOT'], '/').'/')) {
            throw new \RuntimeException('Configurare dominio e percorso sitemap del sito prima di avviare il crawler.');
        }
        $started = time();
        $script = dirname(__DIR__, 4).'/bin/sitemap.php';
        $metrics = tempnam($GLOBALS['ROOT'].'/storage/tmp', 'sitemap-');
        if ($metrics === false) { throw new \RuntimeException('Impossibile creare il file metriche sitemap.'); }
        try {
            $exit = Process::run([PHP_BINARY, $script, $GLOBALS['ROOT'], $metrics], $GLOBALS['ROOT'], $this->timeout(), $context->log(...));
        } finally {
            $data = json_decode((string) file_get_contents($metrics), true);
            $context->processMetrics = is_array($data) ? $data : null;
            unlink($metrics);
        }
        if ($exit !== 0) { throw new \RuntimeException('Crawler sitemap terminato con codice '.$exit); }
        clearstatcache(true, $target);
        if (!is_file($target) || filesize($target) === 0 || filemtime($target) < $started) {
            throw new \RuntimeException('Il crawler non ha aggiornato il file sitemap XML. Controllare il suo output.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_file($target, \SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false || !in_array($xml->getName(), ['urlset', 'sitemapindex'], true)) {
                throw new \RuntimeException('Il crawler ha prodotto una sitemap XML non valida.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return ['exit_code' => $exit, 'metrics_scope' => 'crawler_php_process'];
    }
}
