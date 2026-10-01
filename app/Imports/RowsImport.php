<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Reads a spreadsheet's cells as plain rows, header included — the caller
 * (ContactImporter) works out which column is which.
 */
class RowsImport implements ToArray
{
    public function array(array $array): void
    {
    }
}
