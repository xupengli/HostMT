#!/usr/bin/env php
<?php
/**
 * ===============================
 * 更改hosts内容,方便切换各种环境的配置
 * ===============================
 * 将mhosts命令拷贝到/usr/bin目录下
 * =============help 协议=================================
 * mhosts -a  添加mhosts内容
 * mhosts -l  展示所有存在的hosts环境
 * mhosts -s local 切换到local环境的hosts
 * mhosts -v local 查看local环境的配置hosts
 * mhosts -e local 更改local环境的数据
 * mhosts -r        还原初始化前的hosts数据
 * mhosts --current 查看当前所有激活的配置
 * mhosts --version 版本
 * =================================================
 * ==============脚本思路================================
 * 首先是~/.mhosts/mhosts.json是否存在,不存在创建,并且备份当前/etc/hosts内容到mhosts.json中
 * ======================================================
 */
class MHosts
{
    public $configPath;
    public $configName;
    public $allHosts;

    public $orders = [
        "-a" => "addHosts",
        "-l" => "listHosts",
        "-s" => "switchHosts",
        "-v" => "viewHosts",
        "-e" => "editHosts",
        "-r" => "restore",
        "-h" => "help",
        "--current" => "current",
        "--version" => "version"
    ];

    public function __construct()
    {
        $this->setConfigPath(getenv("HOME") . DIRECTORY_SEPARATOR . ".mhosts" . DIRECTORY_SEPARATOR);
        $this->setConfigName("mhosts.json");

        $this->initHosts();
    }

    /**
     * @param $argv
     * @return mixed
     */
    public function run($argv)
    {
        if (empty($argv)) {
            $this->help();
        }

        $params = $this->resolveOrder($argv);
        if (empty($params)) {
            $this->echoTips("argv is invalid");
            $this->help();
        }

        return call_user_func_array([$this, $this->orders[$params[0]]], $params[1]);
    }

    /**
     * 解析传入的命令参数,第一个参数是命令,其余的是环境参数
     * resolve the argv to an array, the first element is order, others are env params
     * @param $argv
     * @return array
     */
    private function resolveOrder($argv)
    {
        $order = null;
        $index = 0;
        foreach($argv as $key => $param) {
            if (in_array($param, array_keys($this->orders))) {
                $order = $param;
                $index = $key;
            }
        }

        if ($order == null) {
            return [];
        }
        unset($argv[$index]);
        return [$order, $argv];
    }

    /**
     * @param $hostName
     */
    private function addHosts($hostName)
    {
        if (isset($this->allHosts[$hostName])) {
            $this->echoTips("named {$hostName} config has exist");
        }

        $this->allHosts[$hostName] = new Host();

        $this->sysOrderViHosts($hostName);

        $this->writeConfig();
    }

    /**
     * 生成临时文件,
     * 调用系统命令vi编辑该文件,保存文件后读取该文件内容,写入allhosts变量中,进行保存
     * ====================================================================
     * create a temp file, exec system order `vi` to edit the temp file,
     * read and save the content when edit status of the temp file has been exited
     * @param $hostName
     */
    private function editHosts($hostName)
    {
        if (! isset($this->allHosts[$hostName])) {
            $this->echoTips("named {$hostName} config not found");
        }

        $this->sysOrderViHosts($hostName);

        $this->writeConfig();
    }

    /**
     * use system order edit temp file
     * @param $hostName
     */
    private function sysOrderViHosts($hostName)
    {
        $filename = getenv('TMPDIR') . $hostName;

        if (file_exists($filename)) {
            unlink($filename);
        }

        touch($filename);

        $content = $this->allHosts[$hostName]->content ? : "";

        file_put_contents($filename, $content);

        passthru("vi " . $filename . " >/dev/tty", $result_var);

        $this->allHosts[$hostName]->content = file_get_contents($filename, "r");

        unlink($filename);
    }

    /**
     * switch hosts
     */
    private function switchHosts()
    {
        $argv = func_get_args();
        $diff = array_diff($argv, array_keys($this->allHosts));

        if (! empty($diff)) {
            $this->echoTips(implode(',', $diff) . " are not exist");
        }

        //switch hosts
        system("sudo chmod 0777 /etc/hosts");
        system("echo \"\" > /etc/hosts");
        foreach ($argv as $value) {
            $cmd = "echo \"" . $this->allHosts[$value]->content . "\" >>/etc/hosts";
            system($cmd);
        }
        system("sudo chmod 0644 /etc/hosts");

        //active key tag
        foreach($this->allHosts as $key => $value) {
            if (in_array($key, $argv)) {
                $value->enabled = true;
                continue;
            }
            $value->enabled = false;
        }

        $this->writeConfig();
    }

    /**
     * 还原系统备份数据
     * restore the default hosts info
     */
    private function restore()
    {
        $this->switchHosts("backup");
    }

    /**
     * watch hosts detail
     */
    private function viewHosts()
    {
        $args = func_get_args();

        foreach ($args as $value) {
            echo "====={$value}=================" . PHP_EOL;
            if (! isset($this->allHosts[$value])) {
                echo "named {$value} is no exist! " . PHP_EOL;
            } else {
                echo $this->allHosts[$value]->content . PHP_EOL;
            }
        }
    }

    /**
     *
     */
    private function listHosts()
    {
        if (! empty($this->allHosts)) {
            echo implode("\n", array_keys($this->allHosts)) . "\n";
        }
    }

    private function current()
    {
        $result = [];
        foreach ($this->allHosts as $key => $value) {
            if (true == $value->enabled) {
                array_push($result, $key . "  √");
            }
        }

        $this->echoTips("the follow list show all active env! " . PHP_EOL . implode("\n", $result));
    }

    /**
     * document for help
     */
    private function help()
    {
        $msg = <<<help

mhosts is a tool of hosts for mac/linux, it dependent php

-l                              list of all hosts
-a [local]                      add a hosts
-s [local, local1, local2...]   switch local env hosts
-v [local]                      view local env hosts
-e [local]                      edit local env hosts data
-r                              restore the default hosts
--current                       list of the active hosts
--version                       the version
help;
;
        $this->echoTips($msg);
    }

    private function version()
    {
        $msg = <<<version
version 1.0
version;
        $this->echoTips($msg);
    }

    private function setConfigPath($path)
    {
        $this->configPath = $path;
        if (!file_exists($path)) {
            mkdir($path, 0700, true);
        }
    }

    private function setConfigName($name)
    {
        $this->configName = $this->configPath . $name;

        if (!file_exists($this->configName)) {
            touch($this->configName);
        }
    }

    private function initHosts()
    {
        $hosts = [];

        $this->allHosts = $this->readConfig();
        if (empty($this->allHosts)) {
            $this->allHosts['backup'] = new Host(file_get_contents("/etc/hosts"), true);
            $this->writeConfig();
        }
    }

    private function readConfig()
    {
        $result = [];
        $content = file_get_contents($this->configName, "r");
        if (! empty($content)) {
            $temp = json_decode($content, true);
            foreach($temp as $key => $value) {
                $result[$key] = new Host($value["content"], $value["enabled"]);
            }
        }
        return $result;
    }

    /**
     *
     * @return int
     */
    private function writeConfig()
    {
        return file_put_contents($this->configName, json_encode($this->allHosts));
    }

    private function echoTips($msg)
    {
        echo $msg . PHP_EOL;
        exit();
    }
}

class Host
{
    public $content;
    public $enabled;

    public function __construct($content = "", $enabled = false)
    {
        $this->content = $content;
        $this->enabled = $enabled;
    }
}


$mhost = new MHosts();
unset($argv[0]);
$mhost->run($argv);