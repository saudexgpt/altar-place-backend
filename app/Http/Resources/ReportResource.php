<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reportable_type' => class_basename($this->reportable_type),
            'reportable_id' => $this->reportable_id,
            'reportable_summary' => $this->reportableSummary(),
            'reason' => $this->reason,
            'details' => $this->details,
            'status' => $this->status,
            'reporter' => $this->when($this->relationLoaded('reporter') && $this->reporter, fn () => [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'resolver' => $this->when($this->relationLoaded('resolver') && $this->resolver, fn () => [
                'id' => $this->resolver->id,
                'name' => $this->resolver->name,
            ]),
            'resolution_note' => $this->resolution_note,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function reportableSummary(): ?string
    {
        $reportable = $this->whenLoaded('reportable');

        if (! $reportable || ! $this->reportable) {
            return null;
        }

        return match ($this->reportable_type) {
            \App\Models\Track::class => $this->reportable->title,
            \App\Models\Comment::class => str($this->reportable->body)->limit(80)->toString(),
            default => null,
        };
    }
}
