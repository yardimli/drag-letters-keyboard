<?php
	
	session_start();
	
	$settings = require 'settings.php';
	
	// --- NEW: Load Path Settings ---
	// Fallback to defaults if not set in settings.php
	$paths = $settings['paths'] ?? [
		'upload_dir' => 'assets/uploads/',
		'audio_dir' => 'assets/audio/',
		'words_json' => 'assets/words.json',
		'upload_url' => 'assets/uploads/',
		'audio_url' => 'assets/audio/',
	];
	
	$jsonFile = $paths['words_json'];
	$uploadDir = $paths['upload_dir'];
	$audioDir = $paths['audio_dir'];
	$uploadUrl = $paths['upload_url'];
	$audioUrl = $paths['audio_url'];
	
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
	
	// Include Helper Functions
	require_once 'includes/functions.php';
	
	// --- 0. Thumbnail Check (Run on load) ---
	$dataChanged = false;
	foreach ($data['words'] as &$word) {
// Check if image exists using the stored URL path converted to physical path
// Note: This logic assumes the stored path is relative.
// If stored path is 'assets/uploads/img.jpg', we map it to physical.
		$storedImg = $word['image'] ?? '';
		if (!empty($storedImg)) {
			$fileName = basename($storedImg);
			$physicalImgPath = $uploadDir . $fileName;
			
			if (file_exists($physicalImgPath)) {
				$thumbName = pathinfo($fileName, PATHINFO_FILENAME) . '_thumb.jpg';
				$physicalThumbPath = $uploadDir . $thumbName;
				$webThumbPath = $uploadUrl . $thumbName;
				
				if (empty($word['thumb']) || !file_exists($physicalThumbPath)) {
					if (createThumbnail($physicalImgPath, $physicalThumbPath, 256, 256)) {
						$word['thumb'] = $webThumbPath;
						$dataChanged = true;
					}
				}
			}
		}
	}
	unset($word);
	if ($dataChanged) {
		file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}
	
	// --- 1. Handle AJAX Requests ---
	require 'includes/api_handler.php';
	
	// --- 2. Authentication ---
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
		require 'views/login.php';
		exit;
	}
	
	// --- 3. Handle Form Submissions (POST) ---
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
			$imagePath = $_POST['current_image_path'] ?? ''; // This should be the URL path
			$audioPath = $_POST['current_audio_path'] ?? ''; // This should be the URL path
			$imagePrompt = $_POST['image_prompt'] ?? '';

// Handle File Upload
			if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
				$ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
				$filename = uniqid() . '.' . $ext;
				$physicalDest = $uploadDir . $filename;
				
				if (move_uploaded_file($_FILES['image_file']['tmp_name'], $physicalDest)) {
					$imagePath = $uploadUrl . $filename;
				}
			}

// Handle Thumbnail Generation
			$thumbPath = '';
			if (!empty($imagePath)) {
				$fileName = basename($imagePath);
				$physicalImg = $uploadDir . $fileName;
				
				if (file_exists($physicalImg)) {
					$thumbName = pathinfo($fileName, PATHINFO_FILENAME) . '_thumb.jpg';
					$physicalThumb = $uploadDir . $thumbName;
					
					createThumbnail($physicalImg, $physicalThumb, 256, 256);
					$thumbPath = $uploadUrl . $thumbName;
				}
			}

// Auto-generate audio if missing and key exists
			if (empty($audioPath) && !empty($text) && !empty($settings['gemini_api_key'])) {
				$spelled = implode(', ', str_split($text));
				$prompt = "Spell: " . $spelled . "\nSay cheerfully: " . $text;

// Generate to physical path
				$newAudioPhysical = generateAudio($prompt, $settings['gemini_api_key'], $audioDir);
				if ($newAudioPhysical) {
// Convert to URL path
					$audioPath = $audioUrl . basename($newAudioPhysical);
				}
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

// Redirect logic
			$rPage = $_POST['page'] ?? 1;
			$rQuery = $_POST['q'] ?? '';
			$redirectUrl = "admin.php?view=list&page=" . urlencode($rPage);
			if (!empty($rQuery)) {
				$redirectUrl .= "&q=" . urlencode($rQuery);
			}
			
			header("Location: " . $redirectUrl);
			exit;
		}

// C. Save Batch Words
		if (isset($_POST['action']) && $_POST['action'] === 'save_batch') {
			$texts = $_POST['batch_text'] ?? [];
			$prompts = $_POST['batch_prompt'] ?? [];
			$lang = $_POST['batch_lang'];
			
			foreach ($texts as $idx => $text) {
				$text = strtoupper(trim($text));
				$prompt = $prompts[$idx] ?? '';

// MODIFIED: Do NOT auto-generate audio here.
// The user will use the "Auto-Fill Missing Assets" tool or generate individually.
				$audioPath = '';
				
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

// Resolve URL paths to physical paths for deletion
				if (!empty($w['image'])) {
					$p = $uploadDir . basename($w['image']);
					if (file_exists($p)) unlink($p);
				}
				if (!empty($w['thumb'])) {
					$p = $uploadDir . basename($w['thumb']);
					if (file_exists($p)) unlink($p);
				}
				if (!empty($w['audio'])) {
					$p = $audioDir . basename($w['audio']);
					if (file_exists($p)) unlink($p);
				}
				
				array_splice($data['words'], $index, 1);
				file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			}
			header("Location: admin.php");
			exit;
		}
	}
	
	// --- 4. View Logic ---
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
	if ($page < 1) {
		$page = 1;
	}
	if ($page > $totalPages && $totalPages > 0) {
		$page = $totalPages;
	}
	$offset = ($page - 1) * $perPage;
	$displayWords = array_slice($filteredWords, $offset, $perPage);
	
	// --- 5. Render Page ---
	require 'views/header.php';
?>
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
	
	<?php
		if ($view === 'list') {
			require 'views/list.php';
		} elseif ($view === 'generator') {
			require 'views/generator.php';
		}
	?>
</div>
<?php
	require 'views/modals.php';
	require 'views/scripts.php';
?>
</body>
</html>
