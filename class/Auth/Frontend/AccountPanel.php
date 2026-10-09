<?php

namespace Wonder\Auth\Frontend;

use Wonder\Http\Route;
use Wonder\View\View;

/**
 * Pannello account del core; il sito lo sostituisce con una sottoclasse, i moduli lo estendono con AccountExtension.
 * Una sottoclasse che sovrascrive un hook chiama `parent::` per tenere le estensioni dei moduli.
 */
class AccountPanel
{
    private const ITEMS = [
        'overview' => ['account.index', 'bi bi-house'],
        'personal' => ['account.personal', 'bi bi-person'],
        'addresses' => ['account.addresses', 'bi bi-geo-alt'],
        'billing' => ['account.billing', 'bi bi-receipt'],
    ];

    public function parentLayout(): string { return 'frontend.main'; }
    public function title(): string { return (string) __t('account.title'); }
    public function authorities(): array { return ['client']; }
    public function sections(): array { return ['overview', 'personal', 'addresses', 'billing']; }
    public function enabled(string $section): bool { return in_array($section, $this->sections(), true); }

    /** Voci del menu, già filtrate: una voce senza href o etichetta non esce. */
    public function navigation(object $user, string $active = ''): array
    {
        $items = [];
        foreach (self::ITEMS as $key => [$route, $icon]) {
            if ($this->enabled($key)) {
                $items[$key] = ['label' => (string) __t('account.navigation.'.$key), 'href' => Route::url($route), 'icon' => $icon];
            }
        }
        foreach (AccountRoutes::extensions() as $extension) {
            $items = $extension->navigation($items, $user);
        }
        $out = [];
        foreach ($items as $key => $item) {
            if (!is_array($item) || trim((string) ($item['href'] ?? '')) === '' || trim((string) ($item['label'] ?? '')) === '') {
                continue;
            }
            $out[$key] = ['key' => (string) $key] + $item + ['icon' => 'bi bi-circle'];
            $out[$key]['active'] = (string) $key === $active;
        }
        return $out;
    }

    public function overviewRows(array $rows, object $user): array { return $this->cascade('overviewRows', $rows, $user); }
    public function personalRows(array $rows, object $user): array { return $this->cascade('personalRows', $rows, $user); }
    public function personalFields(array $fields, object $user): array { return $this->cascade('personalFields', $fields, $user); }

    public function validatePersonal(array $input, object $user): array
    {
        $messages = [];
        foreach (AccountRoutes::extensions() as $extension) {
            $messages = array_merge($messages, $extension->validatePersonal($input, $user));
        }
        return $messages;
    }

    public function personalUserValues(array $input, object $user): array
    {
        $values = [];
        foreach (AccountRoutes::extensions() as $extension) {
            $values = array_merge($values, $extension->personalUserValues($input, $user));
        }
        return $values;
    }

    public function afterPersonalSaved(array $input, object $user): void
    {
        foreach (AccountRoutes::extensions() as $extension) {
            $extension->afterPersonalSaved($input, $user);
        }
    }

    public function head(): string
    {
        return implode("\n", array_map(static fn (AccountExtension $e): string => $e->head(), AccountRoutes::extensions()));
    }

    public function layout(array $data = []): void
    {
        View::layout('frontend.account.panel', $data + ['account_panel' => $this]);
    }

    private function cascade(string $hook, array $value, object $user): array
    {
        foreach (AccountRoutes::extensions() as $extension) {
            $value = $extension->{$hook}($value, $user);
        }
        return $value;
    }
}
