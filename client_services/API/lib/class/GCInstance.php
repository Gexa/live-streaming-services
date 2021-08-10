<?php

require __DIR__.'/GCServiceFactory.php';

class GCInstance extends GCServiceFactory {

    private $instanceTemplate = null;

    function __construct($selectedZone = null) {
        if (!$selectedZone) {
            if (!$selectedZone)$this->setZone($this->availableZones[0]);
        } elseif (in_array($selectedZone, $this->availableZones)) {
            $this->setZone($selectedZone);
        }
        parent::__construct();
    }


    public function setInstanceTemplate($templateName = null) {
        if (!$templateName || strlen($templateName) < 2) return false;
        $this->instanceTemplate = str_replace('{PROJECTNAME}', self::PROJECT, 'https://www.googleapis.com/compute/v1/projects/{PROJECTNAME}/global/instanceTemplates/').$templateName;
    }

    public function getInstanceTemplate() {
        if (!$this->instanceTemplate)return false;
        $tplSplit = explode('/', $this->instanceTemplate);
        return $tplSplit[count($tplSplit)-1];
    }

    public function list() {
        try {
            $instanceList = $this->service->instances->listInstances(self::PROJECT, $this->selectedZone);
            if (!isset($instanceList->items) || count($instanceList->items)==0) return [];
            return $instanceList->items;
        } catch (Google_Service_Exception $e) {
            return [];
        }
    }

    public function get($instanceName = null) {
        if (!$instanceName || strlen($instanceName) < 1)return false;
        try {
            return $this->service->instances->get(self::PROJECT, $this->selectedZone, $instanceName);
        } catch (Google_Service_Exception $e) {
            return [];
        }
    }

    public function create() {
        $requestBody = new Google_Service_Compute_Instance();

        $instanceList = $this->list();
        $newInstanceNum = 1;
        if (is_array($instanceList) || count($instanceList) > 0) {
            $newInstanceNum = count($instanceList) + 1;
        }

        $requestBody->name = ($this->getInstanceTemplate().'-'.$newInstanceNum);
        $requestBody->hostname = ($this->getInstanceTemplate().'-'.$newInstanceNum.'.'.self::DOMAIN);

        try {
            return $this->service->instances->insert(self::PROJECT, $this->selectedZone, $requestBody, [
                'sourceInstanceTemplate' => $this->instanceTemplate
            ]);
        } catch (Google_Service_Exception $e) {
            return ['error' => var_export($e, true)];
        }

        return false;
    }

    public function start($instanceName = null) {
        if (!$instanceName || strlen($instanceName) < 1)return false;
        try {
            return $this->service->instances->start(self::PROJECT, $this->selectedZone, $instanceName);
        } catch (Google_Service_Exception $e) {
            return ['error' => var_export($e, true)];
        }
        return false;
    }

    public function reset($instanceName = null) {
        if (!$instanceName || strlen($instanceName) < 1)return false;
        try {
            return $this->service->instances->reset(self::PROJECT, $this->selectedZone, $instanceName);
        } catch (Google_Service_Exception $e) {
            return ['error' => var_export($e, true)];
        }
        return false;
    }

    public function stop($instanceName = null) {
        if (!$instanceName || strlen($instanceName) < 1)return false;
        try {
            return $this->service->instances->stop(self::PROJECT, $this->selectedZone, $instanceName);
        } catch (Google_Service_Exception $e) {
            return ['error' => var_export($e, true)];
        }
        return false;
    }

    public function delete($instanceName = null) {
        if (!$instanceName || strlen($instanceName) < 1)return false;
        try {
            return $this->service->instances->delete(self::PROJECT, $this->selectedZone, $instanceName);
        } catch (Google_Service_Exception $e) {
            return ['error' => var_export($e, true)];
        }
        return false;
    }
}