<?php
// api/rajaongkir/cost.php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Cek konfigurasi API Key
if (RAJAONGKIR_API_KEY === 'YOUR_API_KEY_HERE') {
    // Mock Data untuk Demo
    $input = json_decode(file_get_contents('php://input'), true);
    $courier = strtoupper($input['courier'] ?? 'JNE');
    
    echo json_encode([
        'success' => true,
        'data' => [
            [
                'code' => $courier,
                'name' => $courier . ' Express',
                'costs' => [
                    [
                        'service' => 'REG',
                        'description' => 'Layanan Reguler',
                        'cost' => [['value' => 15000, 'etd' => '2-3', 'note' => '']]
                    ],
                    [
                        'service' => 'YES',
                        'description' => 'Yakin Esok Sampai',
                        'cost' => [['value' => 25000, 'etd' => '1-1', 'note' => '']]
                    ]
                ]
            ]
        ]
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data JSON tidak valid']);
    exit();
}

$destination = $input['destination'] ?? 0;
$weight = $input['weight'] ?? 1000; // Default 1kg
$courier = $input['courier'] ?? 'jne'; // Default JNE

if (empty($destination)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Kota tujuan diperlukan']);
    exit();
}

// Inisialisasi CURL
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => RAJAONGKIR_BASE_URL . "/cost",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => "origin=".ORIGIN_CITY_ID."&destination=".$destination."&weight=".$weight."&courier=".$courier,
  CURLOPT_HTTPHEADER => array(
    "content-type: application/x-www-form-urlencoded",
    "key: " . RAJAONGKIR_API_KEY
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "cURL Error #: " . $err]);
} else {
    $data = json_decode($response, true);
    
    if (isset($data['rajaongkir']['status']['code']) && $data['rajaongkir']['status']['code'] == 200) {
        echo json_encode([
            'success' => true,
            'data' => $data['rajaongkir']['results']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => $data['rajaongkir']['status']['description'] ?? 'Terjadi kesalahan pada RajaOngkir'
        ]);
    }
}
?>
