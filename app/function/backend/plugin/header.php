<?php

    function sidebarOffcanvas($id, $title, $list) {

        $id = (string) $id;
        $title = (string) $title;
        $renderItems = static function (array $items, int $depth = 0) use (&$renderItems): string {
            $markup = '';

            foreach ($items as $value) {
                if (!is_array($value)) {
                    continue;
                }

                $listTitle = (string) ($value['title'] ?? '');
                $children = (array) ($value['children'] ?? []);

                if ($children !== []) {
                    $padding = $depth > 0 ? 'ps-3' : 'ps-0';
                    $titleClass = !empty($value['active']) ? 'text-body-emphasis' : 'text-body-secondary';
                    $markup .= "<li class='list-group-item border-0 m-0 p-0 w-100 float-none'>"
                        ."<div class='small text-uppercase fw-semibold pt-3 pb-1 ".e($titleClass).' '.e($padding)."'>".e($listTitle)."</div>"
                        ."<ul class='list-group list-group-flush mt-0 w-100'>".$renderItems($children, $depth + 1)."</ul>"
                        ."</li>";
                    continue;
                }

                $listHref = (string) ($value['link'] ?? '');
                $listActive = (bool) ($value['active'] ?? false);
                $listClass = $listActive ? 'fw-semibold text-body-emphasis' : 'text-secondary';
                $padding = $depth > 0 ? 'ps-3' : 'ps-0';
                $label = $listActive ? '• '.$listTitle : $listTitle;

                $markup .= "<li class='list-group-item border-0 m-0 p-0 w-100 float-none'>"
                    ."<a href='".e($listHref)."' class='d-block w-100 m-0 py-2 pe-0 float-none ".e($padding).' '.e($listClass)." text-decoration-none'>".e($label)."</a>"
                    ."</li>";
            }

            return $markup;
        };
        $listGroup = $renderItems((array) $list);

        return "
        <div class='offcanvas offcanvas-start border-end' tabindex='-1' id='".e($id)."' data-bs-scroll='false' data-bs-backdrop='true'>
            <div class='offcanvas-header'>
                <h5 class='offcanvas-title'>".e($title)."</h5>
            </div>
            <div class='offcanvas-body pt-0'>
                <ul class='list-group list-group-flush mt-0 w-100'>$listGroup</ul>
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
