-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3307
-- Время создания: Май 03 2026 г., 15:25
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
(1, 'game1', 'Лопа и пропавшие гласные', '/Веб-сервис/games/game1/index.html'),
(2, 'game2', 'Лопа и загадочные карточки', '/Веб-сервис/games/game2/index.html'),
(3, 'game3', 'Поймай гласный звук', '/Веб-сервис/games/game3/index.html'),
(4, 'game4', 'Волшебные жетоны', '/Веб-сервис/games/game4/index.html'),
(5, 'game5', 'Лопа сортирует звуки', '/Веб-сервис/games/game5/index.html');

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

--
-- Дамп данных таблицы `game_result`
--

INSERT INTO `game_result` (`id`, `student_id`, `game_id`, `correct_answers`, `incorrect_answers`, `time_spent`, `completed_at`) VALUES
(1, 16, 5, 10, 1, 21, '2026-05-01 18:02:21'),
(2, 16, 5, 10, 0, 46, '2026-05-01 18:03:09'),
(3, 17, 1, 5, 0, 33, '2026-05-02 18:05:23'),
(4, 17, 2, 6, 0, 51, '2026-05-02 18:06:19'),
(5, 16, 4, 5, 0, 9, '2026-05-03 04:49:04'),
(6, 16, 4, 5, 0, 8, '2026-05-03 04:49:14'),
(7, 16, 2, 6, 0, 75, '2026-05-03 05:17:39'),
(8, 16, 5, 10, 0, 31, '2026-05-03 05:20:15'),
(9, 16, 4, 5, 0, 17, '2026-05-03 05:20:59'),
(10, 16, 5, 10, 2, 104, '2026-05-03 13:17:37');

-- --------------------------------------------------------

--
-- Структура таблицы `student_game_access`
--

CREATE TABLE `student_game_access` (
  `student_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `student_game_access`
--

INSERT INTO `student_game_access` (`student_id`, `game_id`, `is_enabled`) VALUES
(16, 1, 1),
(16, 2, 1),
(16, 3, 1),
(16, 4, 1),
(16, 5, 1),
(17, 1, 1),
(17, 2, 1),
(17, 3, 1),
(17, 4, 1),
(17, 5, 1),
(18, 1, 0),
(18, 2, 0),
(18, 3, 0),
(18, 4, 0),
(18, 5, 0),
(20, 1, 0),
(20, 2, 0),
(20, 3, 0),
(20, 4, 0),
(20, 5, 0),
(21, 1, 0),
(21, 2, 0),
(21, 3, 0),
(21, 4, 0),
(21, 5, 0);

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
(1, 'teacher', 'teacher', '$2y$10$wR.TEXmrEL1ooEFH5RhtqeH8hj/YEYkFBjpzcUN90iA/eTCCfrdya', 'Учитель', '2026-01-06 16:33:01', 0),
(16, 'student', 'user', '$2y$10$mf6gpRbLSXlcXWR1TXcz/.331FReqtCAT1L2aNT6gz6weTMKrdaHS', 'user', '2026-05-01 17:49:32', 1),
(17, 'student', 'aaa', '$2y$10$2ZJdFMWOgGnbtV4mVefipeu07LPjJ/8g9imx4pAc0ttV32cp1etJy', 'aaa', '2026-05-02 18:02:48', 1),
(18, 'student', '1', '$2y$10$CF0d7QTp6k3WzZqiG.Ctce5jQlys1XdaXlXqeUCv5NRPDh4ayd.TK', '1', '2026-05-03 02:43:38', 1),
(20, 'student', '333', '$2y$10$Ek2OsPASaosTd0mRm1x81e38zu6xB4O2pOtoOJ8qFtJB2nge9.siO', '233', '2026-05-03 02:43:48', 1),
(21, 'student', '41242143', '$2y$10$FZBBJRR3B39c1SlPNeT4tOfQatbjg40W3ZawNidhqoWwYmwpUAOia', '123124', '2026-05-03 02:43:52', 1);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

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
