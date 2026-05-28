<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebDocumentationPageTest extends TestCase
{
    public function test_docs_page_is_public_and_renders_usage_documentation(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Docs/Index', false));
    }
}
