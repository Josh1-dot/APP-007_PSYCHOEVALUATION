<?php

namespace App\Services;

use App\Models\AssessmentDefinition;
use App\Models\PatientRagDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PatientRagWorkflow
{
    public const INDEX_VERSION = 'lexical-v0.9';

    private function actor(): User
    {
        abort_unless(auth()->check(), 401);
        $user = User::findOrFail(auth()->id());
        abort_unless($user->active && $user->canPublish() && $user->tenant_id, 403);

        return $user;
    }

    /** @param array<string, mixed> $data */
    public function valid(array $data): bool
    {
        $rules = [
            'document_key' => 'required|string|max:80|regex:/^[a-z0-9][a-z0-9-]*$/D',
            'title' => 'required|string|max:120', 'version' => 'required|string|max:80|regex:/^[a-zA-Z0-9.-]+$/D',
            'source' => 'required|string|max:500', 'source_label' => 'required|string|max:120|regex:/^[\pL\pN \x{2019}\x{0027}.-]+$/uD',
            'audience' => 'required|in:'.implode(',', PatientRagDocument::AUDIENCES),
            'content' => 'required|string|max:'.min(8000, max(1, (int) config('patientai.rag.max_document_chars'))),
            'assessment_definition_id' => 'nullable|integer|min:1',
        ];
        if (Validator::make($data, $rules)->fails()) {
            return false;
        }

        return $data['audience'] !== 'PATIENT_CONTEXTUAL' || isset($data['assessment_definition_id']);
    }

    public function checksum(PatientRagDocument $document): string
    {
        return hash('sha256', json_encode($document->only(['tenant_id', 'document_key', 'title', 'version', 'source', 'source_label', 'audience', 'assessment_definition_id', 'content']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function approved(PatientRagDocument $document): bool
    {
        if (! $this->valid($document->getAttributesForRag()) || $document->review_status !== 'reviewed' || $document->approval_status !== 'approved' || ! $document->reviewed_by || ! $document->reviewed_at || ! $document->approved_by || ! $document->approved_at) {
            return false;
        }
        $hash = $this->checksum($document);

        return $document->review_checksum === $hash && $document->approval_checksum === $hash;
    }

    /** @param array<string, mixed> $data */
    public function draft(array $data): PatientRagDocument
    {
        $actor = $this->actor();
        abort_unless($this->valid($data), 422);
        if (isset($data['assessment_definition_id'])) {
            abort_unless(AssessmentDefinition::where('tenant_id', $actor->tenant_id)->whereKey($data['assessment_definition_id'])->exists(), 422);
        }
        $document = PatientRagDocument::create(['tenant_id' => $actor->tenant_id] + array_intersect_key($data, array_flip(['document_key', 'title', 'version', 'source', 'source_label', 'audience', 'assessment_definition_id', 'content'])));
        Access::audit('patientai.rag_brouillon', $document);

        return $document;
    }

    /** @return list<PatientRagDocument> */
    public function importGuide(): array
    {
        $this->actor();
        $guide = app(PatientGuideRegistry::class)->approved();
        abort_unless($guide !== null, 422);

        return DB::transaction(function () use ($guide): array {
            $documents = [];
            foreach (PatientGuideRegistry::TOPICS as $topic) {
                $documents[] = $this->draft(['document_key' => 'guide-'.$topic, 'title' => 'Guide patient - '.$topic, 'version' => $guide['version'], 'source' => $guide['source'], 'source_label' => 'Guide patient APP-007', 'audience' => 'PATIENT_PUBLIC', 'content' => $guide['topics'][$topic]['text']]);
            }

            return $documents;
        });
    }

    public function transition(PatientRagDocument $document, string $action): void
    {
        $actor = $this->actor();
        DB::transaction(function () use ($actor, $document, $action): void {
            if ($action === 'index') {
                Tenant::whereKey($actor->tenant_id)->lockForUpdate()->firstOrFail(['id']);
            }
            $document = PatientRagDocument::where('tenant_id', $actor->tenant_id)->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $hash = $this->checksum($document);
            switch ($action) {
                case 'submit':
                    abort_unless($document->status === 'draft' && $this->valid($document->getAttributesForRag()), 409);
                    $document->update(['status' => 'review']);
                    break;
                case 'review':
                case 'reject':
                    abort_unless($document->status === 'review' && $document->review_status === 'pending', 409);
                    $document->update(['status' => $action === 'reject' ? 'rejected' : 'review', 'review_status' => $action === 'reject' ? 'rejected' : 'reviewed', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_checksum' => $hash]);
                    break;
                case 'approve':
                    abort_unless($document->status === 'review' && $document->review_status === 'reviewed' && $document->review_checksum === $hash && $this->valid($document->getAttributesForRag()), 409);
                    $document->update(['status' => 'approved', 'approval_status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'approval_checksum' => $hash]);
                    break;
                case 'index':
                    abort_unless($document->status === 'approved' && $this->approved($document), 409);
                    $size = min(800, max(1, (int) config('patientai.rag.chunk_chars')));
                    $count = (int) ceil(mb_strlen($document->content) / $size);
                    abort_if($count > min(12, max(1, (int) config('patientai.rag.max_document_chunks'))), 422);
                    $document->chunks()->delete();
                    for ($i = 0; $i < $count; $i++) {
                        $text = mb_substr($document->content, $i * $size, $size);
                        $document->chunks()->create(['tenant_id' => $actor->tenant_id, 'ordinal' => $i + 1, 'content' => $text, 'checksum' => hash('sha256', $text)]);
                    }
                    PatientRagDocument::where('tenant_id', $actor->tenant_id)->where('document_key', $document->document_key)->where('status', 'indexed')->whereKeyNot($document->id)->update(['status' => 'retired']);
                    $document->update(['status' => 'indexed', 'index_version' => self::INDEX_VERSION, 'index_checksum' => $hash, 'chunk_size' => $size]);
                    break;
                case 'retire':
                    abort_unless(in_array($document->status, ['approved', 'indexed'], true), 409);
                    $document->update(['status' => 'retired']);
                    break;
                default:
                    abort(422);
            }
            Access::audit('patientai.rag_'.$action, $document);
        });
    }
}
