<?php
/** php tests/Plugin/Brevo/TransactionalEmailReplyToTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Plugin\Brevo\TransactionalEmail;

check('Brevo omette un reply-to vuoto', function () {
    $mail = (new TransactionalEmail('test-key'))->replyTo('   ', 'Wonder');

    return !array_key_exists('replyTo', $mail->params);
});

check('Brevo conserva un reply-to valorizzato', function () {
    $mail = (new TransactionalEmail('test-key'))->replyTo(' reply@example.com ', 'Wonder');

    return array_key_exists('replyTo', $mail->params)
        && $mail->params['replyTo'] instanceof \Brevo\TransactionalEmails\Types\SendTransacEmailRequestReplyTo;
});

summary();
