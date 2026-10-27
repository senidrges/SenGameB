<x-public-layout title="Supprimer votre compte">
            <h1>Supprimer votre compte LoveGames</h1>
            <p class="updated">Application LoveGames, éditée par Seninova Group</p>

            <h2>Depuis l'application (immédiat)</h2>
            <ol>
                <li>Ouvrez LoveGames et connectez-vous.</li>
                <li>Allez dans l'onglet <strong>Profil</strong>.</li>
                <li>Touchez <strong>Supprimer mon compte</strong> en bas de l'écran.</li>
                <li>Confirmez avec votre mot de passe.</li>
            </ol>
            <p>Votre compte est supprimé immédiatement et définitivement.</p>

            <h2>Sans l'application</h2>
            <p>
                Si vous n'avez plus accès à l'application, écrivez à
                <a href="mailto:{{ $contactEmail }}?subject=Suppression%20de%20mon%20compte%20LoveGames">{{ $contactEmail }}</a>
                depuis l'adresse e-mail de votre compte, avec pour objet « Suppression de mon compte LoveGames »
                et en indiquant votre pseudo. Nous supprimons le compte sous 30 jours au plus et vous le confirmons
                par e-mail.
            </p>

            <h2>Données supprimées</h2>
            <ul>
                <li>Votre compte : e-mail, mot de passe, numéro de téléphone, prénom, nom, pseudo, statut et photo de profil.</li>
                <li>Les messages, photos, messages vocaux et fichiers que vous avez envoyés.</li>
                <li>Les jeux que vous avez créés, vos choix et vos messages dans les jeux.</li>
                <li>Vos blocages, vos signalements, vos préférences (conversations archivées ou verrouillées) et votre jeton de notification.</li>
                <li>Toutes vos sessions de connexion, sur tous vos appareils.</li>
            </ul>

            <h2>Données conservées</h2>
            <ul>
                <li>
                    Une copie d'un message que vous avez envoyé et que votre interlocuteur a lui-même transféré
                    reste dans les conversations de celui-ci.
                </li>
                <li>
                    Les journaux techniques du serveur (adresse IP, date des requêtes) sont conservés pour une durée
                    limitée, à des fins de sécurité, puis effacés.
                </li>
            </ul>

            <p>
                Pour en savoir plus, consultez nos <a href="{{ route('privacy') }}">règles de confidentialité</a>.
            </p>
</x-public-layout>
