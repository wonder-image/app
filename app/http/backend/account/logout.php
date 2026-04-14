<?php

logoutUser('backend');

header('Location: '.__r('backend.account.login'));
exit;
