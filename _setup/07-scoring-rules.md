# Scoring Rules (Punktesystem)

This document describes the official scoring rules as displayed to users in the application (from Profile.vue).

> **Note:** The scoring system in `05-game-logic.md` shows a simplified 4/3/1/0 implementation. The actual rules below are **cumulative** - points from multiple criteria can be added together.

---

## Group Phase Scoring

**Maximum points per tip: 10**

Points are awarded cumulatively for each correct prediction:

| Criteria | Points | Description |
|----------|--------|-------------|
| Correct tendency | 5 | Predicted the correct winner or draw |
| Correct goal difference | 3 | Predicted the exact goal difference (e.g., 2-1 vs 3-2 both have +1 difference) |
| Correct home team goals | 1 | Predicted exact goals for home team |
| Correct visitor team goals | 1 | Predicted exact goals for visitor team |

### Examples

| Actual Result | Prediction | Points | Breakdown |
|---------------|------------|--------|-----------|
| 2-1 | 2-1 | 10 | 5 (tendency) + 3 (difference) + 1 (home) + 1 (visitor) |
| 2-1 | 3-2 | 8 | 5 (tendency) + 3 (difference) |
| 2-1 | 2-0 | 6 | 5 (tendency) + 1 (home) |
| 2-1 | 1-0 | 5 | 5 (tendency only) |
| 2-1 | 0-1 | 0 | Wrong tendency |

---

## K.O. Phase Scoring (Knockout Rounds)

**Maximum points per tip: 20**

Points are awarded cumulatively:

| Criteria | Points | Description |
|----------|--------|-------------|
| Correct winner | 10 | Predicted the team that advances (including penalty shootout winner) |
| Correct tendency after 90/120 min | 3 | Predicted the correct result after regular/extra time |
| Correct goal difference | 3 | Predicted the exact goal difference |
| Correct home team goals | 2 | Predicted exact goals for home team |
| Correct visitor team goals | 2 | Predicted exact goals for visitor team |

### Penalty Shootout Rules

- If you predict a draw (e.g., 1-1), you must also select which team wins the penalty shootout
- The "Correct winner" (10 points) is awarded based on who advances, not the score after 90/120 min

### Examples

| Actual Result | Prediction | Points | Breakdown |
|---------------|------------|--------|-----------|
| 2-1 | 2-1 | 20 | 10 (winner) + 3 (tendency) + 3 (difference) + 2 (home) + 2 (visitor) |
| 2-1 (Home wins) | 3-2 | 16 | 10 (winner) + 3 (tendency) + 3 (difference) |
| 1-1 (Home wins shootout) | 1-1 (Home wins shootout) | 20 | 10 (winner) + 3 (tendency) + 3 (difference) + 2 (home) + 2 (visitor) |
| 1-1 (Home wins shootout) | 2-2 (Home wins shootout) | 16 | 10 (winner) + 3 (tendency) + 3 (difference) |
| 1-1 (Home wins shootout) | 1-1 (Visitor wins shootout) | 10 | 3 (tendency) + 3 (difference) + 2 (home) + 2 (visitor) - no winner points |

---

## Bonus Tips (Zusatztipps)

Special predictions that award bonus points:

| Bonus Tip | Points | Description |
|-----------|--------|-------------|
| World Cup Winner | 25 | Predict the tournament champion |
| Group Winners | 8 × 3 = 24 | Predict the winner of each group (A-H) |
| Switzerland Goals | 10 | Predict total goals scored by Switzerland (excluding penalty shootout goals) |
| Switzerland Final Ranking | 6 | Predict how far Switzerland advances |

---

## Tip Submission Deadlines

### Match Tips
- **Deadline:** Until kickoff of the respective match
- Tips can be modified until the game starts

### Bonus Tips (Special Tips)
- **Deadline:** Kickoff of the opening game
- Example: November 21, 2022, 17:00
- No changes allowed after this deadline

### Knockout Games
- You can predict a draw (penalty shootout scenario)
- If predicting a draw, you **must** select the penalty shootout winner

---

## Ranking Tiebreakers

When players have equal total points, the following tiebreakers apply:

1. **Total points** (primary)
2. **Average score per tip** (secondary)
