{{-- Spinner bouton submit (même logique que la page de connexion) --}}
<style>
.form-submit-spinner {
    display: inline-block;
    width: 1em;
    height: 1em;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: form-submit-spin .6s linear infinite;
    vertical-align: -.2em;
    margin-right: .35rem;
}
.form-submit-loading { opacity: .78; cursor: not-allowed; }
@keyframes form-submit-spin { to { transform: rotate(360deg); } }
</style>
<script>
(function () {
    if (window.__gedSubmitLoadingBound) return;
    window.__gedSubmitLoadingBound = true;
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.skipSubmitLoading === '1') return;

        var submitter = event.submitter;
        var btn = submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement
            ? submitter
            : form.querySelector('button[type="submit"]:not([disabled]), input[type="submit"]:not([disabled])');
        if (!btn) return;
        if (btn.dataset.loading === '1') return;

        btn.dataset.loading = '1';
        if (!btn.dataset.originalHtml && btn instanceof HTMLButtonElement) {
            btn.dataset.originalHtml = btn.innerHTML;
        }

        var loadingText = btn.dataset.loadingText || form.dataset.loadingText || 'Chargement...';
        if (btn instanceof HTMLButtonElement) {
            btn.innerHTML = '<span class="form-submit-spinner" aria-hidden="true"></span> ' + loadingText;
        } else {
            btn.value = loadingText;
        }

        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.classList.add('form-submit-loading');
    }, true);
})();
</script>
