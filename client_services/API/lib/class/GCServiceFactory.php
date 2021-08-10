<?php
require_once __DIR__ . '/../vendor/autoload.php';
putenv('GOOGLE_APPLICATION_CREDENTIALS='.__DIR__.'/../adc.json');


abstract class GCServiceFactory {
    const PROJECT = 'utopia-193108';
    const DOMAIN = 'utopialive.hu';

    public $projectPublicName = 'Utopia Streaming ecoSystem v0.1';

    protected $availableZones = ['europe-west2-a', 'europe-west2-b', 'europe-west2-c'];
    protected $selectedZone = null;
    protected $service = null;

    private $client = null;

    function __construct() {
        $this->client = new Google_Client();
        $this->client->setApplicationName($this->projectPublicName);
        $this->client->useApplicationDefaultCredentials();
        $this->client->addScope('https://www.googleapis.com/auth/cloud-platform');
        $this->service = new Google_Service_Compute($this->client);
    }

    public function setZone($zoneName = null) {
        if (!in_array($zoneName, $this->availableZones)) return false;
        $this->selectedZone = $zoneName;
    }

    public function getZones() {
        return $this->availableZones;
    }

    public function getZone() {
        return $this->selectedZone;
    }

    public static function dump($str = '') {
        echo '<pre>'.print_r($str , true) . '</pre>';
    }
}
