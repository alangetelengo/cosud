@extends('layouts.app')

@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')
@section('page-title', 'Notifications courriers')
@section('page-title-info')
    <nav class="flex items-center gap-1.5 text-sm flex-wrap" aria-label="Fil d'Ariane">
        <a href="{{ route('parametres.index') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 transition-colors">Paramètres</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">Notifications</span>
    </nav>
@endsection

@section('content')
<div class="max-w-3xl space-y-6">
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-transition class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200/80 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-4 shadow-sm">
        <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold">✓</span>
        <span class="flex-1 font-medium">{{ session('success') }}</span>
        <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-emerald-200/50 dark:hover:bg-emerald-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
    </div>
    @endif

    <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
        Activez ou désactivez les alertes automatiques liées aux courriers. Lorsque l’option ci-dessous est <strong class="font-semibold text-slate-700 dark:text-slate-200">désactivée</strong>, le DG ne reçoit ni <strong class="font-semibold text-slate-700 dark:text-slate-200">SMS</strong> ni <strong class="font-semibold text-slate-700 dark:text-slate-200">notification cloche</strong> à l’enregistrement d’une facture / prestataire. Par défaut, l’option est <strong class="font-semibold text-slate-700 dark:text-slate-200">activée</strong>.
    </p>

    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
        <form method="post" action="{{ route('parametres.notifications.update') }}" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="flex gap-4 items-start">
                <input type="hidden" name="notif_facture_enregistree_dg" value="0">
                <input
                    type="checkbox"
                    name="notif_facture_enregistree_dg"
                    id="notif_facture_enregistree_dg"
                    value="1"
                    class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700"
                    @checked(old('notif_facture_enregistree_dg', $notifFactureEnregistreeDg))
                >
                <div class="min-w-0 flex-1">
                    <label for="notif_facture_enregistree_dg" class="font-semibold text-slate-800 dark:text-slate-100 cursor-pointer">
                        Notifier le DG à l’enregistrement d’une facture / prestataire
                    </label>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Si cette option est activée, chaque enregistrement d’un courrier sur le circuit facture prestataire envoie au DG une <strong class="font-medium">notification dans COSUD</strong> et un <strong class="font-medium">SMS</strong> (si un numéro est renseigné) pour demander le Bon pour accord. Utile à désactiver pendant une reprise massive du registre.
                    </p>
                    @error('notif_facture_enregistree_dg')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#00b464] text-white font-semibold hover:bg-[#00a055] shadow-sm transition-all">
                    Enregistrer
                </button>
                <a href="{{ route('parametres.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all no-underline">
                    Retour aux paramètres
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
