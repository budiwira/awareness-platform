<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitasi HTML rich content (editor ala Word) sebelum disimpan.
 *
 * Kontrak keamanan:
 * - Allowlist ketat: tidak ada script, tidak ada atribut on*, tidak ada javascript: URI
 * - Iframe HANYA untuk embed YouTube/Vimeo (semua varian URL canonical)
 * - Konten tenant admin = input UNTRUSTED, bahkan dari admin sekalipun
 */
class RichContentSanitizer
{
    public static function clean(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();

        $cachePath = dirname(__DIR__, 2).'/storage/app/purifier';

        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.Allowed', 'p,ul,ol,li,strong,em,u,br,h2,h3,h4,blockquote,a[href|target],img[src|alt],iframe[src|width|height|frameborder]');
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^https://(?:www\.youtube(?:-nocookie)?\.com/embed/[a-zA-Z0-9_-]+|player\.vimeo\.com/video/[0-9]+)(?:[?#][^\s]*)?$%D');
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('AutoFormat.AutoParagraph', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        $sanitized = (new HTMLPurifier($config))->purify($html);

        return self::normalizeEmptyParagraphs($sanitized);
    }

    private static function normalizeEmptyParagraphs(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="rich-content-root">'.$html.'</div>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        $root = $document->getElementById('rich-content-root');
        if (! $root instanceof DOMElement) {
            return $html;
        }

        $previousWasEmptyParagraph = false;
        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) === '') {
                continue;
            }

            $isEmptyParagraph = self::isEmptyParagraph($node);
            if ($isEmptyParagraph && $previousWasEmptyParagraph) {
                $root->removeChild($node);

                continue;
            }

            $previousWasEmptyParagraph = $isEmptyParagraph;
        }

        $normalized = '';
        foreach ($root->childNodes as $node) {
            $normalized .= $document->saveHTML($node);
        }

        return $normalized;
    }

    private static function isEmptyParagraph(DOMNode $node): bool
    {
        if (! $node instanceof DOMElement || strtolower($node->tagName) !== 'p') {
            return false;
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) !== 'br') {
                return false;
            }

            if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) !== '') {
                return false;
            }
        }

        return true;
    }
}
