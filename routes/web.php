<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
})->name('home');

//Route::get("/auth/telegram/redirect",function(){
//    return Socialite::driver('telegram')->redirect();
//});


Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::get('/auth/telegram/redirect',function (){
    return Socialite::driver('telegram')->redirect();
});
Route::get('/auth/telegram/callback/', function () {
    $telegramUser = Socialite::driver('telegram')->stateless()->user();

    $avatarUrl = $telegramUser->user['photo_url'] ?? null;
    $avatarPath = null;

    if ($avatarUrl) {
        try {
            $imageContents = Http::get($avatarUrl)->body();
            $filename = 'avatars/telegram_' . $telegramUser->getId() . '.jpg';
            Storage::disk('public')->put($filename, $imageContents);
            $avatarPath = Storage::url($filename);
        } catch (\Exception $e) {
            // Log or ignore the avatar fetch error
        }
    }

    // 🔍 Check if user already exists
    $user = User::where('telegram_id', $telegramUser->getId())->first();

    if ($user) {
        // Update existing user data if needed
        $user->update([
            'name' => $telegramUser->getName() ?? $telegramUser->user['first_name'] ?? null,
            'telegram_username' => $telegramUser->user['username'] ?? null,
            'telegram_avatar' => $avatarPath ?? $user->telegram_avatar,
            'email' => $telegramUser->getEmail() ?? $user->email,
        ]);
    } else {
        // Create new user
        $user = User::create([
            'telegram_id' => $telegramUser->getId(),
            'name' => $telegramUser->getName() ?? $telegramUser->user['first_name'] ?? null,
            'telegram_username' => $telegramUser->user['username'] ?? null,
            'telegram_avatar' => $avatarPath,
            'email' => $telegramUser->getEmail() ?? $telegramUser->getId() . '@telegram.local',
            'password' => bcrypt($telegramUser->user['first_name'] ?? 'password'),
        ]);
    }

    Auth::login($user->fresh());

    return redirect()->route('dashboard');
});

require __DIR__.'/auth.php';
