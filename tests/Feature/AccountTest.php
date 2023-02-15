<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class AccountTest extends TestCase
{
    public function testCreate()
    {
        $response = $this->post('/account/create', [
            'email' => 'teste@teste.com',
            'name' => 'Teste',
            'phone' => '123456789',
            'phone_country' => 'BR',
            'brand_name' => 'Teste Brand',
            'password' => Str::random(10),
        ]);

        $response->assertJson([
            'success' => true,
        ]);
    }

    public function testLogin()
    {
        $response = $this->post('/account/login', [
            'email' => 'kleber.santos@gobliver.com',
            'password' => 'rbSwh7DQ72de98A7uX75CPTz',
        ]);

        $response->assertJson([
            'success' => true,
        ]);
    }
}
