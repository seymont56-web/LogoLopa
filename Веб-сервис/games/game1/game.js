document.addEventListener("DOMContentLoaded", function() {
    // --- Получение DOM-элементов ---
    const lettersContainer = document.getElementById("letters-container");
    const wordsContainer = document.querySelector(".words-container");
    const redPaint = document.getElementById("red-paint");
    const checkBtn = document.getElementById("check-btn");
    const resetBtn = document.getElementById("reset-btn");
    const result = document.getElementById("result");
    const novelOverlay = document.getElementById("novel-overlay");
    const novelBtn = document.getElementById("novel-btn");

    // --- ID ИГРЫ В БАЗЕ ДАННЫХ ---
    const GAME_ID = 1;

    // --- ПЕРЕМЕННЫЕ ДЛЯ РЕЗУЛЬТАТОВ ---
    let correctAnswers = 0;
    let incorrectAnswers = 0;
    let startTime = null;
    let resultSaved = false;

    // --- ДАННЫЕ УРОВНЕЙ И ЛИШНИЕ БУКВЫ ---
    const GAME_LEVELS = [
        {
            word: "КАША",
            correctVowels: 2,
            extraLetters: ["А", "Е", "И", "О"],
            imagePlaceholder: "Изображение каши"
        },
        {
            word: "ТОРТ",
            correctVowels: 1,
            extraLetters: ["А", "Е", "И", "У"],
            imagePlaceholder: "Изображение торта"
        },
        {
            word: "СУП",
            correctVowels: 1,
            extraLetters: ["А", "Е", "О", "Я"],
            imagePlaceholder: "Изображение супа"
        }
    ];

    let currentLevel = 0;
    const maxErrors = 3;
    let errors = 0;

    // --- Управление начальным окном ---
    novelBtn.addEventListener("click", function() {
        novelOverlay.style.display = "none";
        startNewGame();
    });

    // --- СТАРТ НОВОЙ ИГРЫ ---
    function startNewGame() {
        currentLevel = 0;
        errors = 0;
        correctAnswers = 0;
        incorrectAnswers = 0;
        resultSaved = false;
        startTime = Date.now();

        loadLevel(currentLevel);
    }

    // --- ФУНКЦИЯ ЗАГРУЗКИ УРОВНЯ ---
    function loadLevel(levelIndex) {
        if (levelIndex >= GAME_LEVELS.length) {
            finishGame();
            return;
        }

        const levelData = GAME_LEVELS[levelIndex];

        currentLevel = levelIndex;
        errors = 0;

        wordsContainer.innerHTML = '';
        createWordElements(levelData.word);

        lettersContainer.innerHTML = '';

        const allLetters = getRequiredLetters(levelData.word)
            .concat(levelData.extraLetters)
            .sort(() => Math.random() - 0.5);

        createLetterElements(allLetters);

        result.textContent = `Уровень ${levelIndex + 1}: Собери слово "${levelData.word}". Ошибок: ${errors}/${maxErrors}`;
        result.style.color = "#4CAF50";

        checkBtn.style.display = 'block';
        checkBtn.removeAttribute('disabled');

        redPaint.style.display = 'none';

        resetBtn.onclick = null;
        resetBtn.textContent = 'Сбросить';

        attachDragListeners();
    }

    // --- ЗАВЕРШЕНИЕ ИГРЫ ---
    async function finishGame() {
        const timeSpent = getTimeSpent();

        result.textContent = "🏆 Ты прошёл все уровни! Сохраняем результат...";
        result.style.color = "gold";

        checkBtn.style.display = 'none';
        redPaint.style.display = 'none';

        lettersContainer.innerHTML = '';
        wordsContainer.innerHTML = '';

        resetBtn.textContent = 'Начать заново';
        resetBtn.onclick = () => startNewGame();

        await saveGameResult(timeSpent);
    }

    // --- ПОДСЧЁТ ВРЕМЕНИ В СЕКУНДАХ ---
    function getTimeSpent() {
        if (!startTime) {
            return 0;
        }

        return Math.floor((Date.now() - startTime) / 1000);
    }

    // --- СОХРАНЕНИЕ РЕЗУЛЬТАТА В БД ---
    async function saveGameResult(timeSpent) {
        if (resultSaved) {
            return;
        }

        resultSaved = true;

        try {
            const response = await fetch('../../save_result.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    game_id: GAME_ID,
                    correct_answers: correctAnswers,
                    incorrect_answers: incorrectAnswers,
                    time_spent: timeSpent
                })
            });

            const data = await response.json();

            if (data.success) {
                result.textContent =
                    `🏆 Игра завершена! Результат сохранён. Правильных ответов: ${correctAnswers}, ошибок: ${incorrectAnswers}, время: ${timeSpent} сек.`;
                result.style.color = "gold";
            } else {
                result.textContent =
                    `🏆 Игра завершена, но результат не сохранён: ${data.message}`;
                result.style.color = "orange";
            }
        } catch (error) {
            result.textContent =
                "🏆 Игра завершена, но произошла ошибка при сохранении результата.";
            result.style.color = "orange";
        }
    }

    // --- ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ---
    function getRequiredLetters(word) {
        const uniqueVowels = new Set();
        const vowels = ['А', 'Е', 'Ё', 'И', 'О', 'У', 'Ы', 'Э', 'Ю', 'Я'];

        for (const char of word.toUpperCase()) {
            if (vowels.includes(char)) {
                uniqueVowels.add(char);
            }
        }

        return Array.from(uniqueVowels);
    }

    function createWordElements(word) {
        const wordDiv = document.createElement('div');

        wordDiv.className = 'word';
        wordDiv.id = 'current-word';

        const vowels = ['А', 'Е', 'Ё', 'И', 'О', 'У', 'Ы', 'Э', 'Ю', 'Я'];

        for (const char of word.toUpperCase()) {
            const span = document.createElement('span');

            span.className = 'char';

            if (vowels.includes(char)) {
                span.classList.add('placeholder');
                span.textContent = '_';
                span.setAttribute('data-correct-char', char);
            } else {
                span.classList.add('static');
                span.textContent = char;
            }

            wordDiv.appendChild(span);
        }

        wordsContainer.appendChild(wordDiv);
    }

    function createLetterElements(letters) {
        letters.forEach((char, index) => {
            const letterDiv = document.createElement('div');

            letterDiv.className = 'letter';
            letterDiv.id = `letter-${char}-${index}_${Math.random().toString(36).substring(7)}`;
            letterDiv.textContent = char;
            letterDiv.setAttribute('draggable', 'true');

            lettersContainer.appendChild(letterDiv);
        });
    }

    function attachDragListeners() {
        document.querySelectorAll(".letter").forEach(letter => {
            letter.removeEventListener('dragstart', handleDragStart);
            letter.addEventListener("dragstart", handleDragStart);
        });

        redPaint.removeEventListener('dragstart', handlePaintDragStart);
        redPaint.addEventListener("dragstart", handlePaintDragStart);
    }

    function handleDragStart(event) {
        event.dataTransfer.setData("letter-id", event.target.id);
        event.dataTransfer.setData("letter-content", event.target.textContent);
        event.dataTransfer.setData("drag-type", "letter");
    }

    function handlePaintDragStart(event) {
        event.dataTransfer.setData("drag-type", "paint");
    }

    // --- ОБРАБОТКА DRAG AND DROP ---
    wordsContainer.addEventListener("dragover", (event) => {
        event.preventDefault();

        const target = event.target.closest(".char");
        const dragType = event.dataTransfer.getData("drag-type");

        if (target && target.classList.contains("placeholder") && dragType === "letter") {
            target.classList.add("drag-over");
        }

        if (target && dragType === "paint") {
            event.preventDefault();
        }
    });

    wordsContainer.addEventListener("dragleave", () => {
        document.querySelectorAll(".char.drag-over").forEach(char => {
            char.classList.remove("drag-over");
        });
    });

    wordsContainer.addEventListener("drop", (event) => {
        event.preventDefault();

        const targetChar = event.target.closest(".char");
        const dragType = event.dataTransfer.getData("drag-type");

        document.querySelectorAll(".char.drag-over").forEach(char => {
            char.classList.remove("drag-over");
        });

        if (!targetChar) {
            return;
        }

        if (dragType === "letter") {
            if (!targetChar.classList.contains("placeholder")) {
                result.textContent = "Это место уже занято!";
                setTimeout(() => result.textContent = "", 1500);
                return;
            }

            const letterId = event.dataTransfer.getData("letter-id");
            const letterContent = event.dataTransfer.getData("letter-content");

            targetChar.textContent = letterContent;
            targetChar.classList.remove("placeholder");
            targetChar.classList.add("filled");
            targetChar.setAttribute("data-original-id", letterId);

            const letterElement = document.getElementById(letterId);

            if (letterElement) {
                letterElement.style.display = "none";
            }
        }

        if (dragType === "paint") {
            if (!checkBtn.hasAttribute('disabled')) {
                result.textContent = "Сначала собери слово и нажми 'Проверить'!";
                setTimeout(() => result.textContent = "", 2000);
                return;
            }

            if (targetChar.classList.contains("filled")) {
                if (targetChar.textContent.match(/[АЕЁИОУЫЭЮЯ]/i)) {
                    targetChar.classList.add("red-vowel");
                    checkIfAllPainted();
                }
            } else if (targetChar.classList.contains("static")) {
                result.textContent = "Нельзя красить согласные буквы!";
                setTimeout(() => result.textContent = "", 1500);
            }
        }
    });

    // --- ПРОВЕРКА СЛОВА ---
    checkBtn.addEventListener("click", () => {
        const wordSpans = document.querySelectorAll("#current-word .char");
        const levelData = GAME_LEVELS[currentLevel];

        let isCorrect = true;

        const filledVowels = document.querySelectorAll(".filled");

        if (filledVowels.length < levelData.correctVowels) {
            result.textContent = "Сначала заполни все пропуски!";
            result.style.color = "orange";
            return;
        }

        wordSpans.forEach(span => {
            if (span.classList.contains('filled')) {
                const correctChar = span.getAttribute("data-correct-char");

                if (span.textContent !== correctChar) {
                    isCorrect = false;
                    span.classList.add('error');
                } else {
                    span.classList.remove('error');
                }
            }
        });

        if (isCorrect) {
            result.textContent = "✅ Верно! Теперь покрась гласные в красный цвет.";
            result.style.color = "#4CAF50";

            checkBtn.setAttribute('disabled', 'true');
            redPaint.style.display = 'flex';
        } else {
            errors++;
            incorrectAnswers++;

            if (errors >= maxErrors) {
                result.textContent = `🚫 Уровень провален! Правильное слово было "${levelData.word}".`;
                result.style.color = "red";

                checkBtn.style.display = 'none';

                resetBtn.textContent = 'Следующий уровень';
                resetBtn.onclick = () => loadLevel(currentLevel + 1);
            } else {
                resetWordAndLetters();
            }
        }
    });

    // --- ПРОВЕРКА ПОКРАСКИ И ПРОХОЖДЕНИЕ УРОВНЯ ---
    function checkIfAllPainted() {
        const levelData = GAME_LEVELS[currentLevel];
        const paintedVowels = document.querySelectorAll(".filled.red-vowel");

        if (paintedVowels.length === levelData.correctVowels) {
            correctAnswers++;

            result.textContent = "👍 Гласные покрашены! Уровень пройден! Нажми 'Следующий уровень'.";
            result.style.color = "green";

            resetBtn.textContent = 'Следующий уровень';
            resetBtn.onclick = () => loadLevel(currentLevel + 1);

            checkBtn.style.display = 'none';
        }
    }

    // --- СБРОС СЛОВА И ВОЗВРАТ БУКВ ---
    function resetWordAndLetters() {
        document.querySelectorAll("#current-word .char:not(.static)").forEach(charSpan => {
            if (charSpan.classList.contains('filled') || charSpan.getAttribute("data-original-id")) {
                const letterId = charSpan.getAttribute("data-original-id");
                const letterElement = document.getElementById(letterId);

                if (letterElement) {
                    letterElement.style.display = "flex";
                }
            }

            charSpan.textContent = '_';
            charSpan.classList.remove("filled", "red-vowel", "drag-over", "error");
            charSpan.classList.add("placeholder");
            charSpan.removeAttribute("data-original-id");
        });

        result.textContent = `❌ Неверно. Попробуй снова. Ошибок: ${errors}/${maxErrors}`;
        result.style.color = "red";
    }

    // --- ОБРАБОТЧИК КНОПКИ СБРОСА ---
    resetBtn.addEventListener("click", () => {
        if (resetBtn.onclick && resetBtn.textContent === 'Следующий уровень') {
            resetBtn.onclick();
            return;
        }

        if (resetBtn.onclick && resetBtn.textContent === 'Начать заново') {
            resetBtn.onclick();
            return;
        }

        loadLevel(currentLevel);
    });
});