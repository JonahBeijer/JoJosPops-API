<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JOJO'S POPS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#440075',
                        brandDark: '#1A1A1A',
                        brandLight: '#F6F0FA',
                        accentRed: '#FF3B30',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-brandDark antialiased flex flex-col min-h-screen">

<!-- NAVIGATION -->
<nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="text-lg font-black tracking-wider text-brandDark">
                JOJO'S <span class="text-brand">POPS</span> <span class="text-xs text-gray-400 uppercase tracking-widest ml-2">Admin</span>
            </span>
        </div>
        <div>
            <!-- Je kunt hier een logout knop toevoegen -->
            <span class="text-sm font-semibold text-gray-500">Beveiligde Omgeving</span>
        </div>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="max-w-7xl mx-auto px-6 py-10 w-full flex-1">

    <div class="mb-8">
        <h1 class="text-3xl font-black text-brandDark">Actieve Meldingen</h1>
        <p class="text-gray-500 mt-2 font-medium">Beheer hier alle gerapporteerde gebruikers en pop-ups.</p>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-6 font-semibold">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
            <tr class="bg-gray-50 border-b border-gray-100 text-sm uppercase tracking-widest text-gray-500">
                <th class="p-4 font-bold">Datum</th>
                <th class="p-4 font-bold">Melder</th>
                <th class="p-4 font-bold">Type</th>
                <th class="p-4 font-bold">Reden / Info</th>
                <th class="p-4 font-bold text-right">Acties</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($reports as $report)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="p-4 text-sm font-medium text-gray-600">
                        {{ $report->created_at->format('d M Y, H:i') }}
                    </td>
                    <td class="p-4 text-sm font-bold text-brandDark">
                        {{ $report->reporter->name ?? 'Verwijderde Gebruiker' }}
                    </td>
                    <td class="p-4">
                        @if($report->target_type === 'pop')
                            <span class="bg-brandLight text-brand px-2.5 py-1 rounded-md text-xs font-black tracking-wider uppercase">Pop-up (#{{ $report->target_id }})</span>
                        @else
                            <span class="bg-gray-200 text-gray-700 px-2.5 py-1 rounded-md text-xs font-black tracking-wider uppercase">User (#{{ $report->target_id }})</span>
                        @endif
                    </td>
                    <td class="p-4">
                        <p class="text-sm font-bold text-brandDark">{{ $report->reason }}</p>
                        @if($report->description)
                            <p class="text-xs text-gray-500 mt-1 max-w-xs truncate">{{ $report->description }}</p>
                        @endif
                    </td>
                    <td class="p-4 flex items-center justify-end gap-2">

                        <!-- Negeer / Opgelost -->
                        <form action="{{ route('admin.reports.resolve', $report->id) }}" method="POST">
                            @csrf @method('PATCH')
                            <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">
                                <i class="fa-solid fa-check mr-1"></i> Negeer
                            </button>
                        </form>

                        <!-- Verwijder Actie (Afhankelijk van het type) -->
                        @if($report->target_type === 'pop')
                            <form action="{{ route('admin.pops.delete', $report->target_id) }}" method="POST" onsubmit="return confirm('Weet je zeker dat je deze pop-up wilt verwijderen?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="bg-accentRed/10 hover:bg-accentRed text-accentRed hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">
                                    <i class="fa-solid fa-trash mr-1"></i> Verwijder Pop
                                </button>
                            </form>
                        @else
                            <form action="{{ route('admin.users.delete', $report->target_id) }}" method="POST" onsubmit="return confirm('Weet je zeker dat je deze GEBRUIKER volledig wilt verbannen?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="bg-black hover:bg-gray-800 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">
                                    <i class="fa-solid fa-user-slash mr-1"></i> Ban User
                                </button>
                            </form>
                        @endif

                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-8 text-center text-gray-400 font-medium">
                        <i class="fa-regular fa-face-smile text-3xl mb-3"></i>
                        <p>Geen openstaande meldingen. Alles is veilig!</p>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</main>

</body>
</html>
