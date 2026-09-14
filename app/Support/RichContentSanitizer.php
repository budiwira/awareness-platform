<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitasi HTML rich content (editor ala Word) sebelum disimpan.
 *
 * Kontrak keamanan:
 * - Allowlist ketat: tidak ada script, tidak ada atribut on*, tidak ada javascript: URI
 * - Iframe HANYA untuk embed YouTube/Vimeo
 * - Konten tenant admin = input UNTRUSTED, bahkan dari admin sekalipun
 */
class RichContentSanitizer
{
    public static function clean(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();

        // Path relative dari file ini: app/Support -> root -> storage/app/purifier
        $cachePath = dirname(__DIR__, 2).'/storage/app/purifier';

        $config->set('Cache.SerializerPath', $cachePath);

        // Catatan: allowfullscreen tidak dimasukkan karena HTMLPurifier core tidak support attribute itu tanpa custom definition.
        $config->set(
            'HTML.Allowed',
            'p,ul,ol,li,strong,em,u,br,h2,h3,h4,blockquote,a[href|target],img[src|alt],iframe[src|width|height|frameborder]'
        );

        $config->set('HTML.SafeIframe', true);

        // Hanya izinkan embed YouTube dan Vimeo.
        $config->set(
            'URI.SafeIframeRegexp',
            '%^https://(www\.youtube\.com/embed/|player\.vimeo\.com/video/)%'
        );

        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('AutoFormat.AutoParagraph', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        return (new HTMLPurifier($config))->purify($html);
    }
}
