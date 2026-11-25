<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autentificare · M.E.S.H.</title>
    <link rel="stylesheet" href="{{ asset('css/acces_cont.css') }}">
    <script src="{{ asset('js/acces_cont.js') }}" defer></script>
</head>
<body>
    <div class="layout">
        <header class="brand">
            <span class="logo" aria-hidden="true">M</span>
            <div>
                <strong>M.E.S.H.</strong>
                <span>Mentenanță, Erori, Stocuri, Hală</span>
            </div>
        </header>

        <main class="card">
            <h1>Bine ai revenit!</h1>
            <p class="subtitle">Autentificare</p>

            <form data-kind="login" method="POST" action="{{ route('login') }}">
                @csrf

                <div class="field">
                    <label for="email">Adresă de e-mail</label>
                    <input id="email" name="email" type="email"
                           value="{{ old('email') }}"
                           placeholder="nume@exemplu.ro"
                           autocomplete="username" maxlength="254" required>
                </div>

                <div class="field">
                    <label for="password">Parolă</label>
                    <input id="password" name="password" type="password"
                           placeholder="Introdu parola"
                           autocomplete="current-password" required>
                </div>

                @error('email')
                    <p class="feedback" role="alert">{{ $message }}</p>
                @enderror

                <div class="options">
                    <label class="check" for="show-password">
                        <input id="show-password" type="checkbox"
                               data-passwords="password">
                        Arată parola
                    </label>
                    <a href="#">Am uitat parola</a>
                </div>

                <button class="primary" type="submit">
                    Autentificare <span aria-hidden="true">→</span>
                </button>
            </form>

            <div class="bottom">
                Nu ai cont? <a href="#">Creează cont</a>
            </div>
        </main>
    </div>
</body>
</html>