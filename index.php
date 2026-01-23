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

                <div class="options-section">
                    <div class="option-group">
                        <label>Difficulté</label>
                        <div class="difficulty-selector">
                            <button type="button" class="diff-btn" data-difficulty="easy">Facile</button>
                            <button type="button" class="diff-btn active" data-difficulty="normal">Normal</button>
                            <button type="button" class="diff-btn" data-difficulty="hard">Difficile</button>
                        </div>
                    </div>

                    <div class="option-group">
                        <label>Mode chrono</label>
                        <div class="toggle-switch">
                            <input type="checkbox" id="timer-mode" class="toggle-input">
                            <label for="timer-mode" class="toggle-label">
                                <span class="toggle-text-off">Désactivé</span>
                                <span class="toggle-text-on">Activé</span>
                            </label>
                        </div>
                        <small class="option-hint">Répondez vite pour des bonus !</small>
                    </div>
                </div>

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
                    <span class="label">Joueur</span>
                    <span id="player-display" class="value">-</span>
                </div>
                <div class="stat">
                    <span class="label">Score</span>
                    <span id="score-display" class="value">0</span>
                </div>
                <div class="stat">
                    <span class="label">Série</span>
                    <span id="streak-display" class="value">0</span>
                </div>
                <div class="stat">
                    <span class="label">Vies</span>
                    <span id="lives-display" class="value lives-container"></span>
                </div>
            </div>

            <div id="timer-bar-container" class="timer-bar-container hidden">
                <div id="timer-bar" class="timer-bar"></div>
                <span id="timer-text" class="timer-text">15s</span>
            </div>

            <div class="images-grid" id="images-grid">
            </div>

            <div class="question-box">
                <div class="question-header">
                    <h2>De quel pays viennent ces images ?</h2>
                    <button id="hint-btn" class="btn btn-hint" title="Utiliser un indice (-2 pts)">
                        Indice
                    </button>
                </div>

                <div id="hint-display" class="hint-display hidden"></div>

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
                    <div class="stat-item">
                        <span class="stat-label">Meilleure série</span>
                        <span class="stat-value" id="final-streak">0</span>
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
