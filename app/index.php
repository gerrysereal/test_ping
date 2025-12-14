<?php
// ==========================================
// BAGIAN BACKEND (HANDLING 2 MODE STREAMING)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'stream') {
    // Matikan buffering & set timeout unlimited agar bisa looping selamanya
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    @ob_end_clean();
    set_time_limit(0); // PENTING: Agar script tidak mati (timeout) saat ping lama

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    $ip = $_GET['ip'] ?? '';
    $port = intval($_GET['port'] ?? 80);
    $mode = $_GET['mode'] ?? 'paping'; // Mode: 'paping' atau 'tracert'

    // Fungsi kirim pesan SSE
    function sendMsg($msg, $type = 'normal', $section = 'paping')
    {
        echo "data: " . json_encode(['msg' => $msg, 'type' => $type, 'section' => $section]) . "\n\n";
        ob_flush();
        flush();
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_DOMAIN)) {
        sendMsg("Error: Invalid IP Address.", 'error', $mode);
        echo "retry: 100\n\n";
        exit;
    }

    $target = escapeshellcmd($ip);

    // =====================================
    // MODE 1: PAPING (INFINITE LOOP)
    // =====================================
    if ($mode == 'paping') {
        sendMsg("Initializing Continuous TCP Ping to $ip:$port...", 'header', 'paping');

        $seq = 1;
        // LOOP SELAMANYA (While True)
        while (true) {
            // Cek apakah klien (browser) memutus koneksi/klik Stop
            if (connection_aborted()) {
                break;
            }

            $starttime = microtime(true);
            $file      = @fsockopen($ip, $port, $errno, $errstr, 2);
            $stoptime  = microtime(true);
            $duration  = round(($stoptime - $starttime) * 1000, 2);

            if ($file) {
                fclose($file);
                sendMsg("Seq=$seq Connected to $ip: time={$duration}ms protocol=TCP port=$port", 'success', 'paping');
            } else {
                sendMsg("Seq=$seq Connection timed out to $ip", 'error', 'paping');
            }

            $seq++;
            usleep(1000000); // Jeda 1 detik setiap ping (biar tidak flood server)
        }
        exit;
    }

    // =====================================
    // MODE 2: TRACEROUTE (RUN ONCE)
    // =====================================
    if ($mode == 'tracert') {
        sendMsg("Initializing traceroute...", 'normal', 'tracert');

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = "tracert -d $target";
            $displayCmd = "tracert -d $ip";
        } else {
            // Linux
            $cmd = "traceroute -n -w 1 -q 1 $target";
            $displayCmd = "traceroute -n $ip";
        }

        sendMsg("Command: $displayCmd", 'header', 'tracert');
        sendMsg("Tracing route to $ip over a maximum of 30 hops:", 'normal', 'tracert');

        $proc = popen("$cmd 2>&1", 'r');
        if ($proc) {
            while (!feof($proc)) {
                if (connection_aborted()) break; // Stop jika user klik stop

                $line = fgets($proc);
                if ($line) {
                    $line = trim($line);
                    if (!empty($line)) sendMsg($line, 'normal', 'tracert');
                }
            }
            pclose($proc);
        } else {
            sendMsg("Error: Could not execute traceroute.", 'error', 'tracert');
        }
        sendMsg("Trace complete.", 'header', 'tracert');
        sendMsg("DONE", 'close', 'tracert'); // Kirim sinyal selesai hanya untuk tracert
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Continuous Network Tool</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* ================= BACKGROUND BINTANG (Sama seperti sebelumnya) ================= */
        :root {
            --term-bg: #0c0c0c;
            --term-text: #cccccc;
            --term-green: #2ecc71;
            --term-red: #e74c3c;
            --term-blue: #3498db;
            --panel-bg: rgba(255, 255, 255, 0.97);
        }

        body {
            margin: 0;
            padding: 0;
            background: radial-gradient(ellipse at bottom, #1B2735 0%, #090A0F 100%);
            font-family: 'Inter', sans-serif;
            color: #333;
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }

        .star-field {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
        }

        .stars-small,
        .stars-medium,
        .stars-large {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: transparent;
        }

        .stars-small {
            width: 1px;
            height: 1px;
            box-shadow: 156px 231px #FFF, 834px 1024px #FFF, 1245px 543px #FFF, 42px 945px #FFF, 1654px 876px #FFF, 1123px 1456px #FFF, 1920px 234px #FFF, 567px 1234px #FFF, 890px 1567px #FFF, 1567px 1920px #FFF, 1890px 567px #FFF, 1345px 234px #FFF, 456px 789px #FFF, 234px 567px #FFF, 567px 345px #FFF, 789px 1234px #FFF, 456px 890px #FFF, 678px 234px #FFF, 123px 1567px #FFF, 890px 1890px #FFF, 234px 1345px #FFF, 567px 1678px #FFF, 345px 234px #FFF, 789px 678px #FFF, 1567px 567px #FFF, 1890px 345px #FFF;
            animation: animStar 150s linear infinite;
        }

        .stars-medium {
            width: 2px;
            height: 2px;
            box-shadow: 543px 1234px #FFF, 234px 1890px #FFF, 890px 1345px #FFF, 345px 1678px #FFF, 678px 1567px #FFF, 1890px 1789px #FFF, 1678px 123px #FFF, 456px 1456px #FFF, 234px 1234px #FFF, 789px 345px #FFF, 123px 890px #FFF, 890px 234px #FFF, 345px 678px #FFF;
            animation: animStar 100s linear infinite;
        }

        .stars-large {
            width: 3px;
            height: 3px;
            box-shadow: 1234px 456px #FFF, 1890px 890px #FFF, 1567px 234px #FFF, 1789px 1567px #FFF, 1345px 1678px #FFF;
            animation: animStar 50s linear infinite;
        }

        @keyframes animStar {
            from {
                transform: translateY(0px);
            }

            to {
                transform: translateY(-2000px);
            }
        }

        .stars-small::after,
        .stars-medium::after,
        .stars-large::after {
            content: " ";
            position: absolute;
            top: 2000px;
            width: inherit;
            height: inherit;
            background: transparent;
            box-shadow: inherit;
        }

        .rocket-container {
            position: fixed;
            bottom: 30px;
            right: 50px;
            z-index: -1;
            animation: floatRocket 6s ease-in-out infinite;
        }

        .rocket-svg {
            width: 120px;
            height: auto;
            filter: drop-shadow(0 0 15px rgba(52, 152, 219, 0.6));
        }

        @keyframes floatRocket {

            0%,
            100% {
                transform: translateY(0) rotate(5deg);
            }

            50% {
                transform: translateY(-25px) rotate(8deg);
            }
        }

        /* ================= UI UTAMA ================= */
        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .control-panel {
            background: var(--panel-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            margin-bottom: 25px;
            border-top: 5px solid var(--term-blue);
            backdrop-filter: blur(5px);
        }

        h2 {
            text-align: center;
            margin-top: 0;
            color: #2c3e50;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .input-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .input-group {
            flex: 1;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }

        input,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            background: #fff;
            color: #333;
            transition: 0.2s;
            box-sizing: border-box;
        }

        input:focus,
        select:focus {
            border-color: var(--term-blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        /* Tombol Start & Stop */
        .btn-group {
            display: flex;
            gap: 10px;
        }

        button {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            color: white;
        }

        #btnStart {
            background-color: var(--term-blue);
        }

        #btnStart:hover {
            background-color: #2980b9;
        }

        #btnStop {
            background-color: var(--term-red);
            display: none;
        }

        /* Default sembunyi */
        #btnStop:hover {
            background-color: #c0392b;
        }

        /* Terminal */
        .terminal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .terminal-grid {
                grid-template-columns: 1fr;
            }
        }

        .terminal-box {
            background-color: var(--term-bg);
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.6);
            overflow: hidden;
            border: 1px solid #2a2a2a;
            display: flex;
            flex-direction: column;
            height: 450px;
        }

        .term-header {
            background-color: #151515;
            color: #fff;
            padding: 10px 15px;
            font-size: 13px;
            font-weight: 600;
            border-bottom: 1px solid #2a2a2a;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Inter', sans-serif;
        }

        .status-indicator {
            font-size: 11px;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .term-body {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            font-family: 'Roboto Mono', 'Consolas', monospace;
            font-size: 13px;
            line-height: 1.5;
            color: var(--term-text);
        }

        .line {
            margin-bottom: 2px;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .text-success {
            color: var(--term-green);
        }

        .text-error {
            color: var(--term-red);
        }

        .text-header {
            color: var(--term-blue);
            font-weight: 700;
            margin-top: 15px;
            margin-bottom: 5px;
            display: inline-block;
            border-bottom: 1px solid #333;
            padding-bottom: 2px;
            width: 100%;
        }

        /* Pulse Effect untuk Status Running */
        .pulse {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--term-green);
            margin-right: 5px;
            animation: pulseAnim 1s infinite;
        }

        @keyframes pulseAnim {
            0% {
                opacity: 0.2;
            }

            50% {
                opacity: 1;
            }

            100% {
                opacity: 0.2;
            }
        }

        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #1a1a1a;
        }

        ::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>

<body>

    <div class="star-field">
        <div class="stars-small"></div>
        <div class="stars-medium"></div>
        <div class="stars-large"></div>
    </div>

    <div class="rocket-container">
        <svg class="rocket-svg" viewBox="0 0 512 512">
            <path fill="#e0e0e0" d="M256 16c-50 0-90 40-90 90v160l-60 60v80l90-30 60 60 60-60 90 30v-80l-60-60V106c0-50-40-90-90-90z" />
            <path fill="#ff3333" d="M256 416l-30 30-30-10 60 60 60-60-30 10z" />
            <path fill="#f39c12" d="M256 446l-20 20-20-5 40 40 40-40-20 5z" />
            <circle cx="256" cy="120" r="35" fill="#3498db" />
            <circle cx="256" cy="120" r="15" fill="#85c1e9" />
        </svg>
    </div>

    <div class="container">
        <div class="control-panel">
            <h2>EDP NETWORK DIAGNOSTIC</h2>
            <div class="input-row">
                <div class="input-group">
                    <label>Target IP Address / Host</label>
                    <input type="text" id="ip" value="172.31.147.216" placeholder="Example: 192.168.1.1">
                </div>
                <div class="input-group">
                    <label>Target Port (Service)</label>
                    <select id="port">
                        <option value="5432">5432 - PostgreSQL Database</option>
                        <option value="3306">3306 - MySQL Database</option>
                        <option value="80">80 - HTTP Web Server</option>
                        <option value="443">443 - HTTPS Secure Web</option>
                        <option value="22">22 - SSH Remote</option>
                    </select>
                </div>
            </div>

            <div class="btn-group">
                <button id="btnStart" onclick="startCheck()">START CONTINUOUS MONITORING</button>
                <button id="btnStop" onclick="stopCheck()">STOP ALL PROCESSES</button>
            </div>
        </div>

        <div class="terminal-grid">
            <div class="terminal-box">
                <div class="term-header">
                    <span>TCP LIVE MONITOR (UNLIMITED)</span>
                    <span id="status-paping" class="status-indicator">IDLE</span>
                </div>
                <div class="term-body" id="out-paping">
                    <div class="line">Ready to monitor...</div>
                </div>
            </div>

            <div class="terminal-box">
                <div class="term-header">
                    <span>TRACEROUTE LOG</span>
                    <span id="status-tracert" class="status-indicator">IDLE</span>
                </div>
                <div class="term-body" id="out-tracert">
                    <div class="line">Ready to trace...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let papingSource = null;
        let tracertSource = null;

        const btnStart = document.getElementById('btnStart');
        const btnStop = document.getElementById('btnStop');
        const outPaping = document.getElementById('out-paping');
        const outTracert = document.getElementById('out-tracert');
        const statusPaping = document.getElementById('status-paping');
        const statusTracert = document.getElementById('status-tracert');

        function startCheck() {
            const ip = document.getElementById('ip').value;
            const port = document.getElementById('port').value;

            if (!ip) {
                alert("Please enter an IP Address.");
                return;
            }

            // UI Reset
            outPaping.innerHTML = '';
            outTracert.innerHTML = '';
            btnStart.style.display = 'none';
            btnStop.style.display = 'block';

            statusPaping.innerHTML = '<span class="pulse"></span> LIVE';
            statusTracert.innerHTML = '<span class="pulse"></span> RUNNING';

            // ===========================================
            // 1. JALANKAN PAPING (INFINITE STREAM)
            // ===========================================
            if (papingSource) papingSource.close();
            papingSource = new EventSource(`index.php?action=stream&mode=paping&ip=${encodeURIComponent(ip)}&port=${port}`);

            papingSource.onmessage = function(e) {
                const data = JSON.parse(e.data);
                appendLog(outPaping, data);
            };
            papingSource.onerror = function() {
                appendLog(outPaping, {
                    msg: "Connection lost/stopped.",
                    type: 'error'
                });
                papingSource.close();
            };

            // ===========================================
            // 2. JALANKAN TRACERT (PARALLEL STREAM)
            // ===========================================
            if (tracertSource) tracertSource.close();
            tracertSource = new EventSource(`index.php?action=stream&mode=tracert&ip=${encodeURIComponent(ip)}`);

            tracertSource.onmessage = function(e) {
                const data = JSON.parse(e.data);
                if (data.type === 'close') {
                    tracertSource.close();
                    statusTracert.innerText = "FINISHED";
                    return;
                }
                appendLog(outTracert, data);
            };
            tracertSource.onerror = function() {
                tracertSource.close();
            };
        }

        function stopCheck() {
            if (papingSource) {
                papingSource.close();
                papingSource = null;
            }
            if (tracertSource) {
                tracertSource.close();
                tracertSource = null;
            }

            btnStart.style.display = 'block';
            btnStop.style.display = 'none';

            statusPaping.innerText = "STOPPED";
            statusTracert.innerText = "STOPPED"; // Tracert mungkin sudah finish duluan, tapi gpp

            appendLog(outPaping, {
                msg: ">> MONITORING STOPPED BY USER <<",
                type: 'header'
            });
            appendLog(outTracert, {
                msg: ">> PROCESS HALTED <<",
                type: 'header'
            });
        }

        function appendLog(element, data) {
            let className = 'line';
            if (data.type === 'success') className += ' text-success';
            if (data.type === 'error') className += ' text-error';
            if (data.type === 'header') className += ' text-header';

            const newLine = `<div class="${className}">${data.msg}</div>`;
            element.innerHTML += newLine;
            element.scrollTop = element.scrollHeight;
        }
    </script>

</body>

</html>