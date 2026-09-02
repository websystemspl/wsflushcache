<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminWsFlushCacheController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->ajax = true;

        parent::__construct();
    }

    public function postProcess()
    {
        if (Tools::getValue('action') !== 'flush') {
            return parent::postProcess();
        }

        $parts = [];
        $ok = true;

        // 1. Cache obiektowy - ten, ktory trzymal stara cene mimo swiezych danych w bazie.
        try {
            if (defined('_PS_CACHING_SYSTEM_') && class_exists(_PS_CACHING_SYSTEM_)) {
                Cache::getInstance()->flush();
                $parts[] = 'obiektowy (' . _PS_CACHING_SYSTEM_ . ')';
            } else {
                $parts[] = 'obiektowy: brak skonfigurowanego systemu';
            }
        } catch (Exception $e) {
            $ok = false;
            $parts[] = 'obiektowy BLAD: ' . $e->getMessage();
        }

        // 2. Smarty, media, index modulow.
        try {
            Tools::clearSmartyCache();
            Tools::clearXMLCache();
            Media::clearCache();
            Tools::generateIndex();
            $parts[] = 'Smarty/media/index';
        } catch (Exception $e) {
            $ok = false;
            $parts[] = 'Smarty BLAD: ' . $e->getMessage();
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $ok,
            'message' => ($ok ? 'Wyczyszczono: ' : 'Czesciowo: ') . implode(', ', $parts),
        ]);
        exit;
    }
}
