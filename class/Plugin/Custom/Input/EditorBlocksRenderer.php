<?php

namespace Wonder\Plugin\Custom\Input;

class EditorBlocksRenderer
{
    private array $config = [];

    private array $swiperInstances = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'wrapper_class' => 'w-100 mt-6',
            'border_class' => '',
            'image_class' => 'f-16-9',
            'table_alt_row_class' => 'bg-color-50',
            'list_class' => 'w-100 text',
            'code_class' => 'w-100 text b-1 p-3 bg-white-20 tx-white box-border',
            'download_card_class' => 'w-100 bg-white tx-black p-3',
            'download_button_class' => 'btn btn-sm btn-gray f-end',
            'delimiter_style' => 'height: 2px;background: var(--tx-color);',
            'render_unknown_blocks' => false,
            'unknown_block_callback' => null
        ], $config);
    }

    public static function make(mixed $payload, array $config = []): string
    {
        return (new self($config))->render($payload);
    }

    public function render(mixed $payload): string
    {
        $blocks = $this->extractBlocks($payload);

        if (empty($blocks)) {
            return '';
        }

        $output = '';

        foreach ($blocks as $block) {
            $html = $this->renderBlock($this->toArray($block));

            if ($html === '') {
                continue;
            }

            $output .= $this->wrapBlock($html);
        }

        $output .= $this->renderQueuedScripts();

        return $output;
    }

    private function extractBlocks(mixed $payload): array
    {
        if (is_string($payload)) {
            $payload = trim($payload);

            if ($payload === '') {
                return [];
            }

            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            } else {
                return [];
            }
        }

        $payload = $this->toArray($payload);

        if (empty($payload)) {
            return [];
        }

        if (isset($payload['paragraph']) && is_array($payload['paragraph'])) {
            return $payload['paragraph'];
        }

        if (isset($payload['blocks']) && is_array($payload['blocks'])) {
            return $payload['blocks'];
        }

        if (isset($payload['type']) && is_string($payload['type'])) {
            return [$payload];
        }

        return array_is_list($payload) ? $payload : [];
    }

    private function renderBlock(array $block): string
    {
        $type = strtolower((string)($block['type'] ?? ''));
        $data = $this->toArray($block['data'] ?? []);

        return match ($type) {
            'header', 'paragraph' => $this->renderTextBlock($block, $data),
            'image' => $this->renderImageBlock($data),
            'gallery' => $this->renderGalleryBlock($data),
            'video' => $this->renderVideoBlock($data),
            'embed' => $this->renderEmbedBlock($data),
            'list' => $this->renderListBlock($data),
            'quote' => $this->renderQuoteBlock($data),
            'delimiter' => $this->renderDelimiterBlock(),
            'table' => $this->renderTableBlock($data),
            'code' => $this->renderCodeBlock($data),
            'attaches' => $this->renderAttachBlock($data),
            default => $this->renderUnknownBlock($block)
        };
    }

    private function renderTextBlock(array $block, array $data): string
    {
        $text = $this->decodeHtml((string)($data['text'] ?? ''));

        if ($text === '') {
            return '';
        }

        $level = (string)($data['level'] ?? '');
        $alignment = (string)($block['tunes']['textAlign']['alignment'] ?? '');

        $textClass = match ($level) {
            '1' => 'title-big',
            '2' => 'title',
            '4' => 'subtitle',
            default => 'text'
        };

        $alignClass = match ($alignment) {
            'center' => 'a-c',
            'right' => 'a-r',
            default => 'a-l'
        };

        return "<div class='{$textClass} {$alignClass} w-100'>{$text}</div>";
    }

    private function renderImageBlock(array $data): string
    {
        $file = $this->normalizeFile($data['file'] ?? null);

        $large = $this->toString($file['large'] ?? $file['url'] ?? '');
        $original = $this->toString($file['original'] ?? $file['url'] ?? $large);

        if ($large === '' && $original === '') {
            return '';
        }

        if ($large === '') {
            $large = $original;
        }

        if ($original === '') {
            $original = $large;
        }

        $paragraphId = $this->newParagraphId();
        $caption = $this->decodeHtml((string)($data['caption'] ?? ''));
        $captionAttribute = $caption === '' ? '' : " data-caption='" . $this->escapeAttr($caption) . "'";
        $alt = $this->escapeAttr(strip_tags($caption));

        $anchorClass = $this->classList('p-r f-start', $this->config['border_class'], $this->config['image_class']);

        return "<a class='{$anchorClass}' href='javascript:;' data-fancybox='{$paragraphId}' data-src='" . $this->escapeAttr($original) . "'{$captionAttribute}><img src='" . $this->escapeAttr($large) . "' alt='{$alt}' class='bg bg-cover skeleton' loading='lazy'></a>";
    }

    private function renderGalleryBlock(array $data): string
    {
        $files = $this->toArray($data['files'] ?? []);

        if (empty($files)) {
            return '';
        }

        $paragraphId = $this->newParagraphId();
        $swiperId = "swiper-{$paragraphId}";
        $prevClass = "swiper-button-prev-{$paragraphId}";
        $nextClass = "swiper-button-next-{$paragraphId}";

        $wrapperClass = $this->classList('swiper w-100', $this->config['border_class'], $this->config['image_class']);
        $slideCount = 0;

        $html = "<div data-swiper='{$swiperId}' class='{$wrapperClass}'>";
        $html .= "<div class='swiper-wrapper p-a top w-100 h-100'>";

        foreach ($files as $fileRow) {
            $file = $this->normalizeFile($fileRow);

            $large = $this->toString($file['large'] ?? $file['url'] ?? '');
            $original = $this->toString($file['original'] ?? $file['url'] ?? $large);
            $caption = $this->decodeHtml((string)($file['caption'] ?? ($data['caption'] ?? '')));

            if ($large === '' && $original === '') {
                continue;
            }

            if ($large === '') {
                $large = $original;
            }

            if ($original === '') {
                $original = $large;
            }

            $captionAttribute = $caption === '' ? '' : " data-caption='" . $this->escapeAttr($caption) . "'";
            $alt = $this->escapeAttr(strip_tags($caption));

            $html .= "<a class='swiper-slide' href='javascript:;' data-fancybox='{$paragraphId}' data-src='" . $this->escapeAttr($original) . "'{$captionAttribute}><img src='" . $this->escapeAttr($large) . "' alt='{$alt}' class='bg bg-cover skeleton' loading='lazy'></a>";
            $slideCount++;
        }

        if ($slideCount === 0) {
            return '';
        }

        $html .= '</div>';
        $html .= "<div class='swiper-button-prev {$prevClass}'></div>";
        $html .= "<div class='swiper-button-next {$nextClass}'></div>";
        $html .= '</div>';

        $this->swiperInstances[] = [
            'selector' => "[data-swiper=\"{$swiperId}\"]",
            'prev' => ".{$prevClass}",
            'next' => ".{$nextClass}",
        ];

        return $html;
    }

    private function renderVideoBlock(array $data): string
    {
        $file = $this->normalizeFile($data['file'] ?? null);

        $url = $this->toString($file['url'] ?? ($data['url'] ?? ''));
        $mimeType = $this->toString($file['mime-type'] ?? ($file['mimeType'] ?? ($data['mime-type'] ?? '')));

        if ($url === '') {
            return '';
        }

        $videoClass = $this->classList('video-js vjs-16-9', $this->config['border_class']);

        if ($mimeType === '') {
            return "<video class='{$videoClass}' controls preload='auto' data-setup='{}'><source src='" . $this->escapeAttr($url) . "' /></video>";
        }

        return "<video class='{$videoClass}' controls preload='auto' data-setup='{}'><source src='" . $this->escapeAttr($url) . "' type='" . $this->escapeAttr($mimeType) . "' /></video>";
    }

    private function renderEmbedBlock(array $data): string
    {
        $src = $this->toString($data['source'] ?? '');

        if ($src === '') {
            return '';
        }

        $wrapperClass = $this->classList($this->config['border_class'], $this->config['image_class']);

        return "<div class='{$wrapperClass}'><iframe class='bg bg-cover' src='" . $this->escapeAttr($src) . "' frameborder='0' loading='lazy' allowfullscreen></iframe></div>";
    }

    private function renderListBlock(array $data): string
    {
        $items = $this->toArray($data['items'] ?? []);

        if (empty($items)) {
            return '';
        }

        $style = strtolower((string)($data['style'] ?? 'unordered'));
        $listHtml = $this->renderListTag($items, $style);

        if ($listHtml === '') {
            return '';
        }

        return "<div class='" . $this->escapeAttr((string)$this->config['list_class']) . "'>{$listHtml}</div>";
    }

    private function renderQuoteBlock(array $data): string
    {
        $text = $this->decodeHtml((string)($data['text'] ?? ''));

        if ($text === '') {
            return '';
        }

        $caption = $this->decodeHtml((string)($data['caption'] ?? ''));
        $alignment = strtolower((string)($data['alignment'] ?? ''));
        $alignClass = ($alignment === 'center') ? 'a-c' : 'a-l';

        return "<div class='w-70 w-p-100 c-w mh-6'><i class='p-r f-start title {$alignClass} w-100 fw-300'>\"{$text}\"</i><div class='text {$alignClass} w-100 fw-300 mt-2'>{$caption}</div></div>";
    }

    private function renderDelimiterBlock(): string
    {
        $style = $this->escapeAttr((string)$this->config['delimiter_style']);

        return "<div class='w-100 mh-6' style='{$style}'></div>";
    }

    private function renderTableBlock(array $data): string
    {
        $rows = $this->toArray($data['content'] ?? []);

        if (empty($rows)) {
            return '';
        }

        $withHeadings = (bool)($data['withHeadings'] ?? true);
        $rowIndex = 0;

        $html = "<div class='w-100 o-scroll text'><div class='b-1 {$this->escapeAttr((string)$this->config['border_class'])} o-hidden box-border' style='overflow-x: auto;white-space: nowrap;'><table>";

        foreach ($rows as $row) {
            $columns = $this->toArray($row);

            if (empty($columns)) {
                continue;
            }

            if ($withHeadings && $rowIndex === 0) {
                $html .= "<tr class='a-l'>";
                foreach ($columns as $column) {
                    $html .= "<th class='ph-2 pw-1'>" . $this->escapeHtml((string)$column) . '</th>';
                }
                $html .= '</tr>';
            } else {
                $background = ($rowIndex % 2 === 0) ? '' : (string)$this->config['table_alt_row_class'];
                $background = $this->escapeAttr($background);

                $html .= "<tr class='bt-1 {$background}'>";
                foreach ($columns as $column) {
                    $html .= "<td class='pw-1' style='font-size: .9em;padding-top: calc(var(--spacer) * 1.5) !important;padding-bottom: calc(var(--spacer) * 1.5) !important;'>" . $this->escapeHtml((string)$column) . '</td>';
                }
                $html .= '</tr>';
            }

            $rowIndex++;
        }

        $html .= '</table></div></div>';

        return $html;
    }

    private function renderCodeBlock(array $data): string
    {
        $code = (string)($data['code'] ?? '');

        if ($code === '') {
            return '';
        }

        $class = $this->escapeAttr((string)$this->config['code_class']);
        $code = $this->escapeHtml($code);

        return "<pre class='{$class}'><code>{$code}</code></pre>";
    }

    private function renderAttachBlock(array $data): string
    {
        $file = $this->normalizeFile($data['file'] ?? null);
        $title = $this->toString($data['title'] ?? '');
        $fileUrl = $this->toString($file['url'] ?? '');
        $fileName = $this->toString($file['name'] ?? '');
        $fileSize = (int)($file['size'] ?? 0);

        if ($fileUrl === '') {
            return '';
        }

        if ($title === '') {
            $title = $fileName;
        }

        $extension = strtolower($this->toString($file['extension'] ?? pathinfo((string)parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION)));
        $sizeLabel = $this->formatSize($fileSize);
        $icon = $this->fileIcon($extension);

        $cardClass = $this->classList($this->config['download_card_class'], $this->config['border_class']);
        $buttonClass = $this->escapeAttr((string)$this->config['download_button_class']);

        return "<div class='{$cardClass}'><div style='width: calc(var(--text-line-height) + var(--text-small-line-height));font-size: calc(var(--text-line-height) + var(--text-small-line-height));line-height: calc(var(--text-line-height) + var(--text-small-line-height));'>{$icon}</div><div class='box-border pl-3'><div class='text'>" . $this->escapeHtml($title) . "</div><div class='text-small'>" . $this->escapeHtml($sizeLabel) . "</div></div><a href='" . $this->escapeAttr($fileUrl) . "' class='{$buttonClass}' download><i class='bi bi-download'></i></a></div>";
    }

    private function renderUnknownBlock(array $block): string
    {
        $callback = $this->config['unknown_block_callback'] ?? null;

        if (is_callable($callback)) {
            return (string)call_user_func($callback, $block);
        }

        if (!empty($this->config['render_unknown_blocks'])) {
            $type = $this->escapeHtml((string)($block['type'] ?? 'unknown'));
            return "<!-- Unsupported editor block: {$type} -->";
        }

        return '';
    }

    private function renderListTag(array $items, string $style = 'unordered'): string
    {
        $tag = ($style === 'ordered') ? 'ol' : 'ul';
        $content = '';

        foreach ($items as $rawItem) {
            $item = $this->toArray($rawItem);

            if (!empty($item) && array_key_exists('content', $item)) {
                $text = $this->decodeHtml((string)($item['content'] ?? ''));
                $nestedItems = $this->toArray($item['items'] ?? []);
                $nestedStyle = strtolower((string)($item['style'] ?? $style));
                $nested = empty($nestedItems) ? '' : $this->renderListTag($nestedItems, $nestedStyle);

                if ($text === '' && $nested === '') {
                    continue;
                }

                $content .= "<li>{$text}{$nested}</li>";
                continue;
            }

            if (!empty($item) && array_is_list($item)) {
                $nested = $this->renderListTag($item, $style);
                if ($nested !== '') {
                    $content .= "<li>{$nested}</li>";
                }
                continue;
            }

            $text = is_scalar($rawItem) ? $this->decodeHtml((string)$rawItem) : '';

            if ($text === '') {
                continue;
            }

            $content .= "<li>{$text}</li>";
        }

        if ($content === '') {
            return '';
        }

        return "<{$tag}>{$content}</{$tag}>";
    }

    private function renderQueuedScripts(): string
    {
        if (empty($this->swiperInstances)) {
            return '';
        }

        $script = "<script>(function () {";
        $script .= "if (typeof Swiper === 'undefined') { return; }";

        foreach ($this->swiperInstances as $instance) {
            $selector = $this->jsonForScript((string)$instance['selector']);
            $prev = $this->jsonForScript((string)$instance['prev']);
            $next = $this->jsonForScript((string)$instance['next']);

            $script .= "(function () {";
            $script .= "var root = document.querySelector({$selector});";
            $script .= "if (!root) { return; }";
            $script .= "var prevEl = root.querySelector({$prev});";
            $script .= "var nextEl = root.querySelector({$next});";
            $script .= "new Swiper(root, { navigation: { prevEl: prevEl, nextEl: nextEl } });";
            $script .= "})();";
        }

        $script .= "})();</script>";

        return $script;
    }

    private function wrapBlock(string $html): string
    {
        $wrapperClass = trim((string)($this->config['wrapper_class'] ?? ''));

        if ($wrapperClass === '') {
            return $html;
        }

        return "<div class='" . $this->escapeAttr($wrapperClass) . "'>{$html}</div>";
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0Kb';
        }

        if ($bytes < 1000000) {
            return number_format($bytes / 1000, 1, '.', '') . 'Kb';
        }

        if ($bytes < 1000000000) {
            return number_format($bytes / 1000000, 1, '.', '') . 'Mb';
        }

        return number_format($bytes / 1000000000, 1, '.', '') . 'Gb';
    }

    private function fileIcon(string $extension): string
    {
        return match ($extension) {
            'pdf' => "<i class='bi bi-file-pdf-fill tx-danger'></i>",
            'mov', 'mp4' => "<i class='bi bi-file-play-fill tx-primary'></i>",
            default => "<i class='bi bi-file-earmark-fill tx-primary'></i>"
        };
    }

    private function normalizeFile(mixed $file): array
    {
        if (is_string($file)) {
            return [
                'url' => $file,
                'original' => $file,
                'large' => $file,
            ];
        }

        return $this->toArray($file);
    }

    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            $json = json_encode($value);
            if ($json === false) {
                return [];
            }

            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function toString(mixed $value): string
    {
        if (is_scalar($value)) {
            return trim((string)$value);
        }

        return '';
    }

    private function decodeHtml(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function classList(string ...$classes): string
    {
        $parts = [];

        foreach ($classes as $class) {
            $class = trim((string)$class);
            if ($class !== '') {
                $parts[] = $class;
            }
        }

        return $this->escapeAttr(implode(' ', $parts));
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function newParagraphId(): string
    {
        try {
            return 'paragraph_' . strtolower(bin2hex(random_bytes(5)));
        } catch (\Throwable) {
            return 'paragraph_' . uniqid();
        }
    }

    private function jsonForScript(string $value): string
    {
        $encoded = json_encode($value);
        return is_string($encoded) ? $encoded : '""';
    }
}
