<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoGuess - Devinez le pays</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div id="welcome-screen" class="screen active">
        <div class="container">
            <h1>GeoGuess</h1>
            <p class="subtitle">Devinez le pays à partir des images</p>

            <div class="welcome-box">
                <h2>Comment jouer ?</h2>
                <ul>
                    <li>Vous avez <strong>5 vies</strong></li>
                    <li>Devinez le bon pays pour gagner <strong>10 points</strong></li>
                    <li>Une mauvaise réponse = <strong>-1 vie</strong></li>
                    <li>Le jeu se termine quand vous n'avez plus de vies</li>
                </ul>

                <div class="input-group">
                    <input type="text" id="player-name" placeholder="Votre nom" maxlength="20">
                    <button id="start-btn" class="btn btn-primary">Commencer</button>
                </div>
            </div>
        </div>
    </div>

    <div id="game-screen" class="screen">
        <div class="container">
            <div class="game-header">
                <div class="stat">
                    <span class="label">Joueur:</span>
                    <span id="player-display" class="value">-</span>
                </div>
                <div class="stat">
                    <span class="label">Score:</span>
                    <span id="score-display" class="value">0</span>
                </div>
                <div class="stat">
                    <span class="label">Vies:</span>
                    <span id="lives-display" class="value">❤️❤️❤️❤️❤️</span>
                </div>
            </div>

            <div class="images-grid" id="images-grid">
            </div>

            <div class="question-box">
                <h2>De quel pays viennent ces images ?</h2>

                <div class="choices" id="choices-container">
                </div>
            </div>

            <div id="feedback-overlay" class="feedback-overlay hidden">
                <div id="feedback" class="feedback">
                    <p id="feedback-text"></p>
                    <button id="next-btn" class="btn btn-primary">Question suivante</button>
                </div>
            </div>
        </div>
    </div>

    <div id="gameover-screen" class="screen">
        <div class="container">
            <div class="gameover-box">
                <h1> Partie terminée !</h1>

                <div class="final-stats">
                    <div class="stat-item">
                        <span class="stat-label">Score final</span>
                        <span class="stat-value" id="final-score">0</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Bonnes réponses</span>
                        <span class="stat-value" id="final-correct">0</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total questions</span>
                        <span class="stat-value" id="final-total">0</span>
                    </div>
                </div>

                <div class="leaderboard" id="leaderboard">
                    <h2> Classement</h2>
                    <table id="leaderboard-table">
                    </table>
                </div>

                <button id="replay-btn" class="btn btn-primary">Rejouer</button>
            </div>
        </div>
    </div>

    <div id="loading" class="loading hidden">
        <div class="spinner"></div>
        <p>Chargement...</p>
    </div>

    <div id="lightbox" class="lightbox hidden">
        <button class="lightbox-close">&times;</button>
        <img id="lightbox-img" src="" alt="">
    </div>

    <script src="assets/game.js"></script>

</body>
</html>
