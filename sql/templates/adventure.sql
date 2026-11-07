-- Vorlage: Dateninsel-Abenteuer (SQL-Werkstatt)
CREATE TABLE orte (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    beschreibung TEXT NOT NULL
);
CREATE TABLE bewohner (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    ort_id INTEGER NOT NULL REFERENCES orte (id),
    beruf TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'frei'
);
CREATE TABLE gegenstaende (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    wert INTEGER NOT NULL DEFAULT 0,
    ort_id INTEGER NOT NULL REFERENCES orte (id),
    besitzer_id INTEGER NULL REFERENCES bewohner (id)
);
CREATE TABLE wege (
    von_ort_id INTEGER NOT NULL REFERENCES orte (id),
    nach_ort_id INTEGER NOT NULL REFERENCES orte (id),
    tage INTEGER NOT NULL DEFAULT 1
);
INSERT INTO orte (id, name, beschreibung) VALUES
    (1, 'Hafen', 'Der Anlegeplatz der Insel'),
    (2, 'Wald', 'Dichter Wald mit Nebelschwaden'),
    (3, 'Bergdorf', 'Ein Dorf hoch am Berg'),
    (4, 'Alter Turm', 'Ein vergessener Wachturm'),
    (5, 'Quelle', 'Die magische Quelle der Insel');
INSERT INTO bewohner (id, name, ort_id, beruf, status) VALUES
    (1, 'Häuptling Mika', 1, 'Häuptling', 'frei'),
    (2, 'Ritter Roland', 3, 'Ritter', 'frei'),
    (3, 'Kräuterfrau Ida', 2, 'Kräuterfrau', 'frei'),
    (4, 'Fischer Finn', 1, 'Fischer', 'frei'),
    (5, 'Wächter Willi', 4, 'Wächter', 'verzaubert'),
    (6, 'Bäckerin Berta', 3, 'Bäckerin', 'verzaubert'),
    (7, 'Jäger Jannik', 2, 'Jäger', 'frei'),
    (8, 'Magierin Mira', 5, 'Magierin', 'frei'),
    (9, 'Zimmerfrau Zoe', 3, 'Zimmerin', 'verzaubert'),
    (10, 'Pilot Paul', 1, 'Pilot', 'frei');
INSERT INTO gegenstaende (id, name, wert, ort_id, besitzer_id) VALUES
    (1, 'Goldmünze', 50, 1, NULL),
    (2, 'Silbernes Schwert', 120, 2, NULL),
    (3, 'Kompass', 35, 1, NULL),
    (4, 'Zaubertrank', 80, 5, NULL),
    (5, 'Schatzkarte', 60, 4, NULL),
    (6, 'Fackel', 15, 2, NULL),
    (7, 'Seil', 20, 1, NULL),
    (8, 'Diamant', 200, 4, NULL);
INSERT INTO wege (von_ort_id, nach_ort_id, tage) VALUES
    (1, 2, 1), (2, 3, 2), (3, 4, 1), (4, 5, 2), (1, 3, 3), (2, 5, 3);
