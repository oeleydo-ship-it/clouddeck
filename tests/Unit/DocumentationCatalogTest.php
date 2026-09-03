<?php

namespace Tests\Unit;

use App\Support\DocumentationCatalog;
use Tests\TestCase;

class DocumentationCatalogTest extends TestCase
{
    public function test_sections_load_markdown_and_render_html(): void
    {
        $sections = DocumentationCatalog::sections(true, true);

        $this->assertNotEmpty($sections);
        $this->assertSame('getting-started', $sections[0]['id']);
        $this->assertSame('Getting started', $sections[0]['title']);
        $this->assertStringContainsString('<h1>', $sections[0]['html']);
        $this->assertStringContainsString('Uplary', $sections[0]['html']);
    }

    public function test_optional_sections_respect_feature_flags(): void
    {
        $all = collect(DocumentationCatalog::sections(true, true))->pluck('id');
        $minimal = collect(DocumentationCatalog::sections(false, false))->pluck('id');

        $this->assertTrue($all->contains('managed-servers'));
        $this->assertTrue($all->contains('staging'));
        $this->assertFalse($minimal->contains('managed-servers'));
        $this->assertFalse($minimal->contains('staging'));
    }
}
