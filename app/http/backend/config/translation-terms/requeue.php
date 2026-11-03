<?php

use Wonder\App\Models\Localization\{Translation, TranslationTerm};
use Wonder\App\Resources\Config\TranslationTermResource;
use Wonder\Backend\Support\FlashAlert;
use Wonder\Http\Csrf;
use Wonder\Localization\Content\Translations;

$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];
$id = (int) ($routeParameters['id'] ?? 0);
$back = __r('backend.resource.'.TranslationTermResource::slug().'.edit', ['id' => $id]);

if (!Csrf::verify()) {
    FlashAlert::custom('Sessione scaduta', 'Ricarica la pagina e riprova.', 'danger');
} else {
    $term = trim((string) (((array) TranslationTerm::findById($id))['term'] ?? ''));
    $ids = $term === '' ? [] : array_column(array_filter(
        TranslationTermResource::usages($term),
        static fn (array $row): bool => $row['status'] === Translation::AUTOMATIC
    ), 'id');
    $count = $ids === [] ? 0 : Translations::store()->requeue(array_map('intval', $ids));

    FlashAlert::custom(
        $count > 0 ? 'Traduzioni rimesse in coda' : 'Niente da ritradurre',
        $count > 0
            ? $count.' traduzioni automatiche si rifaranno alla prossima esecuzione del task.'
            : 'Il termine non compare in traduzioni automatiche.'
    );
}

header('Location: '.$back, true, 303);
exit;
