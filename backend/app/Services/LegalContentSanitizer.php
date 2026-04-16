<?php

namespace App\Services;

/**
 * Sanitize article / product copy to comply with Taiwan's
 * 食安法 §28, 健康食品管理法 §14, 藥事法 §69.
 *
 * Food/health-food products must not claim drug-like efficacy,
 * disease prevention/treatment, or absolute/exaggerated results.
 * This sanitizer replaces clear violations with softer language
 * and appends a legal disclaimer block.
 */
class LegalContentSanitizer
{
    /**
     * Substring replacements applied to inner text.
     * Keep ordering — longer phrases first so they match before shorter sub-phrases.
     */
    private const REPLACEMENTS = [
        // Drug-efficacy language
        '治癒'   => '調養',
        '根治'   => '調養',
        '醫治'   => '調理',
        '治療'   => '調理',
        '療效'   => '保健感受',
        '藥效'   => '營養支持',
        '特效'   => '有感',
        '神效'   => '有感',
        '奇效'   => '有感',
        '立即見效' => '有感',
        '立刻見效' => '有感',
        '速效'   => '有感',
        // Disease prevention / medical claims
        '預防癌症' => '日常保健',
        '抗癌'   => '健康維護',
        '防癌'   => '健康維護',
        '抗病'   => '健康維護',
        '抗老化' => '維持活力',
        '抗衰老' => '維持活力',
        '預防三高' => '日常保健',
        '降血壓' => '循環保健',
        '降血糖' => '體內平衡',
        '降血脂' => '循環保健',
        '降膽固醇' => '循環保健',
        '消除宿便' => '順暢保健',
        '排毒'   => '代謝保健',
        '解毒'   => '代謝保健',
        // Absolute / exaggerated claims
        '100%'  => '高比例',
        '百分之百' => '高比例',
        '絕對'   => '很',
        '完全'   => '明顯',
        '永久'   => '長期',
        '徹底'   => '有效',
        '一定'   => '',
        '保證'   => '',
        '秒殺'   => '',
        '最強'   => '優質',
        '最佳'   => '優質',
        '第一名' => '受歡迎',
        '世界第一' => '備受肯定',
        '全球第一' => '備受肯定',
        // Body-slimming claims (食品不得宣稱減肥)
        '減肥'   => '體重管理',
        '瘦身'   => '體態管理',
        '燃脂'   => '代謝保健',
        // Cosmetic-drug terms that food shouldn't claim
        '美白'   => '亮顏',
        '淡斑'   => '勻亮',
        '除皺'   => '彈潤',
    ];

    /**
     * Sanitize a block of HTML. Replacements happen on text nodes only,
     * and SKIP text inside elements marked as legal-disclaimer (so the
     * mandatory disclaimer wording like "不具療效" isn't corrupted into
     * "不具保健感受").
     */
    public function sanitize(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8"?><root>' . $html . '</root>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        // Only text nodes NOT under a .legal-disclaimer ancestor
        $textNodes = $xpath->query('//text()[not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " legal-disclaimer ")])]');

        if ($textNodes) {
            foreach ($textNodes as $node) {
                $original = $node->nodeValue;
                $replaced = $this->sanitizeText($original ?? '');
                if ($replaced !== $original) {
                    $node->nodeValue = $replaced;
                }
            }
        }

        $root = $doc->getElementsByTagName('root')->item(0);
        if (! $root) {
            return $html;
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    /**
     * Plain-text sanitize (no HTML).
     */
    public function sanitizeText(string $text): string
    {
        return strtr($text, self::REPLACEMENTS);
    }

    /**
     * Strip "活動時間" promo-period paragraphs from scraped article HTML.
     *
     * jerosse.com.tw newsletters repeat the activity start/end dates as
     * prose inside a <p>; we already capture the end date in
     * Article::promo_ends_at, so this redundant block is noise in the UI.
     *
     * Matches: <p>...活動時間：YYYY/MM/DD ... - YYYY/MM/DD HH:MM...</p>
     * including <span>/<strong>/<br> children.
     */
    public function stripPromoTimeBlocks(string $html): string
    {
        if ($html === '') return $html;

        // Remove any <p>...</p> that contains "活動時間" AND a full date
        $stripped = preg_replace(
            '/<p\b[^>]*>(?:(?!<\/p>).)*?活動時間[:：][^<]{0,30}\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}(?:(?!<\/p>).)*?<\/p>/su',
            '',
            $html,
        );
        if ($stripped === null) return $html;

        // Also clean up any now-empty <p></p> or <p>&nbsp;</p> left behind
        $stripped = preg_replace('/<p[^>]*>\s*(?:&nbsp;|\s)*\s*<\/p>/s', '', $stripped) ?? $stripped;

        return $stripped;
    }

    /**
     * Append the standard food-supplement disclaimer block.
     * Strips ANY prior disclaimer-like block first (both legacy orphan
     * blocks without a class, and corrupt "醫保健感受能" variants from
     * earlier sanitizer bugs).
     */
    public function appendDisclaimer(string $html, string $kind = 'article'): string
    {
        $message = $kind === 'product'
            ? '本產品為食品，非藥品，不具醫療效能，亦無法取代正規醫療。孕婦、哺乳中婦女、慢性疾病、服用特殊藥物者，請先諮詢醫師或營養師。請依建議量食用，均衡飲食並搭配規律運動，效果因人而異。'
            : '本內容僅供健康保健資訊分享，不構成醫療建議或療效宣稱。文中產品皆屬食品，非藥品，不具醫療效能。若您有特定健康狀況，請諮詢醫師或營養師。';

        $disclaimer = '<hr><div class="legal-disclaimer" style="margin-top:1.5rem;padding:1rem;background:#fdf7ef;border-left:4px solid #9F6B3E;font-size:0.85em;color:#7a5836;line-height:1.7;border-radius:0 0.5rem 0.5rem 0;"><strong>健康食品提醒</strong><br>' . $message . '</div>';

        $clean = $html;

        // 1) Strip canonical disclaimer (has class="legal-disclaimer")
        $clean = preg_replace('/<hr>?\s*<div[^>]*class="[^"]*legal-disclaimer[^"]*"[^>]*>.*?<\/div>/s', '', $clean) ?? $clean;

        // 2) Strip legacy/orphan disclaimer blocks: any <hr> + <div> containing
        //    "健康食品提醒", regardless of class. Covers earlier manual patches
        //    and corrupted "醫保健感受能" variants.
        //    Repeat until no match (handles multiple stacked orphans).
        do {
            $before = $clean;
            $clean = preg_replace('/<hr>?\s*<div[^>]*>\s*<strong>\s*健康食品提醒\s*<\/strong>.*?<\/div>/s', '', $clean) ?? $clean;
        } while ($clean !== $before);

        return rtrim($clean) . $disclaimer;
    }

    /**
     * Full pipeline: sanitize + append disclaimer.
     */
    public function process(string $html, string $kind = 'article'): string
    {
        return $this->appendDisclaimer($this->sanitize($html), $kind);
    }

    /**
     * Generate a short risk report without mutating content.
     * Returns an array of matched forbidden terms (original text).
     *
     * Scans only user-visible text: strips HTML attributes, inline styles,
     * image alt text (proper nouns like "品質保證金像獎" are acceptable there),
     * and the legal disclaimer block itself.
     */
    public function riskReport(string $text): array
    {
        if ($text === '') return [];

        // Strip the disclaimer block (both HTML and plain-text fallbacks)
        $stripped = preg_replace('/<hr>?\s*<div[^>]*legal-disclaimer[^>]*>.*?<\/div>/s', '', $text) ?? $text;

        // Reduce HTML → visible text only.
        // 1. Remove <script>/<style> blocks entirely.
        $stripped = preg_replace('/<(script|style)[^>]*>.*?<\/\\1>/is', '', $stripped) ?? $stripped;
        // 2. Remove tag attributes (alt=, style=, data-*=) — keep tag names + text content.
        $stripped = preg_replace('/<([a-zA-Z][a-zA-Z0-9]*)\s+[^>]*>/', '<$1>', $stripped) ?? $stripped;
        // 3. Strip remaining tags.
        $stripped = strip_tags($stripped);
        // 4. Strip disclaimer plain-text wording.
        $stripped = preg_replace('/本產品為食品.*?效果因人而異。?/s', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/本內容僅供健康保健.*?諮詢醫師或營養師。?/s', '', $stripped) ?? $stripped;

        $hits = [];
        foreach (array_keys(self::REPLACEMENTS) as $term) {
            if (mb_stripos($stripped, $term) !== false) {
                $hits[] = $term;
            }
        }
        return array_values(array_unique($hits));
    }
}
