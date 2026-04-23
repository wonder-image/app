<?php

use Wonder\Plugin\Custom\Input\SortableInput;

\Wonder\View\View::layout('backend.form');

$selectedActive = (string) ($VALUES['active'] ?? 'true');
$renderInput = static function (string $key, mixed $value = null) use ($RESOURCE_CLASS, $VALUES): string {
    $field = $RESOURCE_CLASS::getInput($key);

    if ($value !== null) {
        $field->value($value);
    } elseif (array_key_exists($key, (array) $VALUES)) {
        $field->value($VALUES[$key]);
    }

    return $field->render();
};
?>

<form class="col-12" action="<?=htmlspecialchars($FORM_ACTION, ENT_QUOTES, 'UTF-8')?>" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
    <input type="hidden" name="area" value="<?=htmlspecialchars($USER_AREA, ENT_QUOTES, 'UTF-8')?>">
    <input type="hidden" name="password" value="<?=code(2, 'letters').'-'.code(4, 'numbers');?>">

    <div class="row g-3">
        <div class="col-9">
            <div class="row g-3">

                <?php if (!empty($ALLOW_EXISTING_USER) && !empty($EXISTING_USER_OPTIONS)) { ?>
                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Crea utente da email già esistente</h6>
                        </div>
                        <div class="col-6">
                            <?=check('Username', 'user_id', $EXISTING_USER_OPTIONS, "onclick=\"if (this.value != '') { disableInput('user'); } else { enabledInput('user'); }\"", 'radio', true);?>
                        </div>
                    </wi-card>
                <?php } ?>

                <wi-card class="col-12">
                    <div class="col-3">
                        <div class="row g-3">
                            <div class="col-12">
                                <?=$renderInput('profile_picture')?>
                            </div>
                        </div>
                    </div>
                    <div class="col-9">
                        <div class="row g-3">
                            <div class="col-4">
                                <?=$renderInput('name')?>
                            </div>
                            <div class="col-4">
                                <?=$renderInput('surname')?>
                            </div>
                            <div class="col-4">
                                <?=$renderInput('color')?>
                            </div>
                            <div class="col-6">
                                <?=$renderInput('username')?>
                            </div>
                            <div class="col-6">
                                <?=$renderInput('phone')?>
                            </div>
                            <div class="col-12">
                                <?=$renderInput('email')?>
                            </div>
                        </div>
                    </div>
                </wi-card>

            </div>
        </div>

        <div class="col-3">
            <div class="row g-3">

                <?php if (!empty($SHOW_API_FIELDS)) { ?>
                    <wi-card class="col-12">
                        <?php
                            $domains = new SortableInput(
                                'allowed_domains',
                                isset($VALUES['allowed_domains'])
                                    ? array_map(static fn ($domain) => ['allowed_domains' => $domain], (array) json_decode((string) $VALUES['allowed_domains'], true))
                                    : null
                            );
                            $domains->Title('Domini');
                            $domains->Position(false);
                            $domains->Column('allowed_domains', 'Dominio', 'text', null, 11);
                            echo $domains->Generate();
                        ?>
                    </wi-card>

                    <wi-card class="col-12">
                        <?php
                            $ips = new SortableInput(
                                'allowed_ips',
                                isset($VALUES['allowed_ips'])
                                    ? array_map(static fn ($ip) => ['allowed_ips' => $ip], (array) json_decode((string) $VALUES['allowed_ips'], true))
                                    : null
                            );
                            $ips->Title('Indirizzi IP');
                            $ips->Position(false);
                            $ips->Column('allowed_ips', 'Ip', 'text', null, 11);
                            echo $ips->Generate();
                        ?>
                    </wi-card>
                <?php } ?>

                <wi-card class="col-12">
                    <div class="col-12">
                        <?=$renderInput('authority', $SELECTED_AUTHORITY)?>
                    </div>
                    <div class="col-12">
                        <?=$renderInput('active', $selectedActive)?>
                    </div>
                    <div class="col-12">
                        <?=submitAdd()?>
                    </div>
                </wi-card>

            </div>
        </div>
    </div>
</form>

<?php \Wonder\View\View::end(); ?>
