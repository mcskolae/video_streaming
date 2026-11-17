<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Ffmpeg;

use HomeFlix\Media\Application\Exception\TranscodingFailed;
use HomeFlix\Media\Application\Port\MediaProbe;
use HomeFlix\Media\Application\Transcoding\ProbeResult;
use HomeFlix\Media\Domain\Model\Duration;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/** Analyse un fichier avec `ffprobe`. */
final readonly class FfprobeMediaProbe implements MediaProbe
{
    public function __construct(private string $ffprobe = 'ffprobe') {}

    public function probe(string $absolutePath): ProbeResult
    {
        // Le chemin est passé en argument, jamais interprété par un shell.
        $process = new Process([$this->ffprobe, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $absolutePath]);
        $process->setTimeout(60);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw TranscodingFailed::unreadable('l\'analyse a pris trop de temps');
        }

        if (! $process->isSuccessful()) {
            throw TranscodingFailed::unreadable($this->lastLine($process->getErrorOutput()));
        }

        $data = json_decode($process->getOutput(), true);

        if (! is_array($data)) {
            throw TranscodingFailed::notAVideo();
        }

        $video = null;
        $hasAudio = false;

        foreach (is_array($data['streams'] ?? null) ? $data['streams'] : [] as $stream) {
            if (! is_array($stream)) {
                continue;
            }

            // Une image de couverture intégrée n'est pas une vidéo.
            $isCover = (int) ($stream['disposition']['attached_pic'] ?? 0) === 1;

            if (($stream['codec_type'] ?? null) === 'video' && ! $isCover && $video === null) {
                $video = $stream;
            }

            if (($stream['codec_type'] ?? null) === 'audio') {
                $hasAudio = true;
            }
        }

        $width = is_array($video) ? (int) ($video['width'] ?? 0) : 0;
        $height = is_array($video) ? (int) ($video['height'] ?? 0) : 0;
        $format = is_array($data['format'] ?? null) ? $data['format'] : [];
        $duration = (int) ceil((float) ($format['duration'] ?? 0));

        if ($video === null || $width < 2 || $height < 2 || $duration < 1 || $duration > Duration::MAX_SECONDS) {
            throw TranscodingFailed::notAVideo();
        }

        return new ProbeResult($duration, $width, $height, $hasAudio);
    }

    private function lastLine(string $output): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output)), fn (string $line) => $line !== ''));

        return mb_substr($lines === [] ? 'erreur inconnue' : (string) end($lines), 0, 300);
    }
}
