<?php

namespace Wonder\Auth\Frontend;

use Wonder\Http\Route;

/** Anteprima dei dati in Panoramica: una riga per sezione accesa, con il link alla pagina che li modifica. */
final class AccountOverview
{
    public static function rows(AccountPanel $panel, object $user, array $contact): array
    {
        $contactId = (int) ($contact['id'] ?? 0);
        $value = static fn (string $text): string => trim($text) !== '' ? trim($text) : '—';
        $rows = [];

        if ($panel->enabled('personal')) {
            $rows[] = ['key' => 'personal', 'columns' => [
                ['label' => (string) __t('account.personal.name'), 'value' => $value((string) ($user->name ?? '').' '.(string) ($user->surname ?? ''))],
                ['label' => (string) __t('account.email.label'), 'value' => $value((string) ($user->email ?? ''))],
                ['label' => (string) __t('account.personal.phone'), 'value' => $value((string) ($user->phone ?? ''))],
            ], 'action' => self::link((string) __t('account.actions.edit'), Route::url('account.personal'), 'bi bi-pencil')];

            if ($panel->enabled('billing') && $contactId > 0) {
                foreach (AccountBilling::rows($contact) as $row) {
                    $rows[] = ['action' => self::link((string) __t('account.actions.edit'), Route::url('account.personal'), 'bi bi-pencil')] + $row;
                }
            }
        }

        if ($panel->enabled('addresses') && $contactId > 0) {
            $addresses = AccountAddresses::all($contactId);
            $first = $addresses !== [] ? AccountAddresses::card($addresses[0]) : null;
            $rows[] = ['key' => 'addresses', 'columns' => [
                ['label' => (string) __t('account.navigation.addresses'), 'value' => (string) __t($addresses === [] ? 'account.overview.addresses_none' : 'account.overview.addresses_count', ['count' => count($addresses)])],
                ['label' => (string) __t('account.overview.address_first'), 'value' => $value($first === null ? '' : implode(', ', array_filter([$first['name'], ...$first['lines']])))],
            ], 'action' => self::link((string) __t('account.actions.manage'), Route::url('account.addresses'), 'bi bi-geo-alt')];
        }

        return $rows;
    }

    /** L'azione di una riga che porta a un'altra pagina: in Panoramica non ci sono modal. */
    public static function link(string $label, string $href, string $icon = ''): array
    {
        return ['label' => $label, 'href' => $href, 'modal' => '', 'icon' => $icon, 'disabled' => false, 'hint' => ''];
    }
}
