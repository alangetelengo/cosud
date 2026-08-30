<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - Connexion</title>
    @include('partials.favicon')
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #0b1320; color: #fff; min-height: 100vh; display: grid; place-items: center; }
        .shell { width: min(980px, 92vw); display: grid; grid-template-columns: 1fr 420px; background: #fff; color: #0f172a; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,.35); }
        .hero { background: radial-gradient(circle at 20% 80%, rgba(0,180,100,.35), transparent 45%), linear-gradient(135deg, #0a0f15, #111b24, #0a0f15); padding: 42px; position: relative; }
        .hero h1 { margin-top: 18px; font-size: 2.2rem; color: #f3f4f6; }
        .hero p { margin-top: 10px; color: #cbd5e1; line-height: 1.5; }
        .logo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 132px;
            height: 132px;
            background: #fff;
            padding: 12px;
            border-radius: 50%;
            border: 2px solid rgba(212,168,75,.7);
            box-sizing: border-box;
            overflow: hidden;
        }
        .logo-acsi {
            display: block;
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }
        .form { padding: 36px 30px; }
        .form h2 { font-size: 1.7rem; margin-bottom: 6px; }
        .muted { color: #64748b; margin-bottom: 18px; font-size: .95rem; }
        .group { margin-bottom: 14px; }
        label { display: block; margin-bottom: 6px; font-size: .85rem; font-weight: 700; color: #334155; }
        input[type="email"], input[type="password"] { width: 100%; border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px; font-size: .95rem; }
        input:focus { outline: none; border-color: #00a86b; box-shadow: 0 0 0 3px rgba(0,168,107,.15); }
        .remember { display: flex; align-items: center; gap: 8px; margin: 8px 0 16px; color: #475569; font-size: .9rem; }
        .btn { width: 100%; border: 0; border-radius: 10px; padding: 12px; background: linear-gradient(135deg, #00b464, #009d58); color: #fff; font-weight: 700; cursor: pointer; }
        .form-submit-spinner { display: inline-block; width: 1em; height: 1em; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: form-submit-spin .6s linear infinite; vertical-align: -.2em; margin-right: .35rem; }
        .form-submit-loading { opacity: .78; cursor: not-allowed; }
        @keyframes form-submit-spin { to { transform: rotate(360deg); } }
        .err { background: #fff1f2; border: 1px solid #fecdd3; color: #be123c; padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; font-size: .88rem; }
        @media (max-width: 860px) { .shell { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="shell">
        <section class="hero">
            <div class="logo-wrap">
                <img src="{{ asset('images/image-logo.jpg') }}" alt="ACSI" class="logo-acsi" width="108" height="108" decoding="async">
            </div>
            <h1>{{ config('app.name') }}</h1>
            <p>Courrier et Suivi des Dépenses</p>
            <p style="margin-top: 6px; font-size: 0.8rem; color: #94a3b8;">v{{ config('cosud.version') }}</p>
        </section>
        <section class="form">
            <h2>Connexion</h2>
            <p class="muted">Saisissez vos identifiants.</p>

            @if ($errors->any())
                <div class="err">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" data-loading-text="Connexion...">
                @csrf
                <div class="group">
                    <label for="email">Adresse email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <div class="group">
                    <label for="password">Mot de passe</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                </div>
                <label class="remember">
                    <input type="checkbox" name="remember" value="1">
                    Se souvenir de moi
                </label>
                <button type="submit" class="btn" data-loading-text="Connexion...">Se connecter</button>
            </form>
        </section>
    </div>
    <script>
        (function () {
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
                    btn.innerHTML = '<span class="form-submit-spinner"></span> ' + loadingText;
                } else {
                    btn.value = loadingText;
                }

                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
                btn.classList.add('form-submit-loading');
            }, true);
        })();
    </script>
</body>
</html>
