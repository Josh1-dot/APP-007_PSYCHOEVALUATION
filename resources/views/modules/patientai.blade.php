@extends('layout')
@section('title', 'PatientAI')
@section('subtitle', 'Assistant de démonstration : réponses prédéfinies, sans consultation du dossier clinique.')
@section('content')
<section class="panel form-panel">
<p>Messages conservés {{ config('patientai.retention_days') }} jours après la dernière activité de conversation (création, échange ou retrait), sauf suspension de conservation. Vous pouvez supprimer une conversation et demander un export depuis votre profil.</p>
@if($conversation)
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
<button class="btn primary">Créer une conversation</button></form>
<h2>Mes conversations</h2>
@forelse($conversations as $item)<p><a href="{{ route('patientai.show', $item) }}">Conversation du {{ $item->created_at->format('d/m/Y H:i') }}</a></p><form method="post" action="{{ route('patientai.destroy', $item) }}" data-confirm="Supprimer cette conversation et retirer son accord ?">@csrf @method('DELETE')<button class="btn secondary">Supprimer et retirer mon accord</button></form>@empty<p>Aucune conversation.</p>@endforelse
{{ $conversations->links() }}
@endif
</section>
@endsection
