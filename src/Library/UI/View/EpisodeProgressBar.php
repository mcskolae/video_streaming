<?php

declare(strict_types=1);

namespace HomeFlix\Library\UI\View;

use HomeFlix\Library\Application\Query\EpisodeProgress;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** Barre et temps regardé sous un épisode ; rien si l'épisode n'a pas été commencé. */
final class EpisodeProgressBar extends Component
{
    public function __construct(public ?EpisodeProgress $progress = null) {}

    public function shouldRender(): bool
    {
        return $this->progress !== null;
    }

    public function render(): View
    {
        return view('library::episode-progress');
    }

    /** « 12 min 30 s » ou « 45 s ». */
    public static function duration(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return $minutes === 0 ? $rest.' s' : ($rest === 0 ? $minutes.' min' : $minutes.' min '.$rest.' s');
    }
}
