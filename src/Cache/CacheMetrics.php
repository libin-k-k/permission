<?php

namespace Libinkk\Permission\Cache;

class CacheMetrics
{
    protected int $l1Hits = 0;

    protected int $l2Hits = 0;

    protected int $l3Hits = 0;

    protected int $misses = 0;

    protected int $invalidations = 0;

    public function hit(string $layer): void
    {
        match ($layer) {
            'l1' => $this->l1Hits++,
            'l3' => $this->l3Hits++,
            default => $this->l2Hits++,
        };
    }

    public function miss(): void
    {
        $this->misses++;
    }

    public function invalidated(): void
    {
        $this->invalidations++;
    }

    /**
     * @return array{l1_hits: int, l2_hits: int, l3_hits: int, misses: int, invalidations: int, hit_rate: float}
     */
    public function snapshot(): array
    {
        $hits = $this->l1Hits + $this->l2Hits + $this->l3Hits;
        $total = $hits + $this->misses;

        return [
            'l1_hits' => $this->l1Hits,
            'l2_hits' => $this->l2Hits,
            'l3_hits' => $this->l3Hits,
            'misses' => $this->misses,
            'invalidations' => $this->invalidations,
            'hit_rate' => $total === 0 ? 0.0 : round($hits / $total, 4),
        ];
    }

    public function flush(): void
    {
        $this->l1Hits = 0;
        $this->l2Hits = 0;
        $this->l3Hits = 0;
        $this->misses = 0;
        $this->invalidations = 0;
    }
}
