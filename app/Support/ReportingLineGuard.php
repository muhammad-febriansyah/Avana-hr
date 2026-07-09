<?php

namespace App\Support;

use App\Models\Position;

/**
 * Detects circular reporting lines before a position's `reports_to` is saved.
 */
class ReportingLineGuard
{
    /**
     * Would pointing $positionId's reporting line at $reportsToId create a
     * cycle? Walks up the target's chain looking for the position itself.
     * $positionId is null when creating (a new position can't be in a cycle).
     */
    public static function wouldCycle(?int $positionId, ?int $reportsToId): bool
    {
        if ($reportsToId === null) {
            return false;
        }

        if ($positionId !== null && $reportsToId === $positionId) {
            return true;
        }

        $current = $reportsToId;

        /** @var list<int> $visited */
        $visited = [];

        while ($current !== null) {
            if ($current === $positionId) {
                return true;
            }

            if (in_array($current, $visited, true)) {
                break; // pre-existing cycle in the data — stop walking
            }

            $visited[] = $current;

            $next = Position::whereKey($current)->value('reports_to_position_id');
            $current = $next === null ? null : (int) $next;
        }

        return false;
    }
}
