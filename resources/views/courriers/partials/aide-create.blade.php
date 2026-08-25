{{-- Aide contextuelle — création courrier --}}
<aside class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
    <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Aide à l’enregistrement</h2>
        <p class="text-xs text-slate-500 mt-0.5">
            {{ $sensCode === 'depart' ? 'Comment créer un courrier départ' : 'Comment enregistrer une arrivée' }}
        </p>
    </div>

    <div class="p-5 space-y-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
        @if($sensCode === 'arrivee')
        <div id="aide-arrivee-defaut">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400 mb-1.5">Objectif</h3>
                <p>Enregistrer le courrier au <strong class="font-medium text-slate-800 dark:text-slate-100">registre d’arrivée</strong> avec son scan, pour qu’il puisse ensuite être placé en parapheur et orienté.</p>
            </div>

            <ol class="mt-4 space-y-3 list-decimal list-inside marker:font-semibold marker:text-slate-400">
                <li>
                    <span class="font-semibold text-slate-800 dark:text-slate-100">Objet</span>
                    <p class="mt-1 pl-5 text-xs text-slate-500">Résumez clairement le contenu (utilisé pour la recherche et le suivi).</p>
                </li>
                <li>
                    <span class="font-semibold text-slate-800 dark:text-slate-100">Expéditeur &amp; contacts</span>
                    <p class="mt-1 pl-5 text-xs text-slate-500">Pour une <strong class="font-medium">demande</strong> (ex. stage) ou une <strong class="font-medium">facture</strong>, le téléphone est obligatoire afin d’envoyer SMS / mail à la validation.</p>
                </li>
                <li>
                    <span class="font-semibold text-slate-800 dark:text-slate-100">N° registre</span>
                    <p class="mt-1 pl-5 text-xs text-slate-500">Numéro du registre papier (ex. <strong class="font-medium">45/2026</strong> ou <strong class="font-medium">192/2026/DAF/SAGP</strong>) — saisie obligatoire.</p>
                </li>
                <li>
                    <span class="font-semibold text-slate-800 dark:text-slate-100">Référence</span>
                    <p class="mt-1 pl-5 text-xs text-slate-500">Optionnel : autre n° de document (facture fournisseur, etc.) pour retrouver le dossier et éviter les doublons.</p>
                </li>
                <li>
                    <span class="font-semibold text-slate-800 dark:text-slate-100">Scan</span>
                    <p class="mt-1 pl-5 text-xs text-slate-500">Obligatoire (PDF, JPG ou PNG, max. 10 Mo). Plusieurs pièces possibles.</p>
                </li>
            </ol>

            <div class="mt-4 rounded-xl border border-amber-200 dark:border-amber-800/60 bg-amber-50/80 dark:bg-amber-900/20 px-4 py-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300 mb-1">Après l’enregistrement</h3>
                <p class="text-xs text-amber-900/80 dark:text-amber-200/90">
                    Le courrier est créé au statut <strong class="font-medium">Reçu</strong>. Il pourra être mis en parapheur, puis orienté par le DG.
                </p>
            </div>
        </div>

        <div id="aide-arrivee-facture" class="hidden">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400 mb-1.5">Facture fournisseur</h3>
                <p>Trace COSUD d’une facture prestataire. Le système ne produit pas la facture : joignez le(s) scan(s).</p>
            </div>
            <ol class="mt-4 space-y-3 list-decimal list-inside marker:font-semibold marker:text-slate-400">
                <li><span class="font-semibold text-slate-800 dark:text-slate-100">Service demandeur</span><p class="mt-1 pl-5 text-xs text-slate-500">Direction à l’origine de la dépense.</p></li>
                <li><span class="font-semibold text-slate-800 dark:text-slate-100">Fournisseur</span><p class="mt-1 pl-5 text-xs text-slate-500">Raison sociale + <strong class="font-medium">téléphone obligatoire</strong> (SMS / mail à la signature du chèque).</p></li>
                <li><span class="font-semibold text-slate-800 dark:text-slate-100">Référence facture</span><p class="mt-1 pl-5 text-xs text-slate-500">N° figurant sur la pièce (en plus du n° registre secrétariat).</p></li>
                <li><span class="font-semibold text-slate-800 dark:text-slate-100">Scans</span><p class="mt-1 pl-5 text-xs text-slate-500">Facture BPA, annexes…</p></li>
            </ol>
            <div class="mt-4 rounded-xl border border-amber-200 dark:border-amber-800/60 bg-amber-50/80 dark:bg-amber-900/20 px-4 py-3">
                <p class="text-xs text-amber-900/80 dark:text-amber-200/90">Circuit paiement : DG → AC → décharge → suivi dépenses. Mme Taty classe / suit en parallèle.</p>
            </div>
        </div>

        <div id="aide-arrivee-mad" class="hidden">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400 mb-1.5">MAD / alimentation de caisse</h3>
                <p>Trace d’une mise à disposition (ex. caisse menues dépenses PNR). Joignez état de besoins, lettre DG, chèque, mandat, pièce d’identité…</p>
            </div>
            <ol class="mt-4 space-y-3 list-decimal list-inside marker:font-semibold marker:text-slate-400">
                <li><span class="font-semibold text-slate-800 dark:text-slate-100">Service demandeur</span><p class="mt-1 pl-5 text-xs text-slate-500">Direction / service à l’origine (ex. SAGP, DD PNR).</p></li>
                <li><span class="font-semibold text-slate-800 dark:text-slate-100">Émetteur</span><p class="mt-1 pl-5 text-xs text-slate-500">Service interne émetteur (pas un fournisseur externe).</p></li>
                <li><span class="font-semibold text-slate-800 dark:text-slate-100">N° registre</span><p class="mt-1 pl-5 text-xs text-slate-500">N° porté au registre (ex. 192/2026/DAF/SAGP).</p></li>
                <li><span class="font-semibold text-slate-800 dark:text-slate-100">Référence</span><p class="mt-1 pl-5 text-xs text-slate-500">Optionnel si déjà saisi comme n° registre.</p></li>
                <li><span class="font-semibold text-slate-800 dark:text-slate-100">Scans</span><p class="mt-1 pl-5 text-xs text-slate-500">Toutes les pièces du dossier — COSUD ne les produit pas.</p></li>
            </ol>
            <div class="mt-4 rounded-xl border border-amber-200 dark:border-amber-800/60 bg-amber-50/80 dark:bg-amber-900/20 px-4 py-3">
                <p class="text-xs text-amber-900/80 dark:text-amber-200/90">Même circuit que les factures. Mme Eleni intervient <strong class="font-medium">après la décharge AC</strong>.</p>
            </div>
        </div>
        @else
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400 mb-1.5">Objectif</h3>
            <p>Créer un <strong class="font-medium text-slate-800 dark:text-slate-100">brouillon de départ</strong> à soumettre ensuite au directeur de votre direction pour signature.</p>
        </div>

        <ol class="space-y-3 list-decimal list-inside marker:font-semibold marker:text-slate-400">
            <li>
                <span class="font-semibold text-slate-800 dark:text-slate-100">Objet</span>
                <p class="mt-1 pl-5 text-xs text-slate-500">Décrivez le motif du départ interne.</p>
            </li>
            <li>
                <span class="font-semibold text-slate-800 dark:text-slate-100">Pièces du parapheur</span>
                <p class="mt-1 pl-5 text-xs text-slate-500">Joignez les documents déjà prêts, ou déposez de nouvelles pièces (rangées dans Mes dossiers → Courriers départ).</p>
            </li>
            <li>
                <span class="font-semibold text-slate-800 dark:text-slate-100">Destinataire</span>
                <p class="mt-1 pl-5 text-xs text-slate-500">Le secrétariat destinataire se choisit <strong class="font-medium">après</strong> la validation du directeur.</p>
            </li>
        </ol>

        <div class="rounded-xl border border-sky-200 dark:border-sky-800/60 bg-sky-50/80 dark:bg-sky-900/20 px-4 py-3">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-300 mb-1">Parcours</h3>
            <p class="text-xs text-sky-900/80 dark:text-sky-200/90">
                Brouillon → transmission au directeur → signature → expédition vers le secrétariat destinataire → réception.
            </p>
        </div>
        @endif
    </div>
</aside>
