<?php
session_start();

$settings = require 'settings.php';
$jsonFile = 'assets/words.json';
$uploadDir = 'assets/uploads/';

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// --- 1. AJAX Handler for AI Generation (Returns JSON) ---
if (isset($_POST['action']) && $_POST['action'] === 'generate_ai_preview') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['is_admin'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $prompt = $_POST['prompt'] ?? '';
    if (empty($prompt)) {
        echo json_encode(['success' => false, 'error' => 'Prompt is required']);
        exit;
    }

    // Include the generation logic inline or via function
    $apiKey = $settings['fal_api_key'];
    $url = 'https://fal.run/fal-ai/qwen-image';
    $data = [
        'prompt' => $prompt, // Added style keywords
        'image_size' => 'square_hd',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Key ' . $apiKey,
        'Content-Type: application/json',
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $json = json_decode($result, true);
        if (isset($json['images'][0]['url'])) {
            $imageUrl = $json['images'][0]['url'];
            $imageContent = file_get_contents($imageUrl);

            $filename = uniqid() . '.jpg';
            $outputPath = $uploadDir . $filename;

            if (file_put_contents($outputPath, $imageContent)) {
                echo json_encode(['success' => true, 'url' => $outputPath]);
                exit;
            }
        }
    }

    echo json_encode(['success' => false, 'error' => 'Failed to generate image']);
    exit;
}

// --- 2. Authentication Check ---
if (isset($_POST['login'])) {
    if ($_POST['password'] === $settings['admin_password']) {
        $_SESSION['is_admin'] = true;
    } else {
        $error = "Invalid Password";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if (!isset($_SESSION['is_admin'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Admin Login</title>
        <style>
            body { font-family: 'Segoe UI', sans-serif; background: #1e1e1e; color: #eee; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            form { background: #2d2d2d; padding: 2.5rem; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.5); width: 300px; }
            h2 { margin-top: 0; color: #00ccff; }
            input { padding: 12px; margin: 15px 0; width: 100%; box-sizing: border-box; background: #444; border: 1px solid #555; color: white; border-radius: 6px; }
            button { padding: 12px 20px; background: #00ccff; border: none; cursor: pointer; font-weight: bold; width: 100%; border-radius: 6px; color: #000; transition: 0.2s; }
            button:hover { background: #00aadd; }
            .error { color: #ff4444; margin-bottom: 10px; }
        </style>
    </head>
    <body>
    <form method="POST">
        <h2>Admin Login</h2>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        <input type="password" name="password" placeholder="Enter Password" required>
        <button type="submit" name="login">Login</button>
    </form>
    </body>
    </html>
    <?php
    exit;
}

// --- 3. Handle Form Submissions (Save/Delete) ---
$data = json_decode(file_get_contents($jsonFile), true);
if (!$data) $data = ['words' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save Word
    if (isset($_POST['action']) && $_POST['action'] === 'save_word') {
        $text = strtoupper(trim($_POST['text']));
        $lang = $_POST['lang'];

        // Current image path (could be existing, or the one just generated via AJAX)
        $imagePath = $_POST['current_image_path'] ?? '';

        // If user uploaded a NEW file, it overrides everything
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename);
            $imagePath = $uploadDir . $filename;
        }

        $newWord = [
            'text' => $text,
            'image' => $imagePath,
            'lang' => $lang
        ];

        if (isset($_POST['index']) && $_POST['index'] !== '') {
            $data['words'][$_POST['index']] = $newWord;
        } else {
            $data['words'][] = $newWord;
        }

        file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
        header("Location: admin.php");
        exit;
    }

    // Delete Word
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $index = $_POST['index'];
        if (isset($data['words'][$index])) {
            array_splice($data['words'], $index, 1);
            file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
        }
        header("Location: admin.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Word Manager</title>
    <style>
        /* Dark Mode Styles */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #1e1e1e; color: #e0e0e0; padding: 20px; margin: 0; }
        .container { max-width: 900px; margin: 0 auto; background: #2d2d2d; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); }

        h1 { margin-top: 0; color: #00ccff; }
        a { color: #ff4444; text-decoration: none; font-weight: bold; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #333; border-radius: 8px; overflow: hidden; }
        th, td { padding: 15px; border-bottom: 1px solid #444; text-align: left; }
        th { background: #444; color: #fff; font-weight: 600; }
        tr:hover { background: #3a3a3a; }

        /* Buttons */
        .btn { padding: 8px 16px; text-decoration: none; color: white; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; transition: 0.2s; }
        .btn-edit { background: #ffaa00; color: #000; }
        .btn-edit:hover { background: #e69900; }
        .btn-delete { background: #ff4444; }
        .btn-delete:hover { background: #cc3333; }
        .btn-add { background: #00cc44; padding: 12px 24px; font-size: 16px; font-weight: bold; }
        .btn-add:hover { background: #00aa38; }
        .btn-gen { background: #00ccff; color: #000; font-weight: bold; margin-top: 5px; }
        .btn-gen:hover { background: #00aadd; }
        .btn-save { background: #00cc44; width: 100%; padding: 12px; font-size: 16px; margin-top: 20px; }

        /* Inputs */
        input[type="text"], select, input[type="file"] {
            width: 100%; padding: 10px; box-sizing: border-box;
            background: #444; border: 1px solid #555; color: white; border-radius: 4px;
        }
        input[type="text"]:focus, select:focus { outline: 2px solid #00ccff; border-color: transparent; }

        .preview-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #555; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; backdrop-filter: blur(3px); }
        .modal-content {
            background: #2d2d2d; margin: 5% auto; padding: 0; width: 800px;
            border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            display: flex; overflow: hidden; border: 1px solid #444;
        }

        .modal-header { padding: 20px; background: #333; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #444; }
        .close { cursor: pointer; font-size: 28px; color: #888; }
        .close:hover { color: #fff; }

        /* Modal Grid Layout */
        .modal-body { display: flex; width: 100%; }
        .col-left { flex: 1; padding: 25px; border-right: 1px solid #444; }
        .col-right { width: 300px; background: #222; padding: 25px; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #bbb; }

        .divider { text-align: center; margin: 15px 0; color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }

        /* Preview Area */
        #previewContainer {
            width: 100%; height: 300px; background: #1a1a1a;
            border: 2px dashed #444; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; position: relative;
        }
        #previewImage { max-width: 100%; max-height: 100%; object-fit: contain; display: none; }
        .placeholder-text { color: #555; text-align: center; }

        /* Spinner */
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-left-color: #00ccff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            position: absolute;
            display: none;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <h1>Word Manager</h1>
        <a href="?logout=1">Logout</a>
    </div>

    <button class="btn btn-add" onclick="openModal()">+ Add New Word</button>

    <table>
        <thead>
        <tr>
            <th width="80">Image</th>
            <th>Word</th>
            <th>Language</th>
            <th width="150">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data['words'] as $index => $word): ?>
            <tr>
                <td>
                    <?php if(!empty($word['image'])): ?>
                        <img src="<?php echo htmlspecialchars($word['image']); ?>" class="preview-thumb">
                    <?php else: ?>
                        <div style="width:50px; height:50px; background:#444; border-radius:4px;"></div>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($word['text']); ?></td>
                <td>
                    <?php
                    $langs = ['en' => 'English', 'zh' => 'Chinese', 'tr' => 'Turkish'];
                    echo $langs[$word['lang']] ?? $word['lang'];
                    ?>
                </td>
                <td>
                    <button class="btn btn-edit" onclick='editWord(<?php echo json_encode($word); ?>, <?php echo $index; ?>)'>Edit</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this word?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                        <button type="submit" class="btn btn-delete">Del</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div id="wordModal" class="modal">
    <div class="modal-content">

        <form method="POST" enctype="multipart/form-data" style="display:flex; width:100%; margin:0; padding:0; background:transparent; box-shadow:none;">
            <input type="hidden" name="action" value="save_word">
            <input type="hidden" name="index" id="editIndex" value="">
            <!-- Stores the path of existing image OR the one generated by AI -->
            <input type="hidden" name="current_image_path" id="currentImagePath" value="">

            <!-- Left Column: Inputs -->
            <div class="col-left">
                <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                    <h2 id="modalTitle" style="margin:0; color:white;">Add Word</h2>
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>

                <div class="form-group">
                    <label>Word Text</label>
                    <input type="text" name="text" id="wordText" required placeholder="e.g. APPLE">
                </div>

                <div class="form-group">
                    <label>Language</label>
                    <select name="lang" id="wordLang">
                        <option value="en">English</option>
                        <option value="zh">Chinese</option>
                        <option value="tr">Turkish</option>
                    </select>
                </div>

                <div class="divider">- IMAGE SOURCE -</div>

                <div class="form-group">
                    <label>Option A: Generate with AI</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="aiPrompt" placeholder="Describe image (defaults to word)">
                        <button type="button" class="btn btn-gen" onclick="generateAIImage()">Generate</button>
                    </div>
                    <small style="color:#888;">Click Generate to preview on the right.</small>
                </div>

                <div class="divider">OR</div>

                <div class="form-group">
                    <label>Option B: Upload File</label>
                    <input type="file" name="image_file" id="fileInput" accept="image/*">
                </div>

                <button type="submit" class="btn btn-save">Save Word</button>
            </div>

            <!-- Right Column: Preview -->
            <div class="col-right">
                <label style="align-self: flex-start;">Preview</label>
                <div id="previewContainer">
                    <div class="spinner" id="loadingSpinner"></div>
                    <div class="placeholder-text" id="placeholderText">No Image Selected</div>
                    <img id="previewImage" src="" alt="Preview">
                </div>
            </div>
        </form>

    </div>
</div>

<script>
    const modal = document.getElementById('wordModal');
    const modalTitle = document.getElementById('modalTitle');
    const editIndex = document.getElementById('editIndex');
    const wordText = document.getElementById('wordText');
    const wordLang = document.getElementById('wordLang');
    const currentImagePath = document.getElementById('currentImagePath');
    const aiPrompt = document.getElementById('aiPrompt');
    const fileInput = document.getElementById('fileInput');

    // Preview Elements
    const previewImage = document.getElementById('previewImage');
    const placeholderText = document.getElementById('placeholderText');
    const loadingSpinner = document.getElementById('loadingSpinner');

    function openModal() {
        modal.style.display = 'block';
        modalTitle.innerText = 'Add New Word';

        // Reset Fields
        editIndex.value = '';
        wordText.value = '';
        wordLang.value = 'en';
        currentImagePath.value = '';
        aiPrompt.value = '';
        fileInput.value = '';

        resetPreview();
    }

    function editWord(word, index) {
        modal.style.display = 'block';
        modalTitle.innerText = 'Edit Word';

        editIndex.value = index;
        wordText.value = word.text;
        wordLang.value = word.lang || 'en';
        currentImagePath.value = word.image || '';
        aiPrompt.value = '';
        fileInput.value = '';

        if (word.image) {
            showPreview(word.image);
        } else {
            resetPreview();
        }
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    // --- Preview Logic ---

    function resetPreview() {
        previewImage.style.display = 'none';
        previewImage.src = '';
        placeholderText.style.display = 'block';
        loadingSpinner.style.display = 'none';
    }

    function showPreview(src) {
        previewImage.src = src;
        previewImage.style.display = 'block';
        placeholderText.style.display = 'none';
        loadingSpinner.style.display = 'none';
    }

    function startLoading() {
        previewImage.style.display = 'none';
        placeholderText.style.display = 'none';
        loadingSpinner.style.display = 'block';
    }

    // --- File Upload Preview ---
    fileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                showPreview(e.target.result);
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    // --- AI Generation Logic ---
    async function generateAIImage() {
        const promptVal = aiPrompt.value.trim() || wordText.value.trim();

        if (!promptVal) {
            alert("Please enter a word or a prompt first.");
            return;
        }

        startLoading();

        const formData = new FormData();
        formData.append('action', 'generate_ai_preview');
        formData.append('prompt', promptVal);

        try {
            const response = await fetch('admin.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Update the preview
                showPreview(data.url);
                // Update the hidden input so it gets saved when form submits
                currentImagePath.value = data.url;
                // Clear file input so it doesn't override the AI image
                fileInput.value = '';
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
                resetPreview();
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to connect to server.');
            resetPreview();
        }
    }

    // Close modal on outside click
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

</body>
</html>