<?php

namespace App\Console\Commands;

use App\Models\PatientRagDocument;
use App\Models\User;
use App\Services\PatientRagWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PatientRagManage extends Command
{
    protected $signature = 'patientai:rag {action : import-guide|import-json|submit|review|reject|approve|index|retire} {--actor= : Identifiant du professionnel humain opérateur} {--document= : Identifiant du document pour une transition} {--file= : Fichier JSON local pour import-json} {--attest-human-review : Attestation explicite de lecture humaine pour review} {--attest-approval : Approbation explicite pour approve}';

    protected $description = 'Pipeline documentaire local PatientAI, revue et approbation explicites sans réseau';

    public function handle(PatientRagWorkflow $workflow): int
    {
        if (! ctype_digit((string) $this->option('actor'))) {
            $this->error('Opérateur professionnel actif obligatoire.');

            return self::FAILURE;
        }
        $actor = User::find((int) $this->option('actor'));
        if (! $actor || ! $actor->active || ! $actor->canPublish()) {
            $this->error('Opérateur professionnel actif obligatoire.');

            return self::FAILURE;
        }
        $previous = Auth::user();
        Auth::setUser($actor);
        try {
            if ($this->argument('action') === 'import-guide') {
                $documents = $workflow->importGuide();
                $this->info(count($documents).' brouillons importés ; revue humaine et approbation restent obligatoires.');
            } elseif ($this->argument('action') === 'import-json') {
                $path = (string) $this->option('file');
                if (str_contains($path, '://') || ! is_file($path) || filesize($path) > 40000) {
                    $this->error('Document local invalide ou trop volumineux.');

                    return self::FAILURE;
                }
                $raw = file_get_contents($path, false, null, 0, 40001);
                if ($raw === false || strlen($raw) > 40000) {
                    $this->error('Document local invalide ou trop volumineux.');

                    return self::FAILURE;
                }
                $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                $workflow->draft(is_array($data) ? $data : []);
                $this->info('Brouillon importé, non approuvé et non indexé.');
            } else {
                if (($this->argument('action') === 'review' && ! $this->option('attest-human-review')) || ($this->argument('action') === 'approve' && ! $this->option('attest-approval'))) {
                    $this->error('Attestation humaine explicite obligatoire.');

                    return self::FAILURE;
                }
                if (! ctype_digit((string) $this->option('document'))) {
                    $this->error('Document obligatoire.');

                    return self::FAILURE;
                }
                $document = PatientRagDocument::where('tenant_id', $actor->tenant_id)->findOrFail((int) $this->option('document'));
                $workflow->transition($document, $this->argument('action'));
                $this->info('Transition documentaire effectuée.');
            }
        } catch (Throwable) {
            $this->error('Transition documentaire indisponible ou non autorisée.');

            return self::FAILURE;
        } finally {
            if ($previous !== null) {
                Auth::setUser($previous);
            } else {
                Auth::forgetUser();
            }
        }

        return self::SUCCESS;
    }
}
