<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    public function test_login_page_renders(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_register_page_renders(): void
    {
        if (! \Illuminate\Support\Facades\Route::has('register')) {
            $this->markTestSkipped('Registration is disabled.');
        }

        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_forgot_password_page_renders(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_page_renders(): void
    {
        $response = $this->get(route('password.reset', [
            'token' => 'dummy-token',
            'email' => 'test@example.com',
        ]));

        $response->assertOk();
    }
}
