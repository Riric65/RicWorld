<?php
$file = __DIR__ . '/clicks.txt';

// Initialisation si le fichier n'existe pas
if (!file_exists($file)) file_put_contents($file, '0');

// --- LOGIQUE SÉCURISÉE (AJAX) ---
if (isset($_GET['ajax'])) {
    $current = (int)file_get_contents($file);

    // Si on essaie de cliquer
    if (isset($_GET['new_val'])) {
        $attempt = (int)$_GET['new_val'];

        // SÉCURITÉ : Strictement égal à l'ancien + 1
        if ($attempt === $current + 1) {
            file_put_contents($file, (string)$attempt);
            echo $attempt;
        } else {
            // Triche ou clic simultané : on refuse et on donne la vraie valeur
            http_response_code(403);
            echo $current;
        }
    } else {
        // Simple lecture pour la synchro
        echo $current;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Button</title>
    
    <!-- FAVICON CERCLE ROUGE -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2250%22 fill=%22red%22/></svg>">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        body {
            margin: 0; background-color: #000; color: white;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            height: 100vh; font-family: 'Arial Black', sans-serif;
            overflow: hidden;
        }

        .btn {
            background: #fff; border: none; border-radius: 50%;
            width: 400px; height: 400px;
            display: flex; justify-content: center; align-items: center;
            cursor: pointer; 
            box-shadow: 0 0 80px rgba(255, 0, 0, 0.4);
            transition: transform 0.05s;
            outline: none;
            -webkit-tap-highlight-color: transparent;
        }

        .btn:active { transform: scale(0.85); }
        .btn i { font-size: 300px; color: #ff0000; }

        .counter-container { text-align: center; margin-top: 30px; }
        #count-val { font-size: 8rem; font-weight: 900; color: #ff0000; line-height: 0.8; }
        .label { color: #333; text-transform: uppercase; letter-spacing: 12px; font-size: 1.2rem; display: block; margin-top: 15px; }
    </style>
</head>
<body>

    <button id="theButton" class="btn">
        <i class="fa-solid fa-circle"></i>
    </button>

    <div class="counter-container">
        <span id="count-val">0</span>
        <span class="label">SECURE CLICKS</span>
    </div>

    <script>
        const display = document.getElementById('count-val');
        
        async function sync(target = null) {
            const url = target ? `?ajax=1&new_val=${target}` : `?ajax=1`;
            try {
                const res = await fetch(url);
                const val = await res.text();
                if (!isNaN(val)) {
                    display.innerText = val;
                }
            } catch (e) {}
        }

        document.getElementById('theButton').onclick = () => {
            let next = (parseInt(display.innerText) || 0) + 1;
            // On demande au serveur de valider le passage au nombre suivant
            sync(next);
        };

        // TON DÉLAI DE 200MS
        setInterval(() => sync(), 200);
        sync();
    </script>
</body>
</html>
