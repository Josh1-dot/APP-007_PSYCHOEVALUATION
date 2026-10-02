@extends('layout')
@section('title', 'PatientAI')
@section('subtitle', 'Assistant de démonstration : réponses prédéfinies, sans consultation du dossier clinique.')
@section('content')
<section class="panel form-panel">
<p>Messages conservés {{ config('patientai.retention_days') }} jours après la dernière activité de conversation (création, échange ou retrait), sauf suspension de conservation. Vous pouvez supprimer une conversation et demander un export depuis votre profil.</p>
<p>La mémoire est une préférence de présentation explicitement choisie, jamais une note clinique ni une source de données métier. La suppression de sa conversation source efface aussi cette préférence.</p>
<form method="post" action="{{ route('patientai.memory.clear') }}" data-confirm="Effacer toutes mes préférences mémorisées ?">@csrf @method('DELETE')<button class="btn secondary">Effacer et désactiver ma mémoire</button></form>
@if($conversation)
<p>{{ $conversation->memory_enabled ? 'Conversation avec mémoire autorisée.' : 'Conversation sans mémoire : aucun souvenir antérieur utilisé.' }}</p>
<a href="{{ route('patientai.index') }}">Mes conversations</a>
<div class="patientai-messages" aria-label="Historique de conversation">
@foreach($messages->getCollection()->reverse() as $message)
<div class="patientai-bubble {{ $message->role === 'patient' ? 'patientai-patient' : 'patientai-assistant' }}"><strong>{{ $message->role === 'patient' ? 'Vous' : 'PatientAI' }}</strong><p>{{ $message->content }}</p><small>{{ $message->created_at->format('d/m/Y H:i') }}</small></div>
@endforeach
</div>
{{ $messages->links() }}
@if($conversation->status === 'active')
<form method="post" action="{{ route('patientai.message', $conversation) }}">@csrf
<label>Votre message<textarea name="content" rows="3" maxlength="{{ config('patientai.max_message_length') }}" required></textarea></label>
<button class="btn primary">Envoyer</button></form>
@else<p>Accord retiré : aucun nouveau message autorisé.</p>@endif
<form method="post" action="{{ route('patientai.destroy', $conversation) }}" data-confirm="Supprimer définitivement cette conversation et retirer son accord ?">@csrf @method('DELETE')<button class="btn danger">Supprimer et retirer mon accord</button></form>
@else
<h2>Nouvelle conversation</h2>
<form method="post" action="{{ route('patientai.store') }}">@csrf
<p>{{ config('patientai.consent_text') }}</p>
<label class="check-label"><input type="checkbox" name="accepted" value="1" required> J’accepte ({{ config('patientai.consent_version') }}).</label>
<label>Mode de conversation<select name="memory_enabled"><option value="0">Nouvelle conversation sans mémoire (par défaut)</option><option value="1">Nouvelle conversation avec mémoire</option></select></label>
<p>{{ \App\Services\PatientMemoryService::CONSENT_TEXT }}</p>
<label class="check-label"><input type="checkbox" name="memory_accepted" value="1"> J’autorise la mémoire si je choisis le mode avec mémoire ({{ \App\Services\PatientMemoryService::CONSENT_VERSION }}).</label>
<label>Préférence à mémoriser uniquement en mode avec mémoire<select name="memory_style"><option value="">Ne rien enregistrer ; réutiliser la préférence autorisée existante</option><option value="standard">Présentation standard</option><option value="concise">Présentation concise (salutations plus courtes)</option></select></label>
<button class="btn primary">Créer une conversation</button></form>
<h2>Mes conversations</h2>
@forelse($conversations as $item)<p><a href="{{ route('patientai.show', $item) }}">Conversation du {{ $item->created_at->format('d/m/Y H:i') }}</a></p><form method="post" action="{{ route('patientai.destroy', $item) }}" data-confirm="Supprimer cette conversation et retirer son accord ?">@csrf @method('DELETE')<button class="btn secondary">Supprimer et retirer mon accord</button></form>@empty<p>Aucune conversation.</p>@endforelse
{{ $conversations->links() }}
@endif
</section>
@endsection
