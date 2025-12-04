<?php if ($view === 'generator'): ?>
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
