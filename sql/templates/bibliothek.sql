-- Vorlage: Schulbibliothek (SQL-Werkstatt)
CREATE TABLE buecher (
    id INTEGER PRIMARY KEY,
    titel TEXT NOT NULL,
    autor TEXT NOT NULL,
    jahr INTEGER NOT NULL,
    genre TEXT NOT NULL
);
CREATE TABLE leser (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    klasse TEXT NOT NULL
);
CREATE TABLE ausleihen (
    id INTEGER PRIMARY KEY,
    buch_id INTEGER NOT NULL REFERENCES buecher (id),
    leser_id INTEGER NOT NULL REFERENCES leser (id),
    ausgeliehen_am TEXT NOT NULL,
    zurueck_am TEXT
);
INSERT INTO buecher (id, titel, autor, jahr, genre) VALUES
    (1, 'Der Hobbit', 'J. R. R. Tolkien', 1937, 'Fantasy'),
    (2, 'Die drei ??? und der Super-Papagei', 'u. a. Hutchinson', 2018, 'Krimi'),
    (3, 'Tschick', 'Wolfgang Herrndorf', 2010, 'Roman'),
    (4, 'Harry Potter und der Stein der Weisen', 'J. K. Rowling', 1997, 'Fantasy'),
    (5, 'Momo', 'Michael Ende', 1973, 'Fantasy'),
    (6, 'Knockout', 'Friedrich Weiller', 2019, 'Krimi'),
    (7, 'Der Sandbox', 'Uwe Timm', 2005, 'Roman');
INSERT INTO leser (id, name, klasse) VALUES
    (1, 'Lena Schmidt', '9a'),
    (2, 'Jonas Weber', '9a'),
    (3, 'Mira Hoffmann', '9b'),
    (4, 'Cem Schulz', '9b'),
    (5, 'Aylin Koch', '10c'),
    (6, 'Tim Bauer', '10c');
INSERT INTO ausleihen (id, buch_id, leser_id, ausgeliehen_am, zurueck_am) VALUES
    (1, 1, 1, '2026-10-05', '2026-10-19'),
    (2, 2, 2, '2026-10-12', NULL),
    (3, 3, 3, '2026-10-20', '2026-11-02'),
    (4, 4, 4, '2026-11-01', NULL),
    (5, 5, 5, '2026-10-25', '2026-11-07'),
    (6, 6, 6, '2026-11-03', NULL),
    (7, 1, 3, '2026-11-04', NULL),
    (8, 7, 2, '2026-10-30', '2026-11-10'),
    (9, 2, 5, '2026-11-05', NULL);
