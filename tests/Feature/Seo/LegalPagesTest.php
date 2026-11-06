<?php

namespace Tests\Feature\Seo;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_offer_page_renders_terms_with_requisites_and_links(): void
    {
        $response = $this->get('/offer');

        $response->assertOk();
        $response->assertSee('Публичная оферта — Sites-shop', false);
        $response->assertSee('ПУБЛИЧНАЯ ОФЕРТА', false);
        $response->assertSee('Ковалёв Вячеслав Юрьевич', false);
        $response->assertSee('https://sites-shop.ru/pricing', false);
        $response->assertSee(url('/privacy'), false);
        $response->assertDontSee('+7 991 078-75-56', false);
        $response->assertSee('index, follow', false);
    }

    public function test_privacy_page_renders_policy_and_links_to_offer(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('Политика конфиденциальности — Sites-shop', false);
        $response->assertSee('Ковалёв Вячеслав Юрьевич', false);
        $response->assertSee('Робокасса', false);
        $response->assertSee(url('/offer'), false);
        $response->assertDontSee('+7 991 078-75-56', false);
        $response->assertSee('index, follow', false);
    }

    public function test_legal_pages_have_canonical_urls(): void
    {
        $this->get('/offer')->assertSee('rel="canonical" href="'.url('/offer').'"', false);
        $this->get('/privacy')->assertSee('rel="canonical" href="'.url('/privacy').'"', false);
    }

    public function test_footer_links_to_both_documents_on_each_page(): void
    {
        $this->get('/offer')
            ->assertSee('href="'.url('/privacy').'"', false)
            ->assertSee('href="'.url('/offer').'"', false);

        $this->get('/privacy')
            ->assertSee('href="'.url('/offer').'"', false)
            ->assertSee('href="'.url('/privacy').'"', false);
    }
}
