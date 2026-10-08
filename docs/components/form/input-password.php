<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Form\Components\InputPassword;

return ComponentDoc::for(InputPassword::class)
    ->title('Input password')
    ->group('text')
    ->order(30)
    ->tags('password', 'policy', 'sicurezza', 'accesso', 'input')
    ->description('Il campo per una password, con la policy dichiarata in modo fluente: `minLength()`, `requireUppercase()`, `requireLowercase()`, `requireNumber()` e `requireSpecial()` finiscono in `password_rules`, le stesse regole che `formToArray()` verifica nel server con `PasswordPolicyValidator`. Nel frontend il tema Wonder aggiunge l\'icona occhio per mostrare la password e, sotto il campo, la lista delle regole che la lib spunta mentre si scrive; nel backend è un `<input type="password">` nel `form-floating`, senza icona né lista.')
    ->uses(Container::class, FormField::class)
    ->docs('concetti/form/form-field.md#password-policy', 'FormField: password policy')
    ->docs('concetti/utenti/auth-frontend.md', 'Auth frontend e pannello account')
    ->related('input-text', 'input-email')
    ->note('wonder', 'Il container ha la classe `wi-input-icon-end` per l\'icona `bi-eye` (`togglePassword()` della lib); le regole escono come `<ul class="wi-password-rules" data-wi-password-rules>`, con le etichette tradotte da `forms.password_rules.*`.')
    ->note('bootstrap', 'Rende con il renderer dell\'`InputText`: nessuna icona e nessuna lista delle regole. La policy vale comunque al salvataggio, perché la verifica è nel server.')
    ->example('Base', <<<'PHP'
    (new InputPassword('password'))
        ->label('Password')
        ->required()
    PHP)
    ->example('Con policy', <<<'PHP'
    (new InputPassword('password'))
        ->label('Nuova password')
        ->autocomplete('new-password')
        ->minLength(8)
        ->requireUppercase()
        ->requireNumber()
        ->requireSpecial()
    PHP, 'Ogni regola è una voce della lista nel tema Wonder; `autocomplete(\'new-password\')` evita che il browser proponga la password salvata, come chiede la barra di salvataggio del backend.')
    ->example('Password e conferma', <<<'PHP'
    (new Container())->gap(3)->components([
        (new InputPassword('password'))->label('Password')->minLength(8),
        (new InputPassword('password_confirm'))->label('Ripeti la password'),
    ])
    PHP, 'Due campi uno sotto l\'altro: il `Container` con `gap()` li distanzia in entrambi i temi. Il confronto fra i due valori resta a carico del server.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('password')->password()
        ->label('Password')
        ->minLength(8)
        ->requireNumber()
        ->requireSpecial()
    PHP, '`password()` ritorna `Inputs\InputPassword`: la policy va in `prepare[\'password_rules\']`, letta sia dal render sia dalla validazione in `formToArray()`.');
