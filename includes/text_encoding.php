<?php

function mojibake_marker_score($value)
{
    $value = (string) $value;
    $markers = [
        'Ã',
        'Â',
        'â€',
        'â€“',
        'â€”',
        'â€˜',
        'â€™',
        'â€œ',
        'â€',
        'â€¢',
        'â€¦',
        'â„¢',
        'ï¿½',
        '�',
    ];

    $score = 0;

    foreach ($markers as $marker) {
        $score += substr_count($value, $marker);
    }

    return $score;
}

function repair_text_mojibake($value)
{
    if (!is_string($value) || $value === '') {
        return $value;
    }

    $current = $value;

    for ($pass = 0; $pass < 4; $pass++) {
        $score = mojibake_marker_score($current);

        if ($score === 0) {
            break;
        }

        $candidate = @mb_convert_encoding($current, 'Windows-1252', 'UTF-8');

        if (!is_string($candidate) || $candidate === $current || !mb_check_encoding($candidate, 'UTF-8')) {
            break;
        }

        if (mojibake_marker_score($candidate) > $score) {
            break;
        }

        $current = $candidate;
    }

    return $current;
}

function match_text_case($source, $replacement)
{
    if ($source === mb_strtoupper($source, 'UTF-8')) {
        return mb_strtoupper($replacement, 'UTF-8');
    }

    if ($source === mb_strtolower($source, 'UTF-8')) {
        return mb_strtolower($replacement, 'UTF-8');
    }

    $first = mb_substr($source, 0, 1, 'UTF-8');
    $rest = mb_substr($source, 1, null, 'UTF-8');

    if ($first === mb_strtoupper($first, 'UTF-8') && $rest === mb_strtolower($rest, 'UTF-8')) {
        return mb_strtoupper(mb_substr($replacement, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($replacement, 1, null, 'UTF-8');
    }

    if ($source === mb_convert_case($source, MB_CASE_TITLE, 'UTF-8')) {
        return mb_convert_case($replacement, MB_CASE_TITLE, 'UTF-8');
    }

    return $replacement;
}

function repair_known_legacy_placeholders($value)
{
    if (!is_string($value) || strpos($value, '?') === false) {
        return $value;
    }

    $patterns = [
        '/para\?aque/iu' => 'parañaque',
        '/dasmari\?as/iu' => 'dasmariñas',
        '/las pi\?as/iu' => 'las piñas',
        '/los ba\?os/iu' => 'los baños',
        '/bi\?an/iu' => 'biñan',
    ];

    foreach ($patterns as $pattern => $replacement) {
        $value = preg_replace_callback($pattern, function ($matches) use ($replacement) {
            return match_text_case($matches[0], $replacement);
        }, $value);
    }

    return $value;
}

function repair_legacy_text_encoding($value)
{
    return repair_known_legacy_placeholders(repair_text_mojibake($value));
}
