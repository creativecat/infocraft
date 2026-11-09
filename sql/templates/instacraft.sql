-- Vorlage: InstaCraft (SQL-Werkstatt): Social-Feed mit Profilen, Posts, Likes und Followern
CREATE TABLE nutzer (
    id INTEGER PRIMARY KEY,
    benutzername TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    bio TEXT NOT NULL DEFAULT ''
);
CREATE TABLE posts (
    id INTEGER PRIMARY KEY,
    nutzer_id INTEGER NOT NULL REFERENCES nutzer (id),
    beschreibung TEXT NOT NULL,
    datum TEXT NOT NULL
);
CREATE TABLE likes (
    post_id INTEGER NOT NULL REFERENCES posts (id),
    nutzer_id INTEGER NOT NULL REFERENCES nutzer (id),
    PRIMARY KEY (post_id, nutzer_id)
);
CREATE TABLE kommentare (
    id INTEGER PRIMARY KEY,
    post_id INTEGER NOT NULL REFERENCES posts (id),
    nutzer_id INTEGER NOT NULL REFERENCES nutzer (id),
    text TEXT NOT NULL,
    datum TEXT NOT NULL
);
CREATE TABLE folgt (
    folger_id INTEGER NOT NULL REFERENCES nutzer (id),
    folgt_id INTEGER NOT NULL REFERENCES nutzer (id),
    PRIMARY KEY (folger_id, folgt_id)
);
INSERT INTO nutzer (id, benutzername, name, bio) VALUES
    (1, 'luna', 'Luna Sommer', 'Fotos aus den Bergen'),
    (2, 'max', 'Max Winter', 'Alles zu Robotik'),
    (3, 'sofia', 'Sofia Brand', 'Zeichnungen und Skizzen'),
    (4, 'tim', 'Tim Hoffmann', 'Klassenclown mit Kamera'),
    (5, 'amira', 'Amira Öztürk', 'Backprojekte am Wochenende'),
    (6, 'luca', 'Luca Steiner', 'Sport und Action'),
    (7, 'emma', 'Emma Krüger', 'Bücher und mehr'),
    (8, 'noah', 'Noah Braun', 'Musik ist mein Leben');
INSERT INTO posts (id, nutzer_id, beschreibung, datum) VALUES
    (1, 1, 'Sonnenaufgang am Berg', '2026-09-30'),
    (2, 1, 'Wanderung mit der Klasse', '2026-10-03'),
    (3, 2, 'Mein neuer Roboter', '2026-10-05'),
    (4, 3, 'Neue Skizze fertig', '2026-10-05'),
    (5, 4, 'Foto aus dem Schulgarten', '2026-10-07'),
    (6, 5, 'Sonntagskuchen', '2026-10-11'),
    (7, 6, 'Basketball-Turnier', '2026-10-12'),
    (8, 7, 'Mein Lesestapel', '2026-10-15'),
    (9, 8, 'Konzert gestern', '2026-10-17'),
    (10, 2, 'Roboter baut einen Turm', '2026-10-19'),
    (11, 5, 'Plätzchen im Ofen', '2026-10-21'),
    (12, 1, 'Nebelsee am Morgen', '2026-10-24');
INSERT INTO likes (post_id, nutzer_id) VALUES
    (1, 2), (1, 3), (1, 4), (1, 5),
    (2, 1), (3, 1), (3, 4), (3, 5), (3, 6), (3, 7),
    (4, 1), (4, 5), (5, 3), (5, 6), (6, 4), (6, 7), (6, 8),
    (7, 1), (7, 6), (8, 2), (8, 3), (9, 6), (9, 8),
    (10, 1), (10, 3), (10, 8), (11, 2), (12, 3), (12, 5);
INSERT INTO kommentare (id, post_id, nutzer_id, text, datum) VALUES
    (1, 1, 3, 'Wunderschön!'),
    (2, 2, 4, 'War ein toller Ausflug'),
    (3, 3, 1, 'Coole Konstruktion'),
    (4, 4, 5, 'Das kannst du super'),
    (5, 6, 7, 'Rezept bitte!'),
    (6, 7, 1, 'Glückwunsch zum Sieg'),
    (7, 9, 6, 'Wann kommt das nächste Konzert?'),
    (8, 12, 5, 'Magischer Moment');
INSERT INTO folgt (folger_id, folgt_id) VALUES
    (2, 1), (3, 1), (4, 1), (5, 1), (6, 1), (7, 1), (8, 1),
    (1, 2), (4, 2), (5, 2), (8, 2),
    (1, 3), (5, 3), (2, 4), (3, 4), (6, 4),
    (1, 5), (4, 5), (7, 5), (2, 6), (6, 7), (1, 8);
