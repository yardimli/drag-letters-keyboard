<?php if ($view === 'list'): ?>
	<!-- LIST VIEW -->

	<!-- Add Language Form -->
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

	<!-- Toolbar: Add Word, Auto-Fill, Search -->
	<div class="toolbar">
		<div style="display:flex; gap: 10px;">
			<button class="btn btn-add" onclick="openModal()">+ Add New Word</button>
			<!-- NEW: Auto Generate Button -->
			<button class="btn btn-gen" onclick="autoGenerateMissing()" style="background: #9b59b6; color: white;">
				&#9881; Auto-Fill Missing Assets
			</button>
		</div>

		<form class="search-box" method="GET">
			<input type="hidden" name="view" value="list">
			<input type="text" name="q" placeholder="Search words..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="margin:0; width: 200px;">
			<button type="submit" class="btn btn-gen" style="margin:0;">Search</button>
			<?php if ($searchQuery): ?>
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
			<th width="250">Actions</th>
		</tr>
		</thead>
		<tbody>
		<?php if (count($displayWords) > 0): ?>
			<?php foreach ($displayWords as $word): ?>
				<?php $realIndex = $word['original_index']; ?>
				<tr>
					<td>
						<?php $imgSrc = !empty($word['thumb']) ? $word['thumb'] : $word['image']; ?>
						<?php if (!empty($imgSrc)): ?>
							<img src="<?php echo htmlspecialchars($imgSrc); ?>?t=<?php echo time(); ?>" class="preview-thumb">
						<?php else: ?>
							<div style="width:50px; height:50px; background:#444;"></div>
						<?php endif; ?>
					</td>
					<td>
						<strong><?php echo htmlspecialchars($word['text']); ?></strong>
						<?php if (!empty($word['image_prompt'])): ?>
							<div style="font-size: 10px; color: #888;">Prompt: <?php echo htmlspecialchars(substr($word['image_prompt'], 0, 30)); ?>...</div>
						<?php endif; ?>
					</td>
					<td><?php echo htmlspecialchars($data['languages'][$word['lang']] ?? $word['lang']); ?></td>
					<td>
						<?php if (!empty($word['audio'])): ?>
							<div style="display: flex; align-items: center; gap: 5px;">
								<span style="color:#00cc44;">&#10004;</span>
								<!-- NEW: Play Button -->
								<button type="button" class="btn btn-audio-small" onclick="playAudio('<?php echo $word['audio']; ?>')">
									&#9658;
								</button>
							</div>
						<?php else: ?>
							<span style="color:#666;">-</span>
						<?php endif; ?>
					</td>
					<td>
						<button class="btn btn-edit" onclick='editWord(<?php echo json_encode($word); ?>, <?php echo $realIndex; ?>)'>Edit</button>
						<!-- NEW: Generate Audio Button (Opens Modal) -->
						<button class="btn btn-audio-small" onclick='openAudioModalForList("<?php echo $word['text']; ?>", <?php echo $realIndex; ?>)'>
							Gen Audio
						</button>
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

<?php endif; ?>
