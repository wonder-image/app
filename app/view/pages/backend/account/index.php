<?php \Wonder\View\View::layout('backend.main'); ?>
<div class="row g-3">

    <wi-card class="col-12">
        <h3>Impostazioni account</h3>
    </wi-card>

    <form class="col-9" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
        <wi-card class="col-12">
            <div class="col-12">
                <h6>Modifica dati</h6>
            </div>
            <div class="col-3">
                <div class="row g-3">
                    <div class="col-12">
                        <?=inputFileDragDrop('', 'profile_picture', 'profile', 'image')?>
                    </div>
                </div>
            </div>
            <div class="col-9">
                <div class="row g-3">
                    <div class="col-5">
                        <?=text('Nome', 'name', 'required'); ?>
                    </div>
                    <div class="col-5">
                        <?=text('Cognome', 'surname', 'required'); ?>
                    </div>
                    <div class="col-6">
                        <?=text('Username', 'username', 'required'); ?>
                    </div>
                    <div class="col-6">
                        <?=phone('Cellulare', 'phone'); ?>
                    </div>
                    <div class="col-4">
                        <?=select('Colore', 'color', $COLOR_OPTIONS); ?>
                    </div>
                    <div class="col-6">
                        <?=email('Email', 'email', 'required'); ?>
                    </div>
                    <div class="col-6">
                        <?=password('Password', 'password', 'required', ''); ?>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <?=submit('Modifica dati', 'modify'); ?>
            </div>
        </wi-card>
    </form>

    <form class="col-3" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
        <wi-card class="col-12">
            <div class="col-12">
                <h6>Modifica password</h6>
            </div>
            <div class="col-12">
                <?=password('Vecchia password', 'old-password', 'required', '')?>
            </div>
            <div class="col-12">
                <?=password('Nuova password', 'new-password', 'required', '')?>
            </div>
            <div class="col-12">
                <?=submit('Modifica password', 'modify-password'); ?>
            </div>
        </wi-card>
    </form>

</div>
<?php \Wonder\View\View::end(); ?>
