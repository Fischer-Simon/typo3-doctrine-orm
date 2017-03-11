<?php
namespace Cyberhouse\DoctrineORM\Database;

/*
 * This file is (c) 2017 by Cyberhouse GmbH
 *
 * It is free software; you can redistribute it and/or
 * modify it under the terms of the GPLv3 license
 *
 * For the full copyright and license information see
 * <https://www.gnu.org/licenses/gpl-3.0.html>
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Pretty print CREATE Statements in a TYPO3 readable way
 *
 * @author Georg Großberger <georg.grossberger@cyberhouse.at>
 */
class CreateTablePrinter
{
    /**
     * @var IdentifierQuotes
     */
    private $quotes;

    public function __construct()
    {
        $this->quotes = GeneralUtility::makeInstance(IdentifierQuotes::class);
    }

    public function getStatement(string $src): string
    {
        $pos = strpos($src, '(');
        $table = $this->quotes->remove(substr($src, 13, $pos - 13));

        $target[] = '#';
        $target[] = '# Table structure for table \'' . $table . '\'';
        $target[] = '#';
        $target[] = 'CREATE TABLE ' . $table . ' (';

        $inBraces = false;
        $buffer = '';

        while (++$pos < strlen($src)) {
            $char = $src[$pos];

            switch (true) {
                case !$inBraces && $char === ',':
                    $target[] = '  ' . trim($buffer) . ',';
                    $buffer = '';
                    break;

                case !$inBraces && $char === '(':
                    $buffer .= $char;
                    $inBraces = true;
                    break;

                case $inBraces && $char === ')':
                    $buffer .= $char;
                    $inBraces = false;
                    break;

                default:
                    $buffer .= $char;
            }
        }

        if (strlen($buffer) > 1) {
            $target[] = '  ' . trim(substr($buffer, 0, -1));
        }

        return implode(LF, $target) . LF . ');' . LF;
    }
}