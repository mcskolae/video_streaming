<?php

declare(strict_types=1);

namespace HomeFlix\Library\UI\View;

use HomeFlix\Library\Application\Query\ContinueWatchingItem;
use HomeFlix\Library\Application\Query\GetTitleResumeHandler;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/** « Reprendre où vous en êtes » sur la page d'un titre : rien pour un visiteur, ni pour un membre qui n'a rien à reprendre. */
final class ResumeHere extends Component
{
    public ?ContinueWatchingItem $item = null;

    public function __construct(GetTitleResumeHandler $handler, string $slug)
    {
        $member = Auth::id();

        if ($member !== null) {
            $this->item = $handler->handle((string) $member, $slug);
        }
    }

    public function shouldRender(): bool
    {
        return $this->item !== null;
    }

    public function render(): View
    {
        return view('library::resume-here');
    }
}
