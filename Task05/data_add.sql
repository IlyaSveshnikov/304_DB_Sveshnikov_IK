-- 1. Добавление 5 новых пользователей (себя + 4 соседа по группе)
INSERT INTO users (name, email, gender_id, register_date, occupation) VALUES
('Савин Руслан', 'ruslan.savin@example.com', 1, datetime('now'), 'student'),
('Пазухина Анастасия', 'anastasia.pazuhina@example.com', 2, datetime('now'), 'student'),
('Свешников Илья', 'ilya.sveshnikov@example.com', 1, datetime('now'), 'student'),
('Сергеева Ольга', 'olga.sergeeva@example.com', 2, datetime('now'), 'student'),
('Тумайкина Дарья', 'darya.tumaykina@example.com', 2, datetime('now'), 'student');

-- 2. Добавление 3 новых фильмов разных жанров
INSERT INTO movies (title, year) VALUES
('Интерстеллар 2', 2025),
('Аватар 3', 2025),
('Дюна 3', 2025);

-- 3. Связь с СУЩЕСТВУЮЩИМИ жанрами (БЕЗ INSERT genres!)
INSERT INTO movie_genres (movie_id, genre_id) VALUES
((SELECT id FROM movies WHERE title = 'Интерстеллар 2'), 
 (SELECT id FROM genres WHERE name = 'Sci-Fi')),
((SELECT id FROM movies WHERE title = 'Аватар 3'), 
 (SELECT id FROM genres WHERE name = 'Fantasy')),
((SELECT id FROM movies WHERE title = 'Дюна 3'), 
 (SELECT id FROM genres WHERE name = 'Adventure'));

-- 4. 3 новых отзыва ОТ СВЕШНИКОВА ИЛЬИ (себя)
INSERT INTO ratings (user_id, movie_id, rating, timestamp) VALUES
((SELECT id FROM users WHERE email = 'ilya.sveshnikov@example.com'), 
 (SELECT id FROM movies WHERE title = 'Интерстеллар 2'), 5, datetime('now')),
((SELECT id FROM users WHERE email = 'ilya.sveshnikov@example.com'), 
 (SELECT id FROM movies WHERE title = 'Аватар 3'), 4, datetime('now')),
((SELECT id FROM users WHERE email = 'ilya.sveshnikov@example.com'), 
 (SELECT id FROM movies WHERE title = 'Дюна 3'), 5, datetime('now'));
