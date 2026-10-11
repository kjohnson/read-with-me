import { formatTime } from './recorder';

/**
 * Play a recorded story as a single audio file, highlighting each word as it is
 * read and turning the pages automatically.
 */
export function initPlayer(root) {
    const config = JSON.parse(root.dataset.config);
    const pages = config.pages;
    const audio = new Audio(config.audioUrl);
    audio.preload = 'auto';

    const el = (selector) => root.querySelector(selector);
    const elements = {
        pageNumber: el('[data-page-number]'),
        pageText: el('[data-page-text]'),
        theEnd: el('[data-the-end]'),
        seek: el('[data-seek]'),
        currentTime: el('[data-current-time]'),
        totalTime: el('[data-total-time]'),
        play: el('[data-action="play"]'),
        previous: el('[data-action="previous"]'),
        next: el('[data-action="next"]'),
    };

    let shownPageIndex = -1;
    let wordElements = [];
    let isSeeking = false;

    elements.totalTime.textContent = formatTime(config.duration);

    elements.play.addEventListener('click', togglePlay);
    elements.previous.addEventListener('click', () => seekToPage(currentPageIndex() - (timeIntoPage() > 1.5 ? 0 : 1)));
    elements.next.addEventListener('click', () => seekToPage(currentPageIndex() + 1));

    root.querySelectorAll('[data-page-button]').forEach((button) => {
        button.addEventListener('click', () => seekToPage(Number(button.dataset.pageButton) - 1));
    });

    elements.pageText.addEventListener('click', (event) => {
        const word = event.target.closest('[data-word]');

        if (word) {
            const page = pages[shownPageIndex];
            seekTo(page.offset + page.timings[Number(word.dataset.word)][0], { play: true });
        }
    });

    elements.seek.addEventListener('input', () => {
        isSeeking = true;
        sync(Number(elements.seek.value));
    });
    elements.seek.addEventListener('change', () => {
        isSeeking = false;
        seekTo(Number(elements.seek.value));
    });

    document.addEventListener('keydown', (event) => {
        if (event.code === 'Space' && !['INPUT', 'BUTTON', 'TEXTAREA'].includes(event.target.tagName)) {
            event.preventDefault();
            togglePlay();
        }
    });

    audio.addEventListener('play', () => {
        elements.theEnd.classList.add('hidden');
        elements.pageText.classList.remove('hidden');
        renderPlayButton();
        requestAnimationFrame(tick);
    });
    audio.addEventListener('pause', renderPlayButton);
    audio.addEventListener('seeked', () => sync(audio.currentTime));
    audio.addEventListener('ended', () => {
        renderPlayButton();
        wordElements.forEach((word) => word.classList.replace('is-current', 'is-read'));
        elements.pageText.classList.add('hidden');
        elements.theEnd.classList.remove('hidden');
    });

    sync(0);

    function tick() {
        if (!isSeeking) {
            sync(audio.currentTime);
        }

        if (!audio.paused) {
            requestAnimationFrame(tick);
        }
    }

    function sync(time) {
        const pageIndex = pageIndexAt(time);

        if (pageIndex !== shownPageIndex) {
            showPage(pageIndex);
        }

        const page = pages[pageIndex];
        const localTime = time - page.offset;
        let currentWord = -1;

        page.timings.forEach(([start], index) => {
            if (start <= localTime) {
                currentWord = index;
            }
        });

        wordElements.forEach((word, index) => {
            word.classList.toggle('is-read', index < currentWord);
            word.classList.toggle('is-current', index === currentWord);
        });

        if (!isSeeking) {
            elements.seek.value = time;
        }

        elements.currentTime.textContent = formatTime(time);
    }

    function showPage(pageIndex) {
        shownPageIndex = pageIndex;
        const page = pages[pageIndex];

        elements.pageText.replaceChildren(...page.words.flatMap((text, index) => {
            const word = document.createElement('span');
            word.className = 'word cursor-pointer';
            word.dataset.word = index;
            word.textContent = text;

            return index === 0 ? [word] : [document.createTextNode(' '), word];
        }));
        elements.pageText.animate([{ opacity: 0, transform: 'translateX(12px)' }, { opacity: 1, transform: 'none' }], { duration: 300, easing: 'ease-out' });

        wordElements = [...elements.pageText.querySelectorAll('[data-word]')];
        elements.pageNumber.textContent = page.number;
        elements.previous.disabled = false;
        elements.next.disabled = pageIndex >= pages.length - 1;

        root.querySelectorAll('[data-page-button]').forEach((button) => {
            const isCurrent = Number(button.dataset.pageButton) === page.number;
            ['border-violet-500', 'bg-violet-600', 'text-white'].forEach((name) => button.classList.toggle(name, isCurrent));
            button.setAttribute('aria-current', isCurrent ? 'page' : 'false');
        });
    }

    function pageIndexAt(time) {
        let index = 0;

        pages.forEach((page, pageIndex) => {
            if (page.offset <= time + 0.001) {
                index = pageIndex;
            }
        });

        return index;
    }

    function currentPageIndex() {
        return pageIndexAt(audio.currentTime);
    }

    function timeIntoPage() {
        return audio.currentTime - pages[currentPageIndex()].offset;
    }

    function seekToPage(pageIndex) {
        const index = Math.min(Math.max(pageIndex, 0), pages.length - 1);
        seekTo(pages[index].offset);
    }

    function seekTo(time, { play = false } = {}) {
        audio.currentTime = Math.min(Math.max(time, 0), config.duration);
        sync(audio.currentTime);

        if (play && audio.paused) {
            audio.play();
        }
    }

    function togglePlay() {
        if (audio.paused) {
            if (audio.ended) {
                audio.currentTime = 0;
            }

            audio.play();
        } else {
            audio.pause();
        }
    }

    function renderPlayButton() {
        elements.play.textContent = audio.paused ? '▶' : '⏸';
        elements.play.setAttribute('aria-label', audio.paused ? 'Play' : 'Pause');
    }
}
