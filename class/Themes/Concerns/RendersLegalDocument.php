<?php

namespace Wonder\Themes\Concerns;

use Wonder\Plugin\Custom\Input\EditorBlocksRenderer;

/**
 * Markup del documento legale condiviso dai temi: ogni tema passa solo le sue
 * classi. Il contenuto è l'HTML di `EditorBlocksRenderer`, lo stesso del
 * backend e del PDF.
 */
trait RendersLegalDocument
{
    use RendersComponentAttributes;

    /** @param array{root: list<string>, title: string, content: string} $classes */
    protected function renderLegalDocument(object $class, array $classes): string
    {
        $document = $class->resolved();

        if ($document === null) {
            return '';
        }

        $title = '';

        if ($class->getSchema('title') && $document['name'] !== '') {
            $tag = (string) $class->getSchema('title-tag');
            $title = "<{$tag} class=\"{$classes['title']}\">".$this->escape($document['name'])."</{$tag}>";
        }

        $lang = $document['language'] !== '' ? ' lang="'.$this->escape($document['language']).'"' : '';

        return '<article '.$this->renderComponentAttributes($class, $classes['root']).$lang.'>'
            .$title
            .'<div class="'.$classes['content'].'">'.EditorBlocksRenderer::make($document['content']).'</div>'
            .'</article>';
    }
}
