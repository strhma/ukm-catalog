<?php
// api/rajaongkir/provinces.php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Cek konfigurasi API Key
if (RAJAONGKIR_API_KEY === 'YOUR_API_KEY_HERE') {
    // Mock Data untuk Demo - 38 Provinsi Indonesia
    $provinces = [
        ['province_id' => '1', 'province' => 'Aceh'],
        ['province_id' => '2', 'province' => 'Bali'],
        ['province_id' => '3', 'province' => 'Banten'],
        ['province_id' => '4', 'province' => 'Bengkulu'],
        ['province_id' => '5', 'province' => 'DI Yogyakarta'],
        ['province_id' => '6', 'province' => 'DKI Jakarta'],
        ['province_id' => '7', 'province' => 'Gorontalo'],
        ['province_id' => '8', 'province' => 'Jambi'],
        ['province_id' => '9', 'province' => 'Jawa Barat'],
        ['province_id' => '10', 'province' => 'Jawa Tengah'],
        ['province_id' => '11', 'province' => 'Jawa Timur'],
        ['province_id' => '12', 'province' => 'Kalimantan Barat'],
        ['province_id' => '13', 'province' => 'Kalimantan Selatan'],
        ['province_id' => '14', 'province' => 'Kalimantan Tengah'],
        ['province_id' => '15', 'province' => 'Kalimantan Timur'],
        ['province_id' => '16', 'province' => 'Kalimantan Utara'],
        ['province_id' => '17', 'province' => 'Kepulauan Bangka Belitung'],
        ['province_id' => '18', 'province' => 'Kepulauan Riau'],
        ['province_id' => '19', 'province' => 'Lampung'],
        ['province_id' => '20', 'province' => 'Maluku'],
        ['province_id' => '21', 'province' => 'Maluku Utara'],
        ['province_id' => '22', 'province' => 'Nusa Tenggara Barat'],
        ['province_id' => '23', 'province' => 'Nusa Tenggara Timur'],
        ['province_id' => '24', 'province' => 'Papua'],
        ['province_id' => '25', 'province' => 'Papua Barat'],
        ['province_id' => '26', 'province' => 'Papua Barat Daya'],
        ['province_id' => '27', 'province' => 'Papua Pegunungan'],
        ['province_id' => '28', 'province' => 'Papua Selatan'],
        ['province_id' => '29', 'province' => 'Papua Tengah'],
        ['province_id' => '30', 'province' => 'Riau'],
        ['province_id' => '31', 'province' => 'Sulawesi Barat'],
        ['province_id' => '32', 'province' => 'Sulawesi Selatan'],
        ['province_id' => '33', 'province' => 'Sulawesi Tengah'],
        ['province_id' => '34', 'province' => 'Sulawesi Tenggara'],
        ['province_id' => '35', 'province' => 'Sulawesi Utara'],
        ['province_id' => '36', 'province' => 'Sumatera Barat'],
        ['province_id' => '37', 'province' => 'Sumatera Selatan'],
        ['province_id' => '38', 'province' => 'Sumatera Utara']
    ];

    echo json_encode([
        'success' => true,
        'data' => $provinces
    ]);
    exit();
}

// Inisialisasi CURL (Fallback ke API Asli jika key ada)
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => RAJAONGKIR_BASE_URL . "/province",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => array(
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
