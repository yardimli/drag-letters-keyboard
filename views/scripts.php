<script>
	// --- Common Elements ---
	const modal = document.getElementById('wordModal');
	const audioModal = document.getElementById('audioModal');
	const progressModal = document.getElementById('progressModal');

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

	// Progress Modal Elements
	const progressBar = document.getElementById('progressBar');
	const progressText = document.getElementById('progressText');
	const processLog = document.getElementById('processLog');
	const btnStopProcess = document.getElementById('btnStopProcess');
	const btnFinishProcess = document.getElementById('btnFinishProcess');

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

	// --- NEW: Auto Generate Missing (Queue System) ---
	let taskQueue = [];
	let isProcessing = false;
	let stopRequested = false;
	let totalTasks = 0;
	let completedTasks = 0;

	async function autoGenerateMissing() {
		// 1. Reset UI
		progressModal.style.display = 'block';
		processLog.innerHTML = '';
		progressBar.style.width = '0%';
		progressText.innerText = 'Initializing...';
		btnStopProcess.style.display = 'inline-block';
		btnStopProcess.innerText = 'Stop';
		btnFinishProcess.style.display = 'none';
		stopRequested = false;

		appendLog("Scanning for missing assets...", "#fff");

		// 2. Fetch Task List
		const formData = new FormData();
		formData.append('action', 'scan_missing_assets');

		try {
			const res = await fetch('admin.php', { method: 'POST', body: formData });
			const data = await res.json();

			if (data.success) {
				taskQueue = data.tasks;
				totalTasks = taskQueue.length;
				completedTasks = 0;

				if (totalTasks === 0) {
					appendLog("No missing assets found.", "#0f0");
					finishAutoProcess();
				} else {
					appendLog(`Found ${totalTasks} items to process.`, "#0cc");
					updateProgress();
					// Start Processing
					processNextTask();
				}
			} else {
				appendLog("Error scanning: " + data.error, "#f00");
			}
		} catch (e) {
			appendLog("Connection error during scan.", "#f00");
		}
	}

	async function processNextTask() {
		if (stopRequested) {
			appendLog("Process stopped by user.", "#fa0");
			finishAutoProcess();
			return;
		}

		if (taskQueue.length === 0) {
			appendLog("All tasks completed!", "#0f0");
			finishAutoProcess();
			return;
		}

		const task = taskQueue.shift(); // Get next task
		appendLog(`Processing: ${task.desc}...`, "#aaa");

		const formData = new FormData();
		formData.append('action', 'generate_single_asset');
		formData.append('index', task.index);
		formData.append('type', task.type);

		try {
			const res = await fetch('admin.php', { method: 'POST', body: formData });
			const data = await res.json();

			if (data.success) {
				appendLog(`[OK] ${task.text} (${task.type})`, "#0f0");
			} else {
				appendLog(`[FAIL] ${task.text}: ${data.message}`, "#f00");
			}
		} catch (e) {
			appendLog(`[ERR] Network error on ${task.text}`, "#f00");
		}

		completedTasks++;
		updateProgress();

		// Recursively call next
		processNextTask();
	}

	function stopAutoGeneration() {
		stopRequested = true;
		btnStopProcess.innerText = "Stopping...";
		btnStopProcess.disabled = true;
	}

	function finishAutoProcess() {
		btnStopProcess.style.display = 'none';
		btnFinishProcess.style.display = 'inline-block';
	}

	function appendLog(msg, color) {
		const div = document.createElement('div');
		div.innerText = msg;
		div.style.color = color || '#ccc';
		div.style.marginBottom = '4px';
		processLog.appendChild(div);
		processLog.scrollTop = processLog.scrollHeight;
	}

	function updateProgress() {
		const pct = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 100;
		progressBar.style.width = pct + '%';
		progressText.innerText = `${completedTasks} / ${totalTasks}`;
	}

	// --- NEW: Play Audio Helper ---
	function playAudio(url) {
		const audio = new Audio(url + "?t=" + new Date().getTime());
		audio.play();
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
		// Do not close progressModal on click outside
	}
</script>
