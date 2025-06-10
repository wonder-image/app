<?php

    function sidebarOffcanvas($id, $title, $list) {

        $listGroup = "";

        foreach ($list as $key => $value) {

            $listTitle = $value['title'];
            $listHref = $value['link'];
            $listActive = $value['active'];

            if ($listActive) {
                $listClass = "fw-semibold";
                $listTitle = ' • '.$listTitle;
            } else {
                $listClass = "";
            }
            
            $listGroup .= "<a href='$listHref' class='list-group-item ps-0 $listClass text-secondary text-decoration-none'>$listTitle</a>";

        }

        return "
        <div class='offcanvas offcanvas-start border-end' tabindex='-1' id='$id' data-bs-scroll='false' data-bs-backdrop='true'>
            <div class='offcanvas-header'>
                <h5 class='offcanvas-title'>$title</h5>
            </div>
            <div class='offcanvas-body pt-0'>
                <ul class='list-group list-group-flush'>$listGroup</ul>
            </div>
        </div>
        <script>
            document.getElementById('$id').addEventListener('hide.bs.offcanvas', event => {
                document.querySelector('a[data-bs-target=\"#$id\"]').parentElement.classList.remove('active-offcanvas');
            });
            document.getElementById('$id').addEventListener('show.bs.offcanvas', event => {
                document.querySelector('a[data-bs-target=\"#$id\"]').parentElement.classList.add('active-offcanvas');
            });
        </script>";

    }