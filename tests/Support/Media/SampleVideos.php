<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use Symfony\Component\Process\Process;

/** Fabrique de courtes vidéos de test avec ffmpeg, mises en cache pour toute la série de tests. */
final class SampleVideos
{
    private static ?string $directory = null;

    /** @var array<string, string> */
    private static array $cache = [];

    public static function ffmpegAvailable(): bool
    {
        static $available = null;

        return $available ??= (new Process(['ffmpeg', '-version']))->run() === 0 && (new Process(['ffprobe', '-version']))->run() === 0;
    }

    /** Vidéo de test : mire colorée, avec ou sans bande son. */
    public static function video(string $size = '1920x1080', bool $audio = true, int $seconds = 3): string
    {
        $key = "video-{$size}-".($audio ? 'a' : 'n')."-{$seconds}";

        return self::$cache[$key] ??= self::generate($key.'.mp4', array_filter([
            '-f', 'lavfi', '-i', "testsrc=duration={$seconds}:size={$size}:rate=24",
            $audio ? '-f' : null, $audio ? 'lavfi' : null, $audio ? '-i' : null, $audio ? "sine=frequency=440:duration={$seconds}" : null,
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', $audio ? '-c:a' : null, $audio ? 'aac' : null, $audio ? '-shortest' : null,
        ], fn (?string $argument) => $argument !== null));
    }

    /** Fichier audio seul : valide pour ffmpeg, mais pas une vidéo. */
    public static function audioOnly(): string
    {
        return self::$cache['audio'] ??= self::generate('audio.m4a', ['-f', 'lavfi', '-i', 'sine=frequency=440:duration=2', '-c:a', 'aac']);
    }

    /** Un fichier qui se fait passer pour une vidéo. */
    public static function fake(string $content = ''): string
    {
        $path = self::directory().'/fake-'.bin2hex(random_bytes(4)).'.mp4';
        file_put_contents($path, $content === '' ? random_bytes(4096) : $content);

        return $path;
    }

    /** @param  list<string>  $arguments */
    private static function generate(string $name, array $arguments): string
    {
        $path = self::directory().'/'.$name;
        $process = new Process(['ffmpeg', '-v', 'error', '-y', ...$arguments, $path]);
        $process->setTimeout(120);
        $process->mustRun();

        return $path;
    }

    private static function directory(): string
    {
        if (self::$directory === null) {
            self::$directory = sys_get_temp_dir().'/homeflix-samples-'.bin2hex(random_bytes(4));
            mkdir(self::$directory, 0755, true);
            register_shutdown_function(fn () => (new Process(['rm', '-rf', (string) self::$directory]))->run());
        }

        return self::$directory;
    }
}
