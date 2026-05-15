<?php

$id ??= '';
$tag ??= 'a';
$size ??= ''; # sm | lg
$type ??= 'primary';
$arrow ??= true;
$icon ??= true;
$iconClass ??= '';
$iconHtml ??= '';
$label ??= '';
$title ??= '';
$href ??= '#';
$onclick ??= '#';
$blank ??= false;
$class ??= '';
$attributes ??= '';

$classes = [];

if ($class !== '') {
    $classes[] = trim((string) $class);
}

$classes[] = "btn btn-{$type}";

if (!empty($size)) {
    $classes[] = "btn-{$size}";
}

if ($arrow) {
    $classes[] = 'btn-arrow';
}

if ($icon) {
    $classes[] = 'btn-icon';

    if (!empty($iconClass)) {
        $iconHtml = '<i class="'.e($iconClass).'"></i>';
    }
}

if ($blank) {
    $attributes .= ' target="_blank" rel="noopener noreferrer"';
}

if (!empty($href)) {
    $attributes .= ' href="'.e($href).'"';
}

if (!empty($onclick)) {
    if ($tag == 'a') { $tag = 'button'; }
    $attributes .= ' onclick="'.e($onclick).'"';
}

if (!empty($title)) {
    $attributes .= ' title="'.e($title).'"';
}

$class = trim(implode(' ', $classes));

?>
<<?=$tag?> 
    id="<?= $id ?>"
    class="<?= e($class) ?>" 
    <?=$attributes?>
>
    <?=$label?> <?= $iconHtml ?>
</<?=$tag?>>
