<?php

namespace App\Http\Controllers;

use App\Models\AssessmentDefinition;
use App\Services\Access;
use App\Services\Scoring;
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

    public function store(Request $r, Scoring $scoring)
    {
        Access::publisher();
        if ($r->hasFile('questions_file')) {
            $r->validate(['questions_file' => 'file|max:200|mimetypes:application/json,text/plain']);
            $r->merge(['questions' => file_get_contents($r->file('questions_file')->getRealPath())]);
        }
        $d = $r->validate(['name' => 'required|string|max:200', 'kind' => 'required|in:gordon,enneagramme,besoins,personnalise', 'questions' => 'required|json|max:100000', 'previous_id' => 'nullable|integer', 'is_demo' => 'nullable|boolean', 'source_reference' => 'nullable|string|max:2000', 'licensed' => 'nullable|boolean']);
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
        if ($d['kind'] === 'enneagramme' && (count($questions) !== 9 || collect($questions)->contains(fn ($q) => $q['type'] !== 'scale' || $q['min'] !== 0 || $q['max'] !== 100))) {
            throw ValidationException::withMessages(['questions' => 'L’Ennéagramme nécessite neuf échelles de 0 à 100.']);
        }
        DB::transaction(function () use ($d, $questions, $scoring) {
            $previous = isset($d['previous_id']) ? AssessmentDefinition::lockForUpdate()->findOrFail($d['previous_id']) : null;
            $version = $previous ? (AssessmentDefinition::where('family', $previous->family)->max('version') + 1) : 1;
            $model = new AssessmentDefinition(['family' => $previous?->family ?? (string) Str::uuid(), 'name' => $d['name'], 'kind' => $d['kind'], 'version' => $version, 'engine_version' => match ($d['kind']) {
                'gordon' => 'gordon-v1','enneagramme' => 'self-report-v1',default => 'raw-v1'
            }, 'questions' => $questions, 'is_demo' => $d['is_demo'] ?? false, 'source_reference' => $d['source_reference'] ?? null, 'licensed' => $d['licensed'] ?? false]);
            if ($d['kind'] === 'gordon') {
                $scoring->calculate($model, []);
            }
            $model->save();
            Access::audit('questionnaire.version_creee', $model);
        });

        return back()->with('success', 'Version immuable créée. Les passations existantes conservent leur version.');
    }

    public function export(AssessmentDefinition $definition): StreamedResponse
    {
        Access::professional();

        return response()->streamDownload(fn () => print (json_encode($definition->questions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 'questionnaire-'.$definition->id.'-v'.$definition->version.'.json', ['Content-Type' => 'application/json']);
    }
}
