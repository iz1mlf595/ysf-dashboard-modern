<?php
/**
 * YSFReflector-Dashboard2 - Main Index
 * Ultra-Modern dashboard for YSFReflector & pYSFReflector
 */

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once(__DIR__ . '/include/functions.php');

// Carica la configurazione personalizzata della dashboard (con fallback se non esiste)
$dashConfig = file_exists(__DIR__ . '/config.dashboard.php') 
    ? require(__DIR__ . '/config.dashboard.php') 
    : [];

$configs = getYSFReflectorConfig();

// Funzione per mascherare l'indirizzo IP
function maskIP($ip) {
    if (empty($ip)) return '***.***.***.***';
    $parts = explode('.', $ip);
    if (count($parts) == 4) {
        return '***.***.' . $parts[2] . '.' . $parts[3];
    }
    return '****:' . substr($ip, -9);
}

// Funzione di supporto per calcolare i conteggi: Bridge IZ1MLF-YSF vs Bridge XLX595 vs Diretti
function getReflectorStatistics($logLines) {
    $bridgeCount = 0;
    $xlxCount = 0;
    $directCount = 0;
    
    if (!empty($logLines)) {
        $lastHeard = getLastHeard($logLines);
        foreach ($lastHeard as $item) {
            $gateway = isset($item[3]) ? trim($item[3]) : '';
            if (stripos($gateway, 'IZ1MLF-YSF') !== false) {
                $bridgeCount++;
            } elseif (stripos($gateway, 'XLX595') !== false) {
                $xlxCount++;
            } else {
                $directCount++;
            }
        }
    }
    
    return [
        'bridgeCount' => $bridgeCount,
        'xlxCount' => $xlxCount,
        'directCount' => $directCount
    ];
}

// Se viene fatta una chiamata AJAX per aggiornare i dati in tempo reale
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $logLines = getYSFReflectorLog();
    $currentlyTXing = getCurrentlyTXing($logLines);
    $lastHeard = getLastHeard($logLines);
    $linkedGateways = getLinkedGateways($logLines);
    $stats = getReflectorStatistics($logLines);
    
    // Calcolo contatori per i box riassuntivi
    $uniqueCallsCount = 0;
    if (!empty($lastHeard)) {
        $callsList = array();
        foreach ($lastHeard as $item) {
            $callBase = explode('-', trim($item[1]))[0];
            if (!in_array($callBase, $callsList)) {
                $callsList[] = $callBase;
            }
        }
        $uniqueCallsCount = count($callsList);
    }
    
    $response = array();
    $response['txData'] = $currentlyTXing;
    $response['stats'] = $stats;
    $response['gatewaysCount'] = count($linkedGateways);
    $response['uniqueCallsCount'] = $uniqueCallsCount;
    $response['qsoCount'] = count($lastHeard);
    $response['lastUpdate'] = date('H:i:s');

    // 1. Box Nominativi Transitati di Recente (Senza Suffisso)
    ob_start();
    if (!empty($lastHeard)):
        $uniqueCallsAjax = array();
        foreach ($lastHeard as $item) {
            $fullCall = trim($item[1]);
            $callBase = explode('-', $fullCall)[0];
            
            if (!in_array($callBase, $uniqueCallsAjax)) {
                $uniqueCallsAjax[] = $callBase;
                $qrzUrl = "https://www.qrz.com/db/" . urlencode($callBase);
    ?>
                <a href="<?php echo $qrzUrl; ?>" target="_blank" class="bg-slate-100 dark:bg-slate-800/80 hover:bg-blue-500 hover:text-white text-slate-700 dark:text-slate-300 px-3 py-1.5 rounded-xl text-xs font-mono font-bold transition-all border border-slate-200 dark:border-slate-700/50 flex items-center gap-1.5 shadow-xs" title="Cerca su QRZ.com">
                    <i class="fa-solid fa-tower-broadcast text-[10px] opacity-60"></i>
                    <?php echo htmlspecialchars($callBase); ?>
                </a>
    <?php 
            }
        }
    else: 
    ?>
        <p class="text-slate-400 dark:text-slate-500 text-sm italic w-full text-center py-4">Nessun nominativo registrato.</p>
    <?php 
    endif;
    $response['transitCallsignsBox'] = ob_get_clean();

    // 2. Tabella Last Heard
    ob_start();
    if (!empty($lastHeard)):
        foreach ($lastHeard as $item): 
            $callsignClean = trim($item[1]);
            $qrzUrl = "https://www.qrz.com/db/" . urlencode($callsignClean);
        ?>
            <tr class="hover:bg-blue-500/5 transition-colors group lh-row">
                <td class="py-3.5 px-5 text-slate-500 dark:text-slate-400 font-mono text-xs"><?php echo htmlspecialchars($item[0]); ?></td>
                <td class="py-3.5 px-5 font-bold callsign-cell">
                    <a href="<?php echo $qrzUrl; ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 inline-flex items-center gap-2 group-hover:translate-x-0.5 transition-transform" title="Cerca su QRZ.com">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 opacity-50"></span>
                        <?php echo htmlspecialchars($item[1]); ?>
                    </a>
                </td>
                <td class="py-3.5 px-5 text-slate-700 dark:text-slate-300 font-medium">
                    <span class="bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 px-2.5 py-1 rounded-md text-xs font-mono border border-slate-200 dark:border-slate-700/50">
                        <?php echo htmlspecialchars($item[2]); ?>
                    </span>
                </td>
                <td class="py-3.5 px-5 text-slate-500 dark:text-slate-400 font-mono text-xs gateway-cell"><?php echo htmlspecialchars($item[3]); ?></td>
            </tr>
        <?php endforeach;
    else: ?>
        <tr class="no-data-row">
            <td colspan="4" class="py-8 text-center text-slate-400 dark:text-slate-500 italic text-sm">Nessun dato registrato nei log recenti.</td>
        </tr>
    <?php endif;
    $response['lastHeardTable'] = ob_get_clean();

    // 3. Box Gateway Connessi
    ob_start();
    $gatewayIdentifiers = array();
    if (!empty($linkedGateways)):
        foreach ($linkedGateways as $gw): 
            $gwCallFull = trim($gw['callsign']);
            if (isset($gw['suffix']) && !empty($gw['suffix']) && strpos($gwCallFull, '-') === false) {
                $gwCallFull .= '-' . trim($gw['suffix']);
            }
            $gwKey = $gwCallFull . '@' . $gw['ip'];
            $gatewayIdentifiers[] = $gwKey;
            
            $aprsCallBase = explode('-', $gwCallFull)[0];
            $aprsUrl = "https://aprs.fi/#!z=11&call=a%2F" . urlencode($aprsCallBase) . "&timerange=3600&tail=3600";
        ?>
            <div class="bg-white/80 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-3.5 flex flex-col gap-2 text-sm transition-all duration-200 hover:border-blue-500/40 hover:bg-slate-50 dark:hover:bg-slate-800/40 shadow-xs gateway-card" data-gw-key="<?php echo htmlspecialchars($gwKey); ?>">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-3">
                        <div class="relative flex items-center justify-center">
                            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                            <div class="absolute w-4 h-4 rounded-full bg-emerald-500/30 animate-ping"></div>
                        </div>
                        <div>
                            <a href="<?php echo $aprsUrl; ?>" target="_blank" class="font-bold text-slate-800 dark:text-slate-200 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors flex items-center gap-1.5" title="Visualizza su aprs.fi">
                                <?php echo htmlspecialchars($gwCallFull); ?>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] opacity-40"></i>
                            </a>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 font-mono mt-0.5">IP: <?php echo htmlspecialchars(maskIP($gw['ip'])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800/60 text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                    <span class="flex items-center gap-1">
                        <i class="fa-regular fa-clock text-blue-500 text-[10px]"></i>
                        Connected since:
                    </span>
                    <span class="gateway-time bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700/50 font-bold text-slate-700 dark:text-slate-300">
                        --/--/---- --:--:--
                    </span>
                </div>
            </div>
        <?php endforeach;
    else: ?>
        <p class="text-slate-400 dark:text-slate-500 text-sm text-center py-8 italic">Nessun gateway connesso.</p>
    <?php endif;
    $response['gatewaysBox'] = ob_get_clean();
    $response['gatewaysList'] = $gatewayIdentifiers;

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$logLines = getYSFReflectorLog();
$lastHeard = getLastHeard($logLines);
$currentlyTXing = getCurrentlyTXing($logLines);
$linkedGateways = getLinkedGateways($logLines);
$stats = getReflectorStatistics($logLines);

// Conteggio iniziale calls uniche per il render server-side
$uniqueCallsCountInitial = 0;
if (!empty($lastHeard)) {
    $callsListInit = array();
    foreach ($lastHeard as $item) {
        $callBase = explode('-', trim($item[1]))[0];
        if (!in_array($callBase, $callsListInit)) {
            $callsListInit[] = $callBase;
        }
    }
    $uniqueCallsCountInitial = count($callsListInit);
}

$initialGateways = array();
foreach($linkedGateways as $gw) {
    $gwCallFull = trim($gw['callsign']);
    if (isset($gw['suffix']) && !empty($gw['suffix']) && strpos($gwCallFull, '-') === false) {
        $gwCallFull .= '-' . trim($gw['suffix']);
    }
    $initialGateways[] = $gwCallFull . '@' . $gw['ip'];
}
$systemInfo = getSystemInfo();
$diskInfo = getDiskInfo();
$reflectorVersion = getYSFReflectorVersion();
$reflectorName = isset($configs['General']['Name']) ? $configs['General']['Name'] : "YSF Reflector";

// Valori di configurazione dashboard con fallback
$dashTitle = $dashConfig['header']['title'] ?? 'IT-DSTAR-ITALY-N';
$dashSubTitle = $dashConfig['header']['sub_title'] ?? $reflectorName;
$dashReflectorId = $dashConfig['header']['reflector_id'] ?? '22355';
$dashNavButtons = $dashConfig['nav_buttons'] ?? [];
$sysopEmail = $dashConfig['sysop']['email'] ?? 'iz1mlf@hotmail.it';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dashTitle); ?> - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#eff6ff', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col justify-between relative selection:bg-blue-500 selection:text-white transition-colors duration-300">

    <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -left-40 w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-[120px]"></div>
        <div class="absolute top-1/4 -right-40 w-[500px] h-[500px] bg-indigo-600/10 rounded-full blur-[120px]"></div>
    </div>

    <div id="toast-container" class="fixed top-24 right-5 z-50 flex flex-col space-y-3 pointer-events-none"></div>

    <header class="bg-white/80 dark:bg-slate-900/60 backdrop-blur-2xl border-b border-slate-200/80 dark:border-slate-800/80 sticky top-0 z-40 transition-colors duration-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 border border-blue-500/30 flex items-center justify-center text-blue-600 dark:text-blue-400 text-xl shadow-inner">
                    <i class="fa-solid fa-tower-broadcast animate-pulse"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                            <?php echo htmlspecialchars($dashSubTitle); ?>
                        </span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">
                        <?php echo htmlspecialchars($dashTitle); ?>
                    </h1>
                    
                    <!-- Badge e Pulsanti Intestazione Dinamici -->
                    <div class="flex flex-wrap items-center gap-2 text-xs font-mono font-medium mt-1">
                        <?php if (!empty($dashReflectorId)): ?>
                            <span class="bg-blue-500/10 text-blue-600 dark:text-blue-400 px-2 py-0.5 rounded-md border border-blue-500/20">
                                ID #<?php echo htmlspecialchars($dashReflectorId); ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($dashNavButtons)): ?>
                            <?php foreach ($dashNavButtons as $btn): 
                                $c = $btn['color'] ?? 'blue';
                            ?>
                                <span class="text-slate-400 dark:text-slate-700">&bull;</span>
                                <a href="<?php echo htmlspecialchars($btn['url']); ?>" target="_blank" class="bg-<?php echo $c; ?>-500/10 text-<?php echo $c; ?>-600 dark:text-<?php echo $c; ?>-400 hover:opacity-80 transition-opacity px-2 py-0.5 rounded-md border border-<?php echo $c; ?>-500/20 flex items-center gap-1.5">
                                    <?php if (!empty($btn['icon'])): ?>
                                        <i class="<?php echo htmlspecialchars($btn['icon']); ?> text-[10px]"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($btn['label']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <button id="theme-toggle" onclick="toggleTheme()" class="bg-white hover:bg-slate-100 dark:bg-slate-900/80 dark:hover:bg-slate-800 text-slate-700 dark:text-yellow-400 w-10 h-10 rounded-xl transition-all shadow-xs flex items-center justify-center border border-slate-200 dark:border-slate-800" title="Cambia Modalità Giorno/Notte">
                    <i id="theme-icon" class="fa-solid fa-sun text-sm"></i>
                </button>

                <div class="flex items-center space-x-2 bg-emerald-500/10 border border-emerald-500/30 px-3.5 py-2 rounded-xl text-xs font-bold text-emerald-600 dark:text-emerald-400">
                    <span class="relative flex h-2 w-2">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="tracking-wider">ONLINE</span>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 w-full flex-grow grid grid-cols-1 lg:grid-cols-3 gap-8 relative z-10">

        <div class="lg:col-span-2 space-y-6">
            
            <!-- Activity In Tempo Reale -->
            <div class="bg-white/80 dark:bg-slate-900/40 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-xl transition-all duration-200">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-black uppercase tracking-wider flex items-center text-blue-600 dark:text-blue-400">
                        <i class="fa-solid fa-podcast mr-2 text-base animate-pulse"></i> Attivit&agrave; in Tempo Reale
                    </h2>
                    <span class="text-[10px] font-mono bg-slate-100 dark:bg-slate-800/80 text-slate-500 px-2.5 py-1 rounded-full border border-slate-200 dark:border-slate-700/50">Live Stream</span>
                </div>
                
                <div id="live-tx-container">
                    <?php if ($currentlyTXing): 
                        $txSourceClean = trim($currentlyTXing['source']);
                        $txQrzUrl = "https://www.qrz.com/db/" . urlencode($txSourceClean);
                    ?>
                        <div class="bg-gradient-to-r from-blue-500/15 via-indigo-500/5 to-transparent border border-blue-500/30 rounded-2xl p-5 flex justify-between items-center shadow-inner relative overflow-hidden">
                            <div class="absolute right-0 top-0 translate-x-4 -translate-y-4 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl pointer-events-none"></div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                                    <p class="text-[11px] text-red-500 uppercase font-black tracking-widest">Trasmissione in Corso</p>
                                </div>
                                <p id="tx-source" class="text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-1.5">
                                    <a href="<?php echo $txQrzUrl; ?>" target="_blank" class="hover:text-blue-500 dark:hover:text-blue-400 transition-colors inline-flex items-center gap-2" title="Cerca su QRZ.com">
                                        <?php echo htmlspecialchars($currentlyTXing['source']); ?>
                                        <i class="fa-solid fa-arrow-up-right-from-square text-xs opacity-50"></i>
                                    </a>
                                </p>
                                <p id="tx-details" class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-1">Verso: <?php echo htmlspecialchars($currentlyTXing['target']); ?> &bull; Gateway: <?php echo htmlspecialchars($currentlyTXing['gateway']); ?></p>
                                <div class="inline-flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400 font-mono mt-3 bg-blue-500/10 px-2.5 py-1 rounded-lg border border-blue-500/20">
                                    <i class="fa-regular fa-clock"></i> Durata: <span id="tx-timer">0</span>s
                                </div>
                            </div>
                            
                            <div class="flex flex-col items-end gap-3">
                                <span class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider bg-red-500 text-white shadow-lg shadow-red-500/30 animate-pulse">
                                    LIVE TX
                                </span>
                                <div class="flex items-end gap-0.5 h-5">
                                    <span class="w-1 bg-red-500 rounded-full animate-bounce" style="height: 60%; animation-delay: 0.1s;"></span>
                                    <span class="w-1 bg-red-500 rounded-full animate-bounce" style="height: 100%; animation-delay: 0.3s;"></span>
                                    <span class="w-1 bg-red-500 rounded-full animate-bounce" style="height: 40%; animation-delay: 0.2s;"></span>
                                    <span class="w-1 bg-red-500 rounded-full animate-bounce" style="height: 80%; animation-delay: 0.4s;"></span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div id="tx-inactive" class="text-slate-500 dark:text-slate-400 text-sm py-8 text-center bg-slate-50/80 dark:bg-slate-900/30 rounded-2xl border border-slate-200/80 dark:border-slate-800/60 italic">
                            Nessuna trasmissione in corso al momento.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Box Statistiche Riassuntive -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white/80 dark:bg-slate-900/40 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-3 sm:p-4 flex items-center space-x-2.5 sm:space-x-3.5 shadow-lg transition-all duration-200 hover:border-blue-500/30">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-500 shrink-0">
                        <i class="fa-solid fa-satellite-dish text-sm sm:text-base"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[9px] sm:text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-400 leading-tight">Gateway Online</p>
                        <p id="stat-gateways" class="text-xl font-black text-slate-900 dark:text-white font-mono leading-tight mt-0.5"><?php echo count($linkedGateways); ?></p>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-slate-900/40 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-3 sm:p-4 flex items-center space-x-2.5 sm:space-x-3.5 shadow-lg transition-all duration-200 hover:border-blue-500/30">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-500 shrink-0">
                        <i class="fa-solid fa-user text-sm sm:text-base"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[9px] sm:text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-400 leading-tight">Callsign Distinti</p>
                        <p id="stat-unique-calls" class="text-xl font-black text-slate-900 dark:text-white font-mono leading-tight mt-0.5"><?php echo $uniqueCallsCountInitial; ?></p>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-slate-900/40 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-3 sm:p-4 flex items-center space-x-2.5 sm:space-x-3.5 shadow-lg transition-all duration-200 hover:border-blue-500/30">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 shrink-0">
                        <i class="fa-solid fa-radio text-sm sm:text-base"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[9px] sm:text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-400 leading-tight">QSO Registrati</p>
                        <p id="stat-qso-count" class="text-xl font-black text-slate-900 dark:text-white font-mono leading-tight mt-0.5"><?php echo count($lastHeard); ?></p>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-slate-900/40 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-3 sm:p-4 flex items-center space-x-2.5 sm:space-x-3.5 shadow-lg transition-all duration-200 hover:border-blue-500/30">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500 shrink-0">
                        <i class="fa-solid fa-stopwatch text-sm sm:text-base"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[9px] sm:text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-400 leading-tight">Aggiornamento</p>
                        <p id="stat-last-update" class="text-xl font-black text-slate-900 dark:text-white font-mono leading-tight mt-0.5"><?php echo date('H:i:s'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Nominativi Transitati di Recente -->
            <div class="bg-white/80 dark:bg-slate-900/40 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-xl transition-all duration-200">
                <h2 class="text-sm font-black uppercase tracking-wider mb-4 flex items-center justify-between text-blue-600 dark:text-blue-400">
                    <span class="flex items-center"><i class="fa-solid fa-users mr-2 text-base"></i> Nominativi Transitati di Recente</span>
                    <span class="text-[10px] font-mono bg-slate-100 dark:bg-slate-800/80 text-slate-500 px-2.5 py-1 rounded-full border border-slate-200 dark:border-slate-700/50">Ultimi transiti</span>
                </h2>
                <div id="all-transit-callsigns" class="flex flex-wrap gap-2 max-h-40 overflow-y-auto pr-1">
                    <?php if (!empty($lastHeard)): ?>
                        <?php 
                        $uniqueCalls = array();
                        foreach ($lastHeard as $item) {
                            $fullCall = trim($item[1]);
                            $callBase = explode('-', $fullCall)[0];
                            
                            if (!in_array($callBase, $uniqueCalls)) {
                                $uniqueCalls[] = $callBase;
                                $qrzUrl = "https://www.qrz.com/db/" . urlencode($callBase);
                        ?>
                            <a href="<?php echo $qrzUrl; ?>" target="_blank" class="bg-slate-100 dark:bg-slate-800/80 hover:bg-blue-500 hover:text-white text-slate-700 dark:text-slate-300 px-3 py-1.5 rounded-xl text-xs font-mono font-bold transition-all border border-slate-200 dark:border-slate-700/50 flex items-center gap-1.5 shadow-xs" title="Cerca su QRZ.com">
                                <i class="fa-solid fa-tower-broadcast text-[10px] opacity-60"></i>
                                <?php echo htmlspecialchars($callBase); ?>
                            </a>
                        <?php 
                            }
                        } 
                        ?>
                    <?php else: ?>
                        <p class="text-slate-400 dark:text-slate-500 text-sm italic w-full text-center py-4">Nessun nominativo registrato.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ultime Trasmissioni (Last Heard) -->
            <div class="bg-white/80 dark:bg-slate-900/40 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-xl transition-all duration-200">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-5">
                    <h2 class="text-sm font-black uppercase tracking-wider flex items-center text-blue-600 dark:text-blue-400">
                        <i class="fa-solid fa-list-ul mr-2 text-base"></i> Ultime Trasmissioni (Last Heard)
                    </h2>
                    <div class="flex flex-col sm:flex-row items-center gap-2 w-full lg:w-auto">
                        <!-- Filtro Callsign -->
                        <div class="relative w-full sm:w-48">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text" id="lh-search" placeholder="Filtra Callsign..." onkeyup="filterLastHeard()" class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 rounded-xl pl-9 pr-4 py-1.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 transition-all">
                        </div>
                        <!-- Filtro Gateway -->
                        <div class="relative w-full sm:w-48">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                                <i class="fa-solid fa-tower-broadcast text-xs"></i>
                            </span>
                            <input type="text" id="gw-search" placeholder="Filtra Gateway..." onkeyup="filterLastHeard()" class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 rounded-xl pl-9 pr-4 py-1.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 transition-all">
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto -mx-6 px-6">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead class="sticky top-0 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md z-10">
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 uppercase text-[10px] font-mono tracking-widest">
                                <th class="py-3 px-5">Orario</th>
                                <th class="py-3 px-5">Callsign</th>
                                <th class="py-3 px-5">Target</th>
                                <th class="py-3 px-5">Gateway</th>
                            </tr>
                        </thead>
                        <tbody id="last-heard-tbody" class="divide-y divide-slate-100 dark:divide-slate-800/50">
                            <?php if (!empty($lastHeard)): ?>
                                <?php foreach ($lastHeard as $item): 
                                    $callsignClean = trim($item[1]);
                                    $qrzUrl = "https://www.qrz.com/db/" . urlencode($callsignClean);
                                ?>
                                    <tr class="hover:bg-blue-500/5 transition-colors group lh-row">
                                        <td class="py-3.5 px-5 text-slate-500 dark:text-slate-400 font-mono text-xs"><?php echo htmlspecialchars($item[0]); ?></td>
                                        <td class="py-3.5 px-5 font-bold callsign-cell">
                                            <a href="<?php echo $qrzUrl; ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 inline-flex items-center gap-2 group-hover:translate-x-0.5 transition-transform" title="Cerca su QRZ.com">
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 opacity-50"></span>
                                                <?php echo htmlspecialchars($item[1]); ?>
                                            </a>
                                        </td>
                                        <td class="py-3.5 px-5 text-slate-700 dark:text-slate-300 font-medium">
                                            <span class="bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 px-2.5 py-1 rounded-md text-xs font-mono border border-slate-200 dark:border-slate-700/50">
                                                <?php echo htmlspecialchars($item[2]); ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-5 text-slate-500 dark:text-slate-400 font-mono text-xs gateway-cell"><?php echo htmlspecialchars($item[3]); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="no-data-row">
                                    <td colspan="4" class="py-8 text-center text-slate-400 dark:text-slate-500 italic text-sm">Nessun dato registrato nei log recenti.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="space-y-6">
            <div class="bg-white/80 dark:bg-slate-900/40 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-xl transition-all duration-200">
                <h2 class="text-sm font-black uppercase tracking-wider mb-4 flex items-center text-blue-600 dark:text-blue-400">
                    <span class="flex items-center"><i class="fa-solid fa-network-wired mr-2 text-base"></i> Gateway Connessi</span>
                    <span id="gateways-count" class="hidden"><?php echo count($linkedGateways); ?></span>
                </h2>
                <div id="gateways-container" class="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                    <?php if (!empty($linkedGateways)): ?>
                        <?php foreach ($linkedGateways as $gw): 
                            $gwCallFull = trim($gw['callsign']);
                            if (isset($gw['suffix']) && !empty($gw['suffix']) && strpos($gwCallFull, '-') === false) {
                                $gwCallFull .= '-' . trim($gw['suffix']);
                            }
                            $gwKey = $gwCallFull . '@' . $gw['ip'];
                            $aprsCallBase = explode('-',$gwCallFull)[0];
                            $aprsUrl = "https://aprs.fi/#!z=11&call=a%2F" . urlencode($aprsCallBase) . "&timerange=3600&tail=3600";
                        ?>
                            <div class="bg-white/80 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-3.5 flex flex-col gap-2 text-sm transition-all duration-200 hover:border-blue-500/40 hover:bg-slate-50 dark:hover:bg-slate-800/40 shadow-xs gateway-card" data-gw-key="<?php echo htmlspecialchars($gwKey); ?>">
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center space-x-3">
                                        <div class="relative flex items-center justify-center">
                                            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                                            <div class="absolute w-4 h-4 rounded-full bg-emerald-500/30 animate-ping"></div>
                                        </div>
                                        <div>
                                            <a href="<?php echo $aprsUrl; ?>" target="_blank" class="font-bold text-slate-800 dark:text-slate-200 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors flex items-center gap-1.5" title="Visualizza su aprs.fi">
                                                <?php echo htmlspecialchars($gwCallFull); ?>
                                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] opacity-40"></i>
                                            </a>
                                            <p class="text-[11px] text-slate-400 dark:text-slate-500 font-mono mt-0.5">IP: <?php echo htmlspecialchars(maskIP($gw['ip'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800/60 text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                    <span class="flex items-center gap-1">
                                        <i class="fa-regular fa-clock text-blue-500 text-[10px]"></i>
                                        Connected since:
                                    </span>
                                    <span class="gateway-time bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700/50 font-bold text-slate-700 dark:text-slate-300">
                                        --/--/---- --:--:--
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-slate-400 dark:text-slate-500 text-sm text-center py-8 italic">Nessun gateway connesso.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white/80 dark:bg-slate-900/40 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-xl transition-all duration-200">
                <h2 class="text-sm font-black uppercase tracking-wider mb-4 flex items-center text-blue-600 dark:text-blue-400">
                    <i class="fa-solid fa-chart-pie mr-2 text-base"></i> Statistiche Traffico
                </h2>
                
                <div class="space-y-6">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">Origine Transiti (Ultimi Log)</span>
                        </div>
                        <div class="relative w-full h-48 flex items-center justify-center">
                            <canvas id="trafficPieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 dark:bg-slate-900/40 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-xl transition-all duration-200">
                <h2 class="text-sm font-black uppercase tracking-wider mb-4 flex items-center text-blue-600 dark:text-blue-400">
                    <i class="fa-solid fa-server mr-2 text-base"></i> Stato Sistema
                </h2>
                <div class="space-y-4 text-sm">
                    <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800/60 pb-3">
                        <span class="text-slate-500 dark:text-slate-400 flex items-center gap-2 text-xs"><i class="fa-regular fa-clock text-blue-500"></i> Uptime Sistema:</span>
                        <span class="font-mono font-bold text-slate-700 dark:text-slate-200 text-xs bg-slate-100 dark:bg-slate-800/80 px-2.5 py-1 rounded-md border border-slate-200 dark:border-slate-700/50"><?php echo htmlspecialchars($systemInfo['uptime']); ?></span>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800/60 pb-3">
                        <span class="text-slate-500 dark:text-slate-400 flex items-center gap-2 text-xs"><i class="fa-solid fa-microchip text-blue-500"></i> Carico CPU (Load):</span>
                        <span class="font-mono font-bold text-slate-700 dark:text-slate-200 text-xs bg-slate-100 dark:bg-slate-800/80 px-2.5 py-1 rounded-md border border-slate-200 dark:border-slate-700/50"><?php echo htmlspecialchars($systemInfo['load'][0] . ', ' . $systemInfo['load'][1] . ', ' .$systemInfo['load'][2]); ?></span>
                    </div>
                    <div class="pt-1">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-slate-500 dark:text-slate-400 flex items-center gap-2 text-xs"><i class="fa-solid fa-hard-drive text-blue-500"></i> Spazio Disco:</span>
                            <span class="font-mono font-bold text-slate-700 dark:text-slate-200 text-xs"><?php echo htmlspecialchars($diskInfo['total']); ?> GB <span class="text-blue-500 font-extrabold">(<?php echo $diskInfo['percent']; ?>%)</span></span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden border border-slate-200 dark:border-slate-700/50 p-0.5">
                            <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full transition-all duration-500 shadow-sm" style="width: <?php echo $diskInfo['percent']; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <footer class="bg-white/60 dark:bg-slate-900/40 backdrop-blur-2xl border-t border-slate-200/80 dark:border-slate-800/80 py-6 px-4 text-center text-xs text-slate-500 dark:text-slate-400 transition-colors duration-200 relative z-10">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
            <p class="font-medium">YSFReflector Dashboard &bull; Infrastruttura multi-protocollo</p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                <a href="mailto:<?php echo htmlspecialchars($sysopEmail); ?>" class="flex items-center space-x-2 text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition">
                    <i class="fa-solid fa-envelope text-blue-500"></i>
                    <span>Sysop: <?php echo htmlspecialchars($sysopEmail); ?></span>
                </a>
                <div class="flex items-center space-x-1.5 text-slate-600 dark:text-slate-300 font-mono bg-slate-100 dark:bg-slate-800/80 px-2.5 py-1 rounded-md border border-slate-200 dark:border-slate-700/50">
                    <i class="fa-solid fa-code-version text-blue-500"></i>
                    <span>v<?php echo htmlspecialchars($reflectorVersion); ?></span>
                </div>
            </div>
        </div>
    </footer>

    <script>
        const themeIcon = document.getElementById('theme-icon');

        function updateThemeIcon() {
            if (document.documentElement.classList.contains('dark')) {
                themeIcon.className = "fa-solid fa-sun text-sm text-yellow-400";
            } else {
                themeIcon.className = "fa-solid fa-moon text-sm text-slate-700";
            }
        }

        updateThemeIcon();

        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
            updateThemeIcon();
            updateChartTheme();
        }

        let trafficPieChart;

        function initCharts() {
            const pieCtx = document.getElementById('trafficPieChart').getContext('2d');

            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#94a3b8' : '#64748b';

            trafficPieChart = new Chart(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Bridge IZ1MLF-YSF', 'Bridge XLX595', 'Diretti YSF'],
                    datasets: [{
                        data: [<?php echo $stats['bridgeCount']; ?>, <?php echo $stats['xlxCount']; ?>, <?php echo $stats['directCount']; ?>],
                        backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: textColor, font: { size: 11, family: 'monospace' } }
                        }
                    }
                }
            });
        }

        function updateChartTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#94a3b8' : '#64748b';

            if (trafficPieChart) {
                trafficPieChart.options.plugins.legend.labels.color = textColor;
                trafficPieChart.update();
            }
        }

        let gatewayConnectionTimes = {};

        function formatTimestamp(date) {
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            return `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;
        }

        function updateGatewayTimesDOM() {
            document.querySelectorAll('.gateway-card').forEach(card => {
                const key = card.getAttribute('data-gw-key');
                if (key) {
                    if (!gatewayConnectionTimes[key]) {
                        gatewayConnectionTimes[key] = new Date();
                    }
                    const timeSpan = card.querySelector('.gateway-time');
                    if (timeSpan) {
                        timeSpan.innerText = formatTimestamp(gatewayConnectionTimes[key]);
                    }
                }
            });
        }

        document.addEventListener("DOMContentLoaded", function() {
            initCharts();
            updateGatewayTimesDOM();
        });

        function filterLastHeard() {
            const callsignInput = document.getElementById('lh-search');
            const gatewayInput = document.getElementById('gw-search');
            
            const callsignFilter = callsignInput ? callsignInput.value.toUpperCase() : '';
            const gatewayFilter = gatewayInput ? gatewayInput.value.toUpperCase() : '';
            
            const tbody = document.getElementById('last-heard-tbody');
            const rows = tbody.getElementsByClassName('lh-row');

            for (let i = 0; i < rows.length; i++) {
                const callsignCell = rows[i].getElementsByClassName('callsign-cell')[0];
                const gatewayCell = rows[i].getElementsByClassName('gateway-cell')[0];
                
                if (callsignCell && gatewayCell) {
                    const callsignText = callsignCell.textContent || callsignCell.innerText;
                    const gatewayText = gatewayCell.textContent || gatewayCell.innerText;
                    
                    const matchesCallsign = callsignText.toUpperCase().indexOf(callsignFilter) > -1;
                    const matchesGateway = gatewayText.toUpperCase().indexOf(gatewayFilter) > -1;

                    if (matchesCallsign && matchesGateway) {
                        rows[i].style.display = "";
                    } else {
                        rows[i].style.display = "none";
                    }
                }
            }
        }

        let knownGateways = <?php echo json_encode($initialGateways); ?>;
        let isFirstLoad = true;

        knownGateways.forEach(gw => {
            if (!gatewayConnectionTimes[gw]) {
                gatewayConnectionTimes[gw] = new Date();
            }
        });

        let activeTxSource = "<?php echo isset($currentlyTXing['source']) ? $currentlyTXing['source'] : ''; ?>";
        let txStartTime = activeTxSource ? Date.now() : null;

        setInterval(function() {
            if (activeTxSource && txStartTime) {
                const elapsed = Math.floor((Date.now() - txStartTime) / 1000);
                const timerSpan = document.getElementById('tx-timer');
                if (timerSpan) {
                    timerSpan.innerText = elapsed;
                }
            }
        }, 1000);

        function showNotification(itemIdentifier, type) {
            const callsign = itemIdentifier.split('@')[0];
            const aprsUrl = `https://aprs.fi/#!z=11&call=a%2F${encodeURIComponent(callsign)}&timerange=3600&tail=3600`;
            
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-center space-x-3 px-4 py-3 rounded-2xl shadow-xl border text-sm font-semibold backdrop-blur-2xl transition-all duration-300 transform translate-y-0 opacity-100 ${
                type === 'connect' 
                    ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-800 dark:text-emerald-300' 
                    : 'bg-rose-500/10 border-rose-500/40 text-rose-800 dark:text-rose-300'
            }`;

            const iconClass = type === 'connect' ? 'fa-solid fa-circle-check text-emerald-500 text-base' : 'fa-solid fa-circle-exclamation text-rose-500 text-base';
            const message = type === 'connect' 
                ? `Gateway Connesso: <a href="${aprsUrl}" target="_blank" class="font-mono ml-1 underline hover:text-blue-500">${callsign}</a>` 
                : `Gateway Disconnesso: <a href="${aprsUrl}" target="_blank" class="font-mono ml-1 underline hover:text-blue-500">${callsign}</a>`;

            toast.innerHTML = `<i class="${iconClass}"></i><div>${message}</div>`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-x-4');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        setInterval(function() {
            fetch('index.php?ajax=1')
                .then(response => response.json())
                .then(data => {
                    if (data.gatewaysCount !== undefined) {
                        document.getElementById('stat-gateways').innerText = data.gatewaysCount;
                    }
                    if (data.uniqueCallsCount !== undefined) {
                        document.getElementById('stat-unique-calls').innerText = data.uniqueCallsCount;
                    }
                    if (data.qsoCount !== undefined) {
                        document.getElementById('stat-qso-count').innerText = data.qsoCount;
                    }
                    if (data.lastUpdate !== undefined) {
                        document.getElementById('stat-last-update').innerText = data.lastUpdate;
                    }

                    document.getElementById('all-transit-callsigns').innerHTML = data.transitCallsignsBox;
                    document.getElementById('last-heard-tbody').innerHTML = data.lastHeardTable;
                    
                    const callsignSearch = document.getElementById('lh-search');
                    const gatewaySearch = document.getElementById('gw-search');
                    if ((callsignSearch && callsignSearch.value) || (gatewaySearch && gatewaySearch.value)) {
                        filterLastHeard();
                    }

                    const newGateways = data.gatewaysList || [];

                    Object.keys(gatewayConnectionTimes).forEach(gwKey => {
                        if (!newGateways.includes(gwKey)) {
                            delete gatewayConnectionTimes[gwKey];
                        }
                    });

                    newGateways.forEach(gwKey => {
                        if (!gatewayConnectionTimes[gwKey]) {
                            gatewayConnectionTimes[gwKey] = new Date();
                        }
                    });

                    document.getElementById('gateways-container').innerHTML = data.gatewaysBox;
                    updateGatewayTimesDOM();
                    document.getElementById('gateways-count').innerText = data.gatewaysCount;

                    if (data.stats && trafficPieChart) {
                        trafficPieChart.data.datasets[0].data = [data.stats.bridgeCount, data.stats.xlxCount, data.stats.directCount];
                        trafficPieChart.update();
                    }

                    const txContainer = document.getElementById('live-tx-container');
                    const txData = data.txData;

                    if (txData) {
                        const newSource = txData.source;
                        const newSourceClean = newSource.trim();
                        const qrzUrl = `https://www.qrz.com/db/${encodeURIComponent(newSourceClean)}`;
                        
                        if (activeTxSource !== newSource) {
                            activeTxSource = newSource;
                            txStartTime = Date.now();
                            
                            txContainer.innerHTML = `
                                <div class="bg-gradient-to-r from-blue-500/15 via-indigo-500/5 to-transparent border border-blue-500/30 rounded-2xl p-5 flex justify-between items-center shadow-inner relative overflow-hidden">
                                    <div class="absolute right-0 top-0 translate-x-4 -translate-y-4 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl pointer-events-none"></div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                                            <p class="text-[11px] text-red-500 uppercase font-black tracking-widest">Trasmissione in Corso</p>
                                        </div>
                                        <p id="tx-source" class="text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-1.5">
                                            <a href="${qrzUrl}" target="_blank" class="hover:text-blue-500 dark:hover:text-blue-400 transition-colors inline-flex items-center gap-2" title="Cerca su QRZ.com">
                                                ${newSource}
                                                <i class="fa-solid fa-arrow-up-right-from-square text-xs opacity-50"></i>
                                            </a>
                                        </p>
                                        <p id="tx-details" class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-1">Verso: ${txData.target} | Gateway: ${txData.gateway}</p>
                                        <div class="inline-flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400 font-mono mt-3 bg-blue-500/10 px-2.5 py-1 rounded-lg border border-blue-500/20">
                                            <i class="fa-regular fa-clock"></i> Durata: <span id="tx-timer">0</span>s
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-3">
                                        <span class="inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider bg-red-500 text-white shadow-lg shadow-red-500/30 animate-pulse">
                                            LIVE TX
                                        </span>
                                        <div class="flex items-end gap-0.5 h-5">
                                            <span class="w-1 bg-red-500 rounded-full animate-bounce" style="height: 60%; animation-delay: 0.1s;"></span>
                                            <span class="w-1 bg-red-500 rounded-full animate-bounce" style="height: 100%; animation-delay: 0.3s;"></span>
                                            <span class="w-1 bg-red-500 rounded-full animate-bounce" style="height: 40%; animation-delay: 0.2s;"></span>
                                            <span class="w-1 bg-red-500 rounded-full animate-bounce" style="height: 80%; animation-delay: 0.4s;"></span>
                                        </div>
                                    </div>
                                </div>`;
                        } else {
                            const detailsEl = document.getElementById('tx-details');
                            if (detailsEl) {
                                detailsEl.innerText = `Verso: ${txData.target} | Gateway: ${txData.gateway}`;
                            }
                        }
                    } else {
                        if (activeTxSource !== "") {
                            activeTxSource = "";
                            txStartTime = null;
                            txContainer.innerHTML = `
                                <div id="tx-inactive" class="text-slate-500 dark:text-slate-400 text-sm py-8 text-center bg-slate-50/80 dark:bg-slate-900/30 rounded-2xl border border-slate-200/50 dark:border-slate-800/60 italic">
                                    Nessuna trasmissione in corso al momento.
                                </div>`;
                        }
                    }

                    if (!isFirstLoad) {
                        newGateways.forEach(gw => {
                            if (!knownGateways.includes(gw)) {
                                showNotification(gw, 'connect');
                            }
                        });

                        knownGateways.forEach(gw => {
                            if (!newGateways.includes(gw)) {
                                showNotification(gw, 'disconnect');
                            }
                        });
                    }

                    knownGateways = newGateways;
                    isFirstLoad = false;
                })
                .catch(error => console.error('Errore durante l\'aggiornamento:', error));
        }, 1000);
    </script>
</body>
</html>