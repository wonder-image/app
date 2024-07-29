<?php

    # Email
        $MAIL->host = (isset($_ENV['MAIL_HOST']) && !empty($_ENV['MAIL_HOST'])) ? $_ENV['MAIL_HOST'] : "";
        $MAIL->username = (isset($_ENV['MAIL_USERNAME']) && !empty($_ENV['MAIL_USERNAME'])) ? $_ENV['MAIL_USERNAME'] : "";
        $MAIL->password = (isset($_ENV['MAIL_PASSWORD']) && !empty($_ENV['MAIL_PASSWORD'])) ? $_ENV['MAIL_PASSWORD'] : "";
        $MAIL->port = (isset($_ENV['MAIL_PORT']) && !empty($_ENV['MAIL_PORT'])) ? $_ENV['MAIL_PORT'] : "";