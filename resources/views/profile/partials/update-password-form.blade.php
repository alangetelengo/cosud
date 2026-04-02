<section>
    <form method="post" action="{{ route('password.update') }}" class="space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" value="Mot de passe actuel" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-900 focus:!border-emerald-500 focus:!ring-emerald-500" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" value="Nouveau mot de passe" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-900 focus:!border-emerald-500 focus:!ring-emerald-500" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" value="Confirmer le mot de passe" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-900 focus:!border-emerald-500 focus:!ring-emerald-500" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <p class="text-xs text-slate-500 dark:text-slate-400">Utilisez un mot de passe long et difficile à deviner.</p>

        <div class="flex items-center gap-4 pt-1">
            <x-primary-button class="rounded-xl !normal-case !tracking-normal !text-sm !px-6 !py-2.5 !bg-emerald-600 hover:!bg-emerald-700 focus:!ring-emerald-500 !border-transparent">Enregistrer</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-emerald-600 dark:text-emerald-400"
                >Enregistré.</p>
            @endif
        </div>
    </form>
</section>
