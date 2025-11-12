# AI Reading Trainer (`mod_aireading`)

![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)
![License](https://img.shields.io/badge/License-GPLv3-green)
![Version](https://img.shields.io/badge/Version-1.0.0-blue)

**Automated reading fluency and pronunciation assessment using Speech-to-Text AI**

---

## 📋 Overview

The AI Reading Trainer is a Moodle activity module that helps students improve their reading skills through automated feedback. Students record themselves reading a text, and the system analyzes their performance using Speech-to-Text technology to provide detailed feedback on:

- **Accuracy** - How correctly words were read
- **Fluency** - Reading speed and smoothness
- **Pronunciation** (optional) - Quality of word pronunciation

Perfect for:
- 📚 Primary school reading practice
- 🌍 Foreign language learning
- 🎓 Reading fluency assessment
- ♿ Special education support

---

## ✨ Features

### Core Features
✅ **Audio Recording** - WebRTC-based recording with automatic silence detection
✅ **AI-Powered Analysis** - Automated assessment using Speech-to-Text
✅ **Visual Feedback** - Color-coded text highlighting showing errors
✅ **Progress Tracking** - Comprehensive statistics and charts
✅ **Grade Integration** - Automatic grading with multiple strategies
✅ **Multi-Language Support** - German, English, and more

### Advanced Features
✅ **Pronunciation Assessment** - Optional word-level pronunciation evaluation
✅ **Beginner Mode** - Simplified interface for young learners
✅ **Teacher Reports** - Word difficulty analysis and class statistics
✅ **Flexible Grading** - Highest, latest, or average grade methods
✅ **Attempt Limits** - Configurable maximum attempts
✅ **Accessibility** - WCAG 2.1 AA compliant

---

## 🎯 Use Cases

### Primary School / Reading Beginners
- Simplified UI with large buttons and clear feedback
- Tolerance for reading pauses (configurable silence threshold)
- Progress visualization showing improvement over time
- Positive reinforcement with encouraging messages

### Foreign Language Learning
- Pronunciation assessment with confidence scores
- Support for multiple languages (de, en, fr, es)
- Word-level feedback for targeted practice
- Teacher reports identifying difficult words for the class

---

## 📦 Installation

### Requirements

**Server:**
- Moodle 4.5+ (LTS recommended)
- PHP 8.1+
- PostgreSQL 12+ or MySQL 8.0+
- Sufficient storage for audio files (1-5 MB per attempt)

**Required Plugin:**
- [`local_ai_manager`](https://moodle.org/plugins/local_ai_manager) v1.0.0+
  - Must support word-level timestamps
  - Must provide confidence scores per word

**Client (Student Browser):**
- WebRTC support (getUserMedia, MediaRecorder)
- Modern browser: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- HTTPS connection (required for microphone access)

### Installation Steps

#### Via Moodle UI

1. Download latest release from [Moodle Plugins Directory](https://moodle.org/plugins/mod_aireading)
2. Go to **Site administration → Plugins → Install plugins**
3. Upload ZIP file and follow installation wizard
4. Complete database upgrade

#### Via Command Line

```bash
cd /path/to/moodle/mod
git clone https://github.com/your-org/moodle-mod_aireading.git aireading
cd aireading
git checkout v1.0.0

# Run upgrade
php /path/to/moodle/admin/cli/upgrade.php --non-interactive
```

### Configuration

1. **Configure AI Manager Integration**
   - Go to **Site administration → AI Manager → Connectors**
   - Configure STT connector (Whisper, Google STT, Azure Speech, etc.)
   - Verify connector provides:
     - Word-level timestamps
     - Confidence scores per word

2. **Plugin Settings** (Site administration → Plugins → Activity modules → AI Reading)
   - `analysis_batch_size` - Attempts processed per cron run (default: 50)
   - `max_audio_filesize` - Maximum audio file size (default: 50MB)
   - `default_silence_threshold` - Seconds of silence for auto-stop (default: 10)

3. **Ensure Cron Runs Regularly**
   ```bash
   # Recommended: Every minute
   * * * * * php /path/to/moodle/admin/cli/cron.php
   ```

---

## 🚀 Quick Start

### For Teachers

**1. Create an Activity**
- Turn editing on in your course
- Add activity → AI Reading Trainer
- Configure:
  - **Reading text** - The text students will read
  - **Language** - Language of the text (de, en, etc.)
  - **Target WPM** - Target words per minute (e.g., 100)
  - **Maximum attempts** - How many tries students get (0 = unlimited)
  - **Grading method** - Highest, latest, or average

**2. Optional: Enable Pronunciation Assessment**
- Check "Enable pronunciation assessment"
- Set minimum confidence threshold (recommended: 80%)
- Best for foreign language learning

**3. View Results**
- Click on activity → View all attempts
- See detailed statistics and progress charts
- Access word difficulty report (if pronunciation enabled)

### For Students

**1. Open the Activity**
- Click on the AI Reading activity
- Read the text to familiarize yourself

**2. Record Your Reading**
- Click "Start Recording"
- Read the text clearly into your microphone
- Recording stops automatically after silence, or click "Stop"

**3. View Feedback**
- See color-coded text:
  - 🟢 Green = Correct
  - 🟡 Yellow = Fluency issue (pause, hesitation)
  - 🔴 Red = Accuracy error (wrong word)
  - 🟠 Orange = Pronunciation issue (if enabled)
- Check your WPM, accuracy, and fluency scores
- Compare with previous attempts

**4. Improve and Retry**
- Review errors and practice problem words
- Record again to improve your score
- Track your progress over attempts

---

## 📊 Grading Methods

The module supports three grading strategies:

| Method | Description | Best For |
|--------|-------------|----------|
| **Highest Grade** | Uses best attempt score | Encouraging improvement, final assessment |
| **Latest Grade** | Uses most recent attempt | Showing current ability, progress tracking |
| **Average Grade** | Average of all attempts | Consistent performance evaluation |

Grades are automatically calculated from:
- Accuracy score (50%)
- Fluency score (30%)
- Pronunciation score (20%, if enabled)
- WPM bonus (extra points for exceeding target)

---

## 🎨 Pronunciation Assessment Feature

### How It Works

When enabled, the system evaluates the pronunciation quality of **every word** based on the STT confidence scores:

- **Good** (≥ min confidence) - Word pronounced clearly ✅
- **Acceptable** (min confidence - 10%) - Minor issues ⚠️
- **Poor** (< min confidence - 10%) - Needs improvement ❌

### When to Use

**Recommended for:**
- Foreign language learning
- Accent reduction training
- Speech therapy support
- Advanced reading assessment

**Not recommended for:**
- Native language reading (beginners)
- Dialects significantly different from standard language
- Very young children

### Settings

- **Minimum confidence** - Threshold for "good" pronunciation
  - 70% - Very lenient (beginners, strong accents)
  - 80% - Recommended (most use cases)
  - 90% - Strict (advanced learners)

### Limitations

- Based on STT confidence, not phonetic analysis
- May be affected by:
  - Microphone quality
  - Background noise
  - Regional accents
  - Speech rate
- Teachers should review flagged words in context

---

## 📈 Statistics & Reports

### For Students

- **Progress Chart** - WPM improvement over attempts
- **Error Analysis** - Types and frequency of errors
- **Pronunciation Trends** - Average confidence over time (if enabled)
- **Comparison** - Your performance vs. class average

### For Teachers

- **Class Overview** - All students' latest attempts
- **Word Difficulty Report** - Most challenging words for the class
- **Progress Tracking** - Individual student improvement
- **Pronunciation Hotspots** - Words needing extra attention

---

## 🔒 Privacy & Data Protection

### Data Collected
- Audio recordings of reading attempts
- Speech-to-text transcriptions
- Analysis results (scores, errors)
- User metadata (timestamps, user ID, course ID)

### Data Usage
- Educational feedback and assessment
- Progress tracking
- Statistical analysis for teachers

### Data Retention
- Audio files: Configurable (default: course completion + 1 year)
- Analysis results: Course completion + 2 years
- Personal data: Deleted upon user account deletion

### Data Sharing
- Audio files sent to configured STT service for transcription
- Analysis results accessible to course teachers and administrators
- No third-party sharing for marketing purposes

### User Rights (GDPR Compliant)
- Right to access personal data
- Right to export personal data
- Right to delete personal data
- Right to object to processing

---

## 🛠️ Troubleshooting

### Students Can't Record

**Problem:** "Start Recording" button doesn't work

**Solutions:**
1. Ensure HTTPS is enabled (required for microphone access)
2. Grant microphone permissions in browser
3. Check browser compatibility (Chrome 90+, Firefox 88+, etc.)
4. Try a different browser
5. Check microphone is not used by another application

### Analysis Not Running

**Problem:** Attempts stuck as "Submitted" without results

**Solutions:**
1. Check cron is running: `php admin/cli/cron.php`
2. Verify AI Manager connectivity (Site admin → AI Manager)
3. Check scheduled task is enabled (Server → Scheduled tasks → analyze_attempt_task)
4. Review cron logs for errors
5. Ensure STT service is responding

### Poor Accuracy

**Problem:** STT transcription is very inaccurate

**Solutions:**
1. Verify audio quality (check recordings are clear)
2. Ensure correct language is selected
3. Check microphone quality and positioning
4. Reduce background noise
5. Speak clearly and at moderate pace
6. Verify STT service configuration

### Performance Issues

**Problem:** System slow with many students

**Solutions:**
1. Enable caching (should be enabled by default)
2. Increase `analysis_batch_size` in plugin settings
3. Check database has proper indexes
4. Monitor STT API response times
5. Consider load balancing for large deployments (see docs/PERFORMANCE.md)

---

## 🤝 Support & Contributing

### Getting Help

- 📖 **Documentation:** See `docs/` folder
- 🐛 **Bug Reports:** [GitHub Issues](https://github.com/your-org/moodle-mod_aireading/issues)
- 💬 **Discussion:** [Moodle.org Forums](https://moodle.org/mod/forum)
- 📧 **Email:** support@example.com

### Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development

```bash
# Clone repository
git clone https://github.com/your-org/moodle-mod_aireading.git

# Run tests
php admin/cli/phpunit.php --testsuite=mod_aireading_testsuite

# Check coding style
php admin/cli/codechecker.sh mod/aireading

# Run Behat tests
php admin/tool/behat/cli/run.php --tags=@mod_aireading
```

---

## 📄 License

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.

---

## 👥 Credits

**Copyright:** 2025 ISB Bayern
**Author:** Dr. Peter Mayer
**Maintainer:** ISB Bayern

### Acknowledgments

- Moodle Community for the excellent platform
- AI Manager plugin for STT integration
- All contributors and testers

---

## 📚 Additional Documentation

- [DEPLOYMENT.md](docs/DEPLOYMENT.md) - Full deployment guide
- [PERFORMANCE.md](docs/PERFORMANCE.md) - Performance tuning and scaling
- [ACCESSIBILITY.md](docs/ACCESSIBILITY.md) - Accessibility features and compliance
- [COMPLIANCE.md](docs/COMPLIANCE.md) - GDPR and data protection
- [CHANGELOG.md](CHANGELOG.md) - Version history
- [PRONUNCIATION_GUIDE.md](docs/PRONUNCIATION_GUIDE.md) - Pronunciation assessment details

---

**Made with ❤️ for better reading education**
