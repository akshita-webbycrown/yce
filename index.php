<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

// === CONFIG === //
$client_id = 'EMm6kK7D31vzKrTj6U0FYk4s1rDXqggd';

// Your PRIVATE RSA key (the one that matches your given public key)
// NEVER share this or commit it in public repos!
$privateKey = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCTL5BsDxnxF4ALTeHVB9W/IVDaQC4WLBJ+jmcE0ux0zp6UBaCP0mWUxZaAOqZC25eXAt4xMxagcoT6LUfW/sIYPUrdL4JFK/CR/ehMeW7vzzUinXMhTwkHnqbG69K6w90XKqwJNuvVoBYmjMZToUiGk9HitpslYcXkb3aEZwuUqwIDAQAB';

$client_id = "QIEG6bfaEIK6lK6Dd5wtIF0ASsNmF4OE";
$publicKeyRaw = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCHWhOiFUt7QPkGFdBoLF75PeIv/KXkUT1V7CAs8RIpBQOtyioqsG8X02tW8UXR5Ef20Ekc5exUUXvyX1qCwOcOGRpQJww8N/vwfyEdY/ihW8dUm/vVj2nHDpuL6yLX5dTFj5cwDxLkuiPQjclBvVUJHMCnofOKqJ1fYHO6XpnK4wIDAQAB";





if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['face_image']) || $_FILES['face_image']['error'] !== UPLOAD_ERR_OK) {
        die("Image upload failed!");
    }
    $task_type = $_POST['task_type'] ?? 'skin-analysis';
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

    echo "<h3>✅ Step 1: Got Access Token</h3><pre>" . htmlspecialchars($auth_response) . "</pre>";

      // ✅ 3️⃣ Upload file (make sure field name is `file`, not `files`)
    $tmpPath = $_FILES['face_image']['tmp_name'];
    $fileName = $_FILES['face_image']['name'];

    if (!is_uploaded_file($tmpPath) || filesize($tmpPath) === 0) {
        die("❌ Uploaded file invalid or empty.");
    }
    // === 2. Create CURLFile ===
    // Force mime type if needed (jpeg/png)
    $mimeType = mime_content_type($tmpPath);
    if ($mimeType === false) {
        $mimeType = 'image/jpeg'; // fallback
    }
   // echo $mimeType;
    echo "<pre>File Name: $fileName</pre>";
    echo "<pre>MIME Type: $mimeType</pre>";
    echo "<pre>File Size: " . filesize($tmpPath) . "</pre>";
    $cfile = new CURLFile($tmpPath, $mimeType, $fileName);

    $metadata = [
        [
            'content_type' => $mimeType,
            'file_name' => $fileName,
            'file_size' => filesize($tmpPath)
        ]
    ];

    $postfields = [
        'file' => $cfile,
        'files' => json_encode($metadata)
    ];

    $upload = curl_init();
    curl_setopt_array($upload, [
        CURLOPT_URL => "https://yce-api-01.perfectcorp.com/s2s/v1.1/file/$task_type",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => json_encode([
    'files' => $metadata
  ]),
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer ".$access_token,
    "content-type: application/json"
  ],
    ]);
    $upload_response = curl_exec($upload);
    if (curl_errno($upload)) {
        echo "<pre>cURL error: " . curl_error($upload) . "</pre>";
    }
    //echo "<pre>cURL info: " . print_r(curl_getinfo($upload), true) . "</pre>";
    curl_close($upload);

    $upload_data = json_decode($upload_response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("❌ JSON decode error: " . json_last_error_msg());
    }
    if( isset($upload_data['result']) ){
        $upload_data = $upload_data['result'];
    }

    if (!isset($upload_data['files'])) {
        die("<h3>❌ Upload failed:</h3><pre>" . htmlspecialchars($upload_response) . "</pre>");
    }

    $src_id = '';
    if ( isset($upload_data['files']) ){
        $src_id = $upload_data['files'][0]['file_id'];
    }
    echo "<h3>✅ Step 2: File Uploaded</h3><pre>" . htmlspecialchars($upload_response) . "</pre>";

    echo '<pre>';
    //print_r($upload_data);
    echo '</pre>';

    // 4️⃣ Run the task
    $task = curl_init();
    $task_payload = [];

    if ( $task_type == 'skin-analysis' ) {
    $task_payload = [
        'request_id' => 1,
        'payload' => [
            'file_sets' => [
                'src_ids' => [ $src_id ]
            ],
            'actions' => [
                [
                                'id' => 1,
                                'params' => [
                                             'src_id' => $src_id,
                    'face_mode' => 'hd'                     
                                ],
                                'dst_actions' => [
                                                                'hd_wrinkle',
                                                                'hd_pore',
                                                                'hd_texture',
                                                                'hd_acne'
                                ]
                ]
            ]
        ]
    ];
    }

    if ( $task_type == 'face-attr-analysis' ) {
        $task_payload = [
            'request_id' => 1,
            'payload' => [
                'file_sets' => [
                    'src_ids' => [ $src_id ]
                ],
                'actions' => [
                    [
                        'id' => 0,
                        'params' => [
                            'face_angle_strictness_level' => 'medium', // or 'strict', 'medium', etc.
                            'features' => [
                                "eyeShape", "eyeSize", "eyeAngle", "eyeDistance", "eyelid"
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    if ( $task_type == 'hair-color' ) {
        $task_payload = [
            'request_id' => 1,
            'payload' => [
                'file_sets' => [
                    'src_ids' => [ $src_id ]
                ],
                'actions' => [
                    [
                        'id' => 0,
                        'params' => [
                            'pattern' => [
                                'name' => 'ombre',
                                'blend_strength' => 80,
                                'line_offset' => 0.2,
                                'coloring_section' => 'top'
                            ],
                            'palettes' => [
                                [
                                    'color' => '#FF0000',
                                    'color_intensity' => 80,
                                    'shine_intensity' => 60
                                ],
                                [
                                    'color' => '#0000FF',
                                    'color_intensity' => 70,
                                    'shine_intensity' => 50
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    
    curl_setopt_array($task, [
        CURLOPT_URL => "https://yce-api-01.perfectcorp.com/s2s/v1.0/task/$task_type",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $access_token",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($task_payload)
    ]);
    $task_response = curl_exec($task);
    if (curl_errno($task)) { die("Task cURL error: " . curl_error($task)); }
    curl_close($task);

    // Assign status to $task_status
    $task_response_data = json_decode($task_response, true);
    $task_status = isset($task_response_data['status']) ? $task_response_data['status'] : null;

    // Store task_id and task_type in session if status is 200
    if ($task_status == 200) {
        $task_id = $task_response_data['result']['task_id'] ?? null;
        //$task_type = 'skin-analysis'; // Set your task type here
        if ($task_id && !empty($task_type)) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            if (!isset($_SESSION['_tasks'])) {
                $_SESSION['_tasks'] = [];
            }
            $_SESSION['_tasks'][] = [
                'task_id' => $task_id,
                'task_type' => $task_type,
                'task_status' => 0,
                'task_status_last_checked' => time(),
                'task_status_label' => 'Pending',
                'task_response' => ''
            ];

            if (isset($_SESSION['_tasks'])) {
              //print_r($_SESSION['_tasks']);
          } else {
              //echo 'No tasks in session.';
          }
        }
    }

    echo "<h3>✅ Step 3: Task Result</h3><pre>";
    echo '<pre>';
    print_r($task_payload);
    echo '</pre>';
    echo '<pre>';
    print_r($task_response);
    echo '</pre>';
    echo "</pre>";
    ?>


<?php
  //  exit;
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PerfectCorp Face Analysis — Full Flow</title>
</head>
<body>
<pre>
<?php
if (isset($_SESSION['_tasks'])) {
    //print_r($_SESSION['_tasks']);
} else {
   // echo 'No tasks in session.';
}
?>
</pre>
    <h2>Upload an image for Face Attribute Analysis</h2>
    <form method="POST" enctype="multipart/form-data">
        <label>Select a task type:</label>
        <select name="task_type">
            <option value="skin-analysis">Skin Analysis</option>
            <option value="face-attr-analysis">Face Attribute Analysis</option>
            <option value="hair-color">Hair Color</option>
        </select>
        <br><br>
        <label>Select an image:</label>
        <input type="file" name="face_image" accept="image/*" required>
        <br><br>
        <button type="submit">Analyze</button>
    </form>
<div id="result"></div>
<script>
function checkTasks() {
    fetch('check_tasks.php')
        .then(response => response.json())
        .then(data => {
            const tasks = data.tasks || [];
            let html = '';
            if (tasks.length > 0) {
                html += '<table border="1" cellpadding="5" cellspacing="0">';
                html += '<tr><th>#</th><th>Task ID</th><th>Type</th><th>Status</th><th>Status Label</th><th>Last Checked</th><th>Response</th></tr>';
                tasks.forEach((task, idx) => {
                    html += `<tr>
                        <td>${idx + 1}</td>
                        <td>${task.task_id || ''}</td>
                        <td>${task.task_type || ''}</td>
                        <td>${task.task_status}</td>
                        <td>${task.task_status_label}</td>
                        <td>${task.task_status_last_checked}</td>
                        <td><pre style="white-space:pre-wrap;max-width:300px;overflow:auto;">${task.task_response ? (typeof task.task_response === 'string' ? task.task_response : JSON.stringify(task.task_response, null, 2)) : ''}</pre></td>
                    </tr>`;
                });
                html += '</table>';
            } else {
                html = '<em>No tasks found.</em>';
            }
            document.getElementById('result').innerHTML = html;
        });
}
setInterval(checkTasks, 10000);
checkTasks();
</script>
</body>
</html>
