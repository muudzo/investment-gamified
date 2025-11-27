<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_shows_custom_content()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Ensure the exact string from the user's view exists — non-destructive check
        // Match an unambiguous substring that's present in the view to avoid any escaping issues
        $response->assertSeeText('get started');
    }
}
