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
 * Carrier Lists, provided by Ofcom
 * @see http://static.ofcom.org.uk/static/numbering/index.htm
 */

$carrierURLs = [
    "http://www.ofcom.org.uk/static/numbering/S7.xlsx",
];

$carriers = [];


foreach ($carrierURLs as $carrierURL) {

    /*
     * Save file locally
     */
    $localName = explode('/', $carrierURL);
    $localFileName = tempnam(sys_get_temp_dir(), end($localName));

    $bytesWritten = file_put_contents($localFileName, fopen($carrierURL, 'r'));

    if ($bytesWritten === 0) {
        echo "Unable to download file from Ofcom" . PHP_EOL;
        exit;
    }

    $excel = \PhpOffice\PhpSpreadsheet\IOFactory::load($localFileName);

    $worksheet = $excel->getActiveSheet();


    foreach ($worksheet->getRowIterator() as $row) {
        if ($row->getRowIndex() === 1) {

            /*
             * Work out which columns to use
             */

            $commsProviderColumn = null;
            $changeColumn = null;
            $statusColumn = null;

            $column = 1;
            $data = $worksheet->getCellByColumnAndRow($column, $row->getRowIndex())->getValue();
            while ($data != '') {
                if ($data == 'Communications Provider') {
                    $commsProviderColumn = $column;
                } elseif ($data == 'Change') {
                    $changeColumn = $column;
                } elseif ($data == 'Status') {
                    $statusColumn = $column;
                }

                $column++;
                $data = $worksheet->getCellByColumnAndRow($column, $row->getRowIndex())->getValue();
            }

            if ($changeColumn === null || $commsProviderColumn === null || $statusColumn === null) {
                throw new RuntimeException("Unable to find columns! {$changeColumn} - {$commsProviderColumn} - {$statusColumn}");
            }

            continue;
        }

        $prefix = trim($worksheet->getCellByColumnAndRow(1, $row->getRowIndex())->getValue())
            . trim($worksheet->getCellByColumnAndRow(2, $row->getRowIndex())->getValue());

        $allocated = trim($worksheet->getCellByColumnAndRow($statusColumn, $row->getRowIndex())->getValue());

        if ($allocated == 'Allocated') {
            $carrier = (string)$worksheet->getCellByColumnAndRow($commsProviderColumn, $row->getRowIndex())->getValue();
//            $date = $worksheet->getCellByColumnAndRow($changeColumn, $row->getRowIndex())->getValue();
//
//            $UNIX_DATE = ($date - 25569) * 86400;
//            $date = gmdate('Y-m-d', $UNIX_DATE);
            /*
             * Ensure it's a permitted prefix
             */

            $carriers['44' . $prefix] = $carrier;
        }
    }

    // Delete temp file
    @unlink($localFileName);
}


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
            $result = array_unique($childEntries, SORT_REGULAR);

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

                if ($removeChildren === true) {
                    foreach ($childNumbers as $childNumber) {
                        unset($carriers[$numberToCheck . $childNumber]);
                    }
                }
            } elseif (count($result) < count($childNumbers)) {
                // Set a new master with the majority carrier
                $values = array_count_values($childEntries);

                $maxCarrier = array_search(max($values), $values); // We only want the top result

                $carriers[$numberToCheck] = $maxCarrier;

                foreach ($childNumbers as $childNumber) {
                    if ($carriers[$numberToCheck . $childNumber] == $maxCarrier) {
                        unset($carriers[$numberToCheck . $childNumber]);
                    }
                }
            }
        }
    }
}

// Sort as strings

ksort($carriers, SORT_STRING);

/*
 * Write to a text file
 */

$swapCarriers = [
    'Hutchison 3G UK Ltd' => 'Three',
    'EE Limited (Orange)' => 'Orange',
    'EE Limited ( TM)' => 'EE',
    'Telefonica UK Limited' => 'O2',
    'Virgin Mobile Telecoms Limited' => 'Virgin Mobile',
    'Vodafone Uk Ltd' => 'Vodafone',
    'Limitless Mobile Ltd' => 'Limitless',
    'TalkTalk Communications Limited' => 'TalkTalk',
    'Lycamobile UK Limited' => 'Lycamobile',
    'Cheers International Sales Limited' => 'Cheers',
    '08Direct Limited' => '08Direct',
    '24 Seven Communications Ltd' => '24 Seven',
    'TGL Services (UK) Ltd' => 'TGL',
    'Truphone Ltd' => 'Truphone',
    'Manx Telecom Trading Limited' => 'Manx Telecom',
    'Vectone Mobile Limited' => 'Vectone Mobile',
    'IV Response Limited' => 'IV Response',
    'Icron Network Limited' => 'Icron Network',
    'Dynamic Mobile Billing Limited' => 'Oxygen8',
    // Company recently changed their name from Oxygen8 to Dynamic Mobile (Apr 2017)
    'TeleWare PLC' => 'TeleWare',
    'Marathon Telecom Limited' => 'Marathon Telecom',
    'JT (Guernsey) Limited' => 'JT',
    'Citrus Telecommunications Ltd' => 'Citrus',
    'aql Wholesale Limited' => 'aql',
    'Magrathea Telecommunications Limited' => 'Magrathea',
    'HAY SYSTEMS LIMITED' => 'HSL',
    'Telesign Mobile Limited' => 'Telesign',
    'Guernsey Airtel Limited' => 'Airtel',
    'Sure (Guernsey) Limited' => 'Sure',
    'Jersey Airtel  Limited' => 'Airtel',
    'Swiftnet Ltd' => 'Swiftnet',
    'FleXtel Limited' => 'FleXtel',
    'Airwave Solutions Ltd' => 'Airwave',
    'Core Communication Services Ltd' => 'Core Communication',
    'Sure (Jersey) Limited' => 'Sure',
    'Nationwide Telephone Assistance Ltd' => 'Nationwide Telephone',
    'Cloud9 Mobile Communications Ltd' => 'Cloud9',
    'PageOne Communications Ltd' => 'PageOne',
    'Telency Limited' => 'Telency', // Rebranded from Telsis Systems
    'Relax Telecom Limited' => 'Relax',
    'Core Telecom Limited' => 'Core Telecom',
    'Confabulate Limited' => 'Confabulate',
    'M P Tanner Limited t/a FIO Telecom' => 'FIO Telecom',
    'Syntec Limited' => 'Syntec',
    'Plus Telecom Limited' => 'Plus',
    'Media Telecom Ltd' => 'Media',
    'Sure (Isle of Man) Limited' => 'Sure',
    'Test2date B.V' => 'Test2date',
    'JT (Jersey) Limited' => 'JT',
    'QX Telecom Ltd' => 'QX Telecom',
    'Lleida.net Serveis Telematics Limited' => 'Lleida.net',
    'Nodemax Limited' => 'Nodemax',
    'Resilient Plc' => 'Resilient',
    'Globecom International Limited.' => 'Globecom',
    'IPV6 Limited' => 'IPV6',
    'Mars Communications Limited' => 'Mars',
    'CFL Communications Limited' => 'CFL',
    'Sound Advertising Ltd' => 'Mediatel',
    'Stour Marine Limited' => 'Stour Marine',
    'Wavecrest (UK) Ltd' => 'Wavecrest',
    'MOBIWEB TELECOM LIMITED' => 'Mobiweb',
    '(aq) Limited trading as aql' => 'aql',
    'Tismi BV' => 'Tismi',
    'Esendex Limited' => 'Esendex',
    'Simwood eSMS Limited' => 'Simwood',
    'BT OnePhone Limited' => 'BT OnePhone',
    'Fogg Mobile AB' => 'Fogg',
    'Sky UK Limited' => 'Sky',
    'Lanonyx Telecom Limited' => 'Lanonyx',
    'Ziron (UK) Ltd' => 'Ziron',
    'Telecom2 Ltd' => 'Telecom2',
    'Telecom 10 Ltd' => 'Telecom 10',
    'Teleena UK Limited' => 'Teleena',
    'Anywhere Sim Limited' => 'Anywhere Sim',
    'Hanhaa Limited' => 'Hanhaa',
    'Bellingham Telecommunications Limited' => 'Bellingham',
    'Telecom North America Mobile Inc' => 'Telna',
    'Ace Call Limited' => 'Ace Call',
    'Telecoms Cloud Networks Limited' => 'Telecoms Cloud',
    'Andrews & Arnold Ltd' => 'Andrews & Arnold',
    'Synectiv Ltd' => 'Synectiv',
    'Voxbone SA' => 'Voxbone',
    'UK Broadband Limited' => 'UK Broadband',
    'Voicetec Systems Ltd' => 'Voicetec',
    'Spacetel UK Ltd' => 'Spacetel',
    'Gamma Telecom Holdings Ltd' => 'Gamma Telecom',
    'Premium Routing GmbH' => 'Premium Routing',
    'Compatel Ltd' => 'Compatel',
    'Global Reach Networks Limited' => 'GlobalReach',
];

$unusedCarriers = $swapCarriers;

ksort($swapCarriers);

foreach ($swapCarriers as $original => $new) {
    echo "#  - {$original}: {$new}\n";
}

$text = fopen('data.txt', 'w');

foreach ($carriers as $prefix => $carrierData) {
    if (substr($prefix, 0, 4) == '4470') {
        // Skip personal numbers
        continue;
    }

    $carrier = $swapCarriers[$carrierData] ?? $carrierData;

    fwrite($text, "{$prefix}|{$carrier}\n");

    unset($unusedCarriers[$carrierData]);
}

fclose($text);

if (count($unusedCarriers) > 0) {
    echo "Notice! We have unused carriers in our list!:\n";

    echo implode("\n", array_keys($unusedCarriers));
}

