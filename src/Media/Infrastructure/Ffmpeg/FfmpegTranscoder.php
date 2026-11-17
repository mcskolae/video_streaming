<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Ffmpeg;

use HomeFlix\Media\Application\Exception\TranscodingFailed;
use HomeFlix\Media\Application\Port\VideoTranscoder;
use HomeFlix\Media\Application\Transcoding\RenditionPlan;
use HomeFlix\Media\Application\Transcoding\TranscodedRendition;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Convertit une vidéo en HLS avec `ffmpeg` : H.264 (preset veryfast, CRF 23) et AAC, segments de 6 secondes,
 * toutes les qualités en une seule passe de décodage, et un `master.m3u8`.
 *
 * Le serveur n'a pas d'encodage matériel : le processus tourne avec une priorité basse (`nice`) pour ne pas
 * affamer le site.
 */
final readonly class FfmpegTranscoder implements VideoTranscoder
{
    public function __construct(private string $ffmpeg = 'ffmpeg', private int $timeoutSeconds = 14400) {}

    public function transcode(string $sourceAbsolutePath, string $outputDirectory, array $plans, bool $hasAudio): array
    {
        foreach ($plans as $plan) {
            if (! is_dir($outputDirectory.'/'.$plan->label) && ! mkdir($outputDirectory.'/'.$plan->label, 0755, true) && ! is_dir($outputDirectory.'/'.$plan->label)) {
                throw TranscodingFailed::ffmpeg('impossible de créer le dossier d\'une qualité');
            }
        }

        $process = new Process($this->command($sourceAbsolutePath, $outputDirectory, $plans, $hasAudio));
        $process->setTimeout($this->timeoutSeconds);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw TranscodingFailed::ffmpeg('délai maximal dépassé');
        }

        if (! $process->isSuccessful()) {
            throw TranscodingFailed::ffmpeg($this->tail($process->getErrorOutput()));
        }

        $produced = [];

        foreach ($plans as $plan) {
            $playlist = $plan->label.'/index.m3u8';

            if (! is_file($outputDirectory.'/'.$playlist)) {
                throw TranscodingFailed::noOutput();
            }

            $produced[] = new TranscodedRendition($plan->label, $plan->width, $plan->height, $plan->videoBitrateKbps, $playlist);
        }

        if (! is_file($outputDirectory.'/master.m3u8')) {
            throw TranscodingFailed::noOutput();
        }

        return $produced;
    }

    /**
     * @param  list<RenditionPlan>  $plans
     * @return list<string>
     */
    private function command(string $source, string $output, array $plans, bool $hasAudio): array
    {
        $count = count($plans);
        $command = ['nice', '-n', '10', $this->ffmpeg, '-hide_banner', '-nostdin', '-y', '-i', $source];

        if ($count === 1) {
            $command = [...$command, '-map', '0:v:0', '-vf', sprintf('scale=%d:%d', $plans[0]->width, $plans[0]->height)];
        } else {
            $split = '';
            $scales = '';

            foreach ($plans as $i => $plan) {
                $split .= "[s{$i}]";
                $scales .= sprintf(';[s%d]scale=%d:%d[v%d]', $i, $plan->width, $plan->height, $i);
            }

            $command = [...$command, '-filter_complex', "[0:v:0]split={$count}{$split}{$scales}"];

            foreach (array_keys($plans) as $i) {
                $command = [...$command, '-map', "[v{$i}]"];
            }
        }

        foreach ($plans as $i => $plan) {
            $rate = $plan->videoBitrateKbps;
            $command = [
                ...$command,
                "-c:v:{$i}", 'libx264', '-preset', 'veryfast', '-crf', '23',
                "-maxrate:v:{$i}", ($rate * 107 / 100).'k', "-bufsize:v:{$i}", ($rate * 3 / 2).'k',
            ];
        }

        // Image clé à intervalle fixe : les segments de 6 secondes tombent juste, quelle que soit la source.
        $command = [...$command, '-g', '48', '-keyint_min', '48', '-sc_threshold', '0', '-pix_fmt', 'yuv420p'];

        if ($hasAudio) {
            foreach (array_keys($plans) as $i) {
                $command = [...$command, '-map', '0:a:0', "-c:a:{$i}", 'aac', "-b:a:{$i}", '128k', '-ac', '2'];
            }
        }

        $streamMap = implode(' ', array_map(
            fn (RenditionPlan $plan, int $i) => ($hasAudio ? "v:{$i},a:{$i}" : "v:{$i}").",name:{$plan->label}",
            $plans,
            array_keys($plans),
        ));

        return [
            ...$command,
            '-f', 'hls', '-hls_time', '6', '-hls_playlist_type', 'vod', '-hls_flags', 'independent_segments',
            '-hls_segment_type', 'mpegts', '-hls_segment_filename', $output.'/%v/seg_%05d.ts',
            '-master_pl_name', 'master.m3u8', '-var_stream_map', $streamMap,
            $output.'/%v/index.m3u8',
        ];
    }

    /** Les dernières lignes de la sortie d'erreur de ffmpeg, qui disent pourquoi il s'est arrêté. */
    private function tail(string $stderr): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $stderr)), fn (string $line) => $line !== ''));

        return mb_substr(implode(' | ', array_slice($lines, -4)), 0, 600);
    }
}
