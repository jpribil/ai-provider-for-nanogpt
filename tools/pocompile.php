<?php

/**
 * Minimal .po -> .mo compiler (no gettext dependency).
 *
 * Usage: php tools/pocompile.php <file1.po> [file2.po ...]
 * Writes a sibling .mo next to each .po. Skips untranslated entries.
 */

declare(strict_types=1);

/**
 * Unescapes a PO double-quoted string body.
 */
function po_unescape(string $s): string
{
    $out = '';
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $ch = $s[$i];
        if ($ch === '\\' && $i + 1 < $len) {
            $n = $s[$i + 1];
            switch ($n) {
                case 'n': $out .= "\n"; break;
                case 't': $out .= "\t"; break;
                case 'r': $out .= "\r"; break;
                case '"': $out .= '"'; break;
                case '\\': $out .= '\\'; break;
                default: $out .= $n;
            }
            $i++;
        } else {
            $out .= $ch;
        }
    }
    return $out;
}

/**
 * Extracts the body between the first and last double quote on a line.
 */
function po_quoted(string $line): string
{
    $first = strpos($line, '"');
    $last = strrpos($line, '"');
    if ($first === false || $last === false || $last <= $first) {
        return '';
    }
    return substr($line, $first + 1, $last - $first - 1);
}

/**
 * Parses a .po file into [msgid => msgstr].
 *
 * @return array<string, string>
 */
function po_parse(string $path): array
{
    $entries = [];
    $msgid = null;
    $msgstr = '';
    $mode = null;

    $flush = static function () use (&$entries, &$msgid, &$msgstr) {
        if ($msgid !== null && ($msgid === '' || $msgstr !== '')) {
            $entries[$msgid] = $msgstr;
        }
        $msgid = null;
        $msgstr = '';
    };

    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        if ($line === '') {
            $flush();
            $mode = null;
            continue;
        }
        if ($line[0] === '#') {
            continue;
        }
        if (strncmp($line, 'msgid ', 6) === 0) {
            if ($msgid !== null) {
                $flush();
            }
            $msgid = po_unescape(po_quoted($line));
            $mode = 'id';
        } elseif (strncmp($line, 'msgstr ', 7) === 0) {
            $msgstr = po_unescape(po_quoted($line));
            $mode = 'str';
        } elseif ($line[0] === '"') {
            $chunk = po_unescape(po_quoted($line));
            if ($mode === 'id') {
                $msgid .= $chunk;
            } elseif ($mode === 'str') {
                $msgstr .= $chunk;
            }
        }
    }
    $flush();

    return $entries;
}

/**
 * Compiles [msgid => msgstr] into binary .mo content.
 *
 * @param array<string, string> $entries
 */
function mo_build(array $entries): string
{
    ksort($entries, SORT_STRING);
    $keys = array_keys($entries);
    $n = count($keys);

    $idData = '';
    $strData = '';
    $idMeta = [];
    $strMeta = [];

    foreach ($keys as $k) {
        $v = $entries[$k];
        $idMeta[] = [strlen($k), strlen($idData)];
        $idData .= $k . "\0";
        $strMeta[] = [strlen($v), strlen($strData)];
        $strData .= $v . "\0";
    }

    $headerSize = 28;
    $idBase = $headerSize + 16 * $n; // originals table (8n) + translations table (8n)
    $strBase = $idBase + strlen($idData);

    $out = pack(
        'V7',
        0x950412de, // magic
        0,          // revision
        $n,         // number of strings
        $headerSize,            // O: originals table offset
        $headerSize + 8 * $n,   // T: translations table offset
        0,                      // S: hash table size
        $headerSize + 16 * $n   // H: hash table offset (empty)
    );

    foreach ($idMeta as [$len, $off]) {
        $out .= pack('VV', $len, $idBase + $off);
    }
    foreach ($strMeta as [$len, $off]) {
        $out .= pack('VV', $len, $strBase + $off);
    }
    $out .= $idData . $strData;

    return $out;
}

$files = array_slice($argv, 1);
if ($files === []) {
    fwrite(STDERR, "Usage: php pocompile.php <file.po> ...\n");
    exit(1);
}

foreach ($files as $po) {
    $entries = po_parse($po);
    $mo = preg_replace('/\.po$/', '.mo', $po);
    file_put_contents($mo, mo_build($entries));
    printf("%s -> %s (%d entries)\n", basename($po), basename($mo), count($entries));
}
