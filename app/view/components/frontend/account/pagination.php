<?php

use Wonder\Elements\Frontend\Pagination;

/**
 * Wrapper di compatibilità dell'Element `Frontend\Pagination`, con gli stessi
 * parametri di prima: nel codice nuovo usa `Pagination::account(...)`.
 *
 * @var array{page: int, pages: int, from: int, to: int, total: int} $pagination
 * @var string $base_url
 */

echo Pagination::account($pagination, $base_url)->render('wonder');
