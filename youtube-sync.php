<?php
$stateFile = __DIR__ . '/party_state.json';
$chatFile = __DIR__ . '/party_chat.json';
$usersFile = __DIR__ . '/party_users.json';

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    if (!file_exists($usersFile)) file_put_contents($usersFile, json_encode([]));
    $users = json_decode(file_get_contents($usersFile), true) ?: [];
    $uid = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
    $users[$uid] = time();
    $users = array_filter($users, function($t) { return $t > time() - 15; });
    file_put_contents($usersFile, json_encode($users), LOCK_EX);
    if (!file_exists($stateFile)) file_put_contents($stateFile, json_encode(["id" => "dQw4w9WgXcQ", "time" => 0, "state" => 2]));
    if (!file_exists($chatFile)) file_put_contents($chatFile, json_encode([]));
    $state = json_decode(file_get_contents($stateFile), true);
    $chat = json_decode(file_get_contents($chatFile), true);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['video'])) { $state = array_merge($state, $input['video']); file_put_contents($stateFile, json_encode($state), LOCK_EX); }
        if (isset($input['msg'])) { $chat[] = ["u" => htmlspecialchars($input['user']), "m" => htmlspecialchars($input['msg']), "t" => date('H:i')]; if (count($chat) > 30) array_shift($chat); file_put_contents($chatFile, json_encode($chat), LOCK_EX); }
    }
    echo json_encode(["state" => $state, "chat" => $chat, "count" => count($users)]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YouTube WWW</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2280%22>🍿</text></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body, html { margin: 0; padding: 0; height: 100%; width: 100%; background: #000; color: white; font-family: sans-serif; overflow: hidden; }
        
        #app { display: flex; height: 100vh; width: 100vw; }
        
        /* GAUCHE - VIDEO */
        #video-section { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        header { height: 60px; background: #111; display: flex; align-items: center; padding: 0 15px; gap: 10px; border-bottom: 2px solid red; }
        #ytUrl { flex: 1; padding: 10px; background: #000; border: 1px solid #333; color: white; border-radius: 4px; }
        
        #player-wrapper { flex: 1; display: flex; align-items: center; justify-content: center; background: #000; padding: 20px; position: relative; }
        
        /* Conteneur vidéo robuste */
        .video-box { 
            width: 100%; 
            height: 100%; 
            max-width: 1200px; 
            max-height: 675px; /* Force le 16:9 max */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #player { width: 100%; height: 100%; background: #111; border: 2px solid #222; }

        /* DROITE - CHAT */
        #sidebar { width: 350px; background: #0a0a0a; border-left: 1px solid #222; display: flex; flex-direction: column; flex-shrink: 0; }
        #status-bar { padding: 15px; background: red; font-weight: bold; text-align: center; font-size: 0.8rem; }
        #chat-messages { flex: 1; overflow-y: auto; padding: 15px; }
        .m { background: #1a1a1a; padding: 10px; border-radius: 5px; margin-bottom: 10px; border-left: 3px solid red; word-wrap: break-word; }
        .m b { color: red; font-size: 0.7rem; display: block; }
        #chat-input { padding: 10px; background: #111; display: flex; gap: 5px; }
        #chat-input input { flex: 1; padding: 10px; background: #000; border: 1px solid #333; color: white; border-radius: 3px; }
        
        button { background: red; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #ff3333; }
    </style>
</head>
<body>

<div id="app">
    <div id="video-section">
        <header>
            <input type="text" id="ytUrl" placeholder="Lien YouTube (watch?v=...)">
            <button onclick="changeVideo()">GO</button>
        </header>
        <div id="player-wrapper">
            <div class="video-box">
                <div id="player"></div>
            </div>
        </div>
    </div>

    <div id="sidebar">
        <div id="status-bar"><i class="fa-solid fa-users"></i> <span id="online">0</span> CONNECTÉS</div>
        <div id="chat-messages"></div>
        <div id="chat-input">
            <input type="text" id="msg" placeholder="Message..." onkeypress="if(event.key==='Enter') sendChat()">
            <button onclick="sendChat()"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<script>
    // Chargement asynchrone sécurisé de l'API
    var tag = document.createElement('script');
    tag.src = "https://www.youtube.com/iframe_api";
    var firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

    let player;
    let lastVideoId = "";
    let isReady = false;
    let isUpdating = false;
    let myNick = "User-" + Math.floor(Math.random() * 999);

    function onYouTubeIframeAPIReady() {
        player = new YT.Player('player', {
            videoId: 'dQw4w9WgXcQ',
            playerVars: { 
                'autoplay': 1, 
                'controls': 1, 
                'rel': 0, 
                'origin': window.location.origin,
                'widget_referrer': window.location.href 
            },
            events: {
                'onReady': (event) => { 
                    isReady = true; 
                    // LE TRICK : On force un redimensionnement pour faire apparaître l'iframe
                    window.dispatchEvent(new Event('resize'));
                },
                'onStateChange': (e) => { 
                    if (!isUpdating && isReady) syncPush({state: e.data, time: player.getCurrentTime()}); 
                }
            }
        });
    }

    async function syncPush(v) {
        try {
            await fetch('?ajax=1', { method: 'POST', body: JSON.stringify({ video: v }) });
        } catch(e){}
    }

    function changeVideo() {
        const url = document.getElementById('ytUrl').value;
        const id = url.split('v=')[1]?.split('&')[0];
        if (id) syncPush({ id: id, time: 0, state: 1 });
    }

    async function sendChat() {
        const m = document.getElementById('msg');
        if (!m.value.trim()) return;
        await fetch('?ajax=1', { method: 'POST', body: JSON.stringify({ user: myNick, msg: m.value }) });
        m.value = "";
    }

    // Boucle de Synchro
    setInterval(async () => {
        try {
            const r = await fetch('?ajax=1');
            const d = await r.json();

            document.getElementById('online').innerText = d.count;
            const box = document.getElementById('chat-messages');
            box.innerHTML = d.chat.map(m => `<div class="m"><b>${m.u} à ${m.t}</b>${m.m}</div>`).join('');

            if (!isReady || !player || !player.getPlayerState) return;

            const s = d.state;
            isUpdating = true;

            if (s.id && s.id !== lastVideoId) {
                player.loadVideoById(s.id);
                lastVideoId = s.id;
            }

            const myTime = player.getCurrentTime();
            if (Math.abs(myTime - s.time) > 1 && s.state === 1) {
                player.seekTo(s.time);
            }

            const myState = player.getPlayerState();
            if (myState !== s.state && s.state !== -1 && s.state !== 3) {
                if (s.state === 1) player.playVideo();
                if (s.state === 2) player.pauseVideo();
            }

            if (myState === 1) syncPush({ time: myTime, state: 1, id: lastVideoId || 'dQw4w9WgXcQ' });

            isUpdating = false;
        } catch (e) {}
    }, 500);
</script>

</body>
</html>
