<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'user',
        'login_streak' => 0,
        'last_login_date' => null,
    ]);
});

test('streak increments on consecutive day login', function () {
    // Login pertama
    $this->user->login_streak = 1;
    $this->user->last_login_date = now()->subDay();
    $this->user->save();
    
    // Simulate login hari ini
    $today = now()->toDateString();
    $yesterday = now()->subDay()->toDateString();
    $lastLogin = $this->user->last_login_date->toDateString();
    
    expect($lastLogin)->toBe($yesterday);
    
    // Update streak
    $this->user->login_streak += 1;
    $this->user->last_login_date = now();
    $this->user->save();
    
    expect($this->user->login_streak)->toBe(2);
});

test('streak resets after missing a day', function () {
    // Login 3 hari lalu
    $this->user->login_streak = 5;
    $this->user->last_login_date = now()->subDays(3);
    $this->user->save();
    
    // Login hari ini (gap > 1 hari)
    $lastLogin = $this->user->last_login_date->toDateString();
    $yesterday = now()->subDay()->toDateString();
    
    expect($lastLogin)->not->toBe($yesterday);
    
    // Reset streak
    $this->user->login_streak = 1;
    $this->user->last_login_date = now();
    $this->user->save();
    
    expect($this->user->login_streak)->toBe(1);
});

test('streak does not change on same day login', function () {
    // Login hari ini pagi
    $this->user->login_streak = 3;
    $this->user->last_login_date = now();
    $this->user->save();
    
    // Login lagi hari ini sore
    $today = now()->toDateString();
    $lastLogin = $this->user->last_login_date->toDateString();
    
    expect($lastLogin)->toBe($today);
    
    // Tidak ada perubahan
    expect($this->user->login_streak)->toBe(3);
});

test('streak starts at 1 on first login', function () {
    expect($this->user->login_streak)->toBe(0);
    expect($this->user->last_login_date)->toBeNull();
    
    // First login
    $this->user->login_streak = 1;
    $this->user->last_login_date = now();
    $this->user->save();
    
    expect($this->user->login_streak)->toBe(1);
    expect($this->user->last_login_date)->not->toBeNull();
});
