<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

// 1. Aangepast: Laadt nu de Blade view in plaats van de Inertia render
Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});


// 2. Aangepast: Strakke Tailwind styling voor de verwijder-pagina
Route::get('/delete-account', function () {
    return '
    <!DOCTYPE html>
    <html lang="nl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account verwijderen - JOJO\'S POPS</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>body { font-family: "Plus Jakarta Sans", sans-serif; }</style>
    </head>
    <body class="bg-[#F6F0FA] flex items-center justify-center min-h-screen p-6">
        <div class="max-w-md w-full bg-white p-8 rounded-2xl shadow-xl border border-gray-100">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-black text-[#1A1A1A]">Account verwijderen</h1>
                <p class="text-sm text-gray-500 mt-2">Vul je gegevens in om je account en data permanent te verwijderen. Dit kan niet ongedaan worden gemaakt.</p>
            </div>

            <form method="POST" action="/delete-account" class="space-y-4">
                '.csrf_field().'

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Gebruikersnaam</label>
                    <input type="text" name="username" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#440075] focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Wachtwoord</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#440075] focus:outline-none">
                </div>

                <button type="submit" class="w-full bg-[#FF3B30] text-white font-bold py-3 px-4 rounded-xl hover:bg-red-600 transition-colors mt-4 shadow-lg shadow-red-500/20">
                    Permanent verwijderen
                </button>
            </form>
        </div>
    </body>
    </html>';
});


// 3. Aangepast: Ook de succes/error berichten hebben nu een strakke look
Route::post('/delete-account', function (Request $request) {

    $request->validate([
        'username' => 'required',
        'password' => 'required',
    ]);

    $user = User::where('username', $request->username)->first();

    // Helper functie voor foutmeldingen styling
    $errorView = function($message) {
        return '<!DOCTYPE html><html lang="nl"><head><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-[#F6F0FA] flex items-center justify-center min-h-screen p-6"><div class="max-w-md w-full bg-white p-8 rounded-2xl shadow-xl text-center"><div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl font-bold">!</div><h1 class="text-xl font-bold text-gray-800 mb-2">Foutmelding</h1><p class="text-gray-600 mb-6">'.$message.'</p><a href="/delete-account" class="inline-block bg-[#440075] text-white px-6 py-2.5 rounded-xl font-bold">Probeer opnieuw</a></div></body></html>';
    };

    if (!$user) {
        return $errorView('Gebruiker niet gevonden.');
    }

    if (!Hash::check($request->password, $user->password)) {
        return $errorView('Onjuist wachtwoord.');
    }

    $user->tokens()->delete(); // Sanctum
    $user->delete();

    return '
    <!DOCTYPE html>
    <html lang="nl">
    <head>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-[#F6F0FA] flex items-center justify-center min-h-screen p-6">
        <div class="max-w-md w-full bg-white p-8 rounded-2xl shadow-xl text-center">
            <div class="w-16 h-16 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl font-bold">✓</div>
            <h1 class="text-2xl font-black text-gray-800 mb-2">Account verwijderd</h1>
            <p class="text-gray-600 mb-6">Je account en alle bijbehorende gegevens zijn definitief verwijderd uit ons systeem.</p>
            <a href="/" class="inline-block border-2 border-[#440075] text-[#440075] px-6 py-2.5 rounded-xl font-bold hover:bg-[#440075] hover:text-white transition-colors">
                Terug naar home
            </a>
        </div>
    </body>
    </html>';
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
