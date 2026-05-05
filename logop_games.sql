-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3307
-- Время создания: Май 05 2026 г., 19:24
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `logop_games`
--

-- --------------------------------------------------------

--
-- Структура таблицы `games`
--

CREATE TABLE `games` (
  `id` int(11) NOT NULL,
  `code` varchar(64) NOT NULL,
  `title` varchar(128) NOT NULL,
  `path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `games`
--

INSERT INTO `games` (`id`, `code`, `title`, `path`) VALUES
(1, 'game1', 'Лопа и пропавшие гласные', '/LogoLopa/games/game1/index.html'),
(2, 'game2', 'Лопа и загадочные карточки', '/LogoLopa/games/game2/index.html'),
(3, 'game3', 'Поймай гласный звук', '/LogoLopa/games/game3/index.html'),
(4, 'game4', 'Волшебные жетоны', '/LogoLopa/games/game4/index.html'),
(5, 'game5', 'Лопа сортирует звуки', '/LogoLopa/games/game5/index.html');

-- --------------------------------------------------------

--
-- Структура таблицы `game_result`
--

CREATE TABLE `game_result` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `correct_answers` int(11) NOT NULL DEFAULT 0,
  `incorrect_answers` int(11) NOT NULL DEFAULT 0,
  `time_spent` int(11) NOT NULL DEFAULT 0,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `student_game_access`
--

CREATE TABLE `student_game_access` (
  `student_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` enum('teacher','student') NOT NULL,
  `login` varchar(64) NOT NULL,
  `pass_hash` varchar(255) NOT NULL,
  `display_name` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `must_change_pass` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `role`, `login`, `pass_hash`, `display_name`, `created_at`, `must_change_pass`) VALUES
(1, 'teacher', 'teacher', '$2y$10$wR.TEXmrEL1ooEFH5RhtqeH8hj/YEYkFBjpzcUN90iA/eTCCfrdya', 'Учитель', '2026-01-06 16:33:01', 0);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Индексы таблицы `game_result`
--
ALTER TABLE `game_result`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `game_id` (`game_id`);

--
-- Индексы таблицы `student_game_access`
--
ALTER TABLE `student_game_access`
  ADD PRIMARY KEY (`student_id`,`game_id`),
  ADD KEY `game_id` (`game_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `games`
--
ALTER TABLE `games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `game_result`
--
ALTER TABLE `game_result`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `game_result`
--
ALTER TABLE `game_result`
  ADD CONSTRAINT `game_result_game_fk` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `game_result_student_fk` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `student_game_access`
--
ALTER TABLE `student_game_access`
  ADD CONSTRAINT `student_game_access_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_game_access_ibfk_2` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
