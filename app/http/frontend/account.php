<?php

use Wonder\Auth\Frontend\AccountController;
use Wonder\Auth\Frontend\AccountRoutes;

(new AccountController(AccountRoutes::panel(), AccountRoutes::auth()))
    ->handle((string) ($ROUTE_META['account_action'] ?? ''), (array) ($ROUTE_PARAMETERS ?? []));
