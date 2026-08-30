@extends('layouts.app')
@section('content-container-class', 'w-full max-w-none px-4 sm:px-6 lg:px-8')

@section('page-title', 'Notifications')
@section('page-title-info', 'Liste de vos notifications')

@section('btn-create')
    @php $unreadCount = $notifications->where('read_at', null)->count(); @endphp
    @if($unreadCount > 0)
    <form action="{{ route('notifications.read-all') }}" method="POST" class="inline">
        @csrf
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 font-semibold hover:bg-emerald-200/80 dark:hover:bg-emerald-800/40 transition-all border border-emerald-200/60 dark:border-emerald-700/50">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            Tout marquer comme lu
        </button>
    </form>
    @endif
@endsection

@section('content')
@if(session('success'))
<div x-data="{ show: true }" x-show="show" x-transition class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-center gap-3">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
    <span class="flex-1">{{ session('success') }}</span>
    <button type="button" @click="show = false" class="flex-shrink-0 w-8 h-8 rounded-lg hover:bg-emerald-200/50 dark:hover:bg-emerald-800/30 flex items-center justify-center text-lg font-bold transition-colors" title="Fermer">×</button>
</div>
@endif

<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg shadow-slate-200/30 dark:shadow-none border border-slate-100 dark:border-slate-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-600 bg-slate-50/50 dark:bg-slate-800/50">
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Liste des notifications</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50 dark:bg-slate-700/70 border-b border-slate-200 dark:border-slate-600">
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase w-12">#</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Message</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase hidden md:table-cell w-32">Type</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase w-36">Date</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase w-24">Statut</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase w-40">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($notifications as $n)
                @php
                    $data = $n->data ?? [];
                    $message = $data['message'] ?? $data['message_title'] ?? $data['subject'] ?? class_basename($n->type);
                    $url = $data['url'] ?? null;
                    $typeLabel = str_contains($n->type, 'DocumentDepose') ? 'Document déposé' : 'Notification';
                    $typeBadge = 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300';
                @endphp
                <tr class="{{ $loop->odd ? 'bg-emerald-100 hover:bg-emerald-200/80 dark:bg-emerald-900/30 dark:hover:bg-emerald-900/45' : 'bg-amber-50 hover:bg-amber-100/80 dark:bg-amber-900/20 dark:hover:bg-amber-900/35' }} transition-colors">
                    <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">{{ $loop->iteration + ($notifications->currentPage() - 1) * $notifications->perPage() }}</td>
                    <td class="px-6 py-4">
                        <div class="font-medium text-slate-800 dark:text-slate-200">{{ is_string($message) ? $message : json_encode($message) }}</div>
                    </td>
                    <td class="px-6 py-4 hidden md:table-cell">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-medium {{ $typeBadge }}">{{ $typeLabel }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ $n->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-6 py-4">
                        @if($n->read_at)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-600 text-slate-700 dark:text-slate-300">Lu</span>
                        @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">Non lu</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-2">
                            @if(!$n->read_at)
                            <a href="{{ route('notifications.read', $n->id) }}?back=1" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-700/50 hover:bg-emerald-200 dark:hover:bg-emerald-800/40 transition-colors no-underline" title="Marquer comme lu">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                Lu
                            </a>
                            @endif
                            @if($url)
                            <a href="{{ $url }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-white/80 dark:bg-slate-600 text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-slate-600 hover:bg-white dark:hover:bg-slate-500 transition-colors" title="Voir">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                Voir
                            </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-20 text-center">
                        <span class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-slate-100 dark:bg-slate-700/80 text-slate-400 dark:text-slate-500 mb-5">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                        </span>
                        <p class="text-slate-700 dark:text-slate-300 font-semibold text-lg">Aucune notification</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 max-w-sm mx-auto">Vous serez notifié des événements importants : nouveaux documents déposés, partages de dossiers, etc.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($notifications->hasPages())
    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600 bg-slate-50/50 dark:bg-slate-800/30">
        {{ $notifications->links() }}
    </div>
    @endif
</div>
@endsection
