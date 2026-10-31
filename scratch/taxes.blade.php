@extends('layouts.portal', ['title' => 'Taxes & Revenue'])

@section('content')
<div class="bg-[#123D2B] text-white py-16 border-b border-emerald-900 bg-cover bg-center" style="background-image: linear-gradient(to right, rgba(11, 42, 30, 0.94), rgba(18, 61, 43, 0.90)), url('{{ asset('images/background.jpg') }}');">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <span class="text-xs uppercase font-bold tracking-widest text-[#D4AF37]">Revenue Mobilization</span>
        <h1 class="text-3xl sm:text-4xl font-heading font-extrabold mt-2">Inland Revenue &amp; Domestic Taxes</h1>
        <p class="text-emerald-100/80 mt-3 max-w-2xl text-sm leading-relaxed">
            Domestic revenue administration, tax types, taxpayer registration (TIN), taxpayer rights charter, and training curricula manuals.
        </p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-16">

    <!-- Section A: Domestic Revenue / Inland Revenue -->
    <div id="domestic-revenue" class="scroll-mt-24 space-y-6">
        <div>
            <span class="text-xs uppercase font-bold tracking-widest text-[#D4AF37]">Inland Revenue</span>
            <h2 class="font-heading font-extrabold text-3xl text-slate-900 mt-1">Domestic Revenue / Inland Revenue Department</h2>
            <p class="text-sm text-slate-500 mt-1">Official Inland Revenue resources, tax compliance frameworks, and Revenue Act regulations.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-7 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center mb-4">
                        <i data-lucide="building-2" class="w-6 h-6"></i>
                    </div>
                    <span class="badge-green mb-2">Department Structure</span>
                    <h3 class="font-heading font-bold text-lg text-slate-900 mb-2">Inland Revenue Department</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Entrusted with domestic revenue assessment, audit enforcement, voluntary tax compliance programs, and regional revenue office supervision.
                    </p>
                </div>
                <a href="{{ route('portal.ministry') }}#dept-inland-revenue" class="text-xs font-bold text-[#123D2B] hover:underline flex items-center gap-1 mt-4">
                    <span>Department Profile</span> <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-7 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-800 flex items-center justify-center mb-4">
                        <i data-lucide="shield-check" class="w-6 h-6"></i>
                    </div>
                    <span class="badge-gold mb-2">Statutory Compliance</span>
                    <h3 class="font-heading font-bold text-lg text-slate-900 mb-2">Revenue Act &amp; Tax Clearance</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Statutory guidelines under the Somaliland Revenue Act for obtaining official Tax Clearance Certificates (TCC) for public tenders and imports.
                    </p>
                </div>
                <a href="{{ route('portal.acts') }}#regulations" class="text-xs font-bold text-[#123D2B] hover:underline flex items-center gap-1 mt-4">
                    <span>Read Revenue Act</span> <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-7 shadow-sm flex flex-col justify-between" id="calculator">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-800 flex items-center justify-center mb-4">
                        <i data-lucide="calculator" class="w-6 h-6"></i>
                    </div>
                    <span class="badge-green mb-2">Online Calculator</span>
                    <h3 class="font-heading font-bold text-lg text-slate-900 mb-2">Tax Calculator</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Quickly compute payable obligations for Rental Income Tax, PAYE Employment Income, and Goods &amp; Services Tax (GST).
                    </p>
                </div>
                <a href="#rental-tax-calc" class="text-xs font-bold text-[#123D2B] hover:underline flex items-center gap-1 mt-4">
                    <span>Calculate Now</span> <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Section B: Tax Types (5 Categories with Rental Detailed) -->
    <div id="tax-types" class="scroll-mt-24 space-y-8">
        <div>
            <span class="text-xs uppercase font-bold tracking-widest text-[#D4AF37]">Tax Classifications</span>
            <h2 class="font-heading font-extrabold text-3xl text-slate-900 mt-1">Tax Types (5 Core Categories)</h2>
            <p class="text-sm text-slate-500 mt-1">Classification of domestic revenue regimes administered across Somaliland.</p>
        </div>

        <!-- 1. Rental Income Tax Detailed Breakdown -->
        <div id="rental-tax-calc" class="bg-white rounded-2xl border border-slate-200 p-8 sm:p-10 shadow-sm space-y-6">
            <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-sm">1</span>
                <div>
                    <h3 class="font-heading font-bold text-2xl text-slate-900">Rental Income Tax</h3>
                    <p class="text-xs text-slate-500">Statutory tax imposed on rental proceeds received from leased residential and commercial real estate.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 text-sm">
                <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
                    <h4 class="font-bold text-slate-900 text-sm">What is Rental Income Tax?</h4>
                    <p class="text-xs text-slate-600 leading-relaxed">A direct tax levied on gross rental earnings derived by landlords, property owners, and subletters within the Republic of Somaliland.</p>
                </div>
                <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
                    <h4 class="font-bold text-slate-900 text-sm">Who is Obliged to Pay?</h4>
                    <p class="text-xs text-slate-600 leading-relaxed">All individuals, companies, trusts, and resident or non-resident entities that receive monthly or annual rental income from buildings or land.</p>
                </div>
                <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
                    <h4 class="font-bold text-slate-900 text-sm">Tax Rate</h4>
                    <p class="text-xs text-slate-600 leading-relaxed">Standard statutory rate of <strong class="text-emerald-800 font-bold">10%</strong> applied on assessed gross rental proceeds.</p>
                </div>
                <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
                    <h4 class="font-bold text-slate-900 text-sm">Calculation Example</h4>
                    <p class="text-xs text-slate-600 leading-relaxed">Monthly rent of $1,000 &times; 10% rate = <strong class="text-emerald-800 font-bold">$100 payable tax</strong> per month ($1,200 annually).</p>
                </div>
                <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
                    <h4 class="font-bold text-slate-900 text-sm">Late Filing Penalties</h4>
                    <p class="text-xs text-slate-600 leading-relaxed">Statutory penalty of 2% per month on unpaid balances for declarations submitted after the statutory filing deadline.</p>
                </div>
                <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
                    <h4 class="font-bold text-slate-900 text-sm">Late Payment Fees</h4>
                    <p class="text-xs text-slate-600 leading-relaxed">Interest surcharge plus administration fee applied until full settlement is cleared with the Inland Revenue Department.</p>
                </div>
            </div>
        </div>

        <!-- 2–5 Other Categories -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-2">
                <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-800 font-bold flex items-center justify-center text-sm mb-3">2</div>
                <h4 class="font-heading font-bold text-base text-slate-900">Goods &amp; Services Tax (GST)</h4>
                <p class="text-xs text-slate-500 leading-relaxed">Indirect consumption tax applied at standard statutory rates on taxable goods supplied or imported into Somaliland and specified domestic services.</p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-sm mb-3">3</div>
                <h4 class="font-heading font-bold text-base text-slate-900">Payroll Tax (PAYE)</h4>
                <p class="text-xs text-slate-500 leading-relaxed">Pay-As-You-Earn employment income tax deducted at source by registered public and private sector employers from employee salaries.</p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-2">
                <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-800 font-bold flex items-center justify-center text-sm mb-3">4</div>
                <h4 class="font-heading font-bold text-base text-slate-900">Non-Resident Withholding Tax</h4>
                <p class="text-xs text-slate-500 leading-relaxed">Withholding deductions applied on consultancy, technical services, interest, and dividends remitted to foreign entities.</p>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-2">
                <div class="w-8 h-8 rounded-lg bg-purple-100 text-purple-800 font-bold flex items-center justify-center text-sm mb-3">5</div>
                <h4 class="font-heading font-bold text-base text-slate-900">Road Tax &amp; Transport Fees</h4>
                <p class="text-xs text-slate-500 leading-relaxed">Annual vehicle license tariffs, road maintenance fees, and commercial transport operating permits managed across regional centers.</p>
            </div>
        </div>
    </div>

    <!-- Section C: Taxpayer Registration (TIN) -->
    <div id="taxpayer-registration" class="scroll-mt-24 bg-white rounded-2xl border border-slate-200 p-8 sm:p-10 shadow-sm space-y-8">
        <div>
            <span class="text-xs uppercase font-bold tracking-widest text-[#D4AF37]">Taxpayer Identification</span>
            <h2 class="font-heading font-extrabold text-3xl text-slate-900 mt-1">Taxpayer Registration in Somaliland</h2>
            <p class="text-sm text-slate-500 mt-1">Mission of the Inland Revenue Department: Enhancing voluntary compliance through simple digital taxpayer registration.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
                <h4 class="font-bold text-slate-900 text-sm">What is a TIN?</h4>
                <p class="text-xs text-slate-600 leading-relaxed">A unique 9-digit Taxpayer Identification Number issued to track statutory tax obligations and entitlements.</p>
            </div>
            <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
                <h4 class="font-bold text-slate-900 text-sm">Who Needs to Register?</h4>
                <p class="text-xs text-slate-600 leading-relaxed">All sole proprietors, corporate entities, NGOs, civil servants, private employees, and property owners.</p>
            </div>
            <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
                <h4 class="font-bold text-slate-900 text-sm">Why Should I Register?</h4>
                <p class="text-xs text-slate-600 leading-relaxed">Mandatory for opening commercial bank accounts, bidding for government contracts, clearing imports, and obtaining trade licenses.</p>
            </div>
            <div class="p-5 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
                <h4 class="font-bold text-slate-900 text-sm">When Do I Need to Register?</h4>
                <p class="text-xs text-slate-600 leading-relaxed">Within 30 days of commencing business activities, employment, or property acquisition in the Republic of Somaliland.</p>
            </div>
        </div>

        <!-- Types of Taxes to Register For -->
        <div class="pt-6 border-t border-slate-100 space-y-4">
            <h4 class="font-heading font-bold text-base text-slate-900">Types of Taxes to Register For:</h4>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3 text-center text-xs">
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 font-semibold text-emerald-900">
                    <span class="block font-bold text-sm text-emerald-800">EIT</span>
                    Employment Income
                </div>
                <div class="p-3 rounded-xl bg-blue-50 border border-blue-200 font-semibold text-blue-900">
                    <span class="block font-bold text-sm text-blue-800">CIT</span>
                    Corporate Income
                </div>
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 font-semibold text-amber-900">
                    <span class="block font-bold text-sm text-amber-800">IIT</span>
                    Individual Income
                </div>
                <div class="p-3 rounded-xl bg-purple-50 border border-purple-200 font-semibold text-purple-900">
                    <span class="block font-bold text-sm text-purple-800">RIT</span>
                    Rental Income Tax
                </div>
                <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 font-semibold text-rose-900">
                    <span class="block font-bold text-sm text-rose-800">WHT</span>
                    Withholding Tax
                </div>
                <div class="p-3 rounded-xl bg-teal-50 border border-teal-200 font-semibold text-teal-900">
                    <span class="block font-bold text-sm text-teal-800">GST</span>
                    Goods &amp; Services
                </div>
                <div class="p-3 rounded-xl bg-indigo-50 border border-indigo-200 font-semibold text-indigo-900">
                    <span class="block font-bold text-sm text-indigo-800">EXT</span>
                    Excise Tax
                </div>
            </div>
        </div>
    </div>

    <!-- Section D: Taxpayer's Charter -->
    <div id="taxpayer-charter" class="scroll-mt-24 bg-gradient-to-r from-emerald-900 to-[#123D2B] rounded-2xl p-8 sm:p-10 text-white shadow-xl space-y-6">
        <div>
            <span class="badge-gold mb-2">Service Standards</span>
            <h3 class="font-heading font-black text-2xl sm:text-3xl">Taxpayer's Charter &bull; Rights, Obligations &amp; Service Standards</h3>
            <p class="text-emerald-100/90 text-sm max-w-2xl mt-1">
                The Taxpayer's Charter defines the Ministry's commitment to treat every taxpayer with impartiality, confidentiality, and professional courtesy.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm text-slate-100">
            <div class="p-5 rounded-xl bg-white/10 border border-white/10 space-y-2">
                <h4 class="font-bold text-[#D4AF37] text-base">Your Rights</h4>
                <p class="text-xs leading-relaxed text-emerald-100">Right to fair assessment, confidentiality of financial records, transparent explanations of tax law, and impartial objection appeal mechanisms.</p>
            </div>
            <div class="p-5 rounded-xl bg-white/10 border border-white/10 space-y-2">
                <h4 class="font-bold text-[#D4AF37] text-base">Your Obligations</h4>
                <p class="text-xs leading-relaxed text-emerald-100">Maintain accurate accounting books, declare true income, submit returns on time, and pay all statutory assessments promptly.</p>
            </div>
            <div class="p-5 rounded-xl bg-white/10 border border-white/10 space-y-2">
                <h4 class="font-bold text-[#D4AF37] text-base">Service Standards</h4>
                <p class="text-xs leading-relaxed text-emerald-100">TIN certificate issuance within 48 hours, responsive dispute resolution, and clear guidance across all regional revenue service desks.</p>
            </div>
        </div>
    </div>

    <!-- Section E: Tax Curricula Manuals -->
    <div id="tax-curricula" class="scroll-mt-24 space-y-6">
        <div>
            <span class="text-xs uppercase font-bold tracking-widest text-[#D4AF37]">Official Manuals</span>
            <h2 class="font-heading font-extrabold text-3xl text-slate-900 mt-1">Tax Curricula Manuals</h2>
            <p class="text-sm text-slate-500 mt-1">Downloadable training curricula and implementation manuals published for tax administrators and accounting professionals.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="badge-green mb-3">Core Curriculum</span>
                    <h4 class="font-heading font-bold text-base text-slate-900 mb-2">Tax Administration Fundamentals</h4>
                    <p class="text-xs text-slate-500 leading-relaxed mb-4">Foundational principles of tax law, assessment procedures, and taxpayer service standards.</p>
                </div>
                <a href="#" class="btn-green text-xs justify-center"><i data-lucide="download" class="w-3.5 h-3.5"></i> Download PDF</a>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="badge-gold mb-3">Direct Tax</span>
                    <h4 class="font-heading font-bold text-base text-slate-900 mb-2">Income Tax Manual</h4>
                    <p class="text-xs text-slate-500 leading-relaxed mb-4">Comprehensive guidance on Corporate Income Tax (CIT) and PAYE withholding schedules.</p>
                </div>
                <a href="#" class="btn-green text-xs justify-center"><i data-lucide="download" class="w-3.5 h-3.5"></i> Download PDF</a>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="badge-green mb-3">Indirect Tax</span>
                    <h4 class="font-heading font-bold text-base text-slate-900 mb-2">VAT Implementation Guide</h4>
                    <p class="text-xs text-slate-500 leading-relaxed mb-4">Operational handbook for Goods &amp; Services Tax (GST) invoicing, input credit, and audits.</p>
                </div>
                <a href="#" class="btn-green text-xs justify-center"><i data-lucide="download" class="w-3.5 h-3.5"></i> Download PDF</a>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="badge-gold mb-3">Border Operations</span>
                    <h4 class="font-heading font-bold text-base text-slate-900 mb-2">Customs Procedures Manual</h4>
                    <p class="text-xs text-slate-500 leading-relaxed mb-4">Standard operating procedures for cargo valuation, port clearance, and ASYCUDA declarations.</p>
                </div>
                <a href="#" class="btn-green text-xs justify-center"><i data-lucide="download" class="w-3.5 h-3.5"></i> Download PDF</a>
            </div>
        </div>
    </div>

</div>
@endsection
