<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Règles de confidentialité – LoveGames</title>
        <style>
            :root { --text: #1a1a1a; --muted: #555; --accent: #0a7ea4; --bg: #fff; --border: #e5e5e5; }
            @media (prefers-color-scheme: dark) {
                :root { --text: #ececec; --muted: #a8a8a8; --accent: #4fb3d9; --bg: #121212; --border: #2c2c2c; }
            }
            body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
            main { max-width: 760px; margin: 0 auto; padding: 32px 16px 64px; }
            h1 { font-size: 1.9rem; line-height: 1.25; margin: 0 0 4px; }
            h2 { font-size: 1.2rem; margin: 36px 0 8px; padding-top: 16px; border-top: 1px solid var(--border); }
            .updated { color: var(--muted); margin: 0 0 24px; }
            a { color: var(--accent); }
            ul { padding-left: 22px; }
            li { margin: 4px 0; }
            table { width: 100%; border-collapse: collapse; font-size: .95rem; }
            th, td { text-align: left; vertical-align: top; padding: 8px; border-bottom: 1px solid var(--border); }
            th { color: var(--muted); font-weight: 600; }
            .table-wrap { overflow-x: auto; }
        </style>
    </head>
    <body>
        <main>
            <h1>Règles de confidentialité de LoveGames</h1>
            <p class="updated">Dernière mise à jour : 27 octobre 2026</p>

            <p>
                LoveGames est une application mobile de messagerie et de jeux à deux, éditée par
                <strong>Seninova Group</strong> (Burkina Faso). Ces règles expliquent quelles données
                l'application collecte, pourquoi, avec qui elles sont partagées, combien de temps elles sont
                conservées et quels sont vos droits.
            </p>
            <p>
                Contact pour toute question ou demande :
                <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
            </p>

            <h2>1. Données que nous collectons</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Données</th><th>Utilisation</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Adresse e-mail, mot de passe (enregistré sous forme chiffrée irréversible), numéro de téléphone</td>
                            <td>Créer votre compte, vous connecter, réinitialiser votre mot de passe, permettre à vos contacts de vous trouver</td>
                        </tr>
                        <tr>
                            <td>Prénom, nom, pseudo, statut, photo de profil</td>
                            <td>Afficher votre profil aux autres utilisateurs</td>
                        </tr>
                        <tr>
                            <td>Messages, photos, messages vocaux, fichiers, réactions, contenus et choix des jeux</td>
                            <td>Les transmettre à votre interlocuteur et les conserver dans vos conversations et vos jeux</td>
                        </tr>
                        <tr>
                            <td>Statut en ligne et dernière connexion</td>
                            <td>Indiquer à vos interlocuteurs si vous êtes disponible</td>
                        </tr>
                        <tr>
                            <td>Jeton de notification de l'appareil</td>
                            <td>Vous envoyer des notifications (nouveaux messages, jeux)</td>
                        </tr>
                        <tr>
                            <td>Préférences : conversations archivées ou verrouillées, comptes bloqués</td>
                            <td>Faire fonctionner ces options pour vous seul ; l'autre personne n'en est pas informée</td>
                        </tr>
                        <tr>
                            <td>Signalements que vous envoyez ou qui vous concernent</td>
                            <td>Modération : examen par nos modérateurs et, si nécessaire, suspension d'un compte</td>
                        </tr>
                        <tr>
                            <td>Données techniques (adresse IP, date et heure des requêtes)</td>
                            <td>Sécurité, prévention des abus et résolution des problèmes, dans les journaux du serveur</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h2>2. Accès aux fonctions du téléphone</h2>
            <ul>
                <li>
                    <strong>Contacts</strong> (si vous l'autorisez) : les numéros de téléphone de votre répertoire
                    sont envoyés à notre serveur uniquement pour vous indiquer lesquels de vos contacts utilisent
                    LoveGames. Ils sont comparés aux comptes existants puis <strong>ne sont pas conservés</strong>.
                    L'application peut aussi enregistrer un nouveau contact dans votre répertoire, à votre demande.
                </li>
                <li>
                    <strong>Microphone</strong> : utilisé seulement quand vous enregistrez un message vocal.
                </li>
                <li>
                    <strong>Photos et fichiers</strong> : seuls les éléments que vous choisissez d'envoyer ou de
                    mettre en photo de profil sont transmis.
                </li>
                <li>
                    <strong>Empreinte digitale, Face ID, code du téléphone</strong> : la vérification est faite
                    par votre téléphone. LoveGames ne reçoit que le résultat (réussi ou non) et
                    <strong>n'a jamais accès à vos données biométriques</strong>.
                </li>
                <li>
                    <strong>Notifications</strong> : pour vous prévenir des nouveaux messages et des jeux.
                    Les notifications d'une discussion verrouillée n'affichent ni le nom ni le contenu.
                </li>
            </ul>

            <h2>3. Partage des données</h2>
            <p>
                Nous ne vendons pas vos données et ne les utilisons pas pour de la publicité. L'application ne
                contient ni publicité ni outil de mesure d'audience. Vos données ne sont partagées qu'avec :
            </p>
            <ul>
                <li><strong>Les personnes avec qui vous échangez</strong> : votre profil, vos messages et vos contenus de jeu.</li>
                <li><strong>o2switch</strong> (France) : hébergement du serveur et de la base de données.</li>
                <li><strong>Pusher</strong> (serveurs en Europe) : acheminement des messages en temps réel ; les données ne font qu'y transiter.</li>
                <li><strong>Expo et Google (Firebase Cloud Messaging), Apple</strong> : acheminement des notifications vers votre téléphone.</li>
                <li><strong>Les autorités</strong>, uniquement si la loi nous y oblige.</li>
            </ul>
            <p>
                Ces prestataires traitent les données uniquement pour fournir leur service. Certains peuvent
                être situés hors du Burkina Faso.
            </p>

            <h2>4. Durée de conservation</h2>
            <ul>
                <li>Les données de votre compte sont conservées tant que votre compte existe.</li>
                <li>Un message supprimé « pour tout le monde » n'est plus affiché : son texte et le lien vers son fichier joint sont retirés de la conversation.</li>
                <li>Un signalement conserve une copie du contenu signalé, le temps nécessaire à la modération.</li>
                <li>À la suppression de votre compte, votre profil, vos messages, vos fichiers et vos jeux sont supprimés, sauf obligation légale de conservation.</li>
                <li>Les journaux techniques du serveur sont conservés pour une durée limitée, à des fins de sécurité.</li>
            </ul>

            <h2>5. Sécurité</h2>
            <p>
                Les échanges entre l'application et nos serveurs sont chiffrés (HTTPS/TLS). Les mots de passe sont
                enregistrés sous forme chiffrée irréversible. L'accès aux conversations est réservé à leurs
                participants. Aucun système n'étant infaillible, nous vous invitons à protéger l'accès à votre
                téléphone et à ne pas partager votre mot de passe.
            </p>

            <h2>6. Vos droits et la suppression de votre compte</h2>
            <p>
                Vous pouvez accéder à vos données, les corriger, vous opposer à leur traitement, en demander une
                copie ou leur suppression. Vous pouvez modifier votre profil directement dans l'application.
            </p>
            <p>
                Pour supprimer votre compte et les données associées, écrivez-nous à
                <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> depuis l'adresse e-mail de votre compte,
                en indiquant votre pseudo. Nous traitons la demande sous 30 jours au plus.
            </p>
            <p>
                Si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir la Commission de
                l'Informatique et des Libertés (CIL) du Burkina Faso.
            </p>

            <h2>7. Âge minimum</h2>
            <p>
                LoveGames est réservée aux personnes âgées de <strong>18 ans ou plus</strong>. Nous ne collectons pas
                sciemment de données concernant des mineurs ; si vous pensez qu'un mineur utilise l'application,
                contactez-nous pour que nous supprimions son compte.
            </p>

            <h2>8. Modifications</h2>
            <p>
                Nous pouvons mettre à jour ces règles. La date de dernière mise à jour figure en haut de cette page.
                En cas de changement important, nous vous en informerons dans l'application.
            </p>
        </main>
    </body>
</html>
