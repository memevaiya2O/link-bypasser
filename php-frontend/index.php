<?php
define('BACKEND_URL', 'http://localhost:5001');

function callBackend(string $endpoint, array $payload = [], string $method = 'POST'): array {
    $ch = curl_init();
    $url = BACKEND_URL . $endpoint;
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return ['success' => false, 'error' => 'Backend connection failed: ' . $err];
    }
    
    $decoded = json_decode($response, true);
    return $decoded ?: ['success' => false, 'error' => 'Invalid response from backend'];
}

$result = null;
$error = null;
$submitted = false;
$inputUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;
    $inputUrl = trim($_POST['url'] ?? '');
    
    if (empty($inputUrl)) {
        $error = 'অনুগ্রহ করে একটি লিংক প্রদান করুন।';
    } else {
        $result = callBackend('/api/bypass', ['url' => $inputUrl]);
        if (!$result['success'] && isset($result['error'])) {
            $error = $result['error'];
        }
    }
}

$supported = callBackend('/api/supported', [], 'GET');
$supportedDomains = $supported['domains'] ?? [];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkBypass Pro — যেকোনো লিংক বাইপাস করুন</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-primary: #050508;
            --bg-secondary: #0d0d14;
            --bg-card: #0f0f1a;
            --bg-glass: rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.08);
            --border-hover: rgba(139,92,246,0.5);
            --accent: #8b5cf6;
            --accent-2: #6366f1;
            --accent-glow: rgba(139,92,246,0.3);
            --accent-light: #a78bfa;
            --green: #10b981;
            --green-glow: rgba(16,185,129,0.2);
            --red: #ef4444;
            --red-glow: rgba(239,68,68,0.2);
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #475569;
            --radius: 14px;
            --radius-sm: 8px;
        }

        html { scroll-behavior: smooth; }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(ellipse 80% 80% at 20% 10%, rgba(139,92,246,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 60% at 80% 80%, rgba(99,102,241,0.10) 0%, transparent 60%),
                radial-gradient(ellipse 40% 40% at 50% 50%, rgba(16,185,129,0.05) 0%, transparent 60%);
            z-index: 0;
            animation: bgPulse 12s ease-in-out infinite alternate;
        }

        @keyframes bgPulse {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(2%, 2%) scale(1.05); }
        }

        /* Grid overlay */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px);
            background-size: 60px 60px;
            z-index: 0;
            pointer-events: none;
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* ─── Header ─── */
        header {
            text-align: center;
            padding: 4rem 0 3rem;
        }

        .logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: 100px;
            padding: 6px 16px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--accent-light);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .logo-badge::before {
            content: '';
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 8px var(--green);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.85); }
        }

        h1 {
            font-size: clamp(2.2rem, 6vw, 3.8rem);
            font-weight: 900;
            letter-spacing: -0.03em;
            line-height: 1.1;
            margin-bottom: 1.2rem;
            background: linear-gradient(135deg, #ffffff 0%, #e2e8f0 40%, var(--accent-light) 80%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            max-width: 560px;
            margin: 0 auto 2rem;
            font-weight: 400;
        }

        /* Stats bar */
        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 3rem;
        }

        .stat {
            text-align: center;
        }

        .stat-num {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--accent-light);
            letter-spacing: -0.02em;
        }

        .stat-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* ─── Main Card ─── */
        .main-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
        }

        .main-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent), var(--accent-2), transparent);
            opacity: 0.6;
        }

        .card-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.6rem;
        }

        /* Input Area */
        .input-group {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .url-input-wrapper {
            flex: 1;
            min-width: 0;
            position: relative;
        }

        .url-input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1rem;
            pointer-events: none;
        }

        .url-input {
            width: 100%;
            padding: 1rem 1rem 1rem 2.8rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.2s ease;
        }

        .url-input::placeholder { color: var(--text-muted); }

        .url-input:focus {
            border-color: var(--accent);
            background: rgba(139,92,246,0.07);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .btn-bypass {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.02em;
        }

        .btn-bypass::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .btn-bypass:hover::before { opacity: 1; }

        .btn-bypass:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px var(--accent-glow);
        }

        .btn-bypass:active { transform: translateY(0); }

        .btn-bypass.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        /* Quick examples */
        .quick-examples {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .examples-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .example-chip {
            display: inline-block;
            padding: 3px 10px;
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: 100px;
            font-size: 0.78rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'JetBrains Mono', monospace;
        }

        .example-chip:hover {
            border-color: var(--accent);
            color: var(--accent-light);
        }

        /* ─── Result Section ─── */
        .result-card {
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.35s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .result-card.success {
            background: rgba(16,185,129,0.06);
            border: 1px solid rgba(16,185,129,0.2);
        }

        .result-card.error {
            background: rgba(239,68,68,0.06);
            border: 1px solid rgba(239,68,68,0.2);
        }

        .result-header {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            margin-bottom: 1.2rem;
        }

        .result-icon {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .result-icon.ok { background: rgba(16,185,129,0.15); color: var(--green); }
        .result-icon.fail { background: rgba(239,68,68,0.15); color: var(--red); }

        .result-title {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .result-title.ok { color: var(--green); }
        .result-title.fail { color: var(--red); }

        .result-subtitle {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .url-display {
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 1rem 1.2rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .url-display-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
        }

        .url-display-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            color: var(--text-primary);
            word-break: break-all;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .url-display-value a {
            color: var(--accent-light);
            text-decoration: none;
            flex: 1;
        }

        .url-display-value a:hover { text-decoration: underline; }

        .copy-btn {
            background: var(--bg-glass);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            border-radius: 6px;
            padding: 3px 10px;
            font-size: 0.75rem;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
            transition: all 0.2s;
        }

        .copy-btn:hover {
            border-color: var(--accent);
            color: var(--accent-light);
        }

        .open-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.7rem 1.5rem;
            background: var(--green);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            margin-top: 0.5rem;
        }

        .open-btn:hover {
            background: #0da271;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px var(--green-glow);
        }

        /* Meta info badges */
        .meta-row {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            margin: 1rem 0;
        }

        .meta-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: 100px;
            padding: 4px 12px;
            font-size: 0.77rem;
            color: var(--text-secondary);
        }

        .meta-badge .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
        }

        .dot-green { background: var(--green); }
        .dot-purple { background: var(--accent); }
        .dot-blue { background: #60a5fa; }

        /* Redirect chain */
        .chain-section {
            margin-top: 1.2rem;
        }

        .chain-toggle {
            background: none;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            border-radius: var(--radius-sm);
            padding: 6px 14px;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .chain-toggle:hover {
            border-color: var(--accent);
            color: var(--accent-light);
        }

        .chain-list {
            margin-top: 0.8rem;
            display: none;
            flex-direction: column;
            gap: 0.5rem;
        }

        .chain-list.open { display: flex; }

        .chain-item {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.6rem 1rem;
        }

        .chain-step {
            width: 24px; height: 24px;
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .chain-url {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.78rem;
            color: var(--text-secondary);
            word-break: break-all;
            flex: 1;
        }

        .chain-status {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
        }

        .status-3xx { background: rgba(251,191,36,0.1); color: #fbbf24; }
        .status-2xx { background: rgba(16,185,129,0.1); color: var(--green); }

        /* ─── Features grid ─── */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 1.2rem;
            transition: border-color 0.2s;
        }

        .feature-card:hover { border-color: rgba(139,92,246,0.3); }

        .feature-icon {
            font-size: 1.5rem;
            margin-bottom: 0.6rem;
        }

        .feature-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.3rem;
        }

        .feature-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* ─── Supported domains ─── */
        .domains-section {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .domains-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .domain-chip {
            display: inline-block;
            background: var(--bg-glass);
            border: 1px solid var(--border);
            border-radius: 100px;
            padding: 4px 12px;
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-family: 'JetBrains Mono', monospace;
            transition: all 0.2s;
        }

        .domain-chip:hover {
            border-color: var(--accent);
            color: var(--accent-light);
        }

        /* ─── Footer ─── */
        footer {
            text-align: center;
            padding: 2rem 0;
            color: var(--text-muted);
            font-size: 0.82rem;
            border-top: 1px solid var(--border);
        }

        footer a { color: var(--accent-light); text-decoration: none; }

        /* Spinner */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .spinner {
            display: inline-block;
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .input-group { flex-direction: column; }
            .btn-bypass { width: 100%; justify-content: center; }
            .stats-bar { gap: 1rem; }
            .main-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="container">

    <header>
        <div class="logo-badge">⚡ LinkBypass Pro — Online</div>
        <h1>যেকোনো লিংক বাইপাস করুন</h1>
        <p class="subtitle">
            শর্ট লিংক, বিজ্ঞাপন লিংক, এবং যেকোনো রিডাইরেক্ট লিংক তাৎক্ষণিকভাবে বাইপাস করুন — সম্পূর্ণ বিনামূল্যে।
        </p>

        <div class="stats-bar">
            <div class="stat">
                <div class="stat-num"><?= count($supportedDomains) ?>+</div>
                <div class="stat-label">সাপোর্টেড ডোমেইন</div>
            </div>
            <div class="stat">
                <div class="stat-num">∞</div>
                <div class="stat-label">আনলিমিটেড বাইপাস</div>
            </div>
            <div class="stat">
                <div class="stat-num">100%</div>
                <div class="stat-label">বিনামূল্যে</div>
            </div>
        </div>
    </header>

    <!-- Main Bypass Card -->
    <div class="main-card">
        <div class="card-label">লিংক বাইপাস করুন</div>
        <form method="POST" id="bypassForm" onsubmit="handleSubmit(event)">
            <div class="input-group">
                <div class="url-input-wrapper">
                    <span class="url-input-icon">🔗</span>
                    <input
                        type="text"
                        name="url"
                        class="url-input"
                        id="urlInput"
                        placeholder="https://bit.ly/xxxxxx অথবা যেকোনো শর্ট লিংক পেস্ট করুন..."
                        value="<?= htmlspecialchars($inputUrl) ?>"
                        autocomplete="off"
                        spellcheck="false"
                    >
                </div>
                <button type="submit" class="btn-bypass" id="submitBtn">
                    ⚡ বাইপাস করুন
                </button>
            </div>

            <div class="quick-examples">
                <span class="examples-label">উদাহরণ:</span>
                <span class="example-chip" onclick="setUrl('https://bit.ly/3example')">bit.ly</span>
                <span class="example-chip" onclick="setUrl('https://tinyurl.com/example')">tinyurl.com</span>
                <span class="example-chip" onclick="setUrl('https://adf.ly/example')">adf.ly</span>
                <span class="example-chip" onclick="setUrl('https://linkvertise.com/example')">linkvertise</span>
                <span class="example-chip" onclick="setUrl('https://ouo.io/example')">ouo.io</span>
                <span class="example-chip" onclick="setUrl('https://shorte.st/example')">shorte.st</span>
            </div>
        </form>
    </div>

    <?php if ($submitted): ?>

    <?php if ($error): ?>
    <!-- Error Result -->
    <div class="result-card error">
        <div class="result-header">
            <div class="result-icon fail">✕</div>
            <div>
                <div class="result-title fail">বাইপাস ব্যর্থ হয়েছে</div>
                <div class="result-subtitle">লিংকটি প্রক্রিয়া করতে সমস্যা হয়েছে</div>
            </div>
        </div>
        <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); border-radius:8px; padding:1rem; font-size:0.9rem; color:#fca5a5;">
            <?= htmlspecialchars($error) ?>
        </div>
    </div>

    <?php elseif ($result && $result['success']): ?>
    <!-- Success Result -->
    <div class="result-card success">
        <div class="result-header">
            <div class="result-icon ok">✓</div>
            <div>
                <div class="result-title ok">
                    <?= $result['bypassed'] ? '✅ লিংক সফলভাবে বাইপাস হয়েছে!' : '🔍 ফাইনাল ডেসটিনেশন খুঁজে পাওয়া গেছে' ?>
                </div>
                <div class="result-subtitle">
                    <?php if ($result['bypassed']): ?>
                        <?= $result['hops'] ?> টি রিডাইরেক্ট অতিক্রম করে ফাইনাল লিংক পাওয়া গেছে
                    <?php else: ?>
                        লিংকটি সরাসরি ডেসটিনেশনে রিডাইরেক্ট করছে
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Meta badges -->
        <div class="meta-row">
            <?php if (!empty($result['domain'])): ?>
            <span class="meta-badge">
                <span class="dot dot-purple"></span>
                <?= htmlspecialchars($result['domain']) ?>
            </span>
            <?php endif; ?>
            <span class="meta-badge">
                <span class="dot dot-blue"></span>
                <?= $result['hops'] ?> হপ
            </span>
            <?php if ($result['is_known_shortener']): ?>
            <span class="meta-badge">
                <span class="dot dot-green"></span>
                পরিচিত শর্টেনার
            </span>
            <?php endif; ?>
            <?php if (!empty($result['method'])): ?>
            <span class="meta-badge">
                🔧 <?= htmlspecialchars($result['method']) ?>
            </span>
            <?php endif; ?>
        </div>

        <!-- Original URL -->
        <div class="url-display">
            <div class="url-display-label">মূল লিংক</div>
            <div class="url-display-value">
                <a href="<?= htmlspecialchars($result['original_url']) ?>" target="_blank" rel="noopener noreferrer">
                    <?= htmlspecialchars($result['original_url']) ?>
                </a>
            </div>
        </div>

        <!-- Final URL -->
        <div class="url-display" style="border-color: rgba(16,185,129,0.3);">
            <div class="url-display-label" style="color: var(--green);">✨ ফাইনাল লিংক (বাইপাস করা)</div>
            <div class="url-display-value">
                <a href="<?= htmlspecialchars($result['final_url']) ?>" target="_blank" rel="noopener noreferrer">
                    <?= htmlspecialchars($result['final_url']) ?>
                </a>
                <button class="copy-btn" onclick="copyUrl('<?= htmlspecialchars(addslashes($result['final_url'])) ?>', this)">
                    📋 কপি
                </button>
            </div>
        </div>

        <a href="<?= htmlspecialchars($result['final_url']) ?>" target="_blank" rel="noopener noreferrer" class="open-btn">
            🚀 লিংক খুলুন
        </a>

        <!-- Redirect chain -->
        <?php if (!empty($result['chain']) && count($result['chain']) > 1): ?>
        <div class="chain-section">
            <button class="chain-toggle" onclick="toggleChain(this)">
                🔗 রিডাইরেক্ট চেইন দেখুন (<?= count($result['chain']) ?> স্টেপ)
            </button>
            <div class="chain-list" id="chainList">
                <?php foreach ($result['chain'] as $step): ?>
                <div class="chain-item">
                    <div class="chain-step"><?= $step['step'] ?></div>
                    <div class="chain-url"><?= htmlspecialchars($step['url']) ?></div>
                    <?php if (isset($step['status'])): ?>
                    <div class="chain-status <?= $step['status'] >= 300 && $step['status'] < 400 ? 'status-3xx' : 'status-2xx' ?>">
                        <?= $step['status'] ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- Features -->
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">⚡</div>
            <div class="feature-title">তাৎক্ষণিক বাইপাস</div>
            <div class="feature-desc">মাত্র কয়েক সেকেন্ডে যেকোনো লিংক বাইপাস করুন</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🛡️</div>
            <div class="feature-title">অ্যান্টি-বট বাইপাস</div>
            <div class="feature-desc">Cloudflare এবং অ্যান্টি-বট সুরক্ষা অতিক্রম করতে সক্ষম</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🔗</div>
            <div class="feature-title">চেইন লিংক</div>
            <div class="feature-desc">একাধিক রিডাইরেক্টের চেইন সম্পূর্ণ ট্র্যাক করে</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🌐</div>
            <div class="feature-title">সব ধরনের লিংক</div>
            <div class="feature-desc">শর্ট লিংক, অ্যাড লিংক, পেওয়াল — সবকিছু বাইপাস</div>
        </div>
    </div>

    <!-- Supported Domains -->
    <?php if (!empty($supportedDomains)): ?>
    <div class="domains-section">
        <div class="section-title">
            🔥 সাপোর্টেড ডোমেইনসমূহ
        </div>
        <div class="domains-grid">
            <?php foreach ($supportedDomains as $domain): ?>
            <span class="domain-chip"><?= htmlspecialchars($domain) ?></span>
            <?php endforeach; ?>
            <span class="domain-chip" style="border-style:dashed; border-color: var(--accent);">+ সব অজানা লিংক</span>
        </div>
    </div>
    <?php endif; ?>

    <footer>
        <p>LinkBypass Pro &mdash; Python Backend + PHP Frontend &mdash; সম্পূর্ণ বিনামূল্যে</p>
        <p style="margin-top:0.4rem; opacity:0.6;">অনুগ্রহ করে বৈধ উদ্দেশ্যে ব্যবহার করুন।</p>
    </footer>

</div>

<script>
function setUrl(url) {
    document.getElementById('urlInput').value = url;
    document.getElementById('urlInput').focus();
}

function copyUrl(url, btn) {
    navigator.clipboard.writeText(url).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ কপি হয়েছে!';
        btn.style.borderColor = 'var(--green)';
        btn.style.color = 'var(--green)';
        setTimeout(() => {
            btn.textContent = orig;
            btn.style.borderColor = '';
            btn.style.color = '';
        }, 2000);
    });
}

function toggleChain(btn) {
    const list = document.getElementById('chainList');
    list.classList.toggle('open');
    btn.textContent = list.classList.contains('open')
        ? btn.textContent.replace('দেখুন', 'লুকান')
        : btn.textContent.replace('লুকান', 'দেখুন');
}

function handleSubmit(e) {
    const btn = document.getElementById('submitBtn');
    const url = document.getElementById('urlInput').value.trim();
    if (!url) { e.preventDefault(); return; }
    
    btn.classList.add('loading');
    btn.innerHTML = '<span class="spinner"></span> প্রসেস হচ্ছে...';
}

document.getElementById('urlInput').addEventListener('paste', function(e) {
    setTimeout(() => {
        const val = this.value.trim();
        if (val && (val.startsWith('http') || val.includes('.'))) {
            document.getElementById('submitBtn').style.boxShadow = '0 0 20px var(--accent-glow)';
        }
    }, 50);
});
</script>
</body>
</html>
