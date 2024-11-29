<?php

    function responsiveGallery($ARRAY_IMAGES, $GAP = 6, $DOWNLOAD = false) {

        $RETURN = "";

        $id = "gallery-".code(5);

        $colDesktop = 4;
        $colTablet = 3;
        $colMobile = 2;

        $IMG = [];
        
        for ($i=0; $i < $colDesktop; $i++) { 
            
            if ($i <= $colDesktop) {$IMG['desktop'][$i] = [];}
            if ($i <= $colTablet) {$IMG['tablet'][$i] = [];}
            if ($i <= $colMobile) {$IMG['mobile'][$i] = [];}

        }

        $desktop = 0;
        $tablet = 0;
        $mobile = 0;

        $i = 0;

        $RETURN .= "<div class='d-none'>";

        foreach ($ARRAY_IMAGES as $key => $value) {

            $value['position'] = $i;

            $srcPrewiew = $value['src'];
            $src = isset($value['src-original']) ? $value['src-original'] : $srcPrewiew;
            $alt = $value['alt'];
            $caption = isset($value['caption']) ? "data-caption='".$value['caption']."'" : '';
            
            array_push($IMG['desktop'][$desktop], $value);
            array_push($IMG['tablet'][$tablet], $value);
            array_push($IMG['mobile'][$mobile], $value);

            $desktop++;
            $tablet++;
            $mobile++;
            $i++;

            if ($desktop == $colDesktop) {$desktop = 0;}
            if ($tablet == $colTablet) {$tablet = 0;}
            if ($mobile == $colMobile) {$mobile = 0;}

            $RETURN .= "<a data-fancybox='$id' data-src='$src' $caption></a>";
            
        }

        $RETURN .= "</div>";

        $RETURN .= "<div class='w-100 d-grid col-$colDesktop col-t-$colTablet col-p-$colMobile gap-$GAP'>";

        for ($i=0; $i < $colDesktop; $i++) { 
            
            
            $RETURN .= "<div class='w-100 tablet-none'>
            <div class='w-100 d-grid col-1 gap-$GAP'>";

            foreach ($IMG['desktop'][$i] as $value) { 

                $src = $value['src'];
                $alt = $value['alt'];
                $position = $value['position'];
                $class = isset($value['class']) ? $value['class'] : '';
                $attributes = isset($value['attributes']) ? $value['attributes'] : '';

                $RETURN .= "
                <a href='javascript:;' data-fancybox-trigger='$id' data-fancybox-index='$position' class='col-1 h-fit' $attributes>
                    <img src='$src' alt='$alt' class='p-r f-start w-100 skeleton $class' loading='lazy' style='min-height: 120px'>
                </a>";

            }

            $RETURN .= "</div>
            </div>";

        }

        for ($i=0; $i < $colTablet; $i++) { 
            
            
            $RETURN .= "<div class='w-100 pc-none phone-none'>
            <div class='w-100 d-grid col-1 gap-$GAP'>";
            
            foreach ($IMG['tablet'][$i] as $value) { 

                $src = $value['src'];
                $alt = $value['alt'];
                $position = $value['position'];
                $class = isset($value['class']) ? $value['class'] : '';
                $attributes = isset($value['attributes']) ? $value['attributes'] : '';

                $RETURN .= "
                <a href='javascript:;' data-fancybox-trigger='$id' data-fancybox-index='$position' class='col-1 h-fit' $attributes>
                    <img src='$src' alt='$alt' class='p-r f-start w-100 skeleton $class' loading='lazy' style='min-height: 120px'>
                </a>"; 
            
            }

            $RETURN .= "</div>
            </div>";

        }

        for ($i=0; $i < $colMobile; $i++) { 
            
            
            $RETURN .= "<div class='w-100 pc-none tablet-none h-fit'>
            <div class='w-100 d-grid col-1 gap-$GAP'>";
            
            foreach ($IMG['mobile'][$i] as $value) { 

                $src = $value['src'];
                $alt = $value['alt'];
                $position = $value['position'];
                $class = isset($value['class']) ? $value['class'] : '';
                $attributes = isset($value['attributes']) ? $value['attributes'] : '';

                $RETURN .= "
                <a href='javascript:;' data-fancybox-trigger='$id' data-fancybox-index='$position' class='col-1 h-fit' $attributes>
                    <img src='$src' alt='$alt' class='p-r f-start w-100 skeleton $class' loading='lazy' style='min-height: 80px'>
                </a>"; 
            
            }

            $RETURN .= "</div>
            </div>";

        }

        $FANCYBOX = "{";

        if ($DOWNLOAD) {

            $FANCYBOX .= "
            buttons : [
                'download',
                'thumbs',
                'close'
            ]";

        }

        $FANCYBOX .= "}";

        $RETURN .= "</div>
        <script>
            Fancybox.bind('[data-fancybox=\"$id\"]', $FANCYBOX);
        </script>";

        return $RETURN;

    }