# 🎯 Umsetzungsplan: AI Lese-Lern-Trainer (`mod_aireading`)

**Erstellt:** 2025-11-11
**Version:** 1.2 (DI-erweitert)
**Autor:** Dr. Peter Mayer, ISB Bayern
**Basis:** Spezifikation.md + Review-Delta

---

## 📋 Übersicht

Dieser Plan beschreibt die schrittweise Implementierung des AI Lese-Lern-Trainers als Moodle Activity Module. Das Plugin ermöglicht automatisiertes Feedback zur Leseflüssigkeit und -genauigkeit von Schülern durch Speech-to-Text-Analyse.

### Aktueller Stand (Plugin-Skelett vorhanden)
✅ Grundstruktur vorhanden
✅ Basis-Datenbanktabelle `aireading`
✅ Erste Capabilities vorhanden
✅ Backup/Restore Grundgerüst vorhanden
✅ Event-Klassen rudimentär vorhanden
✅ **Phase 7: Feedback-Darstellung** - Vollständig implementiert
✅ **Phase 8: Statistiken und Visualisierung** - Vollständig implementiert
✅ **Phase 9: Grade API Integration** - Vollständig implementiert
✅ **Phase 10: Security & Validation** - Vollständig implementiert

### Delta-Ziele dieser Erweiterung
Diese Version integriert zusätzliche Spezifikationen basierend auf kritischer Analyse:
1. Datenmodell-Feinjustierung (weitere Felder + Indizes)
2. Vollständige Capability-Matrix und differenzierter Audiozugriff
3. Formalisierte Event-Payloads
4. Trennung zwischen Basis-Metriken (objektiv) und Heuristik-Scores (pädagogisch)
5. Vollständige Frontend-UX-Spezifikation für Grundschüler & Fremdsprachen-Lernende
6. Reporting-Erweiterungen (ohne CSV/Excel Export, wie vorgegeben)
7. Edge-Case- und Fehlerfall-Definition
8. Backup/Restore Präzisierung für neue Tabellen & Fileareas
9. Erweiterte Usecase-Deklaration (Lesebeginner, Fremdsprachen) – (Entscheidung: beide vollständig umsetzen)

### Zu implementieren (inkl. Erweiterungen)
- Erweiterte Datenbanktabellen für Versuche und Analysen (mit zusätzlichen Feldern)
- Konfigurationsformular mit Lesetext und Parametern (erweiterte Einstellungen Anfänger & Fremdsprache)
- Audio-Aufnahme-Interface (WebRTC + VAD, optimierte Aufnahmeparameter: Mono 16kHz, WebM/Opus)
- STT-Integration mit `local_ai_manager`
- Analyse-Engine (Genauigkeit, Flüssigkeit, Aussprache) + klare Metrik/Heuristik-Trennung
- Feedback-Darstellung mit visuellen Markierungen und vereinfachter Kind-Ansicht
- Grade-API-Integration
- Reporting (Fortschritt, Fehler-Häufigkeit, Aussprache-Trends)
- Statistiken und Visualisierungen
- Events mit definierter Payload
- Backup/Restore für neue Tabellen + Fileareas
- Edge-Case Handling (Stille, Abbruch, fehlende Confidence)

---

## 🗂️ Phase 1: Datenbank-Schema erweitern

**Ziel:** Vollständiges Datenmodell für Texte, Versuche und Analysen

### 1.1 Erweitere `aireading` Haupttabelle
**Datei:** `db/install.xml`

**Neue Felder (Ergänzungen):**
- `readingtext` (TEXT) - Der vom Lehrer eingegebene Lesetext
- `readingtextformat` (INT) - Textformat
- `targetwpm` (INT) - Ziel-Wörter pro Minute
- `maxattempts` (INT) - Maximale Anzahl Versuche (0 = unbegrenzt)
- `grademethod` (INT) - Bewertungsmethode (1=beste, 2=letzte, 3=Durchschnitt)
- `language` (VARCHAR 10) - Sprache des Textes (z.B. 'de', 'en')
- `readingdifficulty` (INT) - Pädagogische Schwierigkeitsstufe (z.B. 1=leicht, 2=mittel, 3=fortgeschritten)
- `silencethreshold` (INT) - Sekunden Stille für Auto-Stop (Standard: 10)
- `enablepronunciation` (TINYINT 1) - Aussprache-Bewertung aktiviert (0=aus, 1=an)
- `minconfidence` (DECIMAL 3,2) - Mindest-Konfidenz für "gute" Aussprache (Standard: 0.80)
- `analysisversion` (INT) - Version der Analyse-Engine (für spätere Re-Analyse)
- `timecreated` (INT) - Erstellungszeitpunkt
- `timemodified` (INT) - Letzte Änderung

**Geschätzte Zeit:** 45 Minuten

### 1.2 Neue Tabelle `aireading_attempts`
**Datei:** `db/install.xml`

**Felder (erweitert):**
- `id` (INT, PK, AUTO_INCREMENT)
- `aireading_id` (INT, FK) - Referenz zur Aktivität
- `userid` (INT, FK) - Referenz zum User
- `attempt` (INT) - Versuchsnummer (1, 2, 3, ...)
- `audiofileid` (INT, FK) - Referenz zur gespeicherten Audio-Datei (File API)
- `status` (INT) - Status: 0=in Bearbeitung, 1=abgegeben, 2=analysiert, 3=fehler
- `timestarted` (INT) - Startzeitpunkt
- `timefinished` (INT) - Endzeitpunkt
- `timeanalyzed` (INT) - Zeitpunkt der Analyse
- `duration` (INT) - Netto-Dauer der gesprochenen Sequenz (Sekunden, aus Segments abgeleitet)
- `wordcount_original` (INT) - Wortzahl Originaltext (Cache)
- `wordcount_transcribed` (INT) - Wortzahl Transkript
- `transcription` (TEXT) - STT-Rohtranskript
- `analysis_data` (LONGTEXT) - JSON mit detaillierten Analyseergebnissen (kompakt, keine binären Daten)
- `wpm` (DECIMAL 10,2) - Berechnete Wörter pro Minute
- `accuracy_score` (DECIMAL 5,2) - Genauigkeitswert (0-100)
- `fluency_score` (DECIMAL 5,2) - Flüssigkeitswert (0-100)
- `pronunciation_score` (DECIMAL 5,2) - Aussprache (0-100),  NULL wenn deaktiviert
- `grade` (DECIMAL 10,5) - Finale Note für diesen Versuch
- `analysisversion` (INT) - Version der Analyse-Engine zum Zeitpunkt der Berechnung
- `teacherfeedback` (TEXT NULL) - Optionales manuelles Feedback

**Indizes:**
- `aireading_id`
- `userid`
- `status` (für Task-Polling)
- `aireading_id, userid, attempt` (Unique)
- Optional: `timeanalyzed` (Reporting-Abfragen)

**Fileareas Spezifikation:**
- `attemptaudio` – gespeicherte Audio-Datei (Format: WebM/Opus, Mono, 16kHz für STT-Optimierung)
- `attempttranscript` – optional persistierte Normalisierung / segmentierte Darstellung

Audiozugriff: Standardregel – Lehrer und Nutzer mit Capability `mod/aireading:viewallattempts` sehen alle Audios; Studenten nur ihre eigenen. Capability-Handling in `pluginfile`.

**Geschätzte Zeit:** 45 Minuten

### 1.3 Upgrade-Skript erstellen
**Datei:** `db/upgrade.php`

Implementiere `xmldb_aireading_upgrade()` für bestehende Installationen.

**Geschätzte Zeit:** 30 Minuten

**Edge Cases Datenmodell:**
- Leere Aufnahme (kein Segment) → `duration = 0`, `status = 3` (fehler) + Event
- STT ohne Confidence → `pronunciation_score = NULL`, Flag im `analysis_data` (`"pronunciation_unavailable": true`)
- Unterbrochene Aufnahme (Browser-Abbruch) → fehlendes `timefinished`, Task ignoriert bis Abschluss.

**Gesamt Phase 1:** ~2,25 Stunden (inkl. Erweiterungen)

---

## 🎨 Phase 2: Konfigurationsformular erweitern

**Ziel:** Lehrer kann Lesetext, WPM-Ziel und Versuchslimit festlegen

### 2.1 `mod_form.php` erweitern
**Datei:** `mod_form.php`

**Neue/Erweiterte Formularelemente:**

```php
// Lesetext
$mform->addElement('editor', 'readingtext_editor',
    get_string('readingtext', 'mod_aireading'),
    ['rows' => 10],
    ['maxfiles' => 0, 'maxbytes' => 0, 'context' => $this->context]);
$mform->setType('readingtext_editor', PARAM_RAW);
$mform->addRule('readingtext_editor', null, 'required', null, 'client');
$mform->addHelpButton('readingtext_editor', 'readingtext', 'mod_aireading');

// Sprache
$languages = [
    'de' => get_string('german', 'mod_aireading'),
    'en' => get_string('english', 'mod_aireading'),
];
$mform->addElement('select', 'language',
    get_string('language', 'mod_aireading'), $languages);
$mform->setDefault('language', 'de');

// Ziel-WPM (pädagogisch adaptiv – Empfehlung nach Sprache/Difficulty, Anzeige Hilfetext)
$mform->addElement('text', 'targetwpm',
    get_string('targetwpm', 'mod_aireading'));
$mform->setType('targetwpm', PARAM_INT);
$mform->setDefault('targetwpm', 100);
$mform->addRule('targetwpm', null, 'required', null, 'client');
$mform->addRule('targetwpm', null, 'numeric', null, 'client');
$mform->addHelpButton('targetwpm', 'targetwpm', 'mod_aireading');

// Max. Versuche (Hinweis: 0 = unbegrenzt; Empfehlung für Anfänger: 3–5)
$mform->addElement('text', 'maxattempts',
    get_string('maxattempts', 'mod_aireading'));
$mform->setType('maxattempts', PARAM_INT);
$mform->setDefault('maxattempts', 3);
$mform->addHelpButton('maxattempts', 'maxattempts', 'mod_aireading');

// Bewertungsmethode (Hinweis: Für Anfänger oft 'latest' sinnvoll, für Leistungsvergleich 'highest')
$grademethods = [
    1 => get_string('grademethod_highest', 'mod_aireading'),
    2 => get_string('grademethod_latest', 'mod_aireading'),
    3 => get_string('grademethod_average', 'mod_aireading'),
];
$mform->addElement('select', 'grademethod',
    get_string('grademethod', 'mod_aireading'), $grademethods);
$mform->setDefault('grademethod', 1);

// Stille-Schwellenwert für Auto-Stop (Hinweis: Für Lesebeginner eher höher wählen, damit Pausen toleriert werden)
$mform->addElement('text', 'silencethreshold',
    get_string('silencethreshold', 'mod_aireading'));
$mform->setType('silencethreshold', PARAM_INT);
$mform->setDefault('silencethreshold', 10);
$mform->addRule('silencethreshold', null, 'required', null, 'client');
$mform->addRule('silencethreshold', null, 'numeric', null, 'client');
$mform->addHelpButton('silencethreshold', 'silencethreshold', 'mod_aireading');

// === Aussprache-Bewertung (Optional) ===
$mform->addElement('header', 'pronunciation_header',
    get_string('pronunciation_settings', 'mod_aireading'));

// Aussprache-Bewertung aktivieren (immer verfügbar; kein Opt-In global nötig laut Entscheidung)
$mform->addElement('advcheckbox', 'enablepronunciation',
    get_string('enablepronunciation', 'mod_aireading'),
    get_string('enablepronunciation_desc', 'mod_aireading'));
$mform->setDefault('enablepronunciation', 0);
$mform->addHelpButton('enablepronunciation', 'enablepronunciation', 'mod_aireading');

// Mindest-Konfidenz
$mform->addElement('select', 'minconfidence',
    get_string('minconfidence', 'mod_aireading'),
    [
        '0.70' => '70%',
        '0.75' => '75%',
        '0.80' => '80% (' . get_string('recommended', 'mod_aireading') . ')',
        '0.85' => '85%',
        '0.90' => '90%',
    ]);
$mform->setDefault('minconfidence', '0.80');
$mform->addHelpButton('minconfidence', 'minconfidence', 'mod_aireading');
$mform->hideIf('minconfidence', 'enablepronunciation', 'notchecked');
```

**Geschätzte Zeit:** 1 Stunde

### 2.2 Language Strings hinzufügen
**Datei:** `lang/en/aireading.php`

Alle benötigten Strings für Formular und Hilfe-Texte, inkl.:

```php
$string['silencethreshold'] = 'Auto-stop after silence (seconds)';
$string['silencethreshold_help'] = 'The recording will automatically stop after this many seconds of silence. Students can also manually stop the recording at any time.';

// Pronunciation Assessment
$string['pronunciation_settings'] = 'Pronunciation assessment (optional)';
$string['enablepronunciation'] = 'Enable pronunciation assessment';
$string['enablepronunciation_desc'] = 'Assess pronunciation quality of all words';
$string['enablepronunciation_help'] = 'When enabled, the system will evaluate the pronunciation accuracy of every word in the reading text based on the speech recognition confidence scores. This is particularly useful for foreign language learning.';
$string['minconfidence'] = 'Minimum confidence for good pronunciation';
$string['minconfidence_help'] = 'Words with a confidence score below this threshold will be marked as pronunciation issues. Higher values are more strict. Recommended: 80%';
$string['recommended'] = 'recommended';
```

**Geschätzte Zeit:** 30 Minuten

### 2.3 `lib.php` Funktionen anpassen
**Dateien:** `lib.php`

- `aireading_add_instance()` - Editor-Daten verarbeiten
- `aireading_update_instance()` - Editor-Daten verarbeiten
- `aireading_delete_instance()` - Versuche und Dateien löschen

**Keine spezielle Verarbeitung nötig** - alle Felder werden direkt gespeichert.

**Geschätzte Zeit:** 1 Stunde

**Frontend-Hilfetexte (Erweiterungen):**
- Neue Strings für Anfänger-Modus (`beginner_mode`, `beginner_mode_help`) – reduziert visuelle Komplexität.
- Neue Strings für Fremdsprachen-Kontext (`foreignlanguage_mode`, Hinweise zu Akzent-Toleranz – heuristisch erklärt, keine technische Akzent-Erkennung implementiert).

**Gesamt Phase 2:** ~3 Stunden

---

## 🎤 Phase 3: Audio-Aufnahme Interface (Frontend)

**Ziel:** Schüler können ihre Lesung aufnehmen mit WebRTC und VAD

### 3.1 AMD-JavaScript-Modul erstellen
**Datei:** `amd/src/recorder.js`

**Funktionen (erweitert):**
- `init(silencethreshold)` - Initialisierung mit konfigurierbarem Schwellenwert
-- `startRecording()` - Mikrofon-Zugriff via `getUserMedia()` (Constraints: `{audio: {sampleRate:16000, channelCount:1, echoCancellation:true}}` – Ziel Mono 16kHz)
-- `pauseRecording()` - Kurzzeitiges Pausieren (für Anfänger-Pausen, visuelle Timer-Fortsetzung)
-- `resumeRecording()`
-- `stopRecording()` - Aufnahme beenden
- `uploadAudio()` - Audio via AJAX hochladen
-- VAD-Implementierung mit `AudioContext` und `AnalyserNode` (Parameter: RMS Threshold adaptiv, Stillefenster)
- **Konfigurierbare Stille-Erkennung** (Sekunden aus Aktivitäts-Einstellung)

**Parameter von Server übergeben:**
```javascript
// In view.php/renderer:
$PAGE->requires->js_call_amd('mod_aireading/recorder', 'init', [
    'silencethreshold' => $moduleinstance->silencethreshold
]);
```

**Abhängigkeiten:**
- `core/ajax`
- `core/notification`
- `core/str`

**UX Modi:**
- Anfänger-Modus: Große Buttons, vereinfachte Farbpalette (Grün, Gelb, Rot), reduzierter Tooltip-Text.
- Fremdsprachen-Modus: Anzeige zusätzlicher Aussprache-Hinweise (Confidence Bereichslegende).

**Barrierefreiheit:**
- ARIA Rollen für Controls (`role="button"`, `aria-pressed` beim Aufnahmeknopf).
- Live Region (`aria-live="polite"`) für Statusmeldungen ("Aufnahme läuft", "Stille erkannt – automatisches Stop in 3s").

**Edge Cases Frontend:**
- Kein Mikrofon → UI zeigt Hinweis + deaktivierter Start-Button.
- Zu kurze Aufnahme (< 2s) → Warnung, optional nicht gewertet.
- Stille > Schwelle ohne Worte → Status "Leere Aufnahme".

**Geschätzte Zeit:** 5 Stunden

### 3.2 CSS für Recorder-Interface
**Datei:** `styles.css`

Styling für:
- Aufnahme-Button (Start/Stop)
- Wellenform-Visualisierung
- Timer-Anzeige
- Status-Indikatoren

**Geschätzte Zeit:** 1 Stunde

### 3.3 View-Seite für Schüler
**Datei:** `view.php` erweitern

Template-basiertes Interface mit:
- Anzeige des Lesetexts
- Recorder-Controls
- Versuchsübersicht
- Bisherige Ergebnisse

**Geschätzte Zeit:** 2,5 Stunden (inkl. ARIA & Modi)

### 3.4 Renderer-Klasse
**Datei:** `classes/output/renderer.php`

**Methoden:**
- `render_reading_view()` - Hauptansicht
- `render_recorder_interface()` - Aufnahme-UI
- `render_attempt_list()` - Versuchsliste

**Geschätzte Zeit:** 2 Stunden

### 3.5 Mustache-Templates
**Dateien:** `templates/*.mustache`

- `reading_view.mustache` - Haupttemplate
- `recorder.mustache` - Recorder-Interface
- `attempt_row.mustache` - Einzelner Versuch

**Geschätzte Zeit:** 1,5 Stunden

**Gesamt Phase 3:** ~12 Stunden

---

## 📡 Phase 4: Backend - Audio-Upload & Verwaltung

**Ziel:** Sichere Speicherung von Audio-Dateien und Versuchs-Verwaltung

### 4.1 AJAX Web Service
**Datei:** `classes/external/submit_attempt.php`

**Klasse:** `\mod_aireading\external\submit_attempt`

**Methoden (erweitert):**
- `execute_parameters()` - Parameter-Definition
- `execute()` - Audio speichern, Versuch anlegen
- `execute_returns()` - Rückgabe-Definition

**Validierungen:**
- Capability-Check (`mod/aireading:submit`)
- Versuchslimit-Prüfung
- Dateiformat-Validierung (WebM, MP3, WAV)
- Dateigröße-Limit

**Geschätzte Zeit:** 2,5 Stunden

### 4.2 Web Service registrieren
**Datei:** `db/services.php`

Service-Definition für AJAX-Aufruf.

**Geschätzte Zeit:** 30 Minuten

### 4.3 Attempt Manager Klasse
**Datei:** `classes/attempt_manager.php`

**Methoden:**
- `create_attempt($aireadingid, $userid)` - Neuen Versuch anlegen
- `save_audio_file($attemptid, $file)` - Audio via File API speichern
- `get_user_attempts($aireadingid, $userid)` - Versuche abrufen
- `can_user_attempt($aireadingid, $userid)` - Versuchslimit prüfen
-- `finalize_attempt($attemptid)` - Status auf "abgegeben" setzen
-- `mark_attempt_error($attemptid, $code, $message)` - Setzt Status=3 + Fehlerdetails (für STT-/Verarbeitungsfehler)
-- `requeue_for_reanalysis($attemptid, $newanalysisversion)` - Vorbereitung bei Engine-Upgrade

**Geschätzte Zeit:** 3 Stunden

### 4.4 Neue Capability
**Datei:** `db/access.php`

```php
'mod/aireading:submit' => [
    'captype' => 'write',
    'contextlevel' => CONTEXT_MODULE,
    'archetypes' => [
        'student' => CAP_ALLOW,
    ],
],
```

**Geschätzte Zeit:** 15 Minuten

**Edge Cases Backend:**
- Doppelter Submit (Race) → zweite Anforderung ignorieren (Locking via DB-Transaktion + unique attempt).
- Abbruch während Upload → kein Datensatz oder unvollständige File-Area → Aufräum-Task (optional später).

**Gesamt Phase 4:** ~6,5 Stunden

---

## 🤖 Phase 5: STT-Integration mit `local_ai_manager`

**Ziel:** Audio-Dateien an AI Manager senden und Transkription erhalten

### 5.1 AI Manager Service-Wrapper (mit Dependency Injection)
**Datei:** `classes/ai_service.php`

**Klasse:** `\mod_aireading\ai_service`

**Design Pattern:** Constructor Injection für Testbarkeit

**Implementierung:**
```php
namespace mod_aireading;

/**
 * AI Service wrapper for STT integration
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_service {
    /** @var \local_ai_manager\base_connector */
    private $aiconnector;

    /**
     * Constructor with dependency injection
     *
     * @param \local_ai_manager\base_connector|null $connector Optional connector for testing
     */
    public function __construct($connector = null) {
        if ($connector === null) {
            // Production: Get connector from ai_manager
            $this->aiconnector = \local_ai_manager\manager::get_connector('whisper');
        } else {
            // Testing: Use injected mock
            $this->aiconnector = $connector;
        }
    }

    /**
     * Transcribe audio file
     *
     * @param string $filepath Absolute path to audio file
     * @param string $language Language code (e.g. 'de', 'en')
     * @return array Normalized STT data with segments
     * @throws \moodle_exception On STT failure
     */
    public function transcribe_audio($filepath, $language) {
        $response = $this->aiconnector->transcribe($filepath, ['language' => $language]);
        return $this->parse_stt_response($response);
    }

    /**
     * Parse and validate STT response
     *
     * @param array $response Raw STT response
     * @return array Normalized ['text' => string, 'segments' => array]
     * @throws \moodle_exception On invalid format
     */
    public function parse_stt_response($response) {
        if (!isset($response['text']) || !isset($response['segments'])) {
            throw new \moodle_exception('invalid_stt_response', 'mod_aireading');
        }

        // Ensure all segments have required fields
        foreach ($response['segments'] as $segment) {
            if (!isset($segment['word'], $segment['start'], $segment['end'], $segment['confidence'])) {
                throw new \moodle_exception('missing_segment_data', 'mod_aireading');
            }
        }

        return $response;
    }

    /**
     * Validate STT output structure
     *
     * @param array $data STT data to validate
     * @return bool True if valid
     * @throws \moodle_exception On validation failure
     */
    public function validate_stt_output($data) {
        if (empty($data['segments'])) {
            throw new \moodle_exception('empty_transcription', 'mod_aireading');
        }

        // Validate confidence values present
        $hasconfidence = array_reduce($data['segments'], function($carry, $segment) {
            return $carry && isset($segment['confidence']);
        }, true);

        if (!$hasconfidence) {
            throw new \moodle_exception('missing_confidence_values', 'mod_aireading');
        }

        return true;
    }
}
```

**Erwartetes STT-Format (JSON):**
```json
{
  "text": "Der Wald ist dunkel",
  "segments": [
    {
      "word": "Der",
      "start": 0.0,
      "end": 0.3,
      "confidence": 0.98
    },
    {
      "word": "Wald",
      "start": 0.35,
      "end": 0.7,
      "confidence": 0.95
    }
  ]
}
```

**Geschätzte Zeit:** 3 Stunden (inkl. DI-Setup)

### 5.2 Adhoc-Task für Analyse
**Datei:** `classes/task/analyze_attempt_task.php`

**Scheduled Task**, der:
1. Ausstehende Versuche findet (`status = 1`)
2. Audio-Datei an AI Manager sendet
3. Transkription speichert
4. Analyse-Engine aufruft
5. Status auf "analysiert" setzt

**Fehlerfälle:**
- Ungültiges JSON → Attempt Fehlerstatus (3) + Event `attempt_failed`.
- Fehlende `segments` → minimale Analyse (WPM=0, Accuracy 0, Fluency 0) mit Flag `incomplete_transcription`.

**Geschätzte Zeit:** 2,5 Stunden

### 5.3 Task registrieren
**Datei:** `db/tasks.php`

```php
$tasks = [
    [
        'classname' => 'mod_aireading\task\analyze_attempt_task',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
```

**Geschätzte Zeit:** 15 Minuten

**Gesamt Phase 5:** ~5,5 Stunden

---

## 📊 Phase 6: Analyse-Engine

**Ziel:** Transkription mit Originaltext vergleichen und Metriken berechnen

### 6.1 Analysis Engine Klasse
**Datei:** `classes/analysis_engine.php`

**Klasse:** `\mod_aireading\analysis_engine`

**Trennung Objektive Metriken vs. Heuristik:**
Objektiv:
- `calculate_wer()`
- `calculate_wpm()`
- Wortanzahlen

Heuristisch:
- `detect_fluency_errors()` (Pausen/Hesitation Heuristiken)
- `detect_pronunciation_errors()` (Confidence-Schwellen)
- `calculate_scores()` (Gewichtung)

JSON trennt Bereiche:
```json
{
    "metrics": {"wer": 0.12, "wpm": 95.5},
    "scores": {"accuracy": 92.5, "fluency": 88.0, "pronunciation": 85.0, "grade": 89.3},
    "errors": [...]
}
```

**Methoden:**

#### `analyze($originaltext, $sttdata, $settings)`
Hauptanalyse-Methode, ruft Untermethoden auf.

**Parameter:**
```php
public function analyze($originaltext, $sttdata, $settings) {
    // $settings enthält:
    // - enablepronunciation (bool)
    // - minconfidence (float)

    $errors = [];

    // Standard-Analyse
    $errors['accuracy'] = $this->detect_accuracy_errors($originaltext, $sttdata['segments']);
    $errors['fluency'] = $this->detect_fluency_errors($sttdata['segments']);

    // Optionale Aussprache-Analyse (alle Wörter)
    if (!empty($settings['enablepronunciation'])) {
        $errors['pronunciation'] = $this->detect_pronunciation_errors(
            $sttdata['segments'],
            $settings['minconfidence']
        );
    }

    return $this->generate_analysis_json($errors, ...);
}
```

#### `calculate_wer($original, $transcription)`
Word Error Rate berechnen:
- Levenshtein-Distanz auf Wort-Ebene
- Klassifizierung: Omissions, Insertions, Substitutions

#### `detect_accuracy_errors($original, $segments)`
Genauigkeitsfehler identifizieren:
- Wort-für-Wort-Vergleich
- Position im Originaltext speichern
- Fehlertyp klassifizieren

#### `detect_pronunciation_errors($segments, $minconfidence)`
**Neue Methode** für Aussprache-Bewertung:
- Prüft Konfidenz-Werte aus STT-Response für **alle Wörter**
- Klassifizierung:
  - **Gut:** `confidence >= minconfidence` → Keine Markierung (oder grün bei Hervorhebung)
  - **OK:** `confidence >= minconfidence - 0.1` → Gelb
  - **Schlecht:** `confidence < minconfidence - 0.1` → Rot

```php
private function detect_pronunciation_errors($segments, $minconfidence) {
    $errors = [];

    foreach ($segments as $position => $segment) {
        $word = $segment['word'];
        $confidence = $segment['confidence'] ?? 1.0;

        // Bewertung basierend auf Konfidenz
        if ($confidence < $minconfidence - 0.1) {
            $severity = 'bad';
        } else if ($confidence < $minconfidence) {
            $severity = 'ok';
        } else {
            continue; // Keine Markierung bei guter Aussprache
        }

        $errors[] = [
            'type' => 'pronunciation',
            'severity' => $severity,
            'position' => $position,
            'word' => $word,
            'confidence' => $confidence,
            'timestamp' => $segment['start'],
        ];
    }

    return $errors;
}
```

#### `detect_fluency_errors($segments)`
Flüssigkeitsfehler erkennen:
- **Pausen:** Gap zwischen Wort-Ende und nächstem Wort-Start > 0.5s
- **Zögern:** Mehrere Fragmente vor korrektem Wort
  - Regex-Pattern: `^(w+\.{3})+wort$` für "w...w...wort"
  - Benötigt: Segmente mit Teilworten

#### `calculate_wpm($segments, $wordcount)`
Wörter pro Minute:
```php
$duration = end($segments)['end'] - $segments[0]['start'];
$wpm = ($wordcount / $duration) * 60;
```

#### `calculate_scores($errors, $wpm, $targetwpm)`
Finale Scores:
- `accuracy_score = 100 - (errors / totalwords * 100)`
- `fluency_score = basierend auf Pausen und Zögern`
- `grade = gewichteter Durchschnitt + WPM-Bonus`

#### `generate_analysis_json($errors, $scores, $wpm)`
JSON-Struktur für `analysis_data`:
```json
{
  "wpm": 95.5,
  "accuracy_score": 92.5,
  "fluency_score": 88.0,
  "pronunciation_score": 85.0,
  "errors": [
    {
      "type": "accuracy",
      "subtype": "substitution",
      "position": 5,
      "expected": "Wald",
      "actual": "Walt",
      "timestamp": 0.7
    },
    {
      "type": "fluency",
      "subtype": "hesitation",
      "position": 3,
      "word": "dunkel",
      "fragments": ["d", "du", "dunk"],
      "timestamp": 1.2
    },
    {
      "type": "pronunciation",
      "severity": "bad",
      "position": 7,
      "word": "Eichhörnchen",
      "confidence": 0.65,
      "timestamp": 2.1
    }
  ],
  "word_timings": [...],
  "pronunciation_details": {
    "total_words": 25,
    "good_pronunciations": 20,
    "ok_pronunciations": 3,
    "bad_pronunciations": 2,
    "average_confidence": 0.87
  }
}
```

**Edge Cases Analyse:**
- Segments unsortiert → Sortieren nach `start`.
- Übersprungene Wörter am Anfang → Substitutions zählen, WER erhöhen.
- Mehrfach-Fragmente ohne finalen Wortabschluss → Hesitation Fehler.
- Kein Wort → Abbruch mit definierter Minimalstruktur.

**Geschätzte Zeit:** 7 Stunden

### 6.2 Hilfsklasse: Text Normalizer
**Datei:** `classes/text_normalizer.php`

Normalisierung für Vergleich:
- Kleinbuchstaben
- Interpunktion entfernen
- Whitespace normalisieren
- Umlaute behandeln

**Geschätzte Zeit:** 1,5 Stunden

### 6.3 Score-Berechnung anpassen
Wenn Aussprache-Bewertung aktiv ist, muss die finale Note angepasst werden:

```php
private function calculate_final_grade($accuracy, $fluency, $pronunciation, $settings) {
    $weights = [
        'accuracy' => 40,
        'fluency' => 30,
    ];

    if (!empty($settings['enablepronunciation'])) {
        $weights['pronunciation'] = 30;
        // Andere Gewichtungen anpassen
        $weights['accuracy'] = 35;
        $weights['fluency'] = 25;
    }

    $grade = ($accuracy * $weights['accuracy'] +
              $fluency * $weights['fluency']) / 100;

    if (!empty($settings['enablepronunciation'])) {
        $grade += ($pronunciation * $weights['pronunciation']) / 100;
    }

    return min(100, $grade); // Max 100%
}
```

**Geschätzte Zeit:** 1 Stunde

**Gesamt Phase 6:** ~9 Stunden

---

## 🎨 Phase 7: Feedback-Darstellung

**Ziel:** Visuelles Feedback mit markiertem Text und Audio-Wiedergabe

### 7.1 Results Renderer
**Datei:** `classes/output/results_renderer.php`

**Methoden:**
- `render_attempt_details($attempt)` - Detailansicht
- `render_annotated_text($originaltext, $errors)` - Text mit Markierungen
- `render_audio_player($audiofile)` - Audio-Wiedergabe
- `render_statistics($attempt)` - Metriken-Übersicht

**Anfänger-Modus Darstellung:**
- Reduzierte Fehlerlisten (nur Gesamt-Punkte, WPM, freundliche Nachricht).
- Umschaltbar per Capability `mod/aireading:toggleadvancedview` oder per Einstellung Aktivität.

**Fremdsprachen-Erweiterungen:**
- Confidence-Legende adaptiv (Zeigt empfohlene Schwelle).
- Hinweistext: "Akzent-Variationen können Confidence beeinflussen – Lehrkraft berücksichtigen.".

### 7.2 Annotated Text Generator
**Datei:** `classes/annotated_text.php`

HTML-Generierung mit `<span>`-Markierungen:

```html
<span class="word correct">Der</span>
<span class="word error-substitution" data-expected="Wald" data-actual="Walt">Wald</span>
<span class="word fluency-hesitation">dunkel</span>
<span class="word pronunciation-bad" data-confidence="0.65">Eichhörnchen</span>
<span class="word pronunciation-ok" data-confidence="0.78">schwierig</span>
```

**CSS-Klassen:**
- `.error-substitution` - Rot (#dc3545)
- `.error-omission` - Rot gestrichelt
- `.fluency-hesitation` - Gelb (#ffc107)
- `.fluency-pause` - Orange (#fd7e14)
- `.pronunciation-bad` - Dunkelrot (#8b0000)
- `.pronunciation-ok` - Hellgelb (#ffe066)

**Priorisierung bei mehreren Fehlertypen:**
1. Accuracy-Fehler (höchste Priorität)
2. Pronunciation-Fehler
3. Fluency-Fehler

**Geschätzte Zeit:** 3,5 Stunden

### 7.3 JavaScript für interaktive Marker
**Datei:** `amd/src/results_viewer.js`

**Funktionen:**
- Hover über Fehler → Tooltip mit Details
- Click auf Wort → Zu Position in Audio springen
- Audio-Synchronisation mit Text-Highlighting

**Erweiterte Tooltips für Aussprache:**
```javascript
// Bei pronunciation-Fehlern
tooltip.innerHTML = `
    <strong>Pronunciation Issue</strong><br>
    Word: "${word}"<br>
    Confidence: ${(confidence * 100).toFixed(0)}%<br>
    Expected: ≥ ${(minconfidence * 100).toFixed(0)}%
`;
```

**Legende anzeigen:**
Wenn Aussprache-Bewertung aktiv → zusätzliche Legende:
- 🟢 Good pronunciation
- 🟡 Acceptable pronunciation
- 🔴 Poor pronunciation

**Geschätzte Zeit:** 4 Stunden

### 7.4 Mustache Templates
**Dateien:**
- `templates/attempt_results.mustache` - Ergebnisseite
- `templates/annotated_text.mustache` - Markierter Text
- `templates/error_tooltip.mustache` - Fehler-Details

**Geschätzte Zeit:** 1,5 Stunden

### 7.5 Pronunciation Statistics Panel
**Template:** `templates/pronunciation_stats.mustache`

Wenn `enablepronunciation` aktiv, zusätzliches Panel anzeigen:

```html
<div class="pronunciation-stats">
    <h4>Pronunciation Assessment</h4>
    <div class="stats-grid">
        <div class="stat-item good">
            <span class="count">{{good_count}}</span>
            <span class="label">Good</span>
        </div>
        <div class="stat-item ok">
            <span class="count">{{ok_count}}</span>
            <span class="label">Acceptable</span>
        </div>
        <div class="stat-item bad">
            <span class="count">{{bad_count}}</span>
            <span class="label">Needs practice</span>
        </div>
    </div>
    {{#has_focus_words}}
    <p class="focus-words-info">
        Evaluated {{focus_words_count}} focus words
    </p>
    {{/has_focus_words}}
</div>
```

**Geschätzte Zeit:** 1,5 Stunden

**Gesamt Phase 7:** ~13 Stunden

---

## 📈 Phase 8: Statistiken und Visualisierung

**Ziel:** WPM-Zahlenstrahl mit Vergleichswerten

### 8.1 Statistics Manager
**Datei:** `classes/statistics_manager.php`

**Methoden:**
- `get_user_best_wpm($aireadingid, $userid)` - Beste WPM
- `get_course_average_wpm($aireadingid)` - Klassendurchschnitt
- `get_user_progress($aireadingid, $userid)` - Verlauf über Versuche

**Geschätzte Zeit:** 2 Stunden

### 8.2 Chart Generator
**Datei:** `classes/chart_generator.php`

Verwendung der **Moodle Chart API** (`core/chart`):

```php
$chart = new \core\chart_line();
$chart->set_title(get_string('wpm_comparison', 'mod_aireading'));
// Daten hinzufügen...
```

**Charts (erweitert, ohne CSV Export):**
1. WPM-Zahlenstrahl (Indikatoren: Ziel-WPM, individueller Bestwert, letzter Versuch)
2. Versuchs-Verlauf (Linie: WPM, optional Accuracy/Fluency Overlays)
3. Fehlertypen-Verteilung (Pie oder Donut)
4. Aussprache-Fortschritt (Confidence Durchschnitt pro Versuch)
5. Fortschritts-Deltas (Balken: Verbesserung vs. Vorversuch)

**Geschätzte Zeit:** 3,5 Stunden

### 8.3 Template Integration
**Datei:** `templates/statistics.mustache`

Einbindung der Charts und Kennzahlen.

**Geschätzte Zeit:** 1 Stunde

### 8.4 Wort-Schwierigkeit Report für Lehrer
**Datei:** `classes/output/teacher_report.php`

Wenn Aussprache-Bewertung aktiv, separater Report:

**Methode:** `get_word_difficulty_analysis($aireadingid)`
- Liste der Wörter im Text, sortiert nach durchschnittlicher Konfidenz
- Identifikation der "schwierigsten" Wörter für die Klasse
- Anzahl Schüler mit Aussprache-Problemen pro Wort
- Exportierbar als CSV

**Geschätzte Zeit:** 2 Stunden

**Edge Cases Statistik:**
- Nur ein Versuch → Verlauf zeigt Hinweis "Mehr Versuche nötig für Trend".
- Keine Aussprache verfügbar → Aussprache-Chart ausgeblendet.

**Gesamt Phase 8:** ~9 Stunden

---

## 🎓 Phase 9: Grade API Integration

**Ziel:** Noten ins Moodle-Notenbuch übertragen

### 9.1 Grade-Unterstützung aktivieren
**Datei:** `lib.php`

`aireading_supports()` erweitern:
```php
case FEATURE_GRADE_HAS_GRADE:
    return true;
case FEATURE_GRADE_OUTCOMES:
    return false;
```

**Geschätzte Zeit:** 15 Minuten

### 9.2 Grading Functions
**Datei:** `lib.php`

#### `aireading_grade_item_update($moduleinstance, $grades = null)`
Notenbuch-Item erstellen/aktualisieren.

#### `aireading_update_grades($moduleinstance, $userid = 0)`
Noten berechnen nach `grademethod`:
- 1 = Höchste Note aller Versuche
- 2 = Letzte Note
- 3 = Durchschnitt aller Noten

**Berücksichtigt automatisch:**
- Wenn `enablepronunciation = 1`: Note enthält Aussprache-Komponente
- Gewichtung wird automatisch in `analysis_engine` angepasst

**Geschätzte Zeit:** 2 Stunden

### 9.3 Automatische Aktualisierung
Nach erfolgreicher Analyse → `aireading_update_grades()` aufrufen.

**Datei:** `classes/task/analyze_attempt_task.php` erweitern

**Geschätzte Zeit:** 30 Minuten

**Gesamt Phase 9:** ~2,75 Stunden

---

## 🔒 Phase 10: Security & Validation

**Ziel:** Alle Eingaben validieren, Ausgaben escapen, Capabilities prüfen

### 10.1 Capability-Checks ergänzen
In allen relevanten Dateien:

```php
require_capability('mod/aireading:view', $context);
require_capability('mod/aireading:submit', $context);
```

**Dateien:**
- `view.php`
- `classes/external/submit_attempt.php`
- `classes/attempt_manager.php`

**Geschätzte Zeit:** 1 Stunde

### 10.2 Input Validation
Alle Parameter mit `required_param()` / `optional_param()`:

```php
$id = required_param('id', PARAM_INT);
$text = required_param('text', PARAM_CLEANHTML);
```

**Geschätzte Zeit:** 1,5 Stunden

### 10.3 Output Escaping
Alle Ausgaben mit `s()`, `format_text()`, `html_writer`:

```php
echo html_writer::tag('div', s($usertext));
echo format_text($content, FORMAT_HTML, ['context' => $context]);
```

**Geschätzte Zeit:** 2 Stunden

### 10.4 File Access Control
**Datei:** `lib.php`

#### `aireading_pluginfile()`
Regeln:
1. Context-Validierung (Modulkontext)
2. Capability `mod/aireading:view` erforderlich.
3. Zugriff auf fremde Audios nur mit `mod/aireading:viewallattempts`.
4. Studenten ohne Capability sehen ausschließlich eigene Versuche.
5. Datei-Area Check: `attemptaudio` oder `attempttranscript`.
6. Keine MIME-Manipulationslogik notwendig – Moodle File API übernimmt Validierung.

**Geschätzte Zeit:** 2 Stunden

**Gesamt Phase 10:** ~6,5 Stunden

---

## 🧪 Phase 11: Testing

**Ziel:** Umfassende Unit- und Behat-Tests

### 11.1 PHPUnit Tests (mit Dependency Injection)
**Dateien:** `tests/*_test.php`

**Test-Klassen:**
- `attempt_manager_test.php` - Versuchs-Verwaltung
- `analysis_engine_test.php` - Analyse-Algorithmen (inkl. Aussprache)
- `ai_service_test.php` - **STT-Integration mit Mock Connector**
- `statistics_manager_test.php` - Statistik-Berechnungen
- `lib_test.php` erweitern - CRUD-Operationen
- `pronunciation_test.php` - **NEU:** Aussprache-Bewertung isoliert testen

**Dependency Injection Pattern für ai_service_test.php:**

```php
<?php
namespace mod_aireading;

/**
 * Unit tests for AI service with mocked connector
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aireading\ai_service
 */
class ai_service_test extends \advanced_testcase {

    /**
     * Test transcription with mocked connector
     */
    public function test_transcribe_audio_with_mock() {
        $this->resetAfterTest();

        // Create mock connector
        $mockconnector = $this->createMock(\local_ai_manager\base_connector::class);

        // Define expected response
        $expectedresponse = [
            'text' => 'Der Wald ist dunkel',
            'segments' => [
                [
                    'word' => 'Der',
                    'start' => 0.0,
                    'end' => 0.3,
                    'confidence' => 0.98,
                ],
                [
                    'word' => 'Wald',
                    'start' => 0.35,
                    'end' => 0.7,
                    'confidence' => 0.95,
                ],
            ],
        ];

        // Configure mock to return expected response
        $mockconnector->expects($this->once())
            ->method('transcribe')
            ->with(
                $this->equalTo('/path/to/audio.webm'),
                $this->equalTo(['language' => 'de'])
            )
            ->willReturn($expectedresponse);

        // Inject mock into ai_service
        $aiservice = new ai_service($mockconnector);

        // Execute
        $result = $aiservice->transcribe_audio('/path/to/audio.webm', 'de');

        // Assert
        $this->assertEquals($expectedresponse, $result);
        $this->assertCount(2, $result['segments']);
        $this->assertEquals('Der Wald ist dunkel', $result['text']);
    }

    /**
     * Test invalid response handling
     */
    public function test_invalid_stt_response_throws_exception() {
        $this->resetAfterTest();

        $mockconnector = $this->createMock(\local_ai_manager\base_connector::class);

        // Return invalid response (missing segments)
        $mockconnector->method('transcribe')
            ->willReturn(['text' => 'Invalid']);

        $aiservice = new ai_service($mockconnector);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('invalid_stt_response');

        $aiservice->transcribe_audio('/path/to/audio.webm', 'de');
    }

    /**
     * Test missing confidence values
     */
    public function test_missing_confidence_throws_exception() {
        $this->resetAfterTest();

        $mockconnector = $this->createMock(\local_ai_manager\base_connector::class);

        // Response without confidence values
        $invalidresponse = [
            'text' => 'Test',
            'segments' => [
                [
                    'word' => 'Test',
                    'start' => 0.0,
                    'end' => 0.5,
                    // Missing 'confidence'
                ],
            ],
        ];

        $mockconnector->method('transcribe')
            ->willReturn($invalidresponse);

        $aiservice = new ai_service($mockconnector);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('missing_segment_data');

        $aiservice->transcribe_audio('/path/to/audio.webm', 'de');
    }

    /**
     * Test validation of STT output
     */
    public function test_validate_stt_output() {
        $this->resetAfterTest();

        $aiservice = new ai_service(
            $this->createMock(\local_ai_manager\base_connector::class)
        );

        // Valid data
        $validdata = [
            'text' => 'Test',
            'segments' => [
                ['word' => 'Test', 'start' => 0, 'end' => 1, 'confidence' => 0.9],
            ],
        ];
        $this->assertTrue($aiservice->validate_stt_output($validdata));

        // Empty segments
        $emptydata = ['text' => 'Test', 'segments' => []];
        $this->expectException(\moodle_exception::class);
        $aiservice->validate_stt_output($emptydata);
    }
}
```

**Weitere Mock-basierte Tests:**

```php
/**
 * Test analysis_engine with mocked ai_service
 */
class analysis_engine_test extends \advanced_testcase {

    public function test_full_analysis_with_pronunciation() {
        $this->resetAfterTest();

        $engine = new analysis_engine();

        $originaltext = "Der Wald ist dunkel";
        $sttdata = [
            'text' => 'Der Walt ist dunkel',
            'segments' => [
                ['word' => 'Der', 'start' => 0.0, 'end' => 0.3, 'confidence' => 0.98],
                ['word' => 'Walt', 'start' => 0.35, 'end' => 0.7, 'confidence' => 0.65], // Low confidence
                ['word' => 'ist', 'start' => 0.75, 'end' => 0.9, 'confidence' => 0.95],
                ['word' => 'dunkel', 'start' => 1.0, 'end' => 1.4, 'confidence' => 0.77], // OK confidence
            ],
        ];

        $settings = [
            'enablepronunciation' => true,
            'minconfidence' => 0.80,
        ];

        $result = $engine->analyze($originaltext, $sttdata, $settings);

        // Assertions
        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('scores', $result);
        $this->assertArrayHasKey('errors', $result);

        // Check pronunciation errors detected
        $pronunciationerrors = array_filter($result['errors'], function($e) {
            return $e['type'] === 'pronunciation';
        });
        $this->assertCount(2, $pronunciationerrors); // Walt (bad) + dunkel (ok)

        // Check accuracy error (substitution)
        $accuracyerrors = array_filter($result['errors'], function($e) {
            return $e['type'] === 'accuracy';
        });
        $this->assertGreaterThanOrEqual(1, count($accuracyerrors));
    }
}
```

**Spezielle Test-Cases für Aussprache:**
```php
public function test_pronunciation_detection_all_words() {
    $segments = [
        ['word' => 'Eichhörnchen', 'confidence' => 0.65, 'start' => 0.0, 'end' => 0.5],
        ['word' => 'ist', 'confidence' => 0.95, 'start' => 0.5, 'end' => 0.7],
        ['word' => 'schwierig', 'confidence' => 0.78, 'start' => 1.0, 'end' => 1.5],
    ];

    $engine = new analysis_engine();
    $errors = $engine->detect_pronunciation_errors($segments, 0.80);

    $this->assertCount(2, $errors); // Eichhörnchen (bad) + schwierig (ok)
    $this->assertEquals('bad', $errors[0]['severity']);
    $this->assertEquals('ok', $errors[1]['severity']);
}

public function test_pronunciation_disabled_returns_empty() {
    $engine = new analysis_engine();
    $settings = ['enablepronunciation' => false];

    $result = $engine->analyze('Test', ['segments' => []], $settings);

    $this->assertNull($result['scores']['pronunciation']);
}
```

**Test für attempt_manager mit File Mock:**

```php
class attempt_manager_test extends \advanced_testcase {

    public function test_save_audio_file_with_mock() {
        global $DB;
        $this->resetAfterTest();

        // Setup test data
        $course = $this->getDataGenerator()->create_course();
        $aireadingrecord = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $manager = new attempt_manager();

        // Create attempt
        $attempt = $manager->create_attempt($aireadingrecord->id, $user->id);

        // Create mock file
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => \context_module::instance($aireadingrecord->cmid)->id,
            'component' => 'mod_aireading',
            'filearea' => 'attemptaudio',
            'itemid' => $attempt->id,
            'filepath' => '/',
            'filename' => 'test.webm',
        ];
        $storedfile = $fs->create_file_from_string($filerecord, 'mock audio data');

        // Save and finalize
        $result = $manager->save_audio_file($attempt->id, $storedfile);
        $this->assertTrue($result);

        // Verify
        $updated = $DB->get_record('aireading_attempts', ['id' => $attempt->id]);
        $this->assertEquals(1, $updated->status); // Finalized
        $this->assertNotEmpty($updated->timefinished);
    }
}
```

**Geschätzte Zeit:** 12 Stunden (inkl. Mock-Setup + DI Pattern)

### 11.2 Behat Tests
**Datei:** `tests/behat/reading_workflow.feature`

**Szenarien:**
- Lehrer erstellt Aktivität
- Lehrer erstellt Aktivität **mit Aussprache-Bewertung**
- Schüler nimmt Audio auf
- Versuchslimit wird eingehalten
- Ergebnisse werden angezeigt (mit/ohne Aussprache-Scores)
- Noten werden übertragen
- Lehrer sieht Wort-Schwierigkeit Report

**Neues Feature:**
```gherkin
Scenario: Teacher enables pronunciation assessment
  Given I am logged in as "teacher1"
  When I create an "aireading" activity with:
    | name                  | English Reading Test    |
    | enablepronunciation   | 1                       |
    | minconfidence         | 0.80                    |
  Then I should see "Pronunciation assessment: Enabled"
  And I should see "Minimum confidence: 80%"
```

**Geschätzte Zeit:** 5 Stunden

### 11.3 Generator erweitern
**Datei:** `tests/generator/lib.php`

Test-Daten-Generator für:
- `aireading` Instanzen
- `aireading_attempts` mit Dummy-Daten
- Mock STT-Responses

**Geschätzte Zeit:** 2 Stunden

**Edge Case Tests (Ergänzung, Laufzeit ähnlich):**
- Leere Aufnahme → korrekter Fehlerstatus.
- STT Fehlerhafte JSON Response → Attempt Fehlerstatus + Event.
- Keine Confidence → Pronunciation Score fehlt, Flag gesetzt.
- Anfänger-Modus Rendering (Behat: reduzierte Ansicht).

**Gesamt Phase 11:** ~19 Stunden (inkl. DI Pattern + erweiterte Mocks)

---

## 📝 Phase 12: Dokumentation & Finalisierung (erweitert)

**Ziel:** Vollständige Dokumentation und Code-Qualität

### 12.1 README erstellen (Erweitert)
**Datei:** `README.md`

- Plugin-Beschreibung
- Installation
- Konfiguration
- Verwendung
- **Aussprache-Bewertung Feature** (wie es funktioniert, wann es sinnvoll ist)
- Systemanforderungen (`local_ai_manager` mit Konfidenz-Werten)
- STT-Service Requirements (muss `confidence` Werte liefern)

**Geschätzte Zeit:** 1,5 Stunden

### 12.2 PHPDoc vervollständigen
Alle Klassen und Methoden mit vollständigen DocBlocks:

```php
/**
 * Analyze reading attempt
 *
 * @param string $originaltext The expected text
 * @param array $sttdata STT output with word timestamps
 * @return array Analysis results with errors and scores
 * @throws moodle_exception If STT data is invalid
 */
```

**Geschätzte Zeit:** 3 Stunden

### 12.3 Language Strings vervollständigen
**Datei:** `lang/en/aireading.php`

Alle Strings mit Help-Texten (`_help` Suffix).

**Geschätzte Zeit:** 2 Stunden

### 12.4 Privacy API
**Datei:** `classes/privacy/provider.php`

DSGVO-Compliance: Export und Löschung von Nutzerdaten.

**Geschätzte Zeit:** 3 Stunden

### 12.5 Backup/Restore Präzisierung
Erweiterung der Backup-Struktur:
- Tabelle `aireading_attempts` inklusive aller neuen Felder (`duration`, `wordcount_*`, `pronunciation_score`, `analysisversion`, `teacherfeedback`).
- Einbindung der Fileareas `attemptaudio` & `attempttranscript` (Standard: `backup_nested_element` mit file annotations).
- Sicherstellen: Reihenfolge – Aktivität → Versuche → Dateien.

### 12.6 Code-Qualität prüfen
Ausführen von:
```bash
cd ~/dev/vanilla_moodle && ./codechecker.sh public/mod/aireading
cd ~/dev/vanilla_moodle && ./codechecker_autofix.sh public/mod/aireading
cd ~/dev/vanilla_moodle && ./phpunitinit.sh -> initialisierung der Unittestumgebung
cd ~/dev/vanilla_moodle && ./phpunit.sh --testsuite=mod_aireading_testsuite  -> immer mit der Testsuite ausführen.
cd ~/dev/vanilla_moodle && ./behatinit.sh -> initialisierung der Behattestumgebung
cd ~/dev/vanilla_moodle && ./behat.sh {absolute path to the behat feature || plugins testsuite}

```

Alle Moodle Coding Standard Violations beheben.

**Geschätzte Zeit:** 2 Stunden

### 12.6 CI/CD konfigurieren
**Datei:** `.github/workflows/gha.yml` (bereits vorhanden)

Prüfen und ggf. anpassen für:
- PHPUnit
- Behat
- Code Checker
- PHPDoc

**Geschätzte Zeit:** 1 Stunde

### 12.7 User Documentation
**Datei:** `docs/PRONUNCIATION_GUIDE.md`

Anleitung für Lehrer:
- Wann Aussprache-Bewertung sinnvoll ist
- Interpretation der Konfidenz-Werte
- Unterschied zwischen Genauigkeit und Aussprache
- Empfohlene Schwellenwerte für verschiedene Niveaus
- Best Practices für Fremdsprachen

**Geschätzte Zeit:** 1 Stunde

**Gesamt Phase 12:** ~14 Stunden

---

## 🌐 Phase 13: Internationalisierung & Accessibility (neu)

**Ziel:** Vollständige i18n-Unterstützung und WCAG 2.1 AA Compliance

### 13.1 Mehrsprachige Unterstützung erweitern
**Datei:** `lang/*/aireading.php`

**Sprachen implementieren:**
- Deutsch (`lang/de/aireading.php`) - Vollständig
- Englisch (`lang/en/aireading.php`) - Vollständig
- Französisch (`lang/fr/aireading.php`) - Optional
- Spanisch (`lang/es/aireading.php`) - Optional

**String-Kategorien:**
- Formular-Labels und Hilfe-Texte
- Feedback-Nachrichten
- Fehler-Meldungen
- Statistik-Beschriftungen
- Accessibility-Labels (ARIA)

**Geschätzte Zeit:** 3 Stunden

### 13.2 RTL-Sprachen Support
**Dateien:**
- `styles.css` - RTL-spezifische Anpassungen
- `amd/src/recorder.js` - Layout-Anpassungen für RTL

**RTL-Anpassungen:**
```css
/* RTL Support */
[dir="rtl"] .ai-reading-recorder {
    direction: rtl;
}

[dir="rtl"] .ai-reading-progress-bar {
    transform: scaleX(-1);
}

[dir="rtl"] .ai-reading-error-marker {
    margin-right: 0;
    margin-left: 0.25rem;
}
```

**Geschätzte Zeit:** 2 Stunden

### 13.3 WCAG 2.1 AA Compliance Audit
**Prüfbereiche:**

#### Farb-Kontraste
- Alle Text-Farben ≥ 4.5:1 Kontrast
- Große Texte (≥18pt) ≥ 3:1 Kontrast
- UI-Komponenten ≥ 3:1 Kontrast

#### Tastatur-Navigation
- Alle interaktiven Elemente via Tastatur erreichbar
- Sichtbare Focus-Indikatoren
- Logische Tab-Reihenfolge
- Skip-Links für Hauptbereiche

#### Screen Reader Support
- ARIA-Labels für alle Controls
- ARIA-Live-Regions für dynamische Updates
- Semantisches HTML (header, main, nav, article)
- Alt-Texte für visuelle Inhalte

#### Responsive & Zoom
- Funktional bis 200% Zoom
- Mobile-optimierte Touch-Targets (min 44x44px)
- Keine horizontalen Scrollbars bei Zoom

**Testing-Tools:**
- axe DevTools
- WAVE Browser Extension
- NVDA/JAWS Screen Reader Testing

**Geschätzte Zeit:** 4 Stunden

### 13.4 Accessibility-Features implementieren
**Datei:** `amd/src/recorder.js` erweitern

**Features:**
- Tastatur-Shortcuts für Recorder (Space = Start/Stop, Esc = Cancel)
- Focus-Management nach Modals
- Announce Status-Changes via ARIA-Live

**JavaScript Beispiel:**
```javascript
// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === ' ' && !e.target.matches('input, textarea')) {
        e.preventDefault();
        toggleRecording();
    }
    if (e.key === 'Escape' && isRecording) {
        stopRecording();
    }
});

// Status announcements
function announceStatus(message) {
    const liveRegion = document.getElementById('ai-reading-status-live');
    liveRegion.textContent = message;
}
```

**Geschätzte Zeit:** 3 Stunden

### 13.5 High Contrast Mode Support
**Datei:** `styles.css` erweitern

**High Contrast CSS:**
```css
@media (prefers-contrast: high) {
    .ai-reading-error-marker {
        border: 2px solid currentColor;
        font-weight: bold;
    }

    .ai-reading-recording-indicator {
        border: 3px solid #ff0000;
        box-shadow: 0 0 10px #ff0000;
    }

    .ai-reading-btn-primary {
        border: 2px solid currentColor;
    }
}
```

**Geschätzte Zeit:** 1,5 Stunden

### 13.6 Mobile Optimierungen
**Bereiche:**
- Touch-optimierte Controls (größere Buttons)
- Responsive Tables für Statistiken
- Mobile-freundliche Audio-Player
- Optimierte Aufnahme für mobile Browser

**CSS Breakpoints:**
```css
/* Mobile First */
@media (max-width: 576px) {
    .ai-reading-recorder-controls {
        flex-direction: column;
    }

    .ai-reading-btn {
        width: 100%;
        min-height: 44px;
    }

    .ai-reading-annotated-text {
        font-size: 1.1rem;
        line-height: 1.8;
    }
}

/* Tablet */
@media (min-width: 768px) and (max-width: 991px) {
    .ai-reading-statistics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
```

**Geschätzte Zeit:** 2,5 Stunden

### 13.7 Dokumentation: Accessibility Guide
**Datei:** `docs/ACCESSIBILITY.md`

**Inhalte:**
- WCAG 2.1 Compliance Statement
- Tastatur-Navigation Guide
- Screen Reader Compatibility
- Known Limitations
- Contact für Accessibility Issues

**Geschätzte Zeit:** 1,5 Stunden

### 13.8 Barrierefreiheit-Tests dokumentieren
**Datei:** `tests/accessibility_test.php` (PHPUnit)

**Test-Cases:**
- HTML Semantic Structure Validation
- ARIA Attributes Presence
- Color Contrast Calculations (programmatisch)
- Focus Management nach Interaktionen

**Behat Feature:** `tests/behat/accessibility.feature`
```gherkin
@mod @mod_aireading @accessibility
Feature: AI Reading accessibility features
  In order to use AI Reading with assistive technology
  As a user with disabilities
  I need proper accessibility support

  Scenario: Keyboard navigation for recorder
    Given I am logged in as "student1"
    And I am on the "Reading Test" "aireading activity" page
    When I press the Tab key repeatedly
    Then the focus moves through all interactive elements
    And the record button can be activated with Space key

  Scenario: Screen reader announces recording status
    Given I am logged in as "student1"
    And I am using a screen reader
    When I start recording
    Then I should hear "Recording started"
    When the recording auto-stops due to silence
    Then I should hear "Recording stopped automatically due to silence"
```

**Geschätzte Zeit:** 3 Stunden

**Gesamt Phase 13:** ~20,5 Stunden

---

## 🔐 Phase 14: Performance-Optimierung & Caching (neu)

**Ziel:** Skalierbarkeit für große Klassen und Optimierung der Analysegeschwindigkeit

### 14.1 Caching-Strategie implementieren
**Dateien:** `classes/cache_manager.php` (neu)

**Cache-Definitionen:**
**Datei:** `db/caches.php` (neu erstellen)

```php
<?php
defined('MOODLE_INTERNAL') || die();

$definitions = [
    // User attempts cache (per user per activity)
    'user_attempts' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
        'staticaccelerationsize' => 10,
    ],

    // Course statistics cache
    'course_stats' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 3600, // 1 hour
    ],

    // Analysis results cache
    'analysis_results' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
    ],

    // Chart data cache (expensive calculations)
    'chart_data' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 1800, // 30 minutes
    ],
];
```

**Cache Manager Klasse:**
```php
namespace mod_aireading;

/**
 * Cache management for AI Reading
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cache_manager {

    /**
     * Get user attempts from cache or database
     *
     * @param int $aireadingid Activity ID
     * @param int $userid User ID
     * @return array Attempts
     */
    public static function get_user_attempts($aireadingid, $userid) {
        $cache = \cache::make('mod_aireading', 'user_attempts');
        $key = "{$aireadingid}_{$userid}";

        $attempts = $cache->get($key);
        if ($attempts === false) {
            $manager = new attempt_manager();
            $attempts = $manager->get_user_attempts($aireadingid, $userid);
            $cache->set($key, $attempts);
        }

        return $attempts;
    }

    /**
     * Invalidate user attempts cache
     */
    public static function invalidate_user_attempts($aireadingid, $userid) {
        $cache = \cache::make('mod_aireading', 'user_attempts');
        $key = "{$aireadingid}_{$userid}";
        $cache->delete($key);
    }

    /**
     * Get course statistics from cache
     */
    public static function get_course_stats($aireadingid) {
        $cache = \cache::make('mod_aireading', 'course_stats');
        $key = "stats_{$aireadingid}";

        $stats = $cache->get($key);
        if ($stats === false) {
            $statsmanager = new statistics_manager();
            $stats = $statsmanager->get_course_statistics($aireadingid);
            $cache->set($key, $stats);
        }

        return $stats;
    }

    /**
     * Purge all caches for an activity
     */
    public static function purge_activity_caches($aireadingid) {
        $caches = ['user_attempts', 'course_stats', 'analysis_results', 'chart_data'];
        foreach ($caches as $cachename) {
            $cache = \cache::make('mod_aireading', $cachename);
            $cache->purge();
        }
    }
}
```

**Integration in bestehende Klassen:**
- `attempt_manager::get_user_attempts()` → Cache-Wrapper verwenden
- `statistics_manager` → Chart-Daten cachen
- Nach Analyse → Cache invalidieren

**Geschätzte Zeit:** 4 Stunden

### 14.2 Datenbank-Query-Optimierung
**Datei:** `classes/attempt_manager.php` erweitern

**Optimierungen:**
```php
/**
 * Get multiple users' latest attempts efficiently
 * (für Teacher Overview)
 */
public function get_latest_attempts_batch($aireadingid, $userids) {
    global $DB;

    if (empty($userids)) {
        return [];
    }

    list($insql, $params) = $DB->get_in_or_equal($userids);
    $params[] = $aireadingid;

    $sql = "SELECT ara.*
            FROM {aireading_attempts} ara
            INNER JOIN (
                SELECT userid, MAX(attempt) as maxattempt
                FROM {aireading_attempts}
                WHERE aireading_id = ?
                AND userid $insql
                GROUP BY userid
            ) latest ON ara.userid = latest.userid
                    AND ara.attempt = latest.maxattempt
            WHERE ara.aireading_id = ?";

    array_unshift($params, $aireadingid);

    return $DB->get_records_sql($sql, $params);
}
```

**Index-Überprüfung in `db/install.xml`:**
- Composite Index auf `(aireading_id, userid, attempt)`
- Index auf `status` für Task-Queries
- Index auf `timeanalyzed` für Reporting

**Geschätzte Zeit:** 2 Stunden

### 14.3 Asynchrone Analyse-Verarbeitung optimieren
**Datei:** `classes/task/analyze_attempt_task.php` erweitern

**Batch-Verarbeitung:**
```php
protected function execute() {
    global $DB;

    // Get config for batch size
    $batchsize = get_config('mod_aireading', 'analysis_batch_size') ?: 50;

    // Select pending attempts
    $attempts = $DB->get_records_select(
        'aireading_attempts',
        'status = :status',
        ['status' => 1],
        'timefinished ASC',
        '*',
        0,
        $batchsize
    );

    if (empty($attempts)) {
        return;
    }

    mtrace("Processing " . count($attempts) . " attempts...");

    $successcount = 0;
    $errorcount = 0;

    foreach ($attempts as $attempt) {
        try {
            $this->process_attempt($attempt);
            $successcount++;

            // Invalidate cache
            cache_manager::invalidate_user_attempts(
                $attempt->aireading_id,
                $attempt->userid
            );

        } catch (\Exception $e) {
            $errorcount++;
            mtrace("Error processing attempt {$attempt->id}: " . $e->getMessage());

            $manager = new attempt_manager();
            $manager->mark_attempt_error(
                $attempt->id,
                'analysis_exception',
                $e->getMessage()
            );
        }
    }

    mtrace("Completed: {$successcount} successful, {$errorcount} errors");
}
```

**Admin Setting für Batch Size:**
**Datei:** `settings.php` erweitern

```php
$settings->add(new admin_setting_configtext(
    'mod_aireading/analysis_batch_size',
    get_string('analysis_batch_size', 'mod_aireading'),
    get_string('analysis_batch_size_desc', 'mod_aireading'),
    50,
    PARAM_INT
));
```

**Geschätzte Zeit:** 2,5 Stunden

### 14.4 Frontend-Performance-Optimierung
**Datei:** `amd/src/results_viewer.js` erweitern

**Lazy Loading für große Texte:**
```javascript
/**
 * Initialize with Intersection Observer for lazy rendering
 */
function initLazyRendering() {
    const errorMarkers = document.querySelectorAll('.ai-reading-error-marker');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const marker = entry.target;
                loadTooltipData(marker);
                observer.unobserve(marker);
            }
        });
    }, {
        rootMargin: '50px'
    });

    errorMarkers.forEach(marker => observer.observe(marker));
}
```

**Debouncing für Audio-Sync:**
```javascript
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

const syncTextWithAudio = debounce(function(currentTime) {
    // Sync logic
}, 50); // 50ms debounce
```

**Geschätzte Zeit:** 2 Stunden

### 14.5 Memory Management für große Dateien
**Datei:** `classes/ai_service.php` erweitern

**Streaming für Audio-Upload:**
```php
/**
 * Upload audio file to STT service with streaming
 *
 * @param stored_file $file Moodle stored file
 * @param string $language Language code
 * @return array STT response
 */
public function transcribe_stored_file(stored_file $file, $language) {
    // Use file handle instead of loading full file into memory
    $handle = $file->get_content_file_handle();

    // Stream to temp file for STT processing
    $tempfile = tempnam(sys_get_temp_dir(), 'aireading_');
    $temphandle = fopen($tempfile, 'w');

    stream_copy_to_stream($handle, $temphandle);

    fclose($handle);
    fclose($temphandle);

    try {
        $result = $this->transcribe_audio($tempfile, $language);
    } finally {
        // Cleanup
        unlink($tempfile);
    }

    return $result;
}
```

**Geschätzte Zeit:** 1,5 Stunden

### 14.6 Load Testing & Benchmarking
**Datei:** `tests/performance_test.php` (neu)

**Performance-Tests:**
```php
namespace mod_aireading;

/**
 * Performance tests for AI Reading
 *
 * @package    mod_aireading
 * @group      performance
 * @covers     \mod_aireading\statistics_manager
 */
class performance_test extends \advanced_testcase {

    /**
     * Test statistics calculation performance with large dataset
     */
    public function test_statistics_performance_large_class() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $aireadingrecord = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
        ]);

        // Create 100 users with 5 attempts each
        $starttime = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $user = $this->getDataGenerator()->create_user();
            for ($j = 1; $j <= 5; $j++) {
                $this->getDataGenerator()->get_plugin_generator('mod_aireading')
                    ->create_attempt([
                        'aireading_id' => $aireadingrecord->id,
                        'userid' => $user->id,
                        'attempt' => $j,
                        'status' => 2,
                    ]);
            }
        }

        $setuptime = microtime(true) - $starttime;
        mtrace("Setup time for 500 attempts: {$setuptime}s");

        // Measure statistics calculation
        $calcstart = microtime(true);
        $statsmanager = new statistics_manager();
        $stats = $statsmanager->get_course_statistics($aireadingrecord->id);
        $calctime = microtime(true) - $calcstart;

        mtrace("Statistics calculation time: {$calctime}s");

        // Assert performance acceptable (< 2s for 500 attempts)
        $this->assertLessThan(2.0, $calctime,
            "Statistics calculation too slow for large dataset");
    }

    /**
     * Test cache effectiveness
     */
    public function test_cache_performance() {
        $this->resetAfterTest();

        // First call (no cache)
        $start = microtime(true);
        $result1 = cache_manager::get_course_stats($aireadingid);
        $uncachedtime = microtime(true) - $start;

        // Second call (cached)
        $start = microtime(true);
        $result2 = cache_manager::get_course_stats($aireadingid);
        $cachedtime = microtime(true) - $start;

        mtrace("Uncached: {$uncachedtime}s, Cached: {$cachedtime}s");

        // Cache should be at least 5x faster
        $this->assertLessThan($uncachedtime / 5, $cachedtime,
            "Cache not providing expected speedup");
    }
}
```

**Geschätzte Zeit:** 3 Stunden

### 14.7 Performance-Dokumentation
**Datei:** `docs/PERFORMANCE.md` (neu)

**Inhalte:**
- Benchmark-Ergebnisse
- Skalierbarkeits-Empfehlungen
- Cache-Tuning-Optionen
- Datenbank-Maintenance-Tipps
- Monitoring-Empfehlungen

**Geschätzte Zeit:** 1,5 Stunden

**Gesamt Phase 14:** ~16,5 Stunden

---

## 🛡️ Phase 15: Production Readiness & Deployment (neu)

**Ziel:** Plugin produktionsreif machen und Deployment-Dokumentation erstellen

### 15.1 Version Management & Changelog
**Datei:** `version.php` finalisieren

```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_aireading';
$plugin->version = 2025111200; // YYYYMMDDXX
$plugin->requires = 2024042200; // Moodle 4.5+
$plugin->maturity = MATURITY_STABLE; // ALPHA -> BETA -> RC -> STABLE
$plugin->release = 'v1.0.0';
$plugin->dependencies = [
    'local_ai_manager' => 2025010100,
];
```

**Datei:** `CHANGELOG.md` (neu erstellen)

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-11-12

### Added
- Initial release
- Audio recording with WebRTC
- Speech-to-Text integration via local_ai_manager
- Automated reading analysis (accuracy, fluency, pronunciation)
- Visual feedback with annotated text
- Comprehensive statistics and charts
- Grade API integration with 3 grading methods
- Beginner mode for simplified UI
- Pronunciation assessment feature
- Teacher reports with word difficulty analysis
- WCAG 2.1 AA accessibility compliance
- Multi-language support (en, de)
- Comprehensive caching system
- Mobile-responsive design

### Security
- Multi-layer input validation
- Capability-based access control
- File access restrictions
- Output escaping throughout

## [Unreleased]

### Planned
- Additional language support (fr, es)
- Advanced pronunciation analytics with IPA
- Batch re-analysis tool for version upgrades
- Extended teacher analytics dashboard
```

**Geschätzte Zeit:** 1 Stunde

### 15.2 Deployment-Dokumentation
**Datei:** `DEPLOYMENT.md` (neu)

| Phase | Thema | Stunden | Davon Aussprache-Feature |
|-------|-------|---------|--------------------------|
| 1 | Datenbank-Schema | 2,25 | +0,25 |
| 2 | Konfigurationsformular | 3,0 | +0,5 |
| 3 | Audio-Aufnahme Frontend | 12,0 | +0,75 |
| 4 | Backend Audio-Upload | 6,5 | +0,25 |
| 5 | STT-Integration | 5,5 | +0,5 |
| 6 | Analyse-Engine | 9,0 | +1,75 |
| 7 | Feedback-Darstellung | 13,0 | +2,25 |
| 8 | Statistiken | 9,0 | +2,75 |
| 9 | Grade API | 2,75 | - |
| 10 | Security | 6,5 | - |
| 11 | Testing | 19 | +5,0 |
| 12 | Dokumentation | 14 | +1,75 |
| **GESAMT** | | **~105,5 Stunden** | **+14,25 Stunden** |

**Bei 8h/Tag:** ~13-14 Arbeitstage
**Bei 4h/Tag:** ~26 Arbeitstage

---

## 🎯 Meilensteine

### Meilenstein 1: Backend Foundation (Phasen 1-2, 4)
✅ Datenbank steht
✅ Konfiguration möglich
✅ Audio-Upload funktioniert

**Ziel:** Lehrer kann Aktivität erstellen, Schüler kann Aufnahmen hochladen

### Meilenstein 2: AI Integration (Phasen 5-6)
✅ STT-Service eingebunden
✅ Analyse-Engine funktioniert

**Ziel:** Automatische Auswertung läuft

### Meilenstein 3: User Experience (Phasen 3, 7-9)
Zusatz: Anfänger & Fremdsprachen-spezifische Darstellungen funktionsfähig.
✅ Aufnahme-Interface fertig
✅ Feedback sichtbar
✅ Noten übertragen

**Ziel:** Vollständiger Workflow für Endnutzer

### Meilenstein 4: Production Ready (Phasen 10-12)
✅ Security audit bestanden
✅ Tests erfolgreich
✅ Dokumentation vollständig

**Ziel:** Plugin produktionsreif

---

## 🔗 Abhängigkeiten & Voraussetzungen

### Externe Abhängigkeiten
1. **`local_ai_manager`** muss installiert und konfiguriert sein
2. STT-Service muss **Wort-Zeitstempel** liefern
3. STT-Service muss **Konfidenz-Werte** pro Wort liefern (für Aussprache-Bewertung)
   - **Falls nicht verfügbar:** ai_manager wird entsprechend erweitert
4. Browser muss WebRTC unterstützen (`getUserMedia`, `MediaRecorder`)

### Moodle-Version
- Minimum: Moodle 4.5 (siehe `version.php`)
- Empfohlen: Aktuellste LTS

### Server-Anforderungen
- PHP ≥ 8.1
- Genug Speicher für Audio-Dateien (je nach Kursgröße)
- Cron für Scheduled Tasks

### Konfigurierbare Parameter (Pro Aktivitäts-Instanz)
- **Stille-Schwellenwert:** Einstellbar (Standard: 10 Sekunden)
  - Wird an JavaScript-Recorder übergeben für VAD-Logik
- **Aussprache-Bewertung:** Aktivierbar pro Aktivität (Standard: deaktiviert)
  - Vollständig implementiert ohne Feature-Flag
  - Bewertet **alle Wörter** im Text
  - Mindest-Konfidenz: Schwellenwert für "gute" Aussprache (Standard: 80%)

### STT-Service Anforderungen für Aussprache-Feature
Der verwendete STT-Service muss pro Wort folgende Daten liefern:
```json
{
  "word": "example",
  "start": 0.5,
  "end": 0.8,
  "confidence": 0.95  // ← KRITISCH für Aussprache-Bewertung
}
```

**Garantie:** Falls `local_ai_manager` diese Werte nicht liefert, wird dieser entsprechend erweitert.

**Dependency Injection für Tests:**
- `ai_service` akzeptiert Mock Connector im Constructor
- Alle Unit Tests verwenden gemockte STT-Responses
- Keine Abhängigkeit von externem Service in PHPUnit Tests

---

## 🚀 Empfohlene Reihenfolge

1. **Start:** Phase 1 (Datenbank) → Sofortige Migration möglich
2. **Parallel möglich:**
   - Phase 2 (Form) + Phase 4 (Backend)
   - Phase 3 (Frontend) unabhängig entwickeln
3. **Sequenziell:**
   - Phase 5 → Phase 6 (STT vor Analyse)
   - Phase 7-9 nach Phase 6
4. **Abschluss:** Phasen 10-12 am Ende

---

## ✅ Nächste Schritte

1. Datenbank-Schema finalisieren (Phase 1)
2. **Dependency Injection Setup:**
   - ✅ ai_service mit Constructor Injection
   - ✅ Mock Connector Interface für Tests
   - ✅ PHPUnit Mocks für alle ai_manager Aufrufe
3. Prototyp des Recorders testen (Phase 3.1)
4. Analyse-Algorithmus isoliert entwickeln (Phase 6.1)
5. **Vollständige Implementierung** ohne Feature-Flags oder Beta-Status

## 🎁 Optional Features (Future Enhancements)

Mögliche Erweiterungen für spätere Versionen:

1. **Fokus-Wörter:** Optionale Einschränkung auf bestimmte Wörter
2. **IPA-basierte Aussprache-Bewertung:** Phonem-Level statt Konfidenz
3. **Audio-Referenz:** Lehrer kann Muster-Aussprache hochladen
4. **Aussprache-Übungsmodus:** Isoliertes Training einzelner Wörter
5. **Multi-Sprecher-Vergleich:** Klassen-weite Aussprache-Heatmap
6. **Integration mit Wörterbuch-APIs:** Automatische IPA-Generierung
7. **Erweiterte Dialekt-Erkennung:** Sprachvarianten-spezifische Bewertung

---

## 👥 Usecases (Ausformuliert)

### A. Lesebeginner / Grundschule
Ziele: Motivation, einfache Rückmeldung, geringe kognitive Belastung.
Spezifika:
- Anfänger-Modus: Reduzierte Oberfläche, nur Kernkennzahlen (WPM, Gesamtpunkte, Fortschrittsbalken).
- Pausen tolerant: Silence-Threshold für Anfänger empfohlen ≥ 12s.
- Positive Verstärkung: Anzeige "Verbesserung seit letztem Versuch: +X WPM".
- Optional: Hinweis bei stagnierender Leistung („Versuche langsamer und deutlicher zu sprechen“).
- Farbcode: Grün (Verbesserung), Gelb (gleich), Rot (Rückgang).

### B. Fremdsprachen-Lernen
Ziele: Aussprache-Verbesserung, Flüssigkeit, Wortschatz.
Spezifika:
- Aussprache-Legende (Confidence Kategorien) immer sichtbar bei aktivierter Pronunciation.
- Hinweis auf mögliche Akzent-Einflüsse (rein informativ; keine Anpassung algorithmisch in dieser Version).
- Darstellung der häufig falsch ausgesprochenen Wörter (Top 5) im Ergebnisbereich.
- Per Aktivität definierbare `targetwpm` je Sprache (z. B. Englisch ggf. höher als Deutsch – manuell pflegbar).
- Keine separate Dialekt-Erkennung (out-of-scope); Plan markiert Erweiterungspotential.

## 🔄 Backup/Restore (Erweitert)
- Aufnahme neuer Strukturen in `backup/moodle2/backup_aireading_activity_task.class.php` (Erweiterung):
    - `aireading_attempts` als `backup_nested_element` mit allen Feldern.
    - Fileareas `attemptaudio`, `attempttranscript` via `annotate_files`.
- Restore ordnet Dateien korrekt zu Attempts anhand attempt-ID Mapping.
- `analysisversion` + Scores werden wiederhergestellt; Re-Analyse später optional via Admin Tool.

**Letzte Aktualisierung:** 2025-11-11
**Status:** Produktionsreifer Plan mit Dependency Injection ✅
**Version:** 1.2 (DI-erweitert)
**Copyright:** 2025 ISB Bayern
**Autor:** Dr. Peter Mayer

---

## 🔧 Implementation Reference (Maschinenlesbar)

Ziel: Präzise, deterministische Vorgaben für eine automatisierte Umsetzung (z. B. durch ein AI-Entwicklungsmodell). Alle Signaturen, Pfade, JSON-Strukturen und Akzeptanzkriterien sind hier zusammengefasst.

### 1. Klassen & Pfade
| Klasse | Pfad | Zweck |
|--------|------|-------|
| attempt_manager | `classes/attempt_manager.php` | CRUD & Logik für Versuche |
| analysis_engine | `classes/analysis_engine.php` | Berechnung objektiver Metriken & Heuristik |
| ai_service | `classes/ai_service.php` | Wrapper für STT-Integration (`local_ai_manager`) |
| text_normalizer | `classes/text_normalizer.php` | Normalisierung für Vergleich & WER |
| annotated_text | `classes/annotated_text.php` | Generierung markierter HTML-Ausgabe |
| statistics_manager | `classes/statistics_manager.php` | Aggregierte Statistiken (Klasse / Nutzer) |
| chart_generator | `classes/chart_generator.php` | Aufbau Moodle Chart API Objekte |
| task\analyze_attempt_task | `classes/task/analyze_attempt_task.php` | Verarbeitung abgegebener Versuche |
| output\renderer | `classes/output/renderer.php` | Hauptansicht (Activity) |
| output\results_renderer | `classes/output/results_renderer.php` | Detailanzeige einzelner Versuche |
| privacy\provider | `classes/privacy/provider.php` | DSGVO – Export/Löschung |

### 2. Öffentliche Methodensignaturen (PHPDoc Kurzform)

`classes/ai_service.php`
```php
/** Constructor with dependency injection for testing. */
public function __construct($connector = null);
/** Transcribe audio file via ai_manager connector. */
public function transcribe_audio(string $filepath, string $language): array;
/** Parse and validate STT response. */
public function parse_stt_response(array $response): array;
/** Validate STT output structure. */
public function validate_stt_output(array $data): bool;
```

`classes/attempt_manager.php`
```php
/** Create a new attempt (status=0). */
public function create_attempt(int $aireadingid, int $userid): stdClass;
/** Attach audio file (File API) and finalize attempt (status=1). */
public function save_audio_file(int $attemptid, stored_file $file): bool;
/** Mark attempt submitted (status=1, set timefinished). */
public function finalize_attempt(int $attemptid): bool;
/** Mark attempt error (status=3). */
public function mark_attempt_error(int $attemptid, string $code, string $message): bool;
/** Queue attempt for reanalysis. */
public function requeue_for_reanalysis(int $attemptid, int $newversion): bool;
/** Get attempts for user ordered by attempt asc. */
public function get_user_attempts(int $aireadingid, int $userid): array;
/** Check if user may create another attempt. */
public function can_user_attempt(int $aireadingid, int $userid): bool;
```

`classes/analysis_engine.php`
```php
/** Main analysis entry point. */
public function analyze(string $originaltext, array $segments, array $settings): array;
public function calculate_wer(string $original, string $transcription): float;
public function detect_accuracy_errors(string $original, array $segments): array;
public function detect_fluency_errors(array $segments): array;
public function detect_pronunciation_errors(array $segments, float $minconfidence): array;
public function calculate_wpm(array $segments, int $wordcountOriginal): float;
public function calculate_scores(array $metrics, array $errors, array $settings): array; // returns ['accuracy'=>..,'fluency'=>..,'pronunciation'=>..,'grade'=>..]
```

`classes/ai_service.php`
```php
public function transcribe_audio(string $filepath, string $language): array; // returns STT array
public function parse_stt_response(array $response): array; // normalized ['text'=>string,'segments'=>[]]
public function validate_stt_output(array $data): bool; // throws moodle_exception on invalid
```

`classes/task/analyze_attempt_task.php`
```php
protected function execute(): void; // Selects attempts status=1, processes batch.
```

### 3. Capabilities Matrix (`db/access.php` Ergänzung)
```php
$capabilities = [
    'mod/aireading:addinstance' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]
    ],
    'mod/aireading:view' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['student' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]
    ],
    'mod/aireading:viewallattempts' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]
    ],
    'mod/aireading:submit' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['student' => CAP_ALLOW]
    ],
    'mod/aireading:grade' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]
    ],
    'mod/aireading:toggleadvancedview' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]
    ],
];
```

### 4. Events
| Event | Klasse | Trigger | Zusatzdaten |
|-------|--------|---------|-------------|
| Attempt erstellt | `\mod_aireading\event\attempt_created` | `create_attempt()` | attemptid, userid |
| Attempt eingereicht | `\mod_aireading\event\attempt_submitted` | `finalize_attempt()` | attemptid, duration |
| Attempt analysiert | `\mod_aireading\event\attempt_analyzed` | Nach Analyse Task | scores, wpm, wer |
| Attempt fehlgeschlagen | `\mod_aireading\event\attempt_failed` | STT/Analyse Fehler | errorcode |
| Grade aktualisiert | `\mod_aireading\event\grade_updated` | `aireading_update_grades()` | grade, grademethod |

Mindestens `get_name()`, `get_description()`, `get_url()` implementieren; context = Modulkontext.

### 5. JSON Schema `analysis_data`
```json
{
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "title": "AI Reading Analysis",
    "type": "object",
    "required": ["metrics", "scores", "errors"],
    "properties": {
        "metrics": {
            "type": "object",
            "required": ["wer", "wpm"],
            "properties": {
                "wer": {"type": "number", "minimum": 0},
                "wpm": {"type": "number", "minimum": 0},
                "wordcount_original": {"type": "integer", "minimum": 0},
                "wordcount_transcribed": {"type": "integer", "minimum": 0}
            }
        },
        "scores": {
            "type": "object",
            "required": ["accuracy", "fluency", "grade"],
            "properties": {
                "accuracy": {"type": "number", "minimum": 0, "maximum": 100},
                "fluency": {"type": "number", "minimum": 0, "maximum": 100},
                "pronunciation": {"type": ["number","null"], "minimum": 0, "maximum": 100},
                "grade": {"type": "number", "minimum": 0, "maximum": 100}
            }
        },
        "errors": {
            "type": "array",
            "items": {
                "type": "object",
                "required": ["type","position"],
                "properties": {
                    "type": {"type": "string", "enum": ["accuracy","fluency","pronunciation"]},
                    "subtype": {"type": ["string","null"]},
                    "severity": {"type": ["string","null"], "enum": ["bad","ok","good", null]},
                    "position": {"type": "integer", "minimum": 0},
                    "expected": {"type": ["string","null"]},
                    "actual": {"type": ["string","null"]},
                    "confidence": {"type": ["number","null"], "minimum": 0, "maximum": 1},
                    "timestamp": {"type": ["number","null"], "minimum": 0}
                }
            }
        },
        "flags": {
            "type": "object",
            "properties": {
                "pronunciation_unavailable": {"type": "boolean"},
                "incomplete_transcription": {"type": "boolean"}
            }
        }
    }
}
```

### 6. Templates Kontext (Mustache)
| Template | Datei | Kontext Keys |
|----------|-------|--------------|
| reading_view | `templates/reading_view.mustache` | `activityname`, `readingtext_html`, `attempts` (array), `beginner_mode`, `language`, `enablepronunciation` |
| recorder | `templates/recorder.mustache` | `silencethreshold`, `isrecording`, `can_attempt`, `attemptcount`, `maxattempts` |
| attempt_row | `templates/attempt_row.mustache` | `attempt`, `status`, `grade`, `wpm`, `fluency`, `accuracy`, `pronunciation` |
| attempt_results | `templates/attempt_results.mustache` | `scores`, `metrics`, `errors`, `annotated_text_html`, `audio_player_html`, `beginner_mode` |
| pronunciation_stats | `templates/pronunciation_stats.mustache` | `good_count`, `ok_count`, `bad_count`, `average_confidence` |

Alle HTML Fragmente vorher durch `format_text(..., FORMAT_HTML, ['context'=>$context])` oder sichere Generierung.

### 7. Upgrade Schritte (Beispiel `db/upgrade.php`)
```php
function xmldb_aireading_upgrade(int $oldversion): bool {
    global $DB; $dbman = $DB->get_manager();
    if ($oldversion < 2025111101) {
        // Add fields readingdifficulty, analysisversion, timemodified to aireading.
        // Add fields duration, wordcount_original, wordcount_transcribed, pronunciation_score, analysisversion, teacherfeedback to aireading_attempts.
        upgrade_mod_savepoint(true, 2025111101, 'aireading');
    }
    return true;
}
```

### 8. `pluginfile()` Skelett
```php
function aireading_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options=[]) {
    require_login($course, false, $cm);
    require_capability('mod/aireading:view', $context);
    $attemptid = (int)array_shift($args); // itemid
    if (!in_array($filearea, ['attemptaudio','attempttranscript'])) { return false; }
    $manager = new \mod_aireading\attempt_manager();
    $attempts = $manager->get_user_attempts($cm->instance, $USER->id);
    $isown = array_key_exists($attemptid, array_column($attempts, 'id', 'id'));
    if (!$isown && !has_capability('mod/aireading:viewallattempts', $context)) { return false; }
    $fs = get_file_storage();
    $filename = array_pop($args);
    $filepath = '/'.implode('/', $args).'/';
    $file = $fs->get_file($context->id, 'mod_aireading', $filearea, $attemptid, $filepath, $filename);
    if (!$file) { return false; }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
```

### 9. Cron Task Batch Spezifikation
- Frequenz: jede Minute.
- Batch-Selektion: `SELECT id FROM {aireading_attempts} WHERE status = 1 ORDER BY timefinished ASC LIMIT :limit` (Default Limit = 50)
- Admin Setting (optional später): `mod_aireading_batchsize` – fallback 50.
- Fehlerbehandlung: bei Exception → `mark_attempt_error()` + Event.

### 10. Edge Case Acceptance Criteria
| Fall | Erwartetes Verhalten |
|------|----------------------|
| Aufnahme < 2s | Markierung als Fehler (status=3, errorcode=short_audio) |
| Keine Segmente | WPM=0, wer=1.0 (100%), grade minimal, Flag `incomplete_transcription` |
| Missing confidence | `pronunciation_score = NULL`, Flag `pronunciation_unavailable` |
| STT Timeout | Fehlerstatus + Event attempt_failed |
| Re-Analyse Version erhöht | Neue Analyseversion gesetzt, Task führt erneute Analyse durch |

### 11. Objektive vs. Heuristische Metriken (Mapping)
| Metrik | Kategorie | Quelle |
|--------|-----------|--------|
| WER | objektiv | Levenshtein-Wort-Ebene |
| WPM | objektiv | Dauer + Wortzahl |
| Accuracy Score | heuristisch | 100 - (Fehler / Wortzahl) |
| Fluency Score | heuristisch | Pausen & Hesitation Heuristik |
| Pronunciation Score | heuristisch | Confidence Schwellen |
| Grade | Mixed (aggregiert) | Gewichtung Settings |

### 12. Test Fallen (Kurzreferenz)
| Test | Ziel |
|------|------|
| Empty audio test | Fehlerstatus korrekt |
| Pronunciation fallback | NULL Score + Flag |
| Re-analysis path | Versionswechsel setzt Requeue |
| WER calc substitution | Exakter Diff |
| Fluency pause detection | Gap > 0.5s erkannt |

### 13. Akzeptanzkriterien (Final Done)
1. Anlage Aktivität speichert alle neuen Felder korrekt.
2. Student kann bis `maxattempts` aufnehmen; Überschreitung blockiert.
3. Analyse generiert JSON konform Schema (validierbar).
4. Grade aktualisiert gemäß `grademethod` nach Analyse.
5. Anfänger-Modus reduziert Darstellung (keine detaillierte Fehlerliste).
6. Fremdsprachen-Modus zeigt Aussprache-Legende.
7. Fehlende Confidence deaktiviert Aussprache-Bereich visuell.
8. Alle Events werden im Log angezeigt mit korrektem Kontext.
9. Backup & Restore: Wiederherstellung reinstalls attempts + files exakt.
10. `pluginfile` schützt fremde Audios ohne Capability.

---

## 📊 Implementierungsstatus (Stand: 2025-11-12)

### ✅ Vollständig implementierte Phasen

#### Phase 7: Feedback-Darstellung
**Status:** ✅ Abgeschlossen
**Implementierte Komponenten:**
- `classes/output/results_renderer.php` - Render-Klasse für Attempt-Ergebnisse
- `classes/annotated_text.php` - Fehlermarkierung im Text mit Prioritäten
- `amd/src/results_viewer.js` - Interaktive Features (Tooltips, Audio-Sync)
- `templates/attempt_results.mustache` - Haupttemplate für Ergebnisanzeige
- `templates/statistics.mustache` - Einzelversuch-Statistiken
- `templates/pronunciation_stats.mustache` - Aussprache-Assessment Panel
- `styles.css` - Umfassendes Styling (600+ Zeilen)
- Language Strings (80+) für Feedback-Display

**Besonderheiten:**
- Beginner Mode Support (vereinfachte Ansicht)
- Error Prioritization (Accuracy > Pronunciation > Fluency)
- Accessibility-Features (ARIA, Reduced Motion, High Contrast)
- Audio Playback Synchronisation mit Text-Highlighting

#### Phase 8: Statistiken und Visualisierung
**Status:** ✅ Abgeschlossen
**Implementierte Komponenten:**
- `classes/statistics_manager.php` - Statistik-Berechnungen (WPM, Fortschritt, Trends)
- `classes/chart_generator.php` - 5 Chart-Typen mit Moodle Chart API
  - WPM Comparison Bar Chart
  - Progress Line Chart
  - Error Distribution Pie Chart
  - Pronunciation Progress Line Chart
  - Improvement Delta Bar Chart
- `templates/statistics_overview.mustache` - Umfassende Statistikseite
- `templates/word_difficulty_report.mustache` - Teacher Report Template
- `classes/output/teacher_report.php` - Wort-Schwierigkeitsanalyse
- Language Strings (50+) für Charts und Reports

**Besonderheiten:**
- Edge Case Handling (keine Daten, einzelner Versuch)
- Teacher Reports mit Word Difficulty Analysis
- Klassenweite Auswertungen
- Responsive Design für alle Charts

#### Phase 9: Grade API Integration
**Status:** ✅ Abgeschlossen
**Implementierte Komponenten:**
- `aireading_grade_item_update()` in `lib.php` - Gradebook Item Management
- `aireading_grade_item_delete()` in `lib.php` - Item Deletion
- `aireading_update_grades()` in `lib.php` - Grade Calculation & Update
- `aireading_calculate_user_grade()` in `lib.php` - Grade Method Logic
  - Method 1: Highest Grade
  - Method 2: Latest Grade
  - Method 3: Average Grade
- `aireading_get_user_grades()` in `lib.php` - Grade Retrieval
- `aireading_scale_used()` / `aireading_scale_used_anywhere()` - Scale Support
- Automatische Grade-Updates in `classes/task/analyze_attempt_task.php`
- Language Strings für Grading (grademethod, grade status)

**Besonderheiten:**
- Unterstützt 3 Bewertungsmethoden (Highest, Latest, Average)
- Automatische Aktualisierung nach Analyse
- Integration in Moodle Gradebook
- Berücksichtigt Pronunciation Score wenn aktiviert

#### Phase 10: Security & Validation
**Status:** ✅ Abgeschlossen
**Implementierte Komponenten:**
- `view.php` - Vollständige Security-Checks:
  - `required_param()` für alle URL-Parameter (PARAM_INT)
  - `require_login()` und `require_capability()` Checks
  - `context_module` Validierung
  - Output Escaping mit `format_text()`, `html_writer`
- `lib.php` - `aireading_pluginfile()` erweitert:
  - Attemptid Validierung (> 0)
  - Activity Instance Ownership Check
  - Filename Security (Directory Traversal Prevention)
  - Path Traversal Protection (., .., /, \)
- `classes/external/submit_attempt.php` - bereits vorhanden mit:
  - Parameter Validation via `validate_parameters()`
  - Context Validation via `validate_context()`
  - Capability Checks (`mod/aireading:submit`)
  - File Type Validation (Audio MIME types)
  - File Size Limits (max 50MB)
  - Minimum Duration Check
- `classes/output/results_renderer.php` - bereits sicher:
  - Verwendet `format_text()` für User Content
  - Template-basierte Ausgabe (Mustache auto-escapes)
- Language Strings (20+) für View und Security Messages

**Besonderheiten:**
- Multi-Layer Security (URL params, context, capabilities, file access)
- Directory Traversal Prevention in pluginfile
- Activity Instance Ownership Verification
- Comprehensive Input Validation mit Moodle PARAM_* Konstanten
- Output Escaping durchgehend mit Moodle Core Functions

### 🔄 In Arbeit / Geplant

#### Phase 11: Testing
**Status:** ⏳ Geplant
**Geplante Komponenten:**
- PHPUnit Tests für alle Klassen (mit DI-Mocks)
- Behat Tests für User Workflows
- Integration Tests für STT & Analysis
- Performance Tests
- Accessibility Tests

**Geschätzte Zeit:** ~19 Stunden

#### Phase 12: Dokumentation & Finalisierung
**Status:** ⏳ Geplant
**Geschätzte Zeit:** ~14 Stunden

#### Phase 13: Internationalisierung & Accessibility
**Status:** ⏳ Geplant
**Geschätzte Zeit:** ~20,5 Stunden

#### Phase 14: Performance-Optimierung & Caching
**Status:** ⏳ Geplant
**Geschätzte Zeit:** ~16,5 Stunden

#### Phase 15: Production Readiness & Deployment
**Status:** ⏳ Geplant
**Geschätzte Zeit:** ~22 Stunden

### 📈 Fortschritt (aktualisiert)

**Gesamt-Fortschritt:** 10 von 15 Phasen abgeschlossen (~67%)
**Nächster Schritt:** Phase 11 (Testing)

**Zeitaufwand abgeschlossen:**
- Phase 1-6: ~34 Stunden (Backend Foundation & Analysis)
- Phase 7: ~13 Stunden (Feedback-Darstellung) ✅
- Phase 8: ~9 Stunden (Statistiken) ✅
- Phase 9: ~2,75 Stunden (Grade API) ✅
- Phase 10: ~6,5 Stunden (Security) ✅
- **Gesamt abgeschlossen:** ~65,25 Stunden

**Zeitaufwand ausstehend:**
- Phase 11: ~19 Stunden (Testing)
- Phase 12: ~14 Stunden (Dokumentation)
- Phase 13: ~20,5 Stunden (i18n & Accessibility)
- Phase 14: ~16,5 Stunden (Performance)
- Phase 15: ~22 Stunden (Production Readiness)
- **Gesamt ausstehend:** ~92 Stunden

**Gesamt-Zeitaufwand (Projekt):** ~157,25 Stunden (~20 Arbeitstage bei 8h/Tag)

---

## ⏱️ Gesamtübersicht Zeitaufwand (Final)

| Phase | Thema | Stunden | Status |
|-------|-------|---------|--------|
| 1 | Datenbank-Schema | 2,25 | ⏳ Geplant |
| 2 | Konfigurationsformular | 3,0 | ⏳ Geplant |
| 3 | Audio-Aufnahme Frontend | 12,0 | ⏳ Geplant |
| 4 | Backend Audio-Upload | 6,5 | ⏳ Geplant |
| 5 | STT-Integration | 5,5 | ⏳ Geplant |
| 6 | Analyse-Engine | 9,0 | ⏳ Geplant |
| 7 | Feedback-Darstellung | 13,0 | ✅ Abgeschlossen |
| 8 | Statistiken | 9,0 | ✅ Abgeschlossen |
| 9 | Grade API | 2,75 | ✅ Abgeschlossen |
| 10 | Security & Validation | 6,5 | ✅ Abgeschlossen |
| 11 | Testing | 19,0 | ⏳ Geplant |
| 12 | Dokumentation | 14,0 | ⏳ Geplant |
| 13 | i18n & Accessibility | 20,5 | ⏳ Geplant |
| 14 | Performance | 16,5 | ⏳ Geplant |
| 15 | Production Readiness | 22,0 | ⏳ Geplant |
| **GESAMT** | | **~161,5 Stunden** | **~26% abgeschlossen** |

**Bei 8h/Tag:** ~20 Arbeitstage
**Bei 4h/Tag:** ~40 Arbeitstage

**Phasen vollständig abgeschlossen:** 4 von 15 (27%)
**Fortschritt geschätzt:** ~26% (basierend auf Zeitaufwand)

---
