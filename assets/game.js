let gameState = {
    sessionId: null,
    playerId: null,
    playerName: '',
    currentCity: null,
    score: 0,
    lives: 5,
    streak: 0,
    bestStreak: 0,
    difficulty: 'normal',
    timerMode: false,
    timerInterval: null,
    timeRemaining: 15,
    hintUsed: false,
    hintsAvailable: ['continent', 'capital', 'population']
};

const DIFFICULTY_CONFIG = {
    easy: { choices: 3, timerSeconds: 25, basePoints: 8 },
    normal: { choices: 4, timerSeconds: 15, basePoints: 10 },
    hard: { choices: 6, timerSeconds: 10, basePoints: 15 }
};

const welcomeScreen = document.getElementById('welcome-screen');
const gameScreen = document.getElementById('game-screen');
const gameoverScreen = document.getElementById('gameover-screen');
const loading = document.getElementById('loading');

const startBtn = document.getElementById('start-btn');
const nextBtn = document.getElementById('next-btn');
const replayBtn = document.getElementById('replay-btn');
const hintBtn = document.getElementById('hint-btn');

const playerNameInput = document.getElementById('player-name');
const timerModeCheckbox = document.getElementById('timer-mode');
const difficultyBtns = document.querySelectorAll('.diff-btn');

startBtn.addEventListener('click', startGame);
nextBtn.addEventListener('click', loadNextQuestion);
replayBtn.addEventListener('click', () => location.reload());
hintBtn.addEventListener('click', useHint);

playerNameInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') startGame();
});

difficultyBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        difficultyBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        gameState.difficulty = btn.dataset.difficulty;
    });
});

async function startGame() {
    const playerName = playerNameInput.value.trim();

    if (!playerName) {
        alert('Veuillez entrer votre nom');
        return;
    }

    gameState.playerName = playerName;
    gameState.timerMode = timerModeCheckbox.checked;
    gameState.streak = 0;
    gameState.bestStreak = 0;

    showLoading();

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'start_session',
                player_name: playerName,
                difficulty: gameState.difficulty,
                timer_mode: gameState.timerMode
            })
        });

        const data = await response.json();

        if (data.success) {
            gameState.sessionId = data.session_id;
            gameState.playerId = data.player_id;
            gameState.score = 0;
            gameState.lives = 5;

            document.getElementById('player-display').textContent = playerName;
            updateStats();

            const timerContainer = document.getElementById('timer-bar-container');
            if (gameState.timerMode) {
                timerContainer.classList.remove('hidden');
            } else {
                timerContainer.classList.add('hidden');
            }

            switchScreen('game');
            loadNextQuestion();
        } else {
            alert('Erreur: ' + data.error);
            hideLoading();
        }
    } catch (error) {
        alert('Erreur de connexion: ' + error.message);
        hideLoading();
    }
}

async function loadNextQuestion() {
    showLoading();
    stopTimer();
    document.getElementById('feedback-overlay').classList.add('hidden');
    gameState.hintUsed = false;

    const hintDisplay = document.getElementById('hint-display');
    hintDisplay.classList.add('hidden');
    hintDisplay.innerHTML = '';
    hintBtn.disabled = false;
    hintBtn.classList.remove('used');

    try {
        const config = DIFFICULTY_CONFIG[gameState.difficulty];

        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'get_question',
                difficulty: gameState.difficulty,
                num_choices: config.choices
            })
        });

        const data = await response.json();

        if (data.success) {
            gameState.currentCity = data.city;
            gameState.currentHints = data.hints || {};
            displayQuestion(data.city, data.images, data.choices);
            hideLoading();

            if (gameState.timerMode) {
                startTimer();
            }
        } else {
            alert('Erreur: ' + data.error);
            hideLoading();
        }
    } catch (error) {
        alert('Erreur: ' + error.message);
        hideLoading();
    }
}

function displayQuestion(city, images, choices) {
    const imagesGrid = document.getElementById('images-grid');
    imagesGrid.innerHTML = '';

    images.forEach(image => {
        const div = document.createElement('div');
        div.className = 'image-item';

        const img = document.createElement('img');
        img.src = image.url;
        img.alt = image.title;
        img.loading = "lazy";

        img.onload = () => img.classList.add('loaded');

        const zoomIcon = document.createElement('div');
        zoomIcon.className = 'zoom-icon';
        zoomIcon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35M11 8v6M8 11h6"/></svg>';

        div.onclick = () => openLightbox(image.url);

        div.appendChild(img);
        div.appendChild(zoomIcon);
        imagesGrid.appendChild(div);
    });

    const choicesContainer = document.getElementById('choices-container');
    choicesContainer.innerHTML = '';

    choices.forEach(choice => {
        const button = document.createElement('button');
        button.className = 'btn btn-choice';
        button.textContent = choice.name;
        button.onclick = () => submitAnswer(choice.name);
        choicesContainer.appendChild(button);
    });
}

function startTimer() {
    const config = DIFFICULTY_CONFIG[gameState.difficulty];
    gameState.timeRemaining = config.timerSeconds;

    const timerBar = document.getElementById('timer-bar');
    const timerText = document.getElementById('timer-text');

    timerBar.style.width = '100%';
    timerBar.className = 'timer-bar';
    timerText.textContent = gameState.timeRemaining + 's';

    gameState.timerInterval = setInterval(() => {
        gameState.timeRemaining--;

        const percentage = (gameState.timeRemaining / config.timerSeconds) * 100;
        timerBar.style.width = percentage + '%';
        timerText.textContent = gameState.timeRemaining + 's';

        if (percentage <= 33) {
            timerBar.className = 'timer-bar timer-danger';
        } else if (percentage <= 66) {
            timerBar.className = 'timer-bar timer-warning';
        }

        if (gameState.timeRemaining <= 0) {
            stopTimer();
            handleTimeOut();
        }
    }, 1000);
}

function stopTimer() {
    if (gameState.timerInterval) {
        clearInterval(gameState.timerInterval);
        gameState.timerInterval = null;
    }
}

function handleTimeOut() {
    const buttons = document.querySelectorAll('.btn-choice');
    buttons.forEach(btn => btn.disabled = true);

    gameState.lives--;
    gameState.streak = 0;
    updateStats();

    showFeedback({
        is_correct: false,
        correct_country: gameState.currentCity.country_name,
        city_name: gameState.currentCity.name,
        timeout: true
    });

    if (gameState.lives <= 0) {
        setTimeout(() => endGame(), 2000);
    }
}

function useHint() {
    if (gameState.hintUsed || gameState.score < 2) {
        if (gameState.score < 2) {
            alert('Vous avez besoin d\'au moins 2 points pour utiliser un indice');
        }
        return;
    }

    gameState.hintUsed = true;
    gameState.score = Math.max(0, gameState.score - 2);
    updateStats();

    hintBtn.disabled = true;
    hintBtn.classList.add('used');

    const hintDisplay = document.getElementById('hint-display');
    const hints = gameState.currentHints;

    let hintText = '';
    if (hints.continent) {
        hintText = `🌍 Continent : <strong>${hints.continent}</strong>`;
    } else if (hints.region) {
        hintText = `📍 Région : <strong>${hints.region}</strong>`;
    } else {
        hintText = `🏙️ Cette ville commence par la lettre <strong>${gameState.currentCity.name.charAt(0)}</strong>`;
    }

    hintDisplay.innerHTML = hintText;
    hintDisplay.classList.remove('hidden');
    hintDisplay.classList.add('hint-animation');
}

async function submitAnswer(answer) {
    const buttons = document.querySelectorAll('.btn-choice');
    buttons.forEach(btn => btn.disabled = true);

    stopTimer();
    showLoading();

    const config = DIFFICULTY_CONFIG[gameState.difficulty];
    let timeBonus = 0;

    if (gameState.timerMode && gameState.timeRemaining > 0) {
        timeBonus = Math.floor(gameState.timeRemaining / 3);
    }

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'submit_answer',
                session_id: gameState.sessionId,
                city_id: gameState.currentCity.id,
                answer: answer,
                time_bonus: timeBonus,
                streak: gameState.streak,
                hint_used: gameState.hintUsed
            })
        });

        const data = await response.json();

        if (data.success) {
            if (data.is_correct) {
                gameState.streak++;
                if (gameState.streak > gameState.bestStreak) {
                    gameState.bestStreak = gameState.streak;
                }

                let points = config.basePoints + timeBonus;
                if (gameState.streak >= 3) {
                    points += Math.floor(gameState.streak / 3) * 5;
                    data.streak_bonus = Math.floor(gameState.streak / 3) * 5;
                }

                gameState.score += points;
                data.points_earned = points;
                data.time_bonus = timeBonus;
            } else {
                gameState.streak = 0;
                gameState.lives--;
            }

            updateStats();
            showFeedback(data);

            if (gameState.lives <= 0) {
                setTimeout(() => endGame(), 2000);
            }
        } else {
            alert('Erreur: ' + data.error);
        }

        hideLoading();
    } catch (error) {
        alert('Erreur: ' + error.message);
        hideLoading();
    }
}

function showFeedback(data) {
    const feedbackOverlay = document.getElementById('feedback-overlay');
    const feedback = document.getElementById('feedback');
    const feedbackText = document.getElementById('feedback-text');

    feedbackOverlay.classList.remove('hidden');
    feedback.classList.remove('correct', 'incorrect');

    if (data.is_correct) {
        feedback.classList.add('correct');
        let text = `✓ Correct ! C'était bien ${data.correct_country}`;

        if (data.points_earned) {
            text += `<br><span class="points-earned">+${data.points_earned} pts</span>`;

            if (data.time_bonus > 0) {
                text += `<span class="bonus-detail"> (dont +${data.time_bonus} bonus temps)</span>`;
            }
            if (data.streak_bonus > 0) {
                text += `<span class="bonus-detail"> (+${data.streak_bonus} bonus série)</span>`;
            }
        }

        if (gameState.streak >= 3) {
            text += `<br><span class="streak-message">🔥 Série de ${gameState.streak} !</span>`;
        }

        feedbackText.innerHTML = text;

        const buttons = document.querySelectorAll('.btn-choice');
        buttons.forEach(btn => {
            if (btn.textContent === data.correct_country) {
                btn.classList.add('correct');
            }
        });
    } else {
        feedback.classList.add('incorrect');
        let text = '';

        if (data.timeout) {
            text = `⏱️ Temps écoulé ! C'était ${data.correct_country}`;
        } else {
            text = `✗ Incorrect ! C'était ${data.correct_country}`;
        }

        text += `<br><small>Ville: ${data.city_name}</small>`;
        feedbackText.innerHTML = text;

        const buttons = document.querySelectorAll('.btn-choice');
        buttons.forEach(btn => {
            if (btn.textContent === data.correct_country) {
                btn.classList.add('correct');
            } else if (btn.disabled) {
                const wasClicked = Array.from(buttons).some(b =>
                    b.textContent !== data.correct_country && !b.classList.contains('correct')
                );
                if (wasClicked) {
                    btn.classList.add('incorrect');
                }
            }
        });
    }
}

function updateStats() {
    document.getElementById('score-display').textContent = gameState.score;

    const hearts = '❤️'.repeat(gameState.lives) + '🖤'.repeat(5 - gameState.lives);
    document.getElementById('lives-display').textContent = hearts;

    const streakDisplay = document.getElementById('streak-display');
    if (gameState.streak >= 3) {
        streakDisplay.textContent = gameState.streak + ' 🔥';
        streakDisplay.classList.add('streak-active');
    } else {
        streakDisplay.textContent = gameState.streak + ' 🔥';
        streakDisplay.classList.remove('streak-active');
    }
}

async function endGame() {
    showLoading();
    stopTimer();

    try {
        const saveResponse = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_score',
                session_id: gameState.sessionId,
                final_score: gameState.score,
                best_streak: gameState.bestStreak
            })
        });

        const saveData = await saveResponse.json();

        if (saveData.success) {
            document.getElementById('final-score').textContent = saveData.final_score || gameState.score;
            document.getElementById('final-correct').textContent = saveData.correct_answers;
            document.getElementById('final-total').textContent = saveData.total_questions;
            document.getElementById('final-streak').textContent = gameState.bestStreak + ' 🔥';
        }

        const leaderboardResponse = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'get_leaderboard'
            })
        });

        const leaderboardData = await leaderboardResponse.json();

        if (leaderboardData.success) {
            displayLeaderboard(leaderboardData.leaderboard);
        }

        switchScreen('gameover');
        hideLoading();
    } catch (error) {
        alert('Erreur: ' + error.message);
        hideLoading();
    }
}

function displayLeaderboard(leaderboard) {
    const table = document.getElementById('leaderboard-table');

    let html = `
        <thead>
            <tr>
                <th>Rang</th>
                <th>Joueur</th>
                <th>Score</th>
                <th>Précision</th>
            </tr>
        </thead>
        <tbody>
    `;

    leaderboard.forEach((entry, index) => {
        const accuracy = entry.total_questions > 0
            ? Math.round((entry.correct_answers / entry.total_questions) * 100)
            : 0;

        const medal = index === 0 ? '🥇 ' : index === 1 ? '🥈 ' : index === 2 ? '🥉 ' : '';

        html += `
            <tr${entry.player_name === gameState.playerName ? ' class="current-player"' : ''}>
                <td>${medal}${index + 1}</td>
                <td>${entry.player_name}</td>
                <td>${entry.final_score}</td>
                <td>${entry.correct_answers}/${entry.total_questions} (${accuracy}%)</td>
            </tr>
        `;
    });

    html += '</tbody>';
    table.innerHTML = html;
}

function switchScreen(screen) {
    welcomeScreen.classList.remove('active');
    gameScreen.classList.remove('active');
    gameoverScreen.classList.remove('active');

    if (screen === 'welcome') welcomeScreen.classList.add('active');
    if (screen === 'game') gameScreen.classList.add('active');
    if (screen === 'gameover') gameoverScreen.classList.add('active');
}

function showLoading() {
    loading.classList.remove('hidden');
}

function hideLoading() {
    loading.classList.add('hidden');
}

const lightbox = document.getElementById('lightbox');
const lightboxImg = document.getElementById('lightbox-img');
const lightboxClose = document.querySelector('.lightbox-close');

function openLightbox(src) {
    lightboxImg.src = src;
    lightbox.classList.remove('hidden');
}

function closeLightbox() {
    lightbox.classList.add('hidden');
    lightboxImg.src = '';
}

lightboxClose.addEventListener('click', closeLightbox);
lightbox.addEventListener('click', (e) => {
    if (e.target === lightbox) closeLightbox();
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLightbox();
});
