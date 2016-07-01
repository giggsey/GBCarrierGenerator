<?php
/**
 * Generate GB Mobile & Pager carrier list
 *
 * @author giggsey
 * @created: 01/07/2016 09:00
 * @project GBCarrierGenerator
 */

require __DIR__ . '/vendor/autoload.php';

/*
 * Carrier list of Mobile (07x) range provided by Ofcom
 */

$carrierURL = "http://www.ofcom.org.uk/static/numbering/S7.xls";

$permittedPrefixes = [
    '71',
    '72',
    '73',
    '74',
    '75',
    '76', // Pagers, apart from 7624 (Isle of Man mobile)
    '77',
    '78',
    '79',
];

/*
 * Save file locally
 */
$localFileName = tempnam(sys_get_temp_dir(), 'OfcomS7');

$bytesWritten = file_put_contents($localFileName, fopen($carrierURL, 'r'));

if ($bytesWritten === 0) {
    echo "Unable to download file from Ofcom" . PHP_EOL;
    exit;
}

$excel = PHPExcel_IOFactory::load($localFileName);

$worksheet = $excel->getActiveSheet();


$carriers = [];

foreach ($worksheet->getRowIterator() as $row) {
    if ($row->getRowIndex() === 1) {
        // Ignore header row
        continue;
    }

    $prefix = trim($worksheet->getCellByColumnAndRow(0, $row->getRowIndex())->getValue())
        . trim($worksheet->getCellByColumnAndRow(1, $row->getRowIndex())->getValue());

    $carrier = $worksheet->getCellByColumnAndRow(4, $row->getRowIndex())->getValue();

    /*
     * Ensure it's a permitted prefix
     */

    foreach ($permittedPrefixes as $permittedPrefix) {
        if (substr($prefix, 0, strlen($permittedPrefix)) === $permittedPrefix) {
            $carriers[$prefix] = $carrier;
        }
    }
}

// Delete temp file
@unlink($localFileName);

/*
 * Compress data
 *
 * If the data is the same as the 'parent' prefix, then don't set it
 */

foreach ($carriers as $prefix => $carrier) {
    if (array_key_exists(substr($prefix, 0, -1), $carriers)) {
        // Check if the carriers are the name
        if ($carrier === $carriers[substr($prefix, 0, -1)]) {
            unset($carriers[$prefix]);
        }
    }

    /*
     * Remove the last character, and check if all the child numbers are set
     */
    $numberToCheck = $prefix;

    while (strlen($numberToCheck) > 0) {

        $numberToCheck = substr($numberToCheck, 0, -1);

        $childNumbers = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];

        $allChildren = true;
        $childEntries = [];
        foreach ($childNumbers as $childNumber) {
            if (!array_key_exists($numberToCheck . $childNumber, $carriers)) {
                $allChildren = false;
                break;
            } else {
                $childEntries[$childNumber] = $carriers[$numberToCheck . $childNumber];
            }
        }

        if ($allChildren === true) {
            $result = array_unique($childEntries);

            $removeChildren = false;

            if (count($result) === 1) {
                // All children are the same
                reset($result);
                $childrenEntry = current($result);
                if (array_key_exists($numberToCheck, $carriers)) {
                    // Check if the children are the same
                    if ($carriers[$numberToCheck] === $childrenEntry) {
                        $removeChildren = true;
                    }
                } else {
                    $carriers[$numberToCheck] = $childrenEntry;
                    $removeChildren = true;
                }
            }

            if ($removeChildren === true) {
                foreach ($childNumbers as $childNumber) {
                    unset($carriers[$numberToCheck . $childNumber]);
                }
            }
        }
    }
}

// Sort as strings

ksort($carriers, SORT_STRING);

/*
 * Output result
 */

foreach ($carriers as $prefix => $carrier) {
    echo $prefix . '|' . $carrier . PHP_EOL;
}
