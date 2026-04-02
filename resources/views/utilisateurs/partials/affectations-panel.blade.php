{{-- Panneau affectations (user_structure) — inclus dans utilisateurs/edit onglet Structures --}}
<div class="mb-6 p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 shadow-sm">
    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
        Définissez la <strong class="text-slate-800 dark:text-slate-200">fonction métier</strong> de cet utilisateur sur chaque structure (pivot <code class="text-xs bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">user_structure</code>).
        Lorsque la fonction choisie correspond à la <strong class="text-slate-800 dark:text-slate-200">fonction de validation</strong> de la structure et qu’il n’y a pas de date de fin, l’utilisateur peut être identifié comme titulaire pour la validation hiérarchique.
        Le champ <strong class="text-slate-800 dark:text-slate-200">Structure</strong> de l’onglet Compte est la structure d’appartenance principale (<code class="text-xs">users.structure_id</code>) ; alignez-la si besoin avec l’affectation métier.
    </p>
</div>

<div class="space-y-6">
    @forelse($utilisateur->structures as $st)
        @php
            $pivotFonctionId = $st->pivot->fonction_id ? (int) $st->pivot->fonction_id : null;
            $structFonctionId = $st->fonction_id ? (int) $st->fonction_id : null;
            $estTitulairePotentiel = $pivotFonctionId && $structFonctionId && $pivotFonctionId === $structFonctionId && empty($st->pivot->date_fin);
            $da = $st->pivot->date_affectation ? \Illuminate\Support\Carbon::parse($st->pivot->date_affectation)->format('Y-m-d') : '';
            $df = $st->pivot->date_fin ? \Illuminate\Support\Carbon::parse($st->pivot->date_fin)->format('Y-m-d') : '';
        @endphp
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ $st->nom }}</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                        <span class="font-mono text-xs">{{ $st->code }}</span>
                        @if($st->relationLoaded('fonction') && $st->fonction)
                            <span class="mx-1">·</span> Validation structure : <span class="text-slate-700 dark:text-slate-300">{{ $st->fonction->libelle }}</span>
                        @elseif($structFonctionId)
                            <span class="mx-1">·</span> Validation structure : <span class="text-amber-600 dark:text-amber-400 text-xs">(fonction #{{ $structFonctionId }})</span>
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($estTitulairePotentiel)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200/60 dark:border-emerald-700/50">
                            Titulaire validation (actif)
                        </span>
                    @elseif($pivotFonctionId && $structFonctionId && $pivotFonctionId !== $structFonctionId)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300" title="La fonction sur le pivot ne correspond pas à la fonction de validation de cette structure">
                            Fonction ≠ validation structure
                        </span>
                    @endif
                    @if($st->pivot->date_fin)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200">
                            Affectation close
                        </span>
                    @endif
                </div>
            </div>
            <div class="p-6">
                @php $formKey = 'update_'.$st->id; $useOld = old('_form') === $formKey; @endphp
                <form action="{{ route('utilisateurs.affectations.update', [$utilisateur, $st]) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form" value="{{ $formKey }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Fonction (métier sur cette structure)</label>
                            <select name="fonction_id" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                                <option value="">— Aucune —</option>
                                @foreach($fonctions as $f)
                                    <option value="{{ $f->id }}" @selected((string) ($useOld ? old('fonction_id') : $st->pivot->fonction_id) === (string) $f->id)>{{ $f->libelle }}</option>
                                @endforeach
                            </select>
                            @if($useOld)
                            @error('fonction_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Rôle (libellé pivot)</label>
                            <input type="text" name="role" value="{{ $useOld ? old('role') : $st->pivot->role }}" placeholder="ex. Responsable"
                                class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                            @if($useOld)
                            @error('role')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Début</label>
                            <input type="date" name="date_affectation" value="{{ $useOld ? old('date_affectation') : $da }}"
                                class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                            @if($useOld)
                            @error('date_affectation')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Fin (vide = en cours)</label>
                            <input type="date" name="date_fin" value="{{ $useOld ? old('date_fin') : $df }}"
                                class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                            @if($useOld)
                            @error('date_fin')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055]">Enregistrer cette affectation</button>
                    </div>
                </form>
                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <form action="{{ route('utilisateurs.affectations.destroy', [$utilisateur, $st]) }}" method="POST" class="inline"
                          onsubmit="return confirm('Retirer cette affectation à la structure « {{ e($st->nom) }} » ?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 rounded-lg border border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 text-sm font-semibold hover:bg-red-50 dark:hover:bg-red-900/20">
                            Retirer cette structure
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="p-10 rounded-xl border border-dashed border-slate-200 dark:border-slate-600 text-center text-slate-500 dark:text-slate-400">
            Aucune affectation sur une structure pour le moment. Ajoutez-en une ci-dessous.
        </div>
    @endforelse

    @if($structuresDisponibles->isNotEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 mb-4">Ajouter une structure</h3>
        @php $storeOld = old('_form') === 'store'; @endphp
        <form action="{{ route('utilisateurs.affectations.store', $utilisateur) }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="_form" value="store">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Structure *</label>
                    <select name="structure_id" required class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                        <option value="">— Choisir —</option>
                        @foreach($structuresDisponibles as $s)
                            <option value="{{ $s->id }}" @selected($storeOld && (string) old('structure_id') === (string) $s->id)>
                                {{ $s->nom }} ({{ $s->code }})
                                @if($s->fonction)
                                    — validation : {{ $s->fonction->libelle }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @if($storeOld)
                    @error('structure_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    @endif
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Fonction</label>
                    <select name="fonction_id" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                        <option value="">— Aucune —</option>
                        @foreach($fonctions as $f)
                            <option value="{{ $f->id }}" @selected($storeOld && (string) old('fonction_id') === (string) $f->id)>{{ $f->libelle }}</option>
                        @endforeach
                    </select>
                    @if($storeOld)
                    @error('fonction_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Rôle (pivot)</label>
                    <input type="text" name="role" value="{{ $storeOld ? old('role') : '' }}" placeholder="ex. Adjoint"
                        class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    @if($storeOld)
                    @error('role')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Début</label>
                    <input type="date" name="date_affectation" value="{{ $storeOld ? old('date_affectation') : '' }}"
                        class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    @if($storeOld)
                    @error('date_affectation')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Fin</label>
                    <input type="date" name="date_fin" value="{{ $storeOld ? old('date_fin') : '' }}"
                        class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    @if($storeOld)
                    @error('date_fin')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                    @endif
                </div>
            </div>
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055]">Ajouter l’affectation</button>
        </form>
    </div>
    @elseif($utilisateur->structures->isEmpty())
    <p class="text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-200/60 dark:border-amber-800/50 rounded-lg px-4 py-3">
        Toutes les structures actives sont déjà affectées à cet utilisateur, ou aucune structure active n’existe en base.
    </p>
    @endif
</div>
