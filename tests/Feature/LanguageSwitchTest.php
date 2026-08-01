<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_default_locale_is_chinese(): void
    {
        $this->get('/login')->assertSee('登录');
    }

    public function test_switching_to_french_translates_static_labels_via_session(): void
    {
        $this->get('/language/fr')->assertRedirect();

        $this->get('/login')->assertSee('Connexion');
    }

    public function test_switching_back_to_chinese_restores_the_original_text(): void
    {
        $this->get('/language/fr');
        $this->get('/language/zh_CN')->assertRedirect();

        $this->get('/login')->assertSee('登录');
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        $this->get('/language/en')->assertNotFound();
    }

    public function test_switching_language_does_not_translate_user_entered_data(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty, '排水');
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, [$project], teams: [$specialty->team]);

        $this->get('/language/fr');

        $this->actingAs($user)
            ->get("/projects/{$project->id}/subcategories/{$subcategory->id}")
            ->assertSee('排水')
            ->assertSee("Vue d'ensemble");
    }
}
