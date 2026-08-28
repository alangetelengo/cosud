{{-- Sidebar : menus pilotés uniquement par permissions Spatie --}}
<aside class="sidebar fixed top-20 left-0 w-[250px] h-[calc(100vh-80px)] z-[998] flex flex-col bg-[radial-gradient(ellipse_20%_80%_at_20%_80%,rgba(0,180,100,0.25),rgba(0,180,100,0.12)_25%,transparent_50%),linear-gradient(135deg,#0a0f15_0%,#0d1a1a_25%,#0f2520_50%,#0d1a1a_75%,#0a0f15_100%)] border-r-2 border-[rgba(0,180,100,0.2)] shadow-[4px_0_20px_rgba(0,0,0,0.4)] overflow-hidden transition-all duration-300">
    <nav class="flex-1 py-5 px-4 overflow-y-auto overflow-x-hidden sidebar-nav-scroll">
        <ul class="space-y-1">
            @can('dashboard.view')
            <li>
                <a href="{{ url('/') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('/') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📊</span>
                    <span class="nav-text">Tableau de bord</span>
                </a>
            </li>
            @endcan

            @can('documents.view')
            <li>
                <a href="{{ url('/documents') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('documents*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📁</span>
                    <span class="nav-text">Documents</span>
                </a>
            </li>
            @endcan

            @can('dossiers.view')
            <li>
                <a href="{{ url('/dossiers') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('dossiers*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📂</span>
                    <span class="nav-text">Dossiers</span>
                </a>
            </li>
            @endcan

            @can('types-documents.view')
            <li>
                <a href="{{ url('/types-documents') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('types-documents*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📋</span>
                    <span class="nav-text">Types de documents</span>
                </a>
            </li>
            @endcan

            @can('recherche.view')
            <li>
                <a href="{{ url('/recherche') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('recherche*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🔍</span>
                    <span class="nav-text">Recherche</span>
                </a>
            </li>
            @endcan

            @can('corbeille.view')
            <li>
                <a href="{{ route('corbeille.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('corbeille*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🗑️</span>
                    <span class="nav-text">Corbeille</span>
                </a>
            </li>
            @endcan

            @can('organigramme.view')
            <li class="pt-4 mt-4 border-t border-white/10 nav-section-header">
                <p class="px-5 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider nav-text">Organisation</p>
            </li>
            <li>
                <a href="{{ route('parametres.structures.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.structures.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🏢</span>
                    <span class="nav-text">Organigramme</span>
                </a>
            </li>
            @endcan

            @can('courriers.view')
            <li>
                <a href="{{ route('courriers.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('courriers') || (request()->is('courriers/*') && ! request()->is('courriers-a-recevoir')) ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">✉️</span>
                    <span class="nav-text flex-1">Courriers</span>
                    @if(($courriersNonLusTotal ?? 0) > 0)
                        <span class="nav-text inline-flex items-center justify-center min-w-[1.35rem] h-5 px-1.5 rounded-full bg-emerald-400 text-[#0b1f17] text-[11px] font-bold">{{ $courriersNonLusTotal }}</span>
                    @endif
                </a>
            </li>
            <li>
                <a href="{{ route('courriers.registres.arrivee') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('courriers.registres.arrivee') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📗</span>
                    <span class="nav-text">Registre Arrivée</span>
                </a>
            </li>
            <li>
                <a href="{{ route('courriers.registres.depart') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('courriers.registres.depart') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📕</span>
                    <span class="nav-text">Registre Départ</span>
                </a>
            </li>
            @endcan

            @can('suivi-paiements.view')
            <li>
                <a href="{{ route('suivi-paiements.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('suivi-paiements.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">💳</span>
                    <span class="nav-text">Suivi de dépense</span>
                </a>
            </li>
            @endcan

            @can('bordereau-transmission.view')
            <li>
                <a href="{{ route('bordereau-transmission.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('bordereau-transmission.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🧾</span>
                    <span class="nav-text">Bordereau de <br>transmission</span>
                </a>
            </li>
            @endcan

            @can('suivi-factures.view')
            <li>
                <a href="{{ route('suivi-factures-fournisseurs.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('suivi-factures-fournisseurs.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📁</span>
                    <span class="nav-text">Suivi de factures</span>
                </a>
            </li>
            @endcan

            @can('fournisseurs-prestataires.view')
            <li>
                <a href="{{ route('fournisseurs-prestataires.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('fournisseurs-prestataires.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🏢</span>
                    <span class="nav-text">Fournisseurs ou <br>prestataires</span>
                </a>
            </li>
            @endcan

            @canany(['factures-regularisation.view', 'factures-regularisation.create'])
            <li>
                <a href="{{ route('factures-regularisation.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('factures-regularisation.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🗂️</span>
                    <span class="nav-text">Reprise des factures <br>prestataires</span>
                </a>
            </li>
            @endcanany

            @can('moratoires.view')
            <li>
                <a href="{{ route('moratoires.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('moratoires.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📅</span>
                    <span class="nav-text">Moratoires</span>
                </a>
            </li>
            @endcan

            {{-- @can('courriers.recevoir')
            <li>
                <a href="{{ route('courriers.a-recevoir') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('courriers-a-recevoir') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📥</span>
                    <span class="nav-text">À réceptionner</span>
                </a>
            </li>
            @endcan --}}

            @if(auth()->user()->can('utilisateurs.view') || auth()->user()->can('parametres.view'))
            <li class="pt-4 mt-4 border-t border-white/10 nav-section-header">
                <p class="px-5 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider nav-text">Administration</p>
            </li>
            @endif

            @can('utilisateurs.view')
            <li>
                <a href="{{ url('/utilisateurs') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->is('utilisateurs*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">👤</span>
                    <span class="nav-text">Utilisateurs</span>
                </a>
            </li>
            @endcan

            @can('parametres.view')
            <li>
                <a href="{{ url('/parametres') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.index') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">⚙️</span>
                    <span class="nav-text">Paramètres</span>
                </a>
            </li>
            @endcan
            @can('parametres.structures.view')
            <li>
                <a href="{{ route('parametres.structures.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.structures.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🏢</span>
                    <span class="nav-text">Structures</span>
                </a>
            </li>
            @endcan
            @can('parametres.roles.view')
            <li>
                <a href="{{ route('parametres.roles.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.roles.*', 'parametres.permissions.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🔐</span>
                    <span class="nav-text">Rôles</span>
                </a>
            </li>
            @endcan
            @can('parametres.plan-classement.view')
            <li>
                <a href="{{ route('parametres.plan-classement.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.plan-classement.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🗂️</span>
                    <span class="nav-text">Plan de classement</span>
                </a>
            </li>
            @endcan
            @can('parametres.types-dossiers.view')
            <li>
                <a href="{{ route('parametres.types-dossiers.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.types-dossiers.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📑</span>
                    <span class="nav-text">Types de dossiers</span>
                </a>
            </li>
            @endcan
            @can('parametres.categories-depense.view')
            <li>
                <a href="{{ route('parametres.categories-depense.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.categories-depense.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🏷️</span>
                    <span class="nav-text">Catégories de dépense</span>
                </a>
            </li>
            @endcan
            @can('parametres.types-metadonnees.view')
            <li>
                <a href="{{ route('parametres.types-metadonnees.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.types-metadonnees.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🏷️</span>
                    <span class="nav-text">Types de métadonnées</span>
                </a>
            </li>
            @endcan
            @can('parametres.audit.view')
            <li>
                <a href="{{ route('parametres.audit.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.audit.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">📜</span>
                    <span class="nav-text">Journal d'audit</span>
                </a>
            </li>
            @endcan
            @can('parametres.workflow.view')
            <li>
                <a href="{{ route('parametres.workflow.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.workflow.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">🔄</span>
                    <span class="nav-text">Workflow</span>
                </a>
            </li>
            @endcan
            @can('parametres.circuits-courriers.view')
            <li>
                <a href="{{ route('parametres.circuits-courriers.index') }}" class="flex items-center gap-3 px-5 py-3 rounded-lg text-white/80 hover:bg-[rgba(0,234,255,0.1)] hover:text-white transition-all {{ request()->routeIs('parametres.circuits-courriers.*') ? 'bg-gradient-to-r from-[#06a269] to-[#1c4d3b] text-white font-semibold' : '' }}">
                    <span class="text-lg flex-shrink-0">✉️</span>
                    <span class="nav-text">Circuits courriers</span>
                </a>
            </li>
            @endcan
        </ul>
    </nav>

    <div class="flex-shrink-0 p-4 border-t border-white/10">
        @auth
        <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-red-500/70 text-white font-semibold hover:bg-red-500/90 transition-colors">
            <span class="flex-shrink-0">🔑</span>
            <span class="nav-text">Déconnexion</span>
        </a>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden" data-skip-submit-loading="1">@csrf</form>
        @else
        <a href="{{ url('/login') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#00b464]/80 text-white font-semibold hover:bg-[#00b464] transition-colors">
            <span class="flex-shrink-0">🔑</span>
            <span class="nav-text">Connexion</span>
        </a>
        @endauth
    </div>
</aside>
