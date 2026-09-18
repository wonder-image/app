<?php

namespace Wonder\Backend\Support;

use Throwable;
use Wonder\App\Logger;
use Wonder\App\Module\ConfigRepository;
use Wonder\Backend\Contracts\HomeWidget;

/**
 * Riquadri della home del backend, raccolti dalla configurazione dei moduli
 * abilitati (`backend.home_widgets`). Un riquadro non valido o che solleva
 * un'eccezione viene saltato: la home non si rompe mai.
 */
final class HomeWidgets
{
    /** @return list<HomeWidget> */
    public static function fromConfigs(array $configs, array $authorities): array
    {
        $widgets = [];

        foreach ($configs as $config) {
            foreach ((array) (($config['backend']['home_widgets'] ?? [])) as $class) {
                if (!is_string($class) || !class_exists($class) || !is_subclass_of($class, HomeWidget::class)) {
                    continue;
                }

                try {
                    $widget = new $class();
                } catch (Throwable) {
                    continue;
                }

                $allowed = $widget->authorities();

                if ($allowed !== [] && array_intersect($allowed, $authorities) === []) {
                    continue;
                }

                $widgets[] = $widget;
            }
        }

        usort($widgets, static fn (HomeWidget $a, HomeWidget $b): int => $a->order() <=> $b->order());

        return $widgets;
    }

    /** @return list<HomeWidget> */
    public static function all(array $authorities): array
    {
        return self::fromConfigs(ConfigRepository::all(), $authorities);
    }

    /** Markup di tutti i riquadri visibili, nell'ordine dichiarato. */
    public static function renderAll(array $authorities, ?array $configs = null): string
    {
        $widgets = $configs === null
            ? self::all($authorities)
            : self::fromConfigs($configs, $authorities);

        $html = '';

        foreach ($widgets as $widget) {
            try {
                $html .= $widget->render();
            } catch (Throwable $exception) {
                Logger::log(
                    $exception,
                    'backend',
                    'home_widget',
                    'ERROR',
                    'error',
                    ['widget' => $widget::class],
                    false
                );
            }
        }

        return $html;
    }
}
