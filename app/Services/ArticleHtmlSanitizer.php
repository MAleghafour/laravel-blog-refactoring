<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class ArticleHtmlSanitizer
{
    /**
     * Sanitize article HTML using an explicit allowlist.
     *
     * Removes executable content while preserving
     * supported rich-text formatting.
     */
    public function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $config = HTMLPurifier_Config::createDefault();

        $config->set('HTML.Allowed', implode(',', [
            'p',
            'br',
            'strong',
            'em',
            'u',
            's',
            'h2',
            'h3',
            'h4',
            'ul',
            'ol',
            'li',
            'blockquote',
            'a[href|title|target]',
            'img[src|alt|width|height]',
            'table',
            'thead',
            'tbody',
            'tr',
            'th',
            'td',
        ]));

        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ]);

        $config->set('HTML.Nofollow', true);
        $config->set('Attr.EnableID', false);

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($html);
    }
}
