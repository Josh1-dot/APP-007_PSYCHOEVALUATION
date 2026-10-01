@if(auth()->user()->canPublish() && $assessment->interpretation)
<section class="panel form-panel">
    <h2>Historique des générations IA</h2>
    <p class="muted">Les sorties originales conservées ici sont distinctes du brouillon révisé et du contenu publié. Les sorties antérieures à l’activation de cet historique ne peuvent pas être reconstituées.</p>
    @forelse($assessment->interpretation->ai_generations ?? [] as $generation)
        <details>
            <summary>Génération {{ $loop->iteration }} · {{ $generation['recorded_at'] }}</summary>
            <p class="muted">Modèle demandé : {{ $generation['requested_model'] }} · Modèle retourné : {{ $generation['response_model'] ?? 'Non communiqué' }} · Prompt : {{ $generation['prompt_version'] }}</p>
            <p class="prewrap">{{ $generation['content'] }}</p>
        </details>
    @empty
        <p>Aucune sortie IA originale conservée dans cet historique. Le brouillon actuel n’est pas une preuve de la sortie IA d’origine.</p>
    @endforelse
</section>
@endif
