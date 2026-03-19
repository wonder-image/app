<?php

namespace Wonder\Themes\Concerns;

use JsonException;
use RuntimeException;

trait InteractsWithCharts
{
    protected function chartContext(mixed $class, array $wrapperClasses = []): array
    {
        $schema = $class->getSchema();
        $canvasId = $this->resolveId($schema['id'] ?? null);
        $wrapperId = $canvasId . '-wrapper';
        $title = trim((string) ($schema['title'] ?? ''));
        $width = $this->escape((string) ($schema['width'] ?? '100%'));
        $height = $this->escape((string) ($schema['height'] ?? '320px'));

        return [
            'title' => $title,
            'safe_title' => $this->escape($title),
            'safe_canvas_id' => $this->escape($canvasId),
            'safe_wrapper_id' => $this->escape($wrapperId),
            'canvas_id_json' => $this->encodeChartJson($canvasId),
            'config_json' => $this->encodeChartJson($class->config()),
            'attributes' => $this->renderChartAttributes($schema, $wrapperClasses),
            'canvas_style' => "position: relative; width: {$width}; height: {$height};",
        ];
    }

    protected function renderChartScript(string $canvasIdJson, string $configJson): string
    {
        return "<script>(function(){const initializeChart=function(){const canvas=document.getElementById({$canvasIdJson});"
            . "if(!canvas||typeof Chart==='undefined'){return;}const existing=typeof Chart.getChart==='function'"
            . "?Chart.getChart(canvas):null;if(existing){existing.destroy();}new Chart(canvas, {$configJson});};"
            . "if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded', initializeChart, { once: true });}"
            . "else{initializeChart();}})();</script>";
    }

    private function renderChartAttributes(array $schema, array $wrapperClasses): string
    {
        $attributes = $schema['attributes'] ?? [];
        $classes = $attributes['class'] ?? [];

        if (!is_array($classes)) {
            $classes = [$classes];
        }

        $attributes['class'] = array_values(array_filter(
            array_merge($wrapperClasses, $classes),
            static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== ''
        ));

        return $this->renderAttributes($attributes);
    }

    private function encodeChartJson(mixed $value): string
    {
        try {
            $json = json_encode(
                $value,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Impossibile serializzare la configurazione del grafico: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        return is_string($json) ? $json : 'null';
    }
}
