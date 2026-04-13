<?php \Wonder\View\View::layout('backend.main'); ?>
<div class="row g-3">

    <wi-card class="col-3">

        <?php

            foreach ($NAV_BACKEND as $navs) {

                $titleNav = isset($navs['title']) ? $navs['title'] : 'ND';
                $folderNav = isset($navs['folder']) ? $navs['folder'] : 'home';
                $iconNav = isset($navs['icon']) ? $navs['icon'] : 'bi-bug';
                $fileNav = isset($navs['file']) ? $navs['file'] : '';
                $authNav = isset($navs['authority']) ? $navs['authority'] : [];
                $subNav = isset($navs['subnavs']) ? $navs['subnavs'] : [];

                if ($titleNav != 'Home') {
                    if (!$authNav || count(array_intersect($authNav, $USER->authority)) >= 1) {

                        if (empty($subNav)) {

                            $subnavsList = '';

                        }else{
                            
                            $subNavs = "";

                            foreach ($subNav as $sub) {
                                
                                $titleSub = isset($sub['title']) ? $sub['title'] : 'ND';
                                $folderSub = isset($sub['folder']) ? $sub['folder'] : 'home';
                                $authSub = isset($sub['authority']) ? $sub['authority'] : [];
                                $fileSub = isset($sub['file']) ? $sub['file'] : '';

                                if (!$authSub || count(array_intersect($authSub, $USER->authority)) >= 1) {
                                    $subNavs .= "<a class='list-group-item list-group-item-action' href='$PATH->backend/$folderSub/$fileSub'>$titleSub <i class='bi bi-chevron-right float-end'></i></a>";
                                }
                                
                            }

                            $subnavsList = "$subNavs";

                        }

                        if (!empty($subnavsList)) {

                            echo "
                            <div class='list-group ps-2'>
                                <li class='list-group-item list-group-item-dark'><i class='bi $iconNav'></i> $titleNav</li>
                                $subnavsList
                            </div>";

                        }else{

                            echo "
                            <div class='list-group ps-2'>
                                <a href='$PATH->backend/$folderNav/$fileNav' type='button' class='list-group-item list-group-item-dark list-group-item-action'><i class='bi $iconNav mr-2'></i> $titleNav <i class='bi bi-chevron-right float-end'></i></a>
                            </div>";

                        }

                    }
                }

            }

        ?>
    </wi-card>

    <div class="col-9">
        <div class="row g-3">

            <?php if (in_array('admin', $USER->authority, true)) { ?>
            <wi-card class="col-12">
                <div class="col-12">
                    <h6>Update applicativo</h6>
                </div>
                <?php if (is_array($UPDATE_RESULT)) { ?>
                <div class="col-12">
                    <?php
                        $icon = !empty($UPDATE_RESULT['success']) ? 'bi-check2 text-success' : 'bi-x-lg text-danger';
                        $message = htmlspecialchars((string) ($UPDATE_RESULT['message'] ?? $UPDATE_RESULT['response'] ?? 'Operazione conclusa.'), ENT_QUOTES, 'UTF-8');
                    ?>
                    <p class="mb-2"><i class="bi <?=$icon?> me-1"></i><?=$message?></p>
                    <?php if (!empty($UPDATE_RESULT['release_id'])) { ?>
                    <p class="mb-1"><b>Release:</b> <?=htmlspecialchars((string) $UPDATE_RESULT['release_id'], ENT_QUOTES, 'UTF-8')?></p>
                    <?php } ?>
                    <?php if (isset($UPDATE_RESULT['stats']) && is_array($UPDATE_RESULT['stats'])) { ?>
                    <p class="mb-0">
                        <b>Tabelle:</b> <?= (int) ($UPDATE_RESULT['stats']['tables'] ?? 0) ?>
                        |
                        <b>Row:</b> <?= (int) ($UPDATE_RESULT['stats']['rows'] ?? 0) ?>
                        |
                        <b>Page:</b> <?= (int) ($UPDATE_RESULT['stats']['pages'] ?? 0) ?>
                    </p>
                    <?php } ?>
                </div>
                <?php } ?>
                <form method="post" class="col-12 mt-2">
                    <div class="row g-3 align-items-end">
                        <div class="col-8">
                            <label for="release_id" class="form-label">Release ID</label>
                            <input type="text" class="form-control" id="release_id" name="release_id" placeholder="es. commit SHA o tag deploy">
                        </div>
                        <div class="col-4">
                            <button type="submit" name="run_app_update" value="true" class="btn btn-dark w-100">Esegui update</button>
                        </div>
                    </div>
                </form>
            </wi-card>
            <?php } ?>

            <wi-card class="col-12">
                <div class="col-12">
                    <h6>Contatti</h6>
                </div>
                <div class="col-12">
                    Cellulare: <a href="tel:393911220336" target="_blank" rel="noopener noreferrer">391 1220336</a> <br>
                    Whatsapp:  <a href="https://wa.me/3911220336?text=Ciao%20Andrea%20ho%20bisogno%20di%20un%20aiuto" target="_blank" rel="noopener noreferrer">391 1220336</a> <br>
                    Email:     <a href="mailto:marinoni@wonderimage.it" target="_blank" rel="noopener noreferrer">marinoni@wonderimage.it</a>
                </div>
            </wi-card>

        </div>
    </div>

</div>
<?php \Wonder\View\View::end(); ?>
