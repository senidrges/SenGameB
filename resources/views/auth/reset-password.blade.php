<x-auth-layout title="Nouveau mot de passe">
    <h1>Nouveau mot de passe</h1>
    <p>Choisissez un nouveau mot de passe pour votre compte LoveGames.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
        @error('email')
            <div class="error">{{ $message }}</div>
        @enderror

        <label for="password">Mot de passe</label>
        <input id="password" type="password" name="password" required minlength="6" autocomplete="new-password">
        @error('password')
            <div class="error">{{ $message }}</div>
        @enderror

        <label for="password_confirmation">Confirmer le mot de passe</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required minlength="6" autocomplete="new-password">

        <button type="submit">Enregistrer</button>
    </form>
</x-auth-layout>
