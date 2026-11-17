<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminVisaIndexRequest;
use App\Http\Requests\VisaRequirementRequest;
use App\Models\VisaRequirement;
use App\Services\VisaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * R6: administrators maintain the visa data. Any change marks the row as source = admin,
 * so a later dataset import never overwrites it.
 */
class VisaAdminController extends Controller
{
    public function __construct(private VisaService $visas) {}

    public function index(AdminVisaIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $page = VisaRequirement::with(['passport', 'destination'])
            ->when($filters['passport'] ?? null, fn (Builder $query, string $code) => $query->where('passport_code', $code))
            ->when($filters['destination'] ?? null, fn (Builder $query, string $code) => $query->where('destination_code', $code))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($request->boolean('stale'), fn (Builder $query) => $this->whereStale($query))
            ->orderBy('passport_code')
            ->orderBy('destination_code')
            ->paginate(25)
            ->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map(fn (VisaRequirement $rule): array => $this->row($rule)),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'counts' => [
                'total' => VisaRequirement::count(),
                'official' => VisaRequirement::where('source', 'official')->count(),
                'admin' => VisaRequirement::where('source', 'admin')->count(),
                'stale' => $this->whereStale(VisaRequirement::query())->count(),
            ],
            'requirements' => VisaRequirement::REQUIREMENTS,
        ]);
    }

    public function store(VisaRequirementRequest $request): JsonResponse
    {
        $rule = VisaRequirement::create($this->attributes($request));

        return response()->json($this->row($rule->load(['passport', 'destination'])), 201);
    }

    public function update(VisaRequirementRequest $request, VisaRequirement $visaRequirement): JsonResponse
    {
        $visaRequirement->update($this->attributes($request));

        return response()->json($this->row($visaRequirement->load(['passport', 'destination'])));
    }

    public function destroy(VisaRequirement $visaRequirement): JsonResponse
    {
        $visaRequirement->delete();

        return response()->json(['message' => 'Visa rule deleted']);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(VisaRequirementRequest $request): array
    {
        return [
            ...$request->validated(),
            'source' => 'admin',
            'last_verified_at' => $request->validated('last_verified_at') ?? now()->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(VisaRequirement $rule): array
    {
        return [
            'passport_code' => $rule->passport_code,
            'passport_name' => $rule->passport?->name,
            'destination_code' => $rule->destination_code,
            'destination_name' => $rule->destination?->name,
        ] + $this->visas->format($rule);
    }

    private function whereStale(Builder $query): Builder
    {
        $cutoff = now()->subDays(config('visa.stale_after_days'))->toDateString();

        return $query->where(fn (Builder $q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', $cutoff));
    }
}
