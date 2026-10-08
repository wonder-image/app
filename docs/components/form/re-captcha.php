<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\reCAPTCHA;

return ComponentDoc::for(reCAPTCHA::class)
    ->title('reCAPTCHA')
    ->group('advanced')
    ->order(50)
    ->tags('captcha', 'spam', 'google', 'sicurezza')
    ->description('Il widget Google reCAPTCHA di un form pubblico: un `div.g-recaptcha` con la chiave del sito (`Credentials::api()->g_recaptcha_site_key`), il tema, la dimensione e l\'azione in `data-wi-*`; la lib carica lo script e la verifica lato server è in `Wonder\\Security`. Nel catalogo la chiave non c\'è: il contenitore resta vuoto. Nelle Resource: `FormField::key(\'nome\')->recaptcha()`.')
    ->uses(FormField::class)
    ->docs('servizi/recaptcha.md', 'reCAPTCHA')
    ->related('input-accept-document')
    ->note('wonder', 'Esiste solo nel frontend.')
    ->example('Base', <<<'PHP'
    new reCAPTCHA()
    PHP, 'Il nome di default è `g-recaptcha`.')
    ->example('Tema, dimensione e azione', <<<'PHP'
    (new reCAPTCHA('captcha'))
        ->theme('dark')
        ->size('compact')
        ->action('contact')
    PHP)
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('g-recaptcha')->recaptcha('signup')
    PHP);
