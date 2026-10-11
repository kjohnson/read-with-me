document.querySelectorAll('form[data-auto-submit]').forEach((form) => form.requestSubmit());

const recorder = document.querySelector('[data-recorder]');

if (recorder) {
    import('./recorder').then(({ initRecorder }) => initRecorder(recorder));
}

const player = document.querySelector('[data-player]');

if (player) {
    import('./player').then(({ initPlayer }) => initPlayer(player));
}
