<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Alert;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Modal;
use Wonder\Elements\Components\Text;
use Wonder\Elements\Form\Components\InputText;

return ComponentDoc::for(Modal::class)
    ->title('Modal')
    ->group('layout')
    ->order(40)
    ->tags('finestra', 'dialogo', 'popup', 'form')
    ->description('Una finestra di dialogo con titolo, corpo a griglia e bottoni in fondo. La apre un `Button::opensModal($id)`. Con `form()` il corpo e i bottoni stanno in un `<form>` con token CSRF e campi nascosti, e arrivano da soli Annulla e Salva; va resa fuori da ogni altro form.')
    ->uses(Alert::class, Button::class, Text::class, InputText::class)
    ->docs('concetti/componenti/README.md#modal', 'Componenti UI')
    ->docs('concetti/form/csrf.md', 'Token CSRF')
    ->related('button', 'accordion', 'quick-create-button')
    ->note('bootstrap', 'Markup `.modal.fade[data-wi-modal-detach]`: uno script, una volta per pagina, sposta la finestra in fondo al `body` così i suoi campi non partono con il form della scheda.')
    ->note('wonder', 'La finestra esce solo con `frontend()` e un `id()` esplicito, con la `wi-modal` della lib; senza `frontend()` il renderer ritorna una stringa vuota.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            [
                Button::make('Apri la finestra')->opensModal('demo-modal'),
                Modal::make('Dettagli ordine')
                    ->id('demo-modal')
                    ->frontend()
                    ->components([
                        Alert::make('Il contenuto della finestra: testo, campi o altri componenti.', 'info')->dismissible(false),
                    ])
                    ->cancel('Chiudi'),
            ]
            PHP)
            ->description('Il bottone porta `data-bs-target` in Bootstrap e `data-wi-modal-target` in Wonder; `frontend()` serve solo al tema Wonder e nel backend non cambia nulla.')
            ->height(120)
    )
    ->example(
        Example::make('Con form, campi a griglia e bottoni')
            ->code(<<<'PHP'
            [
                Button::make('Registra pagamento')->variant('success')->opensModal('pay-modal'),
                Modal::make('Registra pagamento')
                    ->id('pay-modal')
                    ->frontend()
                    ->size('lg')
                    ->help('Il pagamento resta modificabile fino alla chiusura.')
                    ->form(action: '/backend/orders/12/pay/', hidden: ['order_id' => 12])
                    ->columns(12)
                    ->components([
                        (new InputText('amount'))->label('Importo')->required()->columnSpan(6),
                        (new InputText('reference'))->label('Riferimento')->columnSpan(6),
                    ])
                    ->cancel('Indietro')
                    ->submit('Registra', variant: 'success'),
            ]
            PHP)
            ->description('`form()` incarta corpo e bottoni in un `<form method="post">`; `help()` mette un tooltip accanto al titolo; `size()` accetta `sm`, `lg` e `xl`.')
            ->height(120)
    )
    ->example(
        Example::make('Bottoni personalizzati e corpo scorrevole')
            ->code(<<<'PHP'
            [
                Button::make('Apri')->variant('secondary')->outline()->opensModal('long-modal'),
                Modal::make('Termini del servizio')
                    ->id('long-modal')
                    ->scrollable()
                    ->components([
                        Text::make(str_repeat('Testo lungo che scorre dentro la finestra. ', 40)),
                    ])
                    ->footer([
                        Button::make('Rifiuta')->variant('secondary')->attr('data-bs-dismiss', 'modal'),
                        Button::make('Accetto')->variant('primary'),
                    ]),
            ]
            PHP)
            ->description('`footer()` sostituisce Annulla e Salva con i bottoni dati; `scrollable()` tiene fermi intestazione e fondo.')
            ->themes('bootstrap')
            ->height(120)
    );
