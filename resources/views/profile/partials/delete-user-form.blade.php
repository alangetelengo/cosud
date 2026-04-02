<section class="space-y-5">
    <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
        La suppression de votre compte est <strong class="font-semibold text-red-800 dark:text-red-300">définitive</strong> : vos données et ressources associées seront effacées de manière irréversible. Avant de continuer, exportez ou sauvegardez tout ce que vous souhaitez conserver.
    </p>

    <x-danger-button
        class="rounded-xl"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Supprimer mon compte</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 sm:p-8">
            @csrf
            @method('delete')

            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 mb-4" aria-hidden="true">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>

            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                Confirmer la suppression du compte ?
            </h2>

            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                Cette action est irréversible. Saisissez votre mot de passe pour confirmer que vous souhaitez supprimer définitivement votre compte et les données associées.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Mot de passe" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full sm:w-3/4 rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-900"
                    placeholder="Votre mot de passe"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-secondary-button type="button" class="rounded-xl" x-on:click="$dispatch('close')">
                    Annuler
                </x-secondary-button>

                <x-danger-button class="rounded-xl">
                    Supprimer définitivement
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
