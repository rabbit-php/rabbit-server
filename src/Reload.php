<?php
declare(strict_types=1);

namespace rabbit\server;

use rabbit\App;
use rabbit\core\Timer;
use rabbit\helper\FileHelper;
use Swoole\Table;

/**
 * Class Reload
 * @package rabbit\server
 */
class Reload implements WorkerHandlerInterface
{
    /** @var string */
    protected $path;
    /** @var array */
    protected $ext = [];
    /** @var bool */
    protected $isInotify = true;

    /**
     * Reload constructor.
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * @param int $worker_id
     */
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
                Timer::tick(1000, function () {
                    $this->runComparison();
                });
                App::info("server hot reload start : use timer tick comparison");
            }
        }
    }

    /**
     * 扫描文件变更
     */
    private function runComparison()
    {
        $startTime = microtime(true);
        $doReload = false;

        $dirIterator = new \RecursiveDirectoryIterator($this->monitorDir);
        $iterator = new \RecursiveIteratorIterator($dirIterator);
        $inodeList = array();

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $ext = $file->getExtension();
            if ($this->ext && !in_array($ext, $this->ext)) {
                continue;
            } else {
                $inode = $file->getInode();
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
            if (!$this->isReady == false) {
                App::info("severReload at {$time} use : {$usage} s total: {$count} files");
                App::getServer()->getSwooleServer()->reload();
            } else {
                App::info("hot reload ready at {$time} use : {$usage} s total: {$count} files");
                $this->isReady = true;
            }
        }
    }

    private function registerInotifyEvent()
    {
        $lastReloadTime = 0;

        $inotifyResource = inotify_init();

        FileHelper::findFiles($this->path, [
            'filter' => function ($path) use ($inotifyResource) {
                inotify_add_watch($inotifyResource, $path, IN_CREATE | IN_DELETE | IN_MODIFY);
                return true;
            }
        ]);

        // 加入事件循环
        swoole_event_add($inotifyResource, function () use (&$lastReloadTime, $inotifyResource) {
            $events = inotify_read($inotifyResource);
            if ($lastReloadTime < time() && !empty($events)) {
                $lastReloadTime = time();
                App::getServer()->getSwooleServer()->reload();
            }
        });
    }
}