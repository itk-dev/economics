<?php

// @see https://github.com/VincentLanglet/Twig-CS-Fixer/blob/main/docs/configuration.md
$finder = (new TwigCsFixer\File\Finder())
    ->in('templates')
    // Generated files.
    ->notPath('#game_center/.+/#')
;

return (new TwigCsFixer\Config\Config())
    ->setFinder($finder)
;
