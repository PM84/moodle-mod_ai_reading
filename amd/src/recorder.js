// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Audio recorder with Voice Activity Detection (VAD)
 *
 * @module     mod_aireading/recorder
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {

    /**
     * Audio Recorder Class
     */
    class AudioRecorder {
        /**
         * Constructor
         * @param {Object} config Configuration object
         */
        constructor(config) {
            this.config = {
                aireadingId: config.aireadingid,
                cmId: config.cmid,
                silenceThreshold: config.silencethreshold || 10,
                maxDuration: config.maxduration || 600, // 10 minutes max
                wwwroot: config.wwwroot,
                sesskey: config.sesskey
            };

            // Recording state
            this.mediaRecorder = null;
            this.audioContext = null;
            this.analyser = null;
            this.audioStream = null;
            this.recordedChunks = [];
            this.isRecording = false;
            this.isPaused = false;
            this.startTime = null;
            this.elapsedTime = 0;
            this.timerInterval = null;
            this.silenceTimer = null;
            this.silenceStartTime = null;
            this.lastSoundTime = null;

            // VAD settings
            this.vadThreshold = 0.01; // RMS threshold for voice detection
            this.vadCheckInterval = 100; // Check every 100ms

            // DOM elements (will be set in init)
            this.elements = {};

            // String cache
            this.strings = {};
        }

        /**
         * Initialize the recorder
         */
        async init() {
            // Load required strings
            await this.loadStrings();

            // Get DOM elements
            this.elements = {
                startBtn: document.getElementById('aireading-start-recording'),
                pauseBtn: document.getElementById('aireading-pause-recording'),
                resumeBtn: document.getElementById('aireading-resume-recording'),
                stopBtn: document.getElementById('aireading-stop-recording'),
                timer: document.getElementById('aireading-timer'),
                status: document.getElementById('aireading-status'),
                waveform: document.getElementById('aireading-waveform'),
                canvas: document.getElementById('aireading-canvas')
            };

            // Check browser support
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.showError(this.strings.browsernotsupported);
                this.disableControls();
                return;
            }

            // Bind event handlers
            this.bindEvents();

            // Initialize canvas for waveform
            if (this.elements.canvas) {
                this.initCanvas();
            }

            this.updateStatus(this.strings.ready);
        }

        /**
         * Load language strings
         */
        async loadStrings() {
            const stringKeys = [
                {key: 'ready', component: 'mod_aireading'},
                {key: 'recording', component: 'mod_aireading'},
                {key: 'paused', component: 'mod_aireading'},
                {key: 'stopped', component: 'mod_aireading'},
                {key: 'uploading', component: 'mod_aireading'},
                {key: 'uploadcomplete', component: 'mod_aireading'},
                {key: 'silencedetected', component: 'mod_aireading'},
                {key: 'autostopping', component: 'mod_aireading'},
                {key: 'nomicrophone', component: 'mod_aireading'},
                {key: 'microphonedenied', component: 'mod_aireading'},
                {key: 'browsernotsupported', component: 'mod_aireading'},
                {key: 'recordingtoolong', component: 'mod_aireading'},
                {key: 'recordingtooshort', component: 'mod_aireading'},
                {key: 'uploadfailed', component: 'mod_aireading'}
            ];

            const strings = await Str.get_strings(stringKeys);
            stringKeys.forEach((key, index) => {
                this.strings[key.key] = strings[index];
            });
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            if (this.elements.startBtn) {
                this.elements.startBtn.addEventListener('click', () => this.startRecording());
            }
            if (this.elements.pauseBtn) {
                this.elements.pauseBtn.addEventListener('click', () => this.pauseRecording());
            }
            if (this.elements.resumeBtn) {
                this.elements.resumeBtn.addEventListener('click', () => this.resumeRecording());
            }
            if (this.elements.stopBtn) {
                this.elements.stopBtn.addEventListener('click', () => this.stopRecording());
            }
        }

        /**
         * Initialize canvas for waveform visualization
         */
        initCanvas() {
            const canvas = this.elements.canvas;
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
        }

        /**
         * Start recording
         */
        async startRecording() {
            try {
                // Request microphone access with optimal settings for STT
                const constraints = {
                    audio: {
                        sampleRate: 16000,
                        channelCount: 1,
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                };

                this.audioStream = await navigator.mediaDevices.getUserMedia(constraints);

                // Setup audio context for VAD
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const source = this.audioContext.createMediaStreamSource(this.audioStream);
                this.analyser = this.audioContext.createAnalyser();
                this.analyser.fftSize = 2048;
                source.connect(this.analyser);

                // Setup MediaRecorder
                const mimeType = this.getSupportedMimeType();
                this.mediaRecorder = new MediaRecorder(this.audioStream, {
                    mimeType: mimeType
                });

                this.recordedChunks = [];

                this.mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        this.recordedChunks.push(event.data);
                    }
                };

                this.mediaRecorder.onstop = () => {
                    this.handleRecordingStopped();
                };

                // Start recording
                this.mediaRecorder.start(100); // Collect data every 100ms
                this.isRecording = true;
                this.isPaused = false;
                this.startTime = Date.now() - this.elapsedTime;
                this.lastSoundTime = Date.now();

                // Start timer
                this.startTimer();

                // Start VAD
                this.startVAD();

                // Start waveform visualization
                if (this.elements.canvas) {
                    this.visualizeWaveform();
                }

                // Update UI
                this.updateRecordingUI();
                this.updateStatus(this.strings.recording);

            } catch (error) {
                if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                    this.showError(this.strings.microphonedenied);
                } else if (error.name === 'NotFoundError') {
                    this.showError(this.strings.nomicrophone);
                } else {
                    this.showError(error.message);
                }
            }
        }

        /**
         * Get supported MIME type
         */
        getSupportedMimeType() {
            const types = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/ogg;codecs=opus',
                'audio/mp4'
            ];

            for (let type of types) {
                if (MediaRecorder.isTypeSupported(type)) {
                    return type;
                }
            }
            return '';
        }

        /**
         * Pause recording
         */
        pauseRecording() {
            if (this.mediaRecorder && this.isRecording && !this.isPaused) {
                this.mediaRecorder.pause();
                this.isPaused = true;
                this.stopTimer();
                this.stopVAD();
                this.updatePausedUI();
                this.updateStatus(this.strings.paused);
            }
        }

        /**
         * Resume recording
         */
        resumeRecording() {
            if (this.mediaRecorder && this.isRecording && this.isPaused) {
                this.mediaRecorder.resume();
                this.isPaused = false;
                this.startTime = Date.now() - this.elapsedTime;
                this.lastSoundTime = Date.now();
                this.startTimer();
                this.startVAD();
                this.updateRecordingUI();
                this.updateStatus(this.strings.recording);
            }
        }

        /**
         * Stop recording
         */
        stopRecording() {
            if (this.mediaRecorder && this.isRecording) {
                this.stopTimer();
                this.stopVAD();
                this.mediaRecorder.stop();
                this.audioStream.getTracks().forEach(track => track.stop());
                if (this.audioContext) {
                    this.audioContext.close();
                }
                this.isRecording = false;
                this.isPaused = false;
                this.updateStoppedUI();
                this.updateStatus(this.strings.stopped);
            }
        }

        /**
         * Start timer
         */
        startTimer() {
            this.timerInterval = setInterval(() => {
                this.elapsedTime = Date.now() - this.startTime;
                this.updateTimerDisplay();

                // Check max duration
                if (this.elapsedTime >= this.config.maxDuration * 1000) {
                    this.showError(this.strings.recordingtoolong);
                    this.stopRecording();
                }
            }, 100);
        }

        /**
         * Stop timer
         */
        stopTimer() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        }

        /**
         * Update timer display
         */
        updateTimerDisplay() {
            if (this.elements.timer) {
                const seconds = Math.floor(this.elapsedTime / 1000);
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds % 60;
                this.elements.timer.textContent =
                    String(minutes).padStart(2, '0') + ':' + String(remainingSeconds).padStart(2, '0');
            }
        }

        /**
         * Start Voice Activity Detection
         */
        startVAD() {
            this.vadInterval = setInterval(() => {
                if (!this.analyser) {
                    return;
                }

                const bufferLength = this.analyser.fftSize;
                const dataArray = new Float32Array(bufferLength);
                this.analyser.getFloatTimeDomainData(dataArray);

                // Calculate RMS (Root Mean Square)
                let sum = 0;
                for (let i = 0; i < bufferLength; i++) {
                    sum += dataArray[i] * dataArray[i];
                }
                const rms = Math.sqrt(sum / bufferLength);

                // Check if sound is detected
                if (rms > this.vadThreshold) {
                    // Sound detected
                    this.lastSoundTime = Date.now();
                    if (this.silenceStartTime) {
                        this.silenceStartTime = null;
                        this.updateStatus(this.strings.recording);
                    }
                } else {
                    // Silence detected
                    if (!this.silenceStartTime) {
                        this.silenceStartTime = Date.now();
                    }

                    const silenceDuration = (Date.now() - this.silenceStartTime) / 1000;
                    if (silenceDuration >= this.config.silenceThreshold) {
                        this.updateStatus(this.strings.autostopping);
                        this.stopRecording();
                    } else if (silenceDuration >= this.config.silenceThreshold - 3) {
                        // Warn 3 seconds before auto-stop
                        const remaining = Math.ceil(this.config.silenceThreshold - silenceDuration);
                        this.updateStatus(this.strings.silencedetected + ' (' + remaining + 's)');
                    }
                }
            }, this.vadCheckInterval);
        }

        /**
         * Stop VAD
         */
        stopVAD() {
            if (this.vadInterval) {
                clearInterval(this.vadInterval);
                this.vadInterval = null;
            }
            this.silenceStartTime = null;
        }

        /**
         * Visualize waveform
         */
        visualizeWaveform() {
            if (!this.analyser || !this.elements.canvas) {
                return;
            }

            const canvas = this.elements.canvas;
            const canvasCtx = canvas.getContext('2d');
            const bufferLength = this.analyser.fftSize;
            const dataArray = new Uint8Array(bufferLength);

            const draw = () => {
                if (!this.isRecording || this.isPaused) {
                    return;
                }

                requestAnimationFrame(draw);

                this.analyser.getByteTimeDomainData(dataArray);

                canvasCtx.fillStyle = 'rgb(240, 240, 240)';
                canvasCtx.fillRect(0, 0, canvas.width, canvas.height);

                canvasCtx.lineWidth = 2;
                canvasCtx.strokeStyle = 'rgb(0, 123, 255)';
                canvasCtx.beginPath();

                const sliceWidth = canvas.width / bufferLength;
                let x = 0;

                for (let i = 0; i < bufferLength; i++) {
                    const v = dataArray[i] / 128.0;
                    const y = v * canvas.height / 2;

                    if (i === 0) {
                        canvasCtx.moveTo(x, y);
                    } else {
                        canvasCtx.lineTo(x, y);
                    }

                    x += sliceWidth;
                }

                canvasCtx.lineTo(canvas.width, canvas.height / 2);
                canvasCtx.stroke();
            };

            draw();
        }

        /**
         * Handle recording stopped
         */
        async handleRecordingStopped() {
            // Check minimum duration (2 seconds)
            if (this.elapsedTime < 2000) {
                this.showError(this.strings.recordingtooshort);
                this.reset();
                return;
            }

            // Create blob from recorded chunks
            const mimeType = this.mediaRecorder.mimeType;
            const blob = new Blob(this.recordedChunks, {type: mimeType});

            // Upload audio
            await this.uploadAudio(blob);
        }

        /**
         * Upload audio to server
         */
        async uploadAudio(blob) {
            try {
                this.updateStatus(this.strings.uploading);

                // Create FormData
                const formData = new FormData();
                formData.append('aireadingid', this.config.aireadingId);
                formData.append('audiofile', blob, 'recording.webm');
                formData.append('duration', Math.floor(this.elapsedTime / 1000));
                formData.append('sesskey', this.config.sesskey);

                // Upload via AJAX
                const response = await fetch(this.config.wwwroot + '/mod/aireading/submit_attempt.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    this.updateStatus(this.strings.uploadcomplete);
                    Notification.addNotification({
                        message: this.strings.uploadcomplete,
                        type: 'success'
                    });
                    // Reload page to show new attempt
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    this.showError(result.error || this.strings.uploadfailed);
                }

            } catch (error) {
                this.showError(this.strings.uploadfailed + ': ' + error.message);
            }

            this.reset();
        }

        /**
         * Reset recorder state
         */
        reset() {
            this.elapsedTime = 0;
            this.recordedChunks = [];
            this.updateTimerDisplay();
            this.updateReadyUI();
        }

        /**
         * Update UI for recording state
         */
        updateRecordingUI() {
            if (this.elements.startBtn) {
                this.elements.startBtn.style.display = 'none';
            }
            if (this.elements.pauseBtn) {
                this.elements.pauseBtn.style.display = 'inline-block';
            }
            if (this.elements.resumeBtn) {
                this.elements.resumeBtn.style.display = 'none';
            }
            if (this.elements.stopBtn) {
                this.elements.stopBtn.style.display = 'inline-block';
            }
        }

        /**
         * Update UI for paused state
         */
        updatePausedUI() {
            if (this.elements.pauseBtn) {
                this.elements.pauseBtn.style.display = 'none';
            }
            if (this.elements.resumeBtn) {
                this.elements.resumeBtn.style.display = 'inline-block';
            }
        }

        /**
         * Update UI for stopped state
         */
        updateStoppedUI() {
            if (this.elements.pauseBtn) {
                this.elements.pauseBtn.style.display = 'none';
            }
            if (this.elements.resumeBtn) {
                this.elements.resumeBtn.style.display = 'none';
            }
            if (this.elements.stopBtn) {
                this.elements.stopBtn.style.display = 'none';
            }
        }

        /**
         * Update UI for ready state
         */
        updateReadyUI() {
            if (this.elements.startBtn) {
                this.elements.startBtn.style.display = 'inline-block';
            }
            if (this.elements.pauseBtn) {
                this.elements.pauseBtn.style.display = 'none';
            }
            if (this.elements.resumeBtn) {
                this.elements.resumeBtn.style.display = 'none';
            }
            if (this.elements.stopBtn) {
                this.elements.stopBtn.style.display = 'none';
            }
        }

        /**
         * Update status message
         */
        updateStatus(message) {
            if (this.elements.status) {
                this.elements.status.textContent = message;
                this.elements.status.setAttribute('aria-live', 'polite');
            }
        }

        /**
         * Show error message
         */
        showError(message) {
            Notification.addNotification({
                message: message,
                type: 'error'
            });
            this.updateStatus(message);
        }

        /**
         * Disable all controls
         */
        disableControls() {
            Object.values(this.elements).forEach(element => {
                if (element && element.disabled !== undefined) {
                    element.disabled = true;
                }
            });
        }
    }

    return {
        /**
         * Initialize the recorder
         * @param {Object} config Configuration object
         */
        init: function(config) {
            const recorder = new AudioRecorder(config);
            recorder.init();
        }
    };
});
