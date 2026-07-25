@php
    $etapeVide = [
        'code' => '',
        'nom' => '',
        'acteur_type' => 'secretariat',
        'acteur_valeur' => '',
        'action' => 'traiter',
        'mouvement' => 'aucun',
        'instructions_aide' => '',
        'est_finale' => false,
        'notifie_roles' => [],
    ];
    $etapesInitiales = old('etapes');
    if ($etapesInitiales === null) {
        if (isset($circuit)) {
            $etapesInitiales = $circuit->etapes->map(fn ($e) => [
                'code' => $e->code,
                'nom' => $e->nom,
                'acteur_type' => $e->acteur_type,
                'acteur_valeur' => $e->acteur_valeur,
                'action' => $e->action,
                'mouvement' => $e->mouvement,
                'instructions_aide' => $e->instructions_aide,
                'est_finale' => $e->est_finale,
                'notifie_roles' => $e->notifie_roles ?? [],
            ])->values()->all();
        } else {
            $etapesInitiales = [$etapeVide];
        }
    }
@endphp

<div
    x-data="{
        etapes: {{ Js::from($etapesInitiales) }},
        add() {
            this.etapes.push({{ Js::from($etapeVide) }});
        },
        remove(i) {
            if (this.etapes.length > 1) this.etapes.splice(i, 1);
        }
    }"
    class="space-y-6"
>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold mb-1">Code <span class="text-red-500">*</span></label>
            <input type="text" name="code" value="{{ old('code', $circuit->code ?? '') }}" required pattern="[a-z0-9_]+"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 dark:bg-slate-800" placeholder="facture_prestataire">
            @error('code')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1">Sens initial <span class="text-red-500">*</span></label>
            <select name="sens_initial" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 dark:bg-slate-800">
                <option value="arrivee" @selected(old('sens_initial', $circuit->sens_initial ?? 'arrivee') === 'arrivee')>Arrivée</option>
                <option value="depart" @selected(old('sens_initial', $circuit->sens_initial ?? '') === 'depart')>Départ</option>
            </select>
        </div>
    </div>

    <div>
        <label class="block text-sm font-semibold mb-1">Libellé <span class="text-red-500">*</span></label>
        <input type="text" name="libelle" value="{{ old('libelle', $circuit->libelle ?? '') }}" required
               class="w-full rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 dark:bg-slate-800">
        @error('libelle')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-semibold mb-1">Description</label>
        <textarea name="description" rows="2" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 dark:bg-slate-800">{{ old('description', $circuit->description ?? '') }}</textarea>
    </div>

    <label class="inline-flex items-center gap-2 text-sm font-semibold">
        <input type="checkbox" name="actif" value="1" @checked(old('actif', $circuit->actif ?? true))>
        Circuit actif
    </label>

    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="font-bold text-slate-800 dark:text-slate-100">Étapes du circuit</h3>
            <button type="button" @click="add()" class="px-3 py-1.5 rounded-lg bg-slate-800 text-white text-sm font-semibold">+ Étape</button>
        </div>
        @error('etapes')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

        <template x-for="(etape, index) in etapes" :key="index">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-3 bg-slate-50/60 dark:bg-slate-900/30">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-emerald-700" x-text="'Étape ' + (index + 1)"></span>
                    <button type="button" @click="remove(index)" class="text-sm text-red-600 font-semibold" x-show="etapes.length > 1">Retirer</button>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Code</label>
                        <input type="text" :name="'etapes['+index+'][code]'" x-model="etape.code" required pattern="[a-z0-9_]+"
                               class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Nom</label>
                        <input type="text" :name="'etapes['+index+'][nom]'" x-model="etape.nom" required
                               class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Type d’acteur</label>
                        <select :name="'etapes['+index+'][acteur_type]'" x-model="etape.acteur_type"
                                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-800">
                            @foreach($acteurTypes as $val => $lab)
                                <option value="{{ $val }}">{{ $lab }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Valeur acteur (rôle / fonction)</label>
                        <input type="text" :name="'etapes['+index+'][acteur_valeur]'" x-model="etape.acteur_valeur"
                               list="roles-circuit-list"
                               class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-800"
                               placeholder="ex. agent_comptable">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Action</label>
                        <select :name="'etapes['+index+'][action]'" x-model="etape.action"
                                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-800">
                            @foreach($actions as $val => $lab)
                                <option value="{{ $val }}">{{ $lab }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Mouvement</label>
                        <select :name="'etapes['+index+'][mouvement]'" x-model="etape.mouvement"
                                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-800">
                            @foreach($mouvements as $val => $lab)
                                <option value="{{ $val }}">{{ $lab }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Aide / consignes</label>
                    <textarea :name="'etapes['+index+'][instructions_aide]'" x-model="etape.instructions_aide" rows="2"
                              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm dark:bg-slate-800"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-semibold">
                    <input type="checkbox" :name="'etapes['+index+'][est_finale]'" value="1" x-model="etape.est_finale">
                    Étape finale (clôture du circuit)
                </label>
            </div>
        </template>
    </div>

    <datalist id="roles-circuit-list">
        @foreach($roles as $role)
            <option value="{{ $role }}"></option>
        @endforeach
    </datalist>
</div>
