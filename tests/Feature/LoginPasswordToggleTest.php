<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPasswordToggleTest extends TestCase
{
    public function test_login_page_masks_password_and_shows_closed_eye_by_default(): void
    {
        $html = $this->get(route('login'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="password"[^>]*type="password"|<input[^>]*type="password"[^>]*id="password"/',
            $html
        );
        $this->assertStringContainsString('id="togglePassword"', $html);
        $this->assertStringContainsString('password-toggle-icon-hidden', $html);
        $this->assertStringContainsString('password-toggle-icon-visible', $html);
        $this->assertStringContainsString('aria-label="Show password"', $html);
        $this->assertStringContainsString('aria-pressed="false"', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringNotContainsString('&#128065;', $html);
    }
}
