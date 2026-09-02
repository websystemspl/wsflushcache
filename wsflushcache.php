<?php
/**
 * Szybkie czyszczenie cache obiektowego (Memcached/APC/Fs) z poziomu strony Wydajność.
 * Web Systems
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class WsFlushCache extends Module
{
    public function __construct()
    {
        $this->name = 'wsflushcache';
        $this->tab = 'administration';
        $this->version = '1.0.1';
        $this->author = 'Web Systems';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = 'Szybkie czyszczenie cache';
        $this->description = 'Dodaje przycisk czyszczenia cache obiektowego (Memcached/APC) nad sekcja Cache na stronie Zaawansowane > Wydajnosc.';
    }

    public function install()
    {
        return parent::install()
            && $this->installTab()
            && $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        return $this->uninstallTab() && parent::uninstall();
    }

    /**
     * Wpis w ps_tab jest wymagany, zeby PrestaShop wygenerowal token i sprawdzil
     * uprawnienia dla kontrolera ajax. id_parent = -1 trzyma go poza menu -
     * nigdzie w interfejsie sie nie pokazuje.
     */
    private function installTab()
    {
        $tab = new Tab();
        $tab->class_name = 'AdminWsFlushCache';
        $tab->module = $this->name;
        $tab->active = true;
        $tab->id_parent = -1;
        $tab->name = [];

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = 'Flush cache';
        }

        return (bool) $tab->add();
    }

    private function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminWsFlushCache');

        if ($idTab) {
            $tab = new Tab($idTab);

            return (bool) $tab->delete();
        }

        return true;
    }

    public function hookDisplayBackOfficeHeader()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        $isPerformance = (strpos($uri, '/configure/advanced/performance') !== false)
            || (Tools::getValue('controller') === 'AdminPerformance');

        if (!$isPerformance) {
            return '';
        }

        $url = $this->context->link->getAdminLink(
            'AdminWsFlushCache',
            true,
            [],
            ['action' => 'flush', 'ajax' => 1]
        );

        $urlJs = json_encode($url);

        return <<<HTML
<script type="text/javascript">
(function () {
    var endpoint = {$urlJs};

    // Tekst naglowka bez ikon - w PS 8 ikona materialowa wnosi wlasny tekst ("link").
    function headerText(header) {
        var copy = header.cloneNode(true);
        var icons = copy.querySelectorAll('i, svg, .material-icons');

        for (var i = 0; i < icons.length; i++) {
            icons[i].parentNode.removeChild(icons[i]);
        }

        return (copy.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function findCacheSection() {
        var headers = document.querySelectorAll('.card-header, .panel-heading');

        for (var i = 0; i < headers.length; i++) {
            if (headerText(headers[i]) === 'cache') {
                return headers[i].closest('.card, .panel');
            }
        }

        return null;
    }

    function buildBox() {
        var box = document.createElement('div');
        box.className = 'alert alert-info';
        box.style.margin = '0 0 16px 0';
        box.innerHTML =
            '<strong>Szybkie czyszczenie cache</strong>'
            + '<button type="button" id="ws-flush-cache" class="btn btn-primary" style="margin-left:12px">'
            + 'Wyczysc cache obiektowy</button>'
            + '<span id="ws-flush-cache-msg" style="margin-left:12px"></span>';

        return box;
    }

    function init() {
        var box = buildBox();
        var section = findCacheSection();

        if (section && section.parentNode) {
            section.parentNode.insertBefore(box, section);
        } else {
            var host = document.querySelector('#content')
                    || document.querySelector('.content-div')
                    || document.body;
            host.insertBefore(box, host.firstChild);
        }

        document.getElementById('ws-flush-cache').addEventListener('click', function () {
            var btn = this;
            var msg = document.getElementById('ws-flush-cache-msg');

            btn.disabled = true;
            msg.textContent = 'Czyszcze...';

            fetch(endpoint, {credentials: 'same-origin'})
                .then(function (r) { return r.json(); })
                .then(function (d) { msg.textContent = d.message || (d.success ? 'OK' : 'Blad'); })
                .catch(function (e) { msg.textContent = 'Blad: ' + e; })
                .then(function () { btn.disabled = false; });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
HTML;
    }
}
