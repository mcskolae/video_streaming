<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use HomeFlix\Media\Application\Exception\TranscodingFailed;
use HomeFlix\Media\Application\Port\MediaProbe;
use HomeFlix\Media\Application\Transcoding\ProbeResult;

final class FakeMediaProbe implements MediaProbe
{
    public ?TranscodingFailed $failure = null;

    public int $calls = 0;

    public function __construct(public ProbeResult $result = new ProbeResult(600, 1920, 1080, true)) {}

    public function probe(string $absolutePath): ProbeResult
    {
        $this->calls++;

        return $this->failure === null ? $this->result : throw $this->failure;
    }
}
