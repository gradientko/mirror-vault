# mirror-vault

A mirrored dual-room puzzle game written in pure PHP.

There is **no JavaScript**.

You control two characters at the same time: yourself in the left room and a mirror self in the right room. A single input moves both, but the mirror movement depends on the current reflection mode.

## Mirror modes

```text
Horizontal   → becomes ←
Vertical     ↑ becomes ↓
Direct Sync  → stays →
Reverse Sync both axes are inverted
```

Later levels contain mirror nodes that cycle the active rule.

## Features

- pure PHP
- no JavaScript
- two rooms rendered side by side
- simultaneous player + mirror movement
- four mirror modes
- non-symmetrical walls
- double pressure plates
- shared vault doors
- spike traps
- mirror-mode nodes
- synchronized exits
- 5 handcrafted levels
- PHP session statistics
- best completed run
- CSRF protection
- POST / Redirect / GET
- no framework
- no database

## Goal

Both characters must reach their own exit on the **same turn**.

One arriving early is not enough.

## Double lock

Some levels contain:

```text
A  left pressure plate
B  right pressure plate
D  shared door
```

The door opens only when you stand on A while the mirror self stands on B at the same time.

## Mirror nodes

Mirror nodes are shown as:

```text
◇
```

Stepping on one cycles the mirror rule:

```text
Horizontal
↓
Vertical
↓
Direct Sync
↓
Reverse Sync
↓
Horizontal
```

## Spike traps

```text
▲
```

If either character steps on one, the run ends.

## Levels

```text
1. Reflection
2. Split Walls
3. Double Lock
4. Broken Mirror
5. The Vault
```

## Requirements

PHP 8.1+

## Run

```bash
php -S localhost:8000
```

Then open:

```text
http://localhost:8000
```

## Architecture

The entire state is stored in:

```php
$_SESSION['mirror_vault_game']
```

Each move is handled server-side:

```text
POST
↓
player delta
↓
mirror delta
↓
move both actors
↓
process plates / doors / traps / mirror nodes
↓
check synchronized exits
↓
session
↓
redirect
```

## Project structure

```text
mirror-vault/
├── index.php
├── functions.php
├── style.css
├── README.md
└── LICENSE
```

## GitHub Pages

GitHub Pages cannot execute PHP. Use PHP-capable hosting or PHP's built-in server.

## License

MIT
