{{-- Header : hamburger (toggle) + barre supérieure --}}
<div id="mainHeader" class="header fixed top-0 left-[250px] right-0 h-20 z-[1099] flex items-center bg-[radial-gradient(ellipse_80%_80%_at_20%_80%,rgba(0,180,100,0.25),rgba(0,180,100,0.12)_25%,transparent_50%),linear-gradient(135deg,#0a0f15_0%,#0d1a1a_25%,#0f2520_50%,#0d1a1a_75%,#0a0f15_100%)] text-white shadow-md transition-all duration-300">
    {{-- Bouton toggle : hamburger (3 traits) → X quand menu fermé (comme Progcaisse) --}}
    <button type="button" id="navControl" onclick="toggleSidebar()" class="nav-control flex-shrink-0 h-full w-14 flex items-center justify-center border-r border-white/10 hover:bg-white/5 focus:bg-transparent focus:outline-none focus:ring-0 active:bg-white/5 cursor-pointer transition-all duration-300" title="Afficher / masquer le menu">
        <div class="hamburger flex flex-col gap-1.5 w-6 items-center justify-center">
            <span class="line block w-full h-0.5 bg-gradient-to-r from-[#00b464] to-[#00ff88] rounded transition-all duration-300"></span>
            <span class="line block w-full h-0.5 bg-gradient-to-r from-[#00b464] to-[#00ff88] rounded transition-all duration-300"></span>
            <span class="line block w-full h-0.5 bg-gradient-to-r from-[#00b464] to-[#00ff88] rounded transition-all duration-300"></span>
        </div>
    </button>
    <div class="flex-1 flex justify-between items-center px-6">
        <span class="text-sm text-slate-300 font-semibold system-label">COSUD – Courrier et Suivi des Dépenses | ACSI</span>
        <ul class="header-right flex items-center gap-1">
            {{-- 1. Theme Toggle (comme Progcaisse) --}}
            <li class="mr-3">
                <button id="themeToggle" type="button" class="px-3 py-1.5 rounded bg-white/10 text-lg hover:bg-white/20 transition-colors" title="Mode clair / mode sombre">🌙</button>
            </li>
            {{-- 2. Notifications (comme Progcaisse) --}}
            <li class="mr-3" x-data="notifDropdown({{ $unreadNotificationsCount ?? 0 }})" @click.outside="open = false">
                <button type="button" x-ref="notifTrigger" @click="open = !open; if(open) positionDropdown(); if(open) loadNotifs()" class="relative flex items-center gap-1 px-3 py-2 rounded hover:bg-white/10 text-lg" title="Notifications">
                    <span class="relative inline-block">
                        🔔
                        <span x-show="unreadCount > 0" x-cloak x-transition class="absolute -top-2 -right-2 min-w-[1.25rem] h-5 px-1.5 flex items-center justify-center rounded-full bg-red-600 text-white text-xs font-bold shadow-lg ring-2 ring-white/90" x-text="unreadCount > 99 ? '99+' : unreadCount"></span>
                    </span>
                    <span class="text-xs opacity-80">▼</span>
                </button>
                <template x-teleport="body">
                    <div x-show="open" x-cloak x-transition :style="menuStyle" class="notif-dropdown-panel fixed z-[9999] overflow-hidden">
                        <div class="notif-dropdown-header">
                            <span class="notif-dropdown-title">Notifications</span>
                        </div>
                        <div class="notif-dropdown-body">
                            <template x-if="loading">
                                <div class="notif-loading">
                                    <span class="notif-spinner"></span>
                                    <span>Chargement...</span>
                                </div>
                            </template>
                            <template x-if="!loading && notifs.length === 0">
                                <div class="notif-empty">
                                    <span class="notif-empty-icon">✓</span>
                                    <p>Aucune notification</p>
                                </div>
                            </template>
                            <template x-if="!loading && notifs.length > 0">
                                <div class="divide-y divide-slate-100">
                                    <template x-for="n in notifs" :key="n.id">
                                        <a :href="n.url || '{{ route('notifications.index') }}'" class="block px-4 py-3 hover:bg-slate-50 transition-colors">
                                            <p class="text-sm text-slate-800 font-medium line-clamp-2" x-text="n.message"></p>
                                            <p class="text-xs text-slate-500 mt-0.5" x-text="n.created_at"></p>
                                        </a>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <div class="notif-dropdown-footer">
                            <a href="{{ route('notifications.index') }}" class="notif-footer-link">Voir toutes les notifications →</a>
                        </div>
                    </div>
                </template>
            </li>
            @auth
            {{-- 3. Bulle utilisateur + dropdown (comme Progcaisse : fond blanc, texte sombre) --}}
            <li class="flex items-center user-box" x-data="profileDropdown()" @click.outside="open = false" x-ref="profileTrigger">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}&background=2c5364&color=fff" alt="Avatar" class="w-10 h-10 rounded-full avatar">
                <div class="user-details ml-2">
                    <p class="user-name text-sm font-semibold text-white">{{ auth()->user()->name ?? 'Utilisateur' }}</p>
                    <small class="user-email text-xs text-white/90">{{ auth()->user()->email ?? '' }}</small>
                </div>
                <div class="relative user-dropdown ml-2">
                    <button type="button" @click="open = !open; if(open) positionDropdown()" class="text-white/80 hover:text-white cursor-pointer text-sm" aria-haspopup="true" :aria-expanded="open">▼</button>
                    <template x-teleport="body">
                        <div x-show="open" x-cloak x-transition x-ref="profileMenu"
                             :style="menuStyle"
                             class="header-dropdown fixed w-48 bg-white rounded-lg shadow-xl py-1 border border-slate-200 z-[9999] min-w-[12rem]">
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">👤 Profil</a>
                            <form method="POST" action="{{ route('logout') }}" data-skip-submit-loading="1">
                                @csrf
                                <button type="submit" class="flex items-center gap-2 w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50 font-medium">🔑 Déconnexion</button>
                            </form>
                        </div>
                    </template>
                </div>
            </li>
            @else
            <li>
                <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg bg-[#00b464] text-white font-semibold text-sm hover:bg-[#00a055] transition-colors">Connexion</a>
            </li>
            @endauth
        </ul>
    </div>
</div>

<style>
/* Header droit : structure Progcaisse */
.header-right { list-style: none; margin: 0; padding: 0; }
.user-box {
    display: flex; align-items: center; gap: 8px;
    background: rgba(0,0,0,0.2); padding: 6px 12px; border-radius: 8px;
}
.user-details .user-name { font-size: 0.95rem; font-weight: 600; color: #fff; line-height: 1.2; }
.user-details .user-email { font-size: 0.75rem; color: rgba(255,255,255,0.9); }
/* Dropdown profil : fond blanc comme Progcaisse */
.header-dropdown a, .header-dropdown button { transition: background 0.15s; }

/* Dropdown notifications (design Progcaisse : dimensions exactes) */
.notif-dropdown-panel {
    min-width: 380px; max-width: 420px; width: 380px;
    padding: 0; background: #fff; border-radius: 12px;
    border: 1px solid rgba(0,180,100,0.2);
    box-shadow: 0 12px 40px rgba(0,0,0,0.15), 0 0 1px rgba(0,0,0,0.1);
}
.notif-dropdown-header {
    background: linear-gradient(135deg, rgba(0,180,100,0.12), rgba(0,150,85,0.08));
    padding: 12px 16px; border-bottom: 1px solid rgba(0,180,100,0.15);
}
.notif-dropdown-title { font-weight: 700; font-size: 0.95rem; color: #0f172a; }
.notif-dropdown-body {
    max-height: 320px; overflow-y: auto; padding: 8px 0; background: #fff;
}
.notif-loading {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 24px 16px; color: #64748b; font-size: 0.875rem;
}
.notif-spinner {
    width: 18px; height: 18px; border: 2px solid #e2e8f0;
    border-top-color: #00b464; border-radius: 50%;
    animation: notif-spin 0.7s linear infinite;
}
@keyframes notif-spin { to { transform: rotate(360deg); } }
.notif-dropdown-footer {
    padding: 10px 16px; background: #f8fafc; border-top: 1px solid #e2e8f0;
}
.notif-footer-link {
    display: block; text-align: center; color: #059669 !important;
    font-weight: 600; font-size: 0.875rem; text-decoration: none !important;
    padding: 6px 0; border-radius: 6px; transition: background 0.2s, color 0.2s;
}
.notif-footer-link:hover { background: rgba(0,180,100,0.08); color: #047857 !important; }
.notif-empty {
    text-align: center; padding: 32px 20px; color: #94a3b8;
}
.notif-empty-icon {
    display: flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; margin: 0 auto 12px;
    border-radius: 50%; background: #f1f5f9; color: #94a3b8;
    font-size: 1.25rem;
}
.notif-empty p { margin: 0; font-size: 0.9rem; }

/* Animation hamburger → X (comme Progcaisse) */
.header .nav-control .hamburger.is-active .line:nth-child(1) {
    transform: rotate(45deg) translate(5px, 5px);
}
.header .nav-control .hamburger.is-active .line:nth-child(2) {
    opacity: 0;
}
.header .nav-control .hamburger.is-active .line:nth-child(3) {
    transform: rotate(-45deg) translate(6px, -6px);
}
</style>
<script>
// Dropdowns : position dynamique sous le bouton, visible entièrement
function profileDropdown() {
    return {
        open: false,
        menuStyle: 'visibility: hidden;',
        positionDropdown() {
            this.$nextTick(() => {
                this.$nextTick(() => {
                    const el = this.$refs.profileTrigger;
                    if (!el) return;
                    const r = el.getBoundingClientRect();
                    const top = r.bottom + 8;
                    const left = Math.max(8, r.right - 192);
                    this.menuStyle = `position: fixed; top: ${top}px; left: ${left}px; visibility: visible;`;
                });
            });
        }
    };
}
function notifDropdown(initialCount = 0) {
    return {
        open: false,
        loading: true,
        notifs: [],
        unreadCount: initialCount,
        menuStyle: 'visibility: hidden;',
        positionDropdown() {
            this.$nextTick(() => {
                const btn = this.$refs.notifTrigger;
                if (!btn) return;
                const r = btn.getBoundingClientRect();
                const top = r.bottom + 8;
                const right = window.innerWidth - r.right;
                this.menuStyle = `position: fixed; top: ${top}px; right: ${right}px; left: auto; visibility: visible;`;
            });
        },
        loadNotifs() {
            this.loading = true;
            this.notifs = [];
            fetch('{{ route('notifications.recent') }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(r => r.json()).then(data => {
                this.notifs = data.notifications || [];
                if (typeof data.unread_count !== 'undefined') {
                    this.unreadCount = data.unread_count;
                }
                this.loading = false;
            }).catch(() => {
                this.loading = false;
            });
        },
        refreshBadge() {
            fetch('{{ route('notifications.recent') }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(r => r.json()).then(data => {
                if (typeof data.unread_count !== 'undefined') this.unreadCount = data.unread_count;
            }).catch(() => {});
        },
        init() {
            setInterval(() => this.refreshBadge(), 60000);
        }
    };
}
// Toggle sidebar (comme Progcaisse : menu-toggle sur #main-wrapper + is-active sur hamburger)
window.toggleSidebar = function() {
    var wrapper = document.getElementById('main-wrapper');
    if (!wrapper) return;
    wrapper.classList.toggle('menu-toggle');
    var hamburger = document.querySelector('#navControl .hamburger');
    if (hamburger) hamburger.classList.toggle('is-active');
    var isMinimized = wrapper.classList.contains('menu-toggle');
    if (isMinimized) localStorage.setItem('sidebar-collapsed', '1');
    else localStorage.removeItem('sidebar-collapsed');
};
document.addEventListener("DOMContentLoaded", function() {
    var wrapper = document.getElementById('main-wrapper');
    if (wrapper && localStorage.getItem('sidebar-collapsed') === '1') {
        wrapper.classList.add('menu-toggle');
        var hamburger = document.querySelector('#navControl .hamburger');
        if (hamburger) hamburger.classList.add('is-active');
    }
    // Dark/Light Mode (comme Progcaisse : body.dark-mode + persist localStorage)
    var btn = document.getElementById("themeToggle");
    if (btn) {
        var saved = localStorage.getItem("theme");
        if (saved === "dark") {
            document.body.classList.add("dark-mode");
            document.documentElement.classList.add("dark");
            btn.textContent = "☀️";
        } else {
            btn.textContent = "🌙";
        }
        btn.addEventListener("click", function() {
            document.body.classList.toggle("dark-mode");
            document.documentElement.classList.toggle("dark");
            if (document.body.classList.contains("dark-mode")) {
                localStorage.setItem("theme", "dark");
                btn.textContent = "☀️";
            } else {
                localStorage.setItem("theme", "light");
                btn.textContent = "🌙";
            }
        });
    }
});
</script>
