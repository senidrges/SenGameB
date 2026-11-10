<x-public-layout title="Rejoindre un groupe LoveGames">
            <h1>Invitation à rejoindre un groupe</h1>
            <p>On vous invite à rejoindre un groupe de discussion sur LoveGames.</p>

            <p>
                <a href="lovegames://join/{{ $code }}"><strong>Ouvrir l'invitation dans LoveGames</strong></a>
            </p>

            <p>
                Vous n'avez pas encore l'application ? Installez LoveGames depuis le Play Store, connectez-vous,
                puis touchez à nouveau le lien d'invitation.
            </p>

            <script>
                // Tente d'ouvrir directement l'application
                window.location.href = 'lovegames://join/{{ $code }}';
            </script>
</x-public-layout>
