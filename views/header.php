<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Word Manager</title>
	<style>
      body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #1e1e1e; color: #e0e0e0; padding: 20px; margin: 0; }
      .container { max-width: 1000px; margin: 0 auto; background: #2d2d2d; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); }

      h1, h3 { margin-top: 0; color: #00ccff; }
      a { color: #ff4444; text-decoration: none; font-weight: bold; }

      /* Navigation */
      .nav-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #444; padding-bottom: 10px; }
      .nav-tab { padding: 10px 20px; background: #333; color: #aaa; text-decoration: none; border-radius: 4px; }
      .nav-tab.active { background: #00ccff; color: #000; font-weight: bold; }
      .nav-tab:hover:not(.active) { background: #444; }

      table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #333; border-radius: 8px; overflow: hidden; }
      th, td { padding: 15px; border-bottom: 1px solid #444; text-align: left; }
      th { background: #444; color: #fff; font-weight: 600; }
      tr:hover { background: #3a3a3a; }

      .btn { padding: 8px 16px; text-decoration: none; color: white; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; transition: 0.2s; }
      .btn-edit { background: #ffaa00; color: #000; }
      .btn-delete { background: #ff4444; }
      .btn-add { background: #00cc44; padding: 12px 24px; font-size: 16px; font-weight: bold; }
      .btn-gen { background: #00ccff; color: #000; font-weight: bold; margin-top: 5px; }
      .btn-save { background: #00cc44; width: 100%; padding: 12px; font-size: 16px; margin-top: 20px; }
      .btn-audio { background: #9b59b6; color: white; font-weight: bold; }
      .btn-audio-small { background: #9b59b6; color: white; padding: 4px 8px; font-size: 12px; margin-left: 5px; }

      input[type="text"], select, input[type="file"], textarea {
          width: 100%; padding: 10px; box-sizing: border-box;
          background: #444; border: 1px solid #555; color: white; border-radius: 4px; margin-bottom: 10px;
      }

      .preview-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #555; }

      /* Search & Pagination */
      .toolbar { display: flex; justify-content: space-between; align-items: center; margin: 20px 0; }
      .search-box { display: flex; gap: 10px; }
      .pagination { display: flex; gap: 5px; justify-content: center; margin-top: 20px; }
      .page-link { padding: 8px 12px; background: #444; color: white; text-decoration: none; border-radius: 4px; }
      .page-link.active { background: #00ccff; color: #000; font-weight: bold; }

      /* Modal */
      .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; backdrop-filter: blur(3px); }
      .modal-content {
          background: #2d2d2d; margin: 5% auto; padding: 0; width: 800px;
          border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
          display: flex; overflow: hidden; border: 1px solid #444;
      }
      .col-left { flex: 1; padding: 25px; border-right: 1px solid #444; }
      .col-right { width: 300px; background: #222; padding: 25px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
      .close { cursor: pointer; font-size: 28px; color: #888; float: right; }

      #audioModal .modal-content { width: 500px; display: block; padding: 20px; }
      .lang-form { background: #333; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end; }
      #previewContainer { width: 100%; height: 250px; background: #1a1a1a; border: 2px dashed #444; display: flex; align-items: center; justify-content: center; }
      #previewImage { max-width: 100%; max-height: 100%; display: none; }

      /* Generator Styles */
      .gen-results { margin-top: 20px; }
      .gen-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #333; padding: 10px; border-radius: 4px; }
      .gen-row input { margin-bottom: 0; }
	</style>
</head>
<body>
