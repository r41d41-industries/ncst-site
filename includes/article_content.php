<?php

declare(strict_types=1);

/**
 * Sanitize article HTML for storage (allowlisted tags/attrs).
 */
function article_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'img',
        'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'sup', 'span',
    ];
    $allowedAttrs = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'class', 'width', 'height'],
        'span' => ['class'],
        'p' => ['class'],
        'sup' => ['id', 'class'],
    ];

    $wrapped = '<div id="ncst-root">' . $html . '</div>';
    $prev = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$loaded) {
        return strip_tags($html, '<' . implode('><', $allowedTags) . '>');
    }

    $root = $dom->getElementById('ncst-root');
    if ($root === null) {
        return '';
    }

    $walk = null;
    $walk = static function (DOMNode $node) use (&$walk, $allowedTags, $allowedAttrs): void {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            /** @var DOMElement $el */
            $el = $node;
            $tag = strtolower($el->tagName);
            if (!in_array($tag, $allowedTags, true)) {
                // Unwrap disallowed tags; keep (and continue walking) children.
                $parent = $el->parentNode;
                $moved = [];
                while ($el->firstChild) {
                    $moved[] = $el->firstChild;
                    $parent?->insertBefore($el->firstChild, $el);
                }
                $parent?->removeChild($el);
                foreach ($moved as $child) {
                    $walk($child);
                }
                return;
            }
            $keep = $allowedAttrs[$tag] ?? [];
            $toRemove = [];
            foreach ($el->attributes ?? [] as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $keep, true)) {
                    $toRemove[] = $attr->name;
                    continue;
                }
                if ($name === 'href' || $name === 'src') {
                    $val = trim($attr->value);
                    if ($name === 'href' && !preg_match('~^(https?://|/|#|mailto:)~i', $val)) {
                        $toRemove[] = $attr->name;
                    }
                    if ($name === 'src' && !preg_match('~^(https?://|/)~i', $val)) {
                        $toRemove[] = $attr->name;
                    }
                }
                if ($name === 'target' && !in_array($attr->value, ['_blank', '_self'], true)) {
                    $toRemove[] = $attr->name;
                }
            }
            foreach ($toRemove as $attrName) {
                $el->removeAttribute($attrName);
            }
            if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
                $el->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            $walk($child);
        }
    };

    // Walk content nodes only — never treat the #ncst-root wrapper as article HTML
    // (unwrapping it empties the tree and caused Full article saves to store null).
    $toWalk = [];
    foreach ($root->childNodes as $child) {
        $toWalk[] = $child;
    }
    foreach ($toWalk as $child) {
        $walk($child);
    }

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return trim($out);
}

/**
 * @param list<array{n?: int|string, content?: string}>|string|null $raw
 * @return list<array{n: int, content: string}>
 */
function posts_normalize_footnotes(array|string|null $raw): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $n = (int) ($row['n'] ?? 0);
        $content = trim((string) ($row['content'] ?? ''));
        if ($n <= 0 || $content === '') {
            continue;
        }
        $out[] = ['n' => $n, 'content' => $content];
    }
    usort($out, static fn(array $a, array $b): int => $a['n'] <=> $b['n']);
    return $out;
}

/**
 * Expand shortcodes and footnote markers for public display.
 *
 * @param list<array{n: int, content: string}> $footnotes
 * @return array{html: string, footnotes_html: string}
 */
function article_render_body(string $html, array $footnotes = []): array
{
    $html = shortcodes_expand($html);
    $used = [];

    $html = (string) preg_replace_callback(
        '/\{(\d+)\}/',
        static function (array $m) use (&$used): string {
            $n = (int) $m[1];
            $used[$n] = true;
            return '<sup class="article-fnref" id="fnref-' . $n . '"><a href="#fn-' . $n . '">' . $n . '</a></sup>';
        },
        $html
    );

    $items = '';
    foreach ($footnotes as $fn) {
        $n = (int) $fn['n'];
        if (!isset($used[$n])) {
            // Still list defined footnotes even if marker missing
        }
        $items .= '<li id="fn-' . $n . '">'
            . e((string) $fn['content'])
            . ' <a class="article-fnback" href="#fnref-' . $n . '" aria-label="Back to reference ' . $n . '">↩</a>'
            . '</li>';
    }

    $footnotesHtml = $items !== ''
        ? '<section class="article-footnotes" aria-label="Footnotes"><h2 class="article-footnotes__title">Footnotes</h2><ol>' . $items . '</ol></section>'
        : '';

    return ['html' => $html, 'footnotes_html' => $footnotesHtml];
}

function posts_edit_url(array $post): string
{
    $tpl = category_template((string) ($post['category'] ?? ''));
    $id = (int) ($post['id'] ?? 0);
    if ($tpl === 'incident') {
        return '/admin/post_incident.php?id=' . $id;
    }
    return '/admin/post_article.php?id=' . $id;
}
