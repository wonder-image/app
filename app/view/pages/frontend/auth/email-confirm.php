<?php
use Wonder\Elements\Components\Button;
use Wonder\Http\Csrf;
use Wonder\View\View;
// La GET del link non cambia niente: il cambio avviene con il clic su questo bottone (POST).
$auth_profile->layout([
    'title' => (string) __t('account.email.confirm_title'),
    'text' => (string) __t('account.email.confirm_intro', ['email' => $email]),
]);
?>
<form method="post" action="<?=e($action)?>" class="w-100 d-grid col-1 gap-6 mt-6">
    <?=Csrf::field()->render()?>
    <input type="hidden" name="token" value="<?=e($token)?>">
    <?=Button::make((string) __t('account.email.confirm_button'))->type('submit')->addClass('w-100 wi-input-submit wi-submit')->render()?>
</form>
<?php View::end(); ?>
