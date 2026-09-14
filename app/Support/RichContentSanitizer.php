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

        // Auto-create jika tidak ada (CI runner, fresh clone, dll)
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.Allowed', 'p,ul,ol,li,strong,em,u,br,h2,h3,h4,blockquote,a[href|target],img[src|alt],iframe[src|width|height|frameborder]');
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^https://(www\.youtube\.com/embed/|player\.vimeo\.com/video/)%');
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('AutoFormat.AutoParagraph', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        return (new HTMLPurifier($config))->purify($html);
    }
}
