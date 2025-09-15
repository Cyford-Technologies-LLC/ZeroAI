<?php
namespace ZeroAI\Core;

class System {
    public static function getSystemInfo() {
        return [
            'cpu_usage' => self::getCpuUsage(),
            'memory_usage' => self::getMemoryUsage(),
            'disk_usage' => self::getDiskUsage(),
            'uptime' => self::getUptime(),
            'load_average' => self::getLoadAverage()
        ];
    }
    
    private static function getCpuUsage() {
        $load = sys_getloadavg();
        return round($load[0] * 100 / 4, 2); // Assume 4 cores
    }
    
    private static function getMemoryUsage() {
        $free = shell_exec('free -m');
        $free = (string)trim($free);
        $free_arr = explode("\n", $free);
        $mem = explode(" ", $free_arr[1]);
        $mem = array_filter($mem);
        $mem = array_merge($mem);
        return [
            'total' => $mem[1],
            'used' => $mem[2],
            'free' => $mem[3],
            'percentage' => round(($mem[2] / $mem[1]) * 100, 2)
        ];
    }
    
    private static function getDiskUsage() {
        $bytes = disk_free_space("/");
        $total = disk_total_space("/");
        $used = $total - $bytes;
        return [
            'total' => round($total / 1024 / 1024 / 1024, 2),
            'used' => round($used / 1024 / 1024 / 1024, 2),
            'free' => round($bytes / 1024 / 1024 / 1024, 2),
            'percentage' => round(($used / $total) * 100, 2)
        ];
    }
    
    private static function getUptime() {
        $uptime = file_get_contents('/proc/uptime');
        $uptime = explode(' ', $uptime);
        return round($uptime[0] / 3600, 2); // Hours
    }
    
    private static function getLoadAverage() {
        return sys_getloadavg();
    }
}
