<?php

namespace Wonder\Auth\Frontend;

use Wonder\Http\Route;

/** Anteprima dei dati in Panoramica: una riga per sezione accesa, con il link alla pagina che li modifica. */
final class AccountOverview
{
    public static function rows(AccountPanel $panel, object $user): array
    {
        $value = static fn (string $text): string => trim($text) !== '' ? trim($text) : '—';
        $rows = [];

        if ($panel->enabled('personal')) {
            $rows[] = ['key' => 'personal', 'columns' => [
                ['label' => (string) __t('account.personal.name'), 'value' => $value((string) ($user->name ?? '').' '.(string) ($user->surname ?? ''))],
                ['label' => (string) __t('account.email.label'), 'value' => $value((string) ($user->email ?? ''))],
                ['label' => (string) __t('account.personal.phone'), 'value' => $value((string) ($user->phone ?? ''))],
            ], 'action' => self::link((string) __t('account.actions.edit'), Route::url('account.personal'), 'bi bi-pencil')];
        }

        return $rows;
    }

    /** L'azione di una riga che porta a un'altra pagina: in Panoramica non ci sono modal. */
    public static function link(string $label, string $href, string $icon = ''): array
    {
        return ['label' => $label, 'href' => $href, 'modal' => '', 'icon' => $icon, 'disabled' => false, 'hint' => ''];
    }
}
