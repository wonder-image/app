<?php

namespace Wonder\Themes\Bootstrap\Charts;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Concerns\InteractsWithCharts;

class Chart extends Component
{
    use HasAttributes;
    use InteractsWithCharts;

    public function render($class): string
    {
        $chart = $this->chartContext($class);

        $html = "<div id='{$chart['safe_wrapper_id']}' {$chart['attributes']}>";

        if ($chart['title'] !== '') {
            $html .= "<div class='mb-2'><strong>{$chart['safe_title']}</strong></div>";
        }

        $html .= "<div class='chartjs-container' style=\"{$chart['canvas_style']}\">";
        $html .= "<canvas id='{$chart['safe_canvas_id']}' class='w-100 h-100'></canvas>";
        $html .= '</div>';
        $html .= '</div>';
        $html .= $this->renderChartScript($chart['canvas_id_json'], $chart['config_json']);

        return $html;
    }
}
