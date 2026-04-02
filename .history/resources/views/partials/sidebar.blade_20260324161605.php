{{-- Sidebar : menu statique GED - Charte ACSI --}}
<aside class="sidebar fixed top-20 left-0 w-[250px] h-[calc(100vh-80px)] z-[998] flex flex-col bg-[radial-gradient(ellipse_20%_80%_at_20%_80%,rgba(0,180,100,0.25),rgba(0,180,100,0.12)_25%,transparent_50%),linear-gradient(135deg,#0a0f15_0%,#0d1a1a_25%,#0f2520_50%,#0d1a1a_75%,#0a0f15_100%)] border-r-2 border-[rgba(0,180,100,0.2)] shadow-[4px_0_20px_rgba(0,0,0,0.4)] overflow-hidden transition-all duration-300">
    <nav class="flex-1 py-5 px-4 overflow-y-auto overflow-x-hidden sidebar-nav-scroll">
        <ul class="space-y-1">
            {{-- Tableau de bord --}}
            <li>
                <a href="{{ url('/') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('/') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📊</span>
                    <span class="nav-text">Tableau de bord</span>
                </a>
            </li>

            {{-- Documents --}}
            @can('documents.view')
            <li>
                <a href="{{ url('/documents') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('documents*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📁</span>
                    <span class="nav-text">Documents</span>
                </a>
            </li>
            @endcan

            {{-- Dossiers --}}
            @can('dossiers.view')
            <li>
                <a href="{{ url('/dossiers') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('dossiers*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📂</span>
                    <span class="nav-text">Dossiers</span>
                </a>
            </li>
            @endcan

            {{-- Types de documents --}}
            @can('types-documents.view')
            <li>
                <a href="{{ url('/types-documents') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('types-documents*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📋</span>
                    <span class="nav-text">Types de documents</span>
                </a>
            </li>
            @endcan

            {{-- Recherche --}}
            @can('documents.view')
            <li>
                <a href="{{ url('/recherche') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('recherche*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🔍</span>
                    <span class="nav-text">Recherche</span>
                </a>
            </li>
            @endcan

            {{-- Corbeille --}}
            @can('documents.view')
            <li>
                <a href="{{ route('corbeille.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('corbeille*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🗑️</span>
                    <span class="nav-text">Corbeille</span>
                </a>
            </li>
            @endcan

            {{-- Notifications --}}
            <li>
                <a href="{{ route('notifications.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('notifications*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🔔</span>
                    <span class="nav-text">Notifications</span>
                </a>
            </li>

            {{-- Administration --}}
            @if(auth()->user()->can('utilisateurs.view') || auth()->user()->hasRole('admin'))
            <li class="pt-4 mt-4 border-t border-white/10 nav-section-header">
                <p class="px-5 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider nav-text">Administration</p>
            </li>
            @can('utilisateurs.view')
            <li>
                <a href="{{ url('/utilisateurs') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('utilisateurs*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">👤</span>
                    <span class="nav-text">Utilisateurs</span>
                </a>
            </li>
            @endcan
            @if(auth()->user()->hasRole('admin'))
            <li>
                <a href="{{ url('/parametres') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.index') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">⚙️</span>
                    <span class="nav-text">Paramètres</span>
                </a>
            </li>
            <li>
                <a href="{{ route('parametres.structures.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.structures.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🏢</span>
                    <span class="nav-text">Structures</span>
                </a>
            </li>
            <li>
                <a href="{{ route('parametres.roles.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.roles.*', 'parametres.permissions.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🔐</span>
                    <span class="nav-text">Rôles</span>
                </a>
            </li>
            <li>
                <a href="{{ route('parametres.plan-classement.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.plan-classement.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🗂️</span>
                    <span class="nav-text">Plan de classement</span>
                </a>
            </li>
            <li>
                <a href="{{ route('parametres.types-dossiers.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.types-dossiers.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📑</span>
                    <span class="nav-text">Types de dossiers</span>
                </a>
            </li>
            <li>
                <a href="{{ route('parametres.types-metadonnees.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.types-metadonnees.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🏷️</span>
                    <span class="nav-text">Types de métadonnées</span>
                </a>
            </li>
            <li>
                <a href="{{ route('parametres.audit.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.audit.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📜</span>
                    <span class="nav-text">Journal d'audit</span>
                </a>
            </li>
            <li>
                <a href="{{ route('parametres.workflow.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.workflow.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🔄</span>
                    <span class="nav-text">Workflow</span>
                </a>
            </li>
            @endif
            @endif
        </ul>
    </nav>

    <div class="flex-shrink-0 p-4 border-t border-white/10">
        @auth
        <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-red-500/70 text-white font-semibold hover:bg-red-500/90 transition-colors">
            <span class="flex-shrink-0">🔑</span>
            <span class="nav-text">Déconnexion</span>
        </a>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
        @else
        <a href="{{ url('/login') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#00b464]/80 text-white font-semibold hover:bg-[#00b464] transition-colors">
            <span class="flex-shrink-0">🔑</span>
            <span class="nav-text">Connexion</span>
        </a>
        @endauth
    </div>
</aside>
