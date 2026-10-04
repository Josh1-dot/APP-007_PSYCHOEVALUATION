<?php

namespace App\Http\Controllers;

use App\Models\AssessmentDefinition;
use App\Models\Tenant;
use App\Services\Access;
use App\Services\EnneagramScoring;
use App\Services\Scoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DefinitionController extends Controller
{
    public function index()
    {
        Access::professional();

        return view('definitions.index', ['definitions' => AssessmentDefinition::latest()->get()]);
    }

    public function store(Request $r, Scoring $scoring, EnneagramScoring $enneagram)
    {
        Access::publisher();
        if ($r->hasFile('questions_file')) {
            $r->validate(['questions_file' => 'file|max:200|mimetypes:application/json,text/plain']);
            $fileContents = file_get_contents($r->file('questions_file')->getRealPath());
            $snapshot = json_decode($fileContents, true);
            if ($r->input('kind') === 'enneagramme' && is_array($snapshot) && ($snapshot['engine_version'] ?? null) === EnneagramScoring::ENGINE_VERSION) {
                if (($snapshot['is_demo'] ?? false) && ! $r->boolean('is_demo')) {
                    throw ValidationException::withMessages(['is_demo' => 'Un export DEMO doit rester DEMO.']);
                }
                $r->merge(['source_reference' => $r->input('source_reference') ?: ($snapshot['source_reference'] ?? null), 'questions' => json_encode($snapshot['questions'] ?? null), 'form_key' => $snapshot['form_key'] ?? null, 'scoring_rules' => json_encode($snapshot['scoring_rules'] ?? null)]);
            } else {
                $r->merge(['questions' => $fileContents]);
            }
        }
        $d = $r->validate(['name' => 'required|string|max:200', 'kind' => 'required|in:gordon,enneagramme,besoins,personnalise', 'questions' => 'required|json|max:100000', 'previous_id' => 'nullable|integer', 'creation_mode' => 'nullable|in:questionnaire,version,form', 'is_demo' => 'nullable|boolean', 'source_reference' => 'nullable|string|max:2000', 'licensed' => 'nullable|boolean', 'form_key' => 'nullable|string|max:40', 'scoring_rules' => 'nullable|json|max:100000']);
        if ($d['kind'] !== 'personnalise' && ! $r->boolean('is_demo') && (! $r->boolean('licensed') || ! $r->filled('source_reference'))) {
            throw ValidationException::withMessages(['source_reference' => 'Indiquez la source et confirmez votre autorisation d’utilisation, ou marquez cette version comme démonstration.']);
        }
        $questions = json_decode($d['questions'], true);
        if (! is_array($questions) || ! array_is_list($questions) || ! $questions || count($questions) > 250) {
            throw ValidationException::withMessages(['questions' => 'Fournissez une liste de 1 à 250 questions.']);
        }
        Validator::make(['questions' => $questions], ['questions.*.id' => 'required|string|regex:/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/|distinct', 'questions.*.label' => 'required|string|max:1000', 'questions.*.type' => 'required|in:boolean,scale,choice,text', 'questions.*.required' => 'sometimes|boolean', 'questions.*.options' => 'sometimes|array|min:2|max:30', 'questions.*.options.*' => 'required|string|max:300', 'questions.*.dimension' => 'sometimes|in:A,B,C,D', 'questions.*.min' => 'sometimes|integer|min:0|max:100', 'questions.*.max' => 'sometimes|integer|min:1|max:100'])->validate();
        foreach ($questions as $q) {
            if ($q['type'] === 'choice' && count($q['options'] ?? []) < 2) {
                throw ValidationException::withMessages(['questions' => 'Chaque choix multiple nécessite au moins deux options.']);
            }if ($q['type'] === 'scale' && (! isset($q['min'],$q['max']) || $q['min'] >= $q['max'])) {
                throw ValidationException::withMessages(['questions' => 'Chaque échelle nécessite un minimum inférieur au maximum.']);
            }
        }
        $creationMode = $d['creation_mode'] ?? (isset($d['previous_id']) ? 'version' : 'questionnaire');
        if (($creationMode === 'questionnaire') !== ! isset($d['previous_id'])) {
            throw ValidationException::withMessages(['previous_id' => 'Choisissez une définition de référence pour une nouvelle forme ou version uniquement.']);
        }
        $previousDefinition = isset($d['previous_id']) ? AssessmentDefinition::findOrFail($d['previous_id']) : null;
        if ($creationMode === 'form' && ($d['kind'] !== 'enneagramme' || $previousDefinition?->engine_version !== EnneagramScoring::ENGINE_VERSION)) {
            throw ValidationException::withMessages(['previous_id' => 'Une nouvelle forme exige une famille Ennéagramme pondérée.']);
        }
        if ($previousDefinition && $previousDefinition->kind !== $d['kind']) {
            throw ValidationException::withMessages(['previous_id' => 'Une nouvelle version doit conserver la famille de questionnaire.']);
        }
        $weightedEnneagram = $d['kind'] === 'enneagramme' && ($r->filled('form_key') || $r->filled('scoring_rules') || $previousDefinition?->engine_version === EnneagramScoring::ENGINE_VERSION);

        if (
            $weightedEnneagram
            && $creationMode === 'version'
            && $previousDefinition
            && $previousDefinition->engine_version === EnneagramScoring::ENGINE_VERSION
            && strtoupper((string) $previousDefinition->form_key) !== strtoupper((string) ($d['form_key'] ?? ''))
        ) {
            throw ValidationException::withMessages([
                'form_key' => 'Une nouvelle version doit conserver la même form_key.',
            ]);
        }

        if ($weightedEnneagram && $previousDefinition?->is_demo && ! $r->boolean('is_demo')) {
            throw ValidationException::withMessages(['is_demo' => 'Une nouvelle version de contenu DEMO doit rester DEMO. Une source autorisée nécessite une famille distincte.']);
        }

        $scoringRules = $weightedEnneagram && isset($d['scoring_rules']) ? json_decode($d['scoring_rules'], true) : null;
        if ($weightedEnneagram && (! $r->filled('form_key') || ! is_array($scoringRules))) {
            throw ValidationException::withMessages(['scoring_rules' => 'Une forme versionnée exige une form_key et des règles JSON de scoring.']);
        }
        if ($d['kind'] === 'enneagramme' && ! $weightedEnneagram && (count($questions) !== 9 || collect($questions)->contains(fn ($q) => $q['type'] !== 'scale' || ($q['min'] ?? null) !== 0 || ($q['max'] ?? null) !== 100))) {
            throw ValidationException::withMessages(['questions' => 'L’ancien format self-report-v1 exige neuf échelles de 0 à 100.']);
        }
        DB::transaction(function () use ($d, $questions, $scoring, $enneagram, $weightedEnneagram, $scoringRules, $creationMode) {
            Tenant::whereKey(auth()->user()->tenant_id)->lockForUpdate()->firstOrFail();
            $previous = isset($d['previous_id']) ? AssessmentDefinition::lockForUpdate()->findOrFail($d['previous_id']) : null;
            $familyDefinitions = $previous ? AssessmentDefinition::where('family', $previous->family) : null;
            $formKey = $weightedEnneagram ? strtoupper($d['form_key']) : null;
            if ($creationMode === 'form' && (clone $familyDefinitions)->where('form_key', $formKey)->exists()) {
                throw ValidationException::withMessages(['form_key' => 'Cette clé de forme existe déjà dans cette famille.']);
            }
            $version = $creationMode === 'version'
                ? ((clone $familyDefinitions)->when($weightedEnneagram, fn ($query) => $query->where('form_key', $formKey))->max('version') + 1)
                : 1;
            $isDemo = (bool) ($d['is_demo'] ?? false);
            $model = new AssessmentDefinition([
                'family' => $previous?->family ?? (string) Str::uuid(),
                'name' => $d['name'],
                'kind' => $d['kind'],
                'version' => $version,
                'engine_version' => match (true) {
                    $d['kind'] === 'gordon' => 'gordon-v1',
                    $weightedEnneagram => EnneagramScoring::ENGINE_VERSION,
                    $d['kind'] === 'enneagramme' => 'self-report-v1',
                    default => 'raw-v1',
                },
                'questions' => $questions,
                'is_demo' => $isDemo,
                'source_reference' => $d['source_reference'] ?? null,
                'licensed' => $d['licensed'] ?? false,
                'form_key' => $weightedEnneagram ? strtoupper($d['form_key']) : ($d['kind'] === 'enneagramme' ? 'LEGACY' : null),
                'scoring_rules' => $scoringRules,
                'content_status' => $d['kind'] === 'enneagramme' ? ($weightedEnneagram ? ($isDemo ? 'DEMO' : 'DRAFT') : ($isDemo ? 'DEMO' : 'DRAFT')) : null,
                'created_by' => auth()->id(),
            ]);
            if ($d['kind'] === 'gordon') {
                $scoring->calculate($model, []);
            }
            if ($weightedEnneagram) {
                $enneagram->validateDefinition($model);
            }
            $model->save();
            Access::audit($creationMode === 'form' ? 'enneagramme.forme_creee' : 'questionnaire.version_creee', $model);
        });

        return back()->with('success', 'Version immuable créée. Les passations existantes conservent leur version.');
    }

    public function reviewEnneagram(Request $request, AssessmentDefinition $definition, EnneagramScoring $scoring): RedirectResponse
    {
        Access::publisher();
        $request->validate(['reviewed' => 'accepted']);

        DB::transaction(function () use ($definition, $scoring): void {
            $locked = AssessmentDefinition::query()
                ->lockForUpdate()
                ->findOrFail($definition->id);

            abort_unless(
                $locked->kind === 'enneagramme'
                && $locked->engine_version === EnneagramScoring::ENGINE_VERSION,
                404
            );

            abort_if(
                $locked->is_demo || $locked->content_status === 'DEMO',
                422,
                'Une forme DEMO reste une démonstration et ne peut pas entrer dans le workflow d’approbation.'
            );

            abort_unless(
                $locked->content_status === 'DRAFT',
                409,
                'Seule une forme en brouillon peut être revue.'
            );

            $scoring->validateDefinition($locked);

            $locked->forceFill([
                'content_status' => 'REVIEWED',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ])->save();

            Access::audit('enneagramme.forme_revuee', $locked);
        });

        return back()->with(
            'success',
            'Forme Ennéagramme relue. L’approbation reste une étape distincte.'
        );
    }

    public function approveEnneagram(Request $request, AssessmentDefinition $definition, EnneagramScoring $scoring): RedirectResponse
    {
        Access::publisher();
        $request->validate(['approved' => 'accepted']);

        DB::transaction(function () use ($definition, $scoring): void {
            $locked = AssessmentDefinition::query()
                ->lockForUpdate()
                ->findOrFail($definition->id);

            abort_unless(
                $locked->kind === 'enneagramme'
                && $locked->engine_version === EnneagramScoring::ENGINE_VERSION,
                404
            );

            abort_unless(
                $locked->content_status === 'REVIEWED'
                && $locked->reviewed_by
                && $locked->reviewed_at,
                409,
                'Une revue préalable vérifiable est requise.'
            );

            abort_unless(
                ! $locked->is_demo
                && $locked->licensed
                && filled($locked->source_reference),
                422,
                'Une source exacte et une autorisation d’utilisation sont requises ; une forme DEMO ne peut pas être approuvée.'
            );

            $scoring->validateDefinition($locked);

            $locked->forceFill([
                'content_status' => 'APPROVED',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ])->save();

            Access::audit('enneagramme.forme_approuvee', $locked);
        });

        return back()->with(
            'success',
            'Forme approuvée pour assignation. Cette approbation interne n’est pas une validation psychométrique.'
        );
    }

    public function export(AssessmentDefinition $definition): StreamedResponse
    {
        Access::professional();

        $snapshot = $definition->engine_version === EnneagramScoring::ENGINE_VERSION
            ? $definition->only(['name', 'kind', 'version', 'engine_version', 'form_key', 'questions', 'scoring_rules', 'is_demo', 'content_status', 'source_reference', 'licensed'])
            : $definition->questions;

        return response()->streamDownload(fn () => print (json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 'questionnaire-'.$definition->id.'-v'.$definition->version.'.json', ['Content-Type' => 'application/json']);
    }
}
