<?php
namespace AporteWeb\Dashboard\Libs;

class Watchdog {
    use WatchdogTrait;

    public function getInstance()
    {
        return auth()->user();
    }

}
