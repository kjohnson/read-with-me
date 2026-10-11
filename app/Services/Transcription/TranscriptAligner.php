<?php

namespace App\Services\Transcription;

class TranscriptAligner
{
    /**
     * Assign a start and end time (in seconds) to every word of the page text.
     *
     * The transcript rarely matches the text exactly (young readers skip, repeat, and
     * self-correct), so the transcript is aligned to the text using an edit-distance
     * alignment. Words with a matching transcript word take its timing, and any
     * remaining words are spread evenly between their timed neighbours.
     *
     * @param  list<string>  $textWords
     * @param  list<array{word: string, start: float|null, end: float|null}>  $transcriptWords
     * @return list<array{0: float, 1: float}>
     */
    public function align(array $textWords, array $transcriptWords, float $duration): array
    {
        $timings = array_fill(0, count($textWords), null);

        foreach ($this->alignedPairs($textWords, $transcriptWords) as [$textIndex, $transcriptIndex]) {
            $word = $transcriptWords[$transcriptIndex];

            if ($word['start'] !== null && $word['end'] !== null) {
                $timings[$textIndex] = [$word['start'], max($word['start'], $word['end'])];
            }
        }

        return $this->fillGaps($this->enforceOrder($timings), $duration);
    }

    /**
     * Find the (text index, transcript index) pairs of the lowest-cost alignment.
     *
     * @param  list<string>  $textWords
     * @param  list<array{word: string, start: float|null, end: float|null}>  $transcriptWords
     * @return list<array{0: int, 1: int}>
     */
    private function alignedPairs(array $textWords, array $transcriptWords): array
    {
        $text = array_map($this->normalize(...), $textWords);
        $spoken = array_map(fn (array $word): string => $this->normalize($word['word']), $transcriptWords);
        $rows = count($text);
        $columns = count($spoken);

        $cost = [];

        for ($i = 0; $i <= $rows; $i++) {
            $cost[$i][0] = (float) $i;
        }

        for ($j = 0; $j <= $columns; $j++) {
            $cost[0][$j] = (float) $j;
        }

        for ($i = 1; $i <= $rows; $i++) {
            for ($j = 1; $j <= $columns; $j++) {
                $cost[$i][$j] = min(
                    $cost[$i - 1][$j - 1] + $this->substitutionCost($text[$i - 1], $spoken[$j - 1]),
                    $cost[$i - 1][$j] + 1,
                    $cost[$i][$j - 1] + 1,
                );
            }
        }

        $pairs = [];
        $i = $rows;
        $j = $columns;

        while ($i > 0 && $j > 0) {
            $substitution = $this->substitutionCost($text[$i - 1], $spoken[$j - 1]);

            if ($cost[$i][$j] === $cost[$i - 1][$j - 1] + $substitution) {
                if ($text[$i - 1] !== '' && $substitution < 1) {
                    $pairs[] = [$i - 1, $j - 1];
                }

                $i--;
                $j--;
            } elseif ($cost[$i][$j] === $cost[$i - 1][$j] + 1) {
                $i--;
            } else {
                $j--;
            }
        }

        return array_reverse($pairs);
    }

    /**
     * Get the cost of aligning a text word with a spoken word.
     */
    private function substitutionCost(string $textWord, string $spokenWord): float
    {
        if ($textWord === '' || $spokenWord === '') {
            return 1.0;
        }

        if ($textWord === $spokenWord) {
            return 0.0;
        }

        $similarity = 1 - levenshtein($textWord, $spokenWord) / max(strlen($textWord), strlen($spokenWord));

        return $similarity >= 0.6 ? 0.5 : 0.9;
    }

    /**
     * Drop any timings that would move backwards in time.
     *
     * @param  list<array{0: float, 1: float}|null>  $timings
     * @return list<array{0: float, 1: float}|null>
     */
    private function enforceOrder(array $timings): array
    {
        $latestEnd = 0.0;

        foreach ($timings as $index => $timing) {
            if ($timing === null) {
                continue;
            }

            if ($timing[0] < $latestEnd) {
                $timings[$index] = null;

                continue;
            }

            $latestEnd = $timing[1];
        }

        return $timings;
    }

    /**
     * Spread untimed words evenly between the timed words around them.
     *
     * @param  list<array{0: float, 1: float}|null>  $timings
     * @return list<array{0: float, 1: float}>
     */
    private function fillGaps(array $timings, float $duration): array
    {
        $count = count($timings);
        $index = 0;

        while ($index < $count) {
            if ($timings[$index] !== null) {
                $index++;

                continue;
            }

            $gapStart = $index;

            while ($index < $count && $timings[$index] === null) {
                $index++;
            }

            $from = $gapStart > 0 ? $timings[$gapStart - 1][1] : 0.0;
            $to = $index < $count ? $timings[$index][0] : $duration;
            $to = max($from, $to);
            $step = ($to - $from) / ($index - $gapStart);

            for ($gapIndex = $gapStart; $gapIndex < $index; $gapIndex++) {
                $offset = $gapIndex - $gapStart;
                $timings[$gapIndex] = [$from + $step * $offset, $from + $step * ($offset + 1)];
            }
        }

        return array_map(fn (array $timing): array => [
            round(min(max($timing[0], 0), $duration), 3),
            round(min(max($timing[1], 0), $duration), 3),
        ], $timings);
    }

    /**
     * Normalize a word for comparison by lowercasing it and removing punctuation.
     */
    private function normalize(string $word): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($word)) ?? '';
    }
}
