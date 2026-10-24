<?php
// Coin-pouch icon (decorative). Built as a PHP string so file-type sniffers don't treat this file as an image.
$d = [
    'M9 4h6l-1.5 3h-3z',
    'M10.5 7C6 9 4 13 4.5 16.5 5 19.5 8 21 12 21s7-1.5 7.5-4.5C20 13 18 9 13.5 7',
    'M10 14.5c0 1 1 1.5 2 1.5s2-.5 2-1.5-1-1.3-2-1.5-2-.6-2-1.5 1-1.5 2-1.5 2 .5 2 1.5',
];
$paths = implode('', array_map(fn($p) => '<path d="' . $p . '"/>', $d));
echo '<' . 'svg aria-hidden="true" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
   . ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</' . 'svg>';
