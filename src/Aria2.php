<?php

namespace ibibicloud;

use Exception;

class Aria2
{
    // 核心配置
    private $host       = '127.0.0.1';
    private $port       = 6800;
    private $rpcPath    = '/jsonrpc';
    public $rpcSecret   = '';

    // 本地程序路径
    private $aria2Dir;
    private $exePath    = 'aria2c.exe';
    private $confPath   = 'aria2.conf';

    // 下载路径配置
    private $saveDir    = '';

    /**
     * 统一返回格式
     */
    private function res($success, $message = '', $data = []) {
        return [
            'success' => $success,
            'message' => $message,
            'data'    => $data
        ];
    }

    /**
     * 初始化：自动启动 Aria2
     */
    public function __construct($aria2Dir = null) {
        $this->aria2Dir = $aria2Dir ? rtrim($aria2Dir, DIRECTORY_SEPARATOR) : __DIR__ . DIRECTORY_SEPARATOR . 'aria2';
        $this->saveDir  = $this->aria2Dir . DIRECTORY_SEPARATOR . '下载目录';

        if ( !is_dir($this->saveDir) ) {
            mkdir($this->saveDir, 0777, true);
        }

        $this->autoStartAria2();
    }

    /**
     * 自动检测并启动 Aria2
     */
    private function autoStartAria2() {
        if ( !$this->isRunning() ) {
            $this->start();
            usleep(500000);
        }
    }

    /**
     * 检测端口是否监听（判断是否运行）
     */
    public function isRunning() {
        $fp = @fsockopen($this->host, $this->port, $errno, $errstr, 0.1);
        if ( $fp ) {
            fclose($fp);
            return true;
        }
        return false;
    }

    /**
     * 启动 Aria2
     */
    public function start() {
        if ( $this->isRunning() ) {
            return $this->res('success', '✅ Aria2 已在运行');
        }

        $exeFullPath = $this->aria2Dir . DIRECTORY_SEPARATOR . $this->exePath;
        if ( !file_exists($exeFullPath) ) {
            return $this->res('fail', '❌ 未找到 aria2c.exe 程序');
        }

        $dir  = escapeshellarg($this->aria2Dir);
        $conf = escapeshellarg($this->confPath);
        $command = "cd /d {$dir} && start /b \"\" \"{$this->exePath}\" --conf-path={$conf} >nul 2>&1";
        
        pclose(popen($command, 'r'));

        return $this->res('success', '✅ Aria2 启动成功');
    }

    /**
     * 关闭 Aria2
     */
    public function stop() {
        if ( !$this->isRunning() ) {
            return $this->res('success', '✅ Aria2 未运行');
        }

        try {
            $this->rpcCall('aria2.shutdown');
            return $this->res('success', '✅ Aria2 已安全关闭');
        } catch ( Exception $e ) {
            return $this->res('fail', '❌ 关闭失败：' . $e->getMessage());
        }
    }

    /**
     * 添加下载任务
     */
    public function addDownload($url, $saveName = '', $saveDir = null) {
        if ( !filter_var($url, FILTER_VALIDATE_URL) ) {
            return $this->res('fail', '❌ 下载链接不合法');
        }

        $options = [
            'dir' => $saveDir ?: $this->saveDir
        ];

        if ( $saveName ) {
            $options['out'] = $saveName;
        }

        try {
            $gid = $this->rpcCall('aria2.addUri', [[$url], $options]);
            return $this->res('success', '✅ 已添加下载任务', ['gid' => $gid]);
        } catch ( Exception $e ) {
            return $this->res('fail', '❌ 添加下载失败：' . $e->getMessage());
        }
    }

    /**
     * 获取单个任务状态
     */
    public function getTaskStatus($gid) {
        try {
            $status = $this->rpcCall('aria2.tellStatus', [$gid]);
            $data   = $this->formatStatus($status);
            return $this->res('success', '✅ 获取任务状态成功', $data);
        } catch ( Exception $e ) {
            return $this->res('fail', '❌ 获取任务状态失败：' . $e->getMessage());
        }
    }

    /**
     * 获取所有任务
     */
    public function getAllTasks() {
        try {
            $active  = $this->rpcCall('aria2.tellActive');
            $waiting = $this->rpcCall('aria2.tellWaiting', [0, 100]);
            $stopped = $this->rpcCall('aria2.tellStopped', [0, 100]);

            $list = [];
            foreach ( array_merge($active, $waiting, $stopped) as $item ) {
                $list[] = $this->formatStatus($item);
            }

            return $this->res('success', '✅ 获取所有任务成功', $list);
        } catch ( Exception $e ) {
            return $this->res('fail', '❌ 获取任务列表失败：' . $e->getMessage());
        }
    }

    /**
     * RPC 请求核心
     */
    private function rpcCall($method, $params = []) {
        $rpcUrl = "http://{$this->host}:{$this->port}{$this->rpcPath}";
        $fullParams = [];

        if ( !empty($this->rpcSecret) ) {
            $fullParams[] = "token:{$this->rpcSecret}";
        }

        $fullParams = array_merge($fullParams, $params);

        $data = [
            'jsonrpc' => '2.0',
            'id'      => uniqid(),
            'method'  => $method,
            'params'  => $fullParams
        ];

        $ch = curl_init($rpcUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_FOLLOWLOCATION => 1
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ( $curlError ) {
            throw new Exception("RPC 请求失败：{$curlError}");
        }

        $result = json_decode($response, true);

        if ( isset($result['error']) ) {
            throw new Exception($result['error']['message']);
        }

        return $result['result'] ?? null;
    }

    /**
     * 格式化任务信息
     */
    private function formatStatus($status) {
        $total     = (int)($status['totalLength'] ?? 0);
        $completed = (int)($status['completedLength'] ?? 0);
        $speed     = (int)($status['downloadSpeed'] ?? 0);

        return [
            'gid'            => $status['gid'] ?? '',
            'filename'       => $this->getFileName($status),
            'status'         => $this->getStatusText($status['status'] ?? ''),
            'total_size'     => $this->formatSize($total),
            'completed_size' => $this->formatSize($completed),
            'progress'       => ( $total > 0 ) ? round($completed / $total * 100, 2) : 0,
            'speed'          => $this->formatSize($speed) . '/s',
            'raw_data'       => $status
        ];
    }

    /**
     * 获取文件名
     */
    private function getFileName($status) {
        if ( !empty($status['files'][0]['path']) ) {
            return basename($status['files'][0]['path']);
        }
        if ( !empty($status['bittorrent']['info']['name']) ) {
            return $status['bittorrent']['info']['name'];
        }
        return '未知文件';
    }

    /**
     * 状态文字转换
     */
    private function getStatusText($s) {
        $map = [
            'active'   => '下载中',
            'waiting'  => '等待中',
            'paused'   => '已暂停',
            'complete' => '已完成',
            'error'    => '错误',
            'removed'  => '已删除'
        ];
        return $map[$s] ?? $s;
    }

    /**
     * 文件大小格式化
     */
    private function formatSize($bytes) {
        $bytes = (int)$bytes;
        if ( $bytes === 0 ) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, 3);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}