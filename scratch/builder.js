const fs = require('fs');

// Read the complete base template from write_home.js
const src = fs.readFileSync('C:/Users/User/Documents/slmof/scratch/write_home.js', 'utf8');

// Extract the string content
const startQuote = src.indexOf('`');
const endQuote = src.lastIndexOf('`');
let template = src.substring(startQuote + 1, endQuote);

console.log('Original template length:', template.length);

// 1. HERO SLIDES BACKGROUND IMAGES:
// Slide 0: images/background.jpg
// Slide 1: images/background2.jpg
// Slide 2: images/background3.jpg
template = template.replace(
    `<div x-show="slide === 1" x-transition:enter="transition-opacity duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute inset-0" x-cloak>
        <div class="absolute inset-0 bg-gradient-to-br from-[#0B2A1E] via-[#0d2a52] to-[#176838]/50"></div>
        <div class="absolute inset-0 opacity-8" style="background-image:url('{{ asset('images/background.jpg') }}');background-size:cover;background-position:center"></div>
    </div>`,
    `<div x-show="slide === 1" x-transition:enter="transition-opacity duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute inset-0" x-cloak>
        <div class="absolute inset-0 bg-gradient-to-br from-[#0B2A1E]/80 via-[#0d2a52]/80 to-[#176838]/60"></div>
        <div class="absolute inset-0 opacity-25 mix-blend-overlay" style="background-image:url('{{ asset('images/background2.jpg') }}');background-size:cover;background-position:center"></div>
    </div>`
);

template = template.replace(
    `<div x-show="slide === 2" x-transition:enter="transition-opacity duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute inset-0" x-cloak>
        <div class="absolute inset-0 bg-gradient-to-br from-[#0B2A1E] via-[#2d1a0e] to-[#D4AF37]/15"></div>
        <div class="absolute inset-0 opacity-8" style="background-image:url('{{ asset('images/background.jpg') }}');background-size:cover;background-position:center"></div>
    </div>`,
    `<div x-show="slide === 2" x-transition:enter="transition-opacity duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute inset-0" x-cloak>
        <div class="absolute inset-0 bg-gradient-to-br from-[#0B2A1E]/80 via-[#2d1a0e]/80 to-[#D4AF37]/20"></div>
        <div class="absolute inset-0 opacity-25 mix-blend-overlay" style="background-image:url('{{ asset('images/background3.jpg') }}');background-size:cover;background-position:center"></div>
    </div>`
);

console.log('Hero backgrounds replaced:', template.includes('background2.jpg') && template.includes('background3.jpg'));

// 2. QUICK ACCESS CARDS:
// Section Header: SERVICES label with green line, Quick Access title
// Active Card: Pale mint tile (#F0FAF4), green border (#176838), green icon (file-text) inside #DCF2E4 tile, arrow-right green
// Idle Card 2: White card, trending-up icon in dark gray/slate, gray arrow
// Idle Card 3: White card, book-open icon in dark gray/slate, gray arrow
// Idle Card 4: White card, briefcase icon in dark gray/slate, gray arrow
const qaOld = `<section class="bg-white border-b border-slate-100 py-10" id="quick-access">`;
const ministerStart = `{{-- =========================================================\n     MESSAGE FROM THE MINISTER`;

const qaStartIdx = template.indexOf(qaOld);
const ministerStartIdx = template.indexOf(ministerStart);

if (qaStartIdx !== -1 && ministerStartIdx !== -1) {
    const qaNew = `<section class="bg-white py-12" id="quick-access">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section Header matching screenshot --}}
        <div class="mb-8">
            <div class="flex items-center gap-2.5 mb-2">
                <span class="w-7 h-[3px] bg-[#176838] rounded-full inline-block"></span>
                <span class="text-xs font-extrabold uppercase tracking-widest text-[#176838]">SERVICES</span>
            </div>
            <h2 class="font-heading font-black text-3xl sm:text-4xl text-slate-900">Quick Access</h2>
        </div>

        {{-- 4 Quick Access Cards matching screenshot --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

            {{-- 1. Budget Documents (Active Card: Pale mint tile with green icon & border) --}}
            <a href="{{ route('portal.budget') }}" id="qa-budget" class="relative group bg-[#F0FAF4] border-2 border-[#176838] rounded-2xl p-6 transition-all duration-200 hover:shadow-lg flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="w-12 h-12 rounded-xl bg-[#DCF2E4] flex items-center justify-center text-[#176838]">
                            <i data-lucide="file-text" class="w-6 h-6 stroke-[2]"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-[#176838] transition-transform group-hover:translate-x-1"></i>
                    </div>
                    <h3 class="font-heading font-extrabold text-slate-900 text-lg mb-2">Budget Documents</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">Access national budget and financial statements</p>
                </div>
            </a>

            {{-- 2. Tax Services (Idle Card: Trending-up arrow, dark gray icon) --}}
            <a href="{{ route('portal.taxes') }}" id="qa-taxes" class="relative group bg-white border border-slate-200 rounded-2xl p-6 transition-all duration-200 hover:border-slate-300 hover:shadow-md flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center text-slate-600 group-hover:text-slate-900 transition-colors">
                            <i data-lucide="trending-up" class="w-6 h-6 stroke-[1.8]"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-slate-300 group-hover:text-slate-600 transition-all group-hover:translate-x-1"></i>
                    </div>
                    <h3 class="font-heading font-extrabold text-slate-900 text-lg mb-2">Tax Services</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">Tax registration, calculator and payment services</p>
                </div>
            </a>

            {{-- 3. Acts & Regulations (Idle Card: Open book, dark gray icon) --}}
            <a href="{{ route('portal.acts') }}" id="qa-acts" class="relative group bg-white border border-slate-200 rounded-2xl p-6 transition-all duration-200 hover:border-slate-300 hover:shadow-md flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center text-slate-600 group-hover:text-slate-900 transition-colors">
                            <i data-lucide="book-open" class="w-6 h-6 stroke-[1.8]"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-slate-300 group-hover:text-slate-600 transition-all group-hover:translate-x-1"></i>
                    </div>
                    <h3 class="font-heading font-extrabold text-slate-900 text-lg mb-2">Acts &amp; Regulations</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">Official laws, regulations and policies</p>
                </div>
            </a>

            {{-- 4. Career Opportunities (Idle Card: Briefcase, dark gray icon) --}}
            <a href="{{ route('portal.careers') }}" id="qa-careers" class="relative group bg-white border border-slate-200 rounded-2xl p-6 transition-all duration-200 hover:border-slate-300 hover:shadow-md flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center text-slate-600 group-hover:text-slate-900 transition-colors">
                            <i data-lucide="briefcase" class="w-6 h-6 stroke-[1.8]"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-slate-300 group-hover:text-slate-600 transition-all group-hover:translate-x-1"></i>
                    </div>
                    <h3 class="font-heading font-extrabold text-slate-900 text-lg mb-2">Career Opportunities</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">Current vacancies and announcements</p>
                </div>
            </a>

        </div>
    </div>
</section>

`;
    template = template.substring(0, qaStartIdx) + qaNew + template.substring(ministerStartIdx);
    console.log('Quick Access successfully replaced!');
}

// 3. COMBINED LATEST NEWS AND LATEST ANNOUNCEMENTS:
// Exactly as in Screenshot 2:
// Left: Latest News list of articles with thumbnail, date, Hargeisa, title, excerpt, "Read Article ->"
// Right: Latest Announcements card with green solid header (bell icon + "Latest Announcements")
// and the 3 download rows with Download link and green download icon
const newsOld = `{{-- =========================================================\n     LATEST NEWS`;
const keyServicesOld = `{{-- =========================================================\n     OUR KEY SERVICES — 6 Categories`;

const newsStartIdx = template.indexOf(newsOld);
const keyServicesStartIdx = template.indexOf(keyServicesOld);

if (newsStartIdx !== -1 && keyServicesStartIdx !== -1) {
    const combinedSection = `{{-- =========================================================
     UPDATES — LATEST NEWS & ANNOUNCEMENTS (Side-by-Side as in Screenshot)
     ========================================================= --}}
<section class="py-16 bg-white border-b border-slate-200" id="latest-news">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section Header matching screenshot 2 --}}
        <div class="flex items-center justify-between mb-8">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="w-7 h-[3px] bg-[#176838] rounded-full inline-block"></span>
                    <span class="text-xs font-extrabold uppercase tracking-widest text-[#176838]">UPDATES</span>
                </div>
                <h2 class="font-heading font-black text-3xl sm:text-4xl text-slate-900">Latest News</h2>
            </div>
            <a href="{{ route('portal.news') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-300 text-xs font-bold text-slate-800 hover:bg-slate-50 transition">
                <span>View All</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            {{-- Left column: Latest News Articles Stack (7 cols) matching Screenshot 2 --}}
            <div class="lg:col-span-7 space-y-5">
                @if($news->count())
                    @foreach($news->take(2) as $article)
                    <article class="bg-white rounded-2xl border-2 border-emerald-500/70 p-4 hover:shadow-lg transition-all duration-200 flex flex-col sm:flex-row gap-5 items-stretch group">
                        <div class="sm:w-48 sm:h-36 shrink-0 rounded-xl overflow-hidden bg-slate-100">
                            @if($article->image_path && file_exists(public_path($article->image_path)))
                                <img src="{{ asset($article->image_path) }}" alt="{{ $article->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-[#176838] to-[#0B2A1E] flex items-center justify-center text-white/30">
                                    <i data-lucide="newspaper" class="w-10 h-10"></i>
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-col justify-between flex-1 py-1">
                            <div>
                                <div class="flex items-center gap-3 text-[11px] text-slate-500 mb-2">
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                                        {{ $article->published_date ? $article->published_date->format('M d, Y') : 'Oct 21, 2026' }}
                                    </span>
                                    <span>&bull;</span>
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400"></i>
                                        Hargeisa
                                    </span>
                                </div>
                                <h3 class="font-heading font-bold text-base text-[#176838] group-hover:text-[#0B2A1E] transition-colors leading-snug mb-2">
                                    <a href="{{ route('portal.news') }}">{{ $article->title }}</a>
                                </h3>
                                @if($article->excerpt)
                                <p class="text-slate-600 text-xs leading-relaxed line-clamp-2">{{ Str::limit($article->excerpt, 140) }}</p>
                                @endif
                            </div>
                            <div class="pt-3">
                                <a href="{{ route('portal.news') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#176838] hover:text-[#0B2A1E] transition">
                                    <span>Read Article</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                    @endforeach
                @else
                    <article class="bg-white rounded-2xl border-2 border-emerald-500/70 p-4 hover:shadow-lg transition-all duration-200 flex flex-col sm:flex-row gap-5 items-stretch group">
                        <div class="sm:w-48 sm:h-36 shrink-0 rounded-xl overflow-hidden bg-slate-100">
                            <img src="{{ asset('images/background.jpg') }}" alt="Director General Training" class="w-full h-full object-cover">
                        </div>
                        <div class="flex flex-col justify-between flex-1 py-1">
                            <div>
                                <div class="flex items-center gap-3 text-[11px] text-slate-500 mb-2">
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i> Oct 21, 2026
                                    </span>
                                    <span>&bull;</span>
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400"></i> Hargeisa
                                    </span>
                                </div>
                                <h3 class="font-heading font-bold text-base text-[#176838] leading-snug mb-2">
                                    Director General Concludes Three-Day Training for the Ministry's Reform Coordination Committee
                                </h3>
                                <p class="text-slate-600 text-xs leading-relaxed">
                                    Hargeisa, 22 October 2026 (MoF&amp;ED) — The Director General of the Ministry of Finance and Economic Development, Mr. Mohamed Hassan Suleiman, today concluded a three-day...
                                </p>
                            </div>
                            <div class="pt-3">
                                <a href="{{ route('portal.news') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#176838] hover:text-[#0B2A1E] transition">
                                    <span>Read Article</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </div>
                        </div>
                    </article>

                    <article class="bg-white rounded-2xl border-2 border-emerald-500/70 p-4 hover:shadow-lg transition-all duration-200 flex flex-col sm:flex-row gap-5 items-stretch group">
                        <div class="sm:w-48 sm:h-36 shrink-0 rounded-xl overflow-hidden bg-slate-100">
                            <img src="{{ asset('images/background2.jpg') }}" alt="World Bank Review" class="w-full h-full object-cover">
                        </div>
                        <div class="flex flex-col justify-between flex-1 py-1">
                            <div>
                                <div class="flex items-center gap-3 text-[11px] text-slate-500 mb-2">
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i> Oct 12, 2026
                                    </span>
                                    <span>&bull;</span>
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400"></i> Hargeisa
                                    </span>
                                </div>
                                <h3 class="font-heading font-bold text-base text-[#176838] leading-snug mb-2">
                                    Ministry of Finance and World Bank Review Progress of Development Projects
                                </h3>
                                <p class="text-slate-600 text-xs leading-relaxed">
                                    Hargeisa, 13 October 2026 (MoF&amp;ED) — The Director General of the Somaliland Ministry of Finance and Economic Development Mr. Mohamed Hassan Suleiman, chaired the quarterly...
                                </p>
                            </div>
                            <div class="pt-3">
                                <a href="{{ route('portal.news') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#176838] hover:text-[#0B2A1E] transition">
                                    <span>Read Article</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                @endif
            </div>

            {{-- Right column: Latest Announcements Card matching Screenshot 2 (5 cols) --}}
            <div class="lg:col-span-5">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-[#176838] text-white px-6 py-4 flex items-center gap-2.5">
                        <i data-lucide="bell" class="w-5 h-5 text-white stroke-[2.2]"></i>
                        <h3 class="font-heading font-extrabold text-lg text-white">Latest Announcements</h3>
                    </div>

                    <div class="divide-y divide-slate-100">
                        {{-- Item 1 --}}
                        <div class="p-6 hover:bg-slate-50/60 transition-colors">
                            <h4 class="font-heading font-bold text-xs uppercase tracking-wide text-slate-800 hover:text-[#176838] leading-snug mb-4">
                                <a href="{{ route('portal.careers') }}">REQUEST FOR EXPERESSION OF INTEREST FOR CEPCU SOCIAL DEVELOPMENT SPECIALIST</a>
                            </h4>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Jul 23, 2026</span>
                                <a href="{{ route('portal.careers') }}" class="inline-flex items-center gap-1.5 font-bold text-[#176838] hover:text-emerald-800 transition">
                                    <i data-lucide="download" class="w-4 h-4 stroke-[2]"></i>
                                    <span>Download</span>
                                </a>
                            </div>
                        </div>

                        {{-- Item 2 --}}
                        <div class="p-6 hover:bg-slate-50/60 transition-colors">
                            <h4 class="font-heading font-bold text-xs uppercase tracking-wide text-slate-800 hover:text-[#176838] leading-snug mb-4">
                                <a href="{{ route('portal.careers') }}">ToR CEPCU Social Development Specialist</a>
                            </h4>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Jul 23, 2026</span>
                                <a href="{{ route('portal.careers') }}" class="inline-flex items-center gap-1.5 font-bold text-[#176838] hover:text-emerald-800 transition">
                                    <i data-lucide="download" class="w-4 h-4 stroke-[2]"></i>
                                    <span>Download</span>
                                </a>
                            </div>
                        </div>

                        {{-- Item 3 --}}
                        <div class="p-6 hover:bg-slate-50/60 transition-colors">
                            <h4 class="font-heading font-bold text-xs uppercase tracking-wide text-slate-800 hover:text-[#176838] leading-snug mb-4">
                                <a href="{{ route('portal.careers') }}">REQUEST FOR EXPERESSION OF INTEREST FOR CEPCU PROCUREMENT SPECIALIST</a>
                            </h4>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Jul 23, 2026</span>
                                <a href="{{ route('portal.careers') }}" class="inline-flex items-center gap-1.5 font-bold text-[#176838] hover:text-emerald-800 transition">
                                    <i data-lucide="download" class="w-4 h-4 stroke-[2]"></i>
                                    <span>Download</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

`;
    template = template.substring(0, newsStartIdx) + combinedSection + template.substring(keyServicesStartIdx);
    console.log('News & Announcements replaced successfully!');
}

// 4. REMOVE SFGMIS CTA BANNER:
// User requested: "remove this the home page the last screenshor i shared that says Authorized Personnel: Access SFGMIS System..."
const ctaOld = `{{-- =========================================================\n     CTA — SFGMIS Access`;
const endSecOld = `@endsection`;

const ctaStartIdx = template.indexOf(ctaOld);
const endSecStartIdx = template.indexOf(endSecOld);

if (ctaStartIdx !== -1 && endSecStartIdx !== -1) {
    template = template.substring(0, ctaStartIdx) + '\n' + template.substring(endSecStartIdx);
    console.log('SFGMIS CTA removed successfully!');
}

// Write to both Laravel projects
const target1 = 'C:/Users/User/Documents/slmof-laravel/resources/views/portal/home.blade.php';
fs.writeFileSync(target1, template, 'utf8');
console.log('Written to target 1:', target1);

const target2 = 'C:/xampp/htdocs/slmof-laravel/resources/views/portal/home.blade.php';
if (fs.existsSync('C:/xampp/htdocs/slmof-laravel/resources/views/portal')) {
    fs.writeFileSync(target2, template, 'utf8');
    console.log('Written to target 2:', target2);
}

console.log('ALL UPDATES COMPLETE!');