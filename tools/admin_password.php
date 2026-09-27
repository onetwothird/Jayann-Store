<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

require_once __DIR__ . '/../app/bootstrap.php';

$opts = getopt('', ['name::', 'password::', 'list', 'delete', 'yes', 'help']);

if (isset($opts['help'])) {
    echo <<<TXT

    Admin password tool

      --list                 Show every administrator
      --name USERNAME        The account to act on
      --password PASSWORD    New password (omit to be prompted, which is safer)
      --delete               Remove the account
      --yes                  Skip the confirmation prompt

    Examples
      php tools/admin_password.php --list
      php tools/admin_password.php --name admin
      php tools/admin_password.php --name admin --delete --yes

    TXT;
    echo "\n";
    exit(0);
}

$adminNames = $db->all('SELECT name FROM admin ORDER BY name');
$adminNames = array_column($adminNames, 'name');

function ask(string $question, bool $hidden = false): string
{
    echo $question;
    if ($hidden && DIRECTORY_SEPARATOR === '\\') {

        $value = trim((string) fgets(STDIN));
        echo "\n";
        return $value;
    }
    if ($hidden) {
        shell_exec('stty -echo 2>/dev/null');
    }
    $value = trim((string) fgets(STDIN));
    if ($hidden) {
        shell_exec('stty echo 2>/dev/null');
        echo "\n";
    }
    return $value;
}

function confirm(string $question): bool
{
    return in_array(strtolower(ask($question . ' [y/N] ')), ['y', 'yes'], true);
}

function out(string $message): void
{
    echo $message . "\n";
}

function fail(string $message): never
{
    fwrite(STDERR, 'Error: ' . $message . "\n");
    exit(1);
}

if (isset($opts['list'])) {
    if (!$adminNames) {
        out('No administrators exist yet. Create one with:');
        out('  php tools/admin_password.php --name admin');
        exit(0);
    }

    out('');
    out('  Administrators');
    out('  ' . str_repeat('-', 40));
    $rows = $db->all('SELECT id, name, password FROM admin ORDER BY name');
    foreach ($rows as $row) {
        $hashed = str_starts_with((string) $row['password'], '$');
        out(sprintf('  %-5s %-24s %s', '#' . $row['id'], $row['name'], $hashed ? 'bcrypt' : 'LEGACY - log in to upgrade'));
    }
    out('');
    exit(0);
}

$name = trim((string) ($opts['name'] ?? ''));
if ($name === '') {
    if (!$adminNames) {
        $name = ask('New administrator username: ');
    } else {
        out('Administrators: ' . implode(', ', $adminNames));
        $name = ask('Username: ');
    }
}

if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $name)) {
    fail('Username must be 3-60 characters: letters, numbers, dot, underscore or hyphen.');
}

$existing = $db->one('SELECT id FROM admin WHERE name = ?', [$name]);

if (isset($opts['delete'])) {
    if (!$existing) {
        fail('There is no administrator called "' . $name . '".');
    }
    if ($adminNames === [$name]) {
        fail('That is the only administrator. Create a replacement before deleting it.');
    }
    if (!isset($opts['yes']) && !confirm('Delete administrator "' . $name . '"?')) {
        out('Cancelled.');
        exit(0);
    }
    $db->run('DELETE FROM admin WHERE id = ?', [$existing['id']]);
    out('Deleted administrator "' . $name . '".');
    exit(0);
}

$password = (string) ($opts['password'] ?? '');
if ($password === '') {
    $password = ask('New password for "' . $name . '": ', true);
    $confirmValue = ask('Repeat password: ', true);
    if ($password !== $confirmValue) {
        fail('The two passwords did not match.');
    }
}

if (strlen($password) < 8) {
    fail('Use at least 8 characters.');
}
if (strlen($password) > 200) {
    fail('That password is too long (max 200 characters).');
}

$hash = hash_password($password);

if ($existing) {
    $db->run('UPDATE admin SET password = ?, name = ? WHERE id = ?', [$hash, $name, $existing['id']]);
    out('Reset the password for "' . $name . '".');
} else {
    $db->run('INSERT INTO admin (name, password) VALUES (?, ?)', [$name, $hash]);
    out('Created administrator "' . $name . '".');
}

out('Sign in at /admin/admin_login.php with that username and password.');
