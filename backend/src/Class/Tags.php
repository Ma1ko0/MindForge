<?php

declare(strict_types=1);

namespace App;

class Tags
{
    /**
     * Hashtag pattern. Matches #word where word contains letters (incl. Umlaute),
     * digits, underscore, hyphen. Must not be preceded by alphanumeric so that
     * things like email@domain#anchor or markdown headings (`## Title`) don't match.
     */
    private const PATTERN = '/(?<![a-zA-Z0-9_])#([\p{L}\p{N}_-]+)/u';

    /**
     * Extract unique, lowercased tags from arbitrary content.
     *
     * @return string[]
     */
    public static function extract(string $content): array
    {
        if (!preg_match_all(self::PATTERN, $content, $matches)) {
            return [];
        }
        $tags = array_map(fn ($t) => mb_strtolower($t), $matches[1]);
        return array_values(array_unique($tags));
    }

    /**
     * Replace #tag occurrences with clickable HTML anchors. The input is first
     * HTML-escaped, so it is safe to pass user-supplied plain text.
     */
    public static function linkifyHtml(string $content): string
    {
        return preg_replace_callback(
            self::PATTERN,
            fn ($m) => self::renderHtmlChip($m[1]),
            htmlspecialchars($content, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Replace #tag occurrences with markdown link syntax — for content that goes
     * through marked.js client-side rendering.
     */
    public static function linkifyMarkdown(string $content): string
    {
        return preg_replace_callback(
            self::PATTERN,
            fn ($m) => '[#' . $m[1] . '](/tags/' . rawurlencode(mb_strtolower($m[1])) . ')',
            $content
        );
    }

    private static function renderHtmlChip(string $tag): string
    {
        $display = htmlspecialchars($tag, ENT_QUOTES, 'UTF-8');
        $href = rawurlencode(mb_strtolower($tag));
        return '<a class="tag-chip" hx-get="/tags/' . $href . '" hx-target="#main-content" hx-swap="innerHTML">#' . $display . '</a>';
    }

    /**
     * Build a LIKE clause that is reasonably tag-aware: matches #tag at start of
     * content or after a non-alphanumeric char. Use this with a REGEXP for accuracy.
     */
    public static function regexpFor(string $tag): string
    {
        $tag = preg_replace('/[^a-zA-Z0-9_\-äöüÄÖÜß]/u', '', $tag);
        return '(^|[^a-zA-Z0-9_])#' . mb_strtolower($tag) . '($|[^a-zA-Z0-9_\\-])';
    }
}
