<?php
/**
 * Checks ChatProjects Free and Pro against CONTRACT.md.
 *
 * Usage:
 *   php bin/contract-check.php --free=/path/to/chatprojects [--pro=/path/to/chatprojects-pro]
 *
 * With only --free, checks Free against the contract. With both, also checks
 * Pro and the cross-product rules. Exit code 0 = all checks pass.
 *
 * This file is byte-identical in both repositories (like CONTRACT.md).
 *
 * @package ChatProjects
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$opts = getopt('', array('free:', 'pro::'));
if (empty($opts['free'])) {
    fwrite(STDERR, "Usage: php bin/contract-check.php --free=PATH [--pro=PATH]\n");
    exit(2);
}
$free = rtrim($opts['free'], '/');
$pro  = isset($opts['pro']) && $opts['pro'] ? rtrim($opts['pro'], '/') : null;

$failures = 0;
function report($ok, $label, $detail = '') {
    global $failures;
    if (!$ok) {
        $failures++;
    }
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . $label . ('' !== $detail ? "\n      " . str_replace("\n", "\n      ", $detail) : '') . "\n";
}

function read_file($path) {
    if (!is_readable($path)) {
        return null;
    }
    return file_get_contents($path);
}

/**
 * Column/key definitions from a block of CREATE TABLE body lines.
 */
function parse_table_body($body) {
    $out = array('columns' => array(), 'keys' => array());
    foreach (preg_split('/\R/', $body) as $line) {
        $line = trim(preg_replace('/\s+/', ' ', $line));
        $line = rtrim($line, ',');
        if ('' === $line || 0 === strpos($line, '--')) {
            continue;
        }
        $line = str_replace('`', '', $line);
        // A default built from PHP code ("'" . esc_sql(...) . "'") can't be compared.
        $line = preg_replace("/'\" \. .*? \. \"'/", "'<php>'", $line);
        if (preg_match('/^PRIMARY KEY\s*(\(.*\))$/i', $line, $m)) {
            $out['keys']['PRIMARY'] = 'PRIMARY KEY ' . strtolower(str_replace(' ', '', $m[1]));
        } elseif (preg_match('/^(UNIQUE KEY|KEY|INDEX|UNIQUE INDEX|FULLTEXT KEY)\s+(\w+)\s*(\(.*\))$/i', $line, $m)) {
            $out['keys'][strtolower($m[2])] = strtoupper($m[1]) . ' ' . strtolower($m[2]) . ' ' . strtolower(str_replace(' ', '', $m[3]));
        } elseif (preg_match('/^(\w+)\s+(.+)$/', $line, $m)) {
            $out['columns'][strtolower($m[1])] = strtolower($m[2]);
        }
    }
    return $out;
}

/**
 * Table a CREATE TABLE statement creates, from its name token
 * ("{$chats_table}", "$this->chats_table", "{$wpdb->prefix}chatprojects_chats").
 * A variable resolves to its nearest preceding assignment; null if the
 * statement doesn't create one of $tables.
 */
function resolve_created_table($src, $token, $offset, array $tables) {
    if (preg_match('/(chatprojects_\w+)/', $token, $lit)) {
        $name = $lit[1];
    } elseif (preg_match('/\$(?:this->)?(\w+)/', $token, $var)
        && preg_match_all('/\$(?:this->)?' . preg_quote($var[1], '/') . '\s*=[^;]*?[\'"](chatprojects_\w+)[\'"]/', substr($src, 0, $offset), $assign)) {
        $name = end($assign[1]);
    } else {
        return null;
    }
    return in_array($name, $tables, true) ? $name : null;
}

/**
 * Shared-table DDL declared in a plugin's installer. Tables created with
 * CREATE TABLE IF NOT EXISTS are listed in $if_not_exists.
 */
function installer_tables($installer_src, array $tables, &$if_not_exists = array()) {
    $found         = array();
    $if_not_exists = array();
    if (!preg_match_all('/CREATE\s+TABLE(\s+IF\s+NOT\s+EXISTS)?\s+([^\s(]+)\s*\(/i', $installer_src, $mm, PREG_OFFSET_CAPTURE)) {
        return $found;
    }
    foreach ($mm[0] as $i => $match) {
        $table = resolve_created_table($installer_src, $mm[2][$i][0], $match[1], $tables);
        if (null === $table) {
            continue;
        }
        if ('' !== $mm[1][$i][0]) {
            $if_not_exists[] = $table;
        }
        // Body runs to the ") $charset_collate" / ") {$charset_collate}" that closes it.
        $start = $match[1] + strlen($match[0]);
        if (!preg_match('/\)\s*\{?\$charset_collate/', $installer_src, $end, PREG_OFFSET_CAPTURE, $start)) {
            continue;
        }
        $found[$table] = parse_table_body(substr($installer_src, $start, $end[0][1] - $start));
    }
    return $found;
}

/**
 * Canonical shared-table definitions from CONTRACT.md.
 */
function contract_tables($contract) {
    $tables = array();
    if (!preg_match('/```sql\R(.*?)```/s', $contract, $m)) {
        return $tables;
    }
    foreach (preg_split('/^-- \{prefix\}(chatprojects_\w+)\s*$/m', $m[1], -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $part) {
        if ($i % 2 === 1) {
            $name = $part;
        } elseif ($i > 0) {
            $tables[$name] = parse_table_body($part);
        }
    }
    return $tables;
}

function compare_to_contract($product, array $declared, array $canonical) {
    foreach ($canonical as $table => $spec) {
        if (!isset($declared[$table])) {
            report(false, "$product declares shared table $table");
            continue;
        }
        $problems = array();
        foreach (array('columns', 'keys') as $kind) {
            foreach ($spec[$kind] as $name => $def) {
                $got = $declared[$table][$kind][$name] ?? null;
                if (null === $got) {
                    $problems[] = "missing $name";
                } elseif ($got !== $def) {
                    $problems[] = "$name: has \"$got\", contract says \"$def\"";
                }
            }
        }
        report(empty($problems), "$product $table matches the contract", implode("\n", $problems));
    }
}

/**
 * Top-level function names declared in PHP files.
 */
function top_level_functions(array $files) {
    $names = array();
    foreach ($files as $file) {
        $src = read_file($file);
        if (null === $src) {
            continue;
        }
        $depth = 0;
        $tokens = token_get_all($src);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $tok = $tokens[$i];
            if ('{' === $tok || (is_array($tok) && in_array($tok[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
                $depth++;
            } elseif ('}' === $tok) {
                $depth--;
            } elseif (is_array($tok) && T_FUNCTION === $tok[0] && 0 === $depth) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && T_STRING === $tokens[$j][0]) {
                        $names[] = strtolower($tokens[$j][1]);
                        break;
                    }
                    if ('(' === $tokens[$j]) {
                        break; // Closure.
                    }
                }
            }
        }
    }
    return array_values(array_unique($names));
}

/**
 * Chat catalogue + legacy map from a plugin's Model_Registry (run in its own process).
 */
function model_registry_data($registry_file) {
    $stub = '<?php namespace { '
        . 'if (!defined("ABSPATH")) define("ABSPATH", "/"); '
        . 'function apply_filters($t, $v) { return $v; } function __($s) { return $s; } function esc_html__($s) { return $s; } '
        . 'function did_action($a) { return 0; } function wp_parse_args($a, $d = array()) { return array_merge((array) $d, (array) $a); } '
        . 'function sanitize_key($k) { return strtolower(preg_replace("/[^a-z0-9_\\-]/i", "", $k)); } } '
        . 'namespace { require ' . var_export($registry_file, true) . '; '
        . '$r = new ReflectionClass("ChatProjects\\\\Model_Registry"); $d = $r->getMethod("definition"); $d->setAccessible(true); '
        . 'echo json_encode(array("definition" => $d->invoke(null), "legacy" => ChatProjects\\Model_Registry::get_legacy_map())); }';
    $tmp = tempnam(sys_get_temp_dir(), 'cpcc');
    file_put_contents($tmp, $stub);
    $json = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>/dev/null');
    unlink($tmp);
    return json_decode((string) $json, true);
}

// ---------------------------------------------------------------------------

$contract = read_file("$free/CONTRACT.md");
report(null !== $contract, 'Free has CONTRACT.md');
if (null === $contract) {
    exit(1);
}
$canonical = contract_tables($contract);
report(!empty($canonical), 'CONTRACT.md defines the shared tables', implode(', ', array_keys($canonical)));

$products = array('Free' => $free);
if ($pro) {
    $products['Pro'] = $pro;
    $pro_contract = read_file("$pro/CONTRACT.md");
    report(null !== $pro_contract && hash('sha256', $pro_contract) === hash('sha256', $contract), 'CONTRACT.md is byte-identical in Free and Pro');
    $pro_checker = read_file("$pro/bin/contract-check.php");
    report(null !== $pro_checker && hash('sha256', $pro_checker) === hash_file('sha256', __FILE__), 'bin/contract-check.php is byte-identical in Free and Pro');
}

// Shared table DDL.
foreach ($products as $name => $dir) {
    $src = read_file("$dir/includes/class-installer.php");
    report(null !== $src, "$name has includes/class-installer.php");
    if (null !== $src) {
        $declared = installer_tables($src, array_keys($canonical), $if_not_exists);
        compare_to_contract($name, $declared, $canonical);
        report(!empty($declared) && empty($if_not_exists), "$name creates shared tables with dbDelta (no IF NOT EXISTS)", $if_not_exists ? 'IF NOT EXISTS on: ' . implode(', ', array_unique($if_not_exists)) : '');
    }
}

// Schema version options.
$free_src = implode("\n", array_filter(array(read_file("$free/includes/class-installer.php"), read_file("$free/chatprojects.php"), read_file("$free/includes/bootstrap.php"))));
report(!preg_match("/update_option\(\s*'chatprojects_db_version'/", $free_src), 'Free never writes chatprojects_db_version');
if ($pro) {
    $pro_src = implode("\n", array_filter(array(read_file("$pro/includes/class-installer.php"), read_file("$pro/chatprojects.php"))));
    report(false === strpos($pro_src, 'chatprojects_free_db_version'), 'Pro never touches chatprojects_free_db_version');
}

// Encrypted keys.
foreach ($products as $name => $dir) {
    $sec = (string) read_file("$dir/includes/class-security.php");
    report(false !== strpos($sec, "'cpv2:'") && false !== strpos($sec, 'chatprojects_v2|') && false !== strpos($sec, 'sodium_crypto_secretbox_open'), "$name reads and writes the cpv2 key format");
    report((bool) preg_match('/ABSPATH\s*\.\s*\(?\s*(\$db_name|DB_NAME|defined\(\s*\'DB_NAME\'\s*\))/', $sec), "$name uses the ABSPATH . DB_NAME last-resort seed");
}

// Co-install.
$free_main = (string) read_file("$free/chatprojects.php");
report(array() === top_level_functions(array("$free/chatprojects.php")), "Free's main file declares no functions (they live in includes/bootstrap.php)");
report((int) strpos($free_main, "defined('CHATPROJECTS_PRO_VERSION')") > 0 && strpos($free_main, "defined('CHATPROJECTS_PRO_VERSION')") < strpos($free_main, 'define('), "Free's main file stands down for Pro before defining anything");
if ($pro) {
    $clash = array_intersect(
        top_level_functions(array("$free/chatprojects.php", "$free/includes/bootstrap.php")),
        top_level_functions(array("$pro/chatprojects.php"))
    );
    report(empty($clash), 'Free and Pro declare no common global functions', implode(', ', $clash));
}

// Uninstall guards.
report(false !== strpos((string) read_file("$free/uninstall.php"), 'chatprojects-pro/'), "Free's uninstall leaves shared data alone when Pro is installed");
if ($pro) {
    report(false !== strpos((string) read_file("$pro/uninstall.php"), 'chatprojects/chatprojects.php'), "Pro's uninstall leaves shared data alone when Free is installed");
    report(!preg_match("/LIKE\s+'%chatprojects/i", (string) read_file("$pro/uninstall.php")), "Pro's uninstall uses anchored LIKE patterns");
}
report(!preg_match("/LIKE\s+'%chatprojects/i", (string) read_file("$free/uninstall.php")), "Free's uninstall uses anchored LIKE patterns");

// Model catalogue.
$free_models = model_registry_data("$free/includes/class-model-registry.php");
report(is_array($free_models), "Free's model registry can be read");
if ($pro && is_array($free_models)) {
    $pro_models = model_registry_data("$pro/includes/class-model-registry.php");
    report(is_array($pro_models), "Pro's model registry can be read");
    if (is_array($pro_models)) {
        $problems = array();
        foreach (array('openai', 'anthropic', 'gemini', 'chutes', 'openrouter') as $provider) {
            $f = $free_models['definition'][$provider] ?? array();
            $p = $pro_models['definition'][$provider] ?? array();
            $missing = array_diff(array_keys($f), array_keys($p));
            $extra   = array_diff(array_keys($p), array_keys($f));
            if ($missing) {
                $problems[] = "$provider: Pro lacks " . implode(', ', $missing);
            }
            if ($extra) {
                $problems[] = "$provider: Pro has extra " . implode(', ', $extra);
            }
            foreach ($f as $id => $entry) {
                if (!isset($p[$id])) {
                    continue;
                }
                foreach ($entry as $key => $value) {
                    if (!array_key_exists($key, $p[$id]) || $p[$id][$key] !== $value) {
                        $problems[] = "$provider/$id: '$key' differs";
                    }
                }
            }
        }
        report(empty($problems), 'Chat model catalogue matches (keys Free declares)', implode("\n", array_slice($problems, 0, 20)));
        $legacy_problems = array();
        foreach ($free_models['legacy'] as $old => $new) {
            if (($pro_models['legacy'][$old] ?? null) !== $new) {
                $legacy_problems[] = "$old => $new";
            }
        }
        report(empty($legacy_problems), "Pro's legacy remap table includes Free's", implode("\n", array_slice($legacy_problems, 0, 20)));
    }
}

echo "\n" . (0 === $failures ? 'Contract check passed.' : "$failures check(s) failed.") . "\n";
exit(0 === $failures ? 0 : 1);
