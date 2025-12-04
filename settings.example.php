<?php

	return [
// Admin Password
		'admin_password' => 'Secret',

// Fal.ai API Key
		'fal_api_key' => '...',

// Google Gemini API Key
		'gemini_api_key' => '...',

// Define where files are stored on disk and how they are accessed via URL.
		'paths' => [
			'upload_dir' => __DIR__ . '/assets/uploads/',
			'audio_dir' => __DIR__ . '/assets/audio/',
			'words_json' => __DIR__ . '/assets/words.json',

			'upload_url' => 'assets/uploads/',
			'audio_url' => 'assets/audio/',
		],
	];
