-- Vorlage: Das Museum des verschwundenen Meisterwerks (SQL-Werkstatt, Abenteuer #196)
CREATE TABLE raeume (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    fluegel TEXT NOT NULL
);
CREATE TABLE werke (
    id INTEGER PRIMARY KEY,
    titel TEXT NOT NULL,
    kuenstlerin TEXT NOT NULL,
    jahr INTEGER NOT NULL,
    raum_id INTEGER NOT NULL REFERENCES raeume (id),
    verliehen_an TEXT NULL
);
CREATE TABLE besuche (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    raum_id INTEGER NOT NULL REFERENCES raeume (id),
    datum TEXT NOT NULL,
    uhrzeit TEXT NOT NULL
);
CREATE TABLE kameras (
    id INTEGER PRIMARY KEY,
    raum_id INTEGER NOT NULL REFERENCES raeume (id),
    aktiv INTEGER NOT NULL DEFAULT 1
);
INSERT INTO raeume (id, name, fluegel) VALUES
    (1, 'Kuppelsaal', 'Nord'),
    (2, 'Galerie Ost', 'Ost'),
    (3, 'Skulpturenhof', 'Süd'),
    (4, 'Graphikzimmer', 'Ost'),
    (5, 'Foyer', 'Nord');
INSERT INTO werke (id, titel, kuenstlerin, jahr, raum_id, verliehen_an) VALUES
    (1, 'Der Morgen', 'Lina Farb', 1924, 2, NULL),
    (2, 'Blaue Brücke', 'Anton Wasser', 1968, 1, NULL),
    (3, 'Stille Wiese', 'Mira Feld', 1999, 1, 'Kunstverein Nord'),
    (4, 'Roter Turm', 'Anton Wasser', 1970, 4, NULL),
    (5, 'Kleiner Hafen', 'Lina Farb', 1931, 4, NULL),
    (6, 'Wolkenschatten', 'Mira Feld', 2005, 3, NULL),
    (7, 'Nachtflug', 'Jona Stern', 2011, 4, NULL);
INSERT INTO besuche (id, name, raum_id, datum, uhrzeit) VALUES
    (1, 'Vera Nacht', 5, '2026-10-30', '18:05'),
    (2, 'Vera Nacht', 2, '2026-10-30', '20:15'),
    (3, 'Felix Grau', 2, '2026-10-30', '20:42'),
    (4, 'Felix Grau', 4, '2026-10-30', '21:03'),
    (5, 'Tom Reineck', 3, '2026-10-30', '19:30'),
    (6, 'Vera Nacht', 4, '2026-10-30', '20:58'),
    (7, 'Hella Kunz', 1, '2026-10-30', '19:45'),
    (8, 'Felix Grau', 2, '2026-10-30', '21:20'),
    (9, 'Tom Reineck', 2, '2026-10-30', '19:10');
INSERT INTO kameras (id, raum_id, aktiv) VALUES
    (1, 1, 1),
    (2, 2, 0),
    (3, 3, 1),
    (4, 4, 0),
    (5, 5, 1);
