<?php

namespace App\Services\Audio;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class AudioProcessor
{
    /**
     * Convert any audio file into 16kHz mono WAV, the format WhisperX works best with.
     */
    public function convertToWav(string $inputPath, string $outputPath): string
    {
        $this->ffmpeg(['-i', $inputPath, '-vn', '-ac', '1', '-ar', '16000', '-c:a', 'pcm_s16le', $outputPath]);

        return $outputPath;
    }

    /**
     * Trim an audio file to the given range (in seconds) and encode it as AAC with short fades.
     *
     * The trim happens inside the filter chain so the fades are timed against the trimmed audio.
     */
    public function trim(string $inputPath, string $outputPath, float $start, float $end): string
    {
        $length = max(0.1, $end - $start);
        $fade = min(0.05, $length / 4);

        $this->ffmpeg([
            '-i', $inputPath,
            '-vn',
            '-af', implode(',', [
                'asetpts=PTS-STARTPTS',
                sprintf('atrim=start=%s:duration=%s', $this->seconds($start), $this->seconds($length)),
                'asetpts=PTS-STARTPTS',
                sprintf('afade=t=in:d=%s', $this->seconds($fade)),
                sprintf('afade=t=out:st=%s:d=%s', $this->seconds($length - $fade), $this->seconds($fade)),
            ]),
            ...$this->aacOutputArguments(),
            $outputPath,
        ]);

        return $outputPath;
    }

    /**
     * Concatenate audio files, in order, into a single AAC file.
     *
     * @param  list<string>  $inputPaths
     */
    public function concatenate(array $inputPaths, string $outputPath): string
    {
        if ($inputPaths === []) {
            throw new RuntimeException('At least one audio file is required to concatenate.');
        }

        $inputs = collect($inputPaths)->flatMap(fn (string $path): array => ['-i', $path])->all();
        $streams = collect($inputPaths)->keys()->map(fn (int $index): string => "[{$index}:a]")->implode('');

        $this->ffmpeg([
            ...$inputs,
            '-filter_complex', $streams.'concat=n='.count($inputPaths).':v=0:a=1[out]',
            '-map', '[out]',
            ...$this->aacOutputArguments(),
            $outputPath,
        ]);

        return $outputPath;
    }

    /**
     * Get the duration of an audio file in seconds.
     */
    public function duration(string $path): float
    {
        $result = Process::timeout(60)->run([
            config('readalong.ffmpeg.ffprobe_binary'),
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $path,
        ]);

        if ($result->failed() || ! is_numeric(trim($result->output()))) {
            throw new RuntimeException('Unable to read the audio duration: '.trim($result->errorOutput()));
        }

        return round((float) trim($result->output()), 3);
    }

    /**
     * Get the FFmpeg arguments used for every encoded output file.
     *
     * @return list<string>
     */
    private function aacOutputArguments(): array
    {
        return ['-ac', '1', '-ar', '44100', '-c:a', 'aac', '-b:a', config('readalong.ffmpeg.audio_bitrate'), '-movflags', '+faststart'];
    }

    /**
     * Run FFmpeg with the given arguments.
     *
     * @param  list<string>  $arguments
     */
    private function ffmpeg(array $arguments): void
    {
        $result = Process::timeout(config('readalong.ffmpeg.timeout'))->run([
            config('readalong.ffmpeg.binary'), '-hide_banner', '-loglevel', 'error', '-y', ...$arguments,
        ]);

        if ($result->failed()) {
            throw new RuntimeException('FFmpeg failed: '.trim($result->errorOutput()));
        }
    }

    /**
     * Format seconds for FFmpeg arguments.
     */
    private function seconds(float $seconds): string
    {
        return number_format(max(0, $seconds), 3, '.', '');
    }
}
