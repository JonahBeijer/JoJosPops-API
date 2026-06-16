<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JOJO'S POPS - Ontdek Pop-ups in de Buurt</title>
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
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="bg-white text-brandDark antialiased selection:bg-brand selection:text-white">

<nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2">
                <span class="text-lg font-black tracking-wider text-brandDark">
                    JOJO'S <span class="text-brand">POPS</span>
                </span>
        </div>
        <a href="#download" class="bg-brand text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-brand/20 hover:bg-brand/90 transition-all duration-200">
            Download App
        </a>
    </div>
</nav>

<header class="relative overflow-hidden bg-brandLight pt-16 pb-20 lg:pt-24 lg:pb-28">
    <div class="absolute w-72 h-72 rounded-full bg-brand/5 -top-20 -right-20"></div>
    <div class="absolute w-48 h-48 rounded-full bg-brand/5 -bottom-10 right-1/4"></div>

    <div class="max-w-6xl mx-auto px-6 relative z-10 grid lg:grid-cols-12 gap-12 items-center">
        <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
            <div class="inline-flex items-center gap-2 bg-brand text-white px-3 py-1 rounded-lg text-xs font-bold tracking-wider uppercase">
                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span> Right Now
            </div>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-brandDark leading-none">
                Rise & find your <br class="hidden sm:inline">next <span class="text-brand">pop-up</span>.
            </h1>
            <p class="text-base sm:text-lg text-gray-600 font-medium max-w-xl mx-auto lg:mx-0">
                Hottest pop-ups, live in jouw area. Mis nooit meer exclusieve drops, unieke events en verborgen spots van hosts die je volgt.
            </p>

            <div id="download" class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start pt-4">
                <a href="#" target="_blank" class="flex items-center gap-3 bg-brandDark text-white px-6 py-3 rounded-xl hover:bg-black transition-all duration-200 shadow-xl shadow-black/10">
                    <i class="fa-brands fa-apple text-2xl"></i>
                    <div class="text-left">
                        <p class="text-[10px] font-medium text-gray-400 uppercase tracking-wider">Download in de</p>
                        <p class="text-sm font-bold -mt-0.5">App Store</p>
                    </div>
                </a>

                <a href="#" target="_blank" class="flex items-center gap-3 bg-brandDark text-white px-6 py-3 rounded-xl hover:bg-black transition-all duration-200 shadow-xl shadow-black/10">
                    <i class="fa-brands fa-google-play text-xl"></i>
                    <div class="text-left">
                        <p class="text-[10px] font-medium text-gray-400 uppercase tracking-wider">Ontdek het op</p>
                        <p class="text-sm font-bold -mt-0.5">Google Play</p>
                    </div>
                </a>
            </div>
        </div>

        <div class="lg:col-span-5 flex justify-center">
            <div class="relative w-64 h-[520px] bg-brandDark rounded-[40px] p-3 shadow-2xl border-4 border-gray-800">
                <div class="absolute top-4 left-1/2 transform -translate-x-1/2 w-28 h-4 bg-gray-800 rounded-full z-20"></div>
                <div class="w-full h-full bg-white rounded-[32px] overflow-hidden relative flex flex-col justify-between p-4">
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-xs font-black tracking-widest text-brand">JOJO'S POPS</span>
                        <i class="fa-regular fa-bell text-sm"></i>
                    </div>

                    <div class="border border-gray-100 rounded-2xl overflow-hidden shadow-sm bg-white mb-2">
                        <div class="aspect-[4/5] bg-gray-100 relative">
                            <div class="absolute top-2 left-2 bg-accentRed text-white px-2 py-0.5 rounded text-[8px] font-extrabold flex items-center gap-1">
                                <span class="w-1 h-1 rounded-full bg-white"></span>LIVE
                            </div>
                            <div class="absolute top-2 right-2 bg-white w-6 h-6 rounded-full flex items-center justify-center shadow-sm">
                                <i class="fa-regular fa-heart text-xs text-brandDark"></i>
                            </div>
                            <div class="w-full h-full bg-gradient-to-br from-purple-200 to-indigo-200 flex items-center justify-center">
                                <i class="fa-regular fa-image text-brand/20 text-3xl"></i>
                            </div>
                        </div>
                        <div class="p-2.5 space-y-1">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-brand tracking-wide">FASHION DROP</span>
                                <span class="bg-brandLight text-brand text-[9px] font-bold px-1.5 py-0.5 rounded">1.2 km</span>
                            </div>
                            <p class="text-xs font-black text-brandDark truncate">Secret Vintage Pop-Up</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<section class="max-w-6xl mx-auto px-6 py-20 lg:py-28">
    <div class="text-center max-w-2xl mx-auto mb-16 space-y-3">
        <h2 class="text-xs font-extrabold tracking-widest text-brand uppercase">Features</h2>
        <p class="text-3xl sm:text-4xl font-black tracking-tight text-brandDark">Wat maakt JOJO'S POPS uniek?</p>
    </div>

    <div class="grid md:grid-cols-3 gap-8">
        <div class="p-8 rounded-2xl border border-gray-100 bg-white hover:shadow-xl hover:border-transparent transition-all duration-300 space-y-4">
            <div class="w-12 h-12 rounded-xl bg-brandLight flex items-center justify-center text-brand text-xl">
                <i class="fa-solid fa-location-crosshairs"></i>
            </div>
            <h3 class="text-lg font-bold text-brandDark">Trending Nearby</h3>
            <p class="text-sm text-gray-500 leading-relaxed">
                Bekijk direct op basis van je GPS-locatie welke pop-ups er op dit moment het dichtst bij jou in de buurt actief of trending zijn.
            </p>
        </div>

        <div class="p-8 rounded-2xl border border-gray-100 bg-white hover:shadow-xl hover:border-transparent transition-all duration-300 space-y-4">
            <div class="w-12 h-12 rounded-xl bg-brandLight flex items-center justify-center text-brand text-xl">
                <i class="fa-solid fa-bell-concierge"></i>
            </div>
            <h3 class="text-lg font-bold text-brandDark">Gepersonaliseerde Feed</h3>
            <p class="text-sm text-gray-500 leading-relaxed">
                Volg je favoriete hosts, artiesten en organisatoren. Je persoonlijke 'For You Feed' vult zich automatisch met events die aansluiten op jouw smaak.
            </p>
        </div>

        <div class="p-8 rounded-2xl border border-gray-100 bg-white hover:shadow-xl hover:border-transparent transition-all duration-300 space-y-4">
            <div class="w-12 h-12 rounded-xl bg-brandLight flex items-center justify-center text-brand text-xl">
                <i class="fa-solid fa-map-marked-alt"></i>
            </div>
            <h3 class="text-lg font-bold text-brandDark">Interactieve Kaart</h3>
            <p class="text-sm text-gray-500 leading-relaxed">
                Schakel moeiteloos tussen de strakke lijstweergave of de interactieve, geclusterde kaart om visueel te navigeren naar jouw volgende bestemming.
            </p>
        </div>
    </div>
</section>

<section class="bg-brandDark text-white py-16 relative overflow-hidden">
    <div class="absolute w-96 h-96 rounded-full bg-brand/20 -bottom-20 -left-20 blur-3xl"></div>
    <div class="max-w-4xl mx-auto px-6 text-center relative z-10 space-y-6">
            <span class="inline-block bg-brand px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase">
                <i class="fa-solid fa-star text-yellow-400 mr-1"></i> JOJO'S Premium
            </span>
        <h2 class="text-3xl sm:text-4xl font-black tracking-tight">Krijg toegang tot exclusieve Invite-Only Pops</h2>
        <p class="text-gray-400 max-w-lg mx-auto text-sm sm:text-base">
            Upgrade in de app naar Premium en ontgrendel geheime locaties, pre-drops en speciale gastenlijsten voor de meest exclusieve pop-ups.
        </p>
    </div>
</section>

<footer class="bg-white border-t border-gray-100 py-12">
    <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6 text-sm text-gray-500">
        <p>&copy; {{ date('Y') }} JOJO'S POPS. Alle rechten voorbehouden.</p>

        <div class="flex flex-wrap gap-6 justify-center">
            <a href="/privacy" class="hover:text-brand font-medium transition-colors">Privacybeleid</a>
            <a href="/terms" class="hover:text-brand font-medium transition-colors">Voorwaarden</a>
            <a href="/delete-account" class="text-red-500 hover:text-red-600 font-semibold transition-colors">
                <i class="fa-regular fa-trash-can mr-1"></i> Account Verwijderen
                </vega>
        </div>
    </div>
</footer>

</body>
</html>
