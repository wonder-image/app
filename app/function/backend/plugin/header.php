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
                    $collapseId = code(10, 'numbers', 'navgrp-');
                    $titleClass = !empty($value['active']) ? 'text-body-emphasis' : 'text-body-secondary';
                    $markup .= "<li class='list-group-item border-0 m-0 p-0 w-100 float-none'>"
                        ."<button type='button' class='be-nav-toggle be-nav-heading d-flex align-items-center gap-2 w-100 border-0 bg-transparent text-start text-uppercase fw-semibold mt-2 mb-1 py-1 px-2 ".e($titleClass)."' data-bs-toggle='collapse' data-bs-target='#".e($collapseId)."' aria-expanded='true' aria-controls='".e($collapseId)."'>"
                        ."<i class='bi bi-chevron-right be-nav-chev'></i><span>".e($listTitle)."</span>"
                        ."</button>"
                        ."<div class='collapse show' id='".e($collapseId)."'>"
                        ."<ul class='list-group list-group-flush mt-0 w-100'>".$renderItems($children, $depth + 1)."</ul>"
                        ."</div>"
                        ."</li>";
                    continue;
                }

                $listHref = (string) ($value['link'] ?? '');
                $listActive = (bool) ($value['active'] ?? false);
                $stateClass = $listActive ? 'fw-semibold text-body-emphasis be-nav-active' : 'text-secondary';
                $padding = $depth > 0 ? 'ps-4' : 'ps-2';

                $markup .= "<li class='list-group-item border-0 m-0 p-0 w-100 float-none'>"
                    ."<a href='".e($listHref)."' class='be-nav-link d-block w-100 m-0 py-1 pe-2 float-none ".e($padding).' '.e($stateClass)." text-decoration-none'>".e($listTitle)."</a>"
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
            <div class='offcanvas-body pt-2 px-2'>
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
