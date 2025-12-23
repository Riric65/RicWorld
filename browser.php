<?php
$stateFile = __DIR__ . '/master_state.json';

// --- SYNCHRONISATION ---
if (isset($_GET['sync'])) {
    header('Content-Type: application/json');
    if (!file_exists($stateFile)) file_put_contents($stateFile, json_encode(["u" => "https://www.bing.com", "s" => 0, "k" => ""]));
    $state = json_decode(file_get_contents($stateFile), true);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $update = json_decode(file_get_contents('php://input'), true);
        $state = array_merge($state, $update);
        file_put_contents($stateFile, json_encode($state));
    }
    echo json_encode($state);
    exit;
}

// --- LE PROXY TRANSPARENT (Le Pi "aspire" et "nettoie") ---
if (isset($_GET['proxy'])) {
    $url = $_GET['proxy'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    // On récupère les headers originaux pour les modifier
    curl_setopt($ch, CURLOPT_HEADER, true);
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $header_size);
    $realUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    // SUPPRESSION DES SÉCURITÉS ANTI-IFRAME
    header_remove("Content-Security-Policy");
    header_remove("X-Frame-Options");
    header_remove("X-Content-Type-Options");
    header("Access-Control-Allow-Origin: *"); // Autorise tout pour éviter tes erreurs rouges

    $base = parse_url($realUrl, PHP_URL_SCHEME) . '://' . parse_url($realUrl, PHP_URL_HOST);

    // INJECTION DU CERVEAU DE SYNCHRO
    $inject = "
    <base href='$base/'>
    <script>
        // Synchronisation du Scroll
        window.onscroll = () => {
            if(window.isSyncing) return;
            const p = window.scrollY / (document.documentElement.scrollHeight - window.innerHeight);
            fetch('browser.php?sync=1', {method:'POST', body: JSON.stringify({s: p.toFixed(4)})});
        };

        // Interception des liens
        document.addEventListener('click', e => {
            const a = e.target.closest('a');
            if(a && a.href && !a.href.includes('javascript')) {
                e.preventDefault();
                parent.updateMaster(a.href);
            }
        }, true);

        // Réception des ordres
        window.applyState = (d) => {
            window.isSyncing = true;
            const target = d.s * (document.documentElement.scrollHeight - window.innerHeight);
            if(Math.abs(window.scrollY - target) > 50) window.scrollTo({top: target, behavior:'smooth'});
            setTimeout(() => window.isSyncing = false, 150);
        };
    </script>";

    echo str_replace('<head>', '<head>' . $inject, $body);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>RIC-MASTER PROXY V6</title>
    <style>
        body, html { margin: 0; padding: 0; height: 100%; background: #000; overflow: hidden; font-family: sans-serif; }
        #nav { display: flex; background: #111; padding: 10px; border-bottom: 2px solid red; align-items: center; gap: 10px; }
        input { flex: 1; padding: 10px; background: #222; border: 1px solid #444; color: white; border-radius: 5px; }
        iframe { width: 100%; height: calc(100vh - 62px); border: none; background: white; }
    </style>
</head>
<body>
    <div id="nav">
        <input type="text" id="urlIn" placeholder="Entrez une URL...">
        <button onclick="updateMaster(document.getElementById('urlIn').value)" style="padding:10px; background:red; color:white; border:none; border-radius:5px; cursor:pointer;">GO</button>
    </div>
    <iframe id="view" src=""></iframe>

    <script>
        const frame = document.getElementById('view');
        let currentU = "";

        async function updateMaster(u) {
            if(!u.startsWith('http')) u = 'https://' + u;
            await fetch('?sync=1', {method:'POST', body: JSON.stringify({u: u, s: 0})});
        }
        window.updateMaster = updateMaster;

        setInterval(async () => {
            const r = await fetch('?sync=1');
            const d = await r.json();
            if(d.u !== currentU) {
                currentU = d.u;
                document.getElementById('urlIn').value = d.u;
                frame.src = '?proxy=' + encodeURIComponent(d.u);
            }
            if(frame.contentWindow.applyState) frame.contentWindow.applyState(d);
        }, 400);
    </script>
</body>
</html>
