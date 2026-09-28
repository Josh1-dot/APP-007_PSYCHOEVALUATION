<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Client;
use App\Models\Comparison;
use App\Models\Document;
use App\Models\Letter;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkspaceDocument;
use App\Services\Access;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModuleController extends Controller
{
    public function calendar()
    {
        abort_if(auth()->user()->role === 'entreprise', 403);
        $q = Appointment::with('client');
        if (! auth()->user()->isProfessional()) {
            $q->where('client_id', auth()->user()->client?->id ?? 0);
        }

        return view('modules.calendar', ['appointments' => $q->orderBy('starts_at')->paginate(30), 'clients' => auth()->user()->isProfessional() ? Client::all() : collect()]);
    }

    public function appointment(Request $r)
    {
        Access::professional();
        $d = $r->validate(['client_id' => 'required|integer', 'title' => 'required|string|max:200', 'starts_at' => 'required|date', 'duration' => 'required|integer|min:15|max:480', 'location' => 'nullable|string|max:255']);
        Client::findOrFail($d['client_id']);
        $a = Appointment::create($d);
        Access::audit('rendezvous.cree', $a);

        return back()->with('success', 'Rendez-vous ajouté.');
    }

    public function cancel(Appointment $appointment)
    {
        Access::professional();
        $appointment->update(['status' => 'annule']);
        Access::audit('rendezvous.annule', $appointment);

        return back()->with('success', 'Rendez-vous annulé.');
    }

    public function messages()
    {
        $id = auth()->id();
        $messages = Message::where(fn ($q) => $q->where('sender_id', $id)->orWhere('recipient_id', $id))->with('sender', 'recipient')->latest()->paginate(30);
        Message::where('recipient_id', $id)->whereNull('read_at')->update(['read_at' => now()]);
        $recipients = User::where('tenant_id', auth()->user()->tenant_id)->where('active', true)->where('id', '!=', $id);
        if (! auth()->user()->isProfessional()) {
            $recipients->whereIn('role', ['admin', 'psychologue', 'conseiller']);
        }

        return view('modules.messages', ['messages' => $messages, 'recipients' => $recipients->get()]);
    }

    public function send(Request $r)
    {
        $d = $r->validate(['recipient_id' => 'required|integer', 'body' => 'required|string|max:10000']);
        $u = User::where('tenant_id', auth()->user()->tenant_id)->where('active', true)->findOrFail($d['recipient_id']);
        abort_unless(auth()->user()->isProfessional() || $u->isProfessional(), 403);
        $m = Message::create([...$d, 'sender_id' => auth()->id()]);
        Access::audit('message.envoye', $m);

        return back()->with('success', 'Message envoyé.');
    }

    public function documents()
    {
        $u = auth()->user();
        $q = Document::with('client', 'organization');
        if (! $u->isProfessional()) {
            $q->where('shared', true);
            if ($u->role === 'patient') {
                $q->where('client_id', $u->client?->id ?? 0);
            } else {
                $q->whereNull('client_id')->where('organization_id', $u->organization_id ?? 0);
            }
        }

        return view('modules.documents', ['documents' => $q->latest()->paginate(20), 'clients' => $u->isProfessional() ? Client::all() : collect(), 'organizations' => $u->isProfessional() ? Organization::all() : collect()]);
    }

    public function upload(Request $r)
    {
        Access::professional();
        $d = $r->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg,png,docx,txt|max:10240', 'client_id' => 'nullable|integer', 'organization_id' => 'nullable|integer', 'shared' => 'nullable|boolean']);
        abort_if(! empty($d['client_id']) && ! empty($d['organization_id']), 422, 'Choisissez un patient ou une organisation.');
        if ($d['client_id'] ?? null) {
            Client::findOrFail($d['client_id']);
        }if ($d['organization_id'] ?? null) {
            Organization::findOrFail($d['organization_id']);
        }
        $f = $r->file('file');
        $path = 'vault/'.auth()->user()->tenant_id.'/'.Str::uuid().'.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(file_get_contents($f->getRealPath())));
        try {
            $doc = Document::create(['client_id' => $d['client_id'] ?? null, 'organization_id' => $d['organization_id'] ?? null, 'uploaded_by' => auth()->id(), 'name' => mb_substr($f->getClientOriginalName(), 0, 240), 'path' => $path, 'mime' => $f->getMimeType(), 'size' => $f->getSize(), 'shared' => $r->boolean('shared')]);
            Access::audit('document.depose', $doc);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('success', 'Document chiffré et enregistré.');
    }

    public function documentLink(Document $document)
    {
        Access::document($document);

        return redirect(URL::temporarySignedRoute('documents.download', now()->addMinutes(5), ['document' => $document->id]));
    }

    public function download(Document $document)
    {
        Access::document($document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        Access::audit('document.telecharge', $document);
        $content = Crypt::decryptString(Storage::disk('local')->get($document->path));

        return response()->streamDownload(fn () => print ($content), basename($document->name), ['Content-Type' => 'application/octet-stream']);
    }

    public function letters()
    {
        Access::professional();

        return view('modules.letters', ['letters' => Letter::with('client', 'attachments')->latest()->get(), 'clients' => Client::all(), 'documents' => Document::whereNotNull('client_id')->get()]);
    }

    public function letter(Request $r)
    {
        Access::professional();
        $d = $r->validate(['client_id' => 'required|integer', 'subject' => 'required|string|max:200', 'body' => 'required|string|max:30000', 'attachments' => 'nullable|array|max:20', 'attachments.*' => 'integer|distinct']);
        Client::findOrFail($d['client_id']);
        if (! empty($d['attachments'])) {
            abort_unless(Document::where('client_id', $d['client_id'])->whereIn('id', $d['attachments'])->count() === count($d['attachments']), 422, 'Les pièces jointes doivent appartenir au patient du courrier.');
        }
        $attachments = $d['attachments'] ?? [];
        unset($d['attachments']);
        $l = DB::transaction(function () use ($d, $attachments) {
            $letter = Letter::create($d);
            $letter->attachments()->sync($attachments);

            return $letter;
        });
        Access::audit('courrier.cree', $l);

        return back()->with('success', 'Courrier enregistré.');
    }

    public function letterPdf(Letter $letter)
    {
        Access::professional();
        Access::audit('courrier.pdf', $letter);

        return Pdf::loadView('modules.letter-pdf', ['letter' => $letter, 'cabinet' => auth()->user()->tenant])->download('courrier-'.$letter->id.'.pdf');
    }

    public function letterBundle(Letter $letter): BinaryFileResponse
    {
        Access::professional();
        $path = tempnam(storage_path('app/private'), 'courrier-');
        chmod($path, 0600);
        try {
            $zip = new \ZipArchive;
            if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Création de l’archive impossible.');
            }
            $zip->addFromString('courrier.pdf', Pdf::loadView('modules.letter-pdf', ['letter' => $letter, 'cabinet' => auth()->user()->tenant])->output());
            foreach ($letter->attachments as $document) {
                Access::document($document);
                $name = 'piece-'.$document->id.'-'.preg_replace('/[^\pL\pN._-]/u', '_', basename($document->name));
                $zip->addFromString($name, Crypt::decryptString(Storage::disk('local')->get($document->path)));
            }
            $zip->close();
            Access::audit('courrier.archive_telechargee', $letter);

            return response()->download($path, 'courrier-'.$letter->id.'.zip')->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }
    }

    public function compare()
    {
        Access::professional();

        return view('modules.compare', ['assessments' => Assessment::where('status', '!=', 'en_cours')->with('client', 'definition')->get(), 'comparisons' => Comparison::latest()->get()]);
    }

    public function comparison(Request $r)
    {
        Access::professional();
        $d = $r->validate(['first_assessment_id' => 'required|integer', 'second_assessment_id' => 'required|integer|different:first_assessment_id', 'analysis' => 'nullable|string|max:20000']);
        $a = Assessment::findOrFail($d['first_assessment_id']);
        $b = Assessment::findOrFail($d['second_assessment_id']);
        abort_if($a->status === 'en_cours' || $b->status === 'en_cours', 422);
        abort_unless($a->assessment_definition_id === $b->assessment_definition_id, 422, 'Sélectionnez deux passations du même questionnaire et de la même version.');
        $c = Comparison::create([...$d, 'snapshot' => ['first' => ['name' => $a->client->full_name, 'results' => $a->results], 'second' => ['name' => $b->client->full_name, 'results' => $b->results], 'definition' => $a->definition->name, 'version' => $a->definition->version]]);
        Access::audit('comparaison.creee', $c);

        return back()->with('success', 'Comparaison enregistrée avec instantané des résultats.');
    }

    public function comparisonPdf(Comparison $comparison)
    {
        Access::professional();
        Access::audit('comparaison.pdf', $comparison);

        return Pdf::loadView('modules.comparison-pdf', ['comparison' => $comparison, 'cabinet' => auth()->user()->tenant])->download('comparaison-'.$comparison->id.'.pdf');
    }

    public function workspace()
    {
        Access::professional();

        return view('modules.workspace', ['documents' => WorkspaceDocument::where('author_id', auth()->id())->latest()->get()]);
    }

    public function workspaceSave(Request $r)
    {
        Access::professional();
        $d = $r->validate(['title' => 'required|string|max:200', 'body' => 'required|string|max:30000']);
        WorkspaceDocument::create([...$d, 'author_id' => auth()->id()]);

        return back()->with('success', 'Document de travail enregistré.');
    }
}
