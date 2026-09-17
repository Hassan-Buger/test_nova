<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'TriNova Digital Signature Portal') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Caveat:wght@600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                            800: '#115e59',
                            900: '#134e4a',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        signature: ['Caveat', 'cursive'],
                    }
                }
            }
        }
    </script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; color: #1e293b; background: #f8fafc; -webkit-font-smoothing: antialiased; }
        .font-signature { font-family: 'Caveat', cursive; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: rgba(148,163,184,0.4); border-radius: 999px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .animate-fade-in { animation: fadeIn .25s cubic-bezier(.16, 1, .3, 1); }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-slate-50 text-slate-800">
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-teal-600 flex items-center justify-center text-white font-extrabold text-xl shadow-md shadow-teal-500/20">
                    T
                </div>
                <div>
                    <span class="font-extrabold text-slate-900 tracking-tight text-lg">TriNova</span>
                    <span class="text-xs uppercase tracking-wider font-bold text-teal-600 ml-1.5 px-2 py-0.5 bg-teal-50 border border-teal-200/60 rounded-full">Signatures</span>
                </div>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold text-slate-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                    256-bit Secure TLS
                </span>
            </div>
        </div>
    </header>

    <main class="flex-1 flex flex-col">
        <?= $content ?>
    </main>

    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-400">
        <div class="max-w-7xl mx-auto px-4">
            &copy; <?= date('Y') ?> TriNova Accounting Client Portal &bull; Legally Binding Digital Execution
        </div>
    </footer>
</body>
</html>
