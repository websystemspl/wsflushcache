<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Celowo AdminController, nie ModuleAdminController.
 * Ten drugi w konstruktorze rozwiazuje modul przez Module::getInstanceByName()
 * i rzuca "Module wsflushcache not found" zanim dojdzie do postProcess().
 * Do endpointu ajax rozwiazywanie modulu nie jest do niczego potrzebne -
 * token i uprawnienia i tak obsluguje wpis w ps_tab.
 */
class AdminWsFlushCacheController extends AdminController
{
    /** @var string[] */
    private $done = [];

    /** @var string[] */
    private $failed = [];

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

        // Cache obiektowy - ten, ktory trzymal stara cene mimo swiezych danych w bazie.
        $this->step('cache obiektowy', function () {
            if (!defined('_PS_CACHING_SYSTEM_')) {
                return 'brak skonfigurowanego systemu';
            }

            $system = _PS_CACHING_SYSTEM_;

            if (!class_exists($system)) {
                return 'klasa ' . $system . ' niedostepna';
            }

            Cache::getInstance()->flush();

            return $system;
        });

        $this->step('Smarty', function () {
            Tools::clearSmartyCache();
        });

        $this->step('XML', function () {
            if (method_exists('Tools', 'clearXMLCache')) {
                Tools::clearXMLCache();
            }
        });

        $this->step('media', function () {
            if (method_exists('Media', 'clearCache')) {
                Media::clearCache();
            }
        });

        $this->step('index modulow', function () {
            if (method_exists('Tools', 'generateIndex')) {
                Tools::generateIndex();
            }
        });

        $this->respond();
    }

    /**
     * Kazdy krok osobno - awaria jednego nie przerywa pozostalych.
     * Lapiemy Throwable, nie Exception, zeby bledy typu "call to undefined method"
     * wracaly jako czytelny JSON zamiast strony 500.
     */
    private function step($label, callable $action)
    {
        try {
            $detail = $action();
            $this->done[] = $detail ? $label . ' (' . $detail . ')' : $label;
        } catch (Throwable $e) {
            $this->failed[] = $label . ': ' . $e->getMessage()
                . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']';
        }
    }

    private function respond()
    {
        $ok = empty($this->failed);

        $message = $ok
            ? 'Wyczyszczono: ' . implode(', ', $this->done)
            : 'Bledy: ' . implode(' | ', $this->failed)
                . ($this->done ? ' -- udalo sie: ' . implode(', ', $this->done) : '');

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode(['success' => $ok, 'message' => $message]);
        exit;
    }
}
