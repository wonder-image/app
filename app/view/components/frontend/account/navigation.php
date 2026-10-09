<nav aria-label="<?=e((string) __t('account.navigation.label'))?>">
    <ul class="wi-side-nav__list">
        <?php foreach ((array) ($items ?? []) as $item): ?>
            <li>
                <a class="wi-side-nav__link" href="<?=e((string) ($item['href'] ?? ''))?>"<?=!empty($item['active']) ? ' aria-current="page"' : ''?>>
                    <i class="wi-side-nav__icon <?=e((string) ($item['icon'] ?? ''))?>" aria-hidden="true"></i>
                    <span><?=e((string) ($item['label'] ?? ''))?></span>
                </a>
            </li>
        <?php endforeach; ?>
        <?php if (!empty($logout_url)): ?>
            <li>
                <form id="logout" class="wi-side-nav__form" method="post" action="<?=e((string) $logout_url)?>">
                    <input type="hidden" name="csrf_token" value="<?=e((string) ($logout_token ?? ''))?>">
                    <button type="submit" class="wi-side-nav__link wi-input-submit">
                        <i class="wi-side-nav__icon bi bi-box-arrow-right" aria-hidden="true"></i>
                        <span><?=e((string) __t('account.navigation.logout'))?></span>
                    </button>
                </form>
            </li>
        <?php endif; ?>
    </ul>
</nav>
