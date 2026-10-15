<?php

declare(strict_types=1);

final class HtmlSanitizer
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'hr' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'blockquote' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'sub' => [],
        'sup' => [],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
        'figure' => [],
        'figcaption' => [],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
        'span' => [],
        'div' => [],
        'pre' => [],
        'code' => [],
    ];

    /** @var list<string> */
    private const REMOVE = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'svg', 'math'];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body instanceof DOMElement) {
            return '';
        }
        self::walk($body);
        $clean = '';
        foreach ($body->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }
        $clean = trim(str_replace('<?xml encoding="UTF-8">', '', $clean));
        if (self::isBlank($clean)) {
            return '';
        }

        return $clean;
    }

    public static function isBlank(string $html): bool
    {
        if (preg_match('/<img\b/i', $html) === 1) {
            return false;
        }
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = trim(str_replace("\xc2\xa0", ' ', $text));

        return $text === '';
    }

    private static function walk(DOMNode $node): void
    {
        if (!$node instanceof DOMElement) {
            return;
        }
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child instanceof DOMComment) {
                $child->parentNode?->removeChild($child);
                continue;
            }
            if ($child instanceof DOMElement) {
                self::walk($child);
            }
        }
        if ($node->tagName === 'body') {
            return;
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, self::REMOVE, true)) {
            $node->parentNode?->removeChild($node);
            return;
        }
        if ($tag === 'h1') {
            $node = self::rename($node, 'h2');
            $tag = 'h2';
        } elseif ($tag === 'h5' || $tag === 'h6') {
            $node = self::rename($node, 'h4');
            $tag = 'h4';
        }
        if (!isset(self::ALLOWED[$tag])) {
            self::unwrap($node);
            return;
        }
        self::filterAttributes($node, $tag);
        if ($tag === 'a' && trim($node->getAttribute('href')) === '') {
            self::unwrap($node);
            return;
        }
        if ($tag === 'img' && trim($node->getAttribute('src')) === '') {
            $node->parentNode?->removeChild($node);
        }
    }

    private static function rename(DOMElement $element, string $tag): DOMElement
    {
        $document = $element->ownerDocument;
        if (!$document instanceof DOMDocument) {
            return $element;
        }
        $next = $document->createElement($tag);
        while ($element->firstChild instanceof DOMNode) {
            $next->appendChild($element->firstChild);
        }
        $element->parentNode?->replaceChild($next, $element);

        return $next;
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (!$parent instanceof DOMNode) {
            return;
        }
        while ($element->firstChild instanceof DOMNode) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private static function filterAttributes(DOMElement $element, string $tag): void
    {
        $kept = ['style' => self::cleanStyle($element->getAttribute('style'))];
        foreach (self::ALLOWED[$tag] as $name) {
            $raw = $element->getAttribute($name);
            $value = '';
            if ($name === 'href') {
                $value = self::cleanHref($raw);
            } elseif ($name === 'src') {
                $value = post_asset_path($raw);
            } elseif ($name === 'alt' || $name === 'title') {
                $value = trim($raw);
                $value = function_exists('mb_substr') ? mb_substr($value, 0, 180, 'UTF-8') : substr($value, 0, 180);
            } elseif ($name === 'width' || $name === 'height') {
                $raw = trim($raw);
                if (preg_match('/^\d{1,4}(?:px|%)?$/', $raw) === 1) {
                    $value = $raw;
                }
            } elseif ($name === 'colspan' || $name === 'rowspan') {
                $span = (int) $raw;
                if ($span >= 1 && $span <= 12) {
                    $value = (string) $span;
                }
            }
            if ($value !== '') {
                $kept[$name] = $value;
            }
        }
        $names = [];
        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attribute) {
                $names[] = $attribute->name;
            }
        }
        foreach ($names as $name) {
            $element->removeAttribute($name);
        }
        foreach ($kept as $name => $value) {
            if ($value !== '') {
                $element->setAttribute($name, $value);
            }
        }
        if ($tag === 'a' && $element->hasAttribute('href') && preg_match('#^https?://#i', $element->getAttribute('href')) === 1) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function cleanHref(string $href): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || preg_match('/[\s\x00-\x1f]/', $href) === 1) {
            return '';
        }
        if (preg_match('#^(https?://|mailto:|tel:)#i', $href) === 1) {
            return $href;
        }
        if (str_starts_with($href, '/') && !str_starts_with($href, '//') && !str_contains($href, '\\')) {
            return $href;
        }
        $asset = post_asset_path($href);

        return $asset !== '' && !str_starts_with($asset, 'https://') ? '/' . $asset : '';
    }

    private static function cleanStyle(string $style): string
    {
        $allowed = ['text-align', 'color', 'background-color', 'font-size', 'width', 'height', 'vertical-align', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom', 'float', 'display'];
        $rules = [];
        foreach (explode(';', $style) as $part) {
            if (!str_contains($part, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $part, 2));
            $property = strtolower($property);
            $value = strtolower(trim($value, " \"'"));
            if (!in_array($property, $allowed, true) || preg_match('/url\s*\(|expression|javascript|@import|behavior|-moz-binding/i', $value) === 1) {
                continue;
            }
            $valid = match ($property) {
                'text-align' => in_array($value, ['left', 'right', 'center', 'justify'], true),
                'vertical-align' => in_array($value, ['top', 'middle', 'bottom'], true),
                'float' => in_array($value, ['left', 'right', 'none'], true),
                'display' => in_array($value, ['block', 'inline', 'inline-block'], true),
                'color', 'background-color' => self::isColor($value),
                'font-size', 'width', 'height' => preg_match('/^\d+(?:\.\d+)?(px|em|rem|%)$/', $value) === 1,
                'margin-left', 'margin-right', 'margin-top', 'margin-bottom' => $value === 'auto' || preg_match('/^\d+(?:\.\d+)?(px|em|rem|%)$/', $value) === 1,
                default => false,
            };
            if ($valid) {
                $rules[] = $property . ':' . $value;
            }
        }

        return implode(';', $rules);
    }

    private static function isColor(string $value): bool
    {
        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $value) === 1
            || preg_match('/^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$/', $value) === 1
            || preg_match('/^rgba\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*(?:0|1|0?\.\d+)\s*\)$/', $value) === 1;
    }
}
