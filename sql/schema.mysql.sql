-- InfoCraft: Schema für MySQL/MariaDB
-- Minimale Nutzerdaten (Vor-/Nachname, Klasse, Rolle), bewusst DSGVO-freundlich gehalten.

CREATE TABLE IF NOT EXISTS classes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(20) NOT NULL,
    diagram_auto TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_classes_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    login_name VARCHAR(200) NULL,
    password_hash VARCHAR(255) NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'student',
    class_id INT UNSIGNED NULL,
    external_id VARCHAR(191) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_login (login_name),
    UNIQUE KEY uq_users_external (external_id),
    KEY idx_users_class (class_id),
    CONSTRAINT fk_users_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_teachers (
    teacher_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (teacher_id, class_id),
    CONSTRAINT fk_ct_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ct_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS folders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_folders_owner (owner_id),
    CONSTRAINT fk_folders_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id INT UNSIGNED NOT NULL,
    folder_id INT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'code',
    language VARCHAR(20) NULL,
    content MEDIUMTEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_projects_owner (owner_id),
    KEY idx_projects_folder (folder_id),
    CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_projects_folder FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Java-Projekte: mehrere Quelldateien pro Projekt (eine Klasse pro Datei).
-- projects.content bleibt die Konkatenation aller Dateien (Kompatibilitaet).
CREATE TABLE IF NOT EXISTS project_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    position INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pfiles_project (project_id),
    CONSTRAINT fk_pfiles_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Aufgabenpool (Lehrkräfte) und Austeilen an Klassen oder Einzelne.
-- shared = 1: Aufgabe liegt bewusst im gemeinsamen Pool aller Lehrkräfte.
CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'allgemein',
    level INT NOT NULL DEFAULT 6,
    shared TINYINT(1) NOT NULL DEFAULT 0,
    sample_solution TEXT NOT NULL,
    max_points INT UNSIGNED NOT NULL DEFAULT 0,
    rubric TEXT NOT NULL,
    late_lock TINYINT(1) NOT NULL DEFAULT 0,
    topic_id INT UNSIGNED NOT NULL DEFAULT 0,
    description_format VARCHAR(8) NOT NULL DEFAULT 'text',
    variant_of INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tasks_teacher (teacher_id),
    KEY idx_tasks_shared (shared),
    CONSTRAINT fk_tasks_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_targets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    due_at VARCHAR(16) NULL,
    visible_from VARCHAR(16) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tt_task (task_id),
    KEY idx_tt_class (class_id),
    KEY idx_tt_user (user_id),
    CONSTRAINT fk_tt_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
    CONSTRAINT fk_tt_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
    CONSTRAINT fk_tt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Abgaben der Schülerinnen und Schüler (eine je Aufgabe, überschreibbar)
CREATE TABLE IF NOT EXISTS task_submissions (
    task_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    content MEDIUMTEXT NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, student_id),
    KEY idx_ts_student (student_id),
    CONSTRAINT fk_ts_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
    CONSTRAINT fk_ts_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Korrektur je Abgabe: source 'lk' = Lehrkraft, 'api' = KI-Vorschlag
-- (checked = 0, bis die Lehrkraft ihn übernommen hat)
CREATE TABLE IF NOT EXISTS task_gradings (
    task_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    grade VARCHAR(10) NOT NULL DEFAULT '',
    comment TEXT NOT NULL,
    source VARCHAR(4) NOT NULL DEFAULT 'lk',
    checked TINYINT(1) NOT NULL DEFAULT 1,
    graded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, student_id),
    KEY idx_tg_student (student_id),
    CONSTRAINT fk_tg_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
    CONSTRAINT fk_tg_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Themengebiete nach LehrplanPLUS (pro Jahrgangsstufe kuratiert, ergänzbar)
CREATE TABLE IF NOT EXISTS task_topics (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    level INT NOT NULL DEFAULT 6,
    sort INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_topics_level (level)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Materialien/Vorlagen einer Aufgabe: content = base64-kodierte Datei
CREATE TABLE IF NOT EXISTS task_materials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id INT UNSIGNED NOT NULL,
    filename VARCHAR(200) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    size INT UNSIGNED NOT NULL DEFAULT 0,
    sort INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_materials_task (task_id),
    CONSTRAINT fk_materials_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ToDos: student_id NULL, template_id NULL = Vorlage der Lehrkraft,
-- student_id gesetzt + template_id gesetzt = Abhak-Kopie einer Schülerin bzw.
-- eines Schülers für eine Vorlage, student_id gesetzt und template_id NULL
CREATE TABLE IF NOT EXISTS task_todos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NULL,
    template_id INT UNSIGNED NULL,
    title VARCHAR(300) NOT NULL,
    done TINYINT(1) NOT NULL DEFAULT 0,
    sort INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_todos_task (task_id),
    KEY idx_todos_student (student_id),
    CONSTRAINT fk_todos_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
    CONSTRAINT fk_todos_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- KI-Schnittstelle: je Lehrkraft (user_id) oder schulweit (user_id NULL).
-- mode 'chat' = OpenAI-/Mistral-kompatible Chat-API, 'json' = eigener Endpunkt.
CREATE TABLE IF NOT EXISTS ai_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL UNIQUE,
    url VARCHAR(255) NOT NULL DEFAULT '',
    api_key VARCHAR(255) NOT NULL DEFAULT '',
    model VARCHAR(100) NOT NULL DEFAULT '',
    mode VARCHAR(4) NOT NULL DEFAULT 'chat',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ai_user (user_id),
    CONSTRAINT fk_ai_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sql_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    level INT NOT NULL DEFAULT 9,
    is_adventure TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sql_templates_slug (slug)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sql_missions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    position INT NOT NULL DEFAULT 0,
    kind VARCHAR(10) NOT NULL DEFAULT 'query',
    title VARCHAR(200) NOT NULL,
    story TEXT NOT NULL,
    hint TEXT NOT NULL,
    solution_sql TEXT NOT NULL,
    check_sql TEXT NOT NULL,
    check_expected VARCHAR(200) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_missions_template (template_id),
    CONSTRAINT fk_missions_template FOREIGN KEY (template_id) REFERENCES sql_templates (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sql_grants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    permission VARCHAR(10) NOT NULL DEFAULT 'read',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_grants_template_user (template_id, user_id),
    CONSTRAINT fk_grants_template FOREIGN KEY (template_id) REFERENCES sql_templates (id) ON DELETE CASCADE,
    CONSTRAINT fk_grants_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sql_solves (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    grant_id INT UNSIGNED NOT NULL,
    mission_id INT UNSIGNED NOT NULL,
    solved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_solves (grant_id, mission_id),
    CONSTRAINT fk_solves_grant FOREIGN KEY (grant_id) REFERENCES sql_grants (id) ON DELETE CASCADE,
    CONSTRAINT fk_solves_mission FOREIGN KEY (mission_id) REFERENCES sql_missions (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Quiz-Arena: Quizze, Fragen, Austeilung, Ergebnisse
CREATE TABLE IF NOT EXISTS quizzes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    level INT NOT NULL DEFAULT 6,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quizzes_teacher (teacher_id),
    CONSTRAINT fk_quizzes_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id INT UNSIGNED NOT NULL,
    position INT NOT NULL DEFAULT 0,
    question VARCHAR(500) NOT NULL,
    answer_1 VARCHAR(300) NOT NULL,
    answer_2 VARCHAR(300) NOT NULL,
    answer_3 VARCHAR(300) NOT NULL,
    answer_4 VARCHAR(300) NOT NULL,
    correct TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_questions_quiz (quiz_id),
    CONSTRAINT fk_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_targets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quiz_targets_quiz (quiz_id),
    CONSTRAINT fk_qt_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE,
    CONSTRAINT fk_qt_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
    CONSTRAINT fk_qt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    score INT NOT NULL DEFAULT 0,
    total INT NOT NULL DEFAULT 0,
    answers MEDIUMTEXT NULL,
    finished_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_quiz (quiz_id),
    KEY idx_attempts_user (user_id),
    CONSTRAINT fk_attempts_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE,
    CONSTRAINT fk_attempts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;


-- Prüfungen & Schularbeiten (Phase 5): Vorbereitung, Durchführung mit Sperre, Korrektur, Herausgabe
CREATE TABLE IF NOT EXISTS exams (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT UNSIGNED NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'pruefung',
    title VARCHAR(200) NOT NULL,
    description VARCHAR(1000) NOT NULL DEFAULT '',
    level INT NOT NULL DEFAULT 6,
    phase VARCHAR(20) NOT NULL DEFAULT 'vorbereitung',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY idx_exams_teacher (teacher_id),
    CONSTRAINT fk_exams_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_groups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    template_project_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_groups_exam (exam_id),
    CONSTRAINT fk_groups_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
    CONSTRAINT fk_groups_template FOREIGN KEY (template_project_id) REFERENCES projects (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_members (
    group_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, user_id),
    KEY idx_members_user (user_id),
    CONSTRAINT fk_members_group FOREIGN KEY (group_id) REFERENCES exam_groups (id) ON DELETE CASCADE,
    CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id INT UNSIGNED NOT NULL,
    position INT NOT NULL DEFAULT 1,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    be INT NOT NULL DEFAULT 1,
    sample_solution TEXT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_etasks_exam (exam_id),
    CONSTRAINT fk_etasks_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_works (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_works_exam_user (exam_id, user_id),
    KEY idx_works_user (user_id),
    CONSTRAINT fk_works_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
    CONSTRAINT fk_works_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_works_group FOREIGN KEY (group_id) REFERENCES exam_groups (id) ON DELETE SET NULL,
    CONSTRAINT fk_works_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_answers (
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    answer MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    CONSTRAINT fk_answers_task FOREIGN KEY (task_id) REFERENCES exam_tasks (id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_gradings (
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    be_erreicht DECIMAL(5,1) NOT NULL DEFAULT 0,
    comment TEXT NOT NULL,
    source VARCHAR(10) NOT NULL DEFAULT 'lk',
    checked TINYINT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    CONSTRAINT fk_gradings_task FOREIGN KEY (task_id) REFERENCES exam_tasks (id) ON DELETE CASCADE,
    CONSTRAINT fk_gradings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_diagrams (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'manuell',
    source_id INT UNSIGNED NULL,
    mermaid MEDIUMTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_diagrams_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Wiki: Nachschlage-Seiten der Lehrkraefte, sichtbar ab der angegebenen Jahrgangsstufe
CREATE TABLE IF NOT EXISTS wiki_pages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    level INT NOT NULL DEFAULT 6,
    position INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wiki_teacher (teacher_id),
    KEY idx_wiki_level (level),
    CONSTRAINT fk_wiki_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Modul-Freigabe je Klasse: 'freigeben' = vorzeitig sichtbar, 'sperren' = ausgeblendet,
-- kein Eintrag = Jahrgangsstufe laut LehrplanPLUS entscheidet
CREATE TABLE IF NOT EXISTS module_settings (
    class_id INT UNSIGNED NOT NULL,
    module_key VARCHAR(40) NOT NULL,
    mode VARCHAR(16) NOT NULL,
    PRIMARY KEY (class_id, module_key),
    CONSTRAINT fk_ms_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Präsentationen der Lehrkräfte: Folien als JSON (Text- und Code-Folien),
-- sichtbar für Schülerinnen und Schüler ab der angegebenen Jahrgangsstufe.
CREATE TABLE IF NOT EXISTS presentations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(60) DEFAULT NULL,
    level INT NOT NULL DEFAULT 6,
    content MEDIUMTEXT NOT NULL,
    position INT NOT NULL DEFAULT 0,
    seed_version INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_pres_slug (slug),
    KEY idx_pres_teacher (teacher_id),
    KEY idx_pres_level (level),
    CONSTRAINT fk_pres_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
