<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\ApplicationSettings;
use App\Services\LocaleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'active'])->get('/locale-probe', fn (): string => app()->getLocale())
            ->name('locale.probe');
    }

    public function test_available_locales_always_include_english_and_json_files(): void
    {
        $esPath = lang_path('es.json');

        file_put_contents($esPath, '{}');

        try {
            $locales = app(LocaleManager::class)->availableLocales();

            $this->assertSame('en', $locales[0]);
            $this->assertContains('fr', $locales);
            $this->assertContains('es', $locales);
        } finally {
            @unlink($esPath);
        }
    }

    public function test_missing_english_locale_file_is_recreated_as_empty_json(): void
    {
        $enPath = lang_path('en.json');
        $originalContents = file_exists($enPath) ? file_get_contents($enPath) : null;

        @unlink($enPath);

        try {
            $locales = app(LocaleManager::class)->availableLocales();

            $this->assertContains('en', $locales);
            $this->assertFileExists($enPath);
            $this->assertSame("{}\n", file_get_contents($enPath));
        } finally {
            file_put_contents($enPath, $originalContents ?? "{}\n");
        }
    }

    public function test_application_default_locale_is_used_when_user_has_no_preference(): void
    {
        $user = User::factory()->create();

        app(ApplicationSettings::class)->updateDefaultLocale('fr');

        $this
            ->actingAs($user)
            ->get('/locale-probe')
            ->assertOk()
            ->assertSee('fr');
    }

    public function test_user_preferred_locale_overrides_application_default_locale(): void
    {
        $user = User::factory()->create([
            'preferred_locale' => 'en',
        ]);

        app(ApplicationSettings::class)->updateDefaultLocale('fr');

        $this
            ->actingAs($user)
            ->get('/locale-probe')
            ->assertOk()
            ->assertSee('en');
    }

    public function test_user_can_update_personal_locale_preference(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch(route('profile.language.update'), [
                'preferred_locale' => 'fr',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertSame('fr', $user->refresh()->preferred_locale);
    }

    public function test_user_can_return_to_application_default_locale(): void
    {
        $user = User::factory()->create([
            'preferred_locale' => 'fr',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('profile.language.update'), [
                'preferred_locale' => '',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertNull($user->refresh()->preferred_locale);
    }

    public function test_invalid_user_preference_is_cleared_and_application_default_is_used(): void
    {
        $user = User::factory()->create([
            'preferred_locale' => 'de',
        ]);

        app(ApplicationSettings::class)->updateDefaultLocale('fr');

        $this
            ->actingAs($user)
            ->get('/locale-probe')
            ->assertOk()
            ->assertSee('fr')
            ->assertSessionHas('toast');

        $this->assertNull($user->refresh()->preferred_locale);
    }

    public function test_invalid_application_default_locale_is_reset_to_english(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => ApplicationSettings::DEFAULT_LOCALE_KEY],
            ['value' => 'de'],
        );

        Cache::forget(ApplicationSettings::DEFAULT_LOCALE_KEY);

        $this->assertSame('en', app(ApplicationSettings::class)->defaultLocale());
        $this->assertSame('en', Setting::query()->where('key', ApplicationSettings::DEFAULT_LOCALE_KEY)->value('value'));
    }
}
