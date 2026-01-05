-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Янв 05 2026 г., 07:08
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
-- База данных: `lab_system`
--

-- --------------------------------------------------------

--
-- Структура таблицы `analysis_indicators`
--

CREATE TABLE `analysis_indicators` (
  `id` int(11) NOT NULL,
  `analysis_type_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `norm_text` varchar(255) NOT NULL,
  `default_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `analysis_indicators`
--

INSERT INTO `analysis_indicators` (`id`, `analysis_type_id`, `name`, `norm_text`, `default_price`) VALUES
(1, 1, 'Общий билирубин', '5,0–21,0 мкмоль/л', 0.00),
(2, 1, 'Общий белок', '65–85 г/л', 0.00),
(3, 1, 'АСТ', 'Ж: до 31 Е/л, М: до 37 Е/л', 0.00),
(4, 1, 'АЛТ', 'Ж: до 31 Е/л, М: до 40 Е/л', 0.00),
(5, 1, 'Альбумин', '35–55 г/л', 0.00),
(6, 1, 'Щелочная фосфатаза', '70–270 Е/л', 0.00),
(7, 1, 'Сахар крови', '4,0–6,1 ммоль/л', 0.00),
(8, 1, 'Холестерин', 'до 5,20 ммоль/л', 0.00),
(9, 1, 'Креатинин крови', '53–115 мкмоль/л', 0.00),
(10, 1, 'Мочевина крови', '2,1–8,32 ммоль/л', 0.00),
(11, 1, 'Амилаза крови', 'до 220 Е/л', 0.00),
(12, 1, 'Амилаза мочи', 'до 900 Е/л', 0.00),
(13, 1, 'Мочевая кислота', 'М: 262–452 мкмоль/л, Ж: 137–393 мкмоль/л', 0.00),
(14, 1, 'Кальций', '2,02–2,60 ммоль/л', 0.00),
(15, 1, 'Фосфор', '0,70–1,60 ммоль/л', 0.00),
(16, 1, 'Калий', '3,6–5,5 ммоль/л', 0.00),
(17, 1, 'Железо', '7,6–44,8 ммоль/л', 0.00),
(18, 1, 'Ревмафактор', 'до 8 МЕ/мл', 0.00),
(19, 1, 'С-реактивный белок', 'до 6 МЕ/мл', 0.00),
(20, 1, 'Антистрептолизин-О', 'до 200 МЕ/мл', 0.00),
(21, 2, 'Гемоглобин', 'М: 120–160 г/л, Ж: 110–140 г/л', 0.00),
(22, 2, 'Эритроциты', 'М: 4,0–5,0 ·10^12/л, Ж: 3,7–4,7 ·10^12/л', 0.00),
(23, 2, 'Тромбоциты', '180–320 ·10^9/л', 0.00),
(24, 2, 'Цветной показатель', '0,85–1,05', 0.00),
(25, 2, 'Лейкоциты', '4,0–9,0 ·10^9/л', 0.00),
(26, 2, 'Миелоциты', '0 ·10^9/л', 0.00),
(27, 2, 'Метамиелоциты', '0 ·10^9/л', 0.00),
(28, 2, 'Палочкоядерные нейтрофилы', '1–6 %', 0.00),
(29, 2, 'Сегментоядерные нейтрофилы', '47–72 %', 0.00),
(30, 2, 'Эозинофилы', '1,0–5,0 %', 0.00),
(31, 2, 'Базофилы', '0–1,0 %', 0.00),
(32, 2, 'Моноциты', '1–4 %', 0.00),
(33, 2, 'Лимфоциты', '17–37 %', 0.00),
(34, 2, 'Плазматические клетки', '0–0,5 %', 0.00),
(35, 2, 'СОЭ', 'М: 1–10 мм/ч, Ж: 2–15 мм/ч', 0.00),
(36, 3, 'Миқдор', '—', 0.00),
(37, 3, 'Ранг', '—', 0.00),
(38, 3, 'Вазни хос', '1003–1020', 0.00),
(39, 3, 'рН', '4,5–8,0', 0.00),
(40, 3, 'Канд(глюкоза)', 'Нест–Ҳаст', 0.00),
(41, 3, 'Сафеда', 'Нест–Ҳаст', 0.00),
(42, 3, 'Билрубин', '—', 0.00),
(43, 3, 'Кетон', 'до 17 мкмоль/л', 0.00),
(44, 3, 'Лейкоситҳо', '0–6', 0.00),
(45, 3, 'Эритроситҳо', '0–2', 0.00),
(46, 3, 'Эпителияи ҳамвор', '—', 0.00),
(47, 3, 'Эпителияи гузариш', '—', 0.00),
(48, 3, 'Эпителияи гурдагӣ', '—', 0.00),
(49, 3, 'Намак', '—', 0.00),
(50, 3, 'Цилиндрҳо', '—', 0.00),
(51, 3, 'Луоб', '—', 0.00),
(52, 3, 'Бактерияҳо', '—', 0.00),
(53, 3, 'Флора', '—', 0.00),
(54, 3, 'Мукоид', '—', 0.00),
(55, 3, 'Сангчаҳо', '—', 0.00),
(56, 4, 'ЦИТОМЕГАЛОВИРУС lgG', 'Обнаружено (референс диапазон 1.0–0.0, как в исходных данных)', 0.00),
(57, 4, 'ВИРУС ГЕРПЕСА I и II–тип lgG', 'По результату лаборатории', 0.00),
(58, 4, 'ВИРУС ПРОСТОГО ГЕРПЕСА 2 типа lgG', 'Обнаружено (1.0–200.0 усл. ед.)', 0.00),
(59, 4, 'Краснуха IgM', 'По результату лаборатории', 0.00),
(60, 4, 'TOXOPLAZMA GONDII lgG', 'Не Обнаружено', 0.00),
(61, 4, 'UREAPLAZMA UREALYTICUM lgG', 'Не Обнаружено', 0.00),
(62, 4, 'MICOPLAZMA HOMINIS lgG', 'Не Обнаружено', 0.00),
(63, 4, 'TRICHOMONAS VAGINALIS lgG', 'По результату лаборатории', 0.00),
(64, 4, 'CHLAMIDIA TRACHOMATIS IgG', 'Не Обнаружено', 0.00),
(65, 4, 'Гиалуроновая кислота', '1.0–100.0 ng/ml', 0.00),
(66, 4, 'Аминотерминальный пептид проколлагена 3 типа', '1.0–30.0 ng/ml', 0.00),
(67, 4, 'Ламинин', '1.0–50.0 ng/ml', 0.00),
(68, 4, 'Коллаген 4 типа', '1.0–50.0 ng/ml', 0.00),
(69, 4, 'Холиглицин', '0.0–2.7 ng/ml', 0.00),
(70, 5, 'Гемоглобин', 'М: 120–160 г/л, Ж: 110–140 г/л', 0.00),
(71, 5, 'Эритроциты', 'М: 4,0–5,0 ×10^12/л, Ж: 3,7–4,7 ×10^12/л', 0.00),
(72, 5, 'Тромбоциты', '180–320 ×10^9/л', 0.00),
(73, 5, 'Цветной показатель', '0,85–1,05', 0.00),
(74, 5, 'Лейкоциты', '4,0–9,0 ×10^9/л', 0.00),
(75, 5, 'Миелоциты', '0 ×10^9/л', 0.00),
(76, 5, 'Метамиелоциты', '0 ×10^9/л', 0.00),
(77, 5, 'Палочкоядерные нейтрофилы', '1–6 %', 0.00),
(78, 5, 'Сегментоядерные нейтрофилы', '47–72 %', 0.00),
(79, 5, 'Эозинофилы', '1,0–5,0 %', 0.00),
(80, 5, 'Базофилы', '0–1,0 %', 0.00),
(81, 5, 'Моноциты', '1–4 %', 0.00),
(82, 5, 'Лимфоциты', '17–37 %', 0.00),
(83, 5, 'Плазматические клетки', '0–0,5 %', 0.00),
(84, 5, 'СОЭ', 'М: 1–10 мм/ч, Ж: 2–15 мм/ч', 0.00),
(85, 6, 'Миқдор', 'Норма: 1000–2000 мл/сут', 0.00),
(86, 6, 'Ранг', 'Сабзи зард то зард', 0.00),
(87, 6, 'Вазни хос', '1003–1020', 0.00),
(88, 6, 'рН', '4.5–8.0', 0.00),
(89, 6, 'Канд (глюкоза)', 'Нест – Ҳаст', 0.00),
(90, 6, 'Сафеда', 'Нест – Ҳаст', 0.00),
(91, 6, 'Билрубин', 'Нест', 0.00),
(92, 6, 'Кетон', 'то 17 мкмоль/л', 0.00),
(93, 6, 'Лейкоситҳо', '0–6 дар майдони назар', 0.00),
(94, 6, 'Эритроситҳо', '01–02 дар майдони назар', 0.00),
(95, 6, 'Эпителияи ҳамвор', 'Якка – то 2–3', 0.00),
(96, 6, 'Эпителияи гузариш', 'Якка', 0.00),
(97, 6, 'Эпителияи гурдагӣ', 'Нест', 0.00),
(98, 6, 'Намак', 'Якка кристаллҳо', 0.00),
(99, 6, 'Цилиндрҳо', 'Нест – якка', 0.00),
(100, 6, 'Луоб', 'Нест – ками', 0.00),
(101, 6, 'Бактерияҳо', 'Нест', 0.00),
(102, 6, 'Флора', 'Нормальная', 0.00),
(103, 6, 'Мукоид', 'Нест – ками', 0.00),
(104, 6, 'Сангчаҳо', 'Нест', 0.00);

-- --------------------------------------------------------

--
-- Структура таблицы `analysis_results`
--

CREATE TABLE `analysis_results` (
  `id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `indicator_id` int(11) NOT NULL,
  `value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `analysis_tests`
--

CREATE TABLE `analysis_tests` (
  `id` int(11) NOT NULL,
  `analysis_type_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `analysis_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `analysis_types`
--

CREATE TABLE `analysis_types` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `analysis_types`
--

INSERT INTO `analysis_types` (`id`, `code`, `name`) VALUES
(1, 'BA', 'Биохимический анализ'),
(2, 'CBC', 'Общий анализ крови (ТУХ)'),
(3, 'URINE', 'Общий анализ мочи (ТУП)'),
(4, 'IFA', 'ИФА'),
(5, 'TUH', 'Общий анализ крови'),
(6, 'TUP', 'Общий анализ мочи');

-- --------------------------------------------------------

--
-- Структура таблицы `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `sex` enum('M','F') NOT NULL,
  `phones` varchar(100) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `card_number` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `patients`
--

INSERT INTO `patients` (`id`, `first_name`, `last_name`, `sex`, `phones`, `birth_date`, `age`, `card_number`, `created_at`) VALUES
(1, 'Мухаммад', 'Махкамов', 'M', NULL, NULL, 20, '13042005', '2025-11-23 11:56:08'),
(2, 'Баходур', 'махкамов', 'M', NULL, '2005-04-13', 50, '05011978', '2025-11-23 14:20:34'),
(3, 'олимов', 'олимов', 'M', NULL, '2025-11-20', 13, '2', '2025-11-23 15:48:48'),
(4, 'магомед', 'Махкамов', 'M', '+992920212787', NULL, 20, '5', '2025-11-27 07:19:01'),
(5, 'абдуллох', 'махкамов', 'M', '+992 92 021 2787', NULL, 20, '7', '2025-11-27 07:29:16'),
(6, 'Фирдавс', 'Сайфидиннов', 'M', '+992918451401', '2005-03-01', 20, '010305', '2025-11-27 14:38:30'),
(7, 'Абду', 'Олимов', 'M', '+992918575781', '1991-01-01', 32, '010191', '2025-11-27 14:41:39');

-- --------------------------------------------------------

--
-- Структура таблицы `patient_analyses`
--

CREATE TABLE `patient_analyses` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) NOT NULL,
  `analysis_type_id` int(11) NOT NULL,
  `check_number` varchar(50) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `patient_analyses`
--

INSERT INTO `patient_analyses` (`id`, `patient_id`, `doctor_id`, `analysis_type_id`, `check_number`, `total_price`, `created_at`) VALUES
(1, 1, 1, 1, 'BA-20251123093811-1-563', 0.00, '2025-11-23 08:38:11'),
(2, NULL, 1, 1, 'BA-20251123094344-1-367', 0.00, '2025-11-23 08:43:44'),
(3, NULL, 1, 1, 'BA-20251123094431-1-292', 0.00, '2025-11-23 08:44:31'),
(4, 1, 1, 1, 'BA-20251123095635-1-492', 0.00, '2025-11-23 08:56:35'),
(5, 1, 1, 1, 'BA-20251123095941-1-614', 0.00, '2025-11-23 08:59:41'),
(6, 1, 1, 1, 'BA-20251123100128-1-846', 0.00, '2025-11-23 09:01:28'),
(7, 1, 1, 1, 'BA-20251123111358-1-599', 0.00, '2025-11-23 10:13:58'),
(8, 1, 1, 1, 'BA-20251123112509-1-283', 0.00, '2025-11-23 10:25:09'),
(9, 1, 1, 1, 'BA-20251123121324-1-153', 0.00, '2025-11-23 11:13:24'),
(10, 2, 1, 1, 'BA-20251123122138-1-984', 0.00, '2025-11-23 11:21:38'),
(11, 3, 2, 1, 'BA-20251123135017-2-108', 0.00, '2025-11-23 12:50:17'),
(12, 1, 1, 1, 'BA-20251124013837-1-610', 0.00, '2025-11-24 00:38:37'),
(13, 2, 2, 1, 'BA-20251125101410-2-506', 0.00, '2025-11-25 09:14:10'),
(14, 1, 2, 5, 'TUH-20251125103906-2-353', 0.00, '2025-11-25 09:39:06'),
(15, 2, 2, 6, 'TUP-20251125110539-2-293', 0.00, '2025-11-25 10:05:39'),
(16, 2, 2, 4, 'IFA-20251125111045-2-164', 40.00, '2025-11-25 10:10:45'),
(17, 3, 1, 4, 'IFA-20251125133433-1-371', 0.00, '2025-11-25 12:34:33'),
(18, 2, 4, 5, 'TUH-20251125135230-4-253', 0.00, '2025-11-25 12:52:30'),
(19, 2, 2, 1, 'BA-20251126033332-2-934', 0.00, '2025-11-26 02:33:32'),
(20, 1, 2, 1, 'BA-20251126141224-2-936', 0.00, '2025-11-26 13:12:24'),
(21, 5, 1, 1, 'BA-20251127052937-1-429', 0.00, '2025-11-27 04:29:37'),
(22, 5, 1, 1, 'BA-20251127070303-1-199', 0.00, '2025-11-27 06:03:03'),
(23, 6, 1, 4, 'IFA-20251127123847-1-899', 0.00, '2025-11-27 11:38:47'),
(24, 7, 1, 6, 'TUP-20251127124158-1-556', 0.00, '2025-11-27 11:41:58'),
(25, 6, 1, 6, 'TUP-20251129130752-1-166', 0.00, '2025-11-29 12:07:52'),
(26, 6, 3, 1, 'BA-20251203022410-3-501', 0.00, '2025-12-03 01:24:10'),
(27, 5, 5, 5, 'TUH-20260105065312-5-775', 0.00, '2026-01-05 05:53:12');

-- --------------------------------------------------------

--
-- Структура таблицы `patient_analysis_items`
--

CREATE TABLE `patient_analysis_items` (
  `id` int(11) NOT NULL,
  `patient_analysis_id` int(11) NOT NULL,
  `indicator_id` int(11) NOT NULL,
  `result_value` decimal(10,2) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `patient_analysis_items`
--

INSERT INTO `patient_analysis_items` (`id`, `patient_analysis_id`, `indicator_id`, `result_value`, `price`) VALUES
(1, 1, 3, 19.05, 0.00),
(2, 1, 17, 22.61, 0.00),
(3, 1, 20, 22.84, 0.00),
(4, 2, 10, 8.20, 0.00),
(5, 2, 14, 2.16, 0.00),
(6, 3, 10, 8.20, 0.00),
(7, 3, 14, 2.16, 0.00),
(8, 4, 1, 14.24, 0.00),
(9, 4, 8, 5.14, 0.00),
(10, 4, 9, 106.30, 0.00),
(11, 4, 11, 168.21, 0.00),
(12, 5, 1, 5.80, 0.00),
(13, 5, 8, 4.31, 0.00),
(14, 5, 9, 104.60, 0.00),
(15, 5, 11, 162.19, 0.00),
(16, 6, 17, 16.46, 0.00),
(17, 6, 20, 19.86, 0.00),
(18, 7, 6, 209.95, 0.00),
(19, 7, 9, 88.73, 0.00),
(20, 8, 20, 121.69, 0.00),
(21, 9, 4, 20.03, 0.00),
(22, 9, 8, 3.62, 0.00),
(23, 9, 12, 234.17, 0.00),
(24, 10, 5, 48.98, 0.00),
(25, 10, 9, 114.25, 0.00),
(26, 10, 15, 0.92, 0.00),
(27, 10, 18, 1.37, 0.00),
(28, 11, 2, 74.97, 0.00),
(29, 11, 6, 141.59, 0.00),
(30, 11, 8, 3.14, 0.00),
(31, 11, 12, 1234.00, 0.00),
(32, 12, 10, 3.24, 0.00),
(33, 12, 11, 149.41, 0.00),
(34, 12, 18, 1.47, 0.00),
(35, 13, 2, 75.37, 0.00),
(36, 13, 5, 35.89, 0.00),
(37, 13, 9, 113.35, 0.00),
(38, 14, 71, 4.17, 0.00),
(39, 14, 73, 0.86, 0.00),
(40, 14, 76, 0.00, 0.00),
(41, 14, 80, 0.98, 0.00),
(42, 15, 92, 11.28, 0.00),
(43, 15, 93, 5.62, 0.00),
(44, 15, 96, 2.79, 0.00),
(45, 16, 64, 19.71, 20.00),
(46, 16, 67, 22.00, 20.00),
(47, 17, 57, 37.89, 0.00),
(48, 17, 60, 13.50, 0.00),
(49, 17, 67, 86.92, 0.00),
(50, 18, 77, 1.59, 0.00),
(51, 18, 80, 1.00, 0.00),
(52, 18, 81, 1.38, 0.00),
(53, 19, 5, 50.56, 0.00),
(54, 19, 8, 3.40, 0.00),
(55, 20, 17, 15.90, 0.00),
(56, 20, 18, 0.14, 0.00),
(57, 21, 2, 70.58, 0.00),
(58, 21, 5, 51.53, 0.00),
(59, 21, 8, 3.48, 0.00),
(60, 21, 9, 57.09, 0.00),
(61, 22, 2, 66.72, 0.00),
(62, 22, 5, 41.65, 0.00),
(63, 22, 8, 4.52, 0.00),
(64, 22, 9, 102.28, 0.00),
(65, 23, 57, 24.87, 0.00),
(66, 23, 60, 57.60, 0.00),
(67, 23, 64, 76.40, 0.00),
(68, 24, 86, 1.49, 0.00),
(69, 24, 89, 1.00, 0.00),
(70, 24, 90, 1.00, 0.00),
(71, 24, 93, 1.94, 0.00),
(72, 24, 96, 0.09, 0.00),
(73, 25, 85, 2.59, 0.00),
(74, 25, 86, 2.79, 0.00),
(75, 25, 87, 1017.71, 0.00),
(76, 25, 88, 7.88, 0.00),
(77, 25, 89, 0.00, 0.00),
(78, 25, 90, 1.00, 0.00),
(79, 25, 91, 0.90, 0.00),
(80, 25, 92, 12.14, 0.00),
(81, 25, 93, 4.91, 0.00),
(82, 25, 94, 0.76, 0.00),
(83, 25, 95, 2.50, 0.00),
(84, 25, 96, 1.39, 0.00),
(85, 25, 97, 0.00, 0.00),
(86, 25, 98, 2.71, 0.00),
(87, 25, 99, 0.21, 0.00),
(88, 25, 100, 1.62, 0.00),
(89, 25, 101, 1.00, 0.00),
(90, 25, 102, 0.84, 0.00),
(91, 25, 103, 0.07, 0.00),
(92, 25, 104, 0.00, 0.00),
(93, 26, 2, 72.37, 0.00),
(94, 26, 5, 48.92, 0.00),
(95, 27, 70, 156.88, 0.00),
(96, 27, 71, 4.28, 0.00),
(97, 27, 72, 287.45, 0.00),
(98, 27, 73, 0.87, 0.00),
(99, 27, 74, 5.44, 0.00),
(100, 27, 75, 0.00, 0.00),
(101, 27, 76, 0.00, 0.00),
(102, 27, 77, 1.08, 0.00),
(103, 27, 78, 52.39, 0.00),
(104, 27, 79, 4.70, 0.00),
(105, 27, 80, 0.72, 0.00),
(106, 27, 81, 1.48, 0.00),
(107, 27, 82, 30.64, 0.00),
(108, 27, 83, 0.40, 0.00),
(109, 27, 84, 13.18, 0.00);

-- --------------------------------------------------------

--
-- Структура таблицы `receipts`
--

CREATE TABLE `receipts` (
  `id` int(11) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `analysis_type_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `hospital_name` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `login` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` enum('admin','doctor') NOT NULL DEFAULT 'doctor',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`, `password_hash`, `full_name`, `role`, `created_at`) VALUES
(1, 'Мага', '$2y$10$wt0dVuK9LR/P.oJyCRP/.e/gjue4HUzf2n7fBnfYXNnCohexrTY2.', 'Махкамов Мухаммадмустафо Баходурович', 'doctor', '2025-11-23 13:22:47'),
(2, 'Абду', '$2y$10$DMNLWIKmi0PgBXb5BeUw4O9rliWtIU41tXbUhGUkeA4A.bNBIB.JG', 'Олимов А', 'doctor', '2025-11-23 15:47:53'),
(4, 'Федя', '$2y$10$tGg37u3Pf39EhGUK7v0QA.AnuLTbX.TL2DgPBbu25igObBz7uJo5y', 'Сайфидинов Фирдавс Сулаймонович', 'doctor', '2025-11-25 15:51:26'),
(5, 'ггг', '$2y$10$D2ddE2UDwb2/sD8c.wsG8OMvu6zmRUHgjUTElt4K4DEUjYrkQioLG', 'Олимов Абдурахмон Одилович', 'admin', '2026-01-05 08:42:19');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `analysis_indicators`
--
ALTER TABLE `analysis_indicators`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_indicators_type` (`analysis_type_id`);

--
-- Индексы таблицы `analysis_results`
--
ALTER TABLE `analysis_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_results_test` (`test_id`),
  ADD KEY `fk_results_indicator` (`indicator_id`);

--
-- Индексы таблицы `analysis_tests`
--
ALTER TABLE `analysis_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tests_type` (`analysis_type_id`),
  ADD KEY `fk_tests_patient` (`patient_id`),
  ADD KEY `fk_tests_doctor` (`doctor_id`);

--
-- Индексы таблицы `analysis_types`
--
ALTER TABLE `analysis_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Индексы таблицы `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `card_number` (`card_number`);

--
-- Индексы таблицы `patient_analyses`
--
ALTER TABLE `patient_analyses`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `patient_analysis_items`
--
ALTER TABLE `patient_analysis_items`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `fk_receipts_patient` (`patient_id`),
  ADD KEY `fk_receipts_doctor` (`doctor_id`),
  ADD KEY `fk_receipts_type` (`analysis_type_id`),
  ADD KEY `fk_receipts_test` (`test_id`);

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
-- AUTO_INCREMENT для таблицы `analysis_indicators`
--
ALTER TABLE `analysis_indicators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT для таблицы `analysis_results`
--
ALTER TABLE `analysis_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `analysis_tests`
--
ALTER TABLE `analysis_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `analysis_types`
--
ALTER TABLE `analysis_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `patient_analyses`
--
ALTER TABLE `patient_analyses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT для таблицы `patient_analysis_items`
--
ALTER TABLE `patient_analysis_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT для таблицы `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `analysis_indicators`
--
ALTER TABLE `analysis_indicators`
  ADD CONSTRAINT `fk_indicators_type` FOREIGN KEY (`analysis_type_id`) REFERENCES `analysis_types` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `analysis_results`
--
ALTER TABLE `analysis_results`
  ADD CONSTRAINT `fk_results_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `analysis_indicators` (`id`),
  ADD CONSTRAINT `fk_results_test` FOREIGN KEY (`test_id`) REFERENCES `analysis_tests` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `analysis_tests`
--
ALTER TABLE `analysis_tests`
  ADD CONSTRAINT `fk_tests_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_tests_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_tests_type` FOREIGN KEY (`analysis_type_id`) REFERENCES `analysis_types` (`id`);

--
-- Ограничения внешнего ключа таблицы `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `fk_receipts_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_receipts_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_receipts_test` FOREIGN KEY (`test_id`) REFERENCES `analysis_tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_receipts_type` FOREIGN KEY (`analysis_type_id`) REFERENCES `analysis_types` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
