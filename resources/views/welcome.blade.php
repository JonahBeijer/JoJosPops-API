<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JOJO'S POPS - Discover the Exclusive Pop-Up Scene</title>
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
                        premiumGold: '#D4AF37',
                        premiumText: '#B8860B',
                        premiumLight: '#FFFBE6'
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
        /* Hide scrollbar for the phone mockup to make it look native */
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="bg-white text-brandDark antialiased selection:bg-brand selection:text-white flex flex-col min-h-screen">

<!-- NAVIGATION -->
<nav class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="text-lg font-black tracking-wider text-brandDark">
                JOJO'S <span class="text-brand">POPS</span>
            </span>
        </div>
        <a href="#download" class="bg-brand text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-brand/20 hover:bg-brand/90 transition-all duration-200">
            Get Early Access
        </a>
    </div>
</nav>

<!-- HERO SECTION -->
<header class="relative overflow-hidden bg-white pt-16 pb-20 lg:pt-24 lg:pb-28 border-b border-gray-100">
    <!-- Background Design Elements -->
    <div class="absolute w-96 h-96 rounded-full bg-brandLight -top-20 -right-20 blur-3xl opacity-80 pointer-events-none"></div>
    <div class="absolute w-72 h-72 rounded-full bg-purple-50 -bottom-10 right-1/3 blur-3xl opacity-60 pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-6 relative z-10 grid lg:grid-cols-12 gap-12 items-center">

        <!-- Pitch / Marketing Copy -->
        <div class="lg:col-span-7 space-y-8 text-center lg:text-left">
            <div class="inline-flex items-center gap-2 bg-brand text-white px-3.5 py-1.5 rounded-lg text-[11px] font-bold tracking-wider uppercase shadow-md shadow-brand/20">
                <span class="w-2 h-2 rounded-full bg-accentRed animate-pulse"></span>
                Live in your city right now
            </div>

            <h1 class="text-5xl sm:text-6xl lg:text-7xl font-black tracking-tight text-brandDark leading-[1.05]">
                Don't hear about it tomorrow.<br>
                <span class="text-brand">Be there today.</span>
            </h1>

            <p class="text-lg sm:text-xl text-gray-500 font-medium max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                The ultimate radar for exclusive drops, secret vintage sales, underground gigs, and limited-time food spots. Connect with top creators and unlock the hidden pulse of your city.
            </p>

            <div id="download" class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start pt-4">
                <a href="#" class="flex items-center justify-center gap-3 bg-brandDark text-white px-8 py-3.5 rounded-xl hover:bg-black transition-all duration-200 shadow-xl shadow-black/10">
                    <i class="fa-brands fa-apple text-2xl"></i>
                    <div class="text-left">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Download on the</p>
                        <p class="text-sm font-bold leading-tight">App Store</p>
                    </div>
                </a>

                <a href="#" class="flex items-center justify-center gap-3 bg-brandLight text-brandDark border border-gray-200 px-8 py-3.5 rounded-xl hover:bg-gray-50 transition-all duration-200 shadow-lg shadow-gray-200/50">
                    <i class="fa-brands fa-google-play text-xl text-brand"></i>
                    <div class="text-left">
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Get it on</p>
                        <p class="text-sm font-bold leading-tight">Google Play</p>
                    </div>
                </a>
            </div>

            <div class="flex items-center justify-center lg:justify-start gap-4 pt-4">
                <div class="flex -space-x-3">
                    <img class="w-10 h-10 rounded-full border-2 border-white object-cover" src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80" alt="User">
                    <img class="w-10 h-10 rounded-full border-2 border-white object-cover" src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=100&q=80" alt="User">
                    <img class="w-10 h-10 rounded-full border-2 border-white object-cover" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&q=80" alt="User">
                </div>
                <p class="text-sm font-semibold text-gray-600">Joined by <span class="text-brandDark font-black">10,000+</span> urban explorers</p>
            </div>
        </div>

        <!-- Phone Mockup -->
        <div class="lg:col-span-5 flex justify-center relative">
            <div class="relative w-[320px] h-[650px] bg-brandDark rounded-[48px] p-3 shadow-2xl border-4 border-gray-800 transform lg:rotate-2 hover:rotate-0 transition-transform duration-500">

                <!-- Phone Notch -->
                <div class="absolute top-4 left-1/2 transform -translate-x-1/2 w-32 h-6 bg-gray-800 rounded-full z-30"></div>

                <!-- Phone Screen -->
                <div class="w-full h-full bg-white rounded-[36px] overflow-hidden relative hide-scrollbar overflow-y-auto pb-8">

                    <!-- App Header -->
                    <div class="sticky top-0 bg-white/95 backdrop-blur-sm z-20 px-5 pt-12 pb-3 border-b border-gray-100 flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-brand rounded-full flex items-center justify-center text-white text-[10px] font-black">JP</div>
                            <span class="text-[13px] font-black tracking-wide text-brandDark">JOJO'S <span class="text-brand">POPS</span></span>
                        </div>
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fa-regular fa-bell text-sm text-brandDark"></i>
                        </div>
                    </div>

                    <!-- App Hero -->
                    <div class="mx-5 mt-5 bg-brandLight rounded-3xl p-5 relative overflow-hidden min-h-[120px]">
                        <div class="absolute w-32 h-32 rounded-full bg-brand/5 -top-10 -right-8"></div>
                        <div class="relative z-10">
                            <div class="inline-block bg-brand text-white px-2.5 py-1 rounded-md text-[9px] font-bold tracking-wider mb-2">
                                Hey, Alex
                            </div>
                            <h2 class="text-xl font-black text-brandDark leading-tight tracking-tight mb-1">Rise & find your<br>next pop.</h2>
                            <p class="text-[11px] font-medium text-gray-500">Fresh events dropped overnight.</p>
                        </div>
                    </div>

                    <!-- Trending Nearby Section -->
                    <div class="mt-7">
                        <div class="px-5 mb-3 flex justify-between items-end">
                            <div>
                                <p class="text-[9px] font-extrabold text-brand tracking-[1px] mb-0.5">RIGHT NOW</p>
                                <h3 class="text-[17px] font-extrabold tracking-tight text-brandDark">Trending Nearby</h3>
                            </div>
                            <span class="text-[11px] font-bold text-brand">See all</span>
                        </div>

                        <!-- Horizontal Scroll Mockup -->
                        <div class="flex gap-3 px-5 overflow-x-auto hide-scrollbar pb-2">
                            <!-- Nearby Card 1 -->
                            <div class="w-[120px] shrink-0">
                                <div class="w-full aspect-[4/5] rounded-2xl bg-gray-200 mb-2 relative overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1555529771-835f59fc5efe?auto=format&fit=crop&w=300&q=80" class="w-full h-full object-cover" alt="Event">
                                    <div class="absolute top-2 left-2 bg-brand/90 px-1.5 py-0.5 rounded text-[7px] font-bold text-white tracking-wider">INVITE</div>
                                    <div class="absolute top-2 right-2 bg-accentRed px-1.5 py-0.5 rounded text-[7px] font-bold text-white flex items-center gap-1">
                                        <span class="w-1 h-1 bg-white rounded-full"></span>LIVE
                                    </div>
                                    <div class="absolute bottom-2 left-2 bg-black/70 px-1.5 py-0.5 rounded-md flex items-center gap-1 text-[8px] font-bold text-white">
                                        <i class="fa-solid fa-location-dot text-[7px]"></i> 0.8 km
                                    </div>
                                </div>
                                <p class="text-[9px] font-bold text-gray-400 tracking-wider uppercase mb-0.5">SOHO</p>
                                <p class="text-[12px] font-extrabold text-brandDark leading-snug">Secret Vintage Archive Sale</p>
                            </div>

                            <!-- Nearby Card 2 -->
                            <div class="w-[120px] shrink-0">
                                <div class="w-full aspect-[4/5] rounded-2xl border-2 border-premiumGold bg-gray-200 mb-2 relative overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=300&q=80" class="w-full h-full object-cover" alt="Food">
                                    <div class="absolute top-2 left-2 bg-premiumLight border border-premiumGold px-1.5 py-0.5 rounded text-[7px] font-bold text-premiumText tracking-wider">PREM</div>
                                    <div class="absolute bottom-2 left-2 bg-black/70 px-1.5 py-0.5 rounded-md flex items-center gap-1 text-[8px] font-bold text-white">
                                        <i class="fa-solid fa-location-dot text-[7px]"></i> 1.2 km
                                    </div>
                                </div>
                                <p class="text-[9px] font-bold text-gray-400 tracking-wider uppercase mb-0.5">EAST END</p>
                                <p class="text-[12px] font-extrabold text-brandDark leading-snug">Midnight Ramen Omakase</p>
                            </div>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="flex items-center gap-3 px-5 mt-6 mb-5">
                        <div class="flex-1 h-px bg-gray-100"></div>
                        <span class="text-[8px] font-extrabold text-gray-300 tracking-[1.5px]">PICKED FOR YOU</span>
                        <div class="flex-1 h-px bg-gray-100"></div>
                    </div>

                    <!-- For You Section -->
                    <div class="px-5">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-[18px] font-extrabold tracking-tight text-brandDark">For You</h3>
                                <p class="text-[11px] font-medium text-gray-400 mt-0.5">Hosts you follow · your area</p>
                            </div>
                            <div class="bg-brandLight px-2.5 py-1.5 rounded-lg flex items-center gap-1">
                                <i class="fa-solid fa-sliders text-[10px] text-brand"></i>
                                <span class="text-[10px] font-bold text-brand">Filter</span>
                            </div>
                        </div>

                        <div class="w-full mb-6">
                            <div class="w-full aspect-[4/5] rounded-[20px] bg-gray-100 relative overflow-hidden shadow-sm">
                                <img src="https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=600&q=80" class="w-full h-full object-cover" alt="Main Event">

                                <div class="absolute top-3 left-3 bg-black/80 px-2 py-1 rounded-md text-[8px] font-extrabold text-white tracking-widest">EVENT</div>
                                <div class="absolute top-3 right-3 w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-lg">
                                    <i class="fa-regular fa-heart text-sm text-brandDark"></i>
                                </div>
                                <div class="absolute bottom-3 left-3 bg-white/20 backdrop-blur-md border border-white/30 px-2.5 py-1 rounded-lg text-[11px] font-black text-white">01</div>
                            </div>

                            <div class="mt-3">
                                <div class="flex justify-between items-start gap-2">
                                    <h4 class="text-[15px] font-extrabold text-brandDark leading-snug truncate">Underground Techno Warehouse</h4>
                                    <div class="bg-brandLight px-2 py-1 rounded-md flex items-center gap-1 shrink-0">
                                        <i class="fa-solid fa-location-dot text-[8px] text-brand"></i>
                                        <span class="text-[9px] font-bold text-brand">2.4 km</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 mt-1.5">
                                    <i class="fa-solid fa-circle-user text-gray-300 text-[12px]"></i>
                                    <p class="text-[10px] font-medium text-gray-500">Kollektiv · @kollektiv.ldn</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <!-- Decorative blur behind phone -->
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[80%] h-[80%] bg-brand/20 blur-[100px] -z-10 rounded-full"></div>
        </div>
    </div>
</header>

<!-- FEATURES SECTION -->
<section class="bg-gray-50/50 py-24">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center max-w-2xl mx-auto mb-20 space-y-4">
            <h2 class="text-sm font-extrabold tracking-widest text-brand uppercase bg-brandLight inline-block px-4 py-1.5 rounded-full">App Features</h2>
            <p class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-brandDark">Built for the culture. <br>Designed for discovery.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="p-8 rounded-[32px] bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 hover:shadow-[0_8px_40px_rgb(68,0,117,0.08)] hover:-translate-y-1 transition-all duration-300">
                <div class="w-14 h-14 rounded-2xl bg-brandLight flex items-center justify-center text-brand text-2xl mb-6">
                    <i class="fa-solid fa-location-crosshairs"></i>
                </div>
                <h3 class="text-xl font-extrabold text-brandDark mb-3">Hyper-Local Radar</h3>
                <p class="text-gray-500 font-medium leading-relaxed">
                    Instantly see what's popping off within walking distance. Our location-based engine ensures you never miss a trending spot in your immediate vicinity.
                </p>
            </div>

            <!-- Feature 2 -->
            <div class="p-8 rounded-[32px] bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 hover:shadow-[0_8px_40px_rgb(68,0,117,0.08)] hover:-translate-y-1 transition-all duration-300">
                <div class="w-14 h-14 rounded-2xl bg-brandLight flex items-center justify-center text-brand text-2xl mb-6">
                    <i class="fa-solid fa-bell-concierge"></i>
                </div>
                <h3 class="text-xl font-extrabold text-brandDark mb-3">Personalized Feed</h3>
                <p class="text-gray-500 font-medium leading-relaxed">
                    Follow your favorite brands, chefs, and artists. We algorithmically build a personalized feed so you only see the events that actually match your vibe.
                </p>
            </div>

            <!-- Feature 3 -->
            <div class="p-8 rounded-[32px] bg-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 hover:shadow-[0_8px_40px_rgb(68,0,117,0.08)] hover:-translate-y-1 transition-all duration-300">
                <div class="w-14 h-14 rounded-2xl bg-brandLight flex items-center justify-center text-brand text-2xl mb-6">
                    <i class="fa-solid fa-map-marked-alt"></i>
                </div>
                <h3 class="text-xl font-extrabold text-brandDark mb-3">Interactive Map</h3>
                <p class="text-gray-500 font-medium leading-relaxed">
                    Seamlessly toggle between our sleek list feed and a dynamic, clustered map to visually navigate your city and find exactly where the action is happening.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- PREMIUM SECTION -->
<section class="bg-brandDark text-white py-24 relative overflow-hidden">
    <!-- Premium background glow -->
    <div class="absolute w-[600px] h-[600px] rounded-full bg-premiumGold/10 -bottom-40 -left-40 blur-[100px] pointer-events-none"></div>
    <div class="absolute w-[400px] h-[400px] rounded-full bg-brand/30 top-0 right-0 blur-[80px] pointer-events-none"></div>

    <div class="max-w-4xl mx-auto px-6 text-center relative z-10 space-y-8">
        <span class="inline-flex items-center gap-2 bg-gradient-to-r from-premiumGold to-yellow-600 px-4 py-1.5 rounded-full text-xs font-black tracking-widest text-brandDark uppercase shadow-lg shadow-premiumGold/20">
            <i class="fa-solid fa-crown"></i> JOJO'S Premium
        </span>

        <h2 class="text-4xl sm:text-5xl font-black tracking-tight leading-tight">
            Unlock the Velvet Rope.<br>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-premiumGold to-[#FFFBE6]">Access Invite-Only Pops.</span>
        </h2>

        <p class="text-gray-400 max-w-2xl mx-auto text-lg font-medium leading-relaxed">
            Upgrade in-app to Premium and bypass the crowds. Gain exclusive access to secret locations, pre-drop shopping windows, and get on the guestlist for the city's most exclusive, unlisted pop-ups.
        </p>

        <div class="pt-4">
            <button class="bg-white text-brandDark px-8 py-3.5 rounded-xl font-bold hover:bg-gray-100 transition-colors shadow-xl shadow-white/10">
                Explore Premium Features
            </button>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="bg-white border-t border-gray-100 py-12 mt-auto">
    <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6">

        <div class="flex items-center gap-2">
            <span class="text-sm font-black tracking-wider text-brandDark">
                JOJO'S <span class="text-brand">POPS</span>
            </span>
            <span class="text-sm text-gray-400 font-medium ml-2">&copy; 2026 All rights reserved.</span>
        </div>

        <div class="flex flex-wrap gap-x-8 gap-y-4 justify-center text-sm font-semibold text-gray-500">
            <a href="/privacy" class="hover:text-brand transition-colors">Privacy Policy</a>
            <a href="/terms" class="hover:text-brand transition-colors">Terms of Service</a>
            <a href="/delete-account" class="text-accentRed hover:text-red-700 flex items-center gap-1.5 transition-colors">
                <i class="fa-regular fa-trash-can"></i> Delete Account
            </a>
        </div>
    </div>
</footer>

</body>
</html>
