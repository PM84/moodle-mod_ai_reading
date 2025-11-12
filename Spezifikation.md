## 💡 Gesamtkonzept: AI Lese-Lern-Trainer (`mod_aireading`)

Das Modul ist ein interaktiver Lese-Trainer, der die **Leseflüssigkeit** und **Lesegenauigkeit** von Schülern automatisiert bewertet. Es stützt sich auf eine serverseitige **Speech-to-Text (STT)-Analyse** über den `local_ai_manager`.

### 1. Architektur und Technologien 🏗️

| Komponente | Rolle | Technologie |
| :--- | :--- | :--- |
| **Client (Browser)** | Aufnahme und Steuerung | **WebRTC** (`getUserMedia`, `MediaRecorder`), **VAD** (JavaScript `AudioContext`/`AnalyserNode`), Moodle **AJAX** |
| **Server (Moodle)** | Konfiguration, Speicherung, Orchestrierung | Moodle **PHP-Backend** (Standard-Plugin-Struktur), Moodle **DB API**, Moodle **File API** |
| **AI-Service** | Transkription und Zeitstempel | **`local_ai_manager`** (als dedizierter Moodle-Service) |
| **Bewertung** | Notenvergabe und Statistik | Moodle **Grade API**, Moodle **Chart API** (für Visualisierung) |

---

### 2. Detaillierter Nutzungsablauf (Workflow)

#### 2.1. Konfiguration durch den Lehrer

1.  Der Lehrer erstellt eine neue Aktivität vom Typ **"AI Lese-Lern-Trainer"** im Kurs.
2.  Er gibt den **Lesetext** direkt in ein Textfeld ein.
3.  Er legt das **Ziel für "Wörter pro Minute" (WPM)** fest.
4.  Er konfiguriert das **Versuchslimit (x)** für die Schüler.
5.  Er speichert die Einstellungen (Nutzung der Moodle **DB API**).

#### 2.2. Durchführung durch den Schüler

1.  Der Schüler klickt auf die Aktivität **`AI Lese-Lern-Trainer`**.
2.  Er sieht den Text und klickt auf **"Lesen starten"**.
3.  **Clientseitige Aufnahme:**
    * **WebRTC** startet die Mikrofonaufnahme (`MediaRecorder`).
    * **VAD** überwacht den Audiostream.
4.  Der Schüler liest den Text vor.
5.  **Beenden der Aufnahme:**
    * Manuell durch Klick auf **"Beenden und Abgeben"**.
    * Automatisch durch die VAD, wenn **10 Sekunden Stille** erkannt werden.
6.  Die resultierende Audio-Datei wird per **AJAX** an den Moodle-Server hochgeladen und über die **Moodle File API** dem Versuch zugeordnet.
7.  Der Versuch wird in der Datenbank (`mod_aireading_attempts`) als **"Zur Analyse anstehend"** markiert.

---

### 3. Serverseitige Analyse (Kernprozess)

Dieser Prozess läuft im Hintergrund ab und wird durch einen Task oder ein sofortiges Server-Skript ausgelöst.

#### 3.1. Spracherkennung (STT)

1.  Das Moodle-Backend ruft den **`local_ai_manager`** (über eine definierte Service-API, z.B. `local_ai_manager_service::transcribe()`) auf.
2.  Übermittelt werden die **Audio-Datei** und die **Erwartungssprache**.
3.  **Kritische Anforderung:** Der `local_ai_manager` liefert die **Transkription** zurück, die zwingend **Wort-Zeitstempel** (Start/Ende jedes erkannten Wortes/Segments) und idealerweise **Konfidenzwerte** für jedes Wort enthält. Dies ist entscheidend, um Zögern wie "*w...w...waa....waal....wald*" zu erkennen.

#### 3.2. Fehleranalyse und Metrikberechnung

Unter Verwendung des Originaltextes und der detaillierten STT-Ausgabe erfolgt die Auswertung:

1.  **Textvergleich:** Der Originaltext wird Wort für Wort mit der Transkription verglichen.
2.  **Genauigkeitsanalyse:** Ermittlung der **Word Error Rate (WER)**, um die Metriken zu bestimmen:
    * **Ausgelassene Wörter** (Omission).
    * **Hinzugefügte Wörter** (Insertion).
    * **Falsche Wörter** (Substitution).
3.  **Flüssigkeitsanalyse (Fluency):**
    * Verwendung der **Zeitstempel**, um Pausen (Stillstand zwischen Wörtern) zu messen.
    * **Zögern:** Erkennung von Wortfragmenten oder Wiederholungen im STT-Output, die unmittelbar dem korrekten Wort vorausgehen (z.B. "w...w...waa"). Diese werden als **Flüssigkeitsfehler** klassifiziert.
4.  **Geschwindigkeitsanalyse:**
    * Berechnung der **tatsächlichen Lesezeit** (Ende des letzten Wortes - Start des ersten Wortes).
    * Berechnung der **Wörter pro Minute (WPM)**: $WPM = \frac{\text{Anzahl der Wörter im Originaltext}}{\text{Gesamte Lesezeit in Minuten}}$.

5.  **Speicherung:** Alle detaillierten Ergebnisse (Fehlertypen, WPM, Zeitstempel) werden in der Spalte **`analysis_data`** (JSON-Format) im Datensatz des Versuchs gespeichert.

---

### 4. Feedback und Berichterstattung 📊

#### 4.1. Detailliertes Schüler-Feedback

Die Ergebnisansicht zeigt dem Schüler die detaillierten Fehler an:

* **HTML-Overlay:** Der Originaltext wird mit farblichen Markierungen versehen, basierend auf der **`analysis_data`**:
    * **Rot:** **Genauigkeitsfehler** (falsch, ausgelassen, hinzugefügt).
    * **Gelb:** **Flüssigkeitsfehler** (Zögern, Wiederholung, lange Pause).
* **Audio-Wiedergabe:** Der Schüler kann seine eigene Aufnahme anhören.

#### 4.2. Statistische Visualisierung (Moodle Chart API)

Eine grafische Darstellung der **WPM-Leistung** auf einem Zahlenstrahl sorgt für schnelle Orientierung und Kontext:

* **Zielmarke:** WPM-Ziel der Lehrkraft (z.B. grüne Linie).
* **Eigene Leistung:** WPM des aktuellen Versuchs (z.B. blauer Punkt).
* **Klassendurchschnitt:** Durchschnittliche WPM aller Kursteilnehmer (z.B. grauer Punkt).

#### 4.3. Notenbuch-Integration

* Die finale Note (basierend auf WPM und Fehlerquote) wird über die **Moodle `grade` API** an das Notenbuch übermittelt.
* Die Note kann so konfiguriert werden, dass sie nur den besten Versuch oder den letzten Versuch berücksichtigt.

---

### 5. Zusammenfassung der Moodle-API-Nutzung

Das gesamte Konzept basiert auf Moodle-internen Mechanismen:

* **Front-End:** Moodle **JavaScript** und **AJAX-Funktionen**.
* **Konfiguration:** Moodle **`mod_form`** und **`settings.php`**.
* **Datenspeicherung:** Moodle **DB API** und **File API**.
* **Analyse-Service:** Interner Aufruf des **`local_ai_manager`** als lokales Plugin/Service.
* **Bewertung:** Moodle **Grade API**.

Das Modul ist somit vollständig in die Moodle-Umgebung integriert und erfüllt die strengen Anforderungen, keine externen, nicht-Moodle-eigenen Webservices für die kritische Datenverarbeitung zu verwenden.

---
Möchten Sie als Nächstes die **Minimalanforderungen** an die Schnittstelle des **`local_ai_manager`** für dieses Plugin definieren?