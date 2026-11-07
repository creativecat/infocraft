-- Vorlage: Online-Shop (SQL-Werkstatt)
CREATE TABLE kategorien (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL
);
CREATE TABLE kunden (
    id INTEGER PRIMARY KEY,
    vorname TEXT NOT NULL,
    nachname TEXT NOT NULL,
    stadt TEXT NOT NULL,
    email TEXT NOT NULL
);
CREATE TABLE produkte (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    preis REAL NOT NULL,
    lager INTEGER NOT NULL DEFAULT 0,
    kategorie_id INTEGER NOT NULL REFERENCES kategorien (id)
);
CREATE TABLE bestellungen (
    id INTEGER PRIMARY KEY,
    kunde_id INTEGER NOT NULL REFERENCES kunden (id),
    datum TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'offen'
);
CREATE TABLE bestellpositionen (
    id INTEGER PRIMARY KEY,
    bestellung_id INTEGER NOT NULL REFERENCES bestellungen (id),
    produkt_id INTEGER NOT NULL REFERENCES produkte (id),
    menge INTEGER NOT NULL DEFAULT 1
);
INSERT INTO kategorien (id, name) VALUES (1, 'Bücher'), (2, 'Elektronik'), (3, 'Spielzeug'), (4, 'Sport'), (5, 'Haushalt');
INSERT INTO kunden (id, vorname, nachname, stadt, email) VALUES
    (1, 'Lena', 'Müller', 'München', 'lena.mueller@beispiel.de'),
    (2, 'Jonas', 'Wagner', 'Augsburg', 'jonas.wagner@beispiel.de'),
    (3, 'Sofia', 'Brand', 'München', 'sofia.brand@beispiel.de'),
    (4, 'Tim', 'Hoffmann', 'Nürnberg', 'tim.hoffmann@beispiel.de'),
    (5, 'Amira', 'Öztürk', 'München', 'amira.oeztuerk@beispiel.de'),
    (6, 'Luca', 'Steiner', 'Regensburg', 'luca.steiner@beispiel.de'),
    (7, 'Emma', 'Krüger', 'München', 'emma.krueger@beispiel.de'),
    (8, 'Noah', 'Braun', 'Würzburg', 'noah.braun@beispiel.de'),
    (9, 'Mira', 'Wolf', 'München', 'mira.wolf@beispiel.de'),
    (10, 'Finn', 'Sauer', 'Passau', 'finn.sauer@beispiel.de');
INSERT INTO produkte (id, name, preis, lager, kategorie_id) VALUES
    (1, 'Informatik-Spielbuch', 19.90, 12, 1),
    (2, 'SQL für Einsteiger', 24.50, 8, 1),
    (3, 'Algorithmen verstehen', 29.90, 5, 1),
    (4, 'Merkheft Informatik', 9.99, 20, 1),
    (5, 'Maus', 14.99, 30, 2),
    (6, 'Tastatur', 29.99, 15, 2),
    (7, 'USB-Stick', 8.99, 50, 2),
    (8, 'Kopfhörer', 39.90, 10, 2),
    (9, 'Puzzle', 12.99, 25, 3),
    (10, 'Roboterbausatz', 49.90, 6, 3),
    (11, 'Kartenspiel', 7.99, 40, 3),
    (12, 'Federball-Set', 16.99, 18, 4),
    (13, 'Springseil', 6.99, 35, 4),
    (14, 'Fußball', 22.50, 22, 4),
    (15, 'Wasserkocher', 24.99, 14, 5),
    (16, 'Kerzenset', 11.99, 28, 5),
    (17, 'Brotdose', 13.49, 33, 5);
INSERT INTO bestellungen (id, kunde_id, datum, status) VALUES
    (1, 1, '2026-10-02', 'versendet'),
    (2, 3, '2026-10-03', 'offen'),
    (3, 1, '2026-10-05', 'offen'),
    (4, 5, '2026-10-05', 'versendet'),
    (5, 7, '2026-10-07', 'offen'),
    (6, 2, '2026-10-09', 'storniert'),
    (7, 9, '2026-10-11', 'versendet'),
    (8, 4, '2026-10-12', 'offen'),
    (9, 6, '2026-10-14', 'versendet'),
    (10, 7, '2026-10-16', 'versendet'),
    (11, 10, '2026-10-18', 'storniert'),
    (12, 3, '2026-10-20', 'offen');
INSERT INTO bestellpositionen (id, bestellung_id, produkt_id, menge) VALUES
    (1, 1, 1, 1), (2, 1, 4, 2), (3, 2, 7, 3), (4, 2, 9, 1), (5, 3, 2, 1),
    (6, 4, 8, 1), (7, 4, 11, 2), (8, 5, 5, 1), (9, 5, 14, 1), (10, 6, 10, 1),
    (11, 7, 3, 1), (12, 7, 13, 2), (13, 8, 16, 4), (14, 8, 17, 1), (15, 9, 6, 1),
    (16, 9, 7, 2), (17, 10, 12, 1), (18, 10, 4, 1), (19, 11, 15, 1), (20, 11, 9, 1),
    (21, 12, 1, 2), (22, 12, 11, 1), (23, 3, 4, 1), (24, 12, 8, 1);
