<?php

namespace Tests\Unit;

use App\Services\Transcription\TranscriptAligner;
use PHPUnit\Framework\TestCase;

class TranscriptAlignerTest extends TestCase
{
    public function test_words_take_the_timing_of_the_matching_spoken_word_ignoring_case_and_punctuation(): void
    {
        $timings = (new TranscriptAligner)->align(['"Hello,', 'little', 'owl!"'], [
            ['word' => 'hello', 'start' => 0.5, 'end' => 0.9],
            ['word' => 'Little', 'start' => 1.0, 'end' => 1.3],
            ['word' => 'owl.', 'start' => 1.4, 'end' => 1.8],
        ], 2.0);

        $this->assertSame([[0.5, 0.9], [1.0, 1.3], [1.4, 1.8]], $timings);
    }

    public function test_skipped_words_are_spread_between_their_neighbours(): void
    {
        $timings = (new TranscriptAligner)->align(['the', 'big', 'red', 'kite'], [
            ['word' => 'the', 'start' => 0.0, 'end' => 0.2],
            ['word' => 'kite', 'start' => 1.0, 'end' => 1.4],
        ], 2.0);

        $this->assertSame([[0.0, 0.2], [0.2, 0.6], [0.6, 1.0], [1.0, 1.4]], $timings);
    }

    public function test_repeated_and_extra_spoken_words_are_ignored(): void
    {
        $timings = (new TranscriptAligner)->align(['up', 'went', 'pip'], [
            ['word' => 'um', 'start' => 0.0, 'end' => 0.3],
            ['word' => 'up', 'start' => 0.5, 'end' => 0.7],
            ['word' => 'went', 'start' => 0.8, 'end' => 1.0],
            ['word' => 'went', 'start' => 1.2, 'end' => 1.4],
            ['word' => 'pip', 'start' => 1.6, 'end' => 1.9],
        ], 2.0);

        $this->assertSame([0.5, 0.7], $timings[0]);
        $this->assertSame([1.6, 1.9], $timings[2]);
        $this->assertGreaterThanOrEqual(0.7, $timings[1][0]);
    }

    public function test_misheard_words_still_take_the_timing_of_the_spoken_word(): void
    {
        $timings = (new TranscriptAligner)->align(['Pip', 'was', 'small'], [
            ['word' => 'Hip', 'start' => 0.2, 'end' => 0.5],
            ['word' => 'was', 'start' => 0.5, 'end' => 0.8],
            ['word' => 'small', 'start' => 0.8, 'end' => 1.2],
        ], 1.5);

        $this->assertSame([[0.2, 0.5], [0.5, 0.8], [0.8, 1.2]], $timings);
    }

    public function test_without_a_transcript_words_are_spread_across_the_whole_recording(): void
    {
        $timings = (new TranscriptAligner)->align(['one', 'two'], [], 3.0);

        $this->assertSame([[0.0, 1.5], [1.5, 3.0]], $timings);
    }
}
