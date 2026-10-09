<?php
/** php tests/App/SendMailTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';
require __DIR__ . '/../../app/function/mail.php';

/** Il negozio che si fa leggere solo da chi sta per spedire davvero. */
final class NegozioSpia
{
    public function __get(string $name): never
    {
        throw new RuntimeException('sendMail ha cominciato a spedire.');
    }
}

$GLOBALS['SOCIETY'] = new NegozioSpia();

check('con WONDER_NO_MAIL i test non spediscono: sendMail risponde sì prima di toccare il server', static fn (): bool =>
    defined('WONDER_NO_MAIL')
    && sendMail('mittente@example.test', 'cliente@example.test', 'Oggetto', '<p>Corpo</p>') === true
);

summary();
