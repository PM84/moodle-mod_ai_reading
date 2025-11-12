# Changelog

All notable changes to the AI Reading Trainer will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Additional language support (French, Spanish)
- Advanced pronunciation analytics with IPA (International Phonetic Alphabet)
- Batch re-analysis tool for analysis engine version upgrades
- Extended teacher analytics dashboard
- CSV/Excel export for reports
- Custom feedback templates
- Integration with Moodle Competency Framework

---

## [1.0.0] - 2025-11-12

### Added

#### Core Features
- **Audio Recording Interface**
  - WebRTC-based recording with MediaRecorder API
  - Automatic silence detection with configurable threshold
  - Voice Activity Detection (VAD) for intelligent pause detection
  - Mono 16kHz audio optimized for STT processing
  - Manual pause/resume functionality
  - Real-time timer and waveform visualization

- **Speech-to-Text Integration**
  - Integration with `local_ai_manager` plugin
  - Support for multiple STT services (Whisper, Google STT, Azure Speech)
  - Word-level timestamp extraction
  - Confidence score processing per word
  - Dependency Injection pattern for testability

- **Automated Reading Analysis**
  - **Accuracy Assessment** - Word Error Rate (WER) calculation
    - Substitution detection
    - Omission detection
    - Insertion detection
  - **Fluency Assessment** - Reading smoothness evaluation
    - Pause detection (gaps > 0.5s)
    - Hesitation detection (repeated fragments)
    - Words per minute (WPM) calculation
  - **Pronunciation Assessment** (Optional)
    - Word-level confidence-based evaluation
    - Three-tier classification (good/acceptable/poor)
    - Configurable confidence threshold
    - All words in text evaluated

- **Visual Feedback System**
  - Color-coded text annotations
    - Red: Accuracy errors (substitutions, omissions)
    - Orange: Pronunciation issues
    - Yellow: Fluency problems (pauses, hesitations)
    - Green: Correct reading
  - Error prioritization when multiple types occur
  - Interactive tooltips with error details
  - Audio playback synchronized with text highlighting

- **Statistics & Visualizations**
  - Progress tracking across attempts
  - WPM comparison charts
  - Error distribution pie charts
  - Pronunciation progress line charts
  - Improvement delta bar charts
  - Class average comparisons

- **Teacher Reports**
  - All student attempts overview
  - Word difficulty analysis (most challenging words)
  - Pronunciation hotspot identification
  - Per-student progress tracking
  - Exportable statistics (future: CSV/Excel)

- **Grade API Integration**
  - Three grading methods:
    - Highest grade
    - Latest grade
    - Average grade
  - Automatic gradebook synchronization
  - Weighted scoring (accuracy 50%, fluency 30%, pronunciation 20%)
  - WPM bonus for exceeding target

#### User Experience
- **Beginner Mode**
  - Simplified interface with reduced visual complexity
  - Larger buttons and clear color coding
  - Friendly, encouraging messages
  - Optional detailed error list toggle

- **Multi-Language Support**
  - English (en) language pack
  - German (de) language pack
  - Extensible for additional languages
  - Language-specific WPM recommendations

- **Accessibility Features**
  - WCAG 2.1 AA compliant
  - Full keyboard navigation
  - Screen reader optimization
  - ARIA landmarks and live regions
  - High contrast mode support
  - Reduced motion support
  - 4.5:1 minimum color contrast ratios

- **Mobile Responsive Design**
  - Touch-optimized controls (44x44px minimum)
  - Responsive layouts for all screen sizes
  - Mobile-friendly audio player
  - Optimized recorder interface for mobile browsers

#### Technical Features
- **Performance Optimization**
  - Four-tier caching system
    - User attempts cache
    - Course statistics cache
    - Analysis results cache
    - Chart data cache
  - Database query optimization with composite indexes
  - Batch processing for analysis (configurable batch size)
  - Frontend lazy loading and debouncing
  - Memory-efficient file streaming

- **Security**
  - Multi-layer input validation
  - Output escaping throughout
  - Capability-based access control
  - File access restrictions in `pluginfile()`
  - Directory traversal prevention
  - CSRF protection
  - SQL injection prevention (prepared statements)

- **Data Privacy & GDPR**
  - Privacy API implementation
  - User data export functionality
  - User data deletion functionality
  - Configurable data retention policies
  - Transparent data processing documentation

- **Testing Infrastructure**
  - PHPUnit tests with mock objects
  - Dependency Injection for STT service
  - Behat tests for user workflows
  - Performance tests
  - Accessibility tests
  - Test data generator

- **Backup & Restore**
  - Full activity backup support
  - Attempt data restoration
  - Audio file handling
  - Cross-course/site restore compatibility

### Configuration Options

**Activity-Level Settings:**
- Reading text (HTML editor)
- Language selection (de, en, extensible)
- Reading difficulty level (easy, medium, advanced)
- Target words per minute (WPM)
- Maximum attempts (0 = unlimited)
- Grading method (highest, latest, average)
- Silence threshold for auto-stop (seconds)
- Enable/disable pronunciation assessment
- Minimum confidence threshold (70-90%)

**Site-Level Settings:**
- Analysis batch size (default: 50 attempts per cron run)
- Maximum audio file size (default: 50MB)
- Default silence threshold (default: 10 seconds)
- Enable/disable caching
- Cache TTL (Time To Live)

### Dependencies
- Moodle 4.5+
- PHP 8.1+
- `local_ai_manager` v1.0.0+
- Modern browser with WebRTC support

### Known Limitations
- Pronunciation assessment based on STT confidence, not phonetic analysis
- May be affected by regional accents and dialects
- Requires HTTPS for microphone access
- Audio files can be large (1-5 MB per minute)
- STT accuracy depends on service quality

---

## Security

### Security Fixes in v1.0.0
- Comprehensive input validation using Moodle PARAM_* constants
- Output escaping with `s()`, `format_text()`, `html_writer`
- Capability checks on all sensitive operations
- File access control in `pluginfile()` with ownership verification
- Directory traversal prevention in file operations
- CSRF token validation in all forms

### Reporting Security Issues
Please report security vulnerabilities to security@example.com. Do not open public GitHub issues for security concerns.

---

## [0.9.0-beta] - 2025-11-01 (Internal)

### Added
- Initial beta release for internal testing
- Basic recording and analysis functionality
- Simple feedback display
- Grade integration prototype

### Changed
- N/A (initial release)

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- N/A

---

## Versioning Scheme

This project uses [Semantic Versioning](https://semver.org/):
- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes

**Example:**
- v1.0.0 - Initial stable release
- v1.1.0 - Added new feature (backwards-compatible)
- v1.1.1 - Bug fix (backwards-compatible)
- v2.0.0 - Breaking change (not backwards-compatible)

---

## Support Lifecycle

- **Active Support:** 2 years from release
- **Security Fixes:** 3 years from release
- **End of Life:** Announced 6 months in advance

---

## Links

- [Moodle Plugins Directory](https://moodle.org/plugins/mod_aireading)
- [GitHub Repository](https://github.com/your-org/moodle-mod_aireading)
- [Documentation](docs/)
- [Issue Tracker](https://github.com/your-org/moodle-mod_aireading/issues)
- [Moodle.org Discussion](https://moodle.org/mod/forum)

---

**Maintained by:** ISB Bayern
**Copyright:** 2025 ISB Bayern
**License:** GNU GPL v3 or later
