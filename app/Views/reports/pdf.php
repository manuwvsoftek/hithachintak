<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: sans-serif; font-size: 10px; color: #1a1a1a; }
  h1 { font-size: 15px; margin: 0 0 2px; }
  .meta { font-size: 9px; color: #666; margin-bottom: 12px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
  th { background: #eef2f8; font-weight: bold; }
  tr:nth-child(even) td { background: #fafafa; }
</style>
</head>
<body>
  <h1>Hithachintak Abhiyan — Report</h1>
  <div class="meta">Generated <?= esc($generatedAt) ?> &middot; <?= count($rows) ?> record<?= count($rows) !== 1 ? 's' : '' ?></div>
  <table>
    <thead>
      <tr>
        <?php foreach ($fields as $f): ?>
          <th><?= esc($fieldCatalog[$f]) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <?php foreach ($fields as $f): ?>
            <td><?= esc($fieldValue($r, $f)) ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (! $rows): ?>
        <tr><td colspan="<?= count($fields) ?>">No records match these filters.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
