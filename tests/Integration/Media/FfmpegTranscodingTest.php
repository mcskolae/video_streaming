<?php

declare(strict_types=1);

use HomeFlix\Media\Application\Exception\TranscodingFailed;
use HomeFlix\Media\Application\Transcoding\RenditionPlan;
use HomeFlix\Media\Application\Transcoding\RenditionPlanner;
use HomeFlix\Media\Infrastructure\Ffmpeg\FfmpegTranscoder;
use HomeFlix\Media\Infrastructure\Ffmpeg\FfprobeMediaProbe;
use Symfony\Component\Process\Process;
use Tests\Support\Media\SampleVideos;

// Ces tests lancent le vrai ffmpeg sur de courtes vidéos générées. Ils sont ignorés s'il n'est pas installé.
beforeEach(function () {
    if (! SampleVideos::ffmpegAvailable()) {
        $this->markTestSkipped('ffmpeg et ffprobe ne sont pas installés.');
    }

    $this->output = sys_get_temp_dir().'/homeflix-out-'.bin2hex(random_bytes(4));
    mkdir($this->output, 0755, true);
});

afterEach(function () {
    (new Process(['rm', '-rf', $this->output]))->run();
});

/**
 * Cherche le fichier qu'une injection de commande créerait, à tous les endroits où un shell pourrait l'écrire.
 *
 * @return list<string>
 */
function trapFiles(string $marker, string $weirdPath): array
{
    return array_values(array_filter(
        [getcwd().'/'.$marker, dirname($weirdPath).'/'.$marker, sys_get_temp_dir().'/'.$marker, '/'.$marker],
        fn (string $path) => file_exists($path),
    ));
}

/** @return array{height: int, codec: string} */
function describeStream(string $path): array
{
    $process = new Process(['ffprobe', '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=height,codec_name', '-of', 'json', $path]);
    $process->mustRun();
    $stream = json_decode($process->getOutput(), true)['streams'][0];

    return ['height' => (int) $stream['height'], 'codec' => (string) $stream['codec_name']];
}

describe('probe', function () {
    it('reads the size, the duration and the sound of a video', function () {
        $result = (new FfprobeMediaProbe)->probe(SampleVideos::video('1920x1080', audio: true, seconds: 3));

        expect([$result->width, $result->height, $result->durationSeconds, $result->hasAudio])->toBe([1920, 1080, 3, true]);
    });

    it('sees a video without sound', function () {
        expect((new FfprobeMediaProbe)->probe(SampleVideos::video('640x360', audio: false))->hasAudio)->toBeFalse();
    });

    it('refuses what is not a video', function (string $kind) {
        $path = match ($kind) {
            'random bytes' => SampleVideos::fake(),
            'empty file' => SampleVideos::fake(' '),
            'text' => SampleVideos::fake("Ceci n'est pas une vidéo.\n"),
            'audio only' => SampleVideos::audioOnly(),
            'html' => SampleVideos::fake('<html><script>alert(1)</script></html>'),
            default => '/tmp/inexistant-'.bin2hex(random_bytes(4)).'.mp4',
        };

        expect(fn () => (new FfprobeMediaProbe)->probe($path))->toThrow(TranscodingFailed::class);
    })->with(['random bytes', 'empty file', 'text', 'audio only', 'html', 'missing file']);

    it('never runs a file name through a shell', function () {
        $marker = 'PWNED-'.bin2hex(random_bytes(4));
        $weird = dirname(SampleVideos::fake("x\n"))."/a; touch {$marker}; b.mp4";
        copy(SampleVideos::fake("x\n"), $weird);

        expect(fn () => (new FfprobeMediaProbe)->probe($weird))->toThrow(TranscodingFailed::class);
        expect(trapFiles($marker, $weird))->toBe([]);
    });
});

describe('transcoding', function () {
    it('produces a master playlist and one folder per quality, playable by ffprobe', function () {
        $source = SampleVideos::video('1920x1080', audio: true, seconds: 3);
        $plans = RenditionPlanner::plan(1920, 1080);

        $renditions = (new FfmpegTranscoder)->transcode($source, $this->output, $plans, true);

        expect(array_map(fn ($r) => $r->playlist, $renditions))->toBe(['1080p/index.m3u8', '720p/index.m3u8'])
            ->and(is_file($this->output.'/master.m3u8'))->toBeTrue()
            ->and(describeStream($this->output.'/1080p/index.m3u8'))->toBe(['height' => 1080, 'codec' => 'h264'])
            ->and(describeStream($this->output.'/720p/index.m3u8'))->toBe(['height' => 720, 'codec' => 'h264']);

        $master = (string) file_get_contents($this->output.'/master.m3u8');
        expect($master)->toContain('#EXTM3U')
            ->and($master)->toContain('RESOLUTION=1920x1080')
            ->and($master)->toContain('RESOLUTION=1280x720')
            ->and($master)->toContain('1080p/index.m3u8')
            ->and($master)->toContain('720p/index.m3u8')
            ->and($master)->toContain('mp4a.40.2');
    });

    it('cuts the video in segments that the playlist lists', function () {
        $source = SampleVideos::video('1280x720', audio: true, seconds: 14);

        (new FfmpegTranscoder)->transcode($source, $this->output, RenditionPlanner::plan(1280, 720), true);

        $playlist = (string) file_get_contents($this->output.'/720p/index.m3u8');
        $segments = glob($this->output.'/720p/seg_*.ts') ?: [];
        expect($playlist)->toContain('#EXT-X-PLAYLIST-TYPE:VOD')
            ->and($playlist)->toContain('#EXT-X-ENDLIST')
            ->and(count($segments))->toBeGreaterThanOrEqual(2)
            ->and(substr_count($playlist, '.ts'))->toBe(count($segments));

        foreach ($segments as $segment) {
            expect(basename($segment))->toMatch('/^seg_\d{5}\.ts$/');
        }
    });

    it('handles a video without sound', function () {
        $renditions = (new FfmpegTranscoder)->transcode(SampleVideos::video('640x360', audio: false), $this->output, RenditionPlanner::plan(640, 360), false);

        expect($renditions)->toHaveCount(1)
            ->and($renditions[0]->label)->toBe('360p')
            ->and((string) file_get_contents($this->output.'/master.m3u8'))->not->toContain('mp4a');
    });

    it('does not enlarge a small source', function () {
        $renditions = (new FfmpegTranscoder)->transcode(SampleVideos::video('640x360', audio: true), $this->output, RenditionPlanner::plan(640, 360), true);

        expect(array_map(fn ($r) => $r->height, $renditions))->toBe([360])
            ->and(describeStream($this->output.'/360p/index.m3u8')['height'])->toBe(360);
    });

    it('fails with a readable message on a file ffmpeg cannot read', function () {
        $plans = [new RenditionPlan('720p', 1280, 720, 2500)];

        expect(fn () => (new FfmpegTranscoder)->transcode(SampleVideos::fake(), $this->output, $plans, false))
            ->toThrow(TranscodingFailed::class, 'La conversion a échoué');
    });

    it('stops after the time limit', function () {
        $source = SampleVideos::video('1920x1080', audio: true, seconds: 40);

        expect(fn () => (new FfmpegTranscoder(timeoutSeconds: 1))->transcode($source, $this->output, RenditionPlanner::plan(1920, 1080), true))
            ->toThrow(TranscodingFailed::class, 'délai maximal');
    });

    it('does not run a file name through a shell', function () {
        $marker = 'PWNED-'.bin2hex(random_bytes(4));
        $source = SampleVideos::video('640x360', audio: false);
        $weird = dirname($source)."/it's a name; touch {$marker}; \$(touch {$marker}).mp4";
        copy($source, $weird);

        $renditions = (new FfmpegTranscoder)->transcode($weird, $this->output, RenditionPlanner::plan(640, 360), false);

        expect($renditions)->toHaveCount(1)->and(trapFiles($marker, $weird))->toBe([]);
    });

    it('limits the encoder to a low priority so that the site keeps running', function () {
        $reflection = new ReflectionMethod(FfmpegTranscoder::class, 'command');
        $command = $reflection->invoke(new FfmpegTranscoder, '/in.mp4', '/out', RenditionPlanner::plan(1920, 1080), true);

        expect(array_slice($command, 0, 3))->toBe(['nice', '-n', '10'])
            ->and($command)->toContain('libx264', 'veryfast', 'aac', 'master.m3u8', '-hls_time')
            ->and($command[array_search('-hls_time', $command, true) + 1])->toBe('6')
            ->and($command[array_search('-crf', $command, true) + 1])->toBe('23');
    });
});
