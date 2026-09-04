<?php

namespace CodeIgniter\HTTP\Files;

if (ENVIRONMENT !== 'testing') {
    return;
}

if (! function_exists(__NAMESPACE__ . '\\is_uploaded_file')) {
    function is_uploaded_file(string $filename): bool
    {
        return file_exists($filename);
    }
}

if (! function_exists(__NAMESPACE__ . '\\move_uploaded_file')) {
    function move_uploaded_file(string $from, string $to): bool
    {
        return @rename($from, $to);
    }
}
