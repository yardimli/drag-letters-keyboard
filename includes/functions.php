<?php

	/**
	 * Generates a thumbnail from a source image.
	 *
	 * @param string $sourcePath
	 * @param string $destPath
	 * @param int $width
	 * @param int $height
	 * @return bool
	 */
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
		if (!$source) {
			return false;
		}

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

	/**
	 * Generates TTS Audio using Google Gemini.
	 *
	 * @param string $prompt
	 * @param string $apiKey
	 * @param string $outputDir
	 * @return string|null Path to the generated file or null.
	 */
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

		if ($httpCode !== 200) {
			return null;
		}

		$json = json_decode($response, true);
		if (isset($json['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
			$pcmData = base64_decode($json['candidates'][0]['content']['parts'][0]['inlineData']['data']);
			$tempPcm = tempnam(sys_get_temp_dir(), 'tts_') . '.pcm';
			file_put_contents($tempPcm, $pcmData);

			$filename = uniqid('audio_') . '.mp3';
			$outputPath = $outputDir . $filename;

			// Convert PCM to MP3 using ffmpeg
			$cmd = "ffmpeg -y -f s16le -ar 24000 -ac 1 -i " . escapeshellarg($tempPcm) . " " . escapeshellarg($outputPath) . " 2>&1";
			shell_exec($cmd);
			unlink($tempPcm);

			if (file_exists($outputPath)) {
				return $outputPath;
			}
		}
		return null;
	}

	/**
	 * Generates an Image using FAL.AI.
	 *
	 * @param string $prompt
	 * @param string $apiKey
	 * @param string $outputDir
	 * @return string|null Path to the generated file or null.
	 */
	function generateImage($prompt, $apiKey, $outputDir)
	{
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
				$outputPath = $outputDir . $filename;
				if (file_put_contents($outputPath, $imageContent)) {
					return $outputPath;
				}
			}
		}
		return null;
	}
