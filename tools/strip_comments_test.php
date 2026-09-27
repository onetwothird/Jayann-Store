<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/strip_comments.php';
ob_end_clean();

foreach (['strip', 'tidy'] as $fn) {
    if (!function_exists($fn)) {
        fwrite(STDERR, "could not load {$fn}() from strip_comments.php\n");
        exit(1);
    }
}

$cases = [

    [
        'php',
        "<?php\n// a line comment\n\$a = 1; // trailing\n/* a block\n   comment */\n\$b = 2;\n",
        "<?php\n\n\$a = 1; \n\n\$b = 2;\n",
        'line and block comments go, code stays',
    ],
    [
        'php',
        "<?php\n\$n = 5; # hash comment\n",
        "<?php\n\$n = 5; \n",
        'a hash comment goes',
    ],
    [
        'php',
        "<?php\n#[AllowDynamicProperties]\nclass A {}\n",
        "<?php\n#[AllowDynamicProperties]\nclass A {}\n",
        'an attribute is not a hash comment',
    ],

    [
        'php',
        "<?php\n\$s = 'http://example.com/a//b';\n",
        "<?php\n\$s = 'http://example.com/a//b';\n",
        'a // inside a single-quoted string is not a comment',
    ],
    [
        'php',
        "<?php\n\$s = \"/* not a comment */\";\n",
        "<?php\n\$s = \"/* not a comment */\";\n",
        'a block marker inside a double-quoted string is not a comment',
    ],
    [
        'php',
        "<?php\n\$s = 'it\\'s /* fine */';\n",
        "<?php\n\$s = 'it\\'s /* fine */';\n",
        'an escaped quote does not end the string early',
    ],
    [
        'php',
        "<?php\n\$s = \"a \\\\\" ; // gone\n",
        "<?php\n\$s = \"a \\\\\" ; \n",
        'an escaped backslash does not escape the closing quote',
    ],

    [
        'php',
        "<?php\n\$s = <<<TXT\nline // not a comment\n/* also not */\nTXT;\n",
        "<?php\n\$s = <<<TXT\nline // not a comment\n/* also not */\nTXT;\n",
        'heredoc body is preserved verbatim',
    ],
    [
        'php',
        "<?php\n\$s = <<<'TXT'\n// still body\nTXT;\n",
        "<?php\n\$s = <<<'TXT'\n// still body\nTXT;\n",
        'nowdoc body is preserved verbatim',
    ],
    [
        'php',
        "<?php\n\$s = <<<TXT\na // b\nTXT;\n\$after = 1;\n",
        "<?php\n\$s = <<<TXT\na // b\nTXT;\n\$after = 1;\n",
        'code after a heredoc is still parsed, with a semicolon on the label',
    ],
    [
        'php',
        "<?php\n\$h = <<<SQL\nSELECT 1\nSQL;\n// gone\n",
        "<?php\n\$h = <<<SQL\nSELECT 1\nSQL;\n\n",
        'comment after a heredoc is removed, leaving the newline it ended on',
    ],
    [
        'php',
        "<?php\n\$a = <<<A\nfirst // one\nA;\n\$b = <<<B\nsecond // two\nB;\n// gone\n",
        "<?php\n\$a = <<<A\nfirst // one\nA;\n\$b = <<<B\nsecond // two\nB;\n\n",
        'two heredocs in a row, comment after the second',
    ],
    [
        'php',
        "<?php\necho \$a <=< \$b; // shift, not heredoc\n",
        "<?php\necho \$a <=< \$b; \n",
        '<< with no label is not a heredoc, the comment still goes',
    ],

    [
        'css',
        "/* header */\n.a { color: red; } /* trailing */\n",
        "\n.a { color: red; } \n",
        'css block comments go, declarations stay',
    ],
    [
        'css',
        ".a { background: url(data:image/svg+xml;base64,AAA); }\n",
        ".a { background: url(data:image/svg+xml;base64,AAA); }\n",
        'a data URI is untouched',
    ],
    [
        'css',
        "@media (max-width: 600px) { /* note */ .a { color: red; } }\n",
        "@media (max-width: 600px) {  .a { color: red; } }\n",
        'a comment inside a media block goes',
    ],
    [
        'css',
        ".a { content: \"/*\"; }\n",
        ".a { content: \"/*\"; }\n",
        'a quoted marker in CSS is untouched',
    ],
    [
        'css',
        "/* one */\n.a {}\n/* two */\n.b {}\n/* three */\n",
        "\n.a {}\n\n.b {}\n\n",
        'whole-sheet comment banners go, rules stay',
    ],

    [
        'js',
        "/* head */\nvar a = 1; // tail\n",
        "\nvar a = 1; \n",
        'js line and block comments go',
    ],
    [
        'js',
        "var s = 'http://x.test/a//b';\n",
        "var s = 'http://x.test/a//b';\n",
        'a // inside a js string is not a comment',
    ],
    [
        'js',
        "var s = \"a /* b */ c\";\n",
        "var s = \"a /* b */ c\";\n",
        'a block marker inside a js string is not a comment',
    ],
    [
        'js',
        "var re = /\\/\\*not a comment\\*\\//;\nvar n = 10 / 2;\n",
        "var re = /\\/\\*not a comment\\*\\//;\nvar n = 10 / 2;\n",
        'a regex literal is not a comment, and division still works',
    ],
    [
        'js',
        "var t = `a // b \${x} /* c */`;\n",
        "var t = `a // b \${x} /* c */`;\n",
        'template literal contents are untouched',
    ],

    ['tidy', "<?php\n\n\n\n\$a = 1;   \n\t\n", "<?php\n\n\$a = 1;\n", 'blank runs collapse and trailing space goes'],
    ['tidy', "<?php\n\n\$a = 1;\n", "<?php\n\n\$a = 1;\n", 'a single blank line is left alone'],
    ['tidy', "<?php\n\$a = 1;   \n", "<?php\n\$a = 1;\n", 'trailing spaces on a code line go'],
];

$pass = 0;
$fail = 0;

foreach ($cases as $n => $case) {
    [$lang, $in, $want, $why] = $case;

    if ($lang === 'tidy') {
        $got = tidy($in);
    } else {
        [$got] = strip($in, $lang, $lang === 'php');
    }

    if ($got === $want) {
        $pass++;
        continue;
    }

    $fail++;
    printf("FAIL %d: %s\n", $n + 1, $why);
    printf("  input:    %s\n", json_encode($in));
    printf("  expected: %s\n", json_encode($want));
    printf("  actual:   %s\n", json_encode($got));
}

printf(
    "\n%d passed, %d failed, %d total\n",
    $pass,
    $fail,
    count($cases)
);

exit($fail === 0 ? 0 : 1);
