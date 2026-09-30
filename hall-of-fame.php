<?php

declare(strict_types=1);

/**
 * Load and decode a local JSON file.
 *
 * @param string $filePath
 * @param string $label
 * @return array<string, mixed>
 */
function load_json_data(string $filePath, string $label): array
{
    if (!is_file($filePath)) {
        http_response_code(500);
        echo json_encode(['error' => $label . ' file not found']);
        exit;
    }

    $contents = file_get_contents($filePath);
    if ($contents === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read ' . $label . ' file']);
        exit;
    }

    $data = json_decode($contents, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode(['error' => $label . ' file is not valid JSON']);
        exit;
    }

    return $data;
}

/**
 * Load and decode all finals datasets that match YYYY-finals.json.
 *
 * @return array<int, array<string, mixed>>
 */
function load_all_finals_data(): array
{
    $files = glob(__DIR__ . '/finals/*-finals.json');
    if (!is_array($files)) {
        return [];
    }

    $finalsFiles = array_values(array_filter(
        $files,
        static fn (string $path): bool => preg_match('/^\d{4}-finals\.json$/', basename($path)) === 1
    ));

    sort($finalsFiles, SORT_STRING);

    $datasets = [];
    foreach ($finalsFiles as $filePath) {
        $datasets[] = load_json_data($filePath, 'Finals data');
    }

    return $datasets;
}

/**
 * Load and decode the legacy hall-of-fame dataset.
 *
 * @return array<string, mixed>
 */
function load_legacy_data(): array
{
    return load_json_data(__DIR__ . '/finals/legacy.json', 'Legacy data');
}

/**
 * Build a lookup table from player code to player details.
 *
 * @param array<string, mixed> $data
 * @return array<string, array<string, mixed>>
 */
function build_player_lookup(array $data): array
{
    $lookup = [];

    foreach (($data['players'] ?? []) as $player) {
        if (!is_array($player) || !isset($player['code'])) {
            continue;
        }

        $lookup[(string) $player['code']] = $player;
    }

    return $lookup;
}

/**
 * Build the top-three summary for a competition.
 *
 * @param array<string, mixed> $competition
 * @param array<string, array<string, mixed>> $playerLookup
 * @return array<string, mixed>|null
 */
function build_competition_hall_of_fame(array $competition, array $playerLookup): ?array
{
    $standings = $competition['standings'] ?? null;
    if (!is_array($standings) && isset($competition['phases']) && is_array($competition['phases'])) {
        $phases = $competition['phases'];
        $lastPhase = end($phases);
        if (is_array($lastPhase) && isset($lastPhase['standings']) && is_array($lastPhase['standings'])) {
            $standings = $lastPhase['standings'];
        }
    }

    if (!is_array($standings)) {
        return null;
    }

    usort($standings, static fn (array $left, array $right): int => ($left['rank'] ?? 0) <=> ($right['rank'] ?? 0));

    $rankings = [];
    foreach ([1, 2, 3] as $rank) {
        $standingMatch = null;
        foreach ($standings as $standing) {
            if (($standing['rank'] ?? null) === $rank) {
                $standingMatch = $standing;
                break;
            }
        }

        if ($standingMatch === null) {
            $rankings[(string) $rank] = null;
            continue;
        }

        $players = [];
        foreach (($standingMatch['players'] ?? []) as $playerCode) {
            $playerCode = (string) $playerCode;
            $player = $playerLookup[$playerCode] ?? null;
            $playerName = is_array($player) && isset($player['name']) ? (string) $player['name'] : $playerCode;

            $players[] = [
                'name' => $playerName,
            ];
        }

        $rankings[(string) $rank] = [
            'rank' => $rank,
            'players' => $players,
        ];
    }

    return [
        'discipline' => $competition['name'] ?? null,
        'rankings' => $rankings,
    ];
}

/**
 * Build the hall of fame summary as discipline -> year -> rank.
 *
 * @param array<string, mixed> $data
 * @return array<string, array<string, array<string, mixed>|null>>
 */
function build_hall_of_fame(array $data): array
{
    $playerLookup = build_player_lookup($data);
    $hallOfFame = [];
    $year = null;

    if (isset($data['tournament']) && is_array($data['tournament']) && isset($data['tournament']['start_at'])) {
        $startAt = (string) $data['tournament']['start_at'];
        $year = substr($startAt, 0, 4);
    }

    if ($year === null || !preg_match('/^\d{4}$/', $year)) {
        $year = 'unknown';
    }

    foreach (($data['competitions'] ?? []) as $competition) {
        if (!is_array($competition)) {
            continue;
        }

        $summary = build_competition_hall_of_fame($competition, $playerLookup);
        if ($summary !== null && isset($summary['discipline']) && is_string($summary['discipline'])) {
            $discipline = $summary['discipline'];
            $hallOfFame[$discipline] = [
                $year => $summary['rankings'],
            ];
        }
    }

    return $hallOfFame;
}

/**
 * Handle the /hall-of-fame endpoint.
 */
function handle_hall_of_fame_request(): void
{
    $legacyData = load_legacy_data();
    $allFinals = load_all_finals_data();

    foreach ($allFinals as $finalsData) {
        $currentData = build_hall_of_fame($finalsData);

        foreach ($currentData as $discipline => $years) {
            if (!is_array($years)) {
                continue;
            }

            if (!isset($legacyData[$discipline]) || !is_array($legacyData[$discipline])) {
                $legacyData[$discipline] = $years;
                continue;
            }

            foreach ($years as $year => $rankings) {
                $legacyData[$discipline][(string) $year] = $rankings;
            }
        }
    }

    echo json_encode($legacyData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
