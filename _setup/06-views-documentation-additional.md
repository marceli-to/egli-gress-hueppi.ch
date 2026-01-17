# Tippspiel Views Documentation

This document describes all Vue views in `/tippspiel-fe/src/js/views/` and their content structure.

---

## 1. Home.vue

**Purpose:** Landing page / Homepage

**Layout:** Two-column grid (12 columns: 6 + 6)

### Left Column
- **Welcome Section**
  - **Unauthenticated users:**
    - Header: "Herzlich willkommen bei egli-gress.ch"
    - Description text about the tipping game
    - Login button (chip style with arrow icon)
  - **Authenticated users:**
    - Header: "Hallo [username]"
    - Greeting message
    - Logout link

### Right Column
- **Overall Ranking Table (Gesamtrangliste)**
  - Table with columns:
    - `R` - Rank
    - `Spieler` - Player name
    - `C` - Champion team (flag)
    - `P` - Points
  - Displays ranking data fetched from `/ranking` API endpoint

**Components Used:** `Grid`, `GridItem`, `ContentHeader`, `Toast`, `TeamFlag`, `ArrowRightIcon`

---

## 2. Games.vue

**Purpose:** Main view for placing game tips (group and final matches)

**Layout:** Two-column grid (12 columns: 6 + 6)

### Left Column
- **GameGroupsBrowser** - Navigation for selecting:
  - Groups A-H (for group stage)
  - K.O. (for final stage)

- **Group Stage Content:**
  - `GroupRanking` component showing current group standings

- **Final Stage Content:**
  - `FinalTable` component with knockout bracket

### Right Column
- **Group Stage Content:**
  - List of `Game` components for the selected group
  - Each game allows entering tip predictions

- **Final Stage Content:**
  - `GameStageBrowser` for selecting knockout round:
    - Round of 16
    - Quarter-finals
    - Semi-finals
    - Final
  - List of `Game` components for selected stage
  - Final games include penalty shootout winner selection

**Components Used:** `Grid`, `GridItem`, `Team`, `GroupRanking`, `FinalTable`, `Game`, `GameGroupsBrowser`, `GameStageBrowser`, `TipEvaluation`

---

## 3. BonusTips.vue

**Purpose:** Special/bonus tips that award extra points

**Layout:** Two-column grid (12 columns: 6 + 6)

### Left Column

#### World Cup Winner (25 points)
- Header with points indicator
- Current selection with trophy icon
- Team selection grid (all participating teams with flags)
- When team selected, shows stats table:
  - FIFA Ranking
  - WM-Titel (World Cup titles)
  - Teilnahmen (Participations)
  - Tipp-Quote (Tip percentage)
- Jersey icon display

#### Group Winners (8 x 3 points)
- Header with points indicator
- 8 group selections (Groups A-H)
- Each group shows:
  - Group letter pill
  - Team flag
  - Dropdown to select winner from group teams
- Checkmark shown for correct predictions

### Right Column

#### Switzerland Goals (10 points)
- Header with points indicator
- Description: Goals scored by Switzerland (excluding penalty shootout)
- Number input field

#### Switzerland Final Ranking (6 points)
- Header with points indicator
- Pill-style buttons to select how far Switzerland advances:
  - Group stage exit
  - Round of 16
  - Quarter-finals
  - Semi-finals
  - Runner-up
  - Champion

**Components Used:** `Grid`, `GridItem`, `ContentHeader`, `FormGroup`, `Jersey`, `Toast`, `Team`, `TeamFlag`, `Trophy`, `Pill`, `CheckmarkIcon`

---

## 4. Ranking.vue

**Purpose:** Display rankings and compare tips with other players

**Layout:** Two-column grid (12 columns: 6 + 6)

### Left Column
- **Header:** "Meine Ranglisten" (My Rankings)
- **Ranking Component**
  - Shows rankings with tip groups
  - Allows selecting a player to compare
  - Allows selecting a group to filter

### Right Column
- **Tip Comparison Section**
  - **When player selected:**
    - Header: "Tippvergleich mit [username]"
    - List of `GameHistory` components showing comparison
  - **When no player selected:**
    - Header: "Tippvergleich"
    - Instruction text to select a player from the ranking

**Components Used:** `Grid`, `GridItem`, `Toast`, `PointsBar`, `TeamFlag`, `Ranking`, `RankingCurrentTip`, `RankingCurrentGames`, `ArrowRightIcon`, `GameHistory`

---

## 5. Profile.vue

**Purpose:** User account and tip group management

**Layout:** Two-column grid (12 columns: 6 + 6)

### Left Column

#### Tip Groups Section (Tippgruppen)
Displays one of three states:

1. **Create Group Form:**
   - Header: "Tippgruppe erstellen"
   - Close button (X icon)
   - Input: Group name (required)
   - Input: Password (optional)
   - Submit button: "Erstellen"

2. **Join Group Form:**
   - Header: "Tippgruppe beitreten"
   - Close button (X icon)
   - Dropdown: Select group
   - Input: Password
   - Submit button: "Beitreten"

3. **Groups List (default):**
   - Header with action buttons:
     - "Erstellen" (Create) with plus icon
     - "Beitreten" (Join) with login icon
   - List of `TipGroup` components

#### Account Section (Mein Konto)
- Username display
- Logout link

### Right Column

#### Points System / Rules (Punktesystem / Regeln)

**Group Phase Points (max 10 per tip):**
- 5 points: Correct winner/tendency
- 3 points: Correct goal difference
- 1 point: Correct home team goals
- 1 point: Correct visitor team goals

**K.O. Phase Points (max 20 per tip):**
- 10 points: Correct winner
- 3 points: Correct tendency after 90/120 min
- 3 points: Correct goal difference
- 2 points: Correct home team goals
- 2 points: Correct visitor team goals

**Tip Submission:**
- Match tips: Until kickoff of respective match
- Special tips: Locked at kickoff of opening game (November 21, 2022, 17:00)
- K.O. games: Can tip for draw/penalty shootout, must select winner

**Components Used:** `Grid`, `GridItem`, `ContentHeader`, `FormGroup`, `Toast`, `TipGroup`, `XCircleIcon`, `XMarkIcon`, `PlusIcon`, `LoginIcon`

---

## 6. Dashboard.vue

**Purpose:** Admin view for entering official game results

**Layout:** Full-width listing of game forms

### Game Entry Form (for each game)

#### Header
- Date/time and city (uppercase)
- Column headers (10-column grid):
  - Team name space
  - FP (Fairplay points) - group games only
  - "Tore" (Goals) - home team
  - "Tore" (Goals) - visitor team
  - FP (Fairplay points) - group games only

#### Game Row (10-column grid)
- **Home Team:**
  - Team component with short name
  - Fairplay points input (group games)
  - Penalty winner radio button (final games, only when draw)
  - Goals input

- **Visitor Team:**
  - Goals input
  - Penalty winner radio button (final games, only when draw)
  - Fairplay points input (group games)
  - Team component with short name

#### Footer
- Fulltime checkbox
- Save button ("Speichern")

**Components Used:** `Grid`, `GridItem`, `Team`, `Toast`

---

## Common Layout Patterns

### Grid System
All views use a responsive 12-column grid system:
- `sm:grid-cols-12` - 12 columns on small+ screens
- `sm:span-6` - 6 column span (half width)
- `span-1` through `span-10` for fine-grained control

### Content Structure
- `<section class="app-content">` - Main content wrapper
- `<article>` - Content blocks within grid items
- `<content-header>` - Section headers with optional action buttons

### Common Components
- `Toast` - Notification messages
- `Grid` / `GridItem` - Layout grid system
- `ContentHeader` - Section headers
- `FormGroup` - Form input wrapper
- `Team` / `TeamFlag` - Team display with flags
- `Pill` - Badge/chip style elements

### Data Fetching
- All views use `axios` for API calls
- `NProgress` for loading indicators
- `isFetched` state to control content visibility
- `isSaving` state for form submission feedback

### Authentication
- `$isAuthenticated()` - Check login status
- `$username()` - Get current username
- `$logout()` - Logout function
- `$keycloak.login()` - Keycloak authentication
