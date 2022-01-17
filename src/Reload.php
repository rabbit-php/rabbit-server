<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\App;
use Rabbit\Base\Core\Timer;
use Rabbit\Base\Helper\FileHelper;
use Swoole\Table;

/**
 * Class Reload
 * @package Rabbit\Server
 */
class Reload implements WorkerHandlerInterface
{
    protected array $ext = [];

    protected Table $table;

    public function __construct(protected string $path)
    {
    }

    public function handle(int $worker_id): void
    {
        if ($worker_id === 0) {
            if (extension_loaded('inotify') && empty($arg['disableInotify'])) {
                // 扩展可用 优先使用扩展进行处理
                $this->registerInotifyEvent();
                App::info("server hot reload start : use inotify");
            } else {
                // 扩展不可用时 进行暴力扫描
                $this->table = new Table(512);
                $this->table->column('mtime', Table::TYPE_INT, 4);
                $this->table->create();
                $this->runComparison();
                Timer::addTickTimer(1000, function () {
                    $this->runComparison();
                }, 'reload');
                App::info("server hot reload start : use timer tick comparison");
            }
        }
    }

    private function runComparison()
    {
        $startTime = microtime(true);
        $doReload = false;

        $dirIterator = new \RecursiveDirectoryIterator($this->path);
        $iterator = new \RecursiveIteratorIterator($dirIterator);
        $inodeList = array();

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $ext = $file->getExtension();
            if ($this->ext && !in_array($ext, $this->ext)) {
                continue;
            } else {
                $inode = (string)$file->getInode();
                $mtime = $file->getMTime();
                array_push($inodeList, $inode);
                if (!$this->table->exist($inode)) {
                    $this->table->set($inode, ['mtime' => $mtime]);
                    $doReload = true;
                } else {
                    $oldTime = $this->table->get($inode)['mtime'];
                    if ($oldTime != $mtime) {
                        $this->table->set($inode, ['mtime' => $mtime]);
                        $doReload = true;
                    }
                }
            }
        }

        foreach ($this->table as $inode => $value) {
            // 迭代table寻找需要删除的inode
            if (!in_array(intval($inode), $inodeList)) {
                $this->table->del($inode);
                $doReload = true;
            }
        }

        if ($doReload) {
            $count = $this->table->count();
            $time = date('Y-m-d H:i:s');
            $usage = round(microtime(true) - $startTime, 3);
            App::info("severReload at {$time} use : {$usage} s total: {$count} files");
            ServerHelper::getServer()->getSwooleServer()->reload();
        }
    }

    private function registerInotifyEvent()
    {
        $lastReloadTime = 0;

        $inotifyResource = inotify_init();

        FileHelper::dealFiles($this->path, [
            'filter' => function (string $path) use ($inotifyResource) {
                inotify_add_watch($inotifyResource, $path, IN_CREATE | IN_DELETE | IN_MODIFY);
                return true;
            }
        ]);

        // 加入事件循环
        swoole_event_add($inotifyResource, function () use (&$lastReloadTime, $inotifyResource) {
            $events = inotify_read($inotifyResource);
            if ($lastReloadTime < time() && !empty($events)) {
                $lastReloadTime = time();
                ServerHelper::getServer()->getSwooleServer()->reload();
            }
        });
    }
}
