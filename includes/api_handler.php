<?php

// Only process if it's an AJAX request with an action
	if (isset($_POST['action'])) {
		if (!isset($_SESSION['is_admin'])) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'error' => 'Unauthorized']);
			exit;
		}

		// Ensure we have the path variables (inherited from admin.php)
		// $uploadDir, $audioDir, $uploadUrl, $audioUrl, $jsonFile

		// --- A. Generate Image Preview (FAL.AI) ---
		if ($_POST['action'] === 'generate_ai_preview') {
			header('Content-Type: application/json');
			$prompt = $_POST['prompt'] ?? '';
			if (empty($prompt)) {
				echo json_encode(['success' => false, 'error' => 'Prompt is required']);
				exit;
			}

			// Generate to physical path
			$physicalPath = generateImage($prompt, $settings['fal_api_key'], $uploadDir);

			if ($physicalPath) {
				// Return URL path
				$urlPath = $uploadUrl . basename($physicalPath);
				echo json_encode(['success' => true, 'url' => $urlPath]);
			} else {
				echo json_encode(['success' => false, 'error' => 'Failed to generate image']);
			}
			exit;
		}

		// --- B. Regenerate Audio ---
		if ($_POST['action'] === 'regenerate_audio') {
			header('Content-Type: application/json');
			$prompt = $_POST['prompt'] ?? '';
			$index = $_POST['index'] ?? '';

			if (empty($prompt)) {
				echo json_encode(['success' => false, 'error' => 'Prompt is required']);
				exit;
			}
			if (empty($settings['gemini_api_key'])) {
				echo json_encode(['success' => false, 'error' => 'Gemini API Key missing']);
				exit;
			}

			// Generate to physical path
			$physicalPath = generateAudio($prompt, $settings['gemini_api_key'], $audioDir);

			if ($physicalPath) {
				$urlPath = $audioUrl . basename($physicalPath);

				// If index is provided, update the database immediately
				if ($index !== '' && isset($data['words'][$index])) {
					// Cleanup old audio if exists
					$oldAudioUrl = $data['words'][$index]['audio'] ?? '';
					if (!empty($oldAudioUrl)) {
						$oldPhysical = $audioDir . basename($oldAudioUrl);
						if (file_exists($oldPhysical)) {
							unlink($oldPhysical);
						}
					}

					$data['words'][$index]['audio'] = $urlPath;
					file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
				}
				echo json_encode(['success' => true, 'url' => $urlPath]);
			} else {
				echo json_encode(['success' => false, 'error' => 'Failed to generate audio']);
			}
			exit;
		}

		// --- C. Scan for Missing Assets (Step 1 of Auto-Gen) ---
		if ($_POST['action'] === 'scan_missing_assets') {
			header('Content-Type: application/json');
			$tasks = [];

			foreach ($data['words'] as $idx => $word) {
				// 1. Check Audio
				// We check if the file exists physically based on the stored URL
				$audioExists = false;
				if (!empty($word['audio'])) {
					$physAudio = $audioDir . basename($word['audio']);
					if (file_exists($physAudio)) {
						$audioExists = true;
					}
				}

				if (!$audioExists) {
					$tasks[] = [
						'index' => $idx,
						'type' => 'audio',
						'text' => $word['text'],
						'desc' => "Generate Audio for '{$word['text']}'"
					];
				}

				// 2. Check Image (only if prompt exists)
				$imageExists = false;
				if (!empty($word['image'])) {
					$physImage = $uploadDir . basename($word['image']);
					if (file_exists($physImage)) {
						$imageExists = true;
					}
				}

				$hasPrompt = !empty($word['image_prompt']);

				if (!$imageExists && $hasPrompt) {
					$tasks[] = [
						'index' => $idx,
						'type' => 'image',
						'text' => $word['text'],
						'desc' => "Generate Image for '{$word['text']}'"
					];
				}
			}

			echo json_encode(['success' => true, 'tasks' => $tasks]);
			exit;
		}

		// --- D. Generate Single Asset (Step 2 of Auto-Gen) ---
		if ($_POST['action'] === 'generate_single_asset') {
			header('Content-Type: application/json');
			$index = $_POST['index'] ?? null;
			$type = $_POST['type'] ?? '';

			if ($index === null || !isset($data['words'][$index])) {
				echo json_encode(['success' => false, 'error' => 'Invalid word index']);
				exit;
			}

			$word = &$data['words'][$index];
			$success = false;
			$message = "";

			if ($type === 'audio') {
				$text = $word['text'];
				$spelled = implode(', ', str_split($text));
				$prompt = "Spell: " . $spelled . "\nSay cheerfully: " . $text;

				$newAudioPhys = generateAudio($prompt, $settings['gemini_api_key'], $audioDir);
				if ($newAudioPhys) {
					$word['audio'] = $audioUrl . basename($newAudioPhys);
					$success = true;
					$message = "Audio generated successfully.";
				} else {
					$message = "Failed to generate audio.";
				}
			} elseif ($type === 'image') {
				if (!empty($word['image_prompt'])) {
					$newImagePhys = generateImage($word['image_prompt'], $settings['fal_api_key'], $uploadDir);
					if ($newImagePhys) {
						$word['image'] = $uploadUrl . basename($newImagePhys);

						// Create thumb
						$pathInfo = pathinfo($newImagePhys);
						$thumbName = $pathInfo['filename'] . '_thumb.jpg';
						$thumbPhys = $pathInfo['dirname'] . '/' . $thumbName;

						createThumbnail($newImagePhys, $thumbPhys, 256, 256);
						$word['thumb'] = $uploadUrl . $thumbName;

						$success = true;
						$message = "Image generated successfully.";
					} else {
						$message = "Failed to generate image.";
					}
				} else {
					$message = "No image prompt found.";
				}
			}

			if ($success) {
				// Save immediately to persist progress
				file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			}

			echo json_encode(['success' => $success, 'message' => $message]);
			exit;
		}

		// --- E. Generate Word List (LLM) ---
		if ($_POST['action'] === 'generate_word_list') {
			header('Content-Type: application/json');
			$topic = $_POST['topic'] ?? '';
			$lang = $_POST['lang'] ?? 'en';

			if (empty($topic)) {
				echo json_encode(['success' => false, 'error' => 'Topic is required']);
				exit;
			}

			// 1. Scan for existing categories in this language
			$existingCategories = [];
			foreach ($data['words'] as $w) {
				if (isset($w['lang']) && $w['lang'] === $lang) {
					$cat = $w['category'] ?? 'Default';
					if (!in_array($cat, $existingCategories)) {
						$existingCategories[] = $cat;
					}
				}
			}
			$catStr = implode(', ', $existingCategories);

			$apiKey = $settings['gemini_api_key'];
			$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

			$systemPrompt = "You are a helper that generates vocabulary lists for a spelling game. Return ONLY valid JSON. No markdown formatting.";
			$userPrompt = "Generate a list of 10 simple, single words related to the topic '$topic' in language '$lang'.
Also provide a short visual description for an image generator for each word.
Assign a category to each word.
Existing categories in this language are: [$catStr]. Reuse these if they fit, otherwise create a new short category name.
Format: [{\"text\": \"WORD\", \"category\": \"CATEGORY\", \"image_prompt\": \"visual description\"}].
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
			$finalList = [];
			foreach ($generatedWords as $gw) {
				$isDuplicate = false;
				foreach ($data['words'] as $existing) {
					if ($existing['text'] === $gw['text'] && $existing['lang'] === $lang) {
						$isDuplicate = true;
						break;
					}
				}
				if (!$isDuplicate) {
					// Ensure category exists
					if (empty($gw['category'])) {
						$gw['category'] = 'Default';
					}
					$finalList[] = $gw;
				}
			}

			echo json_encode(['success' => true, 'words' => $finalList]);
			exit;
		}
	}
