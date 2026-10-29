<?php
/**
 * YSFReflector-Dashboard2 - Functions
 * Modern dashboard for YSFReflector & pYSFReflector
 */

date_default_timezone_set('Europe/Rome');

function getYSFReflectorConfig() {
    $configFile = "/etc/YSFReflector.ini";
    if (file_exists("/etc/YSFReflector/YSFReflector.ini")) {
        $configFile = "/etc/YSFReflector/YSFReflector.ini";
    }
    if (file_exists($configFile)) {
        $parsed = @parse_ini_file($configFile, true);
        if ($parsed !== false) {
            return $parsed;
        }
    }
    return array();
}

function getConfigItem($section, $key, $configs) {
    if (isset($configs[$section][$key])) {
        return $configs[$section][$key];
    }
    return "";
}

function getLogoPath() {
    $logoDir = "assets/images/";
    $extensions = array('png', 'jpg', 'jpeg', 'gif', 'webp');
    foreach ($extensions as $ext) {
        if (file_exists($logoDir . "logo." . $ext)) {
            return $logoDir . "logo." . $ext;
        }
    }
    return false;
}

function getYSFReflectorLog() {
    $logPath = "/opt/pYSFReflector/";
    $files = glob($logPath . "*.log");
    if (!empty($files)) {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $logFile = $files[0];
        $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false && !empty($lines)) {
            return $lines;
        }
    }
    return array();
}

function getLastHeard($logLines) {
    $lastHeard = array();
    foreach ($logLines as $line) {
        if (stripos($line, 'Received data from') !== false) {
            if (preg_match('/M:\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}).*?from\s+([A-Z0-9\-]+)\s+to\s+([A-Z0-9\-]+)\s+at\s+([A-Z0-9\-]+)/i', $line, $matches)) {
                // Converte l'orario UTC del log aggiungendo 2 ore per l'ora legale italiana
                $correctedTime = date('Y-m-d H:i:s', strtotime($matches[1] . ' +2 hours'));
                $lastHeard[] = array($correctedTime, $matches[2], $matches[3], $matches[4]);
            }
        }
    }
    return array_reverse(array_slice($lastHeard, -20));
}

function getCurrentlyTXing($logLines) {
    if (empty($logLines)) {
        return null;
    }

    $recentLines = array_slice($logLines, -10);
    $reverse = array_reverse($recentLines);

    foreach ($reverse as $line) {
        if (stripos($line, 'end of transmission') !== false || stripos($line, 'disconnect') !== false) {
            return null;
        }

        if (stripos($line, 'Received data from') !== false) {
            if (preg_match('/M:\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}).*?from\s+([A-Z0-9\-]+)\s+to\s+([A-Z0-9\-]+)\s+at\s+([A-Z0-9\-]+)/i', $line, $matches)) {
                $correctedTime = date('Y-m-d H:i:s', strtotime($matches[1] . ' +2 hours'));
                return array(
                    'timestamp' => $correctedTime,
                    'source' => $matches[2],
                    'target' => $matches[3],
                    'gateway' => $matches[4]
                );
            }
            break;
        }
    }
    
    return null;
}

function getLinkedGateways($logLines) {
    if (empty($logLines)) {
        return array();
    }

    $gateways = array();
    $foundBlock = false;
    $blockLines = array();
    $blockTimestamp = date('Y-m-d H:i:s');

    // Prima passata: cerchiamo l'ultimo blocco di gateway collegati e il suo orario nel log
    foreach ($logLines as $line) {
        if (stripos($line, 'Currently linked repeaters/gateways:') !== false) {
            $foundBlock = true;
            $blockLines = array();
            // Estrae l'orario della riga di log del blocco
            if (preg_match('/M:\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/i', $line, $timeMatches)) {
                $blockTimestamp = date('Y-m-d H:i:s', strtotime($timeMatches[1] . ' +2 hours'));
            }
            continue;
        }

        if ($foundBlock) {
            if (stripos($line, 'No repeaters/gateways muted') !== false || stripos($line, 'Muted repeaters') !== false || stripos($line, 'Muted repeaters/gateways') !== false) {
                $foundBlock = false;
                continue;
            }
            $blockLines[] = $line;
        }
    }

    // Se l'ultimo blocco trovato nei log è più vecchio di 3 minuti (180 secondi), lo scarta
    if (!empty($blockLines)) {
        $timeDifference = time() - strtotime($blockTimestamp);
        if ($timeDifference > 180) {
            return array();
        }

        $counter = 1;
        foreach ($blockLines as $line) {
            if (preg_match('/[\|\s]+([A-Z0-9\-]{3,15})\s*:\s*([0-9a-fA-F\.\:]+)/i', $line, $matches)) {
                $callsign = strtoupper(trim($matches[1]));
                $ipPort = trim($matches[2]);
                $ipParts = explode(':', $ipPort);

                $blacklist = array('CURRENTLY', 'NO', 'REPEATERS', 'GATEWAYS', 'MUTED', 'M');
                if (!in_array($callsign, $blacklist) && !is_numeric($callsign)) {
                    $uniqueKey = $callsign . '_' . $ipPort . '_' . $counter;
                    
                    if (!isset($gateways[$uniqueKey])) {
                        $gateways[$uniqueKey] = array(
                            'timestamp' => $blockTimestamp,
                            'callsign' => $callsign,
                            'ip' => isset($ipParts[0]) ? $ipParts[0] : 'N/A'
                        );
                        $counter++;
                    }
                }
            }
        }
    }

    return array_values($gateways);
}

function getSystemInfo() {
    $rawLoad = sys_getloadavg();
    $load = array(round($rawLoad[0], 2), round($rawLoad[1], 2), round($rawLoad[2], 2));
    $uptimeSeconds = @file_get_contents('/proc/uptime');
    $uptimeSeconds = $uptimeSeconds ? floatval(explode(' ', $uptimeSeconds)[0]) : 0;
    
    $days = (int)($uptimeSeconds / 86400);
    $hours = (int)(($uptimeSeconds / 3600) % 24);
    $mins = (int)(($uptimeSeconds / 60) % 60);

    $temp = "N/A";
    if (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
        $temp = round((float)intval(file_get_contents('/sys/class/thermal/thermal_zone0/temp')) / 1000, 1);
    }

    return array('load' => $load, 'uptime' => "{$days}d {$hours}h {$mins}m", 'temperature' => $temp);
}

function getDiskInfo() {
    $total = @disk_total_space("/");
    $free = @disk_free_space("/");
    $total = $total ? $total : 0;
    $free = $free ? $free : 0;
    $used = $total - $free;
    $percent = $total > 0 ? (int)round(($used / $total) * 100) : 0;
    return array('total' => round($total / 1073741824, 1), 'used' => round($used / 1073741824, 1), 'percent' => $percent);
}

function getYSFReflectorVersion() {
    return "YSFReflector v1.0";
}
?>