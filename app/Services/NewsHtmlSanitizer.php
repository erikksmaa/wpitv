<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

final class NewsHtmlSanitizer
{
    private const ALLOWED_ELEMENTS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'h4',
        'ul', 'ol', 'li', 'blockquote', 'a', 'img',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
    ];

    private const DROP_WITH_CONTENTS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'button', 'svg', 'math', 'meta', 'link', 'base', 'template',
    ];

    private const GLOBAL_ATTRIBUTES = ['class'];

    private const ELEMENT_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'td' => ['colspan', 'rowspan'],
    ];

    public function sanitize(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="wpitv-news-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('wpitv-news-root');
        if (! $root) {
            return '';
        }

        $this->sanitizeChildren($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;

            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);

                if (in_array($tag, self::DROP_WITH_CONTENTS, true)) {
                    $parent->removeChild($node);
                    $node = $next;

                    continue;
                }

                if (! in_array($tag, self::ALLOWED_ELEMENTS, true)) {
                    $this->sanitizeChildren($node);
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                    $node = $next;

                    continue;
                }

                $this->sanitizeAttributes($node, $tag);
                $this->sanitizeChildren($node);
            }

            $node = $next;
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRIBUTES, self::ELEMENT_ATTRIBUTES[$tag] ?? []);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $element->removeAttributeNode($attribute);
            }
        }

        foreach (['href', 'src'] as $attribute) {
            if ($element->hasAttribute($attribute) && ! $this->safeUrl($element->getAttribute($attribute), $attribute === 'href')) {
                $element->removeAttribute($attribute);
            }
        }

        if ($tag === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        } elseif ($tag === 'a') {
            $element->removeAttribute('target');
            $element->removeAttribute('rel');
        }
    }

    private function safeUrl(string $url, bool $allowMailto): bool
    {
        $normalized = preg_replace('/[\x00-\x20\x7F]+/u', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
        if ($normalized === '' || str_starts_with($normalized, '/') || str_starts_with($normalized, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));

        return in_array($scheme, $allowMailto ? ['http', 'https', 'mailto'] : ['http', 'https'], true);
    }
}
