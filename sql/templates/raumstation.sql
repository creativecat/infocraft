-- Vorlage: Raumstation Alpha: Meteoritenschauer trifft die Station (SQL-Werkstatt, Abenteuer #196)
CREATE TABLE module (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    sektor TEXT NOT NULL,
    energie INTEGER NOT NULL DEFAULT 50
);
CREATE TABLE crew (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    rolle TEXT NOT NULL,
    modul_id INTEGER NOT NULL REFERENCES module (id),
    status TEXT NOT NULL DEFAULT 'einsatzbereit'
);
CREATE TABLE tanks (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    modul_id INTEGER NOT NULL REFERENCES module (id),
    fuellstand INTEGER NOT NULL DEFAULT 0,
    defekt INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE fracht (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    kategorie TEXT NOT NULL,
    gewicht INTEGER NOT NULL DEFAULT 0,
    modul_id INTEGER NOT NULL REFERENCES module (id)
);
INSERT INTO module (id, name, sektor, energie) VALUES
    (1, 'Brücke', 'A', 62),
    (2, 'Labor', 'B', 48),
    (3, 'Wohnring', 'B', 55),
    (4, 'Maschinenraum', 'A', 41),
    (5, 'Gewächshaus', 'C', 70),
    (6, 'Frachtraum', 'C', 33);
INSERT INTO crew (id, name, rolle, modul_id, status) VALUES
    (1, 'Kommandantin Kira', 'Kommandantin', 1, 'einsatzbereit'),
    (2, 'Pilot Paulo', 'Pilot', 1, 'einsatzbereit'),
    (3, 'Biologin Bea', 'Biologin', 5, 'kalt'),
    (4, 'Techniker Tarek', 'Techniker', 4, 'vermisst'),
    (5, 'Ärztin Aylin', 'Ärztin', 3, 'einsatzbereit'),
    (6, 'Koch Rasmus', 'Koch', 3, 'einsatzbereit'),
    (7, 'Navigatorin Nadia', 'Navigatorin', 1, 'vermisst'),
    (8, 'Ingenieurin Ilka', 'Ingenieurin', 2, 'kalt');
INSERT INTO tanks (id, name, modul_id, fuellstand, defekt) VALUES
    (1, 'Tank Brücke 1', 1, 88, 0),
    (2, 'Tank Labor 1', 2, 12, 1),
    (3, 'Tank Wohnring 1', 3, 74, 0),
    (4, 'Tank Maschinenraum 1', 4, 9, 1),
    (5, 'Tank Gewächshaus 1', 5, 95, 0),
    (6, 'Tank Frachtraum 1', 6, 40, 1),
    (7, 'Tank Wohnring 2', 3, 68, 0);
INSERT INTO fracht (id, name, kategorie, gewicht, modul_id) VALUES
    (1, 'Ersatzpaneel', 'Reparatur', 220, 4),
    (2, 'Sauerstoffflaschen', 'Versorgung', 85, 6),
    (3, 'Kühlschrank', 'Versorgung', 130, 3),
    (4, 'Werkzeugkiste', 'Reparatur', 45, 4),
    (5, 'Laborkiste', 'Forschung', 300, 2),
    (6, 'Nahrungspakete', 'Versorgung', 210, 3);
