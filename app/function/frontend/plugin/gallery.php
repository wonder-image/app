<?php

    function cardResponsiveGallery($galleryId, $array, $format) {

        $src = $array['src'];
        $alt = $array['alt'];
        $position = $array['position'];
        $class = $array['class'] ?? '';
        $attributes = $array['attributes'] ?? '';

        if ($format == 'h-fit') {
                
            return "
            <a href='javascript:;' data-fancybox-trigger='$galleryId' data-fancybox-index='$position' class='col-1 h-fit' $attributes>
                <img src='$src' alt='$alt' class='p-r f-start w-100 skeleton $class' loading='lazy' style='min-height: 120px'>
            </a>";

        } else {

            return "
            <a href='javascript:;' data-fancybox-trigger='$galleryId' data-fancybox-index='$position' class='col-1' $attributes>
                <div class='f-$format o-hidden'>
                    <img src='$src' alt='$alt' class='bg bg-cover skeleton $class' loading='lazy'>
                </div>
            </a>";

        }

        
    }

    function responsiveGallery($ARRAY_IMAGES, $GAP = 6, $DOWNLOAD = false, $FORMAT = 'h-fit') {

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

        if (is_array($GAP)) {
            $gapDesktop = $GAP['desktop'];
            $gapTablet = $GAP['tablet'];
            $gapMobile = $GAP['mobile'];
        } else {
            $gapDesktop = $GAP;
            $gapTablet = $GAP;
            $gapMobile = $GAP;
        }

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

        $RETURN .= "<div class='w-100 d-grid col-$colDesktop col-t-$colTablet col-p-$colMobile gap-$gapDesktop gap-t-$gapTablet gap-p-$gapMobile'>";

        for ($i=0; $i < $colDesktop; $i++) { 
            
            $RETURN .= "<div class='w-100 tablet-none'>
            <div class='w-100 d-grid col-1 gap-$gapDesktop'>";

            foreach ($IMG['desktop'][$i] as $value) { $RETURN .= cardResponsiveGallery($id, $value, $FORMAT); }

            $RETURN .= "</div>
            </div>";

        }

        for ($i=0; $i < $colTablet; $i++) { 
            
            $RETURN .= "<div class='w-100 pc-none phone-none'>
            <div class='w-100 d-grid col-1 gap-$gapTablet'>";
            
            foreach ($IMG['tablet'][$i] as $value) { $RETURN .= cardResponsiveGallery($id, $value, $FORMAT); }

            $RETURN .= "</div>
            </div>";

        }

        for ($i=0; $i < $colMobile; $i++) { 
            
            $RETURN .= "<div class='w-100 pc-none tablet-none h-fit'>
            <div class='w-100 d-grid col-1 gap-$gapMobile'>";
            
            foreach ($IMG['mobile'][$i] as $value) { $RETURN .= cardResponsiveGallery($id, $value, $FORMAT); }

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