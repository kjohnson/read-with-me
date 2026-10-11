<?php

namespace App\Services\Transcription;

final readonly class Transcription
{
    /**
     * @param  list<array{word: string, start: float|null, end: float|null}>  $words
     */
    public function __construct(
        public string $text,
        public array $words,
    ) {}

    /**
     * Get only the words that WhisperX was able to align to the audio.
     *
     * @return list<array{word: string, start: float, end: float}>
     */
    public function timedWords(): array
    {
        return array_values(array_filter($this->words, fn (array $word): bool => $word['start'] !== null && $word['end'] !== null));
    }

    /**
     * Shift every word timing earlier by the given number of seconds.
     */
    public function shiftedBy(float $seconds): Transcription
    {
        return new Transcription($this->text, array_map(fn (array $word): array => [
            'word' => $word['word'],
            'start' => $word['start'] === null ? null : max(0, round($word['start'] - $seconds, 3)),
            'end' => $word['end'] === null ? null : max(0, round($word['end'] - $seconds, 3)),
        ], $this->words));
    }
}
