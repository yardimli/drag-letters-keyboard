<?php
	session_start();
	
	$settings = require 'settings.php';
	$jsonFile = 'assets/words.json';
	$uploadDir = 'assets/uploads/';
	$audioDir = 'assets/audio/';
	
	// Ensure directories exist
	if (!is_dir($uploadDir)) {
		mkdir($uploadDir, 0777, true);
	}
	if (!is_dir($audioDir)) {
		mkdir($audioDir, 0777, true);
	}
	
	// Load Data
	$jsonData = file_get_contents($jsonFile);
	$data = json_decode($jsonData, true);
	if (!$data) {
		$data = ['words' => [], 'languages' => ['en' => 'English', 'zh' => 'Chinese', 'tr' => 'Turkish']];
	}
	if (!isset($data['languages'])) {
		$data['languages'] = ['en' => 'English', 'zh' => 'Chinese', 'tr' => 'Turkish'];
	}
	
	// --- 0. Thumbnail Generation Logic ---
	$dataChanged = false;
	function createThumbnail($sourcePath, $destPath, $width, $height)
	{
		list($origW, $origH, $type) = getimagesize($sourcePath);
		$source = null;
		switch ($type) {
			case IMAGETYPE_JPEG:
				$source = imagecreatefromjpeg($sourcePath);
				break;
			case IMAGETYPE_PNG:
				$source = imagecreatefrompng($sourcePath);
				break;
			case IMAGETYPE_WEBP:
				$source = imagecreatefromwebp($sourcePath);
				break;
		}
		if (!$source) return false;
		$thumb = imagecreatetruecolor($width, $height);
		if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
			imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
			imagealphablending($thumb, false);
			imagesavealpha($thumb, true);
		}
		imagecopyresampled($thumb, $source, 0, 0, 0, 0, $width, $height, $origW, $origH);
		imagejpeg($thumb, $destPath, 80);
		imagedestroy($source);
		imagedestroy($thumb);
		return true;
	}
	
	foreach ($data['words'] as &$word) {
		if (!empty($word['image']) && file_exists($word['image'])) {
			$pathInfo = pathinfo($word['image']);
			$thumbName = $pathInfo['filename'] . '_thumb.jpg';
			$thumbPath = $pathInfo['dirname'] . '/' . $thumbName;
			if (empty($word['thumb']) || !file_exists($thumbPath)) {
				if (createThumbnail($word['image'], $thumbPath, 256, 256)) {
					$word['thumb'] = $thumbPath;
					$dataChanged = true;
				}
			}
		}
	}
	unset($word);
	if ($dataChanged) {
		file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}
	
	// --- 1. Helper: Audio Generation ---
	function generateAudio($prompt, $apiKey, $outputDir)
	{
		$voiceName = "Kore";
		$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent?key=" . $apiKey;
		$payload = [
			"contents" => [["parts" => [["text" => $prompt]]]],
			"generationConfig" => [
				"responseModalities" => ["AUDIO"],
				"speechConfig" => ["voiceConfig" => ["prebuiltVoiceConfig" => ["voiceName" => $voiceName]]]
			],
			"model" => "gemini-2.5-flash-preview-tts"
		];
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		if ($httpCode !== 200) return null;
		
		$json = json_decode($response, true);
		if (isset($json['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
			$pcmData = base64_decode($json['candidates'][0]['content']['parts'][0]['inlineData']['data']);
			$tempPcm = tempnam(sys_get_temp_dir(), 'tts_') . '.pcm';
			file_put_contents($tempPcm, $pcmData);
			$filename = uniqid('audio_') . '.mp3';
			$outputPath = $outputDir . $filename;
			$cmd = "ffmpeg -y -f s16le -ar 24000 -ac 1 -i " . escapeshellarg($tempPcm) . " " . escapeshellarg($outputPath) . " 2>&1";
			shell_exec($cmd);
			unlink($tempPcm);
			if (file_exists($outputPath)) return $outputPath;
		}
		return null;
	}
	
	// --- 2. AJAX Handlers ---
	if (isset($_POST['action'])) {
		if (!isset($_SESSION['is_admin'])) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'error' => 'Unauthorized']);
			exit;
		}
		
		// A. Generate Image Preview (FAL.AI)
		if ($_POST['action'] === 'generate_ai_preview') {
			header('Content-Type: application/json');
			$prompt = $_POST['prompt'] ?? '';
			if (empty($prompt)) { echo json_encode(['success' => false, 'error' => 'Prompt is required']); exit; }
			
			$apiKey = $settings['fal_api_key'];
			$url = 'https://fal.run/fal-ai/qwen-image';
			$dataPayload = [
				'prompt' => $prompt . ", cartoon style, vector art, white background, high quality",
				'image_size' => 'square_hd',
			];
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataPayload));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Key ' . $apiKey, 'Content-Type: application/json']);
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
		
		// B. Regenerate Audio
		if ($_POST['action'] === 'regenerate_audio') {
			header('Content-Type: application/json');
			$prompt = $_POST['prompt'] ?? '';
			$index = $_POST['index'] ?? '';
			if (empty($prompt)) { echo json_encode(['success' => false, 'error' => 'Prompt is required']); exit; }
			if (empty($settings['gemini_api_key'])) { echo json_encode(['success' => false, 'error' => 'Gemini API Key missing']); exit; }
			
			$audioPath = generateAudio($prompt, $settings['gemini_api_key'], $audioDir);
			
			if ($audioPath) {
				if ($index !== '' && isset($data['words'][$index])) {
					if (!empty($data['words'][$index]['audio']) && file_exists($data['words'][$index]['audio'])) {
						unlink($data['words'][$index]['audio']);
					}
					$data['words'][$index]['audio'] = $audioPath;
					file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
				}
				echo json_encode(['success' => true, 'url' => $audioPath]);
			} else {
				echo json_encode(['success' => false, 'error' => 'Failed to generate audio']);
			}
			exit;
		}
		
		// C. Generate Word List (LLM)
		if ($_POST['action'] === 'generate_word_list') {
			header('Content-Type: application/json');
			$topic = $_POST['topic'] ?? '';
			$lang = $_POST['lang'] ?? 'en';
			
			if (empty($topic)) { echo json_encode(['success' => false, 'error' => 'Topic is required']); exit; }
			
			$apiKey = $settings['gemini_api_key'];
			$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
			
			$systemPrompt = "You are a helper that generates vocabulary lists for a spelling game. Return ONLY valid JSON. No markdown formatting.";
			$userPrompt = "Generate a list of 10 simple, single words related to the topic '$topic' in language '$lang'.
        Also provide a short visual description for an image generator for each word.
        Format: [{\"text\": \"WORD\", \"image_prompt\": \"visual description\"}].
        Ensure words are uppercase. Do not include duplicates.";
			
			$payload = [
				"system_instruction" => ["parts" => [["text" => $systemPrompt]]],
				"contents" => [["parts" => [["text" => $userPrompt]]]]
			];
			
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
			$response = curl_exec($ch);
			curl_close($ch);
			
			$json = json_decode($response, true);
			$rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
			
			// Clean Markdown if present
			$rawText = str_replace(['```json', '```'], '', $rawText);
			$generatedWords = json_decode($rawText, true);
			
			if (!is_array($generatedWords)) {
				echo json_encode(['success' => false, 'error' => 'Failed to parse LLM response']);
				exit;
			}
			
			// Deduplicate against existing words
			$existingTexts = array_map(function($w) { return $w['text']; }, $data['words']);
			$finalList = [];
			
			foreach ($generatedWords as $gw) {
				// Check if word exists in the specific language (simple check)
				// Ideally we check text + lang, but for now check text
				$isDuplicate = false;
				foreach ($data['words'] as $existing) {
					if ($existing['text'] === $gw['text'] && $existing['lang'] === $lang) {
						$isDuplicate = true;
						break;
					}
				}
				if (!$isDuplicate) {
					$finalList[] = $gw;
				}
			}
			
			echo json_encode(['success' => true, 'words' => $finalList]);
			exit;
		}
	}
	
	// --- 3. Authentication ---
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
		// Login Form
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
	
	// --- 4. Handle Form Submissions ---
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		// A. Add Language
		if (isset($_POST['action']) && $_POST['action'] === 'add_language') {
			$code = strtolower(trim($_POST['lang_code']));
			$name = trim($_POST['lang_name']);
			if ($code && $name) {
				$data['languages'][$code] = $name;
				file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			}
			header("Location: admin.php");
			exit;
		}
		
		// B. Save Single Word
		if (isset($_POST['action']) && $_POST['action'] === 'save_word') {
			$text = strtoupper(trim($_POST['text']));
			$lang = $_POST['lang'];
			$imagePath = $_POST['current_image_path'] ?? '';
			$audioPath = $_POST['current_audio_path'] ?? '';
			$imagePrompt = $_POST['image_prompt'] ?? ''; // Save the prompt
			
			if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
				$ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
				$filename = uniqid() . '.' . $ext;
				move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename);
				$imagePath = $uploadDir . $filename;
			}
			$thumbPath = '';
			if (!empty($imagePath) && file_exists($imagePath)) {
				$pathInfo = pathinfo($imagePath);
				$thumbName = $pathInfo['filename'] . '_thumb.jpg';
				$thumbPath = $pathInfo['dirname'] . '/' . $thumbName;
				createThumbnail($imagePath, $thumbPath, 256, 256);
			}
			if (empty($audioPath) && !empty($text) && !empty($settings['gemini_api_key'])) {
				$spelled = implode(', ', str_split($text));
				$prompt = "Spell: " . $spelled . "\nSay cheerfully: " . $text;
				$newAudio = generateAudio($prompt, $settings['gemini_api_key'], $audioDir);
				if ($newAudio) $audioPath = $newAudio;
			}
			
			$newWord = [
				'text' => $text,
				'image' => $imagePath,
				'thumb' => $thumbPath,
				'audio' => $audioPath,
				'lang' => $lang,
				'image_prompt' => $imagePrompt
			];
			
			if (isset($_POST['index']) && $_POST['index'] !== '') {
				$data['words'][$_POST['index']] = $newWord;
			} else {
				$data['words'][] = $newWord;
			}
			file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			header("Location: admin.php");
			exit;
		}
		
		// C. Save Batch Words (From Generator)
		if (isset($_POST['action']) && $_POST['action'] === 'save_batch') {
			$texts = $_POST['batch_text'] ?? [];
			$prompts = $_POST['batch_prompt'] ?? [];
			$lang = $_POST['batch_lang'];
			
			foreach ($texts as $idx => $text) {
				$text = strtoupper(trim($text));
				$prompt = $prompts[$idx] ?? '';
				
				// Generate Audio
				$audioPath = '';
				if (!empty($settings['gemini_api_key'])) {
					$spelled = implode(', ', str_split($text));
					$ttsPrompt = "Spell: " . $spelled . "\nSay cheerfully: " . $text;
					$audioPath = generateAudio($ttsPrompt, $settings['gemini_api_key'], $audioDir);
				}
				
				$data['words'][] = [
					'text' => $text,
					'image' => '',
					'thumb' => '',
					'audio' => $audioPath,
					'lang' => $lang,
					'image_prompt' => $prompt
				];
			}
			file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			header("Location: admin.php");
			exit;
		}
		
		// D. Delete Word
		if (isset($_POST['action']) && $_POST['action'] === 'delete') {
			$index = $_POST['index'];
			if (isset($data['words'][$index])) {
				$w = $data['words'][$index];
				if (file_exists($w['image'])) unlink($w['image']);
				if (file_exists($w['thumb'])) unlink($w['thumb']);
				if (file_exists($w['audio'])) unlink($w['audio']);
				array_splice($data['words'], $index, 1);
				file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			}
			header("Location: admin.php");
			exit;
		}
	}
	
	// --- 5. View Logic ---
	$view = $_GET['view'] ?? 'list';
	$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
	$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
	$perPage = 10;
	
	// Filter words for list view
	$filteredWords = [];
	foreach ($data['words'] as $idx => $word) {
		if ($searchQuery === '' || stripos($word['text'], $searchQuery) !== false) {
			$word['original_index'] = $idx;
			$filteredWords[] = $word;
		}
	}
	$totalWords = count($filteredWords);
	$totalPages = ceil($totalWords / $perPage);
	if ($page < 1) $page = 1;
	if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
	$offset = ($page - 1) * $perPage;
	$displayWords = array_slice($filteredWords, $offset, $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Word Manager</title>
	<style>
      body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #1e1e1e; color: #e0e0e0; padding: 20px; margin: 0; }
      .container { max-width: 1000px; margin: 0 auto; background: #2d2d2d; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); }

      h1, h3 { margin-top: 0; color: #00ccff; }
      a { color: #ff4444; text-decoration: none; font-weight: bold; }

      /* Navigation */
      .nav-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #444; padding-bottom: 10px; }
      .nav-tab { padding: 10px 20px; background: #333; color: #aaa; text-decoration: none; border-radius: 4px; }
      .nav-tab.active { background: #00ccff; color: #000; font-weight: bold; }
      .nav-tab:hover:not(.active) { background: #444; }

      table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #333; border-radius: 8px; overflow: hidden; }
      th, td { padding: 15px; border-bottom: 1px solid #444; text-align: left; }
      th { background: #444; color: #fff; font-weight: 600; }
      tr:hover { background: #3a3a3a; }

      .btn { padding: 8px 16px; text-decoration: none; color: white; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; transition: 0.2s; }
      .btn-edit { background: #ffaa00; color: #000; }
      .btn-delete { background: #ff4444; }
      .btn-add { background: #00cc44; padding: 12px 24px; font-size: 16px; font-weight: bold; }
      .btn-gen { background: #00ccff; color: #000; font-weight: bold; margin-top: 5px; }
      .btn-save { background: #00cc44; width: 100%; padding: 12px; font-size: 16px; margin-top: 20px; }
      .btn-audio { background: #9b59b6; color: white; font-weight: bold; }
      .btn-audio-small { background: #9b59b6; color: white; padding: 4px 8px; font-size: 12px; margin-left: 5px; }

      input[type="text"], select, input[type="file"], textarea {
          width: 100%; padding: 10px; box-sizing: border-box;
          background: #444; border: 1px solid #555; color: white; border-radius: 4px; margin-bottom: 10px;
      }

      .preview-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #555; }

      /* Search & Pagination */
      .toolbar { display: flex; justify-content: space-between; align-items: center; margin: 20px 0; }
      .search-box { display: flex; gap: 10px; }
      .pagination { display: flex; gap: 5px; justify-content: center; margin-top: 20px; }
      .page-link { padding: 8px 12px; background: #444; color: white; text-decoration: none; border-radius: 4px; }
      .page-link.active { background: #00ccff; color: #000; font-weight: bold; }

      /* Modal */
      .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; backdrop-filter: blur(3px); }
      .modal-content {
          background: #2d2d2d; margin: 5% auto; padding: 0; width: 800px;
          border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
          display: flex; overflow: hidden; border: 1px solid #444;
      }
      .col-left { flex: 1; padding: 25px; border-right: 1px solid #444; }
      .col-right { width: 300px; background: #222; padding: 25px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
      .close { cursor: pointer; font-size: 28px; color: #888; float: right; }

      #audioModal .modal-content { width: 500px; display: block; padding: 20px; }
      .lang-form { background: #333; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end; }
      #previewContainer { width: 100%; height: 250px; background: #1a1a1a; border: 2px dashed #444; display: flex; align-items: center; justify-content: center; }
      #previewImage { max-width: 100%; max-height: 100%; display: none; }

      /* Generator Styles */
      .gen-results { margin-top: 20px; }
      .gen-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #333; padding: 10px; border-radius: 4px; }
      .gen-row input { margin-bottom: 0; }
	</style>
</head>
<body>

<div class="container">
	<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
		<h1>Word Manager</h1>
		<a href="?logout=1">Logout</a>
	</div>
	
	<!-- Navigation -->
	<div class="nav-tabs">
		<a href="?view=list" class="nav-tab <?php echo $view === 'list' ? 'active' : ''; ?>">Manage Words</a>
		<a href="?view=generator" class="nav-tab <?php echo $view === 'generator' ? 'active' : ''; ?>">AI Word Generator</a>
	</div>
	
	<?php if ($view === 'list'): ?>
		<!-- LIST VIEW -->
		<div class="lang-form">
			<div>
				<label style="font-size:12px; color:#aaa;">New Language Code</label>
				<form method="POST" style="display:flex; gap:10px; margin:0;">
					<input type="hidden" name="action" value="add_language">
					<input type="text" name="lang_code" placeholder="Code" required style="width:80px; margin:0;">
					<input type="text" name="lang_name" placeholder="Name" required style="flex:1; margin:0;">
					<button type="submit" class="btn btn-gen" style="margin:0;">Add Lang</button>
				</form>
			</div>
		</div>
		
		<div class="toolbar">
			<button class="btn btn-add" onclick="openModal()">+ Add New Word</button>
			<form class="search-box" method="GET">
				<input type="hidden" name="view" value="list">
				<input type="text" name="q" placeholder="Search words..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="margin:0; width: 200px;">
				<button type="submit" class="btn btn-gen" style="margin:0;">Search</button>
				<?php if($searchQuery): ?>
					<a href="admin.php?view=list" class="btn" style="background:#555; display:flex; align-items:center;">Clear</a>
				<?php endif; ?>
			</form>
		</div>
		
		<table>
			<thead>
			<tr>
				<th width="80">Thumb</th>
				<th>Word</th>
				<th>Language</th>
				<th>Audio</th>
				<th width="200">Actions</th>
			</tr>
			</thead>
			<tbody>
			<?php if (count($displayWords) > 0): ?>
				<?php foreach ($displayWords as $word): ?>
					<?php $realIndex = $word['original_index']; ?>
					<tr>
						<td>
							<?php $imgSrc = !empty($word['thumb']) ? $word['thumb'] : $word['image']; ?>
							<?php if(!empty($imgSrc)): ?>
								<img src="<?php echo htmlspecialchars($imgSrc); ?>?t=<?php echo time(); ?>" class="preview-thumb">
							<?php else: ?>
								<div style="width:50px; height:50px; background:#444;"></div>
							<?php endif; ?>
						</td>
						<td><?php echo htmlspecialchars($word['text']); ?></td>
						<td><?php echo htmlspecialchars($data['languages'][$word['lang']] ?? $word['lang']); ?></td>
						<td>
							<?php if(!empty($word['audio'])): ?>
								<span style="color:#00cc44;">&#10004;</span>
							<?php else: ?>
								<span style="color:#666;">-</span>
							<?php endif; ?>
						</td>
						<td>
							<button class="btn btn-edit" onclick='editWord(<?php echo json_encode($word); ?>, <?php echo $realIndex; ?>)'>Edit</button>
							<button class="btn btn-audio-small" onclick='openAudioModalForList("<?php echo $word['text']; ?>", <?php echo $realIndex; ?>)'>TTS</button>
							<form method="POST" style="display:inline;" onsubmit="return confirm('Delete this word?');">
								<input type="hidden" name="action" value="delete">
								<input type="hidden" name="index" value="<?php echo $realIndex; ?>">
								<button type="submit" class="btn btn-delete">Del</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else: ?>
				<tr><td colspan="5" style="text-align:center; padding:20px; color:#888;">No words found.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		
		<?php if ($totalPages > 1): ?>
			<div class="pagination">
				<?php if ($page > 1): ?>
					<a href="?view=list&page=<?php echo $page - 1; ?>&q=<?php echo urlencode($searchQuery); ?>" class="page-link">&laquo; Prev</a>
				<?php endif; ?>
				<?php for ($i = 1; $i <= $totalPages; $i++): ?>
					<a href="?view=list&page=<?php echo $i; ?>&q=<?php echo urlencode($searchQuery); ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
				<?php endfor; ?>
				<?php if ($page < $totalPages): ?>
					<a href="?view=list&page=<?php echo $page + 1; ?>&q=<?php echo urlencode($searchQuery); ?>" class="page-link">Next &raquo;</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	
	<?php elseif ($view === 'generator'): ?>
		<!-- GENERATOR VIEW -->
		<div style="background:#333; padding:20px; border-radius:8px;">
			<h2 style="margin-top:0;">Generate Words with AI</h2>
			<div style="display:flex; gap:10px;">
				<div style="flex:1;">
					<label>Topic / Prompt</label>
					<input type="text" id="genTopic" placeholder="e.g. Farm Animals, Kitchen Items, Colors">
				</div>
				<div style="width:150px;">
					<label>Language</label>
					<select id="genLang">
						<?php foreach ($data['languages'] as $code => $name): ?>
							<option value="<?php echo $code; ?>"><?php echo $name; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div style="align-self:flex-end;">
					<button class="btn btn-gen" id="btnStartGen" onclick="startGeneration()">Generate List</button>
				</div>
			</div>
		</div>
		
		<form method="POST" id="batchForm" style="display:none;" class="gen-results">
			<input type="hidden" name="action" value="save_batch">
			<input type="hidden" name="batch_lang" id="batchLangInput">
			
			<h3 id="genStatus">Review Generated Words</h3>
			<p style="color:#aaa; font-size:14px;">Edit words or prompts below. Duplicates have been removed.
				<br>Saving will add these to the database and <strong>automatically generate audio</strong> for them.</p>
			
			<div id="genList"></div>
			
			<button type="submit" class="btn btn-save">Save All</button>
		</form>
	<?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div id="wordModal" class="modal">
	<div class="modal-content">
		<form method="POST" enctype="multipart/form-data" style="display:flex; width:100%; margin:0;">
			<input type="hidden" name="action" value="save_word">
			<input type="hidden" name="index" id="editIndex" value="">
			<input type="hidden" name="current_image_path" id="currentImagePath" value="">
			<input type="hidden" name="current_audio_path" id="currentAudioPath" value="">
			
			<div class="col-left">
				<span class="close" onclick="closeModal()">&times;</span>
				<h2 id="modalTitle" style="margin-top:0;">Add Word</h2>
				
				<label>Word Text</label>
				<input type="text" name="text" id="wordText" required placeholder="e.g. APPLE">
				
				<label>Language</label>
				<select name="lang" id="wordLang">
					<?php foreach ($data['languages'] as $code => $name): ?>
						<option value="<?php echo $code; ?>"><?php echo $name; ?></option>
					<?php endforeach; ?>
				</select>
				
				<hr style="border-color:#444; margin: 20px 0;">
				
				<label>Audio</label>
				<div style="display:flex; align-items:center; gap:10px; margin-bottom:15px; background:#222; padding:10px; border-radius:4px;">
					<audio id="audioPlayer" controls style="height:30px; width:200px;"></audio>
					<button type="button" class="btn btn-audio" onclick="openAudioModalFromEdit()">Regenerate</button>
				</div>
				
				<label>Image Source (AI or Upload)</label>
				<div style="display:flex; gap:10px; margin-bottom:10px;">
					<!-- Stores the prompt for future reference -->
					<input type="text" name="image_prompt" id="aiPrompt" placeholder="AI Prompt (defaults to word)" style="margin:0;">
					<button type="button" class="btn btn-gen" onclick="generateAIImage()" style="margin:0;">Generate</button>
				</div>
				<input type="file" name="image_file" id="fileInput" accept="image/*">
				
				<button type="submit" class="btn btn-save">Save Word</button>
			</div>
			
			<div class="col-right">
				<label style="align-self: flex-start;">Image Preview</label>
				<div id="previewContainer">
					<span id="placeholderText" style="color:#555;">No Image</span>
					<img id="previewImage" src="">
				</div>
			</div>
		</form>
	</div>
</div>

<!-- Audio Prompt Modal -->
<div id="audioModal" class="modal" style="z-index: 1001;">
	<div class="modal-content" style="height:auto;">
		<div style="display:flex; justify-content:space-between; margin-bottom:15px;">
			<h3 style="margin:0;">Regenerate Audio</h3>
			<span class="close" onclick="closeAudioModal()">&times;</span>
		</div>
		<input type="hidden" id="audioTargetIndex" value="">
		<label>TTS Prompt</label>
		<textarea id="ttsPrompt" rows="4" style="font-family:monospace; font-size:14px;"></textarea>
		<div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px;">
			<button type="button" class="btn" style="background:#555;" onclick="closeAudioModal()">Cancel</button>
			<button type="button" class="btn btn-gen" id="btnGenAudio" onclick="submitAudioGeneration()">Generate Audio</button>
		</div>
	</div>
</div>

<script>
	// --- Common Elements ---
	const modal = document.getElementById('wordModal');
	const audioModal = document.getElementById('audioModal');
	
	// Edit Modal Elements
	const modalTitle = document.getElementById('modalTitle');
	const editIndex = document.getElementById('editIndex');
	const wordText = document.getElementById('wordText');
	const wordLang = document.getElementById('wordLang');
	const currentImagePath = document.getElementById('currentImagePath');
	const currentAudioPath = document.getElementById('currentAudioPath');
	const aiPrompt = document.getElementById('aiPrompt');
	const fileInput = document.getElementById('fileInput');
	const audioPlayer = document.getElementById('audioPlayer');
	const previewImage = document.getElementById('previewImage');
	const placeholderText = document.getElementById('placeholderText');
	
	// Audio Modal Elements
	const ttsPrompt = document.getElementById('ttsPrompt');
	const btnGenAudio = document.getElementById('btnGenAudio');
	const audioTargetIndex = document.getElementById('audioTargetIndex');
	
	// --- Generator Logic ---
	async function startGeneration() {
		const topic = document.getElementById('genTopic').value.trim();
		const lang = document.getElementById('genLang').value;
		const btn = document.getElementById('btnStartGen');
		const listDiv = document.getElementById('genList');
		const form = document.getElementById('batchForm');
		const langInput = document.getElementById('batchLangInput');
		
		if (!topic) return alert("Please enter a topic.");
		
		btn.disabled = true;
		btn.innerText = "Generating...";
		listDiv.innerHTML = '<div style="padding:20px; text-align:center;">Asking Gemini...</div>';
		form.style.display = 'block';
		
		const formData = new FormData();
		formData.append('action', 'generate_word_list');
		formData.append('topic', topic);
		formData.append('lang', lang);
		
		try {
			const res = await fetch('admin.php', { method: 'POST', body: formData });
			const data = await res.json();
			
			if (data.success) {
				listDiv.innerHTML = '';
				langInput.value = lang;
				
				if (data.words.length === 0) {
					listDiv.innerHTML = '<div style="padding:20px;">No new words found (duplicates removed).</div>';
				} else {
					data.words.forEach((w, i) => {
						const row = document.createElement('div');
						row.className = 'gen-row';
						row.innerHTML = `
                            <div style="flex:1;">
                                <label style="font-size:10px; color:#aaa;">Word</label>
                                <input type="text" name="batch_text[]" value="${w.text}" required>
                            </div>
                            <div style="flex:2;">
                                <label style="font-size:10px; color:#aaa;">Image Prompt</label>
                                <input type="text" name="batch_prompt[]" value="${w.image_prompt || ''}">
                            </div>
                            <button type="button" class="btn btn-delete" onclick="this.parentElement.remove()">X</button>
                        `;
						listDiv.appendChild(row);
					});
				}
			} else {
				listDiv.innerHTML = `<div style="color:red;">Error: ${data.error}</div>`;
			}
		} catch (e) {
			console.error(e);
			listDiv.innerHTML = `<div style="color:red;">Connection Error</div>`;
		} finally {
			btn.disabled = false;
			btn.innerText = "Generate List";
		}
	}
	
	// --- Modal Logic ---
	function openModal() {
		if(!modal) return;
		modal.style.display = 'block';
		modalTitle.innerText = 'Add New Word';
		editIndex.value = '';
		wordText.value = '';
		currentImagePath.value = '';
		currentAudioPath.value = '';
		aiPrompt.value = '';
		fileInput.value = '';
		audioPlayer.src = '';
		resetPreview();
	}
	
	function editWord(word, index) {
		if(!modal) return;
		modal.style.display = 'block';
		modalTitle.innerText = 'Edit Word';
		editIndex.value = index;
		wordText.value = word.text;
		wordLang.value = word.lang || 'en';
		currentImagePath.value = word.image || '';
		currentAudioPath.value = word.audio || '';
		aiPrompt.value = word.image_prompt || ''; // Load saved prompt
		
		if (word.audio) {
			audioPlayer.src = word.audio + "?t=" + new Date().getTime();
			audioPlayer.style.display = 'block';
		} else {
			audioPlayer.src = '';
		}
		
		const displayImg = word.thumb || word.image;
		if (displayImg) showPreview(displayImg);
		else resetPreview();
	}
	
	function closeModal() { if(modal) modal.style.display = 'none'; }
	
	// --- Audio Logic ---
	function getFormattedPrompt(text) {
		const spelled = text.split('').join(', ');
		return "Spell: " + spelled + "\nSay cheerfully: " + text;
	}
	
	function openAudioModalFromEdit() {
		const text = wordText.value.trim();
		if (!text) return alert("Please enter the word text first.");
		ttsPrompt.value = getFormattedPrompt(text);
		audioTargetIndex.value = "";
		audioModal.style.display = 'block';
	}
	
	function openAudioModalForList(text, index) {
		ttsPrompt.value = getFormattedPrompt(text);
		audioTargetIndex.value = index;
		audioModal.style.display = 'block';
	}
	
	function closeAudioModal() { audioModal.style.display = 'none'; }
	
	async function submitAudioGeneration() {
		const promptVal = ttsPrompt.value.trim();
		const targetIdx = audioTargetIndex.value;
		if (!promptVal) return alert("Prompt cannot be empty");
		
		btnGenAudio.innerText = "Generating...";
		btnGenAudio.disabled = true;
		
		const formData = new FormData();
		formData.append('action', 'regenerate_audio');
		formData.append('prompt', promptVal);
		if (targetIdx !== "") formData.append('index', targetIdx);
		
		try {
			const res = await fetch('admin.php', { method: 'POST', body: formData });
			const data = await res.json();
			if (data.success) {
				if (targetIdx !== "") {
					alert("Audio generated and saved!");
					location.reload();
				} else {
					currentAudioPath.value = data.url;
					audioPlayer.src = data.url + "?t=" + new Date().getTime();
					audioPlayer.play();
					closeAudioModal();
				}
			} else {
				alert("Error: " + data.error);
			}
		} catch (e) {
			alert("Connection failed");
		} finally {
			btnGenAudio.innerText = "Generate Audio";
			btnGenAudio.disabled = false;
		}
	}
	
	// --- Image Logic ---
	function resetPreview() {
		if(!previewImage) return;
		previewImage.style.display = 'none';
		previewImage.src = '';
		placeholderText.style.display = 'block';
	}
	
	function showPreview(src) {
		if(!previewImage) return;
		previewImage.src = src;
		previewImage.style.display = 'block';
		placeholderText.style.display = 'none';
	}
	
	if(fileInput) {
		fileInput.addEventListener('change', function() {
			if (this.files && this.files[0]) {
				const reader = new FileReader();
				reader.onload = (e) => showPreview(e.target.result);
				reader.readAsDataURL(this.files[0]);
			}
		});
	}
	
	async function generateAIImage() {
		const promptVal = aiPrompt.value.trim() || wordText.value.trim();
		if (!promptVal) return alert("Enter a word or prompt.");
		
		placeholderText.innerText = "Generating...";
		previewImage.style.display = 'none';
		
		const formData = new FormData();
		formData.append('action', 'generate_ai_preview');
		formData.append('prompt', promptVal);
		
		try {
			const res = await fetch('admin.php', { method: 'POST', body: formData });
			const data = await res.json();
			if (data.success) {
				showPreview(data.url);
				currentImagePath.value = data.url;
				fileInput.value = '';
			} else {
				alert(data.error);
				resetPreview();
			}
		} catch (e) {
			alert("Connection failed");
			resetPreview();
		}
	}
	
	window.onclick = function(e) {
		if (e.target == modal) closeModal();
		if (e.target == audioModal) closeAudioModal();
	}
</script>

</body>
</html>
