<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light dark">

        <title>@yield('title', config('app.name', 'COSUD'))</title>

        @include('partials.favicon')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

        @stack('head-scripts')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="font-sans antialiased min-h-screen bg-gray-100 dark:bg-slate-900">
        @auth
        {{-- Main wrapper : flex column pour footer toujours visible en bas --}}
        <div id="main-wrapper" style="display: flex; flex-direction: column; min-height: 100vh;">
        {{-- Preloader (comme Progcaisse) --}}
        @include('partials.preload')
        {{-- Layout COSUD : logo (nav-header) + header (hamburger) + sidebar --}}
        @include('partials.nav-header')
        @include('partials.header')
        @include('partials.sidebar')

        {{-- Zone principale : flex-1 pour pousser le footer en bas --}}
        <div id="mainContent" class="main-content pt-20 flex-1 flex flex-col transition-all duration-300">
            {{-- Page Heading --}}
            @isset($header)
                <header class="bg-white dark:bg-slate-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @elseif(View::hasSection('page-title'))
                <header class="@yield('page-header-class', 'bg-white dark:bg-slate-800 shadow')">
                    <div class="@yield('content-container-class', 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8') py-6 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 class="@yield('page-title-class', 'text-2xl font-bold text-slate-800 dark:text-slate-100')">@yield('page-title')</h1>
                            @hasSection('page-title-info')<div class="@yield('page-title-info-class', 'text-sm text-slate-500 dark:text-slate-400 mt-1')">@yield('page-title-info')</div>@endif
                        </div>
                        @yield('btn-create')
                    </div>
                </header>
            @endif

            <main class="py-6">
                <div class="@yield('content-container-class', 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8')">
                    @hasSection('page-aide')
                        @yield('page-aide')
                    @endif
                    @hasSection('content')
                        @yield('content')
                    @else
                        {{ $slot }}
                    @endif
                </div>
            </main>
        </div>

        {{-- Footer (comme Progcaisse : sibling du content, pas dedans) --}}
        @include('partials.footer')
        </div>
        {{-- fin #main-wrapper --}}
        @else
        {{-- Utilisateurs non connectés : layout Breeze simple --}}
        @include('layouts.navigation')
        @isset($header)
            <header class="bg-white dark:bg-slate-800 shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
            </header>
        @endisset
        <main class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @hasSection('content')@yield('content')@else{{ $slot }}@endif
            </div>
        </main>
        @include('partials.footer')
        @endauth

        {{-- Modals en plein écran : rendus ici (hors #mainContent) pour un position:fixed correct --}}
        @stack('body-modals')

        {{-- flashAlert : modal de confirmation global (style Progcaisse) --}}
        <div id="flashAlertModal" style="
            display:none; position:fixed; z-index:9999; inset:0;
            background:rgba(0,0,0,0.45); align-items:center; justify-content:center;
            overflow-y:auto; padding:1rem;
            animation: fadeIn 0.2s ease;">
            <div id="flashAlertPanel" style="
                background:#fff; border-radius:16px; padding:2rem 2.5rem;
                max-width:440px; width:90%; text-align:center;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);
                animation: slideUp 0.25s ease;" onclick="event.stopPropagation()">
                <p id="flashAlertDocTitle" style="display:none; margin:0 0 0.5rem 0; font-size:0.9375rem; font-weight:600; color:#1e293b; line-height:1.35; word-break:break-word;"></p>
                <div style="font-size:3rem; margin-bottom:0.75rem;" id="flashAlertIcon">⚠️</div>
                <h3 id="flashAlertTitle" style="font-size:1.25rem; font-weight:700; color:#1e293b; margin-bottom:0.5rem;">
                    Confirmation
                </h3>
                <p id="flashAlertMessage" style="color:#64748b; font-size:0.95rem; line-height:1.5; margin-bottom:1.75rem; white-space:pre-line;"></p>
                <div id="flashAlertCustomSlot" style="display:none; margin-bottom:1.5rem; text-align:left; position:relative; max-width:100%;"></div>
                <div id="flashAlertInputContainer" style="margin-bottom:1.5rem; text-align:left; display:none;"></div>
                <div id="flashAlertActions" style="display:flex; flex-wrap:wrap; gap:0.75rem; justify-content:center;">
                    <button id="flashAlertCancelBtn" onclick="flashAlertCancel()" style="
                        padding:0.65rem 1.6rem; border-radius:9px; min-height:38px;
                        border:1.5px solid #cbd5e1; background:#fff;
                        color:#475569; font-weight:600; font-size:0.9rem; cursor:pointer;">
                        Annuler
                    </button>
                    <button id="flashAlertConfirmBtn" style="
                        padding:0.65rem 1.6rem; border-radius:9px; min-height:38px;
                        border:1.5px solid transparent; background:#ef4444; color:#fff;
                        font-weight:600; font-size:0.9rem; cursor:pointer;">
                        Confirmer
                    </button>
                </div>
            </div>
        </div>
        <style>
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        </style>
        <script>
        (function () {
            var _form = null;
            var _cb   = null;
            var _inputOpt = null;
            var _onConfirm = null;
            function flashAlertRestoreCustomSlot(poolId) {
                var slot = document.getElementById('flashAlertCustomSlot');
                var pool = document.getElementById(poolId || 'envoi-validation-flash-pool');
                if (!slot || !pool) return;
                while (slot.firstChild) pool.appendChild(slot.firstChild);
                slot.style.display = 'none';
            }
            window.flashAlert = function (message, formOrCb, options) {
                options = options || {};
                flashAlertRestoreCustomSlot(options.customBodyPoolId);
                var panel = document.getElementById('flashAlertPanel');
                if (panel) {
                    panel.style.maxWidth = String(options.maxWidth || '440px');
                    panel.style.width = String(options.width || '90%');
                    panel.style.padding = String(options.padding || '2rem 2.5rem');
                    panel.style.borderRadius = String(options.borderRadius || '16px');
                }
                var docTitleEl = document.getElementById('flashAlertDocTitle');
                var msgEl = document.getElementById('flashAlertMessage');
                var titleEl = document.getElementById('flashAlertTitle');
                if (options.documentTitle) {
                    docTitleEl.textContent = '« ' + String(options.documentTitle) + ' »';
                    docTitleEl.style.display = 'block';
                } else {
                    docTitleEl.textContent = '';
                    docTitleEl.style.display = 'none';
                }
                msgEl.textContent = message;
                if (String(message || '').trim() === '') {
                    msgEl.style.display = 'none';
                    msgEl.style.marginBottom = '0';
                    titleEl.style.marginBottom = '0.75rem';
                } else {
                    msgEl.style.display = 'block';
                    msgEl.style.marginBottom = '1.75rem';
                    titleEl.style.marginBottom = '0.5rem';
                }
                titleEl.textContent   = options.title || 'Confirmation';
                document.getElementById('flashAlertIcon').textContent    = options.icon  || '⚠️';
                var btn = document.getElementById('flashAlertConfirmBtn');
                btn.textContent      = options.confirmText || (options.noCancel ? 'OK' : 'Confirmer');
                btn.style.background = options.danger === false ? '#00b464'
                                     : options.noCancel         ? '#3b82f6'
                                     : '#ef4444';
                var cancelBtn = document.getElementById('flashAlertCancelBtn');
                cancelBtn.style.display = options.noCancel ? 'none' : '';
                _form = (formOrCb instanceof HTMLElement) ? formOrCb : null;
                _cb   = (typeof formOrCb === 'function')  ? formOrCb : null;
                _inputOpt = options.input || null;
                _onConfirm = typeof options.onConfirm === 'function' ? options.onConfirm : null;
                var inpContainer = document.getElementById('flashAlertInputContainer');
                inpContainer.style.display = 'none';
                inpContainer.innerHTML = '';
                var customSlot = document.getElementById('flashAlertCustomSlot');
                if (options.customBodyId) {
                    var pool = document.getElementById(options.customBodyPoolId || 'envoi-validation-flash-pool');
                    var el = document.getElementById(options.customBodyId);
                    if (customSlot && pool && el && pool.contains(el)) {
                        customSlot.appendChild(el);
                        customSlot.style.display = 'block';
                    }
                }
                if (_inputOpt) {
                    var label = document.createElement('label');
                    label.setAttribute('for', 'flashAlertInput');
                    label.style.cssText = 'display:block; font-size:0.875rem; font-weight:600; color:#334155; margin-bottom:0.5rem;';
                    label.textContent = _inputOpt.label || 'Motif';
                    var ta = document.createElement('textarea');
                    ta.id = 'flashAlertInput';
                    ta.name = _inputOpt.name || 'commentaire';
                    ta.placeholder = _inputOpt.placeholder || '';
                    ta.required = !!_inputOpt.required;
                    ta.style.cssText = 'width:100%; min-height:80px; padding:0.6rem 0.75rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.9rem; resize:vertical;';
                    inpContainer.appendChild(label);
                    inpContainer.appendChild(ta);
                    inpContainer.style.display = 'block';
                }
                document.getElementById('flashAlertModal').style.display = 'flex';
            };
            window.flashAlertCancel = function () {
                flashAlertRestoreCustomSlot();
                document.getElementById('flashAlertModal').style.display = 'none';
                _form = null; _cb = null; _inputOpt = null; _onConfirm = null;
            };
            function doConfirm() {
                var inp = document.getElementById('flashAlertInput');
                if (_inputOpt && inp) {
                    var val = (inp.value || '').trim();
                    if (_inputOpt.required && !val) {
                        inp.style.borderColor = '#ef4444';
                        inp.focus();
                        return;
                    }
                    if (_form && val) {
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = _inputOpt.name || 'commentaire';
                        hidden.value = val;
                        _form.appendChild(hidden);
                    }
                }
                if (_form && _onConfirm) {
                    try {
                        if (_onConfirm(_form) === false) return;
                    } catch (e) {}
                }
                document.getElementById('flashAlertModal').style.display = 'none';
                flashAlertRestoreCustomSlot();
                if (_cb) { _cb(); }
                else if (_form) { _form.submit(); }
                _form = null; _cb = null; _inputOpt = null; _onConfirm = null;
            }
            document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('flashAlertConfirmBtn').addEventListener('click', doConfirm);
                document.getElementById('flashAlertModal').addEventListener('click', function (e) {
                    if (e.target === this) flashAlertCancel();
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') return;
                    var m = document.getElementById('flashAlertModal');
                    if (!m || m.style.display !== 'flex') return;
                    flashAlertCancel();
                });
            });
        })();
        </script>

        @stack('scripts')
    </body>
</html>
