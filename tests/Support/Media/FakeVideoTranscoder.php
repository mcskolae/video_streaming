<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use Closure;
use HomeFlix\Media\Application\Exception\TranscodingFailed;
use HomeFlix\Media\Application\Port\VideoTranscoder;
use HomeFlix\Media\Application\Transcoding\RenditionPlan;
use HomeFlix\Media\Application\Transcoding\TranscodedRendition;

final class FakeVideoTranscoder implements VideoTranscoder
{
    public ?TranscodingFailed $failure = null;

    /** @var list<RenditionPlan>|null */
    public ?array $receivedPlans = null;

    public ?bool $receivedHasAudio = null;

    public int $calls = 0;

    /** @var Closure(): void|null appelé pendant la conversion, pour observer l'état à ce moment */
    public ?Closure $during = null;

    /** @var list<string>|null libellés à renvoyer à la place de ceux du plan (pour simuler une sortie invalide) */
    public ?array $forcedLabels = null;

    public function transcode(string $sourceAbsolutePath, string $outputDirectory, array $plans, bool $hasAudio): array
    {
        $this->calls++;
        $this->receivedPlans = $plans;
        $this->receivedHasAudio = $hasAudio;

        if ($this->during !== null) {
            ($this->during)();
        }

        if ($this->failure !== null) {
            throw $this->failure;
        }

        $result = [];

        foreach ($plans as $i => $plan) {
            $label = $this->forcedLabels[$i] ?? $plan->label;
            $result[] = new TranscodedRendition($label, $plan->width, $plan->height, $plan->videoBitrateKbps, $label.'/index.m3u8');
        }

        return $result;
    }
}
