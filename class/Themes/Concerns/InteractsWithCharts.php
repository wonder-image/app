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
        return "<script>(function(){"
            . "const root=window;"
            . "const api=root.WonderCharts||(root.WonderCharts=(function(){"
                . "const registry={};"
                . "const cloneConfig=function(config){"
                    . "if(typeof structuredClone==='function'){return structuredClone(config);}"
                    . "return JSON.parse(JSON.stringify(config));"
                . "};"
                . "const resolveCanvas=function(target){"
                    . "if(typeof target==='string'){return document.getElementById(target);}"
                    . "if(typeof HTMLCanvasElement!=='undefined'&&target instanceof HTMLCanvasElement){return target;}"
                    . "return target&&target.tagName==='CANVAS'?target:null;"
                . "};"
                . "const remember=function(canvas,chart){"
                    . "if(!canvas||!chart){return chart;}"
                    . "registry[canvas.id]=chart;"
                    . "canvas.__wonderChart=chart;"
                    . "return chart;"
                . "};"
                . "const forget=function(canvas){"
                    . "if(!canvas){return;}"
                    . "delete registry[canvas.id];"
                    . "try{delete canvas.__wonderChart;}catch(error){canvas.__wonderChart=null;}"
                . "};"
                . "const get=function(target){"
                    . "if(typeof Chart==='undefined'){return null;}"
                    . "const canvas=resolveCanvas(target);"
                    . "if(!canvas){return null;}"
                    . "if(typeof Chart.getChart==='function'){"
                        . "const existing=Chart.getChart(canvas);"
                        . "if(existing){return remember(canvas,existing);}"
                    . "}"
                    . "if(registry[canvas.id]){return registry[canvas.id];}"
                    . "if(canvas.__wonderChart){return canvas.__wonderChart;}"
                    . "if(Chart.instances){"
                        . "const fallback=Object.values(Chart.instances).find(function(instance){return instance&&instance.canvas===canvas;})||null;"
                        . "if(fallback){return remember(canvas,fallback);}"
                    . "}"
                    . "return null;"
                . "};"
                . "const destroy=function(target){"
                    . "const canvas=resolveCanvas(target);"
                    . "const chart=get(canvas);"
                    . "if(chart&&typeof chart.destroy==='function'){chart.destroy();}"
                    . "forget(canvas);"
                    . "return null;"
                . "};"
                . "const init=function(target,config){"
                    . "if(typeof Chart==='undefined'){return null;}"
                    . "const canvas=resolveCanvas(target);"
                    . "if(!canvas){return null;}"
                    . "destroy(canvas);"
                    . "const chart=remember(canvas,new Chart(canvas,cloneConfig(config)));"
                    . "const detail={id:canvas.id,chart:chart,config:config};"
                    . "canvas.dispatchEvent(new CustomEvent('wonder:chart:init',{detail:detail}));"
                    . "root.dispatchEvent(new CustomEvent('wonder:chart:init',{detail:detail}));"
                    . "return chart;"
                . "};"
                . "const update=function(target,updater){"
                    . "const chart=get(target);"
                    . "if(!chart){return null;}"
                    . "if(typeof updater==='function'){updater(chart);}"
                    . "if(typeof chart.update==='function'){chart.update();}"
                    . "return chart;"
                . "};"
                . "return {cloneConfig:cloneConfig,get:get,init:init,update:update,destroy:destroy};"
            . "})());"
            . "const initializeChart=function(){api.init({$canvasIdJson}, {$configJson});};"
            . "root.addEventListener('loaded', initializeChart);"
            . "if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded', initializeChart, { once: true });}"
            . "else{initializeChart();}"
        . "})();</script>";
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
