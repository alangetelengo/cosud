@props(['ouvert' => false, 'titre' => null])
{{-- 
  Usage: 
  <x-aide>
    <x-slot:titre>Titre optionnel</x-slot:titre>
    <ul class="list-disc pl-5 space-y-1">
      <li>Point 1</li>
      <li>Point 2</li>
    </ul>
  </x-aide>
  OU avec items:
  <x-aide :items="[
    ['titre' => 'Filtrer', 'texte' => 'Utilisez les filtres pour affiner...'],
    ['titre' => 'Déposer', 'texte' => 'Cliquez sur Déposer pour ajouter...'],
  ]" />
--}}
<div x-data="{ open: {{ $ouvert ? 'true' : 'false' }} }" class="mb-6">
    <button type="button" @click="open = !open" 
        class="flex items-center gap-2 w-full px-4 py-3 rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/80 dark:bg-sky-900/20 hover:bg-sky-100/80 dark:hover:bg-sky-900/30 text-left transition-colors">
        <span class="flex-shrink-0 w-8 h-8 rounded-lg bg-sky-500/20 dark:bg-sky-500/30 flex items-center justify-center text-sky-600 dark:text-sky-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </span>
        <span class="flex-1 font-semibold text-sky-800 dark:text-sky-200">
            @if(isset($titre)){{ $titre }}@else Aide – Comment utiliser cette page @endif
        </span>
        <span class="text-sky-500 dark:text-sky-400" x-text="open ? '▼ Réduire' : '▶ Afficher l\'aide'"></span>
    </button>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" x-cloak
        class="mt-2 px-4 py-4 rounded-xl border border-sky-200/80 dark:border-sky-800/80 bg-white dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 text-sm leading-relaxed">
        @if(isset($items) && is_array($items))
            <ul class="space-y-3">
                @foreach($items as $item)
                <li>
                    @if(!empty($item['titre']))
                    <strong class="text-slate-800 dark:text-slate-200">{{ $item['titre'] }}</strong>
                    <span class="text-slate-600 dark:text-slate-400"> — </span>
                    @endif
                    {!! $item['texte'] ?? $item['contenu'] ?? '' !!}
                </li>
                @endforeach
            </ul>
        @else
            {{ $slot }}
        @endif
    </div>
</div>
