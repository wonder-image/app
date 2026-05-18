<?php

    function sidebarOffcanvas($id, $title, $list) {

        $id = (string) $id;
        $title = (string) $title;
        $listGroup = '';

        foreach ($list as $key => $value) {

            $listTitle = (string) ($value['title'] ?? '');
            $listHref = (string) ($value['link'] ?? '');
            $listActive = (bool) ($value['active'] ?? false);

            if ($listActive) {
                $listClass = 'fw-semibold';
                $listTitle = ' • '.$listTitle;
            } else {
                $listClass = '';
            }

            $listGroup .= "<a href='".e($listHref)."' class='list-group-item ps-0 ".e($listClass)." text-secondary text-decoration-none'>".e($listTitle)."</a>";

        }

        return "
        <div class='offcanvas offcanvas-start border-end' tabindex='-1' id='".e($id)."' data-bs-scroll='false' data-bs-backdrop='true'>
            <div class='offcanvas-header'>
                <h5 class='offcanvas-title'>".e($title)."</h5>
            </div>
            <div class='offcanvas-body pt-0'>
                <ul class='list-group list-group-flush'>$listGroup</ul>
            </div>
        </div>
        <script>
            document.getElementById(".json_encode($id, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).").addEventListener('hide.bs.offcanvas', event => {
                document.querySelector('a[data-bs-target=\"#".e($id)."\"]').parentElement.classList.remove('active-offcanvas');
            });
            document.getElementById(".json_encode($id, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).").addEventListener('show.bs.offcanvas', event => {
                document.querySelector('a[data-bs-target=\"#".e($id)."\"]').parentElement.classList.add('active-offcanvas');
            });
        </script>";

    }
