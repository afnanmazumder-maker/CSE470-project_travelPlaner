<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CulturalTipRequest;
use App\Http\Requests\PhraseIndexRequest;
use App\Models\CulturalTip;
use App\Models\Phrase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * R13: phrasebook with pronunciation, saved favorites and cultural tips.
 * Browsing is public; favorites need a login.
 */
class PhraseController extends Controller
{
    public function languages(): JsonResponse
    {
        $languages = Phrase::query()
            ->select('language_code', 'language_name')
            ->selectRaw('count(*) as phrase_count')
            ->groupBy('language_code', 'language_name')
            ->orderBy('language_name')
            ->get();

        return response()->json([
            'languages' => $languages,
            'categories' => Phrase::CATEGORIES,
            'machine_translation' => false,
        ]);
    }

    public function index(PhraseIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $user = $request->user('sanctum');
        $favoriteIds = $user ? $this->favoriteIds($user->id) : [];

        $phrases = Phrase::where('language_code', $filters['language'])
            ->when(! empty($filters['category']), fn ($query) => $query->where('category', $filters['category']))
            ->when(! empty($filters['q']), function ($query) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $query->where(fn ($q) => $q->where('english', 'ilike', $term)->orWhere('romanization', 'ilike', $term));
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Phrase $phrase): array => $phrase->toArray() + ['is_favorite' => in_array($phrase->id, $favoriteIds, true)]);

        return response()->json($phrases->groupBy('category'));
    }

    public function favorites(Request $request): JsonResponse
    {
        $phrases = Phrase::whereIn('id', $this->favoriteIds($request->user()->id))
            ->orderBy('language_name')
            ->orderBy('id')
            ->get()
            ->map(fn (Phrase $phrase): array => $phrase->toArray() + ['is_favorite' => true]);

        return response()->json($phrases);
    }

    public function favorite(Request $request, Phrase $phrase): JsonResponse
    {
        $phrase->favoritedBy()->syncWithoutDetaching([$request->user()->id]);

        return response()->json(['phrase_id' => $phrase->id, 'is_favorite' => true]);
    }

    public function unfavorite(Request $request, Phrase $phrase): JsonResponse
    {
        $phrase->favoritedBy()->detach($request->user()->id);

        return response()->json(['phrase_id' => $phrase->id, 'is_favorite' => false]);
    }

    public function countries(): JsonResponse
    {
        return response()->json(
            CulturalTip::query()->select('country', 'language_code')->distinct()->orderBy('country')->get()
        );
    }

    public function tips(CulturalTipRequest $request): JsonResponse
    {
        $tips = CulturalTip::where('country', $request->validated('country'))->orderBy('id')->get();

        return response()->json([
            'country' => $tips->first()->country,
            'language_code' => $tips->first()->language_code,
            'topics' => collect(CulturalTip::TOPICS)->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'tips' => $tips->where('topic', $key)->values(),
            ])->values(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function favoriteIds(int $userId): array
    {
        return Phrase::whereHas('favoritedBy', fn ($query) => $query->where('users.id', $userId))->pluck('id')->all();
    }
}
