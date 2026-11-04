<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Cheap regression guard for the structural accessibility basics on public pages. */
class AccessibilityBaselineTest extends TestCase
{
    public function test_public_pages_keep_the_accessibility_basics(): void
    {
        foreach (['/', '/login', '/register', '/terms', '/privacy', '/refund-policy', '/acceptable-use', '/cookie-policy', '/accessibility', '/privacy/request', '/admin/login', '/help-center'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/<html[^>]+lang="/', $html, "$url: html lang");
            $this->assertStringContainsString('href="#main"', $html, "$url: skip link");
            $this->assertStringContainsString('id="main"', $html, "$url: main landmark target");
            $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html), "$url: exactly one h1");
            $this->assertDoesNotMatchRegularExpression('/user-scalable\s*=\s*no|maximum-scale\s*=\s*1(\.0)?[",\s]/', $html, "$url: zoom must stay enabled");
        }
    }
}
