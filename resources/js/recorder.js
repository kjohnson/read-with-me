const PREFERRED_MIME_TYPES = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
const UPLOAD_RETRY_DELAYS = [1000, 3000, 8000];

/**
 * Record a reading of a story, one page at a time.
 *
 * Each page is recorded with its own MediaRecorder. Advancing to the next page
 * stops the current recorder, queues its audio for upload, and immediately
 * starts recording the new page.
 */
export function initRecorder(root) {
    const config = JSON.parse(root.dataset.config);
    const pageCount = config.pages.length;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const mimeType = PREFERRED_MIME_TYPES.find((type) => window.MediaRecorder?.isTypeSupported(type)) ?? '';

    const el = (selector) => root.querySelector(selector);
    const elements = {
        intro: el('[data-intro]'),
        introError: el('[data-intro-error]'),
        stage: el('[data-stage]'),
        pageNumber: el('[data-page-number]'),
        pageText: el('[data-page-text]'),
        timer: el('[data-timer]'),
        level: el('[data-level]'),
        pulse: el('[data-pulse]'),
        recordingLabel: el('[data-recording-label]'),
        uploadStatus: el('[data-upload-status]'),
        error: el('[data-error]'),
        start: el('[data-action="start"]'),
        back: el('[data-action="back"]'),
        redo: el('[data-action="redo"]'),
        pause: el('[data-action="pause"]'),
        next: el('[data-action="next"]'),
        finish: el('[data-action="finish"]'),
    };

    const state = {
        page: config.startPage,
        stream: null,
        recorder: null,
        elapsedMs: 0,
        finishing: false,
        recordedPages: new Set(config.recordedPages),
        uploadStatus: new Map(),
        uploadChains: new Map(),
        failedBlobs: new Map(),
    };

    elements.pageNumber.textContent = state.page;
    renderDots();

    elements.start.addEventListener('click', start);
    elements.next.addEventListener('click', next);
    elements.back.addEventListener('click', back);
    elements.redo.addEventListener('click', redo);
    elements.pause.addEventListener('click', togglePause);
    elements.finish.addEventListener('click', finish);
    root.querySelector('[data-page-dots]').addEventListener('click', retryFailedUpload);

    window.addEventListener('beforeunload', (event) => {
        if (!state.finishing && (state.recorder || isUploading())) {
            event.preventDefault();
        }
    });

    setInterval(() => {
        if (state.recorder?.state === 'recording') {
            state.elapsedMs += 250;
            elements.timer.textContent = formatTime(state.elapsedMs / 1000);
        }
    }, 250);

    async function start() {
        if (!window.MediaRecorder || !navigator.mediaDevices?.getUserMedia) {
            showIntroError("Sorry, this browser can't record audio. Try a recent version of Chrome, Firefox, Safari, or Edge.");
            return;
        }

        elements.start.disabled = true;

        try {
            state.stream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
            });
        } catch {
            elements.start.disabled = false;
            showIntroError('We need your microphone to record. Please allow microphone access and try again.');
            return;
        }

        startLevelMeter(state.stream);
        elements.intro.classList.add('hidden');
        elements.stage.classList.remove('hidden');
        beginPage(state.page);
    }

    function beginPage(page) {
        state.page = page;
        state.elapsedMs = 0;
        state.recorder = createRecorder(page);
        state.recorder.start(1000);
        hideError();
        render();
    }

    function createRecorder(page) {
        const recorder = new MediaRecorder(state.stream, { ...(mimeType && { mimeType }), audioBitsPerSecond: 64000 });
        const chunks = [];

        recorder.page = page;
        recorder.discard = false;
        recorder.addEventListener('dataavailable', (event) => event.data.size > 0 && chunks.push(event.data));
        recorder.finished = new Promise((resolve) => {
            recorder.addEventListener('stop', () => {
                resolve(recorder.discard ? null : new Blob(chunks, { type: recorder.mimeType || mimeType || 'audio/webm' }));
            });
        });

        return recorder;
    }

    /**
     * Stop the current recorder, resolving with its audio (or null when discarded).
     */
    function stopRecorder({ discard = false } = {}) {
        const recorder = state.recorder;
        state.recorder = null;

        if (!recorder) {
            return Promise.resolve(null);
        }

        recorder.discard = discard;

        if (recorder.state !== 'inactive') {
            recorder.stop();
        }

        return recorder.finished.then((blob) => (blob ? { page: recorder.page, blob } : null));
    }

    function next() {
        if (state.page >= pageCount) {
            return;
        }

        stopRecorder().then((take) => take && queueUpload(take.page, take.blob));
        beginPage(state.page + 1);
    }

    function back() {
        if (state.page <= 1) {
            return;
        }

        stopRecorder({ discard: true });
        beginPage(state.page - 1);
    }

    function redo() {
        stopRecorder({ discard: true });
        beginPage(state.page);
    }

    function togglePause() {
        const recorder = state.recorder;

        if (recorder?.state === 'recording') {
            recorder.pause();
        } else if (recorder?.state === 'paused') {
            recorder.resume();
        }

        render();
    }

    async function finish() {
        state.finishing = true;
        render();

        const take = await stopRecorder();

        if (take) {
            queueUpload(take.page, take.blob);
        }

        state.stream?.getTracks().forEach((track) => track.stop());
        elements.uploadStatus.textContent = 'Saving your recording…';

        await Promise.allSettled([...state.uploadChains.values()]);

        if (state.failedBlobs.size > 0) {
            return failFinish('Some pages did not upload. Tap a red page number to try again.');
        }

        try {
            const response = await fetch(config.finishUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            });
            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                const missing = body.missing_pages?.length ? ` Missing pages: ${body.missing_pages.join(', ')}.` : '';
                return failFinish((body.message ?? 'We could not finish your recording.') + missing, body.missing_pages?.[0]);
            }

            window.location.assign(body.redirect);
        } catch {
            failFinish('We could not reach the server. Check your connection and press “I\'m done!” again.');
        }
    }

    /**
     * Show an error after finishing failed, letting the reader fix it and try again.
     */
    async function failFinish(message, pageToRecord = null) {
        state.finishing = false;

        if (state.stream?.getTracks().every((track) => track.readyState === 'ended')) {
            state.stream = await navigator.mediaDevices.getUserMedia({ audio: true }).catch(() => null);

            if (state.stream) {
                startLevelMeter(state.stream);
            }
        }

        if (pageToRecord && state.stream) {
            beginPage(pageToRecord);
        }

        showError(message);
        render();
    }

    /**
     * Upload a page's audio. Uploads for the same page run one after another, so the newest take always wins.
     */
    function queueUpload(page, blob) {
        if (blob.size === 0) {
            showError(`We didn't hear anything on page ${page}. Please go back and read it again.`);
            return;
        }

        state.failedBlobs.delete(page);
        state.uploadStatus.set(page, 'uploading');
        render();

        const chain = (state.uploadChains.get(page) ?? Promise.resolve())
            .then(() => uploadWithRetries(page, blob))
            .then(() => {
                state.recordedPages.add(page);
                state.uploadStatus.set(page, 'uploaded');
            })
            .catch(() => {
                state.failedBlobs.set(page, blob);
                state.uploadStatus.set(page, 'failed');
            })
            .finally(render);

        state.uploadChains.set(page, chain);
    }

    async function uploadWithRetries(page, blob) {
        for (let attempt = 0; ; attempt++) {
            try {
                return await upload(page, blob);
            } catch (error) {
                if (attempt >= UPLOAD_RETRY_DELAYS.length || error.permanent) {
                    throw error;
                }

                await new Promise((resolve) => setTimeout(resolve, UPLOAD_RETRY_DELAYS[attempt]));
            }
        }
    }

    async function upload(page, blob) {
        const form = new FormData();
        form.append('audio', blob, `page-${page}.${extensionFor(blob.type)}`);

        const response = await fetch(config.uploadUrl.replace('__PAGE__', page), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            body: form,
        });

        if (!response.ok) {
            const error = new Error(`Upload failed with status ${response.status}`);
            error.permanent = [401, 403, 404, 409, 413, 419, 422].includes(response.status);
            throw error;
        }
    }

    function retryFailedUpload(event) {
        const page = Number(event.target.closest('[data-page-dot]')?.dataset.pageDot);

        if (state.failedBlobs.has(page)) {
            queueUpload(page, state.failedBlobs.get(page));
        }
    }

    function isUploading() {
        return [...state.uploadStatus.values()].includes('uploading');
    }

    function render() {
        const isLastPage = state.page === pageCount;
        const isPaused = state.recorder?.state === 'paused';

        elements.pageNumber.textContent = state.page;
        elements.pageText.textContent = config.pages[state.page - 1];
        elements.timer.textContent = formatTime(state.elapsedMs / 1000);

        elements.back.disabled = state.page <= 1 || state.finishing;
        elements.redo.disabled = state.finishing;
        elements.pause.disabled = state.finishing || !state.recorder;
        elements.pause.textContent = isPaused ? '▶ Resume' : '⏸ Pause';
        elements.next.disabled = isPaused || state.finishing;
        elements.finish.disabled = isPaused || state.finishing;
        elements.next.classList.toggle('hidden', isLastPage);
        elements.finish.classList.toggle('hidden', !isLastPage);

        elements.pulse.classList.toggle('hidden', !state.recorder || isPaused);
        elements.recordingLabel.textContent = state.recorder ? (isPaused ? 'Paused' : 'Recording') : 'Stopped';

        const uploading = [...state.uploadStatus.values()].filter((status) => status === 'uploading').length;
        const failed = state.failedBlobs.size;

        if (!state.finishing) {
            elements.uploadStatus.textContent = failed > 0
                ? `${failed} page${failed === 1 ? '' : 's'} didn't upload. Tap the red page number to try again.`
                : uploading > 0 ? `Saving ${uploading} page${uploading === 1 ? '' : 's'}…` : '';
        }

        renderDots();
    }

    function renderDots() {
        root.querySelectorAll('[data-page-dot]').forEach((dot) => {
            const page = Number(dot.dataset.pageDot);
            const status = state.uploadStatus.get(page);
            const classes = {
                'border-violet-500 text-violet-700 ring-4 ring-violet-100': page === state.page,
                'border-emerald-400 bg-emerald-400 text-white': page !== state.page && state.recordedPages.has(page) && status !== 'uploading',
                'animate-pulse border-sky-400 text-sky-600': page !== state.page && status === 'uploading',
                'cursor-pointer border-rose-500 bg-rose-500 text-white': status === 'failed',
            };

            Object.entries(classes).forEach(([names, active]) => names.split(' ').forEach((name) => dot.classList.toggle(name, active)));
            dot.title = status === 'failed' ? 'Upload failed. Tap to try again.' : `Page ${page}`;
        });
    }

    function startLevelMeter(stream) {
        const AudioContext = window.AudioContext || window.webkitAudioContext;

        if (!AudioContext) {
            return;
        }

        const context = new AudioContext();
        const analyser = context.createAnalyser();
        const samples = new Uint8Array(analyser.fftSize);
        context.createMediaStreamSource(stream).connect(analyser);

        const draw = () => {
            analyser.getByteTimeDomainData(samples);
            const rms = Math.sqrt(samples.reduce((sum, sample) => sum + ((sample - 128) / 128) ** 2, 0) / samples.length);
            elements.level.style.width = `${Math.min(100, rms * 400)}%`;

            if (stream.active) {
                requestAnimationFrame(draw);
            } else {
                elements.level.style.width = '0%';
                context.close();
            }
        };

        draw();
    }

    function showIntroError(message) {
        elements.introError.textContent = message;
        elements.introError.classList.remove('hidden');
    }

    function showError(message) {
        elements.error.textContent = message;
        elements.error.classList.remove('hidden');
    }

    function hideError() {
        elements.error.classList.add('hidden');
    }
}

function extensionFor(type) {
    if (type.includes('ogg')) return 'ogg';
    if (type.includes('mp4')) return 'm4a';

    return 'webm';
}

export function formatTime(seconds) {
    const whole = Math.max(0, Math.floor(seconds));

    return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, '0')}`;
}
