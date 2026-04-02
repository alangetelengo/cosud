{{-- Footer Progcaisse : tous styles inline pour fiabilité --}}
<div class="footer" style="
    background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
    padding-left: {{ auth()->check() ? '250px' : '0' }};
    width: 100%;
    box-sizing: border-box;
    transition: padding-left 0.3s ease;
">
    <div class="copyright" style="padding: 1rem 1.5rem;">
        <p style="text-align: center; margin: 0; font-size: 0.875rem; color: #fff;">
            © {{ date('Y') }} — GED | Développée par l'Agence Congolaise des Systèmes d'Information (ACSI)
            <a href="https://acsi.cg/" target="_blank" rel="noopener" style="color: #c4b5fd; text-decoration: none; font-weight: 600;">ACSI</a>
        </p>
    </div>
</div>
@if(auth()->check())
<style>
/* Footer : padding réduit quand sidebar collapsed */
#main-wrapper.menu-toggle .footer { padding-left: 80px !important; }
</style>
@endif
