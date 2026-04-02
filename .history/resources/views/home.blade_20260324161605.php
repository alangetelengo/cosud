@extends('layouts.app')

@section('page-title', 'Tableau de bord')
@section('page-title-info', 'Vue d\'ensemble du système de gestion électronique des documents')

@section('content')
@php
    $nbDocuments = \App\Models\Document::count();
    $nbDossiers = \App\Models\Dossier::where('actif', true)->count();
    $nbTypes = \App\Models\TypeDocument::count();
    $nbUsers = \App\Models\User::count();
@endphp
<div class="space-y-8">
    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <a href="{{ route('documents.index') }}" class="block bg-white dark:bg-slate-800 rounded-xl p-6 shadow-md border border-slate-100 dark:border-slate-700 hover:shadow-lg hover:-translate-y-1 transition-all">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase">Documents</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{{ $nbDocuments }}</p>
                </div>
                <span class="text-4xl opacity-20">📄</span>
            </div>
        </a>
        <a href="{{ route('dossiers.index') }}" class="block bg-white dark:bg-slate-800 rounded-xl p-6 shadow-md border border-slate-100 dark:border-slate-700 hover:shadow-lg hover:-translate-y-1 transition-all">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase">Dossiers</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{{ $nbDossiers }}</p>
                </div>
                <span class="text-4xl opacity-20">📂</span>
            </div>
        </a>
        <a href="{{ route('types-documents.index') }}" class="block bg-white dark:bg-slate-800 rounded-xl p-6 shadow-md border border-slate-100 dark:border-slate-700 hover:shadow-lg hover:-translate-y-1 transition-all">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase">Types</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{{ $nbTypes }}</p>
                </div>
                <span class="text-4xl opacity-20">📋</span>
            </div>
        </a>
        <a href="{{ route('utilisateurs.index') }}" class="block bg-white dark:bg-slate-800 rounded-xl p-6 shadow-md border border-slate-100 dark:border-slate-700 hover:shadow-lg hover:-translate-y-1 transition-all">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase">Utilisateurs</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{{ $nbUsers }}</p>
                </div>
                <span class="text-4xl opacity-20">👤</span>
            </div>
        </a>
    </div>

    {{-- Zone accueil --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl p-8 shadow-md border border-slate-100 dark:border-slate-700">
        <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-4">Bienvenue sur GED</h2>
        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
            GED est le système de <strong>Gestion Électronique des Documents</strong> développé par l'Agence Congolaise des Systèmes d'Information (ACSI).
        </p>
        <p class="text-slate-600 dark:text-slate-300 leading-relaxed mt-4">
            Utilisez le menu à gauche pour naviguer dans les différentes sections : Documents, Dossiers, Types de documents, Recherche et Administration.
        </p>
    </div>
</div>
@endsection
