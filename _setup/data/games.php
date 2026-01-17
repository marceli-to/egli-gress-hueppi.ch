<?php

/**
 * Games for World Cup 2022
 *
 * All times are in CET (Central European Time)
 * home/visitor reference team position in group (e.g., 'A1' = first team in Group A)
 * location_slug references locations.slug
 *
 * Usage in seeder:
 * See WorldCup2022Seeder.php for implementation
 */

return [
    // ============================================
    // GROUP STAGE
    // ============================================

    // Group A
    ['kickoff_at' => '2022-11-20 17:00:00', 'group' => 'A', 'home' => 'A1', 'visitor' => 'A2', 'location' => 'al-bayt_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-21 17:00:00', 'group' => 'A', 'home' => 'A3', 'visitor' => 'A4', 'location' => 'al-thumama_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-25 14:00:00', 'group' => 'A', 'home' => 'A1', 'visitor' => 'A3', 'location' => 'al-thumama_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-25 17:00:00', 'group' => 'A', 'home' => 'A4', 'visitor' => 'A2', 'location' => 'khalifa_international_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-29 16:00:00', 'group' => 'A', 'home' => 'A4', 'visitor' => 'A1', 'location' => 'al-bayt_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-29 16:00:00', 'group' => 'A', 'home' => 'A2', 'visitor' => 'A3', 'location' => 'khalifa_international_stadium', 'type' => 'GROUP'],

    // Group B
    ['kickoff_at' => '2022-11-21 14:00:00', 'group' => 'B', 'home' => 'B1', 'visitor' => 'B2', 'location' => 'khalifa_international_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-21 20:00:00', 'group' => 'B', 'home' => 'B3', 'visitor' => 'B4', 'location' => 'ahmed_bin_ali_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-25 11:00:00', 'group' => 'B', 'home' => 'B4', 'visitor' => 'B2', 'location' => 'ahmed_bin_ali_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-25 20:00:00', 'group' => 'B', 'home' => 'B1', 'visitor' => 'B3', 'location' => 'al-bayt_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-29 20:00:00', 'group' => 'B', 'home' => 'B2', 'visitor' => 'B3', 'location' => 'al-thumama_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-29 20:00:00', 'group' => 'B', 'home' => 'B4', 'visitor' => 'B1', 'location' => 'ahmed_bin_ali_stadium', 'type' => 'GROUP'],

    // Group C
    ['kickoff_at' => '2022-11-22 11:00:00', 'group' => 'C', 'home' => 'C1', 'visitor' => 'C2', 'location' => 'lusail_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-22 17:00:00', 'group' => 'C', 'home' => 'C3', 'visitor' => 'C4', 'location' => 'stadium_974', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-26 14:00:00', 'group' => 'C', 'home' => 'C4', 'visitor' => 'C2', 'location' => 'education_city_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-26 20:00:00', 'group' => 'C', 'home' => 'C1', 'visitor' => 'C3', 'location' => 'lusail_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-30 20:00:00', 'group' => 'C', 'home' => 'C2', 'visitor' => 'C3', 'location' => 'lusail_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-30 20:00:00', 'group' => 'C', 'home' => 'C4', 'visitor' => 'C1', 'location' => 'stadium_974', 'type' => 'GROUP'],

    // Group D
    ['kickoff_at' => '2022-11-22 14:00:00', 'group' => 'D', 'home' => 'D3', 'visitor' => 'D4', 'location' => 'education_city_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-22 20:00:00', 'group' => 'D', 'home' => 'D1', 'visitor' => 'D2', 'location' => 'al-janoub_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-26 11:00:00', 'group' => 'D', 'home' => 'D4', 'visitor' => 'D2', 'location' => 'al-janoub_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-26 17:00:00', 'group' => 'D', 'home' => 'D1', 'visitor' => 'D3', 'location' => 'stadium_974', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-30 16:00:00', 'group' => 'D', 'home' => 'D4', 'visitor' => 'D1', 'location' => 'education_city_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-30 16:00:00', 'group' => 'D', 'home' => 'D2', 'visitor' => 'D3', 'location' => 'al-janoub_stadium', 'type' => 'GROUP'],

    // Group E
    ['kickoff_at' => '2022-11-23 14:00:00', 'group' => 'E', 'home' => 'E3', 'visitor' => 'E4', 'location' => 'khalifa_international_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-23 17:00:00', 'group' => 'E', 'home' => 'E1', 'visitor' => 'E2', 'location' => 'al-thumama_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-27 11:00:00', 'group' => 'E', 'home' => 'E4', 'visitor' => 'E2', 'location' => 'ahmed_bin_ali_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-27 20:00:00', 'group' => 'E', 'home' => 'E1', 'visitor' => 'E3', 'location' => 'al-bayt_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-12-01 20:00:00', 'group' => 'E', 'home' => 'E2', 'visitor' => 'E3', 'location' => 'al-bayt_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-12-01 20:00:00', 'group' => 'E', 'home' => 'E4', 'visitor' => 'E1', 'location' => 'khalifa_international_stadium', 'type' => 'GROUP'],

    // Group F
    ['kickoff_at' => '2022-11-23 11:00:00', 'group' => 'F', 'home' => 'F3', 'visitor' => 'F4', 'location' => 'al-bayt_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-23 20:00:00', 'group' => 'F', 'home' => 'F1', 'visitor' => 'F2', 'location' => 'ahmed_bin_ali_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-27 14:00:00', 'group' => 'F', 'home' => 'F1', 'visitor' => 'F3', 'location' => 'al-thumama_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-27 17:00:00', 'group' => 'F', 'home' => 'F4', 'visitor' => 'F2', 'location' => 'khalifa_international_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-12-01 16:00:00', 'group' => 'F', 'home' => 'F2', 'visitor' => 'F3', 'location' => 'al-thumama_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-12-01 16:00:00', 'group' => 'F', 'home' => 'F4', 'visitor' => 'F1', 'location' => 'ahmed_bin_ali_stadium', 'type' => 'GROUP'],

    // Group G
    ['kickoff_at' => '2022-11-24 11:00:00', 'group' => 'G', 'home' => 'G3', 'visitor' => 'G4', 'location' => 'al-janoub_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-24 20:00:00', 'group' => 'G', 'home' => 'G1', 'visitor' => 'G2', 'location' => 'lusail_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-28 11:00:00', 'group' => 'G', 'home' => 'G4', 'visitor' => 'G2', 'location' => 'al-janoub_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-28 17:00:00', 'group' => 'G', 'home' => 'G1', 'visitor' => 'G3', 'location' => 'stadium_974', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-12-02 20:00:00', 'group' => 'G', 'home' => 'G2', 'visitor' => 'G3', 'location' => 'stadium_974', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-12-02 20:00:00', 'group' => 'G', 'home' => 'G4', 'visitor' => 'G1', 'location' => 'lusail_stadium', 'type' => 'GROUP'],

    // Group H
    ['kickoff_at' => '2022-11-24 14:00:00', 'group' => 'H', 'home' => 'H3', 'visitor' => 'H4', 'location' => 'education_city_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-24 17:00:00', 'group' => 'H', 'home' => 'H1', 'visitor' => 'H2', 'location' => 'stadium_974', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-28 14:00:00', 'group' => 'H', 'home' => 'H4', 'visitor' => 'H2', 'location' => 'education_city_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-11-28 20:00:00', 'group' => 'H', 'home' => 'H1', 'visitor' => 'H3', 'location' => 'lusail_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-12-02 16:00:00', 'group' => 'H', 'home' => 'H2', 'visitor' => 'H3', 'location' => 'al-janoub_stadium', 'type' => 'GROUP'],
    ['kickoff_at' => '2022-12-02 16:00:00', 'group' => 'H', 'home' => 'H4', 'visitor' => 'H1', 'location' => 'education_city_stadium', 'type' => 'GROUP'],

    // ============================================
    // KNOCKOUT STAGE
    // ============================================

    // Round of 16
    ['kickoff_at' => '2022-12-03 16:00:00', 'type' => 'ROUND_OF_16', 'name' => 'round-of-16-1', 'home_placeholder' => 'Winner A', 'visitor_placeholder' => 'Runner-up B', 'location' => 'khalifa_international_stadium'],
    ['kickoff_at' => '2022-12-03 20:00:00', 'type' => 'ROUND_OF_16', 'name' => 'round-of-16-2', 'home_placeholder' => 'Winner C', 'visitor_placeholder' => 'Runner-up D', 'location' => 'khalifa_international_stadium'],
    ['kickoff_at' => '2022-12-04 16:00:00', 'type' => 'ROUND_OF_16', 'name' => 'round-of-16-3', 'home_placeholder' => 'Winner D', 'visitor_placeholder' => 'Runner-up C', 'location' => 'al-thumama_stadium'],
    ['kickoff_at' => '2022-12-04 20:00:00', 'type' => 'ROUND_OF_16', 'name' => 'round-of-16-4', 'home_placeholder' => 'Winner B', 'visitor_placeholder' => 'Runner-up A', 'location' => 'al-bayt_stadium'],
    ['kickoff_at' => '2022-12-05 16:00:00', 'type' => 'ROUND_OF_16', 'name' => 'round-of-16-5', 'home_placeholder' => 'Winner E', 'visitor_placeholder' => 'Runner-up F', 'location' => 'stadium_974'],
    ['kickoff_at' => '2022-12-05 20:00:00', 'type' => 'ROUND_OF_16', 'name' => 'round-of-16-6', 'home_placeholder' => 'Winner G', 'visitor_placeholder' => 'Runner-up H', 'location' => 'al-janoub_stadium'],
    ['kickoff_at' => '2022-12-06 16:00:00', 'type' => 'ROUND_OF_16', 'name' => 'round-of-16-7', 'home_placeholder' => 'Winner F', 'visitor_placeholder' => 'Runner-up E', 'location' => 'education_city_stadium'],
    ['kickoff_at' => '2022-12-06 20:00:00', 'type' => 'ROUND_OF_16', 'name' => 'round-of-16-8', 'home_placeholder' => 'Winner H', 'visitor_placeholder' => 'Runner-up G', 'location' => 'lusail_stadium'],

    // Quarter Finals
    ['kickoff_at' => '2022-12-09 16:00:00', 'type' => 'QUARTER_FINAL', 'name' => 'quarter-final-1', 'home_placeholder' => 'Winner R16-5', 'visitor_placeholder' => 'Winner R16-6', 'location' => 'education_city_stadium'],
    ['kickoff_at' => '2022-12-09 20:00:00', 'type' => 'QUARTER_FINAL', 'name' => 'quarter-final-2', 'home_placeholder' => 'Winner R16-1', 'visitor_placeholder' => 'Winner R16-2', 'location' => 'lusail_stadium'],
    ['kickoff_at' => '2022-12-10 16:00:00', 'type' => 'QUARTER_FINAL', 'name' => 'quarter-final-3', 'home_placeholder' => 'Winner R16-7', 'visitor_placeholder' => 'Winner R16-8', 'location' => 'al-thumama_stadium'],
    ['kickoff_at' => '2022-12-10 20:00:00', 'type' => 'QUARTER_FINAL', 'name' => 'quarter-final-4', 'home_placeholder' => 'Winner R16-3', 'visitor_placeholder' => 'Winner R16-4', 'location' => 'al-bayt_stadium'],

    // Semi Finals
    ['kickoff_at' => '2022-12-13 20:00:00', 'type' => 'SEMI_FINAL', 'name' => 'semi-final-1', 'home_placeholder' => 'Winner QF-2', 'visitor_placeholder' => 'Winner QF-1', 'location' => 'lusail_stadium'],
    ['kickoff_at' => '2022-12-14 20:00:00', 'type' => 'SEMI_FINAL', 'name' => 'semi-final-2', 'home_placeholder' => 'Winner QF-4', 'visitor_placeholder' => 'Winner QF-3', 'location' => 'al-bayt_stadium'],

    // Third Place
    ['kickoff_at' => '2022-12-17 16:00:00', 'type' => 'THIRD_PLACE', 'name' => 'third-place', 'home_placeholder' => 'Loser SF-1', 'visitor_placeholder' => 'Loser SF-2', 'location' => 'khalifa_international_stadium'],

    // Final
    ['kickoff_at' => '2022-12-18 16:00:00', 'type' => 'FINAL', 'name' => 'final', 'home_placeholder' => 'Winner SF-1', 'visitor_placeholder' => 'Winner SF-2', 'location' => 'lusail_stadium'],
];
