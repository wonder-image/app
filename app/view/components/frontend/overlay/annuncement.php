<?php

use Wonder\App\Models\Communications\Announcement;

$announcements = Announcement::safeFind(['visible' => 'true'], null, 'position', 'ASC');

if (!is_array($announcements) || $announcements === []) {
    return;
}

$swiperId = 'swiper-header-'.strtolower(code(6, 'letters'));
?>
<section class="p-a ph-2 bg-primary tx-white" style="z-index: 90; margin-top: var(--header-height);">
    <div class="content">
        <div class="text a-c w-100 swiper <?=e($swiperId)?>">
            <div class="swiper-wrapper">
                <?php foreach ($announcements as $row) { ?>
                    <div class="swiper-slide max-line-1 c-pointer"><?= (string) ($row['text'] ?? '') ?></div>
                <?php } ?>
            </div>
        </div>
    </div>
</section>

<script>
    new Swiper(<?=json_encode('.'.$swiperId)?>, {
        slidesPerView: 1,
        loop: true,
        autoplay: {
            delay: 2000,
        },
    });
</script>
