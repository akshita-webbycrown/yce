<?php
session_start();
header('Content-Type: application/json');


if (!isset($_SESSION['_tasks'])) {
    //echo json_encode(['tasks' => []]);
    echo 'No tasks in session.';
    exit;
}
// === CONFIG === //
$client_id = 'EMm6kK7D31vzKrTj6U0FYk4s1rDXqggd';

// Your PRIVATE RSA key (the one that matches your given public key)
// NEVER share this or commit it in public repos!
$privateKey = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCTL5BsDxnxF4ALTeHVB9W/IVDaQC4WLBJ+jmcE0ux0zp6UBaCP0mWUxZaAOqZC25eXAt4xMxagcoT6LUfW/sIYPUrdL4JFK/CR/ehMeW7vzzUinXMhTwkHnqbG69K6w90XKqwJNuvVoBYmjMZToUiGk9HitpslYcXkb3aEZwuUqwIDAQAB';

$client_id = "QIEG6bfaEIK6lK6Dd5wtIF0ASsNmF4OE";
$publicKeyRaw = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCHWhOiFUt7QPkGFdBoLF75PeIv/KXkUT1V7CAs8RIpBQOtyioqsG8X02tW8UXR5Ef20Ekc5exUUXvyX1qCwOcOGRpQJww8N/vwfyEdY/ihW8dUm/vVj2nHDpuL6yLX5dTFj5cwDxLkuiPQjclBvVUJHMCnofOKqJ1fYHO6XpnK4wIDAQAB";


    // 2. Generate id_token
    $timestamp = round(microtime(true) * 1000);
    $data = "client_id={$client_id}&timestamp={$timestamp}";
    $pemKey = "-----BEGIN PUBLIC KEY-----\n" .
    chunk_split($publicKeyRaw, 64, "\n") .
    "-----END PUBLIC KEY-----";
    openssl_public_encrypt($data, $encrypted, $pemKey);
    $id_token = base64_encode($encrypted);
    
        // 2️⃣ Call /client/auth to get access_token
        $auth = curl_init();
        curl_setopt_array($auth, [
            CURLOPT_URL => "https://yce-api-01.perfectcorp.com/s2s/v1.0/client/auth",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_POSTFIELDS => json_encode([
                'client_id' => $client_id,
                'id_token' => $id_token
            ]),
        ]);
        $auth_response = curl_exec($auth);
        if (curl_errno($auth)) {
            die("❌ Auth cURL error: " . curl_error($auth));
        }
        curl_close($auth);
    
        $auth_data = json_decode($auth_response, true);
    
        //print_r($auth_data);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            die("❌ JSON decode error: " . json_last_error_msg());
        }
    
        if (!isset($auth_data['result']['access_token'])) {
            die("<h3>❌ Auth failed:</h3><pre>" . htmlspecialchars($auth_response) . "</pre>");
        }
    
        $access_token = $auth_data['result']['access_token'];



foreach ($_SESSION['_tasks'] as $i => &$task) {
    
    if ($task['task_status'] == 0 && !empty($task['task_id'])) {
        $task_id = urlencode($task['task_id']);
        $task_type = urlencode($task['task_type']);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://yce-api-01.perfectcorp.com/s2s/v1.0/task/$task_type?task_id=$task_id",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $access_token"
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            $task['task_response'] = "cURL Error #:" . $err;
        } else {
            $task['task_response'] = $response;
            $response_data = json_decode($response, true);
            if (isset($response_data['status']) && $response_data['status'] == 200) {
               // $task['task_status'] = 1; // Mark as done
                $task['task_status_label'] = $response_data['result']['status'];
                if ( $response_data['result']['status'] == 'success' || $response_data['result']['status'] == 'error' ) {
                    $task['task_status'] = 1;
                } else {
                    $task['task_status'] = 0;
                }
                $task['task_status_last_checked'] = date('Y-m-d H:i:s', time());
            } else {
                $task['task_status'] = 2;
                $task['task_status_label'] = $response_data['error'];
                $task['task_status_last_checked'] = date('Y-m-d H:i:s', time());
            }
        }
    }
}
// Save back to session
$_SESSION['_tasks'] = $_SESSION['_tasks'];

echo json_encode(['tasks' => $_SESSION['_tasks']]);