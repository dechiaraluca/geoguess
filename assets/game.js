let gameState = {
    sessionId: null,
    playerId: null,
    playerName: '',
    currentCity: null,
    score: 0,
    lives: 5
};

const welcomeScreen = document.getElementById('welcome-screen');
const gameScreen = document.getElementById('game-screen');
const gameoverScreen = document.getElementById('gameover-screen');
const loading = document.getElementById('loading');

const startBtn = document.getElementById('start-btn');
const nextBtn = document.getElementById('next-btn');
const replayBtn = document.getElementById('replay-btn');

const playerNameInput = document.getElementById('player-name');

startBtn.addEventListener('click', startGame);
nextBtn.addEventListener('click', loadNextQuestion);
replayBtn.addEventListener('click', () => location.reload());

playerNameInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') startGame();
});

async function startGame() {
    const playerName = playerNameInput.value.trim();

    if (!playerName) {
        alert('Veuillez entrer votre nom');
        return;
    }

    gameState.playerName = playerName;
    showLoading();

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'start_session',
                player_name: playerName
            })
        });

        const data = await response.json();

        if (data.success) {
            gameState.sessionId = data.session_id;
            gameState.playerId = data.player_id;
            gameState.score = 0;
            gameState.lives = 5;

            document.getElementById('player-display').textContent = playerName;
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
    document.getElementById('feedback').classList.add('hidden');

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'get_question'
            })
        });

        const data = await response.json();

        if (data.success) {
            gameState.currentCity = data.city;
            displayQuestion(data.city, data.images, data.choices);
            hideLoading();
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
    img.loading = "lazy"; // Active le lazy loading
    
    // Quand l'image a fini de charger, on ajoute la classe pour l'opacité
    img.onload = () => img.classList.add('loaded');
    
    div.appendChild(img);
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

async function submitAnswer(answer) {
    const buttons = document.querySelectorAll('.btn-choice');
    buttons.forEach(btn => btn.disabled = true);

    showLoading();

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'submit_answer',
                session_id: gameState.sessionId,
                city_id: gameState.currentCity.id,
                answer: answer
            })
        });

        const data = await response.json();

        if (data.success) {
            gameState.score = data.new_score;
            gameState.lives = data.lives_remaining;

            updateStats();
            showFeedback(data);

            if (data.game_over) {
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
    const feedback = document.getElementById('feedback');
    const feedbackText = document.getElementById('feedback-text');

    feedback.classList.remove('hidden', 'correct', 'incorrect');

    if (data.is_correct) {
        feedback.classList.add('correct');
        feedbackText.textContent = ` Correct ! C'était bien ${data.correct_country}`;

        const buttons = document.querySelectorAll('.btn-choice');
        buttons.forEach(btn => {
            if (btn.textContent === data.correct_country) {
                btn.classList.add('correct');
            }
        });
    } else {
        feedback.classList.add('incorrect');
        feedbackText.innerHTML = `✗ Incorrect ! C'était ${data.correct_country}<br><small>Ville: ${data.city_name}</small>`;

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
}

async function endGame() {
    showLoading();

    try {
        const saveResponse = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_score',
                session_id: gameState.sessionId
            })
        });

        const saveData = await saveResponse.json();

        if (saveData.success) {
            document.getElementById('final-score').textContent = saveData.final_score;
            document.getElementById('final-correct').textContent = saveData.correct_answers;
            document.getElementById('final-total').textContent = saveData.total_questions;
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

        html += `
            <tr>
                <td>${index + 1}</td>
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
