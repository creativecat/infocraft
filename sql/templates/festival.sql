-- Vorlage: Festival der jungen Bühnen (SQL-Werkstatt, Abenteuer #196)
CREATE TABLE buehnen (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    groesse TEXT NOT NULL
);
CREATE TABLE bands (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    genre TEXT NOT NULL,
    herkunft TEXT NOT NULL
);
CREATE TABLE auftritte (
    id INTEGER PRIMARY KEY,
    band_id INTEGER NOT NULL REFERENCES bands (id),
    buehne_id INTEGER NOT NULL REFERENCES buehnen (id),
    tag TEXT NOT NULL,
    uhrzeit TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'geplant'
);
CREATE TABLE technik (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    kategorie TEXT NOT NULL,
    buehne_id INTEGER NOT NULL REFERENCES buehnen (id)
);
INSERT INTO buehnen (id, name, groesse) VALUES
    (1, 'Waldbühne', 'groß'),
    (2, 'Zelt', 'klein'),
    (3, 'Wiese', 'mittel'),
    (4, 'Kur', 'klein');
INSERT INTO bands (id, name, genre, herkunft) VALUES
    (1, 'Die Ponys', 'Indie', 'München'),
    (2, 'Static Moon', 'Elektro', 'Berlin'),
    (3, 'Grünstreifen', 'Punk', 'Hamburg'),
    (4, 'Lolas Lied', 'Folk', 'Köln'),
    (5, 'Riff Raffel', 'Rock', 'Stuttgart'),
    (6, 'Kapelle Chaos', 'Brass', 'Leipzig');
INSERT INTO auftritte (id, band_id, buehne_id, tag, uhrzeit, status) VALUES
    (1, 1, 1, 'Freitag', '18:00', 'bestaetigt'),
    (2, 2, 1, 'Freitag', '20:00', 'bestaetigt'),
    (3, 3, 2, 'Freitag', '19:00', 'unklar'),
    (4, 4, 3, 'Samstag', '14:00', 'geplant'),
    (5, 5, 1, 'Samstag', '19:00', 'bestaetigt'),
    (6, 6, 2, 'Samstag', '16:00', 'unklar'),
    (7, 1, 3, 'Sonntag', '15:00', 'geplant'),
    (8, 2, 4, 'Sonntag', '17:30', 'geplant'),
    (9, 3, 3, 'Samstag', '21:30', 'geplant');
INSERT INTO technik (id, name, kategorie, buehne_id) VALUES
    (1, 'Mischpult Groß', 'Ton', 1),
    (2, 'Boxenturm links', 'Ton', 1),
    (3, 'Boxenturm rechts', 'Ton', 1),
    (4, 'Scheinwerfer-Set', 'Licht', 2),
    (5, 'Mischpult Klein', 'Ton', 2),
    (6, 'Stromverteiler', 'Energie', 3),
    (7, 'LED-Wand', 'Licht', 1);
