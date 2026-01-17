@props(['tipp', 'game', 'showTipp' => false])

@php
    $isKnockout = $game->isKnockoutGame();

    if ($isKnockout) {
        $actualWinnerId = $game->goals_home > $game->goals_visitor
            ? $game->home_team_id
            : ($game->goals_visitor > $game->goals_home
                ? $game->visitor_team_id
                : $game->penalty_winner_team_id);
        $predictedWinnerId = $tipp->goals_home > $tipp->goals_visitor
            ? $game->home_team_id
            : ($tipp->goals_visitor > $tipp->goals_home
                ? $game->visitor_team_id
                : $tipp->penalty_winner_team_id);
        $isWinnerCorrect = $actualWinnerId && $predictedWinnerId && $actualWinnerId === $predictedWinnerId;
    }
@endphp

<div class="relative group/score">
    <span class="px-2 py-1 text-xs rounded cursor-help {{ $tipp->score > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
        {{ $tipp->score }} Pkt @if($showTipp)({{ $tipp->goals_home }}:{{ $tipp->goals_visitor }})@endif
    </span>
    <div class="absolute right-0 bottom-full mb-1 bg-gray-900 text-white text-[10px] rounded p-2 invisible group-hover/score:visible z-50 shadow-lg whitespace-nowrap">
        @if ($isKnockout)
            <span class="{{ $isWinnerCorrect ? 'text-green-400' : 'text-gray-500' }}">S:{{ $isWinnerCorrect ? '10' : '0' }}</span>
            <span class="{{ $tipp->is_tendency_correct ? 'text-green-400' : 'text-gray-500' }}">T:{{ $tipp->is_tendency_correct ? '3' : '0' }}</span>
            <span class="{{ $tipp->is_difference_correct ? 'text-green-400' : 'text-gray-500' }}">D:{{ $tipp->is_difference_correct ? '3' : '0' }}</span>
            <span class="{{ $tipp->is_goals_home_correct ? 'text-green-400' : 'text-gray-500' }}">H:{{ $tipp->is_goals_home_correct ? '2' : '0' }}</span>
            <span class="{{ $tipp->is_goals_visitor_correct ? 'text-green-400' : 'text-gray-500' }}">G:{{ $tipp->is_goals_visitor_correct ? '2' : '0' }}</span>
        @else
            <span class="{{ $tipp->is_tendency_correct ? 'text-green-400' : 'text-gray-500' }}">T:{{ $tipp->is_tendency_correct ? '5' : '0' }}</span>
            <span class="{{ $tipp->is_difference_correct ? 'text-green-400' : 'text-gray-500' }}">D:{{ $tipp->is_difference_correct ? '3' : '0' }}</span>
            <span class="{{ $tipp->is_goals_home_correct ? 'text-green-400' : 'text-gray-500' }}">H:{{ $tipp->is_goals_home_correct ? '1' : '0' }}</span>
            <span class="{{ $tipp->is_goals_visitor_correct ? 'text-green-400' : 'text-gray-500' }}">G:{{ $tipp->is_goals_visitor_correct ? '1' : '0' }}</span>
        @endif
    </div>
</div>
