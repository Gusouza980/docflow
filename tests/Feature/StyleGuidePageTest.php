<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StyleGuidePageTest extends TestCase
{
    public function test_style_guide_page_is_rendered_with_inertia(): void
    {
        $this->get('/style-guide')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('StyleGuide/Index', false));
    }
}
