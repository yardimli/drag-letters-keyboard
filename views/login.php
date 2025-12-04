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
	<?php if (isset($error)) {
		echo "<div class='error'>$error</div>";
	} ?>
	<input type="password" name="password" placeholder="Enter Password" required>
	<button type="submit" name="login">Login</button>
</form>
</body>
</html>
