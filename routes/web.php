<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

Route::get('/', function () {
    return view('welcome');
})->name('home');


Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/reports', [AdminController::class, 'index'])->name('admin.reports');
    Route::patch('/reports/{id}/resolve', [AdminController::class, 'resolveReport'])->name('admin.reports.resolve');

    // Delete routes
    Route::delete('/pops/{id}', [AdminController::class, 'deletePop'])->name('admin.pops.delete');
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
});

// 2. Updated: Clean Tailwind styling for the delete page
Route::get('/delete-account', function () {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Delete Account - JOJO\'S POPS</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>body { font-family: "Plus Jakarta Sans", sans-serif; }</style>
    </head>
    <body class="bg-[#F6F0FA] flex items-center justify-center min-h-screen p-6">
        <div class="max-w-md w-full bg-white p-8 rounded-2xl shadow-xl border border-gray-100">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-black text-[#1A1A1A]">Delete Account</h1>
                <p class="text-sm text-gray-500 mt-2">Enter your credentials to permanently delete your account and data. This action cannot be undone.</p>
            </div>

            <form method="POST" action="/delete-account" class="space-y-4">
                '.csrf_field().'

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#440075] focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#440075] focus:outline-none">
                </div>

                <button type="submit" class="w-full bg-[#FF3B30] text-white font-bold py-3 px-4 rounded-xl hover:bg-red-600 transition-colors mt-4 shadow-lg shadow-red-500/20">
                    Permanently Delete
                </button>
            </form>
        </div>
    </body>
    </html>';
});


// 3. Updated: Succes/error messages with a clean look
Route::post('/delete-account', function (Request $request) {

    $request->validate([
        'username' => 'required',
        'password' => 'required',
    ]);

    $user = User::where('username', $request->username)->first();

    // Helper function for error message styling
    $errorView = function($message) {
        return '<!DOCTYPE html><html lang="en"><head><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-[#F6F0FA] flex items-center justify-center min-h-screen p-6"><div class="max-w-md w-full bg-white p-8 rounded-2xl shadow-xl text-center"><div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl font-bold">!</div><h1 class="text-xl font-bold text-gray-800 mb-2">Error</h1><p class="text-gray-600 mb-6">'.$message.'</p><a href="/delete-account" class="inline-block bg-[#440075] text-white px-6 py-2.5 rounded-xl font-bold">Try again</a></div></body></html>';
    };

    if (!$user) {
        return $errorView('User not found.');
    }

    if (!Hash::check($request->password, $user->password)) {
        return $errorView('Incorrect password.');
    }

    $user->tokens()->delete(); // Sanctum
    $user->delete();

    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-[#F6F0FA] flex items-center justify-center min-h-screen p-6">
        <div class="max-w-md w-full bg-white p-8 rounded-2xl shadow-xl text-center">
            <div class="w-16 h-16 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl font-bold">✓</div>
            <h1 class="text-2xl font-black text-gray-800 mb-2">Account Deleted</h1>
            <p class="text-gray-600 mb-6">Your account and all associated data have been permanently removed from our system.</p>
            <a href="/" class="inline-block border-2 border-[#440075] text-[#440075] px-6 py-2.5 rounded-xl font-bold hover:bg-[#440075] hover:text-white transition-colors">
                Back to home
            </a>
        </div>
    </body>
    </html>';
});

Route::get('/sitemap.xml', function () {
    return response()
        ->view('sitemap')
        ->header('Content-Type', 'text/xml');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
