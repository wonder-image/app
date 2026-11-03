<?php

namespace Wonder\Elements\Components;

use RuntimeException;
use Throwable;
use Wonder\Consent\Repository\LegalDocumentRepository;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\Renderer;
use Wonder\Localization\LanguageContext;
use Wonder\Localization\TranslationProvider;

/**
 * Documento legale attivo (`legal_documents`) nella lingua corrente, o in
 * quella di default se manca: la stessa fonte delle versioni accettate.
 *
 * `document()` passa una riga già letta e salta la query; `resolveUsing()`
 * sostituisce la lettura per tutto il sito o nei test.
 */
class LegalDocument extends Component
{
    use Renderer;

    /** @var (callable(string): ?array)|null */
    private static $resolver = null;

    public function __construct(string $type = '')
    {
        $this->schema('type', trim($type))
            ->schema('document', null)
            ->schema('title', true)
            ->schema('title-tag', 'h1');
    }

    public static function make(string $type): self
    {
        return new self($type);
    }

    /** Riga di `legal_documents`: `name` e `content_snapshot` (JSON o array dei blocchi). */
    public function document(?array $row): self
    {
        return $this->schema('document', $row);
    }

    /** Mostra il nome del documento come titolo (`h1`, `h2` o `h3`). */
    public function title(bool $show = true, string $tag = 'h1'): self
    {
        return $this->schema('title', $show)
            ->schema('title-tag', in_array($tag, ['h1', 'h2', 'h3'], true) ? $tag : 'h1');
    }

    /** @param (callable(string $type): ?array)|null $resolver null torna alla lettura dal database */
    public static function resolveUsing(?callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Nome e blocchi con i placeholder risolti, o null se il documento manca.
     *
     * @return array{name: string, content: mixed, version: string, language: string}|null
     */
    public function resolved(): ?array
    {
        $row = $this->getSchema('document');

        if (!is_array($row)) {
            $type = (string) $this->getSchema('type');
            $row = $type === '' ? null : (self::$resolver ?? self::fromDatabase(...))($type);
        }

        if (!is_array($row)) {
            return null;
        }

        $content = $row['content_snapshot'] ?? null;

        if (is_string($content)) {
            $decoded = json_decode($content, true);
            $content = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return [
            'name' => trim((string) self::replace((string) ($row['name'] ?? ''))),
            'content' => self::replace($content),
            'version' => (string) ($row['version'] ?? ''),
            'language' => (string) ($row['language_code'] ?? ''),
        ];
    }

    private static function fromDatabase(string $type): ?array
    {
        try {
            $repository = new LegalDocumentRepository();

            foreach (array_unique([LanguageContext::getLang(), LanguageContext::getDefaultLang()]) as $lang) {
                if ($lang !== '' && ($row = $repository->findActiveByTypeAndLanguage($type, $lang)) !== null) {
                    return $row;
                }
            }
        } catch (Throwable) {
            // Tipo sconosciuto o database assente: la pagina resta senza documento.
        }

        return null;
    }

    private static function replace(mixed $value): mixed
    {
        try {
            return TranslationProvider::replace($value);
        } catch (RuntimeException) {
            return $value;
        }
    }
}
