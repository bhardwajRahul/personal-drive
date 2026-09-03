<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BaseFeatureTest;

class UsernameCapsTest extends BaseFeatureTest
{
    use RefreshDatabase;

    public function test_username_with_caps_is_accepted_on_login(): void
    {
        User::create([
            'username' => 'TestUser',
            'is_admin' => 1,
            'password' => 'password123',
        ]);

        $response = $this->post(route('login'), [
            '_token' => csrf_token(),
            'username' => 'TestUser',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('drive', absolute: false));
    }

    public function test_username_with_mixed_case_is_accepted(): void
    {
        User::create([
            'username' => 'AdminUser2024',
            'is_admin' => 1,
            'password' => 'secureP@ss1',
        ]);

        $response = $this->post(route('login'), [
            '_token' => csrf_token(),
            'username' => 'AdminUser2024',
            'password' => 'secureP@ss1',
        ]);

        $response->assertRedirect(route('drive', absolute: false));
    }

    public function test_username_regex_rejects_special_chars(): void
    {
        $regex = '/^[0-9A-Za-z\_]+$/';

        $this->assertMatchesRegularExpression($regex, 'TestUser');
        $this->assertMatchesRegularExpression($regex, 'AdminUser2024');
        $this->assertMatchesRegularExpression($regex, 'user_123');

        $this->assertDoesNotMatchRegularExpression($regex, 'user name');
        $this->assertDoesNotMatchRegularExpression($regex, 'user.name');
        $this->assertDoesNotMatchRegularExpression($regex, 'user@name');
    }
}
