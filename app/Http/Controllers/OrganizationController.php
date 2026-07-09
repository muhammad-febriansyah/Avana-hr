<?php

namespace App\Http\Controllers;

use App\Enums\OrgUnitType;
use App\Models\Grade;
use App\Models\OrgUnit;
use App\Models\Position;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read side of the organisation structure page (units tree, grades, positions,
 * and the read-only org chart). Mutations live in the resource controllers.
 */
class OrganizationController extends Controller
{
    public function index(): Response
    {
        $orgUnits = OrgUnit::query()
            ->withCount('positions')
            ->orderBy('name')
            ->get()
            ->map(fn (OrgUnit $unit): array => [
                'id' => $unit->id,
                'parent_id' => $unit->parent_id,
                'name' => $unit->name,
                'type' => $unit->type->value,
                'type_label' => $unit->type->label(),
                'cost_center' => $unit->cost_center,
                'effective_date' => $unit->effective_date?->toDateString(),
                'positions_count' => $unit->positions_count,
            ]);

        $grades = Grade::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Grade $grade): array => [
                'id' => $grade->id,
                'code' => $grade->code,
                'name' => $grade->name,
                'salary_min' => $grade->salary_min,
                'salary_max' => $grade->salary_max,
                'salary_min_formatted' => Number::currency($grade->salary_min, 'IDR', 'id'),
                'salary_max_formatted' => Number::currency($grade->salary_max, 'IDR', 'id'),
            ]);

        $positions = Position::query()
            ->with(['orgUnit:id,name', 'grade:id,code', 'reportsTo:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Position $position): array => [
                'id' => $position->id,
                'name' => $position->name,
                'org_unit_id' => $position->org_unit_id,
                'org_unit_name' => $position->orgUnit?->name,
                'grade_id' => $position->grade_id,
                'grade_code' => $position->grade?->code,
                'reports_to_position_id' => $position->reports_to_position_id,
                'reports_to_name' => $position->reportsTo?->name,
            ]);

        return Inertia::render('organization/index', [
            'orgUnits' => $orgUnits,
            'grades' => $grades,
            'positions' => $positions,
            'orgUnitTypes' => OrgUnitType::options(),
        ]);
    }
}
