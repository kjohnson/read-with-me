<?php

namespace App\Services\Transcription;

use Codewithkyrian\Whisper\DtwModelPreset;
use Codewithkyrian\Whisper\DtwParameters;
use Codewithkyrian\Whisper\ModelLoader;
use Codewithkyrian\Whisper\TokenData;
use Codewithkyrian\Whisper\WhisperContext;
use Codewithkyrian\Whisper\WhisperContextParameters;
use Codewithkyrian\Whisper\WhisperFullParams;
use Codewithkyrian\Whisper\WhisperState;

use function Codewithkyrian\Whisper\readAudio;

class WhisperCppTranscriber implements Transcriber
{
    /**
     * The longest a single word is assumed to last, in seconds.
     */
    private const float MAX_WORD_DURATION = 1.5;

    /**
     * The shortest the final word is assumed to last, in seconds.
     */
    private const float MIN_FINAL_WORD_DURATION = 0.3;

    /**
     * @param  array{model: string, model_path: string|null, model_directory: string, threads: int, language: string, dtw_offset: float}  $config
     */
    public function __construct(private array $config) {}

    /**
     * Transcribe a 16kHz WAV file with whisper.cpp, timing each word with DTW token timestamps.
     */
    public function transcribe(string $audioPath, ?string $prompt = null): Transcription
    {
        $context = new WhisperContext($this->modelPath(), $this->contextParameters());

        $parameters = WhisperFullParams::default()
            ->withLanguage($this->config['language'])
            ->withNThreads($this->config['threads'])
            ->withTokenTimestamps()
            ->withPrintProgress(false)
            ->withPrintRealtime(false)
            ->withPrintTimestamps(false);

        if (filled($prompt)) {
            $parameters = $parameters->withInitialPrompt($prompt);
        }

        $state = $context->createState();
        $state->full(readAudio($audioPath), $parameters);

        return $this->transcriptionFrom($state, $context->tokenEot());
    }

    /**
     * Get the path to the model file, downloading the model if it isn't cached yet.
     */
    public function modelPath(): string
    {
        return $this->config['model_path'] ?: ModelLoader::loadModel($this->config['model'], $this->config['model_directory']);
    }

    /**
     * Build words from the transcribed tokens. Tokens that start with a space begin a new word,
     * and the rest (word pieces and punctuation) are joined onto the word before them.
     */
    private function transcriptionFrom(WhisperState $state, int $endOfTextToken): Transcription
    {
        $words = [];
        $segmentTexts = [];
        $lastTokenTime = 0.0;

        for ($segment = 0; $segment < $state->nSegments(); $segment++) {
            $segmentText = trim($state->getSegmentText($segment));

            if ($this->isNonSpeechAnnotation($segmentText)) {
                continue;
            }

            $segmentTexts[] = $segmentText;

            for ($token = 0; $token < $state->nTokens($segment); $token++) {
                if ($state->tokenId($segment, $token) >= $endOfTextToken) {
                    continue;
                }

                $text = $state->tokenText($segment, $token);
                $data = $state->tokenData($segment, $token);
                $lastTokenTime = $this->tokenTime($data);

                if ($words === [] || str_starts_with($text, ' ')) {
                    $words[] = ['word' => trim($text), 'start' => $lastTokenTime, 'end' => null];
                } else {
                    $words[array_key_last($words)]['word'] .= $text;
                }
            }
        }

        foreach ($words as $index => $word) {
            $words[$index]['start'] = round($word['start'], 3);
            $words[$index]['end'] = round(isset($words[$index + 1])
                ? min($words[$index + 1]['start'], $word['start'] + self::MAX_WORD_DURATION)
                : max($lastTokenTime, $word['start'] + self::MIN_FINAL_WORD_DURATION), 3);
        }

        $words = array_values(array_filter($words, fn (array $word): bool => $word['word'] !== ''));

        return new Transcription(implode(' ', $segmentTexts), $words);
    }

    /**
     * Get the time (in seconds) a token starts being spoken.
     *
     * DTW timestamps consistently land a little after the token is first heard,
     * so they are shifted earlier by the configured offset.
     */
    private function tokenTime(TokenData $data): float
    {
        if ($data->dtwTimestamp < 0) {
            return $data->startTimestamp / 100;
        }

        return max(0.0, $data->dtwTimestamp / 100 - $this->config['dtw_offset']);
    }

    /**
     * Determine if a segment is an annotation such as "[Silence]" or "(music)" rather than speech.
     */
    private function isNonSpeechAnnotation(string $text): bool
    {
        return $text === '' || preg_match('/^[\[\(*].*[\]\)*]$/u', $text) === 1;
    }

    /**
     * Get the context parameters, enabling DTW token timestamps when the model has a preset for them.
     */
    private function contextParameters(): WhisperContextParameters
    {
        $preset = match ($this->config['model']) {
            'tiny.en' => DtwModelPreset::TinyEn,
            'tiny' => DtwModelPreset::Tiny,
            'base.en' => DtwModelPreset::BaseEn,
            'base' => DtwModelPreset::Base,
            'small.en' => DtwModelPreset::SmallEn,
            'small' => DtwModelPreset::Small,
            'medium.en' => DtwModelPreset::MediumEn,
            'medium' => DtwModelPreset::Medium,
            'large-v1' => DtwModelPreset::LargeV1,
            'large-v2' => DtwModelPreset::LargeV2,
            'large-v3', 'large' => DtwModelPreset::LargeV3,
            default => null,
        };

        $parameters = WhisperContextParameters::default();

        return $preset === null
            ? $parameters
            : $parameters->withDtwParameters((new DtwParameters)->withModelPreset($preset));
    }
}
