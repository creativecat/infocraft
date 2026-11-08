-- Vorlage: Schule (SQL-Werkstatt)
CREATE TABLE lehrkraefte (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    fach TEXT NOT NULL
);
CREATE TABLE kurse (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    stufe INTEGER NOT NULL,
    lehrkraft_id INTEGER NOT NULL REFERENCES lehrkraefte (id)
);
CREATE TABLE schuelerinnen (
    id INTEGER PRIMARY KEY,
    vorname TEXT NOT NULL,
    nachname TEXT NOT NULL,
    klasse TEXT NOT NULL
);
CREATE TABLE kursteilnahmen (
    id INTEGER PRIMARY KEY,
    kurs_id INTEGER NOT NULL REFERENCES kurse (id),
    schuelerin_id INTEGER NOT NULL REFERENCES schuelerinnen (id),
    note REAL
);
INSERT INTO lehrkraefte (id, name, fach) VALUES
    (1, 'Frau Fischer', 'Informatik'),
    (2, 'Herr Bauer', 'Mathematik'),
    (3, 'Frau Wolf', 'Deutsch'),
    (4, 'Herr Saez', 'Sport');
INSERT INTO kurse (id, name, stufe, lehrkraft_id) VALUES
    (1, 'Informatik 9a', 9, 1),
    (2, 'Mathematik 9a', 9, 2),
    (3, 'Deutsch 9b', 9, 3),
    (4, 'Informatik 10c', 10, 1),
    (5, 'Sport 9a', 9, 4);
INSERT INTO schuelerinnen (id, vorname, nachname, klasse) VALUES
    (1, 'Lena', 'Schmidt', '9a'),
    (2, 'Jonas', 'Weber', '9a'),
    (3, 'Emma', 'Wagner', '9a'),
    (4, 'Ben', 'Becker', '9a'),
    (5, 'Mira', 'Hoffmann', '9b'),
    (6, 'Cem', 'Schulz', '9b'),
    (7, 'Aylin', 'Koch', '10c'),
    (8, 'Tim', 'Bauer', '10c');
INSERT INTO kursteilnahmen (id, kurs_id, schuelerin_id, note) VALUES
    (1, 1, 1, 1.7),
    (2, 1, 2, 2.3),
    (3, 1, 3, 1.0),
    (4, 1, 4, 3.0),
    (5, 2, 1, 2.0),
    (6, 2, 2, 1.3),
    (7, 2, 3, 2.7),
    (8, 3, 5, 1.7),
    (9, 3, 6, NULL),
    (10, 4, 7, 2.0),
    (11, 4, 8, NULL),
    (12, 5, 1, 1.0);
