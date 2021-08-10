<?php
defined('ENTRYPOINT') or die('Access denied!');

class DBMethods {

    /* TODO: outsource to config */
    const HOST = '127.0.0.1';
    const USER = 'c0utopia_hu';
    const PWD = '9!UDr9uz';
    const DBNAME = 'c0utopia_hu';

    private $db = null;

    function __construct() {
        $this->db = new mysqli(self::HOST, self::USER, self::PWD, self::DBNAME);
        $chrset = 'SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;';
        $this->db->query($chrset);
    }

    function __destruct() {
        $this->db->close();
    }

    private function query($sql = null) {
        if (!$sql) return false;
        $result = [];
        if ($stmt = $this->db->query($sql)) {
            $obj = mysqli_fetch_object($stmt, 'stdClass');
            while ($obj) {
                $result[] = $obj;
                $obj = mysqli_fetch_object($stmt, 'stdClass');
            }
            mysqli_free_result($stmt);
        }
        return !count($result) ? null : $result;
    }

    private function escape($str) {
        return '\''.mysqli_real_escape_string($this->db, $str).'\'';
    }

    public function list($dataArr = null) {
        $streamServers = $this->query('SELECT * FROM stream_server WHERE 1');
        $streamServers = $this->syncData($dataArr, is_object($streamServers) ? [$streamServers] : $streamServers );
        return $streamServers;
    }

    private function syncData($dataArr, $streamServers) {
        $existingServers = [];
        if (count($dataArr) < count($streamServers)) {
            foreach ($dataArr AS $server) {
                $existingServers[] = $server->name;
            }
            if (count($existingServers) > 0) {
                $this->query('DELETE FROM `stream_server` WHERE `server_name` NOT IN (\''.implode("','", $existingServers).'\');');
                foreach ($streamServers AS $k => $ss) {
                    if (!in_array($ss->server_name, $existingServers)) {
                        unset($streamServers[$k]);
                    }
                }
            } else {
                $this->query('DELETE FROM `stream_server` WHERE 1');
                $streamServers = [];
            }
        } else if (count($dataArr) > count($streamServers)) {
            foreach ($dataArr AS $server) {
                $this->create($server);
                $streamServers[] = $this->get($server);
            }
        }
        return $streamServers;
    }

    public function get($data = null) {
        if (!$data || empty($data))return false;
        return $this->query('SELECT * FROM `stream_server` WHERE server_name LIKE '.$this->escape($data->name));
    }

    public function create($data = null) {
        if (!$data || empty($data))return false;
        return $this->query("INSERT INTO `stream_server` (`server_name`, `server_unique_id`, `online`, `hostname`) VALUES (".$this->escape($data->name).", ".$this->escape($data->id).", 1, ".$this->escape($data->hostname).");");
    }
    public function delete($data = null) {
        if (!$data || empty($data))return false;
        return $this->query('DELETE FROM `stream_server` WHERE `server_unique_id` = '.$this->escape($data->targetId));
    }

    public function start($data = null) {
        if (!$data || empty($data))return false;
        return $this->query('UPDATE `stream_server` SET `online`=1 WHERE `server_unique_id` = '.$this->escape($data->targetId));
    }
    public function reset($data = null) {
        if (!$data || empty($data))return false;
        return [ 'restart' => 1 ];
    }
    public function stop($data = null) {
        if (!$data || empty($data))return false;
        $q = 'UPDATE `stream_server` SET `online`=0 WHERE `server_unique_id` = '.$this->escape($data->targetId);
        return $this->query($q);
    }

    /// MAPPING FUNCTIONS
    public function getStreamers($server_id = null) {
        if (!$server_id) return false;
        return $this->query('SELECT ss.*, sm.*, u.username, u.email, u.lastname, u.firstname, u.image FROM `stream_server` ss JOIN `stream_mapping` sm ON sm.server_id=ss.id JOIN `users` u ON streamer_id = u.id WHERE `server_name` LIKE BINARY '.$this->escape($server_id).';');
    }
}