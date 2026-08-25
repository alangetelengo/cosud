<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - Vérification 2FA</title>
    @include('partials.favicon')
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { height: 100%; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; overflow: hidden; background: #ffffff; }

        .login-wrapper { display: flex; height: 100vh; position: relative; }

        .particles { position: absolute; width: 100%; height: 100%; overflow: hidden; z-index: 0; }
        .particle { position: absolute; border-radius: 50%; animation: float 20s infinite ease-in-out; }
        .particle:nth-child(1) { width: 5px; height: 5px; top: 20%; left: 10%; background: radial-gradient(circle, rgba(0, 220, 120, 0.7), rgba(0, 180, 100, 0.3) 50%, transparent 70%); box-shadow: 0 0 12px rgba(0, 200, 110, 0.6); animation-delay: 0s; }
        .particle:nth-child(2) { width: 6px; height: 6px; top: 60%; left: 80%; background: radial-gradient(circle, rgba(0, 200, 110, 0.6), rgba(100, 160, 130, 0.3) 50%, transparent 70%); box-shadow: 0 0 15px rgba(0, 180, 100, 0.5); animation-delay: 2s; }
        .particle:nth-child(3) { width: 4px; height: 4px; top: 40%; left: 30%; background: radial-gradient(circle, rgba(0, 240, 130, 0.8), rgba(0, 200, 110, 0.4) 50%, transparent 70%); box-shadow: 0 0 10px rgba(0, 220, 120, 0.7); animation-delay: 4s; }
        .particle:nth-child(4) { width: 5px; height: 5px; top: 80%; left: 60%; background: radial-gradient(circle, rgba(120, 180, 150, 0.6), rgba(160, 190, 170, 0.3) 50%, transparent 70%); box-shadow: 0 0 12px rgba(100, 170, 140, 0.5); animation-delay: 6s; }
        .particle:nth-child(5) { width: 5px; height: 5px; top: 30%; left: 70%; background: radial-gradient(circle, rgba(0, 190, 105, 0.65), rgba(80, 160, 130, 0.3) 50%, transparent 70%); box-shadow: 0 0 13px rgba(0, 180, 100, 0.55); animation-delay: 8s; }
        @keyframes float { 0%, 100% { transform: translateY(0) translateX(0); opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 50% { transform: translateY(-100px) translateX(50px); } }

        .visual-section { flex: 1; position: relative; background: radial-gradient(ellipse at top left, rgba(30, 40, 50, 0.6) 0%, transparent 50%), radial-gradient(ellipse at bottom right, rgba(20, 30, 40, 0.5) 0%, transparent 50%), linear-gradient(135deg, #0a0f15 0%, #151e28 25%, #1a2633 50%, #151e28 75%, #0a0f15 100%); overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .visual-section::before { content: ""; position: absolute; inset: 0; background: radial-gradient(ellipse at 20% 80%, rgba(0, 180, 100, 0.35) 0%, rgba(0, 180, 100, 0.15) 25%, transparent 50%), radial-gradient(ellipse at 80% 60%, rgba(0, 160, 90, 0.25) 0%, rgba(0, 140, 80, 0.12) 30%, transparent 55%), radial-gradient(ellipse at 40% 40%, rgba(0, 200, 110, 0.2) 0%, rgba(0, 150, 85, 0.08) 35%, transparent 60%), radial-gradient(ellipse at 70% 20%, rgba(100, 120, 110, 0.15) 0%, transparent 45%); animation: smokeMove 25s ease-in-out infinite; mix-blend-mode: screen; }
        @keyframes smokeMove { 0%, 100% { transform: translate(0, 0) scale(1) rotate(0deg); opacity: 0.7; } 25% { transform: translate(40px, -30px) scale(1.08) rotate(2deg); opacity: 0.85; } 50% { transform: translate(-30px, 40px) scale(0.95) rotate(-2deg); opacity: 0.75; } 75% { transform: translate(30px, 25px) scale(1.05) rotate(1deg); opacity: 0.8; } }
        .visual-section::after { content: ""; position: absolute; bottom: 0; left: 0; right: 0; height: 75%; background: linear-gradient(to top, rgba(0, 180, 100, 0.3) 0%, rgba(0, 160, 90, 0.22) 15%, rgba(0, 140, 80, 0.15) 30%, rgba(100, 140, 120, 0.08) 50%, rgba(150, 170, 160, 0.04) 70%, transparent 100%); animation: fogMove 18s ease-in-out infinite; filter: blur(45px); mix-blend-mode: screen; }
        @keyframes fogMove { 0%, 100% { transform: translateX(0) translateY(0); opacity: 0.7; } 33% { transform: translateX(60px) translateY(-20px); opacity: 0.85; } 66% { transform: translateX(-40px) translateY(-10px); opacity: 0.75; } }
        .grid-overlay { position: absolute; inset: 0; background-image: linear-gradient(rgba(100, 120, 140, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(100, 120, 140, 0.03) 1px, transparent 1px); background-size: 50px 50px; z-index: 1; }
        .brand-content { position: relative; text-align: center; z-index: 3; width: 90%; max-width: 600px; }
        .brand-content::before { content: ""; position: absolute; top: 50%; left: 50%; width: 600px; height: 600px; transform: translate(-50%, -50%); background: radial-gradient(circle, rgba(0, 200, 110, 0.25) 0%, rgba(0, 180, 100, 0.18) 20%, rgba(0, 160, 90, 0.12) 35%, rgba(80, 120, 100, 0.06) 50%, transparent 70%); filter: blur(60px); animation: centerSmoke 20s ease-in-out infinite; pointer-events: none; z-index: -1; }
        @keyframes centerSmoke { 0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.6; } 50% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.8; } }
        .logo-container { position: relative; display: inline-block; margin-bottom: 3rem; }
        .logo-glow { position: absolute; inset: -40px; background: radial-gradient(circle, rgba(0, 180, 100, 0.25), rgba(0, 180, 100, 0.12) 40%, transparent 70%); filter: blur(50px); animation: pulseGlow 3s ease-in-out infinite; }
        @keyframes pulseGlow { 0%, 100% { opacity: 0.5; transform: scale(1); } 50% { opacity: 0.8; transform: scale(1.1); } }
        .logo-acsi { position: relative; width: 150px; height: auto; filter: drop-shadow(0 0 15px rgba(0, 180, 100, 0.4)) drop-shadow(0 4px 20px rgba(0, 0, 0, 0.5)); animation: fadeInRotate 1.2s ease; }
        @keyframes fadeInRotate { from { opacity: 0; transform: scale(0.8) rotate(-5deg); } to { opacity: 1; transform: scale(1) rotate(0); } }
        .brand-title { font-size: 4.5rem; font-weight: 900; letter-spacing: 8px; margin-bottom: 1rem; position: relative; display: inline-block; background: linear-gradient(135deg, #ffffff 0%, #e0e7ee 25%, #00b464 45%, #e0e7ee 65%, #ffffff 100%); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; animation: shimmer 5s linear infinite, slideUp 1s ease 0.3s both; filter: drop-shadow(0 2px 15px rgba(0, 180, 100, 0.3)); }
        @keyframes shimmer { to { background-position: 200% center; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        .brand-subtitle { font-size: 1.1rem; color: #b8c5d6; letter-spacing: 2px; font-weight: 400; animation: slideUp 1s ease 0.6s both; line-height: 1.8; max-width: 500px; margin: 0 auto; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.4); }
        .decorative-line { width: 120px; height: 4px; background: linear-gradient(90deg, transparent 0%, #00b464 20%, #FFD700 50%, #00b464 80%, transparent 100%); margin: 2rem auto; border-radius: 2px; animation: slideUp 1s ease 0.9s both, lineGlow 2s ease-in-out infinite; box-shadow: 0 0 10px rgba(0, 180, 100, 0.4); }
        @keyframes lineGlow { 0%, 100% { opacity: 0.7; } 50% { opacity: 1; } }
        .form-section { flex: 0 0 520px; background: #ffffff; display: flex; align-items: center; justify-content: center; padding: 3rem; position: relative; z-index: 2; box-shadow: -10px 0 40px rgba(0, 0, 0, 0.08); }
        .form-section::before { content: ""; position: absolute; top: 0; left: 0; width: 3px; height: 100%; background: linear-gradient(180deg, transparent 0%, #00b464 20%, #FFD700 50%, #00b464 80%, transparent 100%); box-shadow: 0 0 15px rgba(0, 180, 100, 0.5); animation: borderPulse 3s ease-in-out infinite; }
        @keyframes borderPulse { 0%, 100% { opacity: 0.7; } 50% { opacity: 1; box-shadow: 0 0 20px rgba(0, 180, 100, 0.6); } }
        .auth-container { width: 100%; max-width: 400px; animation: slideInRight 1s ease; }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(60px); } to { opacity: 1; transform: translateX(0); } }
        .auth-header { margin-bottom: 3rem; }
        .auth-header h2 { font-size: 2.2rem; color: #1e293b; font-weight: 700; margin-bottom: 0.8rem; letter-spacing: 0.5px; }
        .auth-header p { color: #64748b; font-size: 0.95rem; letter-spacing: 0.3px; }
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 1.8rem; font-size: 0.9rem; border-left: 4px solid; }
        .alert-danger { background: rgba(220, 20, 60, 0.08); border-color: #DC143C; color: #DC143C; }
        .alert-success { background: rgba(0, 128, 55, 0.08); border-color: #008037; color: #008037; }
        .input-group { margin-bottom: 1.8rem; position: relative; }
        .input-group label { display: block; color: #475569; font-size: 0.85rem; margin-bottom: 0.6rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
        .form-input { width: 100%; padding: 16px 20px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 12px; color: #1e293b; font-size: 1rem; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); outline: none; }
        .form-input::placeholder { color: #94a3b8; }
        .form-input:focus { background: #ffffff; border-color: #008037; box-shadow: 0 0 0 4px rgba(0, 128, 55, 0.1); transform: translateY(-2px); }
        .btn-submit { width: 100%; padding: 16px; margin-top: 0.5rem; border: none; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; text-transform: uppercase; letter-spacing: 2px; position: relative; overflow: hidden; background: linear-gradient(135deg, #008037 0%, #00a044 100%); color: #ffffff; box-shadow: 0 8px 20px rgba(0, 128, 55, 0.3); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .auth-spinner { display: inline-block; width: 1em; height: 1em; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: auth-spin 0.6s linear infinite; vertical-align: -0.2em; margin-right: 0.35rem; }
        @keyframes auth-spin { to { transform: rotate(360deg); } }
        .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }
        .btn-submit:hover { background: linear-gradient(135deg, #00a044 0%, #008037 100%); box-shadow: 0 12px 30px rgba(0, 128, 55, 0.4); transform: translateY(-3px); }
        .form-footer { display: flex; flex-wrap: wrap; flex-direction: column; gap: 0.8rem; margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #f1f5f9; align-items: center; text-align: center; }
        .form-link { color: #64748b; text-decoration: none; font-size: 0.88rem; transition: all 0.3s ease; font-weight: 500; }
        .form-link:hover { color: #008037; }
        .btn-link { background: none; border: none; color: #008037; cursor: pointer; font-size: 0.9rem; padding: 0; }
        .btn-link:hover { text-decoration: underline; }
        .acsi-badge { position: absolute; bottom: 2rem; left: 50%; transform: translateX(-50%); text-align: center; color: #8a9db3; font-size: 0.75rem; letter-spacing: 1px; z-index: 4; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5); }
        .acsi-badge strong { color: #b8c5d6; font-weight: 600; }
        .modal-2fa { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 9999; align-items: center; justify-content: center; padding: 2rem; }
        .modal-2fa.show { display: flex; }
        .modal-2fa-content { background: #fff; border-radius: 16px; padding: 2rem; max-width: 420px; width: 100%; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35); border: 1px solid rgba(255, 255, 255, 0.2); }
        .modal-2fa-header { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .modal-2fa-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; }
        @media (max-width: 968px) { .login-wrapper { flex-direction: column; } .visual-section { flex: 0 0 320px; } .brand-title { font-size: 3rem; letter-spacing: 6px; } .form-section { flex: 1; padding: 2rem 1.5rem; } .logo-acsi { width: 140px; } }
        @media (max-width: 480px) { .brand-title { font-size: 2.5rem; letter-spacing: 4px; } .auth-header h2 { font-size: 1.8rem; } .brand-subtitle { font-size: 0.95rem; } }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>

        <div class="visual-section">
            <div class="grid-overlay"></div>
            <div class="brand-content">
                <div class="logo-container">
                    <div class="logo-glow"></div>
                    <svg class="logo-acsi" viewBox="0 0 400 150" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="75" cy="75" r="60" fill="none" stroke="#00b464" stroke-width="2" opacity="0.3"><animate attributeName="opacity" values="0.3;0.5;0.3" dur="3s" repeatCount="indefinite" /></circle>
                        <circle cx="75" cy="75" r="55" fill="none" stroke="#FFD700" stroke-width="1.5" opacity="0.25"><animate attributeName="opacity" values="0.25;0.45;0.25" dur="3s" begin="0.5s" repeatCount="indefinite" /></circle>
                        <circle cx="75" cy="75" r="50" fill="none" stroke="#DC143C" stroke-width="1.5" opacity="0.2"><animate attributeName="opacity" values="0.2;0.4;0.2" dur="3s" begin="1s" repeatCount="indefinite" /></circle>
                        <path d="M 35 75 Q 35 35, 75 35" fill="none" stroke="#008037" stroke-width="6" stroke-linecap="round" filter="url(#softGlow)" />
                        <path d="M 75 115 Q 55 115, 45 95" fill="none" stroke="#FFD700" stroke-width="6" stroke-linecap="round" filter="url(#softGlow)" />
                        <path d="M 115 75 Q 115 115, 75 115" fill="none" stroke="#DC143C" stroke-width="6" stroke-linecap="round" filter="url(#softGlow)" />
                        <path d="M 60 95 L 75 55 L 90 95 M 67 80 L 83 80" stroke="#ffffff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" fill="none" opacity="0.9" filter="url(#softGlow)" />
                        <text x="160" y="65" font-family="Arial, sans-serif" font-size="48" font-weight="900" fill="#ffffff" letter-spacing="2" filter="url(#textGlow)">ACSI</text>
                        <text x="160" y="88" font-family="Arial, sans-serif" font-size="11" fill="#b8c5d6" letter-spacing="0.5">Agence Congolaise des</text>
                        <text x="160" y="102" font-family="Arial, sans-serif" font-size="11" fill="#b8c5d6" letter-spacing="0.5">Systèmes d'Information</text>
                        <defs>
                            <filter id="softGlow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="2" result="coloredBlur" /><feMerge><feMergeNode in="coloredBlur" /><feMergeNode in="SourceGraphic" /></feMerge></filter>
                            <filter id="textGlow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="2.5" result="coloredBlur" /><feMerge><feMergeNode in="coloredBlur" /><feMergeNode in="SourceGraphic" /></feMerge></filter>
                        </defs>
                    </svg>
                </div>
                <h1 class="brand-title">{{ config('app.name') }}</h1>
                <div class="decorative-line"></div>
                <p class="brand-subtitle">Courrier et Suivi des Dépenses</p>
            </div>
            <div class="acsi-badge">Propulsé par <strong>ACSI</strong></div>
        </div>

        <div class="form-section">
            <div class="auth-container">
                <div class="auth-header">
                    <h2>Vérification 2FA</h2>
                    <p>Entrez le code à 6 chiffres de votre application d'authentification</p>
                </div>

                @php $mainErrorMessages = collect($errors->messages())->except('recovery_code')->flatten(); @endphp
                @if ($mainErrorMessages->isNotEmpty())
                    <div class="alert alert-danger">
                        @foreach ($mainErrorMessages as $error)
                            {{ $error }}<br>
                        @endforeach
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form id="form-two-factor-verify" method="POST" action="{{ route('two-factor.verify') }}" autocomplete="off">
                    @csrf
                    <div class="input-group">
                        <label for="code">Code de vérification</label>
                        <input type="text" id="code" name="code" class="form-input" placeholder="000000"
                               maxlength="6" pattern="[0-9]{6}"
                               style="font-size:1.5em;letter-spacing:0.3em;text-align:center;font-weight:bold"
                               autocomplete="one-time-code" autofocus required>
                    </div>
                    <button type="submit" id="btn-verify-2fa" class="btn-submit">🔐 Vérifier et Se Connecter</button>
                </form>

                <div class="form-footer">
                    <button type="button" class="btn-link" onclick="document.getElementById('recoveryModal').classList.add('show')">
                        🔑 Utiliser un code de récupération
                    </button>
                    <a href="{{ route('two-factor.cancel') }}" class="form-link">← Retour à la connexion</a>
                </div>

                {{-- Modal Code de Récupération --}}
                <div id="recoveryModal" class="modal-2fa">
                    <div class="modal-2fa-content">
                        <div class="modal-2fa-header">
                            <span>🔑 Code de Récupération</span>
                            <button type="button" class="modal-2fa-close" onclick="document.getElementById('recoveryModal').classList.remove('show')">&times;</button>
                        </div>
                        <form method="POST" action="{{ route('two-factor.verify-recovery') }}" autocomplete="off">
                            @csrf
                            <div class="alert" style="background:rgba(59,130,246,0.1);border-color:#3b82f6;color:#1e40af;margin-bottom:1rem;">
                                <small>Entrez l'un de vos codes de récupération à 8 caractères que vous avez sauvegardés lors de l'activation de la 2FA.</small>
                            </div>
                            @error('recovery_code')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror
                            <div class="input-group">
                                <label for="recovery_code">Code de Récupération</label>
                                <input type="text" id="recovery_code" name="recovery_code" class="form-input"
                                       value="{{ old('recovery_code') }}"
                                       placeholder="XXXXXXXX" maxlength="8"
                                       style="font-size:1.5em;letter-spacing:0.3em;text-align:center;text-transform:uppercase;font-weight:600"
                                       autocomplete="off" required>
                                <small style="color:#64748b;margin-top:0.5rem;display:block;">
                                    ⚠ Ce code ne pourra plus être utilisé après cette connexion
                                </small>
                            </div>
                            <div style="display:flex;gap:0.75rem;margin-top:1rem;">
                                <button type="button" class="btn-submit" style="background:#64748b;flex:1" onclick="document.getElementById('recoveryModal').classList.remove('show')">Annuler</button>
                                <button type="submit" class="btn-submit" style="flex:1">Vérifier</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

<script>
document.getElementById('form-two-factor-verify').addEventListener('submit', function() {
    var btn = document.getElementById('btn-verify-2fa');
    btn.disabled = true;
    btn.innerHTML = '<span class="auth-spinner"></span> Vérification...';
});
document.querySelector('input[name="code"]').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
});
document.getElementById('recovery_code').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
});
document.getElementById('recoveryModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('show');
});
@if ($errors->has('recovery_code'))
document.getElementById('recoveryModal').classList.add('show');
@endif
</script>

</html>
