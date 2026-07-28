@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-2 sm:px-3 lg:px-4')
@section('page-title', 'Registre courrier — '.($sensCode === 'depart' ? 'Départ' : 'Arrivée'))
@section('page-title-class', 'text-lg font-bold text-slate-800 dark:text-slate-100 leading-tight')
@section('page-header-class', 'bg-white dark:bg-slate-800 shadow py-0 registre-page-header-compact')

@section('btn-create')
    <div class="flex flex-wrap items-center gap-1.5">
        <a href="{{ route('courriers.index', ['sens' => $sensCode]) }}"
           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold no-underline bg-emerald-50 text-emerald-800 border border-emerald-200">
            Liste courriers
        </a>
        <a href="{{ $sensCode === 'depart' ? route('courriers.registres.print-depart', ['annee' => $annee, 'q' => request('q')]) : route('courriers.registres.print-arrivee', ['annee' => $annee, 'q' => request('q')]) }}"
           target="_blank"
           class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-slate-800 text-white text-xs font-semibold hover:bg-slate-900 shadow-sm no-underline">
            Imprimer / PDF
        </a>
    </div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/bookblock/css/bookblock.css') }}">
<link rel="stylesheet" href="{{ asset('css/ged-registre-livret.css') }}?v=13">
<script src="{{ asset('vendor/bookblock/js/modernizr.custom.js') }}"></script>
<style>
    /* Bandeau titre compact → plus de hauteur pour le livret */
    #mainContent > header.registre-page-header-compact > div {
        padding-top: 0.35rem !important;
        padding-bottom: 0.35rem !important;
        gap: 0.5rem !important;
    }
    #mainContent > main { padding-top: 0.15rem !important; padding-bottom: 0 !important; }
    #mainContent > main > div { max-width: none !important; width: 100%; }
</style>
@endpush

@section('content')
@php
    $isDepart = $sensCode === 'depart';
    $nbFeuillets = max(1, $feuillets->count());
    $libSens = $isDepart ? 'Départ' : 'Arrivée';
    $libSensUpper = $isDepart ? 'DÉPART' : 'ARRIVÉE';
    $logoPath = public_path('images/image-logo.jpg');
    $hasLogo = is_file($logoPath);
@endphp

<div class="ged-registre-livret is-fullscreen is-closed {{ $isDepart ? 'is-depart' : '' }}">
    <div class="registre-livret-shell">
        <div class="bb-custom-wrapper registre-livret-book-area">
            <div class="registre-livret-panel">
                <div id="bb-bookblock" class="bb-bookblock">

                    {{-- Page de garde : livre portrait CENTRÉ dans une zone BookBlock stable --}}
                    <div class="bb-item bb-item-cover">
                        <div class="registre-closed-book-stage">
                            <div class="registre-closed-book {{ $isDepart ? 'is-depart' : '' }}" title="Page de garde — cliquez › pour ouvrir">
                                <div class="registre-closed-spine" aria-hidden="true"></div>
                                <div class="registre-book-cover">
                                    <div class="registre-book-cover-frame">
                                        <div class="registre-book-cover-line">COURRIER</div>
                                        <div class="registre-book-cover-line">{{ $libSensUpper }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @forelse($feuillets as $index => $chunk)
                    <div class="bb-item bb-item-feuille">
                        <div class="registre-closed-book-stage is-wide">
                            <div class="registre-single-face registre-feuille-face">
                                <div class="registre-feuille-head">
                                    <div>
                                        <p class="text-[10px] uppercase tracking-[0.18em] text-slate-500 font-semibold mb-1">{{ $libelleStructureRegistre }}</p>
                                        <h3>{{ $libSensUpper }}</h3>
                                    </div>
                                    <div class="text-right text-xs text-slate-600">
                                        <div>Année {{ $annee }}</div>
                                        <div class="font-semibold">Feuillet {{ $index + 1 }} / {{ $nbFeuillets }}</div>
                                    </div>
                                </div>
                                <div class="registre-feuille-face-body">
                                    @include('courriers.registres.partials.table', [
                                        'courriers' => $chunk,
                                        'sensCode' => $sensCode,
                                        'annee' => $annee,
                                        'side' => 'full',
                                    ])
                                </div>
                                <div class="registre-feuille-foot">
                                    <span>Registre {{ $libSens }} {{ $annee }}</span>
                                    <span>{{ $chunk->first()->numero_registre ?? '—' }} → {{ $chunk->last()->numero_registre ?? '—' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="bb-item bb-item-feuille">
                        <div class="registre-closed-book-stage is-wide">
                            <div class="registre-single-face registre-feuille-face">
                                <div class="registre-feuille-head">
                                    <h3>{{ $libSensUpper }}</h3>
                                    <div class="text-xs text-slate-600">Année {{ $annee }}</div>
                                </div>
                                <div class="registre-feuille-face-body flex items-center justify-center">
                                    <p class="text-sm text-slate-500 py-16 text-center">Aucune entrée pour l’année {{ $annee }}.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforelse

                    <div class="bb-item bb-item-cloture">
                        <div class="registre-closed-book-stage">
                            <div class="registre-single-face registre-cloture-face">
                                <p class="registre-cover-kicker">Clôture du registre</p>
                                <h2>
                                    Nous, {{ $libelleStructureRegistre }},
                                    arrêtons et clôturons le présent registre de courrier
                                    <strong>{{ $libSens }}</strong> pour l’année <strong>{{ $annee }}</strong>,
                                    comprenant <strong>{{ $courriers->count() }}</strong> entrée(s)
                                    @if($courriers->isNotEmpty())
                                    inscrite(s) du numéro <strong>{{ $courriers->first()->numero_registre }}</strong>
                                    au numéro <strong>{{ $courriers->last()->numero_registre }}</strong> inclus
                                    @endif
                                    et répartie(s) sur <strong>{{ $nbFeuillets }}</strong> feuillet(s).
                                </h2>
                                <div class="registre-cover-fait">
                                    <div>Fait à Brazzaville, le <strong>{{ now()->format('d/m/Y') }}</strong></div>
                                    @if($hasLogo)
                                        <img src="{{ asset('images/image-logo.jpg') }}" alt="ACSI" class="registre-cover-seal">
                                    @endif
                                    <div class="mt-2 text-sm font-semibold">{{ $libelleStructureRegistre }}</div>
                                </div>
                                <p class="registre-cover-label">Fin du livret</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="registre-livret-footer">
            <nav id="btn_footer" aria-label="Navigation du livret">
                <a id="bb-nav-first" href="#" title="Première page">«</a>
                <a id="bb-nav-prev" href="#" title="Page précédente">‹</a>
                <a id="bb-nav-next" href="#" title="Page suivante">›</a>
                <a id="bb-nav-last" href="#" title="Dernière page">»</a>
            </nav>
            <div class="registre-page-indicator">
                <span id="bb-page-label">Page 1</span>
                <span class="mx-1">·</span>
                <span>Flèches ← → pour feuilleter</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="{{ asset('vendor/bookblock/js/jquerypp.custom.js') }}"></script>
<script src="{{ asset('vendor/bookblock/js/jquery.bookblock.js') }}"></script>
<script>
(function ($) {
    var $book = $('#bb-bookblock');
    var $livret = $('.ged-registre-livret');
    if (!$book.length || typeof $.fn.bookblock !== 'function') {
        console.error('BookBlock non initialisé (jQuery plugin manquant).');
        return;
    }

    var total = $book.children('.bb-item').length;
    var $label = $('#bb-page-label');

    function updateLabel(pageIndex) {
        $label.text('Page ' + pageIndex + ' / ' + total);
    }

    function syncBookblockWidth() {
        var inst = $book.data('bookblock');
        if (inst) {
            try { inst.elWidth = $book.width(); } catch (e) {}
        }
    }

    function fitLayout() {
        var root = $livret.get(0);
        var bookEl = $book.get(0);
        if (!root || !bookEl) return;

        var top = Math.ceil(root.getBoundingClientRect().top);
        var siteFooter = document.querySelector('#main-wrapper .footer, .footer');
        var siteFooterH = siteFooter ? Math.max(52, Math.ceil(siteFooter.offsetHeight)) : 60;

        // Barre de feuilletage (compacte)
        var NAV_RESERVE = 78;
        var SAFETY = 12;

        // Espace utile au-dessus du footer du site
        var usable = Math.floor(window.innerHeight - top - siteFooterH - SAFETY);
        usable = Math.max(340, usable);

        var bookH = usable - NAV_RESERVE;
        // Un peu plus de hauteur pour le registre (tout en gardant les boutons visibles)
        var maxBook = Math.floor((window.innerHeight - top) * 0.76);
        bookH = Math.max(280, Math.min(bookH, maxBook));

        var shellH = bookH + NAV_RESERVE;
        root.style.setProperty('--ged-shell-height', shellH + 'px');
        bookEl.style.height = bookH + 'px';
        document.documentElement.style.setProperty('--ged-page-height', bookH + 'px');
        syncBookblockWidth();
    }

    fitLayout();
    $(window).on('load resize', fitLayout);
    setTimeout(fitLayout, 50);
    setTimeout(fitLayout, 200);

    $book.bookblock({
        speed: 800,
        shadowSides: 0.8,
        shadowFlip: 0.7,
        onEndFlip: function (old, page) {
            updateLabel(page + 1);
        }
    });

    // Largeur initiale après init BookBlock
    syncBookblockWidth();
    updateLabel(1);

    $('#bb-nav-next').on('click touchstart', function () { $book.bookblock('next'); return false; });
    $('#bb-nav-prev').on('click touchstart', function () { $book.bookblock('prev'); return false; });
    $('#bb-nav-first').on('click touchstart', function () {
        $book.bookblock('first');
        updateLabel(1);
        return false;
    });
    $('#bb-nav-last').on('click touchstart', function () {
        $book.bookblock('last');
        updateLabel(total);
        return false;
    });

    $book.on('click', '.registre-closed-book', function () {
        $book.bookblock('next');
        return false;
    });

    $book.children().on({
        swipeleft: function () { $book.bookblock('next'); return false; },
        swiperight: function () { $book.bookblock('prev'); return false; }
    });

    $(document).on('keydown', function (e) {
        if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT')) {
            return;
        }
        if (e.keyCode === 37) { $book.bookblock('prev'); }
        if (e.keyCode === 39) { $book.bookblock('next'); }
    });
})(jQuery);
</script>
@endpush
