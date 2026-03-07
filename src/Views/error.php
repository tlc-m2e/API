<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - THE LIFE COINCOIN</title>
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --accent-color: #38bdf8;
            --accent-gradient: linear-gradient(135deg, #ef4444, #f87171); /* Red for errors */
            --card-border: #334155;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Hero Section */
        header {
            text-align: center;
            padding: 2rem 0;
            animation: fadeIn 0.5s ease-in;
        }

        h1 {
            font-size: 6rem;
            margin: 0;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
            letter-spacing: -2px;
            line-height: 1;
        }

        h2 {
            font-size: 2rem;
            margin-top: 1rem;
            color: var(--text-primary);
        }

        .subtitle {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-top: 1rem;
            font-weight: 300;
        }

        .debug-info {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 0.5rem;
            border: 1px solid var(--card-border);
            margin-top: 2rem;
            text-align: left;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .debug-item {
            margin-bottom: 0.5rem;
        }

        .debug-label {
            color: var(--accent-color);
            font-weight: bold;
        }

        .cta-button {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.8rem 2rem;
            background: #334155;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            transition: background 0.2s;
        }

        .cta-button:hover {
            background: #475569;
        }

        footer {
            text-align: center;
            padding: 2rem 0;
            border-top: 1px solid var(--card-border);
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?= htmlspecialchars($code) ?></h1>
            <h2><?= htmlspecialchars($title) ?></h2>
            <p class="subtitle"><?= htmlspecialchars($message) ?></p>

            <?php if (!empty($debug)): ?>
            <div class="debug-info">
                <div class="debug-item">
                    <span class="debug-label">Erreur:</span> <?= htmlspecialchars($debug['message'] ?? 'N/A') ?>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Fichier:</span> <?= htmlspecialchars($debug['file'] ?? 'N/A') ?>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Répertoire:</span> <?= htmlspecialchars($debug['directory'] ?? 'N/A') ?>
                </div>
            </div>
            <?php endif; ?>

            <a href="/" class="cta-button">Retour à l'accueil</a>
        </header>

        <footer>
            <p>&copy; <?= date('Y'); ?> THE LIFE COINCOIN. Tous droits réservés.</p>
        </footer>
    </div>
</body>
</html>
