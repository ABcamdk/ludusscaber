<?php
$result = null;
$error = null;
$url = $_POST['ludus_url'] ?? 'https://bcsyd.luduseg.dk/ui/main';
$username = $_POST['username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $weeks = (int)($_POST['weeks'] ?? 2);

    $ch = curl_init('http://127.0.0.1:3001/scrape');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'url' => trim($url),
            'username' => trim($username),
            'password' => $password,
            'weeksToScrape' => $weeks
        ]),
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        $error = 'Kunne ikke forbinde til scraper-robotten.';
    } else {
        $data = json_decode($response, true);
        if ($httpCode === 200 && !empty($data['success'])) {
            $result = $data['weeks'];
        } else {
            $error = $data['error'] ?? 'Ukendt fejl ved hentning af data.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderne Ludus Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans">

<div class="max-w-7xl mx-auto px-4 py-10">
    <header class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Ludus <span class="text-indigo-400">Next</span></h1>
            <p class="text-slate-400 text-sm">Moderne oversigt over dit skema</p>
        </div>
        <?php if ($result): ?>
            <a href="" class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm font-medium transition">
                Log ud / Skift bruger
            </a>
        <?php endif; ?>
    </header>

    <?php if (!$result): ?>
        <!-- Login Formular -->
        <div class="max-w-md mx-auto bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h2 class="text-xl font-semibold mb-4">Log ind på Ludus</h2>

            <?php if ($error): ?>
                <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/40 text-red-300 text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" class="space-y-4">
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Ludus URL</label>
                    <input type="url" name="ludus_url" required value="<?= htmlspecialchars($url) ?>"
                           placeholder="https://bcsyd.luduseg.dk/ui/main"
                           class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 focus:outline-none focus:border-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Brugernavn</label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($username) ?>"
                           class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 focus:outline-none focus:border-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Adgangskode</label>
                    <input type="password" name="password" required
                           class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 focus:outline-none focus:border-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Antal uger</label>
                    <select name="weeks" class="w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm">
                        <option value="1">1 uge (denne uge)</option>
                        <option value="2" selected>2 uger</option>
                        <option value="3">3 uger</option>
                    </select>
                </div>
                <button type="submit" id="submitBtn"
                        class="w-full py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 font-medium text-white transition">
                    Hent mit skema
                </button>
            </form>
        </div>

        <script>
            document.getElementById('loginForm').addEventListener('submit', function() {
                const btn = document.getElementById('submitBtn');
                btn.disabled = true;
                btn.innerText = 'Robotten logger ind og henter data... (5-15 sek)';
                btn.classList.add('opacity-70', 'cursor-not-allowed');
            });
        </script>

    <?php else: ?>
        <!-- Moderne Skema-visning -->
        <?php foreach ($result as $week): ?>
            <section class="mb-12">
                <h2 class="text-2xl font-bold mb-4 text-indigo-300"><?= htmlspecialchars($week['weekTitle']) ?></h2>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <?php foreach ($week['schedule'] as $day => $lessons): ?>
                        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 flex flex-col">
                            <h3 class="font-semibold text-slate-200 pb-3 mb-3 border-b border-slate-800">
                                <?= htmlspecialchars($day) ?>
                            </h3>
                            <div class="space-y-3 flex-1">
                                <?php if (empty($lessons)): ?>
                                    <p class="text-xs text-slate-500 italic">Ingen lektioner</p>
                                <?php else: ?>
                                    <?php foreach ($lessons as $lesson):
                                        $cardClass = 'bg-slate-800/80 border-slate-700';
                                        $badge = '';
                                        if ($lesson['status'] === 'aendret') {
                                            $cardClass = 'bg-amber-500/10 border-amber-500/40';
                                            $badge = '<span class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300">Ændret</span>';
                                        } elseif ($lesson['status'] === 'aflyst') {
                                            $cardClass = 'bg-red-500/10 border-red-500/40 opacity-75 line-through';
                                            $badge = '<span class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded bg-red-500/20 text-red-300 no-underline">Aflyst</span>';
                                        }
                                    ?>
                                        <div class="p-3 rounded-lg border <?= $cardClass ?> transition hover:border-indigo-400/50">
                                            <div class="flex items-start justify-between gap-2 mb-1">
                                                <span class="font-semibold text-sm text-white leading-tight">
                                                    <?= htmlspecialchars($lesson['subject']) ?>
                                                </span>
                                                <?= $badge ?>
                                            </div>
                                            <div class="text-xs text-slate-400 space-y-0.5">
                                                <?php if ($lesson['time']): ?>
                                                    <div>🕒 <?= htmlspecialchars($lesson['time']) ?></div>
                                                <?php endif; ?>
                                                <?php if ($lesson['room']): ?>
                                                    <div>📍 <?= htmlspecialchars($lesson['room']) ?></div>
                                                <?php endif; ?>
                                                <?php if ($lesson['teacher']): ?>
                                                    <div>👤 <?= htmlspecialchars($lesson['teacher']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($lesson['hasHomework']): ?>
                                                <div class="mt-2 inline-flex items-center text-[11px] font-medium text-indigo-300 bg-indigo-500/15 px-2 py-0.5 rounded">
                                                    📖 Lektier
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>