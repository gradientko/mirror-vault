<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['mirror_vault_stats'])) {
    $_SESSION['mirror_vault_stats'] = [
        'runs' => 0,
        'wins' => 0,
        'best_level' => 1,
        'best_turns' => null,
    ];
}

if (!isset($_SESSION['mirror_vault_game'])) {
    $_SESSION['mirror_vault_game'] = createGame();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request token.';
    } else {
        $game = $_SESSION['mirror_vault_game'];
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'new') {
            $_SESSION['mirror_vault_game'] = createGame();
            header('Location: index.php');
            exit;
        }

        if ($game['status'] === 'playing') {
            performTurn($game, $action);

            $_SESSION['mirror_vault_stats']['best_level'] = max(
                $_SESSION['mirror_vault_stats']['best_level'],
                $game['level']
            );

            if ($game['status'] !== 'playing' && empty($game['settled'])) {
                $_SESSION['mirror_vault_stats']['runs']++;

                if ($game['status'] === 'won') {
                    $_SESSION['mirror_vault_stats']['wins']++;
                    $best = $_SESSION['mirror_vault_stats']['best_turns'];

                    if ($best === null || $game['turns'] < $best) {
                        $_SESSION['mirror_vault_stats']['best_turns'] = $game['turns'];
                    }
                }

                $game['settled'] = true;
            }

            $_SESSION['mirror_vault_game'] = $game;
        }

        header('Location: index.php');
        exit;
    }
}

$game = $_SESSION['mirror_vault_game'];
$stats = $_SESSION['mirror_vault_stats'];
$height = count($game['left']);
$width = count($game['left'][0]);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Mirror Vault — Pure PHP</title>
  <meta name="description" content="A mirrored dual-room puzzle game written in pure PHP.">
  <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="shell">
  <header class="hero">
    <div>
      <span class="eyebrow">PURE PHP MIRROR PUZZLE</span>
      <h1>One move. Two consequences.</h1>
      <p>
        Control yourself and a mirrored copy at the same time. The rooms differ,
        the exits must be reached together, and later levels let you change the mirror rule itself.
      </p>
    </div>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="new">
      <button class="text-button" type="submit">New run</button>
    </form>
  </header>

  <section class="stats">
    <div><span>Level</span><strong><?= (int)$game['level'] ?>/<?= MAX_LEVEL ?></strong></div>
    <div><span>Turns</span><strong><?= (int)$game['turns'] ?></strong></div>
    <div><span>Mirror</span><strong><?= e(mirrorModeLabel($game['mode'])) ?></strong></div>
    <div><span>Door</span><strong><?= doubleLockOpen($game) ? 'OPEN' : 'LOCKED' ?></strong></div>
  </section>

  <section class="layout">
    <section class="game-card">
      <div class="game-head">
        <div>
          <span class="eyebrow">LEVEL <?= (int)$game['level'] ?> · <?= e($game['level_name']) ?></span>
          <h2><?= e($game['message']) ?></h2>
        </div>

        <span class="status-pill <?= e($game['status']) ?>">
          <?= strtoupper(e($game['status'])) ?>
        </span>
      </div>

      <?php if ($error !== null): ?>
        <div class="notice"><?= e($error) ?></div>
      <?php endif; ?>

      <section class="rooms">
        <div class="room">
          <div class="room-head">
            <span class="eyebrow">LEFT ROOM</span>
            <strong>YOU</strong>
          </div>

          <div class="map" style="--cols: <?= $width ?>;">
            <?php for ($y = 0; $y < $height; $y++): ?>
              <?php for ($x = 0; $x < $width; $x++): ?>
                <?php [$class, $glyph, $label] = tileView($game, 'left', $x, $y); ?>
                <div class="tile <?= e($class) ?>" title="<?= e($label) ?>">
                  <?= e($glyph) ?>
                </div>
              <?php endfor; ?>
            <?php endfor; ?>
          </div>
        </div>

        <div class="mirror-divider">
          <span>◈</span>
          <small><?= e(mirrorModeLabel($game['mode'])) ?></small>
        </div>

        <div class="room">
          <div class="room-head">
            <span class="eyebrow">MIRROR ROOM</span>
            <strong>ECHO</strong>
          </div>

          <div class="map" style="--cols: <?= $width ?>;">
            <?php for ($y = 0; $y < $height; $y++): ?>
              <?php for ($x = 0; $x < $width; $x++): ?>
                <?php [$class, $glyph, $label] = tileView($game, 'right', $x, $y); ?>
                <div class="tile <?= e($class) ?>" title="<?= e($label) ?>">
                  <?= e($glyph) ?>
                </div>
              <?php endfor; ?>
            <?php endfor; ?>
          </div>
        </div>
      </section>

      <?php if ($game['status'] === 'playing'): ?>
        <section class="controls">
          <div class="pad">
            <span></span>
            <?= actionButton('up', '↑', 'Move up') ?>
            <span></span>
            <?= actionButton('left', '←', 'Move left') ?>
            <?= actionButton('down', '↓', 'Move down') ?>
            <?= actionButton('right', '→', 'Move right') ?>
          </div>

          <form method="post" class="wait-form">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="wait">
            <button type="submit">
              Wait one turn
              <span>Both selves remain in place</span>
            </button>
          </form>
        </section>
      <?php else: ?>
        <form method="post" class="again">
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="action" value="new">
          <button type="submit"><?= $game['status'] === 'won' ? 'Run again' : 'Retry vault' ?></button>
        </form>
      <?php endif; ?>
    </section>

    <aside class="side">
      <section class="panel">
        <span class="eyebrow">MIRROR RULE</span>
        <div class="mode-card">
          <strong><?= e(mirrorModeLabel($game['mode'])) ?></strong>
          <code><?= e(modeExample($game['mode'])) ?></code>
        </div>
      </section>

      <section class="legend">
        <div><i class="player-mark">●</i><span>You</span></div>
        <div><i class="clone-mark">○</i><span>Mirror self</span></div>
        <div><i class="plate-mark">A</i><span>Pressure plate A</span></div>
        <div><i class="plate-mark">B</i><span>Pressure plate B</span></div>
        <div><i class="door-mark">▥</i><span>Vault door</span></div>
        <div><i class="mirror-mark">◇</i><span>Mirror mode node</span></div>
        <div><i class="spike-mark">▲</i><span>Spike trap</span></div>
        <div><i class="exit-mark">⇥</i><span>Exit</span></div>
      </section>

      <section class="panel">
        <span class="eyebrow">DOUBLE LOCK</span>
        <p>
          Doors open only when you stand on A in the left room while your mirror
          stands on B in the right room at the same time.
        </p>
      </section>

      <section class="panel">
        <span class="eyebrow">RECORDS</span>
        <dl>
          <div><dt>Runs</dt><dd><?= (int)$stats['runs'] ?></dd></div>
          <div><dt>Wins</dt><dd><?= (int)$stats['wins'] ?></dd></div>
          <div><dt>Best level</dt><dd><?= (int)$stats['best_level'] ?></dd></div>
          <div><dt>Best turns</dt><dd><?= $stats['best_turns'] ?? '—' ?></dd></div>
        </dl>
      </section>

      <section class="panel">
        <span class="eyebrow">PURE SERVER SIDE</span>
        <p>
          No JavaScript. Every move, mirrored delta, pressure plate, door, trap,
          mirror-mode change and win check is calculated in PHP.
        </p>
      </section>
    </aside>
  </section>

  <footer>
    <span>PHP sessions · mirrored movement · dual-room puzzles · no JavaScript</span>
    <span>mirror-vault</span>
  </footer>
</main>
</body>
</html>
<?php
function actionButton(string $action, string $label, string $aria): string
{
    $token = e($_SESSION['csrf_token']);

    return <<<HTML
<form method="post">
  <input type="hidden" name="csrf_token" value="{$token}">
  <input type="hidden" name="action" value="{$action}">
  <button type="submit" aria-label="{$aria}">{$label}</button>
</form>
HTML;
}

function modeExample(string $mode): string
{
    return match ($mode) {
        'horizontal' => '→ becomes ←',
        'vertical' => '↑ becomes ↓',
        'direct' => '→ stays →',
        'reverse' => '↑ becomes ↓, → becomes ←',
        default => '→ becomes ←',
    };
}
