<?php
/**
 * Minimal WordPress-style hook system: add_action/do_action for side effects,
 * add_filter/apply_filters for value transformation. Every plugin registers
 * itself purely through these two mechanisms — the core never has
 * plugin-specific code in it, mirroring how wp-includes/plugin.php works.
 */

$GLOBALS['__hooks_actions'] = [];
$GLOBALS['__hooks_filters'] = [];

function add_action(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['__hooks_actions'][$hook][$priority][] = $callback;
}

function do_action(string $hook, ...$args): void
{
    if (empty($GLOBALS['__hooks_actions'][$hook])) {
        return;
    }
    ksort($GLOBALS['__hooks_actions'][$hook]);
    foreach ($GLOBALS['__hooks_actions'][$hook] as $callbacks) {
        foreach ($callbacks as $cb) {
            call_user_func_array($cb, $args);
        }
    }
}

function add_filter(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['__hooks_filters'][$hook][$priority][] = $callback;
}

function apply_filters(string $hook, $value, ...$args)
{
    if (empty($GLOBALS['__hooks_filters'][$hook])) {
        return $value;
    }
    ksort($GLOBALS['__hooks_filters'][$hook]);
    foreach ($GLOBALS['__hooks_filters'][$hook] as $callbacks) {
        foreach ($callbacks as $cb) {
            $value = call_user_func_array($cb, array_merge([$value], $args));
        }
    }
    return $value;
}

function has_action(string $hook): bool
{
    return !empty($GLOBALS['__hooks_actions'][$hook]);
}
