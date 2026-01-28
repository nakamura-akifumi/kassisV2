<?php

namespace App\Service;

use App\Entity\Manifestation;
use Symfony\Component\Workflow\Registry;

class ManifestationStatusResolver
{
    public function __construct(private Registry $workflowRegistry)
    {
    }

    /**
     * @return string[]
     */
    public function getPlaces(): array
    {
        $workflow = $this->workflowRegistry->get(new Manifestation(), 'manifestation');
        return $workflow->getDefinition()->getPlaces();
    }

    public function normalize(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $trimmed = trim($status);
        if ($trimmed === '') {
            return null;
        }

        $places = $this->getPlaces();
        $map = [];
        foreach ($places as $place) {
            $map[$this->normalizeKey($place)] = $place;
        }

        if (isset($map['available'])) {
            $map['active'] = $map['available'];
        }

        return $map[$this->normalizeKey($trimmed)] ?? null;
    }

    public function assertValid(string $status): string
    {
        $normalized = $this->normalize($status);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Invalid status1 value.');
        }

        return $normalized;
    }

    private function normalizeKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['_', '-'], ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return $normalized;
    }
}
