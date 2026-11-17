"""InfoCraft QA-Pipeline: fünf Stationen prüfen die Plattform, erstellen Aufgaben und begutachten sie.

Stationen:
1. QA-Tester: flächendeckende Funktions-/Didaktik-Prüfung aller Bereiche.
2. Lehrplan-Prüfer: Abgleich gegen den Lehrplan PLUS Informatik (Gymnasium Bayern).
3. Aufgaben-Ersteller: Aufgaben aufbauend auf den Berichten aus 1 und 2.
4. Reviewer: prüft die Aufgaben gegen die Berichte aus 1 und 2 und überarbeitet sie.
5. Struktur-Auditor: Navigation, Anklickbarkeit und sinnvolle Inhalts-Verknüpfungen.
"""

from __future__ import annotations

import json
import os
from datetime import timedelta
import urllib.error
import urllib.request
from pathlib import Path
from typing import Literal
from urllib.parse import quote

import mistralai.workflows as workflows
from mistralai.workflows import activity, workflow
from mistralai.workflows.plugins.mistralai import (
    ChatCompletionRequest,
    UserMessage,
    chat_parse_to_model,
)
from pydantic import BaseModel, Field

REPO_OWNER = "creativecat"
REPO_NAME = "infocraft"
MODEL = "mistral-medium-latest"
GITHUB_TOKEN_ENV = "INFOCRAFT_GITHUB_TOKEN"

# Kuratierte Dateien, die der QA-Tester und der Struktur-Auditor sehen sollen.
DUMP_FILES: list[tuple[str, int]] = [
    ("README.md", 9000),
    ("docs/AUFGABEN.md", 12000),
    ("docs/ROADMAP.md", 11000),
    ("src/Router.php", 2000),
    ("src/bootstrap.php", 8000),
    ("src/Models/Module.php", 9000),
    ("src/Controllers/HomeController.php", 8000),
    ("src/Controllers/ModuleController.php", 9000),
    ("src/Controllers/NetworkController.php", 7500),
    ("src/Controllers/SqlWorkshopController.php", 9000),
    ("src/Controllers/HtmlController.php", 9000),
    ("src/Controllers/QuizController.php", 9000),
    ("src/Controllers/WikiController.php", 9000),
    ("templates/layout.php", 9000),
    ("templates/module.php", 12000),
    ("templates/network_workshop.php", 5500),
    ("templates/html_overview.php", 6000),
    ("templates/sql_overview.php", 6000),
]


# ---------------------------------------------------------------------------
# Schemas
# ---------------------------------------------------------------------------


class Befund(BaseModel):
    bereich: str = Field(description="Modul oder Seite, auf der sich der Befund bezieht")
    schwere: Literal["hoch", "mittel", "niedrig"] = "mittel"
    befund: str
    vorschlag: str = ""


class StationBericht(BaseModel):
    zusammenfassung: str
    befunde: list[Befund] = Field(default_factory=list)


class Aufgabe(BaseModel):
    titel: str
    jahrgangsstufe: str
    lehrplanbezug: str
    aufgabentext: str
    material: str = ""
    erwartungshorizont: str = ""


class AufgabenEntwurf(BaseModel):
    zusammenfassung: str
    aufgaben: list[Aufgabe] = Field(default_factory=list)


class ReviewErgebnis(BaseModel):
    zusammenfassung: str
    freigabe: bool = False
    anmerkungen: list[str] = Field(default_factory=list)
    ueberarbeitete_aufgaben: list[Aufgabe] = Field(default_factory=list)


class Verknuepfung(BaseModel):
    von: str
    nach: str
    grund: str


class StrukturBericht(BaseModel):
    zusammenfassung: str
    befunde: list[Befund] = Field(default_factory=list)
    verknuepfungsvorschlaege: list[Verknuepfung] = Field(default_factory=list)


class PipelineInput(BaseModel):
    revision: str = "main"
    schwerpunkt: str = Field(
        default="",
        description="Optionaler Hinweis, worauf die Prüfung besonders achten soll",
    )
    bericht_pr_anlegen: bool = Field(
        default=True,
        description="Legt einen Pull-Request mit dem Bericht an, wenn ein GitHub-Token hinterlegt ist",
    )


class PipelineOutput(BaseModel):
    revision: str
    qa: StationBericht
    lehrplan: StationBericht
    aufgaben: AufgabenEntwurf
    review: ReviewErgebnis
    struktur: StrukturBericht
    bericht_md: str
    pr_url: str | None = None


# ---------------------------------------------------------------------------
# Deterministische Aktivitäten
# ---------------------------------------------------------------------------


def _http_get(url: str, token: str | None = None, accept: str = "application/vnd.github+json") -> bytes:
    headers = {"Accept": accept, "User-Agent": "infocraft-qa-pipeline"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    req = urllib.request.Request(url, headers=headers)
    with urllib.request.urlopen(req, timeout=30) as resp:
        return resp.read()


def _gh_api(path: str, token: str | None = None) -> object:
    url = f"https://api.github.com/repos/{REPO_OWNER}/{REPO_NAME}/{path}"
    return json.loads(_http_get(url, token).decode("utf-8"))


def _raw_file(revision: str, path: str) -> str:
    url = (
        f"https://raw.githubusercontent.com/{REPO_OWNER}/{REPO_NAME}"
        f"/{quote(revision)}/{quote(path)}"
    )
    return _http_get(url, accept="application/octet-stream").decode("utf-8", "replace")


@activity(start_to_close_timeout=timedelta(seconds=120))
async def fetch_repo_dump(revision: str) -> str:
    """Liest kuratierte InfoCraft-Dateien und Verzeichnislisten von GitHub ein."""
    parts: list[str] = []
    for path, limit in DUMP_FILES:
        try:
            text = _raw_file(revision, path)
        except Exception:  # fehlende Datei ist kein Fehler der Pipeline
            continue
        if len(text) > limit:
            text = text[:limit] + "\n... [gekürzt]"
        parts.append(f"### DATEI: {path}\n```\n{text}\n```")
    for listing in ("src/Controllers", "src/Models", "templates", "public/assets/js", "docs"):
        try:
            entries = _gh_api(f"contents/{listing}?ref={quote(revision)}")
            names = ", ".join(e.get("name", "?") for e in entries if isinstance(e, dict))
        except Exception:
            names = "(nicht abrufbar)"
        parts.append(f"### INHALT DES ORDERS {listing}: {names}")
    return "\n\n".join(parts)


@activity(start_to_close_timeout=timedelta(seconds=60))
async def load_lehrplan() -> str:
    """Liest die Lehrplan-PLUS-Texte aus dem mitgelieferten Ordner lehrplan/."""
    base = Path(__file__).resolve()
    for parent in base.parents:
        candidate = parent / "lehrplan"
        if candidate.is_dir():
            parts = []
            for md in sorted(candidate.glob("*.md")):
                parts.append(f"### LEHRPLAN-DATEI: {md.name}\n{md.read_text('utf-8')}")
            if parts:
                return "\n\n".join(parts)
    raise RuntimeError("Lehrplan-Ordner nicht gefunden")


@activity(start_to_close_timeout=timedelta(seconds=60), retry_policy_max_attempts=1)
async def open_report_pr(title: str, filename: str, content: str) -> str | None:
    """Legt einen Pull-Request mit dem Bericht an. Ohne Token läuft die Pipeline im Nur-Bericht-Modus."""
    token = os.environ.get(GITHUB_TOKEN_ENV, "").strip()
    if not token:
        return None
    api = f"https://api.github.com/repos/{REPO_OWNER}/{REPO_NAME}"

    def call(method: str, path: str, body: object | None = None) -> object:
        data = json.dumps(body).encode() if body is not None else None
        req = urllib.request.Request(
            f"{api}/{path}",
            data=data,
            method=method,
            headers={
                "Authorization": f"Bearer {token}",
                "Accept": "application/vnd.github+json",
                "Content-Type": "application/json",
                "User-Agent": "infocraft-qa-pipeline",
            },
        )
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                return json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as err:
            if err.code == 422:  # Branch oder Datei existiert schon
                return {}
            raise

    main = call("GET", "git/ref/heads/main")
    sha = main["object"]["sha"]
    branch = f"agenten/qa-{filename.split('.')[0]}"
    call("POST", "git/refs", {"ref": f"refs/heads/{branch}", "sha": sha})
    call(
        "PUT",
        f"contents/docs/agenten-berichte/{filename}",
        {
            "message": f"Agenten-Bericht: {title}",
            "content": __import__("base64").b64encode(content.encode()).decode(),
            "branch": branch,
        },
    )
    pr = call(
        "POST",
        "pulls",
        {
            "title": f"Agenten-Bericht: {title}",
            "head": branch,
            "base": "main",
            "body": "Automatisch erzeugter QA-Bericht der InfoCraft-Agenten-Pipeline.",
        },
    )
    return pr.get("html_url")


# ---------------------------------------------------------------------------
# LLM-Stationen (als Aktivitäten, damit Timeouts und Retries greifen)
# ---------------------------------------------------------------------------


def _prompt(auftrag: str, quelle: str) -> ChatCompletionRequest:
    return ChatCompletionRequest(
        model=MODEL,
        messages=[
            UserMessage(
                content=(
                    f"{auftrag}\n\n"
                    "Antworte auf Deutsch, sachlich und konkret. Verwende keine Gedankenstriche "
                    "(Em-Dashes) in deinen Texten. Beziehe dich nur auf die genannten Quellen "
                    "und erfinde keine Quellenangaben.\n\n"
                    f"--- QUELLEN ---\n{quelle}"
                )
            )
        ],
        temperature=0.2,
    )


@activity(start_to_close_timeout=timedelta(seconds=300))
async def station_qa(dump: str, schwerpunkt: str) -> StationBericht:
    auftrag = (
        "Du bist intensiver QA-Tester einer Lernplattform (InfoCraft, Informatik, Gymnasium Bayern). "
        "Prüfe anhand der Quellen flächendeckend alle Bereiche (Module, Werkstätten, Quiz, Wiki, "
        "Aufgaben, Prüfungen, Lehrerfunktionen) auf Funktions- und Konsistenzprobleme, "
        "und zusätzlich auf didaktisch-methodische Sinnhaftigkeit: Ist die Lernprogression "
        "stimmig, sind Erklärungen altersgerecht, gibt es sinnlose oder verwirrende Abläufe?"
    )
    if schwerpunkt:
        auftrag += f" Schwerpunkt des Auftraggebers: {schwerpunkt}"
    return await chat_parse_to_model(StationBericht, _prompt(auftrag, dump))


@activity(start_to_close_timeout=timedelta(seconds=300))
async def station_lehrplan(dump: str, lehrplan: str) -> StationBericht:
    auftrag = (
        "Du bist Lehrplan-Experte für Informatik am Gymnasium Bayern (Lehrplan PLUS). "
        "Prüfe, welche Inhalte der Plattform InfoCraft welchen Kompetenzerwartungen des Lehrplans "
        "entsprechen, wo Bezüge fehlen, wo Inhalte den Jahrgangsstufen falsch zugeordnet sind und "
        "welche Lehrplanbereiche noch nicht abgedeckt sind."
    )
    return await chat_parse_to_model(
        StationBericht, _prompt(auftrag, f"{dump}\n\n{lehrplan}")
    )


@activity(start_to_close_timeout=timedelta(seconds=300))
async def station_aufgaben(qa: StationBericht, lehrplan: StationBericht, lehrplan_text: str) -> AufgabenEntwurf:
    auftrag = (
        "Du erstellst praxistaugliche Unterrichtsaufgaben für die Plattform InfoCraft, "
        "aufbauend auf den QA- und Lehrplan-Berichten. Erstelle 4 bis 6 Aufgaben mit Titel, "
        "Jahrgangsstufe, Lehrplanbezug, Aufgabentext, Material und Erwartungshorizont. "
        "Die Aufgaben sollen auf der Plattform tatsächlich umsetzbar sein (passende Werkstatt oder Modul nennen)."
    )
    quelle = (
        f"QA-BERICHT:\n{qa.model_dump_json(indent=1)}\n\n"
        f"LEHRPLAN-BERICHT:\n{lehrplan.model_dump_json(indent=1)}\n\n"
        f"LEHRPLAN-TEXT:\n{lehrplan_text}"
    )
    return await chat_parse_to_model(AufgabenEntwurf, _prompt(auftrag, quelle))


@activity(start_to_close_timeout=timedelta(seconds=300))
async def station_review(
    aufgaben: AufgabenEntwurf, qa: StationBericht, lehrplan: StationBericht
) -> ReviewErgebnis:
    auftrag = (
        "Du bist Reviewer. Prüfe die vorliegenden Aufgaben gegen den QA-Bericht und den "
        "Lehrplan-Bericht: Passen die Aufgaben zur Plattform, sind die Lehrplanbezüge korrekt, "
        "sind Schwierigkeit und Jahrgangsstufe stimmig? Gib eine Freigabe-Entscheidung, "
        "Anmerkungen und die überarbeiteten Aufgaben zurück."
    )
    quelle = (
        f"AUFGABEN-ENTWURF:\n{aufgaben.model_dump_json(indent=1)}\n\n"
        f"QA-BERICHT:\n{qa.model_dump_json(indent=1)}\n\n"
        f"LEHRPLAN-BERICHT:\n{lehrplan.model_dump_json(indent=1)}"
    )
    return await chat_parse_to_model(ReviewErgebnis, _prompt(auftrag, quelle))


@activity(start_to_close_timeout=timedelta(seconds=300))
async def station_struktur(dump: str) -> StrukturBericht:
    auftrag = (
        "Du bist Struktur-Auditor der Website. Prüfe anhand der Routen, Templates und Skripte: "
        "Ist alles sinnvoll aufgebaut, sind alle Bereiche erreichbar und anklickbar, gibt es "
        "Sackgassen oder doppelte Wege? Schlage konkrete Verknüpfungen zwischen Inhalten vor "
        "(zum Beispiel Wiki-Artikel zu Werkstätten, Aufgaben zu Modulen), jeweils mit von, nach und Begründung."
    )
    return await chat_parse_to_model(StrukturBericht, _prompt(auftrag, dump))


def _befunde_md(titel: str, befunde: list[Befund]) -> str:
    lines = [f"## {titel}", ""]
    for b in befunde:
        lines.append(f"- [{b.schwere.upper()}] {b.bereich}: {b.befund}")
        if b.vorschlag:
            lines.append(f"  Vorschlag: {b.vorschlag}")
    return "\n".join(lines) + "\n"


def _bericht_md(
    inp: PipelineInput,
    qa: StationBericht,
    lp: StationBericht,
    auf: AufgabenEntwurf,
    rv: ReviewErgebnis,
    st: StrukturBericht,
) -> str:
    parts = [
        "# InfoCraft QA-Pipeline: Bericht",
        "",
        f"Geprüfte Revision: `{inp.revision}`",
        "",
        _befunde_md("Station 1: QA und Didaktik", qa.befunde),
        qa.zusammenfassung,
        "",
        _befunde_md("Station 2: Lehrplan-Bezug", lp.befunde),
        lp.zusammenfassung,
        "",
        "## Station 3: Erstellte Aufgaben",
        "",
    ]
    for a in auf.aufgaben:
        parts += [
            f"### {a.titel} ({a.jahrgangsstufe})",
            f"Lehrplanbezug: {a.lehrplanbezug}",
            "",
            a.aufgabentext,
            "",
        ]
        if a.material:
            parts += [f"Material: {a.material}", ""]
        if a.erwartungshorizont:
            parts += [f"Erwartungshorizont: {a.erwartungshorizont}", ""]
    parts += [
        "## Station 4: Review",
        "",
        f"Freigabe: {'ja' if rv.freigabe else 'nein'}",
        "",
        rv.zusammenfassung,
        "",
    ]
    parts += [f"- {a}" for a in rv.anmerkungen]
    parts += ["", _befunde_md("Station 5: Struktur und Navigation", st.befunde), st.zusammenfassung, ""]
    parts += ["## Verknüpfungsvorschläge", ""]
    parts += [f"- {v.von} -> {v.nach}: {v.grund}" for v in st.verknuepfungsvorschlaege]
    parts += ["", "## Überarbeitete Aufgaben (nach Review)", ""]
    for a in rv.ueberarbeitete_aufgaben:
        parts += [
            f"### {a.titel} ({a.jahrgangsstufe})",
            f"Lehrplanbezug: {a.lehrplanbezug}",
            "",
            a.aufgabentext,
            "",
        ]
    return "\n".join(parts)


# ---------------------------------------------------------------------------
# Workflow
# ---------------------------------------------------------------------------


@workflow.define(name="infocraft_qa_pipeline")
class InfocraftQaPipeline:
    @workflow.entrypoint
    async def run(self, data: PipelineInput) -> PipelineOutput:
        dump = await fetch_repo_dump(data.revision)
        lehrplan_text = await load_lehrplan()
        qa = await station_qa(dump, data.schwerpunkt)
        lp = await station_lehrplan(dump, lehrplan_text)

        auf = await station_aufgaben(qa, lp, lehrplan_text)
        rv = await station_review(auf, qa, lp)
        st = await station_struktur(dump)

        bericht = _bericht_md(data, qa, lp, auf, rv, st)
        pr_url: str | None = None
        if data.bericht_pr_anlegen:
            stamp = str(workflows.workflow.uuid4())[:8]
            pr_url = await open_report_pr(
                f"Prüfbericht {stamp}", f"pruefbericht-{stamp}.md", bericht
            )
        return PipelineOutput(
            revision=data.revision,
            qa=qa,
            lehrplan=lp,
            aufgaben=auf,
            review=rv,
            struktur=st,
            bericht_md=bericht,
            pr_url=pr_url,
        )
