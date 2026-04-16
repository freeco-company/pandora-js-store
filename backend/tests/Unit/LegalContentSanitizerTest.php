<?php

namespace Tests\Unit;

use App\Services\LegalContentSanitizer;
use PHPUnit\Framework\TestCase;

class LegalContentSanitizerTest extends TestCase
{
    private LegalContentSanitizer $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = new LegalContentSanitizer();
    }

    public function test_sanitize_text_rewrites_forbidden_terms(): void
    {
        $this->assertSame('調理感冒', $this->s->sanitizeText('治療感冒'));
        $this->assertSame('有感 無副作用', $this->s->sanitizeText('特效 無副作用'));
        $this->assertSame('高比例 天然', $this->s->sanitizeText('100% 天然'));
        $this->assertSame('體重管理方案', $this->s->sanitizeText('減肥方案'));
        $this->assertSame('亮顏系列', $this->s->sanitizeText('美白系列'));
    }

    public function test_sanitize_html_preserves_tags(): void
    {
        $input = '<p>有效治療感冒，<strong>100%</strong> 天然</p>';
        $output = $this->s->sanitize($input);
        $this->assertStringContainsString('<p>', $output);
        $this->assertStringContainsString('<strong>', $output);
        $this->assertStringNotContainsString('治療', $output);
        $this->assertStringNotContainsString('100%', $output);
        $this->assertStringContainsString('調理', $output);
        $this->assertStringContainsString('高比例', $output);
    }

    public function test_append_disclaimer_adds_block(): void
    {
        $html = '<p>內容</p>';
        $out = $this->s->appendDisclaimer($html, 'article');
        $this->assertStringContainsString('legal-disclaimer', $out);
        $this->assertStringContainsString('健康食品提醒', $out);
        $this->assertStringContainsString('本內容僅供健康保健資訊', $out);
    }

    public function test_append_disclaimer_is_idempotent(): void
    {
        $html = '<p>內容</p>';
        $once = $this->s->appendDisclaimer($html);
        $twice = $this->s->appendDisclaimer($once);
        // Only one disclaimer block
        $this->assertSame(
            1,
            substr_count($twice, 'legal-disclaimer'),
            'Disclaimer should not be duplicated on repeat calls'
        );
    }

    public function test_product_disclaimer_differs_from_article(): void
    {
        $article = $this->s->appendDisclaimer('<p>x</p>', 'article');
        $product = $this->s->appendDisclaimer('<p>x</p>', 'product');
        $this->assertStringContainsString('本內容僅供', $article);
        $this->assertStringContainsString('本產品為食品', $product);
    }

    public function test_risk_report_lists_matched_terms(): void
    {
        $risks = $this->s->riskReport('這款產品可以治療感冒、根治過敏，還能美白、減肥');
        sort($risks);
        $this->assertContains('治療', $risks);
        $this->assertContains('根治', $risks);
        $this->assertContains('美白', $risks);
        $this->assertContains('減肥', $risks);
    }

    public function test_risk_report_empty_when_clean(): void
    {
        $risks = $this->s->riskReport('每日補充，維持活力');
        $this->assertSame([], $risks);
    }

    public function test_risk_report_ignores_terms_inside_disclaimer(): void
    {
        $processed = $this->s->process('<p>維持活力</p>', 'article');
        $risks = $this->s->riskReport($processed);
        $this->assertSame([], $risks, 'Disclaimer wording should not trigger risk flags');
    }

    public function test_risk_report_ignores_terms_inside_attributes(): void
    {
        // CSS style + img alt + data-attr — should NOT flag
        $html = '<div style="width: 100%"><img alt="台灣品質保證金像獎"/><p>正常內容</p></div>';
        $this->assertSame([], $this->s->riskReport($html));
    }

    public function test_risk_report_catches_terms_in_visible_text(): void
    {
        $html = '<div style="color: red"><p>本品可以治療感冒</p></div>';
        $risks = $this->s->riskReport($html);
        $this->assertContains('治療', $risks);
    }

    public function test_process_runs_full_pipeline(): void
    {
        $out = $this->s->process('<p>100% 治療效果</p>', 'product');
        $this->assertStringContainsString('legal-disclaimer', $out);
        $this->assertStringNotContainsString('100%', $out);
        $this->assertStringNotContainsString('治療', $out);
    }

    public function test_sanitize_does_not_corrupt_disclaimer_wording(): void
    {
        // Simulate content that already had a canonical disclaimer
        $withDisclaimer = $this->s->process('<p>正常內容</p>', 'product');
        // Now run sanitize again — the "不具療效" inside disclaimer must NOT become "不具保健感受"
        $rerun = $this->s->sanitize($withDisclaimer);
        $this->assertStringContainsString('不具醫療效能', $rerun);
        $this->assertStringNotContainsString('不具醫保健感受能', $rerun);
    }

    public function test_append_disclaimer_strips_legacy_orphan_blocks(): void
    {
        // Simulate old orphan block: <hr> + <div> WITHOUT class, but with 健康食品提醒
        $legacy = '<p>商品說明</p><hr><div style="background:#fdf7ef;border-left:4px solid #9F6B3E;"><strong>健康食品提醒</strong><br>本產品為食品，非藥品，不具醫保健感受能</div>';
        $out = $this->s->appendDisclaimer($legacy, 'product');
        // Only one canonical disclaimer remains
        $this->assertSame(1, substr_count($out, '健康食品提醒'), 'orphan block not stripped');
        $this->assertStringContainsString('legal-disclaimer', $out);
        $this->assertStringNotContainsString('不具醫保健感受能', $out);
    }

    public function test_strip_promo_time_blocks_removes_activity_paragraph(): void
    {
        $html = '<p>前言</p>'
            . '<p><span style="color:#ff0000;">▲正式活動時間：2026/3/26(四)12:00 - 2026/4/13(一) 23:59</span><br>'
            . '<span>▲海外活動時間：2026/3/26(四)12:00 - 2026/4/20(一) 23:59</span></p>'
            . '<p>正文內容</p>';

        $out = $this->s->stripPromoTimeBlocks($html);

        $this->assertStringNotContainsString('活動時間', $out);
        $this->assertStringContainsString('前言', $out);
        $this->assertStringContainsString('正文內容', $out);
    }

    public function test_strip_promo_time_leaves_content_without_pattern_untouched(): void
    {
        $html = '<p>本文章無任何活動時間相關內容</p><p>但提到「活動」兩個字</p>';
        $this->assertSame($html, $this->s->stripPromoTimeBlocks($html));
    }

    public function test_append_disclaimer_strips_stacked_orphans(): void
    {
        $stacked = '<p>x</p>'
            . '<hr><div><strong>健康食品提醒</strong><br>old 1</div>'
            . '<hr><div><strong>健康食品提醒</strong><br>old 2</div>';
        $out = $this->s->appendDisclaimer($stacked);
        $this->assertSame(1, substr_count($out, '健康食品提醒'));
    }
}
