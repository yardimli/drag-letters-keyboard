<!-- Add/Edit Modal -->
<div id="wordModal" class="modal">
	<div class="modal-content">
		<form method="POST" enctype="multipart/form-data" style="display:flex; width:100%; margin:0;">
			<input type="hidden" name="action" value="save_word">
			<input type="hidden" name="index" id="editIndex" value="">

			<!-- NEW: Maintain Page State -->
			<input type="hidden" name="page" value="<?php echo htmlspecialchars($page ?? 1); ?>">
			<input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">

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

<!-- Progress Modal (New) -->
<div id="progressModal" class="modal" style="z-index: 1002;">
	<div class="modal-content" style="height:auto; width: 600px; display: block; padding: 20px;">
		<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
			<h3 style="margin:0;">Auto-Generate Progress</h3>
			<!-- No close button here to prevent accidental closing, user must use Stop/Done -->
		</div>

		<!-- Progress Bar -->
		<div style="background: #444; height: 20px; border-radius: 10px; overflow: hidden; margin-bottom: 10px;">
			<div id="progressBar" style="background: #00cc44; width: 0%; height: 100%; transition: width 0.3s;"></div>
		</div>
		<div style="text-align: right; font-size: 12px; color: #aaa; margin-bottom: 20px;">
			<span id="progressText">0 / 0</span>
		</div>

		<!-- Log Window -->
		<div id="processLog" style="background: #111; color: #0f0; font-family: monospace; height: 200px; overflow-y: auto; padding: 10px; border: 1px solid #444; border-radius: 4px; font-size: 12px; margin-bottom: 20px;">
			<div style="color: #aaa;">Waiting to start...</div>
		</div>

		<div style="display:flex; justify-content:flex-end; gap:10px;">
			<button type="button" class="btn btn-delete" id="btnStopProcess" onclick="stopAutoGeneration()">Stop</button>
			<button type="button" class="btn btn-save" id="btnFinishProcess" onclick="location.reload()" style="display:none; width: auto;">Done & Refresh</button>
		</div>
	</div>
</div>
