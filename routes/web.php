<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;



Route::get('/delete-account', function () {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Account verwijderen</title>
    </head>
    <body>
        <h1>Account verwijderen</h1>

        <form method="POST" action="/delete-account">
            '.csrf_field().'

            <p>
                <label>Gebruikersnaam</label><br>
                <input type="text" name="username" required>
            </p>

            <p>
                <label>Wachtwoord</label><br>
                <input type="password" name="password" required>
            </p>

            <button type="submit">
                Account verwijderen
            </button>
        </form>
    </body>
    </html>';
});

Route::post('/delete-account', function (Request $request) {

    $request->validate([
        'username' => 'required',
        'password' => 'required',
    ]);

    $user = User::where('username', $request->username)->first();

    if (!$user) {
        return 'Gebruiker niet gevonden.';
    }

    if (!Hash::check($request->password, $user->password)) {
        return 'Onjuist wachtwoord.';
    }

    $user->tokens()->delete(); // Sanctum
    $user->delete();

    return '
    <h1>Account verwijderd</h1>
    <p>Je account en gekoppelde gegevens zijn verwijderd.</p>';
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
