<?php

namespace App\Models\Concerns;

trait PausesPublishing
{
    public function canPause(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    public function canResume(): bool
    {
        return $this->status === 'paused';
    }
}
