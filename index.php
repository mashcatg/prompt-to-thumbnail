<?php
define('OPENAI_API_KEY', '...');
define('REPLICATE_API_KEY', '...');

function generate_thumbnail_prompt($topic, $details = '') {
    $api_key = OPENAI_API_KEY;
    
    $system_prompt = "You are a YouTube thumbnail expert. Create engaging, detailed prompts for AI image generation. Focus on creating eye-catching, professional thumbnails that would work well at 1280x720 resolution. Include specific details about lighting, composition, style, and mood. Do not include text instructions or references to text overlay. Keep the response as a single, detailed prompt paragraph.";
    
    $user_prompt = "Create a detailed prompt for generating a YouTube thumbnail about: $topic";
    if (!empty($details)) {
        $user_prompt .= "\nAdditional context: $details";
    }
    
    $data = [
        'model' => 'gpt-4',
        'messages' => [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user',
                'content' => $user_prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 500
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            return trim($result['choices'][0]['message']['content']);
        }
        error_log("OpenAI API Response: " . print_r($result, true));
    }
    
    // Fallback prompt if API call fails
    return "Professional cinematic shot of $topic" . 
           ($details ? ", featuring $details" : "") . 
           ", 4K quality, dramatic lighting, professional photography, high-end production value";
}

function generate_image($prompt) {
    $data = [
        'input' => [
            'prompt' => $prompt,
            'seed' => mt_rand(1, 99999),
            'go_fast' => true,
            'guidance' => 3.5,
            'num_outputs' => 1,
            'aspect_ratio' => '16:9',
            'output_format' => 'webp',
            'output_quality' => 80,
            'prompt_strength' => 0.8,
            'num_inference_steps' => 28
        ]
    ];

    $ch = curl_init('https://api.replicate.com/v1/models/black-forest-labs/flux-dev/predictions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . REPLICATE_API_KEY,
            'Content-Type: application/json',
            'Prefer: wait'
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['output'][0] ?? false;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = $_POST['topic'] ?? '';
    $details = $_POST['details'] ?? '';

    if ($topic) {
        $prompt1 = generate_thumbnail_prompt($topic, $details);
        $prompt2 = generate_thumbnail_prompt($topic, $details);


        $image1 = generate_image($prompt1);
        $image2 = generate_image($prompt2);


        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'images' => [
                ['prompt' => $prompt1, 'url' => $image1],
                ['prompt' => $prompt2, 'url' => $image2]
            ]
        ]);
        exit;
    }
}
?>

<!-- Simple HTML form -->
<!DOCTYPE html>
<html>
<head>
    <title>Thumbnail Generator</title>
</head>
<body>
    <form method="post" id="generateForm">
        <input type="text" name="topic" placeholder="Enter your topic" required>
        <input type="text" name="details" placeholder="Additional details (optional)">
        <button type="submit">Generate Thumbnails</button>
    </form>

    <div id="results"></div>

    <script>
        document.getElementById('generateForm').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                const results = document.getElementById('results');
                results.innerHTML = data.images.map((img, i) => `
                    <div>
                        <h3>Version ${i + 1}</h3>
                        <p><strong>Prompt:</strong> ${img.prompt}</p>
                        <img src="${img.url}" width="1280" height="720">
                    </div>
                `).join('');
            }
        };
    </script>
</body>
</html>