-- InfoCraft: Schema für SQLite (Entwicklung und Demo)
-- Minimale Nutzerdaten (Vor-/Nachname, Klasse, Rolle), bewusst DSGVO-freundlich gehalten.

CREATE TABLE IF NOT EXISTS classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
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

-- Aufgabenpool (Lehrkräfte) und Austeilen an Klassen oder Einzelne
CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    type TEXT NOT NULL DEFAULT 'allgemein',
    level INTEGER NOT NULL DEFAULT 6,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS task_targets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL REFERENCES tasks (id) ON DELETE CASCADE,
    class_id INTEGER NULL REFERENCES classes (id) ON DELETE CASCADE,
    user_id INTEGER NULL REFERENCES users (id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
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
