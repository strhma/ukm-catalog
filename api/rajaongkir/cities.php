<?php
// api/rajaongkir/cities.php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Cek konfigurasi API Key
if (RAJAONGKIR_API_KEY === 'YOUR_API_KEY_HERE') {
    // Mock Data untuk Demo - Generate Cities based on Province ID
    $provinceId = isset($_GET['province_id']) ? intval($_GET['province_id']) : 0;
    
    // Mapping Data Mock (Ibu Kota & Contoh Kabupaten)
    $cityMap = [
        '1' => ['Banda Aceh', 'Aceh Besar'],
        '2' => ['Denpasar', 'Badung'],
        '3' => ['Serang', 'Tangerang'],
        '4' => ['Bengkulu', 'Rejang Lebong'],
        '5' => ['Yogyakarta', 'Sleman'],
        '6' => ['Jakarta Pusat', 'Jakarta Selatan'],
        '7' => ['Gorontalo', 'Gorontalo Utara'],
        '8' => ['Jambi', 'Muaro Jambi'],
        '9' => ['Bandung', 'Bogor'],
        '10' => ['Semarang', 'Surakarta'],
        '11' => ['Surabaya', 'Malang'],
        '12' => ['Pontianak', 'Kubu Raya'],
        '13' => ['Banjarmasin', 'Banjarbaru'],
        '14' => ['Palangka Raya', 'Kotawaringin Barat'],
        '15' => ['Samarinda', 'Balikpapan'],
        '16' => ['Tanjung Selor', 'Bulungan'],
        '17' => ['Pangkalpinang', 'Bangka'],
        '18' => ['Tanjung Pinang', 'Batam'],
        '19' => ['Bandar Lampung', 'Lampung Selatan'],
        '20' => ['Ambon', 'Maluku Tengah'],
        '21' => ['Ternate', 'Halmahera Utara'],
        '22' => ['Mataram', 'Lombok Barat'],
        '23' => ['Kupang', 'Flores Timur'],
        '24' => ['Jayapura', 'Biak Numfor'],
        '25' => ['Manokwari', 'Fakfak'],
        '26' => ['Sorong', 'Raja Ampat'],
        '27' => ['Wamena', 'Jayawijaya'],
        '28' => ['Merauke', 'Mappi'],
        '29' => ['Nabire', 'Paniai'],
        '30' => ['Pekanbaru', 'Siak'],
        '31' => ['Mamuju', 'Majene'],
        '32' => ['Makassar', 'Gowa'],
        '33' => ['Palu', 'Poso'],
        '34' => ['Kendari', 'Bau-Bau'],
        '35' => ['Manado', 'Minahasa'],
        '36' => ['Padang', 'Bukittinggi'],
        '37' => ['Palembang', 'Musi Banyuasin'],
        '38' => ['Medan', 'Deli Serdang']
    ];

    $data = [];
    if ($provinceId > 0 && isset($cityMap[$provinceId])) {
        $cities = $cityMap[$provinceId];
        // Generate Kota
        $data[] = [
            'city_id' => $provinceId . '01', // Fake ID
            'province_id' => (string)$provinceId,
            'type' => 'Kota',
            'city_name' => $cities[0],
            'postal_code' => '00000'
        ];
        // Generate Kabupaten
        $data[] = [
            'city_id' => $provinceId . '02', // Fake ID
            'province_id' => (string)$provinceId,
            'type' => 'Kabupaten',
            'city_name' => $cities[1],
            'postal_code' => '00000'
        ];
        
        // Tambahan khusus (DKI, Jabar, Jatim, Jateng) biar lebih rame
        if ($provinceId == 6) { foreach (['Jakarta Barat', 'Jakarta Timur', 'Jakarta Utara'] as $idx => $name) $data[] = ['city_id' => "60".($idx+3), 'province_id' => '6', 'type' => 'Kota', 'city_name' => $name, 'postal_code' => '10000']; }
        if ($provinceId == 9) { foreach (['Bekasi', 'Depok', 'Cimahi', 'Sukabumi'] as $idx => $name) $data[] = ['city_id' => "90".($idx+3), 'province_id' => '9', 'type' => 'Kota', 'city_name' => $name, 'postal_code' => '40000']; }
        
        // SULAWESI SELATAN (ID 32): 3 Kota, 21 Kabupaten
        if ($provinceId == 32) {
            $data = []; // Reset default mock
            $idCounter = 1;
            
            // 3 KOTA
            $kotas = ['Makassar', 'Parepare', 'Palopo'];
            foreach ($kotas as $k) {
                $data[] = ['city_id' => "32".str_pad($idCounter++, 2, '0', STR_PAD_LEFT), 'province_id' => '32', 'type' => 'Kota', 'city_name' => $k, 'postal_code' => '90000'];
            }
            
            // 21 KABUPATEN
            $kabupatens = [
                'Bantaeng', 'Barru', 'Bone', 'Bulukumba', 'Enrekang', 'Gowa', 'Jeneponto', 'Kepulauan Selayar', 
                'Luwu', 'Luwu Timur', 'Luwu Utara', 'Maros', 'Pangkajene dan Kepulauan', 'Pinrang', 
                'Sidenreng Rappang', 'Sinjai', 'Soppeng', 'Takalar', 'Tana Toraja', 'Toraja Utara', 'Wajo'
            ];
            foreach ($kabupatens as $k) {
                $data[] = ['city_id' => "32".str_pad($idCounter++, 2, '0', STR_PAD_LEFT), 'province_id' => '32', 'type' => 'Kabupaten', 'city_name' => $k, 'postal_code' => '90000'];
            }
        }
        
    } else {
        // Fallback or Empty if ID not found
        $data[] = ['city_id' => '999', 'province_id' => (string)$provinceId, 'type' => 'Kota', 'city_name' => 'Kota Contoh', 'postal_code' => '12345'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    exit();
}

// Inisialisasi CURL (Fallback ke API Asli jika key ada)
$url = RAJAONGKIR_BASE_URL . "/city";
if (isset($_GET['province_id'])) {
    $url .= "?province=" . $_GET['province_id'];
}

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => $url,
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
