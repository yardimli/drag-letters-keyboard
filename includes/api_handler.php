<?php

// Only process if it's an AJAX request with an action
	if (isset($_POST['action'])) {
		if (!isset($_SESSION['is_admin'])) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'error' => 'Unauthorized']);
			exit;
		}

		// --- A. Generate Image Preview (FAL.AI) ---
		if ($_POST['action'] === 'generate_ai_preview') {
			header('Content-Type: application/json');
			$prompt = $_POST['prompt'] ?? '';
			if (empty($prompt)) {
				echo json_encode(['success' => false, 'error' => 'Prompt is required']);
				exit;
			}

			$outputPath = generateImage($prompt, $settings['fal_api_key'], $uploadDir);

			if ($outputPath) {
				echo json_encode(['success' => true, 'url' => $outputPath]);
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

			$audioPath = generateAudio($prompt, $settings['gemini_api_key'], $audioDir);

			if ($audioPath) {
				// If index is provided, update the database immediately
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

		// --- C. Scan for Missing Assets (Step 1 of Auto-Gen) ---
		if ($_POST['action'] === 'scan_missing_assets') {
			header('Content-Type: application/json');
			$tasks = [];

			foreach ($data['words'] as $idx => $word) {
				// 1. Check Audio
				if (empty($word['audio']) || !file_exists($word['audio'])) {
					$tasks[] = [
						'index' => $idx,
						'type' => 'audio',
						'text' => $word['text'],
						'desc' => "Generate Audio for '{$word['text']}'"
					];
				}

				// 2. Check Image (only if prompt exists)
				$hasImage = !empty($word['image']) && file_exists($word['image']);
				$hasPrompt = !empty($word['image_prompt']);

				if (!$hasImage && $hasPrompt) {
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

				$newAudio = generateAudio($prompt, $settings['gemini_api_key'], $audioDir);
				if ($newAudio) {
					$word['audio'] = $newAudio;
					$success = true;
					$message = "Audio generated successfully.";
				} else {
					$message = "Failed to generate audio.";
				}
			} elseif ($type === 'image') {
				if (!empty($word['image_prompt'])) {
					$newImage = generateImage($word['image_prompt'], $settings['fal_api_key'], $uploadDir);
					if ($newImage) {
						$word['image'] = $newImage;
						// Create thumb
						$pathInfo = pathinfo($newImage);
						$thumbPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.jpg';
						createThumbnail($newImage, $thumbPath, 256, 256);
						$word['thumb'] = $thumbPath;
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
					$finalList[] = $gw;
				}
			}

			echo json_encode(['success' => true, 'words' => $finalList]);
			exit;
		}
	}
