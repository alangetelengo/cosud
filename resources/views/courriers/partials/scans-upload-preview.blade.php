{{-- Aperçu local (blob) des fichiers sélectionnés avant enregistrement — Alpine --}}
@php
    $scansRequired = $scansRequired ?? false;
    $scansInputId = $scansInputId ?? 'fichier-scan';
    $scansLabel = $scansLabel ?? 'Choisir un ou plusieurs fichiers';
@endphp
<div
    class="space-y-4"
    x-data="scansUploadPreview(@js($scansRequired))"
    x-init="init()"
>
    <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-600 bg-slate-50/50 dark:bg-slate-900/30 px-4 py-8 cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/30 dark:hover:bg-emerald-900/10 transition-colors">
        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ $scansLabel }}
            @if($scansRequired)
            <span class="text-red-500">*</span>
            @endif
        </span>
        <span class="text-xs text-slate-500">PDF, JPG, PNG — max. 10 Mo par fichier</span>
        <input type="file" name="fichiers[]" accept=".pdf,.jpg,.jpeg,.png" multiple class="sr-only"
               id="{{ $scansInputId }}"
               x-ref="fileInput"
               @if($scansRequired) :required="files.length === 0" @endif
               @change="onSelect($event)">
    </label>
    <p class="text-xs text-slate-500 text-center" x-text="statusLabel()"></p>

    <div x-show="files.length > 0" x-cloak class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5">
        <template x-for="(item, index) in files" :key="item.key">
            <article class="rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 overflow-hidden flex flex-col">
                <div class="relative bg-slate-100 dark:bg-slate-950/50 h-28 sm:h-32 flex items-center justify-center">
                    <template x-if="item.type === 'image'">
                        <img :src="item.url" :alt="item.name" class="w-full h-full object-contain p-2">
                    </template>
                    <template x-if="item.type === 'pdf'">
                        <iframe :src="item.url" class="w-full h-full bg-white" :title="'Aperçu ' + item.name"></iframe>
                    </template>
                    <template x-if="item.type === 'other'">
                        <div class="text-xs text-slate-400 px-3 text-center">Aperçu indisponible</div>
                    </template>
                </div>
                <div class="px-3 py-2 border-t border-slate-200 dark:border-slate-600 flex items-center gap-2 min-w-0">
                    <span class="truncate text-xs font-medium text-slate-700 dark:text-slate-200 flex-1" x-text="item.name" :title="item.name"></span>
                    <button type="button" @click="removeAt(index)"
                            class="shrink-0 text-[11px] font-semibold text-red-600 dark:text-red-400 hover:underline">
                        Retirer
                    </button>
                </div>
            </article>
        </template>
    </div>
</div>
