<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdvertiserApplyRequest;
use App\Http\Requests\Api\StoreAdvertisementRequest;
use App\Http\Requests\Api\UpdateAdvertisementRequest;
use App\Http\Resources\AdvertisementResource;
use App\Http\Resources\AdvertiserResource;
use App\Http\Resources\UserResource;
use App\Models\Advertiser;
use App\Models\Advertisement;
use App\Models\Artist;
use App\Models\Playlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class AdvertiserController extends Controller
{
    public function apply(AdvertiserApplyRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->advertiser) {
            return response()->json(['message' => 'You already have an advertiser profile.'], 422);
        }

        $advertiser = Advertiser::create([
            'user_id' => $user->id,
            'company_name' => $request->string('company_name'),
            'website' => $request->input('website'),
            'is_verified' => false,
        ]);

        $user->assignRole('advertiser');

        return response()->json([
            'advertiser' => new AdvertiserResource($advertiser),
            'user' => new UserResource($user->fresh()),
        ], 201);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $advertiser = $request->user()->advertiser()->firstOrFail();
        $campaigns = $advertiser->advertisements();

        return response()->json([
            'advertiser' => new AdvertiserResource($advertiser),
            'total_campaigns' => $campaigns->count(),
            'active_campaigns' => (clone $campaigns)->where('status', 'active')->count(),
            'total_impressions' => (int) (clone $campaigns)->sum('impressions_count'),
            'total_clicks' => (int) (clone $campaigns)->sum('clicks_count'),
        ]);
    }

    public function campaigns(Request $request): AnonymousResourceCollection
    {
        $advertiser = $request->user()->advertiser()->firstOrFail();

        return AdvertisementResource::collection($advertiser->advertisements()->latest()->get());
    }

    public function storeCampaign(StoreAdvertisementRequest $request): JsonResponse
    {
        $advertiser = $request->user()->advertiser()->firstOrFail();

        $advertisement = $advertiser->advertisements()->create([
            ...$request->safe()->except(['image', 'audio', 'sponsorable_type']),
            'sponsorable_type' => $this->resolveSponsorableType($request->input('sponsorable_type')),
            'image_url' => $this->storeCreativeFile($request, 'image'),
            'audio_url' => $this->storeCreativeFile($request, 'audio'),
            'status' => 'draft',
        ]);

        return response()->json(['advertisement' => new AdvertisementResource($advertisement)], 201);
    }

    public function updateCampaign(UpdateAdvertisementRequest $request, Advertisement $advertisement): JsonResponse
    {
        abort_unless($advertisement->advertiser_id === $request->user()->advertiser?->id, 403);

        $data = $request->safe()->except(['image', 'audio']);

        if ($imageUrl = $this->storeCreativeFile($request, 'image')) {
            $data['image_url'] = $imageUrl;
        }

        if ($audioUrl = $this->storeCreativeFile($request, 'audio')) {
            $data['audio_url'] = $audioUrl;
        }

        $advertisement->update($data);

        return response()->json(['advertisement' => new AdvertisementResource($advertisement->fresh())]);
    }

    public function destroyCampaign(Request $request, Advertisement $advertisement): JsonResponse
    {
        abort_unless($advertisement->advertiser_id === $request->user()->advertiser?->id, 403);

        $advertisement->delete();

        return response()->json(['message' => 'Campaign deleted.']);
    }

    private function resolveSponsorableType(?string $type): ?string
    {
        return match ($type) {
            'playlist' => Playlist::class,
            'artist' => Artist::class,
            default => null,
        };
    }

    private function storeCreativeFile(Request $request, string $field): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        return Storage::disk('public')->url($request->file($field)->store('ad-creatives', 'public'));
    }
}
