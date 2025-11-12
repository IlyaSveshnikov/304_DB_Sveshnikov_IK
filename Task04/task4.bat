#!/bin/bash
chcp 65001
sqlite3 movies_rating.db < db_init.sql

echo "1. Найти все пары пользователей, оценивших один и тот же фильм. Устранить дубликаты, проверить отсутствие пар с самим собой. Для каждой пары — имена пользователей и название фильма. Показать первые 100 записей."
echo "--------------------------------------------------"
sqlite3 movies_rating.db -box -echo "SELECT DISTINCT u1.name AS 'Пользователь 1', u2.name AS 'Пользователь 2', m.title AS 'Название Фильма' FROM ratings r1 JOIN ratings r2 ON r1.movie_id = r2.movie_id AND r1.user_id < r2.user_id JOIN users u1 ON r1.user_id = u1.id JOIN users u2 ON r2.user_id = u2.id JOIN movies m ON r1.movie_id = m.id LIMIT 100;"
echo

echo "2. Найти 10 самых старых оценок — по одной оценке от каждого пользователя (самая старая оценка каждого пользователя), вывести название фильма, имя пользователя, оценку и дату отзыва (ГГГГ-ММ-ДД)."
echo "--------------------------------------------------"
sqlite3 movies_rating.db -box -echo "SELECT m.title AS 'Название фильма', u.name AS 'Имя', r.rating AS 'Оценка', date(r.timestamp, 'unixepoch') AS 'Дата Отзыва' FROM ratings r JOIN users u ON r.user_id = u.id JOIN movies m ON r.movie_id = m.id JOIN (SELECT user_id, MIN(timestamp) AS first_ts FROM ratings GROUP BY user_id) firsts ON r.user_id = firsts.user_id AND r.timestamp = firsts.first_ts ORDER BY r.timestamp ASC LIMIT 10;"
echo

echo "3. Вывести в одном списке все фильмы с максимальным средним рейтингом и все фильмы с минимальным средним рейтингом. Отсортировать по году выпуска и названию. Добавить колонку 'Рекомендуем' = 'Да' для фильмов с макс. средним, иначе 'Нет'."
echo "--------------------------------------------------"
sqlite3 movies_rating.db -box -echo "WITH avg_ratings AS ( SELECT movie_id, AVG(rating) AS avg_rating FROM ratings GROUP BY movie_id ), bounds AS ( SELECT MAX(avg_rating) AS max_r, MIN(avg_rating) AS min_r FROM avg_ratings ) SELECT m.title AS 'Название', m.year AS 'Год', ROUND(ar.avg_rating, 3) AS 'Средний рейтинг', CASE WHEN ar.avg_rating = (SELECT max_r FROM bounds) THEN 'Да' ELSE 'Нет' END AS 'Рекомендуем' FROM movies m JOIN avg_ratings ar ON m.id = ar.movie_id WHERE ar.avg_rating = (SELECT max_r FROM bounds) OR ar.avg_rating = (SELECT min_r FROM bounds) ORDER BY m.year, m.title;"
echo

echo "4. Вычислить количество оценок и среднюю оценку, которую дали фильмам пользователи-мужчины в период с 2011 по 2014 год (включительно)."
echo "--------------------------------------------------"
sqlite3 movies_rating.db -box -echo "SELECT COUNT(*) AS 'Количество оценок', ROUND(AVG(r.rating), 3) AS 'Средняя оценка' FROM ratings r JOIN users u ON r.user_id = u.id WHERE u.gender = 'male' AND strftime('%Y', datetime(r.timestamp, 'unixepoch')) BETWEEN '2011' AND '2014';"
echo

echo "5. Составить список фильмов с указанием средней оценки и количества пользователей, которые их оценили. Отсортировать по году и названию. Показать первые 20 записей."
echo "--------------------------------------------------"
sqlite3 movies_rating.db -box -echo "SELECT m.title AS 'Название фильма', m.year AS 'Год выпуска', ROUND(AVG(r.rating),3) AS 'Средняя оценка', COUNT(DISTINCT r.user_id) AS 'Кол-во оценивших' FROM movies m JOIN ratings r ON m.id = r.movie_id GROUP BY m.id ORDER BY m.year ASC, m.title ASC LIMIT 20;"
echo

echo "6. Определить самый распространённый жанр и количество фильмов в этом жанре (жанры хранятся в movies.genres; не использовать отдельную таблицу жанров)."
echo "--------------------------------------------------"
sqlite3 movies_rating.db -box -echo "WITH RECURSIVE split(id, genre, rest) AS ( SELECT id, '', COALESCE(genres, '') || '|' FROM movies UNION ALL SELECT id, substr(rest, 1, instr(rest, '|')-1), substr(rest, instr(rest, '|')+1) FROM split WHERE rest <> '' AND instr(rest, '|')>0 ) SELECT genre AS 'Жанр', COUNT(DISTINCT id) AS 'Количество фильмов' FROM split WHERE genre <> '' GROUP BY genre ORDER BY COUNT(DISTINCT id) DESC LIMIT 1;"
echo

echo "7. Вывести список из 10 последних зарегистрированных пользователей в формате 'Фамилия Имя|Дата регистрации' (сначала фамилия, потом имя)."
echo "--------------------------------------------------"
sqlite3 movies_rating.db -noheader -batch -echo "SELECT (CASE WHEN instr(name, ' ')>0 THEN substr(name, instr(name,' ')+1) || ' ' || substr(name, 1, instr(name,' ')-1) ELSE name END) || '|' || register_date AS 'Фамилия Имя|Дата регистрации' FROM users ORDER BY register_date DESC LIMIT 10;"
echo

echo "8. С помощью рекурсивного CTE определить, на какие дни недели приходился ваш день рождения в каждом году."
echo "--------------------------------------------------"
sqlite3 movies_rating.db -box -echo "WITH params AS (SELECT '04' AS mm, '01' AS dd, CAST(strftime('%Y','now') AS INTEGER) AS this_year), years(y) AS ( SELECT 2005 UNION ALL SELECT y+1 FROM years, params WHERE y < params.this_year ) SELECT y AS 'Год', date(y || '-' || params.mm || '-' || params.dd) AS 'Дата', CASE strftime('%w', date(y || '-' || params.mm || '-' || params.dd)) WHEN '0' THEN 'Воскресенье' WHEN '1' THEN 'Понедельник' WHEN '2' THEN 'Вторник' WHEN '3' THEN 'Среда' WHEN '4' THEN 'Четверг' WHEN '5' THEN 'Пятница' WHEN '6' THEN 'Суббота' END AS 'День недели' FROM years, params WHERE date(y || '-' || params.mm || '-' || params.dd) IS NOT NULL ORDER BY y;"
echo