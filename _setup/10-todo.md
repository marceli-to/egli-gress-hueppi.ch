# TODO - Future Improvements

## Automatic Knockout Progression

Currently, when a knockout game is finished in the admin panel, the winner must be manually assigned to the next round's game.

**Desired behavior:**
- When a knockout game is marked as finished, automatically assign the winner to the correct next-round game
- For semi-finals: winner goes to final, loser goes to 3rd place match
- Should work the same way as the simulation (`propagateWinnerToNextRound()` in TournamentSimulate.php)

**Implementation notes:**
- Add progression logic to `ScoreCalculationService::calculateGameScores()` or create a separate service
- Need to determine the bracket structure (which R16 games feed into which QF games, etc.)
- Consider adding a "Bracket" or "KnockoutProgression" service

**Reference:**
See `app/Console/Commands/TournamentSimulate.php` for working implementation:
- `propagateWinnerToNextRound()`
- `getGameWinner()`
- `getGameLoser()`
- `getNextRoundType()`
