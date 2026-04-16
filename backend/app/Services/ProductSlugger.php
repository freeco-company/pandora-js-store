<?php

namespace App\Services;

/**
 * Generate a clean Taiwanese-Chinese product slug from the full WP name.
 *
 * Rules:
 *   - Drop brand prefixes: "JEROSSE", "婕樂纖", "Jerosse" etc (brand already in domain)
 *   - Drop trust badges: "官方正品", "正品", "授權", "經銷商"
 *   - Drop packaging: "(14份/盒)", "(30錠/瓶)", "（10條/盒）" etc.
 *   - Drop marketing noise: "官方授權", "官方", "熱銷", "熱門", "人氣"
 *   - Drop trailing separators, collapse spaces to dashes
 *
 * Keeps the actual product name (e.g. "雪花紫纖飲 (蔓越莓風味)" → "雪花紫纖飲-蔓越莓風味").
 */
class ProductSlugger
{
    /**
     * Generate a slug from a product name. Returns a URL-safe Chinese string.
     */
    public static function fromName(string $name): string
    {
        $s = $name;

        // 1) Strip brand prefixes (case-insensitive for Latin, exact for Chinese)
        $s = preg_replace('/^\s*JEROSSE\s*/iu', '', $s) ?? $s;
        $s = preg_replace('/\bJEROSSE\b/iu', '', $s) ?? $s;
        $s = preg_replace('/婕樂纖/u', '', $s) ?? $s;
        $s = preg_replace('/Fairy\s*Pandora/iu', '', $s) ?? $s;

        // 2) Strip trust phrases
        foreach (['官方正品授權經銷商', '官方正品授權', '官方授權正品', '官方授權', '官方正品', '正品授權', '授權正品', '授權經銷', '經銷商'] as $phrase) {
            $s = str_replace($phrase, '', $s);
        }

        // 3) Strip packaging parens — both half-width "(14份/盒)" and full-width "（...）"
        $s = preg_replace('/\s*[\(（]\s*\d+\s*[份粒錠條包]\s*[\/／]\s*[盒瓶袋罐包條]\s*[\)）]\s*/u', '', $s) ?? $s;
        // Generic parens containing digit + unit
        $s = preg_replace('/\s*[\(（][^\(\)（）]*\d+[^\(\)（）]*?[\)）]\s*/u', ' ', $s) ?? $s;

        // 4) Strip marketing adjectives
        foreach (['熱銷推薦', '熱門推薦', '限量', '限時', '人氣', '熱銷', '熱門', '新品', '獨家'] as $word) {
            $s = str_replace($word, '', $s);
        }

        // 5) Collapse whitespace + trim
        $s = preg_replace('/[\s\/\\\\]+/u', '-', trim($s)) ?? $s;
        $s = preg_replace('/-+/u', '-', $s) ?? $s;
        $s = trim($s, '-');

        // Remove characters that cause URL path issues (keep Chinese, dashes, digits, latin)
        // Permit unicode letters + digits + dash
        $s = preg_replace('/[^\p{L}\p{N}\-]/u', '', $s) ?? $s;

        // Safety: if somehow empty, fall back to transliterated timestamp
        if ($s === '' || $s === '-') {
            $s = 'product-' . substr(md5($name), 0, 8);
        }

        return $s;
    }

    /**
     * Ensure uniqueness by appending -2, -3, … if a sibling slug already exists.
     * Pass the model class + excluded id (when updating an existing row).
     */
    public static function unique(string $base, string $modelClass, ?int $excludeId = null): string
    {
        $slug = $base;
        $i = 2;
        while (true) {
            $q = $modelClass::where('slug', $slug);
            if ($excludeId !== null) $q->where('id', '!=', $excludeId);
            if (! $q->exists()) return $slug;
            $slug = $base . '-' . $i;
            $i++;
        }
    }
}
