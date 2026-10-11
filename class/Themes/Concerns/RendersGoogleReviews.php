<?php

namespace Wonder\Themes\Concerns;

use Wonder\Themes\Support\PageAssets;

/**
 * Markup delle recensioni Google condiviso dai temi: ogni tema passa solo le
 * sue classi. Le attribuzioni richieste da Google (avatar, nome e profilo
 * dell'autore, link alla recensione, ordine, «Google Maps» non tradotto)
 * stanno qui e non si possono spegnere.
 */
trait RendersGoogleReviews
{
    use RendersComponentAttributes;

    /**
     * @param array{root: list<string>, summary: string, list: string, item: string, author: string, meta: string, text: string, muted: string, link: string} $classes
     */
    protected function renderGoogleReviews(object $class, array $classes): string
    {
        $schema = $class->getSchema();
        $reviews = (array) ($schema['reviews'] ?? []);
        $limit = (int) ($schema['limit'] ?? 0);

        if ($limit > 0) {
            $reviews = array_slice($reviews, 0, $limit);
        }

        $rating = $schema['rating'] ?? null;

        if ($reviews === [] && $rating === null) {
            return '';
        }

        $notice = (string) ($schema['notice'] ?? '');
        $notice = $notice !== '' ? $notice : $this->reviewsText('notice');
        $items = '';

        foreach ($reviews as $review) {
            $items .= '<li class="'.$classes['item'].'">'.$this->renderGoogleReview($review, $classes).'</li>';
        }

        return PageAssets::once('wi-google-reviews', $this->googleReviewsStyle())
            .'<section '.$this->renderComponentAttributes($class, $classes['root']).' aria-label="'.$this->escape($this->reviewsText('label')).'">'
            .$this->renderGoogleReviewsSummary($rating, $schema['rating-count'] ?? null, (string) ($schema['maps-uri'] ?? ''), $classes)
            .'<p class="'.$classes['muted'].'">'.$this->escape($notice).'</p>'
            .($items !== '' ? '<ul class="'.$classes['list'].'">'.$items.'</ul>' : '')
            .'<p class="wi-google-reviews__attribution" translate="no">Google Maps</p>'
            .'</section>';
    }

    private function renderGoogleReviewsSummary(?float $rating, ?int $count, string $mapsUri, array $classes): string
    {
        if ($rating === null) {
            return '';
        }

        $value = number_format($rating, 1, $this->reviewsText('decimal'), '');
        $countText = $count !== null ? $this->reviewsText('count', ['count' => (string) $count]) : '';

        if ($countText !== '' && $mapsUri !== '') {
            $countText = '<a class="'.$classes['link'].'" href="'.$this->escape($mapsUri).'" target="_blank" rel="noopener">'.$this->escape($countText).'</a>';
        } elseif ($countText !== '') {
            $countText = $this->escape($countText);
        }

        return '<div class="'.$classes['summary'].'">'
            .'<strong class="wi-google-reviews__value">'.$this->escape($value).'</strong>'
            .$this->renderGoogleStars($rating, $value)
            .($countText !== '' ? '<span>'.$countText.'</span>' : '')
            .'</div>';
    }

    private function renderGoogleReview(array $review, array $classes): string
    {
        $name = $this->escape($review['author_name']);
        $avatar = $review['author_photo_uri'] !== ''
            ? '<img class="wi-google-reviews__avatar" src="'.$this->escape($review['author_photo_uri']).'" alt="" width="40" height="40" loading="lazy" decoding="async" referrerpolicy="no-referrer">'
            : '';
        $author = $review['author_uri'] !== '' && $name !== ''
            ? '<a class="'.$classes['link'].'" href="'.$this->escape($review['author_uri']).'" target="_blank" rel="noopener">'.$name.'</a>'
            : $name;
        $time = $review['relative_time'] !== '' ? $review['relative_time'] : substr($review['publish_time'], 0, 10);
        $meta = ($review['rating'] !== null ? $this->renderGoogleStars((float) $review['rating'], (string) $review['rating']) : '')
            .($time !== ''
                ? '<time'.($review['publish_time'] !== '' ? ' datetime="'.$this->escape(str_replace(' ', 'T', $review['publish_time'])).'"' : '').'>'.$this->escape($time).'</time>'
                : '');
        $text = $review['text'] !== '' ? '<p class="'.$classes['text'].'">'.$this->escapeWithLineBreaks($review['text']).'</p>' : '';
        $translated = $review['original_text'] !== ''
            && $review['original_language'] !== ''
            && $review['language'] !== ''
            && strtolower($review['original_language']) !== strtolower($review['language']);
        $original = $translated
            ? '<details class="'.$classes['muted'].'"><summary>'.$this->escape($this->reviewsText('translated')).'</summary>'
                .'<p class="'.$classes['text'].'" lang="'.$this->escape($review['original_language']).'">'.$this->escapeWithLineBreaks($review['original_text']).'</p></details>'
            : '';
        $link = $review['maps_uri'] !== ''
            ? '<a class="'.$classes['link'].'" href="'.$this->escape($review['maps_uri']).'" target="_blank" rel="noopener">'.$this->escape($this->reviewsText('open')).'</a>'
            : '';

        return '<article'.($review['language'] !== '' ? ' lang="'.$this->escape($review['language']).'"' : '').'>'
            .'<div class="'.$classes['author'].'">'.$avatar.'<span>'.$author.'</span></div>'
            .($meta !== '' ? '<div class="'.$classes['meta'].'">'.$meta.'</div>' : '')
            .$text.$original
            .($link !== '' ? '<p class="'.$classes['muted'].'">'.$link.'</p>' : '')
            .'</article>';
    }

    private function renderGoogleStars(float $rating, string $label): string
    {
        $full = (int) round($rating);

        return '<span class="wi-google-reviews__stars" role="img" aria-label="'.$this->escape($this->reviewsText('stars', ['rating' => $label])).'">'
            .'<span aria-hidden="true">'.str_repeat('★', $full).'</span>'
            .'<span class="wi-google-reviews__stars-off" aria-hidden="true">'.str_repeat('★', 5 - $full).'</span>'
            .'</span>';
    }

    private function reviewsText(string $key, array $replacements = []): string
    {
        $fallback = [
            'label' => 'Recensioni Google',
            'notice' => 'Le recensioni più recenti da Google.',
            'count' => '{{count}} recensioni',
            'stars' => '{{rating}} su 5',
            'decimal' => ',',
            'translated' => 'Tradotta da Google: mostra l\'originale',
            'open' => 'Vedi su Google Maps',
        ][$key] ?? $key;
        $path = 'components.reviews.'.$key;

        try {
            $text = function_exists('__t') ? \__t($path, $replacements) : null;
        } catch (\Throwable) {
            $text = null;
        }

        if (!is_string($text) || $text === '' || $text === $path) {
            $text = $fallback;

            foreach ($replacements as $name => $value) {
                $text = str_replace('{{'.$name.'}}', (string) $value, $text);
            }
        }

        return $text;
    }

    private function googleReviewsStyle(): string
    {
        return <<<'HTML'
<style data-wi-google-reviews-style>
.wi-google-reviews__row { align-items: center; }
.wi-google-reviews__list { list-style: none; padding: 0; }
.wi-google-reviews__item + .wi-google-reviews__item { padding-top: 1rem; border-top: 1px solid rgba(127, 127, 127, .25); }
.wi-google-reviews__muted { opacity: .75; }
.wi-google-reviews__link { color: inherit; text-decoration: underline; }
.wi-google-reviews__avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex: none; }
.wi-google-reviews__stars { color: #fbbc04; letter-spacing: .05em; white-space: nowrap; }
.wi-google-reviews__stars-off { opacity: .3; }
.wi-google-reviews__attribution { margin: 0; font-family: Roboto, Arial, sans-serif; font-size: 12px; font-weight: 400; font-style: normal; color: #5e5e5e; white-space: nowrap; }
</style>
HTML;
    }
}
