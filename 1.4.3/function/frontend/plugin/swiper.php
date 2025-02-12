<?php

    function swiper_fashionSliderSlide($image) {

        $src = $image['src'];
        $color = isset($image['color']) ? "data-slide-bg-color='{$image['color']}'" : "";
        $title = $image['title'];

        return "
        <div class='swiper-slide' $color>
            <!-- slide title wrap -->
            <div class='fashion-slider-title' data-swiper-parallax='-130%'>
                <!-- slide title text -->
                <div class='fashion-slider-title-text'> $title </div>
            </div>
            <!-- slide image wrap -->
            <div class='fashion-slider-scale'>
                <!-- slide image -->
                <img src='$src'>
            </div>
        </div>";

    }

    function swiper_fashionSlider( array $images, int | bool $autoplayDelay = false) {

        $id = code(5, 'all', 'swiper-');
        $slide = "";

        foreach ($images as $image) { $slide .= swiper_fashionSliderSlide($image); }

        return "
        <div id='$id' class='fashion-slider'>
            <div class='swiper' data-swiper-autoplay-delay='$autoplayDelay'> 
                <div class='swiper-wrapper'> $slide </div>
                <!-- right/next navigation button -->
                <div class='fashion-slider-button-prev fashion-slider-button'>
                    <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 350 160 90'>
                        <g class='fashion-slider-svg-wrap'>
                            <g class='fashion-slider-svg-circle-wrap'>
                                <circle cx='42' cy='42' r='40'></circle>
                            </g>
                            <path class='fashion-slider-svg-arrow' d='M.983,6.929,4.447,3.464.983,0,0,.983,2.482,3.464,0,5.946Z'></path>
                            <path class='fashion-slider-svg-line' d='M80,0H0'></path>
                        </g>
                    </svg>
                </div>
                <!-- left/previous navigation button -->
                <div class='fashion-slider-button-next fashion-slider-button'>
                    <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 350 160 90'>
                        <g class='fashion-slider-svg-wrap'>
                            <g class='fashion-slider-svg-circle-wrap'>
                                <circle cx='42' cy='42' r='40'></circle>
                            </g>
                            <path class='fashion-slider-svg-arrow' d='M.983,6.929,4.447,3.464.983,0,0,.983,2.482,3.464,0,5.946Z'></path>
                            <path class='fashion-slider-svg-line' d='M80,0H0'></path>
                        </g>
                    </svg>
                </div>
            </div>
        </div>
        <script> window.addEventListener('loaded', () => { createFashionSlider(document.getElementById('$id')); }); </script>";

    }