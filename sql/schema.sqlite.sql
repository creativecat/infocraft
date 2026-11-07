-- InfoCraft: Schema für SQLite (Entwicklung und Demo)
-- Minimale Nutzerdaten (Vor-/Nachname, Klasse, Rolle), bewusst DSGVO-freundlich gehalten.

CREATE TABLE IF NOT EXISTS classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    diagram_auto INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    login_name TEXT NULL UNIQUE,
    password_hash TEXT NULL,
    role TEXT NOT NULL DEFAULT 'student',
    class_id INTEGER NULL REFERENCES classes (id) ON DELETE SET NULL,
    external_id TEXT NULL UNIQUE,
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Zuordnung Lehrkraft zu Klasse (wer darf Passwörter zurücksetzen und Inhalte austeilen)
CREATE TABLE IF NOT EXISTS class_teachers (
    teacher_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    class_id INTEGER NOT NULL REFERENCES classes (id) ON DELETE CASCADE,
    PRIMARY KEY (teacher_id, class_id)
);

-- Ordner und Projekte (Code-Werkstatt, später auch HTML-Werkstatt)
CREATE TABLE IF NOT EXISTS folders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    position INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    folder_id INTEGER NULL REFERENCES folders (id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    type TEXT NOT NULL DEFAULT 'code',
    language TEXT NULL,
    content TEXT NOT NULL DEFAULT '',
    created_by INTEGER NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Java-Projekte: mehrere Quelldateien pro Projekt (eine Klasse pro Datei).
-- projects.content bleibt die Konkatenation aller Dateien (Kompatibilitaet).
CREATE TABLE IF NOT EXISTS project_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL REFERENCES projects (id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    content TEXT NOT NULL DEFAULT '',
    position INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Aufgabenpool (Lehrkräfte) und Austeilen an Klassen oder Einzelne.
-- shared = 1: Aufgabe liegt bewusst im gemeinsamen Pool aller Lehrkräfte.
CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    type TEXT NOT NULL DEFAULT 'allgemein',
    level INTEGER NOT NULL DEFAULT 6,
    shared INTEGER NOT NULL DEFAULT 0,
    sample_solution TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS task_targets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL REFERENCES tasks (id) ON DELETE CASCADE,
    class_id INTEGER NULL REFERENCES classes (id) ON DELETE CASCADE,
    user_id INTEGER NULL REFERENCES users (id) ON DELETE CASCADE,
    due_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Abgaben der Schülerinnen und Schüler (eine je Aufgabe, überschreibbar)
CREATE TABLE IF NOT EXISTS task_submissions (
    task_id INTEGER NOT NULL REFERENCES tasks (id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    content TEXT NOT NULL DEFAULT '',
    submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, student_id)
);

-- Korrektur je Abgabe: source 'lk' = Lehrkraft, 'api' = KI-Vorschlag
-- (checked = 0, bis die Lehrkraft ihn übernommen hat)
CREATE TABLE IF NOT EXISTS task_gradings (
    task_id INTEGER NOT NULL REFERENCES tasks (id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    grade TEXT NOT NULL DEFAULT '',
    comment TEXT NOT NULL DEFAULT '',
    source TEXT NOT NULL DEFAULT 'lk',
    checked INTEGER NOT NULL DEFAULT 1,
    graded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, student_id)
);

-- KI-Schnittstelle: je Lehrkraft (user_id) oder schulweit (user_id NULL).
-- mode 'chat' = OpenAI-/Mistral-kompatible Chat-API, 'json' = eigener Endpunkt.
CREATE TABLE IF NOT EXISTS ai_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL UNIQUE REFERENCES users (id) ON DELETE CASCADE,
    url TEXT NOT NULL DEFAULT '',
    api_key TEXT NOT NULL DEFAULT '',
    model TEXT NOT NULL DEFAULT '',
    mode TEXT NOT NULL DEFAULT 'chat',
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- SQL-Werkstatt: Vorlagen, Missionen (Adventure/Aufgaben), Zuweisungen mit Rechten
CREATE TABLE IF NOT EXISTS sql_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    level INTEGER NOT NULL DEFAULT 9,
    is_adventure INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sql_missions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER NOT NULL REFERENCES sql_templates (id) ON DELETE CASCADE,
    position INTEGER NOT NULL DEFAULT 0,
    kind TEXT NOT NULL DEFAULT 'query',
    title TEXT NOT NULL,
    story TEXT NOT NULL DEFAULT '',
    hint TEXT NOT NULL DEFAULT '',
    solution_sql TEXT NOT NULL,
    check_sql TEXT NOT NULL DEFAULT '',
    check_expected TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sql_grants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER NOT NULL REFERENCES sql_templates (id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    permission TEXT NOT NULL DEFAULT 'read',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (template_id, user_id)
);

CREATE TABLE IF NOT EXISTS sql_solves (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    grant_id INTEGER NOT NULL REFERENCES sql_grants (id) ON DELETE CASCADE,
    mission_id INTEGER NOT NULL REFERENCES sql_missions (id) ON DELETE CASCADE,
    solved_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (grant_id, mission_id)
);

-- Quiz-Arena: Quizze, Fragen, Austeilung, Ergebnisse
CREATE TABLE IF NOT EXISTS quizzes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    level INTEGER NOT NULL DEFAULT 6,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quiz_id INTEGER NOT NULL REFERENCES quizzes (id) ON DELETE CASCADE,
    position INTEGER NOT NULL DEFAULT 0,
    question TEXT NOT NULL,
    answer_1 TEXT NOT NULL,
    answer_2 TEXT NOT NULL,
    answer_3 TEXT NOT NULL,
    answer_4 TEXT NOT NULL,
    correct INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quiz_targets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quiz_id INTEGER NOT NULL REFERENCES quizzes (id) ON DELETE CASCADE,
    class_id INTEGER NULL REFERENCES classes (id) ON DELETE CASCADE,
    user_id INTEGER NULL REFERENCES users (id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quiz_id INTEGER NOT NULL REFERENCES quizzes (id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    score INTEGER NOT NULL DEFAULT 0,
    total INTEGER NOT NULL DEFAULT 0,
    answers TEXT NOT NULL DEFAULT '',
    finished_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- Prüfungen & Schularbeiten (Phase 5): Vorbereitung, Durchführung mit Sperre, Korrektur, Herausgabe
CREATE TABLE IF NOT EXISTS exams (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    kind TEXT NOT NULL DEFAULT 'pruefung',
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    level INTEGER NOT NULL DEFAULT 6,
    phase TEXT NOT NULL DEFAULT 'vorbereitung',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TEXT NULL,
    released_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS exam_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    exam_id INTEGER NOT NULL REFERENCES exams (id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    template_project_id INTEGER NULL REFERENCES projects (id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS exam_members (
    group_id INTEGER NOT NULL REFERENCES exam_groups (id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    PRIMARY KEY (group_id, user_id)
);

CREATE TABLE IF NOT EXISTS exam_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    exam_id INTEGER NOT NULL REFERENCES exams (id) ON DELETE CASCADE,
    position INTEGER NOT NULL DEFAULT 1,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    be INTEGER NOT NULL DEFAULT 1,
    sample_solution TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS exam_works (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    exam_id INTEGER NOT NULL REFERENCES exams (id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    group_id INTEGER NULL REFERENCES exam_groups (id) ON DELETE SET NULL,
    project_id INTEGER NULL REFERENCES projects (id) ON DELETE SET NULL,
    started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at TEXT NULL,
    UNIQUE (exam_id, user_id)
);

CREATE TABLE IF NOT EXISTS exam_answers (
    task_id INTEGER NOT NULL REFERENCES exam_tasks (id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    answer TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id)
);

CREATE TABLE IF NOT EXISTS exam_gradings (
    task_id INTEGER NOT NULL REFERENCES exam_tasks (id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    be_erreicht REAL NOT NULL DEFAULT 0,
    comment TEXT NOT NULL DEFAULT '',
    source TEXT NOT NULL DEFAULT 'lk',
    checked INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id)
);

-- Klassendiagramme: selbst gezeichnet oder automatisch aus Skripten/SQL erzeugt
CREATE TABLE IF NOT EXISTS class_diagrams (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    kind TEXT NOT NULL DEFAULT 'manuell',
    source_id INTEGER NULL,
    mermaid TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Wiki: Nachschlage-Seiten der Lehrkraefte, sichtbar ab der angegebenen Jahrgangsstufe
CREATE TABLE IF NOT EXISTS wiki_pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    content TEXT NOT NULL DEFAULT '',
    level INTEGER NOT NULL DEFAULT 6,
    position INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Modul-Freigabe je Klasse: 'freigeben' = vorzeitig sichtbar, 'sperren' = ausgeblendet,
-- kein Eintrag = Jahrgangsstufe laut LehrplanPLUS entscheidet
CREATE TABLE IF NOT EXISTS module_settings (
    class_id INTEGER NOT NULL REFERENCES classes (id) ON DELETE CASCADE,
    module_key TEXT NOT NULL,
    mode TEXT NOT NULL,
    PRIMARY KEY (class_id, module_key)
);

-- Präsentationen der Lehrkräfte: Folien als JSON (Text- und Code-Folien),
-- sichtbar für Schülerinnen und Schüler ab der angegebenen Jahrgangsstufe.
CREATE TABLE IF NOT EXISTS presentations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    slug TEXT,
    level INTEGER NOT NULL DEFAULT 6,
    content TEXT NOT NULL DEFAULT '[]',
    position INTEGER NOT NULL DEFAULT 0,
    seed_version INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_pres_slug ON presentations (slug);
