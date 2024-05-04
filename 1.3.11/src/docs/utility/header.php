<header class="ph-4 bg-white">
    <div class="content">

        <img class="h-80 c-h" src="<?=$PATH->app?>/assets/logos/Wonder-Image.png" alt="Wonder Image">

        <div id="hamburger" class="c-h f-end" onclick="menu()">
            <div class="bar bar-1 bg-dark"></div>
            <div class="bar bar-2 bg-dark"></div>
            <div class="bar bar-3 bg-dark"></div>
            <div class="bar bar-4 bg-dark"></div>
            <div class="bar bar-5 bg-dark"></div>
        </div>

    </div>
</header>

<section class="intro">
    <div class="content">

        <div id="left-header" class="d-flex d-column col-1 gap-2 max-h-100">
            <?php

                $ARRAY_HEADER = [
                    [
                        "title" => "Programmazione",
                        "subtitle" => "",
                        "subnavs" => [
                            [
                                "title" => "Stile",
                                "description" => "",
                                "subnavs" => [
                                    [
                                        "title" => "Bottoni",
                                        "url" => "$PATH->site/docs/button/"
                                    ],
                                    [
                                        "title" => "Testi",
                                        "url" => "$PATH->site/docs/text/"
                                    ],
                                    [
                                        "title" => "Input",
                                        "url" => "$PATH->site/docs/input/"
                                    ]
                                ]
                            ], [
                                "title" => "Comunicazioni",
                                "description" => "",
                                "subnavs" => [
                                    [
                                        "title" => "Alert",
                                        "url" => "$PATH->site/docs/alert/"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];

                foreach ($ARRAY_HEADER as $key => $category) {
                    
                    $categoryTitle = $category['title'];
                    $categorySubnavs = $category['subnavs'];

                    $categoryActive = false;
                    $subcategoryHTML = "";

                    foreach ($categorySubnavs as $key => $subcategory) {

                        $subcategoryTitle = $subcategory['title'];
                        $subcategorySubnavs = $subcategory['subnavs'];

                        $subcategoryActive = "";
                        $articleHTML = "";

                        foreach ($subcategorySubnavs as $key => $article) {

                            $title = $article['title'];
                            $url = $article['url'];

                            $active = "";

                            if ($url == $PAGE->url) { 
                                $categoryActive = true; 
                                $subcategoryActive = "wi-show"; 
                                $active = "active";
                            }

                            $articleHTML .= '<a href="'.$url.'" class="p-r f-start w-100 tx-none '.$active.'"> '.$title.' </a>';

                        }

                        $subcategoryHTML .= '
                        <div class="w-100">
                            <div class="wi-dropdown-box '.$subcategoryActive.' b-0 p-0">
                                <div class="wi-dropdown-title wi-switcher p-r f-start tx-none">
                                    '.$subcategoryTitle.' <i class="bi bi-chevron-down"></i>
                                </div>   
                                <div class="wi-dropdown-content pl-2">
                                    '.$articleHTML.'
                                </div>
                            </div>
                        </div>';

                    }

                    $categoryActive = ($categoryActive == true) ? "wi-show" : ""; 

                    echo '
                    <div class="wi-dropdown-box '.$categoryActive.' b-0 p-0">
                        <div class="wi-dropdown-title wi-switcher fw-400"> '.strtoupper($categoryTitle).' <i class="bi bi-chevron-down"></i> </div>   
                        <div class="wi-dropdown-content pl-2 gap-1"> '.$subcategoryHTML.'</div>
                    </div>';

                }

            ?>

        </div>

        <div class="wrapped">
            