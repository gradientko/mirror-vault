<?php
declare(strict_types=1);

const MAX_LEVEL = 5;

function levelDefinitions(): array
{
    return [
        1 => [
            'name' => 'Reflection',
            'mode' => 'horizontal',
            'left' => [
                '###########',
                '#.........#',
                '#..###....#',
                '#.........#',
                '#....A....#',
                '#.........#',
                '#.......E.#',
                '###########',
            ],
            'right' => [
                '###########',
                '#.........#',
                '#....###..#',
                '#.........#',
                '#....B....#',
                '#.........#',
                '#.E.......#',
                '###########',
            ],
            'player' => [1, 1],
            'clone' => [9, 1],
        ],
        2 => [
            'name' => 'Split Walls',
            'mode' => 'horizontal',
            'left' => [
                '###########',
                '#.........#',
                '#.#####...#',
                '#.....#...#',
                '#..A..#...#',
                '#.........#',
                '#.......E.#',
                '###########',
            ],
            'right' => [
                '###########',
                '#.........#',
                '#...#####.#',
                '#...#.....#',
                '#...#..B..#',
                '#.........#',
                '#.E.......#',
                '###########',
            ],
            'player' => [1, 1],
            'clone' => [9, 1],
        ],
        3 => [
            'name' => 'Double Lock',
            'mode' => 'horizontal',
            'left' => [
                '###########',
                '#.........#',
                '#.###.###.#',
                '#...A.....#',
                '#####D#####',
                '#.........#',
                '#.......E.#',
                '###########',
            ],
            'right' => [
                '###########',
                '#.........#',
                '#.###.###.#',
                '#.....B...#',
                '#####D#####',
                '#.........#',
                '#.E.......#',
                '###########',
            ],
            'player' => [1, 1],
            'clone' => [9, 1],
        ],
        4 => [
            'name' => 'Broken Mirror',
            'mode' => 'horizontal',
            'left' => [
                '###########',
                '#.........#',
                '#.###.###.#',
                '#...M.....#',
                '#.###.###.#',
                '#....A....#',
                '#.......E.#',
                '###########',
            ],
            'right' => [
                '###########',
                '#.........#',
                '#.###.###.#',
                '#.....M...#',
                '#.###.###.#',
                '#....B....#',
                '#.E.......#',
                '###########',
            ],
            'player' => [1, 1],
            'clone' => [9, 1],
        ],
        5 => [
            'name' => 'The Vault',
            'mode' => 'horizontal',
            'left' => [
                '###########',
                '#....M....#',
                '#.###.###.#',
                '#..A......#',
                '#####D#####',
                '#..^......#',
                '#.......E.#',
                '###########',
            ],
            'right' => [
                '###########',
                '#....M....#',
                '#.###.###.#',
                '#......B..#',
                '#####D#####',
                '#......^..#',
                '#.E.......#',
                '###########',
            ],
            'player' => [1, 1],
            'clone' => [9, 1],
        ],
    ];
}

function createGame(): array
{
    $game = [
        'level' => 1,
        'status' => 'playing',
        'turns' => 0,
        'message' => 'Move both selves to their exits.',
        'mode' => 'horizontal',
        'settled' => false,
    ];
    loadLevel($game, 1);
    return $game;
}

function loadLevel(array &$game, int $level): void
{
    $def = levelDefinitions()[$level];
    $game['level'] = $level;
    $game['level_name'] = $def['name'];
    $game['mode'] = $def['mode'];
    $game['left'] = array_map('str_split', $def['left']);
    $game['right'] = array_map('str_split', $def['right']);
    $game['player'] = ['x' => $def['player'][0], 'y' => $def['player'][1]];
    $game['clone'] = ['x' => $def['clone'][0], 'y' => $def['clone'][1]];
    $game['turns'] = 0;
    $game['status'] = 'playing';
    $game['message'] = "Level {$level}: {$def['name']}. Reach both exits.";
}

function performTurn(array &$game, string $action): void
{
    if ($game['status'] !== 'playing') return;
    if (!in_array($action, ['up','down','left','right','wait'], true)) return;

    $game['turns']++;
    [$dx, $dy] = deltaForAction($action);
    [$cdx, $cdy] = mirroredDelta($dx, $dy, $game['mode']);

    $doorOpenBefore = doubleLockOpen($game);
    moveActor($game, 'left', $game['player'], $dx, $dy, $doorOpenBefore);
    moveActor($game, 'right', $game['clone'], $cdx, $cdy, $doorOpenBefore);

    if (actorOnTile($game['left'], $game['player'], 'M')) {
        cycleMirrorMode($game);
        $game['left'][$game['player']['y']][$game['player']['x']] = '.';
    }

    if (actorOnTile($game['right'], $game['clone'], 'M')) {
        cycleMirrorMode($game);
        $game['right'][$game['clone']['y']][$game['clone']['x']] = '.';
    }

    if (
        actorOnTile($game['left'], $game['player'], '^') ||
        actorOnTile($game['right'], $game['clone'], '^')
    ) {
        loseGame($game, 'One of you stepped onto a spike tile.');
        return;
    }

    if (
        actorOnTile($game['left'], $game['player'], 'E') &&
        actorOnTile($game['right'], $game['clone'], 'E')
    ) {
        advanceLevel($game);
        return;
    }

    if (
        actorOnTile($game['left'], $game['player'], 'E') ||
        actorOnTile($game['right'], $game['clone'], 'E')
    ) {
        $game['message'] = 'One exit reached. The other self must arrive on the same turn.';
    } elseif (doubleLockOpen($game)) {
        $game['message'] = 'Both plates are active. The vault doors are open.';
    } else {
        $game['message'] = "Turn {$game['turns']}. Mirror mode: " . mirrorModeLabel($game['mode']) . '.';
    }
}

function moveActor(
    array $game,
    string $room,
    array &$actor,
    int $dx,
    int $dy,
    bool $doorOpen
): void {
    if ($dx === 0 && $dy === 0) return;

    $map = $game[$room];
    $nx = $actor['x'] + $dx;
    $ny = $actor['y'] + $dy;

    if (!isset($map[$ny][$nx])) return;

    $tile = $map[$ny][$nx];
    if ($tile === '#') return;
    if ($tile === 'D' && !$doorOpen) return;

    $actor['x'] = $nx;
    $actor['y'] = $ny;
}

function doubleLockOpen(array $game): bool
{
    return
        actorOnTile($game['left'], $game['player'], 'A') &&
        actorOnTile($game['right'], $game['clone'], 'B');
}

function actorOnTile(array $map, array $actor, string $tile): bool
{
    return ($map[$actor['y']][$actor['x']] ?? '#') === $tile;
}

function cycleMirrorMode(array &$game): void
{
    $order = ['horizontal', 'vertical', 'direct', 'reverse'];
    $index = array_search($game['mode'], $order, true);
    $index = $index === false ? 0 : $index;
    $game['mode'] = $order[($index + 1) % count($order)];
    $game['message'] = 'Mirror mode changed to ' . mirrorModeLabel($game['mode']) . '.';
}

function deltaForAction(string $action): array
{
    return match ($action) {
        'up' => [0, -1],
        'down' => [0, 1],
        'left' => [-1, 0],
        'right' => [1, 0],
        default => [0, 0],
    };
}

function mirroredDelta(int $dx, int $dy, string $mode): array
{
    return match ($mode) {
        'horizontal' => [-$dx, $dy],
        'vertical' => [$dx, -$dy],
        'direct' => [$dx, $dy],
        'reverse' => [-$dx, -$dy],
        default => [-$dx, $dy],
    };
}

function mirrorModeLabel(string $mode): string
{
    return match ($mode) {
        'horizontal' => 'Horizontal',
        'vertical' => 'Vertical',
        'direct' => 'Direct Sync',
        'reverse' => 'Reverse Sync',
        default => 'Horizontal',
    };
}

function advanceLevel(array &$game): void
{
    if ($game['level'] >= MAX_LEVEL) {
        $game['status'] = 'won';
        $game['message'] = 'Both halves align. The mirror vault opens.';
        return;
    }

    $next = $game['level'] + 1;
    loadLevel($game, $next);
    $game['message'] = "Vault segment cleared. Level {$next}: {$game['level_name']}.";
}

function loseGame(array &$game, string $message): void
{
    $game['status'] = 'lost';
    $game['message'] = $message;
}

function tileView(array $game, string $room, int $x, int $y): array
{
    $actor = $room === 'left' ? $game['player'] : $game['clone'];

    if ($actor['x'] === $x && $actor['y'] === $y) {
        return [
            $room === 'left' ? 'player' : 'clone',
            $room === 'left' ? '●' : '○',
            $room === 'left' ? 'You' : 'Mirror self',
        ];
    }

    $tile = $game[$room][$y][$x];

    return match ($tile) {
        '#' => ['wall', '', 'Wall'],
        'A' => ['plate a', 'A', 'Left pressure plate'],
        'B' => ['plate b', 'B', 'Right pressure plate'],
        'D' => [doubleLockOpen($game) ? 'door open' : 'door', doubleLockOpen($game) ? '▱' : '▥', 'Vault door'],
        'M' => ['mirror-node', '◇', 'Mirror mode switch'],
        '^' => ['spike', '▲', 'Spike trap'],
        'E' => ['exit', '⇥', 'Exit'],
        default => ['floor', '', 'Floor'],
    };
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
