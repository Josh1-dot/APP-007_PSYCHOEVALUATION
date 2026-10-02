<?php

namespace App\Services;

use App\Models\PatientRagDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PatientRagRetriever
{
    public function __construct(public PatientContextFactory $contexts, public PatientAssessmentTools $assessments, public PatientRagWorkflow $workflow) {}

    /** @return list<string> */
    public function tokens(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY);
        $stop = ['les', 'des', 'une', 'pour', 'dans', 'sur', 'avec', 'mon', 'mes', 'nos', 'vos', 'est', 'sont', 'que', 'qui', 'quoi', 'comment', 'quand', 'tous', 'tout', 'toutes', 'document', 'documents', 'documentation', 'guide', 'chercher', 'rechercher', 'the', 'and', 'for'];

        return array_values(array_unique(array_filter($words, fn (string $word): bool => strlen($word) >= 3 && strlen($word) <= 40 && ! in_array($word, $stop, true))));
    }

    public function retrieve(string $query): PatientRagResult
    {
        abort_unless(config('patientai.enabled'), 404);
        $context = $this->contexts->fromAuthenticatedUser();
        if (mb_strlen($query) > min(512, max(1, (int) config('patientai.rag.max_query_chars')))) {
            return new PatientRagResult;
        }
        $tokens = $this->tokens($query);
        if (count($tokens) < 2 || count($tokens) > 16) {
            return new PatientRagResult;
        }
        $owned = $this->assessments->authorizedQuery($context)->whereColumn('assessment_definition_id', 'patient_rag_documents.assessment_definition_id')->selectRaw('1');
        $documents = PatientRagDocument::where('tenant_id', $context->tenantId)->where('status', 'indexed')
            ->whereNotExists(function (\Illuminate\Database\Query\Builder $q): void {
                $q->selectRaw('1')->from('patient_rag_documents as newer')->whereColumn('newer.tenant_id', 'patient_rag_documents.tenant_id')->whereColumn('newer.document_key', 'patient_rag_documents.document_key')->where('newer.status', 'indexed')->whereColumn('newer.id', '>', 'patient_rag_documents.id');
            })->where('review_status', 'reviewed')->where('approval_status', 'approved')
            ->whereNotNull('reviewed_by')->whereNotNull('reviewed_at')->whereNotNull('approved_by')->whereNotNull('approved_at')
            ->where(function (Builder $q) use ($owned): void {
                $q->where('audience', 'PATIENT_PUBLIC')->orWhere(fn (Builder $q) => $q->where('audience', 'PATIENT_CONTEXTUAL')->whereExists($owned->toBase()));
            })->orderBy('document_key')->orderBy('id')->limit(min(50, max(1, (int) config('patientai.rag.max_candidates'))))->get();
        $candidates = [];
        foreach ($documents as $document) {
            if (! $this->workflow->approved($document) || $document->index_version !== PatientRagWorkflow::INDEX_VERSION || $document->index_checksum !== $this->workflow->checksum($document) || ! $document->chunk_size || $document->chunk_size > 800) {
                continue;
            }
            $chunks = $document->chunks()->where('tenant_id', $context->tenantId)->orderBy('ordinal')->limit(12)->get();
            foreach ($chunks as $chunk) {
                $text = $chunk->content;
                if ($text !== mb_substr($document->content, ($chunk->ordinal - 1) * $document->chunk_size, $document->chunk_size) || hash('sha256', $text) !== $chunk->checksum || $chunk->ordinal < 1) {
                    continue;
                }
                $matches = count(array_intersect($tokens, $this->tokens($text)));
                if ($matches < 2 || $matches / count($tokens) < 0.5) {
                    continue;
                }
                $candidates[] = ['score' => $matches, 'key' => $document->document_key, 'ordinal' => $chunk->ordinal, 'data' => new PatientRagChunkData($text, new PatientRagProvenance($document->document_key, $document->title, $document->version, $document->source_label, $chunk->ordinal))];
            }
        }
        usort($candidates, fn (array $a, array $b): int => ($b['score'] <=> $a['score']) ?: (($a['key'] <=> $b['key']) ?: ($a['ordinal'] <=> $b['ordinal'])));
        $selected = [];
        $keys = [];
        $chars = 0;
        foreach ($candidates as $candidate) {
            $key = $candidate['key'];
            $text = $candidate['data']->text;
            if (! in_array($key, $keys, true) && count($keys) >= min(3, max(1, (int) config('patientai.rag.max_results')))) {
                continue;
            }
            if ($chars + mb_strlen($text) > min(4000, max(0, (int) config('patientai.rag.max_context_chars')))) {
                continue;
            }
            $next = [...$selected, $candidate['data']];
            if (strlen(json_encode(new PatientRagResult($next), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) > min(10000, max(0, (int) config('patientai.rag.max_context_bytes')))) {
                continue;
            }
            $selected = $next;
            $keys[] = $key;
            $keys = array_unique($keys);
            $chars += mb_strlen($text);
            if (count($selected) >= min(5, max(1, (int) config('patientai.rag.max_chunks')))) {
                break;
            }
        }

        return new PatientRagResult($selected);
    }
}
