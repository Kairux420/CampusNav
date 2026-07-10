<?php
require_once 'config/db.php';

$mappings = [
    // Image 1: Kiri (padu)
    // SECOND FLOOR
    'F2-181' => '', // xde nama
    'F2-182' => 'Atrium',
    'F2-183' => 'Smart Classroom FCVAC',
    'F2-184' => 'TR 12',
    'F2-185' => 'MR 7',
    'F2-186' => '',
    'F2-187' => '',
    'F2-188' => '',

    // FIRST FLOOR
    'F1-118' => 'MR 5',
    'F1-119' => 'MR 6',
    'F1-121' => 'TR 11',
    'F1-122' => 'TR 10',
    'F1-123' => 'TR 8', 
    'F1-124' => '',
    'F1-125' => '',
    'F1-110' => '', // xde nama
    'F1-111' => 'PW, Nor Akmariah Binti Abdullah',
    'F1-112' => '', // xde nama
    'F1-113' => '',
    'F1-114' => '',
    'F1-115' => '',
    'F1-116' => 'PL, Amir Bin Abdullah',
    'F1-117' => 'PW, Aishah Kahijah Binti Mohammad',
    'F1-100' => 'PL Mohd Ikwaniata Bin Taib',
    'F1-101' => 'PL, Ahmad Tarmizi Bun Zakaria',
    'F1-102' => 'PL, Mohd Firdaus Bin Khalid',
    'F1-103' => 'Bilik Penyelaras Program Asas',
    'F1-104' => 'Penyelaras Servis English',

    // GROUND FLOOR
    'Fg-36' => '', // xde nama
    'Fg-22' => 'TR 2',
    'Fg-23' => 'TR 3',
    'Fg-24' => 'TR 5',
    'Fg-25' => 'TR 4',
    'Fg-26' => 'MR 3',
    'Fg-27' => '',
    'Fg-28' => 'TR 1',
    'Fg-29' => '',
    'Fg-30' => 'MR 4',
    'Fg-31' => 'TR 6',
    'Fg-32' => 'TR 7',
    'Fg-33' => 'TR 9',
    'Fg-34' => 'TR 8',

    // Image 2 (Continues First Floor)
    'F1-105' => '',
    'F1-106' => 'PL Shahrizat Bin Said',
    'F1-107' => 'PW, Wan Yusnee Binti Abdullah',
    'F1-108' => 'PW, Bashariyah Binti Bakar',
    'F1-109' => 'PW Marina Binti Mohd Amir',
    'F1-92'  => 'Bilik Transit',
    'F1-93'  => 'PW, Sakinah Binti Mohamed Tajularifin',
    'F1-94'  => 'Bilik Transit',
    'F1-95'  => '',
    'F1-96'  => '',
    'F1-97'  => '',
    'F1-98'  => '',
    'F1-99'  => 'PW, Norazlina Binti Syamsudin',

    // Image 3 (Tengah)
    'F1-52' => 'Bilik Transit Pensyarah',
    'F1-53' => 'Bilik Pemasaran',
    'F1-54' => 'Pejabat Pentadbiran',
    'F1-55' => 'Bilik Sumber',
    'F1-56' => 'Bilik Fail',
    'F1-57' => 'Stor',

    'Fg-3'  => 'MR 1',
    'Fg-t1' => 'Stor',
    'Fg-srv2' => '',
    'Fg-t12' => '',
    'Fg-tk' => 'Surau',
    'Fg-4'  => 'MR 2',
    'Fg-tnb' => 'Bilik Tidak Wujud', 
    'Fg-msb' => '',
    'Fg-gt' => '',
    'Fg-ut1' => '',

    'F2-132' => '',
    'F2-133' => 'Pelaras Bahasa',
    'F2-134' => 'Pelaras FIM',
    'F2-135a' => '',
    'F2-135b' => '',
    'F2-135' => 'Pejabat Dekan',
    'F2-136' => '',
    'F2-137' => 'Bilik Mesyuarat',

    // Image 4 (Kanan)
    'F1-88' => 'Meeting Room',
    '89b' => '',
    '89c' => '',
    'F1-89' => 'Innovation Unit Office',
    '89a' => 'Bilik Persediaan',
    '90c' => 'Help Desk Counter',
    'F1-90' => 'Pusat Pentadbiran',
    '90b' => '',
    '90a' => '',
    'Fg-18a' => 'Stor Pusat Digital'
];

function normalizeCode($code) {
    // Remove all hyphens, spaces, and make lowercase
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $code));
}

// Build a normalized lookup table
$normalizedMappings = [];
foreach ($mappings as $rawCode => $name) {
    $normalizedMappings[normalizeCode($rawCode)] = $name;
}

$stmt = $pdo->query("SELECT node_id, room_code FROM nodes WHERE room_code IS NOT NULL AND room_code != ''");
$nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
$updateStmt = $pdo->prepare("UPDATE nodes SET node_name = ? WHERE node_id = ?");

foreach ($nodes as $node) {
    $normCode = normalizeCode($node['room_code']);
    
    // Check if we have a mapping for this code
    if (isset($normalizedMappings[$normCode])) {
        $name = $normalizedMappings[$normCode];
        if ($name !== '') {
            $updateStmt->execute([$name, $node['node_id']]);
            $updated++;
        }
    } else {
        // As requested: "if the room code dont exists ignore it"
    }
}

echo "Successfully updated $updated room names based on the image.\n";
?>
