<?php

use Wonder\Auth\Frontend\AuthController;
use Wonder\Auth\Frontend\AuthRoutes;

(new AuthController(AuthRoutes::profile((string) ($ROUTE_META['auth_profile_key'] ?? ''))))
    ->handle((string) ($ROUTE_META['auth_action'] ?? ''), (array) ($ROUTE_PARAMETERS ?? []));
